#!/usr/bin/env bash
set -Eeuo pipefail

# =====================================================================
# AUROXLINK - Instalador/Actualizador seguro de SvxLink oficial
# Version del instalador: 4.1
#
# Fuente oficial:
#   https://github.com/sm0svx/svxlink/releases
#
# OBJETIVOS DE SEGURIDAD
# ----------------------
# 1. Nunca mezcla binarios/plugins de dos versiones de SvxLink.
# 2. Nunca sobrescribe la configuración antes de probar la nueva release.
# 3. Prueba la nueva versión con la configuración REAL del nodo.
# 4. Solo cambia systemd si la prueba previa finaliza correctamente.
# 5. Si falla cualquier paso posterior, hace rollback automático.
# 6. Mantiene /etc/svxlink/svxlink.conf como configuración activa para
#    conservar compatibilidad con el panel web de AUROXLINK.
# 7. Conserva releases anteriores para rollback.
#
# ESTRUCTURA
# ----------
# /opt/auroxlink/svxlink/releases/<VERSION>/   release instalada
# /opt/auroxlink/svxlink/current -> release activa
# /etc/svxlink/svxlink.conf                    config activa AUROXLINK
# /etc/auroxlink/svxlink_version               version gestionada
#
# USO
# ----
# sudo bash install_svxlink_latest.sh --check
# sudo bash install_svxlink_latest.sh
# sudo bash install_svxlink_latest.sh --force
# =====================================================================

INSTALLER_VERSION="4.1"

REPO="sm0svx/svxlink"
API_URL="https://api.github.com/repos/${REPO}/releases/latest"

BASE_DIR="/opt/auroxlink/svxlink"
RELEASES_DIR="${BASE_DIR}/releases"
CURRENT_LINK="${BASE_DIR}/current"

WORK_ROOT="/usr/local/src/auroxlink-svxlink"
BACKUP_ROOT="/var/backups/auroxlink"

AUROXLINK_ETC="/etc/auroxlink"
VERSION_FILE="${AUROXLINK_ETC}/svxlink_version"

SVX_CONFIG="/etc/svxlink/svxlink.conf"

OVERRIDE_DIR="/etc/systemd/system/svxlink.service.d"
OVERRIDE_FILE="${OVERRIDE_DIR}/10-auroxlink-latest.conf"

MODE="${1:-}"

TEST_SECONDS=12

# Estado para rollback
BACKUP_DIR=""
SERVICE_WAS_ACTIVE=0
SERVICE_STOPPED=0
ACTIVATION_STARTED=0
ACTIVATION_SUCCESS=0

OLD_CURRENT_TARGET=""
OLD_OVERRIDE_EXISTED=0
OLD_CONFIG_BACKUP=""
OLD_VERSION_FILE_EXISTED=0

TEST_CONFIG=""
TEST_LOG=""

# ---------------------------------------------------------------------
# Utilidades
# ---------------------------------------------------------------------

log()  { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m[OK]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[WARN]\033[0m %s\n' "$*"; }
err()  { printf '\033[1;31m[ERROR]\033[0m %s\n' "$*" >&2; }
die()  { err "$*"; exit 1; }

cleanup_temp() {
  [[ -n "${TEST_CONFIG:-}" && -f "$TEST_CONFIG" ]] && rm -f "$TEST_CONFIG" || true
}

restore_old_state() {
  local reason="${1:-fallo no especificado}"

  trap - ERR INT TERM

  err "Se produjo un fallo: ${reason}"
  warn "Iniciando rollback automático..."

  # Detener cualquier intento de versión nueva.
  systemctl stop svxlink.service 2>/dev/null || true

  # Restaurar symlink current.
  if [[ -n "$OLD_CURRENT_TARGET" ]]; then
    mkdir -p "$BASE_DIR"
    ln -sfn "$OLD_CURRENT_TARGET" "$CURRENT_LINK"
    ok "Rollback: current -> $OLD_CURRENT_TARGET"
  else
    rm -f "$CURRENT_LINK"
  fi

  # Restaurar configuración activa.
  if [[ -n "$OLD_CONFIG_BACKUP" && -f "$OLD_CONFIG_BACKUP" ]]; then
    cp -a "$OLD_CONFIG_BACKUP" "$SVX_CONFIG"
    ok "Rollback: restaurado $SVX_CONFIG"
  fi

  # Restaurar override AUROXLINK.
  if [[ "$OLD_OVERRIDE_EXISTED" -eq 1 && -f "${BACKUP_DIR}/systemd-override.conf" ]]; then
    mkdir -p "$OVERRIDE_DIR"
    cp -a "${BACKUP_DIR}/systemd-override.conf" "$OVERRIDE_FILE"
    ok "Rollback: restaurado override systemd anterior"
  else
    rm -f "$OVERRIDE_FILE"
  fi

  # Restaurar archivo de versión.
  mkdir -p "$AUROXLINK_ETC"
  if [[ "$OLD_VERSION_FILE_EXISTED" -eq 1 && -f "${BACKUP_DIR}/svxlink_version" ]]; then
    cp -a "${BACKUP_DIR}/svxlink_version" "$VERSION_FILE"
  else
    rm -f "$VERSION_FILE"
  fi

  systemctl daemon-reload || true
  systemctl reset-failed svxlink.service 2>/dev/null || true

  if [[ "$SERVICE_WAS_ACTIVE" -eq 1 ]]; then
    if systemctl restart svxlink.service; then
      ok "Rollback completado: el servicio anterior volvió a quedar ACTIVO."
    else
      err "Rollback realizado, pero el servicio anterior no arrancó."
      systemctl --no-pager --full status svxlink.service || true
    fi
  else
    warn "El servicio estaba inactivo antes de comenzar; se dejó inactivo."
  fi

  cleanup_temp

  echo
  warn "Backup disponible en: ${BACKUP_DIR:-no creado}"
  exit 1
}

on_error() {
  local status=$?
  local line="${BASH_LINENO[0]:-?}"
  local cmd="${BASH_COMMAND:-?}"

  if [[ "$ACTIVATION_STARTED" -eq 1 && "$ACTIVATION_SUCCESS" -ne 1 ]]; then
    restore_old_state "comando '${cmd}' (línea ${line}, código ${status})"
  fi

  err "Fallo en línea ${line}: ${cmd} (código ${status})"
  cleanup_temp
  exit "$status"
}

trap on_error ERR
trap 'if [[ "$ACTIVATION_STARTED" -eq 1 && "$ACTIVATION_SUCCESS" -ne 1 ]]; then restore_old_state "interrupción"; else cleanup_temp; exit 130; fi' INT TERM
trap cleanup_temp EXIT

if [[ "$MODE" != "" && "$MODE" != "--check" && "$MODE" != "--force" ]]; then
  die "Opción inválida: $MODE. Usa --check, --force o ninguna opción."
fi

if [[ ${EUID} -ne 0 ]]; then
  die "Ejecuta este script con sudo o como root."
fi

command -v apt-get >/dev/null 2>&1 \
  || die "Este instalador requiere una distribución basada en Debian con APT."

command -v runuser >/dev/null 2>&1 \
  || die "No se encontró runuser (util-linux), necesario para la prueba segura como usuario svxlink."

[[ -r /etc/os-release ]] || die "No se encontró /etc/os-release."

# shellcheck disable=SC1091
. /etc/os-release

if [[ "${ID:-}" != "debian" && "${ID:-}" != "raspbian" ]]; then
  die "Sistema no soportado de forma automática: ID=${ID:-desconocido}. Esta versión se valida solo para Debian/Raspberry Pi OS."
fi

DEBIAN_MAJOR="${VERSION_ID%%.*}"
if [[ "$DEBIAN_MAJOR" != "12" && "$DEBIAN_MAJOR" != "13" ]]; then
  die "Versión no validada: ${PRETTY_NAME:-Linux}. Se permiten automáticamente Debian/Raspberry Pi OS 12 y 13."
fi

export DEBIAN_FRONTEND=noninteractive
export SYSTEMD_PAGER=cat

ARCH="$(dpkg --print-architecture 2>/dev/null || uname -m)"
OS_NAME="${PRETTY_NAME:-${ID:-Linux}}"

debian_pkg_version() {
  dpkg-query -W -f='${Version}\n' svxlink-server 2>/dev/null || true
}

managed_version() {
  if [[ -f "$VERSION_FILE" ]]; then
    tr -d '[:space:]' < "$VERSION_FILE"
  fi
}

active_exec() {
  systemctl show -p ExecStart --value svxlink.service 2>/dev/null || true
}

# ---------------------------------------------------------------------
# Consultar release oficial
# ---------------------------------------------------------------------

cat <<'BANNER'

    _   _   _ ____   _____  __ _     ___ _   _ _  __
   / \ | | | |  _ \ / _ \ \/ /| |   |_ _| \ | | |/ /
  / _ \| | | | |_) | | | \  / | |    | ||  \| | ' /
 / ___ \ |_| |  _ <| |_| /  \ | |___ | || |\  | . \
/_/   \_\___/|_| \_\\___/_/\_\|_____|___|_| \_|_|\_\

                  AUROXLINK
          Gestor seguro de actualización

              SvxLink by SM0SVX

------------------------------------------------------------
 AUROXLINK
   Instalación, validación y rollback automatizado.

 SvxLink
   Proyecto original : SM0SVX / Tobias Blomberg
   GitHub SM0SVX     : https://github.com/sm0svx
   SvxLink oficial   : https://github.com/sm0svx/svxlink

 AUROXLINK gestiona la instalación y actualización.
 SvxLink continúa siendo un proyecto independiente de SM0SVX.
------------------------------------------------------------

BANNER

log "AUROXLINK SvxLink Installer v${INSTALLER_VERSION}"
echo "Sistema       : $OS_NAME"
echo "Arquitectura  : $ARCH"

log "Consultando última release estable oficial de SvxLink"

apt-get update -y
apt-get install -y --no-install-recommends \
  curl ca-certificates jq

LATEST_JSON="$(
  curl -fsSL \
    --retry 3 \
    --retry-delay 2 \
    -H 'Accept: application/vnd.github+json' \
    -H 'User-Agent: AUROXLINK-SvxLink-Installer' \
    "$API_URL"
)" || die "No se pudo consultar GitHub."

LATEST_TAG="$(jq -r '.tag_name // empty' <<<"$LATEST_JSON")"
TARBALL_URL="$(jq -r '.tarball_url // empty' <<<"$LATEST_JSON")"
IS_DRAFT="$(jq -r '.draft // false' <<<"$LATEST_JSON")"
IS_PRERELEASE="$(jq -r '.prerelease // false' <<<"$LATEST_JSON")"

[[ -n "$LATEST_TAG" ]] || die "GitHub no devolvió tag_name."
[[ -n "$TARBALL_URL" ]] || die "GitHub no devolvió tarball_url."
[[ "$IS_DRAFT" == "false" ]] || die "La release detectada está en draft."
[[ "$IS_PRERELEASE" == "false" ]] || die "La release detectada es prerelease."

DEBIAN_VERSION="$(debian_pkg_version)"
MANAGED_VERSION="$(managed_version)"
ACTIVE_EXEC="$(active_exec)"

ok "Última release oficial estable: ${LATEST_TAG}"

echo "Paquete Debian : ${DEBIAN_VERSION:-no instalado}"
echo "AUROXLINK      : ${MANAGED_VERSION:-no instalado}"

if [[ -L "$CURRENT_LINK" ]]; then
  echo "Current        : $(readlink -f "$CURRENT_LINK")"
else
  echo "Current        : no configurado"
fi

if [[ "$MODE" == "--check" ]]; then
  echo
  echo "Release oficial : $LATEST_TAG"
  echo "Paquete APT      : ${DEBIAN_VERSION:-no instalado}"
  echo "Versión gestionada: ${MANAGED_VERSION:-no instalada}"

  if [[ "$ACTIVE_EXEC" == *"${CURRENT_LINK}/bin/svxlink"* \
        && "$MANAGED_VERSION" == "$LATEST_TAG" \
        && -x "${CURRENT_LINK}/bin/svxlink" \
        && "$(systemctl is-active svxlink.service 2>/dev/null || true)" == "active" ]]; then
    ok "La última release oficial ya está ACTIVA y funcionando."
  else
    warn "La última release oficial no está activa; corresponde instalar/reparar."
  fi
  exit 0
fi

if [[ "$MODE" != "--force" \
      && "$ACTIVE_EXEC" == *"${CURRENT_LINK}/bin/svxlink"* \
      && "$MANAGED_VERSION" == "$LATEST_TAG" \
      && -x "${CURRENT_LINK}/bin/svxlink" \
      && "$(systemctl is-active svxlink.service 2>/dev/null || true)" == "active" ]]; then
  ok "SvxLink ${LATEST_TAG} ya está instalado, activo y funcionando."
  exit 0
fi

# ---------------------------------------------------------------------
# Dependencias
# ---------------------------------------------------------------------

log "Instalando paquete base y dependencias de compilación"

# El paquete Debian es nuestro fallback. Si ya está instalado, NO lo
# actualizamos aquí: se conserva exactamente como estaba antes.
if ! dpkg-query -W -f='${Status}\n' svxlink-server 2>/dev/null | grep -q 'install ok installed'; then
  apt-get install -y --no-install-recommends svxlink-server
fi

apt-get install -y --no-install-recommends \
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
  libcurl4-openssl-dev \
  libogg-dev \
  ladspa-sdk \
  libopus-dev \
  opus-tools

[[ -f "$SVX_CONFIG" ]] \
  || die "No existe $SVX_CONFIG. Configura primero el paquete base svxlink-server."

if ! id svxlink >/dev/null 2>&1; then
  die "El paquete svxlink-server no creó el usuario svxlink."
fi

SVX_GROUP="$(id -gn svxlink 2>/dev/null || true)"
[[ -n "$SVX_GROUP" ]] || SVX_GROUP="svxlink"

# ---------------------------------------------------------------------
# Backup completo antes de tocar servicio/configuración
# ---------------------------------------------------------------------

log "Creando respaldo previo"

STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_DIR="${BACKUP_ROOT}/svxlink_${STAMP}"

mkdir -p "$BACKUP_DIR"

cp -a /etc/svxlink "${BACKUP_DIR}/etc-svxlink"
OLD_CONFIG_BACKUP="${BACKUP_DIR}/etc-svxlink/svxlink.conf"

if [[ -f /etc/default/svxlink ]]; then
  cp -a /etc/default/svxlink "${BACKUP_DIR}/default-svxlink"
fi

if [[ -f "$OVERRIDE_FILE" ]]; then
  cp -a "$OVERRIDE_FILE" "${BACKUP_DIR}/systemd-override.conf"
  OLD_OVERRIDE_EXISTED=1
fi

if [[ -f "$VERSION_FILE" ]]; then
  cp -a "$VERSION_FILE" "${BACKUP_DIR}/svxlink_version"
  OLD_VERSION_FILE_EXISTED=1
fi

if [[ -L "$CURRENT_LINK" ]]; then
  OLD_CURRENT_TARGET="$(readlink -f "$CURRENT_LINK")"
fi

if systemctl is-active --quiet svxlink.service; then
  SERVICE_WAS_ACTIVE=1
fi

ok "Backup: $BACKUP_DIR"

# ---------------------------------------------------------------------
# Descargar fuente oficial
# ---------------------------------------------------------------------

log "Descargando SvxLink ${LATEST_TAG}"

WORKDIR="${WORK_ROOT}/${LATEST_TAG}"
SOURCE_DIR="${WORKDIR}/source"
BUILD_DIR="${SOURCE_DIR}/src/build"
STAGE_DIR="${WORKDIR}/stage"

RELEASE_DIR="${RELEASES_DIR}/${LATEST_TAG}"
STAGED_RELEASE_DIR="${STAGE_DIR}${RELEASE_DIR}"

SVXLINK_GIT_URL="https://github.com/sm0svx/svxlink.git"

rm -rf "$WORKDIR"
mkdir -p "$STAGE_DIR"

log "Descarga pública vía Git del tag ${LATEST_TAG}"

CLONE_OK=0

for ATTEMPT in 1 2 3; do
  rm -rf "$SOURCE_DIR"

  echo "Intento Git ${ATTEMPT}/3..."

  if git \
      -c advice.detachedHead=false \
      clone \
      --depth 1 \
      --branch "$LATEST_TAG" \
      --single-branch \
      "$SVXLINK_GIT_URL" \
      "$SOURCE_DIR"
  then
    CLONE_OK=1
    break
  fi

  warn "No se pudo clonar SvxLink en el intento ${ATTEMPT}/3."

  if [[ "$ATTEMPT" -lt 3 ]]; then
    warn "Reintentando en 15 segundos..."
    sleep 15
  fi
done

[[ "$CLONE_OK" -eq 1 ]] \
  || die "No fue posible descargar SvxLink ${LATEST_TAG} mediante Git público."

[[ -d "${SOURCE_DIR}/src" ]] \
  || die "El código descargado no contiene src/."

CHECKOUT_TAG="$(
  git -C "$SOURCE_DIR" \
    describe \
    --tags \
    --exact-match HEAD \
    2>/dev/null || true
)"

[[ "$CHECKOUT_TAG" == "$LATEST_TAG" ]] \
  || die "El código descargado no corresponde al tag ${LATEST_TAG}. Detectado: ${CHECKOUT_TAG:-desconocido}"

SOURCE_COMMIT="$(
  git -C "$SOURCE_DIR" rev-parse --short=12 HEAD
)"

ok "SvxLink ${LATEST_TAG} descargado correctamente mediante Git."
ok "Commit fuente: ${SOURCE_COMMIT}"

[[ -f "${SOURCE_DIR}/INSTALL.adoc" ]] \
  || warn "No se encontró INSTALL.adoc en la release."


# ---------------------------------------------------------------------
# Compilación
# ---------------------------------------------------------------------

log "Configurando compilación de SvxLink ${LATEST_TAG}"

mkdir -p "$BUILD_DIR"
cd "$BUILD_DIR"

cmake \
  -DCMAKE_BUILD_TYPE=Release \
  -DCMAKE_INSTALL_PREFIX="$RELEASE_DIR" \
  -DSYSCONF_INSTALL_DIR=/etc \
  -DLOCAL_STATE_DIR=/var \
  -DUSE_QT=NO \
  ..

log "Compilando con $(nproc) hilo(s)"
make -j"$(nproc)"

# ---------------------------------------------------------------------
# Staging
# ---------------------------------------------------------------------

log "Instalando build en staging seguro"

DESTDIR="$STAGE_DIR" make install

[[ -x "${STAGED_RELEASE_DIR}/bin/svxlink" ]] \
  || die "El staging no generó ${STAGED_RELEASE_DIR}/bin/svxlink."

STAGE_PLUGIN_DIR="$(
  find "$STAGED_RELEASE_DIR" \
    -type f \
    -name 'SimplexLogic.so' \
    -printf '%h\n' \
    | head -n1
)"

STAGE_EVENT_HANDLER="$(
  find "$STAGED_RELEASE_DIR" \
    -type f \
    -path '*/share/svxlink/events.tcl' \
    -print \
    | head -n1
)"

[[ -n "$STAGE_PLUGIN_DIR" && -f "${STAGE_PLUGIN_DIR}/SimplexLogic.so" ]] \
  || die "No se encontró SimplexLogic.so del build nuevo."

[[ -n "$STAGE_EVENT_HANDLER" && -f "$STAGE_EVENT_HANDLER" ]] \
  || die "No se encontró events.tcl del build nuevo."

# Rutas definitivas equivalentes.
PLUGIN_DIR="${STAGE_PLUGIN_DIR#${STAGE_DIR}}"
EVENT_HANDLER="${STAGE_EVENT_HANDLER#${STAGE_DIR}}"

[[ "$PLUGIN_DIR" == "${RELEASE_DIR}"/* ]] \
  || die "Ruta de plugins inesperada: $PLUGIN_DIR"

[[ "$EVENT_HANDLER" == "${RELEASE_DIR}"/* ]] \
  || die "Ruta de eventos inesperada: $EVENT_HANDLER"

ok "Plugins nuevos : $PLUGIN_DIR"
ok "Eventos nuevos : $EVENT_HANDLER"

# ---------------------------------------------------------------------
# Función para generar config compatible sin tocar el original
# ---------------------------------------------------------------------

patch_config() {
  local src="$1"
  local dst="$2"
  local plugin_dir="$3"
  local event_handler="$4"

  python3 - "$src" "$dst" "$plugin_dir" "$event_handler" <<'PY'
from pathlib import Path
import re
import sys

src = Path(sys.argv[1])
dst = Path(sys.argv[2])
plugin_dir = sys.argv[3]
event_handler = sys.argv[4]

text = src.read_text(encoding="utf-8", errors="surrogateescape")
lines = text.splitlines()

out = []
in_global = False
global_found = False
module_written = False
logic_written = False
event_count = 0

def flush_global():
    global module_written, logic_written
    if not module_written:
        out.append(f"MODULE_PATH={plugin_dir}")
        module_written = True
    if not logic_written:
        out.append(f"LOGIC_CORE_PATH={plugin_dir}")
        logic_written = True

for line in lines:
    stripped = line.strip()

    if re.match(r'^\s*\[[^\]]+\]\s*$', line):
        if in_global:
            flush_global()

        section = stripped[1:-1].strip()
        in_global = section.upper() == "GLOBAL"

        if in_global:
            global_found = True
            module_written = False
            logic_written = False

        out.append(line)
        continue

    if in_global:
        if re.match(r'^\s*[#;]?\s*MODULE_PATH\s*=', line, re.I):
            if not module_written:
                out.append(f"MODULE_PATH={plugin_dir}")
                module_written = True
            continue

        if re.match(r'^\s*[#;]?\s*LOGIC_CORE_PATH\s*=', line, re.I):
            if not logic_written:
                out.append(f"LOGIC_CORE_PATH={plugin_dir}")
                logic_written = True
            continue

    # Solo reemplazar EVENT_HANDLER activo. SvxLink 26.x necesita usar
    # el árbol TCL de la misma release que el binario/plugins.
    if re.match(r'^\s*EVENT_HANDLER\s*=', line, re.I):
        prefix = line[:len(line)-len(line.lstrip())]
        out.append(f"{prefix}EVENT_HANDLER={event_handler}")
        event_count += 1
        continue

    out.append(line)

if in_global:
    flush_global()

if not global_found:
    raise SystemExit("ERROR: no existe la sección [GLOBAL] en svxlink.conf")

if event_count == 0:
    raise SystemExit("ERROR: no se encontró ningún EVENT_HANDLER activo en svxlink.conf")

dst.write_text("\n".join(out) + "\n", encoding="utf-8", errors="surrogateescape")
PY
}

# ---------------------------------------------------------------------
# Publicar release versionada (todavía NO activar)
# ---------------------------------------------------------------------

log "Publicando release versionada sin activar"

mkdir -p "$RELEASES_DIR"

if [[ -e "$RELEASE_DIR" && "$MODE" == "--force" ]]; then
  rm -rf "$RELEASE_DIR"
fi

if [[ ! -d "$RELEASE_DIR" ]]; then
  mkdir -p "$RELEASE_DIR"
  cp -a "${STAGED_RELEASE_DIR}/." "$RELEASE_DIR/"
fi

[[ -x "${RELEASE_DIR}/bin/svxlink" ]] \
  || die "No existe ${RELEASE_DIR}/bin/svxlink."

# Recalcular rutas de producción desde la release publicada.
PLUGIN_DIR="$(
  find "$RELEASE_DIR" \
    -type f \
    -name 'SimplexLogic.so' \
    -printf '%h\n' \
    | head -n1
)"

EVENT_HANDLER="$(
  find "$RELEASE_DIR" \
    -type f \
    -path '*/share/svxlink/events.tcl' \
    -print \
    | head -n1
)"

[[ -n "$PLUGIN_DIR" ]] || die "No se encontró plugin dir en release publicada."
[[ -n "$EVENT_HANDLER" ]] || die "No se encontró events.tcl en release publicada."

# ---------------------------------------------------------------------
# Config de prueba basada en la configuración REAL actual
# ---------------------------------------------------------------------

log "Preparando prueba previa de compatibilidad"

TEST_CONFIG="/etc/svxlink/.auroxlink-test-${LATEST_TAG}.conf"

TEST_LOG_DIR="/var/lib/auroxlink/svxlink-update"
TEST_LOG="${TEST_LOG_DIR}/test-${LATEST_TAG}.log"

mkdir -p "$TEST_LOG_DIR"

rm -f "$TEST_CONFIG" "$TEST_LOG"

patch_config \
  "$SVX_CONFIG" \
  "$TEST_CONFIG" \
  "$PLUGIN_DIR" \
  "$EVENT_HANDLER"

chown root:"$SVX_GROUP" "$TEST_CONFIG"
chmod 0640 "$TEST_CONFIG"

touch "$TEST_LOG"
chown svxlink:"$SVX_GROUP" "$TEST_LOG"
chmod 0644 "$TEST_LOG"

log "Deteniendo temporalmente el servicio actual para probar audio/configuración"

systemctl stop svxlink.service
SERVICE_STOPPED=1

# A partir de aquí, cualquier error debe recuperar el servicio anterior.
ACTIVATION_STARTED=1

LIB_PATH="${RELEASE_DIR}/lib:${RELEASE_DIR}/lib64"

if runuser -u svxlink -- \
  env LD_LIBRARY_PATH="$LIB_PATH" \
  timeout --signal=TERM "${TEST_SECONDS}s" \
  "${RELEASE_DIR}/bin/svxlink" \
  --config="$TEST_CONFIG" \
  --logfile="$TEST_LOG"
then
  TEST_RC=0
else
  TEST_RC=$?
fi

if [[ "$TEST_RC" -ne 124 ]]; then
  echo
  err "La nueva versión terminó antes de completar la prueba (${TEST_RC})."
  tail -n 100 "$TEST_LOG" || true
  restore_old_state "prueba previa de SvxLink ${LATEST_TAG} falló"
fi

if ! grep -q 'Initialization done. Starting main application' "$TEST_LOG"; then
  echo
  err "El proceso sobrevivió al timeout, pero no confirmó inicialización completa."
  tail -n 100 "$TEST_LOG" || true
  restore_old_state "no se confirmó la inicialización de SvxLink ${LATEST_TAG}"
fi

if grep -qE '/usr/lib/[^ ]*/svxlink/(SimplexLogic|Module[A-Za-z0-9_]+)\.so' "$TEST_LOG"; then
  echo
  err "Se detectó mezcla con plugins del paquete Debian."
  grep -E '/usr/lib/[^ ]*/svxlink/.*\.so' "$TEST_LOG" || true
  restore_old_state "mezcla de plugins entre versiones"
fi

if ! grep -q "${PLUGIN_DIR}/SimplexLogic.so" "$TEST_LOG"; then
  echo
  err "No se confirmó el uso del SimplexLogic.so de la release nueva."
  tail -n 100 "$TEST_LOG" || true
  restore_old_state "plugin de lógica incorrecto"
fi

ok "Prueba previa exitosa: SvxLink ${LATEST_TAG} funcionó ${TEST_SECONDS}s."
ok "No se detectó mezcla con plugins Debian."

# ---------------------------------------------------------------------
# Activación atómica
# ---------------------------------------------------------------------

log "Activando SvxLink ${LATEST_TAG}"

# 1) Crear configuración nueva en archivo temporal.
NEW_CONFIG_TMP="/etc/svxlink/.svxlink.conf.auroxlink-new"

# Ajustar las rutas usando exactamente la estructura detectada en el build.
PLUGIN_REL="${PLUGIN_DIR#${RELEASE_DIR}/}"
EVENT_REL="${EVENT_HANDLER#${RELEASE_DIR}/}"

patch_config \
  "$SVX_CONFIG" \
  "$NEW_CONFIG_TMP" \
  "${CURRENT_LINK}/${PLUGIN_REL}" \
  "${CURRENT_LINK}/${EVENT_REL}"

chown --reference="$SVX_CONFIG" "$NEW_CONFIG_TMP"
chmod --reference="$SVX_CONFIG" "$NEW_CONFIG_TMP"

# 2) Cambiar current a la release validada.
ln -sfn "$RELEASE_DIR" "$CURRENT_LINK"

# 3) Reemplazar configuración de forma atómica.
mv -f "$NEW_CONFIG_TMP" "$SVX_CONFIG"

# 4) Crear override systemd usando el servicio base de la distribución.
FRAGMENT_PATH="$(systemctl show -p FragmentPath --value svxlink.service)"

[[ -f "$FRAGMENT_PATH" ]] \
  || restore_old_state "no se encontró el archivo base de svxlink.service"

BASE_EXEC="$(
  awk '
    /^ExecStart=/ {
      sub(/^ExecStart=/, "")
      print
      exit
    }
  ' "$FRAGMENT_PATH"
)"

[[ -n "$BASE_EXEC" ]] \
  || restore_old_state "el servicio base no contiene ExecStart"

BASE_BIN="${BASE_EXEC%% *}"

if [[ "$(basename "$BASE_BIN")" != "svxlink" ]]; then
  restore_old_state "ExecStart base inesperado: $BASE_EXEC"
fi

NEW_EXEC="${CURRENT_LINK}/bin/svxlink${BASE_EXEC#"$BASE_BIN"}"

mkdir -p "$OVERRIDE_DIR"

cat > "$OVERRIDE_FILE" <<EOF
# Gestionado automáticamente por AUROXLINK
# SvxLink oficial: ${LATEST_TAG}
# NO editar manualmente.
[Service]
Environment="LD_LIBRARY_PATH=${CURRENT_LINK}/lib:${CURRENT_LINK}/lib64"
ExecStart=
ExecStart=${NEW_EXEC}
EOF

mkdir -p "$AUROXLINK_ETC"
printf '%s\n' "$LATEST_TAG" > "$VERSION_FILE"
chmod 0644 "$VERSION_FILE"

systemctl daemon-reload
systemctl reset-failed svxlink.service || true
systemctl enable svxlink.service

if ! systemctl restart svxlink.service; then
  restore_old_state "systemd no pudo iniciar SvxLink ${LATEST_TAG}"
fi

sleep 3

# ---------------------------------------------------------------------
# Validación posterior a activación
# ---------------------------------------------------------------------

log "Validando servicio activado"

if ! systemctl is-active --quiet svxlink.service; then
  systemctl --no-pager --full status svxlink.service || true
  restore_old_state "svxlink.service no quedó activo"
fi

SYSTEMD_EXEC="$(active_exec)"

if [[ "$SYSTEMD_EXEC" != *"${CURRENT_LINK}/bin/svxlink"* ]]; then
  restore_old_state "systemd no está ejecutando el binario gestionado por AUROXLINK"
fi

if ! kill -0 "$(systemctl show -p MainPID --value svxlink.service)" 2>/dev/null; then
  restore_old_state "MainPID de svxlink.service no está vivo"
fi

# Confirmar en el log principal que arrancó la versión nueva.
MAIN_LOG="$(
  systemctl show -p Environment --value svxlink.service 2>/dev/null \
    | grep -oE 'LOGFILE=[^ ]+' \
    | head -n1 \
    | cut -d= -f2- || true
)"

# Debian normalmente obtiene LOGFILE desde EnvironmentFile y systemctl show
# puede no mostrarlo. Se usa /var/log/svxlink como comprobación adicional.
if [[ -f /var/log/svxlink ]]; then
  if tail -n 120 /var/log/svxlink | grep -q "SvxLink .*@${LATEST_TAG}"; then
    ok "Log confirma SvxLink ${LATEST_TAG}."
  else
    warn "El servicio está activo pero no pude confirmar la versión por /var/log/svxlink."
  fi
fi

ACTIVATION_SUCCESS=1
SERVICE_STOPPED=0

cleanup_temp

# ---------------------------------------------------------------------
# Resultado
# ---------------------------------------------------------------------

echo
echo "============================================================"
echo " AUROXLINK - SvxLink actualizado correctamente"
echo "============================================================"
echo " Release oficial : ${LATEST_TAG}"
echo " Binario activo   : ${CURRENT_LINK}/bin/svxlink"
echo " Release física   : ${RELEASE_DIR}"
echo " Configuración    : ${SVX_CONFIG}"
echo " Plugins          : ${CURRENT_LINK}/${PLUGIN_REL}"
echo " Eventos TCL      : ${CURRENT_LINK}/${EVENT_REL}"
echo " Backup rollback  : ${BACKUP_DIR}"
echo " Paquete Debian   : $(debian_pkg_version)"
echo "============================================================"
echo

ok "svxlink.service está ACTIVO con la release oficial ${LATEST_TAG}."
ok "La configuración de AUROXLINK continúa en ${SVX_CONFIG}."
ok "Las releases anteriores se conservan para rollback."

exit 0
