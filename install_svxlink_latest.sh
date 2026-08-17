#!/usr/bin/env bash
set -Eeuo pipefail

# ============================================================
# AUROXLINK - Instalador/actualizador de SvxLink oficial
# Fuente: https://github.com/sm0svx/svxlink/releases
#
# Estrategia:
#   1) Mantiene svxlink-server de Debian como paquete base
#      (usuario, servicio, estructura de configuración).
#   2) Consulta la última release estable oficial de sm0svx/svxlink.
#   3) Compila e instala esa release en /usr/local.
#   4) Mantiene /etc/svxlink y /var como configuración/estado.
#   5) Hace que systemd ejecute /usr/local/bin/svxlink.
#
# Uso:
#   sudo bash install_svxlink_latest.sh
#   sudo bash install_svxlink_latest.sh --check
#   sudo bash install_svxlink_latest.sh --force
# ============================================================

REPO="sm0svx/svxlink"
API_URL="https://api.github.com/repos/${REPO}/releases/latest"
WORKDIR="/usr/local/src/auroxlink-svxlink"
BACKUP_ROOT="/var/backups/auroxlink"
OVERRIDE_DIR="/etc/systemd/system/svxlink.service.d"
OVERRIDE_FILE="${OVERRIDE_DIR}/10-auroxlink-latest.conf"
VERSION_FILE="/etc/auroxlink/svxlink_version"
MODE="${1:-}"

log()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m[OK]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[WARN]\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; exit 1; }

if [[ ${EUID} -ne 0 ]]; then
  die "Ejecuta este script con sudo o como root."
fi

command -v apt-get >/dev/null 2>&1 || die "Este instalador está diseñado para Debian/Raspberry Pi OS/Ubuntu."

export DEBIAN_FRONTEND=noninteractive

get_bin_version() {
  local bin="$1"
  [[ -x "$bin" ]] || return 0
  "$bin" --version 2>&1 | head -n1 || true
}

normalize_version() {
  # Extrae una versión tipo 26.05.1 desde cualquier texto.
  grep -oE '[0-9]{2}\.[0-9]{2}(\.[0-9]+)?' | head -n1 || true
}

log "Consultando última release estable oficial de SvxLink"
apt-get update -y
apt-get install -y --no-install-recommends curl ca-certificates jq

LATEST_JSON="$(curl -fsSL \
  -H 'Accept: application/vnd.github+json' \
  -H 'User-Agent: AUROXLINK-SvxLink-Installer' \
  "$API_URL")" || die "No se pudo consultar GitHub."

LATEST_TAG="$(jq -r '.tag_name // empty' <<<"$LATEST_JSON")"
TARBALL_URL="$(jq -r '.tarball_url // empty' <<<"$LATEST_JSON")"

[[ -n "$LATEST_TAG" ]] || die "GitHub no devolvió tag_name."
[[ -n "$TARBALL_URL" ]] || die "GitHub no devolvió tarball_url."

ok "Última release oficial: ${LATEST_TAG}"

CURRENT_LOCAL_RAW="$(get_bin_version /usr/local/bin/svxlink)"
CURRENT_SYSTEM_RAW="$(get_bin_version /usr/bin/svxlink)"
CURRENT_LOCAL="$(printf '%s' "$CURRENT_LOCAL_RAW" | normalize_version)"
CURRENT_SYSTEM="$(printf '%s' "$CURRENT_SYSTEM_RAW" | normalize_version)"

[[ -n "$CURRENT_SYSTEM_RAW" ]] && echo "Paquete Debian : $CURRENT_SYSTEM_RAW"
[[ -n "$CURRENT_LOCAL_RAW"  ]] && echo "Build oficial  : $CURRENT_LOCAL_RAW"

if [[ "$MODE" == "--check" ]]; then
  echo
  echo "Release oficial : $LATEST_TAG"
  echo "Versión /usr     : ${CURRENT_SYSTEM:-no detectada}"
  echo "Versión /usr/local: ${CURRENT_LOCAL:-no instalada}"
  if [[ "$CURRENT_LOCAL" == "$LATEST_TAG" ]]; then
    ok "Ya tienes instalada la última release oficial."
  else
    warn "Hay que instalar/actualizar a ${LATEST_TAG}."
  fi
  exit 0
fi

if [[ "$CURRENT_LOCAL" == "$LATEST_TAG" && "$MODE" != "--force" ]]; then
  ok "SvxLink ${LATEST_TAG} ya está instalado en /usr/local. No hay nada que hacer."
  exit 0
fi

log "Instalando paquete base y herramientas de compilación"
apt-get install -y \
  svxlink-server \
  build-essential \
  cmake \
  pkg-config \
  git \
  curl \
  ca-certificates \
  jq \
  groff \
  gzip \
  tar \
  alsa-utils \
  libsigc++-2.0-dev \
  libpopt-dev \
  tcl-dev \
  libgcrypt20-dev \
  libasound2-dev \
  libgsm1-dev \
  libjsoncpp-dev \
  libspeex-dev \
  librtlsdr-dev \
  libgpiod-dev \
  libssl-dev \
  libopus-dev \
  opus-tools

# El paquete base debe haber creado el usuario. Si no, lo creamos de forma segura.
if ! id svxlink >/dev/null 2>&1; then
  warn "El usuario svxlink no existe; se creará."
  useradd --system --home /var/lib/svxlink --shell /usr/sbin/nologin svxlink
fi

getent group daemon >/dev/null 2>&1 || groupadd --system daemon

log "Respaldando configuración actual"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_DIR="${BACKUP_ROOT}/svxlink_${STAMP}"
mkdir -p "$BACKUP_DIR"

if [[ -d /etc/svxlink ]]; then
  cp -a /etc/svxlink "$BACKUP_DIR/"
  ok "Respaldo: ${BACKUP_DIR}/svxlink"
fi

if [[ -f /etc/default/svxlink ]]; then
  cp -a /etc/default/svxlink "$BACKUP_DIR/"
fi

if [[ -f "$OVERRIDE_FILE" ]]; then
  mkdir -p "$BACKUP_DIR/systemd"
  cp -a "$OVERRIDE_FILE" "$BACKUP_DIR/systemd/"
fi

log "Descargando SvxLink ${LATEST_TAG} desde el repositorio oficial"
rm -rf "$WORKDIR"
mkdir -p "$WORKDIR"
ARCHIVE="${WORKDIR}/svxlink-${LATEST_TAG}.tar.gz"

curl -fL \
  -H 'Accept: application/vnd.github+json' \
  -H 'User-Agent: AUROXLINK-SvxLink-Installer' \
  "$TARBALL_URL" \
  -o "$ARCHIVE"

mkdir -p "${WORKDIR}/source"
tar -xzf "$ARCHIVE" -C "${WORKDIR}/source" --strip-components=1

[[ -d "${WORKDIR}/source/src" ]] || die "El código descargado no contiene src/."

log "Compilando SvxLink ${LATEST_TAG}"
BUILD_DIR="${WORKDIR}/source/src/build"
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
cd "$BUILD_DIR"

cmake \
  -DCMAKE_BUILD_TYPE=Release \
  -DCMAKE_INSTALL_PREFIX=/usr/local \
  -DSYSCONF_INSTALL_DIR=/etc \
  -DLOCAL_STATE_DIR=/var \
  -DUSE_QT=NO \
  ..

make -j"$(nproc)"

log "Deteniendo servicio antes de instalar"
systemctl stop svxlink.service 2>/dev/null || true

log "Instalando build oficial en /usr/local"
make install
ldconfig

[[ -x /usr/local/bin/svxlink ]] || die "No apareció /usr/local/bin/svxlink después de make install."

NEW_RAW="$(get_bin_version /usr/local/bin/svxlink)"
NEW_VERSION="$(printf '%s' "$NEW_RAW" | normalize_version)"
ok "Binario instalado: ${NEW_RAW:-/usr/local/bin/svxlink}"

if [[ -n "$NEW_VERSION" && "$NEW_VERSION" != "$LATEST_TAG" ]]; then
  warn "La versión detectada (${NEW_VERSION}) no coincide exactamente con el tag (${LATEST_TAG})."
fi

log "Configurando systemd para utilizar el build oficial"
BASE_EXEC="$(systemctl cat svxlink.service 2>/dev/null \
  | awk '/^ExecStart=/{sub(/^ExecStart=/,""); print; exit}')"

if [[ -z "$BASE_EXEC" ]]; then
  die "No pude determinar ExecStart del servicio svxlink."
fi

if [[ "$BASE_EXEC" != *"/usr/bin/svxlink"* && "$BASE_EXEC" != *"/usr/local/bin/svxlink"* ]]; then
  die "ExecStart inesperado: $BASE_EXEC"
fi

NEW_EXEC="${BASE_EXEC//\/usr\/bin\/svxlink/\/usr\/local\/bin\/svxlink}"

mkdir -p "$OVERRIDE_DIR"
cat > "$OVERRIDE_FILE" <<EOF
# Gestionado por AUROXLINK
# SvxLink oficial ${LATEST_TAG}
[Service]
ExecStart=
ExecStart=${NEW_EXEC}
EOF

mkdir -p /etc/auroxlink
printf '%s\n' "$LATEST_TAG" > "$VERSION_FILE"
chmod 0644 "$VERSION_FILE"

systemctl daemon-reload
systemctl enable svxlink.service
systemctl restart svxlink.service

sleep 2

log "Verificación final"
echo "Latest GitHub : ${LATEST_TAG}"
echo "Binario       : /usr/local/bin/svxlink"
echo "Versión       : $(get_bin_version /usr/local/bin/svxlink)"
echo "Override      : ${OVERRIDE_FILE}"
echo "Backup        : ${BACKUP_DIR}"
echo

if systemctl is-active --quiet svxlink.service; then
  ok "svxlink.service está ACTIVO usando el build oficial."
else
  warn "svxlink.service no quedó activo. Mostrando estado:"
  systemctl --no-pager --full status svxlink.service || true
  echo
  echo "Para revisar logs:"
  echo "  journalctl -u svxlink -n 100 --no-pager"
  exit 1
fi

ok "SvxLink ${LATEST_TAG} instalado/actualizado correctamente."
