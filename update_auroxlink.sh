#!/usr/bin/env bash
set -Eeuo pipefail

# ============================================================
# AUROXLINK - ACTUALIZADOR OFICIAL
# Base de migración: 1.8.5
#
# Objetivos:
#   - Actualizar el código desde la última release oficial GitHub.
#   - Preservar configuración y datos del usuario.
#   - Instalar/migrar todos los componentes requeridos por 1.8.5.
#   - NO reinstalar ni cambiar la versión de SvxLink.
#   - Ser idempotente: puede ejecutarse otra vez para reparar/migrar.
#
# IMPORTANTE PARA 1.7/1.8.4 -> 1.8.5:
# El actualizador antiguo puede copiar primero este nuevo actualizador.
# Si eso ocurre, una segunda ejecución completa automáticamente la
# migración 1.8.5 sin volver a descargar el código.
# ============================================================

APP_DIR="/var/www/html"
REPO_URL="https://github.com/telecov/auroxlink.git"
APT_TIMEOUT=180
TMP_ROOT="/tmp/auroxlink-update"
DOWNLOAD_ZIP="${TMP_ROOT}/auroxlink.zip"
EXTRACT_DIR="${TMP_ROOT}/extract"

SUDOERS_FILE="/etc/sudoers.d/99-www-data-svxlink"
SEISMIC_SUDOERS="/etc/sudoers.d/99-auroxlink-seismic"
CRON_FILE="/etc/cron.d/auroxlink"
LEGACY_SERVICE="/etc/systemd/system/auroralink-monitor.service"

LIBEXEC_DIR="/usr/local/libexec/auroxlink"
SVX_UPDATE_STATE="/var/lib/auroxlink/svxlink-update"
MIGRATIONS_DIR="/var/lib/auroxlink/migrations"
MIGRATION_185="${MIGRATIONS_DIR}/1.8.5.done"

SVXLINK_CONF="/etc/svxlink/svxlink.conf"
ECHOLINK_CONF="/etc/svxlink/svxlink.d/ModuleEchoLink.conf"
COMMAND_PTY="/dev/shm/simplex_logic_ctrl"

SEISMIC_CONFIG="${APP_DIR}/data/seismic_config.json"
SEISMIC_STATE="${APP_DIR}/data/seismic_state.json"
SEISMIC_MONITOR_DST="/opt/auroxlink/scripts/seismic-monitor.php"
SEISMIC_AUDIO_DIR="/var/lib/auroxlink/seismic"
SEISMIC_SERVICE="/etc/systemd/system/auroxlink-seismic.service"
SEISMIC_TIMER="/etc/systemd/system/auroxlink-seismic.timer"

PIPER_VENV="/opt/auroxlink/piper-venv"
PIPER_BIN="${PIPER_VENV}/bin/piper"
PIPER_VOICES="/opt/auroxlink/piper/voices"
PIPER_MODEL_NAME="es_ES-davefx-medium"
PIPER_MODEL="${PIPER_VOICES}/${PIPER_MODEL_NAME}.onnx"
PIPER_MODEL_JSON="${PIPER_MODEL}.json"
PIPER_MODEL_URL="https://huggingface.co/rhasspy/piper-voices/resolve/main/es/es_ES/davefx/medium/es_ES-davefx-medium.onnx"
PIPER_MODEL_JSON_URL="https://huggingface.co/rhasspy/piper-voices/resolve/main/es/es_ES/davefx/medium/es_ES-davefx-medium.onnx.json"

CURRENT_VERSION=""
LATEST_TAG=""
VERSION=""
BACKUP_DIR=""
SOURCE_ROOT=""
CODE_UPDATED=0
WARNINGS=0

log()  { printf '\n\033[1;36m%s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✅ %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m⚠️  %s\033[0m\n' "$*"; WARNINGS=$((WARNINGS+1)); }
fail() { printf '\033[1;31m❌ %s\033[0m\n' "$*" >&2; exit 1; }

trap 'printf "\n❌ Error en la línea %s. El respaldo web, si fue creado, permanece en: %s\n" "$LINENO" "${BACKUP_DIR:-no creado}" >&2' ERR

[[ ${EUID} -eq 0 ]] || fail "El actualizador debe ejecutarse como root."

wait_for_apt() {
    local waited=0
    command -v fuser >/dev/null 2>&1 || return 0

    while \
        fuser /var/lib/dpkg/lock >/dev/null 2>&1 ||
        fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 ||
        fuser /var/lib/apt/lists/lock >/dev/null 2>&1 ||
        fuser /var/cache/apt/archives/lock >/dev/null 2>&1
    do
        if (( waited >= APT_TIMEOUT )); then
            fail "APT/DPKG sigue bloqueado después de ${APT_TIMEOUT}s."
        fi
        printf '⏳ Esperando APT/DPKG... %ss/%ss\r' "$waited" "$APT_TIMEOUT"
        sleep 5
        waited=$((waited + 5))
    done
    printf '\n'
}

apt_install_safe() {
    wait_for_apt
    dpkg --configure -a || true
    wait_for_apt
    DEBIAN_FRONTEND=noninteractive apt-get install -y \
        -o DPkg::Lock::Timeout="$APT_TIMEOUT" "$@"
}

detect_source_root() {
    local dir="$1"
    local found=""

    if [[ -f "$dir/index.php" ]]; then
        printf '%s\n' "$dir"
        return 0
    fi

    found="$(find "$dir" -maxdepth 3 -type f -name index.php -printf '%h\n' 2>/dev/null | head -n1 || true)"
    [[ -n "$found" ]] || return 1
    printf '%s\n' "$found"
}

version_ge() {
    local a="$1" b="$2"
    [[ "$(printf '%s\n%s\n' "$a" "$b" | sort -V | tail -n1)" == "$a" ]]
}

ensure_sudo_line() {
    local line="$1"
    grep -Fxq "$line" "$SUDOERS_FILE" 2>/dev/null || echo "$line" >> "$SUDOERS_FILE"
}

cat <<'BANNER'

    _   _   _ ____   _____  __ _     ___ _   _ _  __
   / \ | | | |  _ \ / _ \ \/ /| |   |_ _| \ | | |/ /
  / _ \| | | | |_) | | | \  / | |    | ||  \| | ' /
 / ___ \ |_| |  _ <| |_| /  \ | |___ | || |\  | . \
/_/   \_\___/|_| \_\\___/_/\_\|_____|___|_| \_|_|\_\

             AUROXLINK - ACTUALIZADOR OFICIAL

BANNER

# ============================================================
# 1. VERSIONES
# ============================================================
log "[1/15] Consultando versión oficial"

if [[ -f "$APP_DIR/version.txt" ]]; then
    CURRENT_VERSION="$(tr -d '[:space:]' < "$APP_DIR/version.txt" || true)"
fi

LATEST_TAG="$(
    git ls-remote --tags --refs "$REPO_URL" 'refs/tags/v*' 2>/dev/null |
    awk -F/ '{print $3}' |
    grep -E '^v[0-9]+([.][0-9]+)*$' |
    sort -V |
    tail -n1
)"

[[ -n "$LATEST_TAG" ]] || fail "No fue posible determinar la última versión publicada en GitHub."

VERSION="${LATEST_TAG#v}"
[[ "$VERSION" =~ ^[0-9]+([.][0-9]+)*$ ]] || fail "Tag inválido: $LATEST_TAG"

printf '  Versión instalada : %s\n' "${CURRENT_VERSION:-desconocida}"
printf '  Última publicada  : %s\n' "$VERSION"

NEED_CODE_UPDATE=1
if [[ -n "$CURRENT_VERSION" ]]; then
    if [[ "$CURRENT_VERSION" == "$VERSION" ]]; then
        NEED_CODE_UPDATE=0
        ok "El código ya corresponde a AUROXLINK ${VERSION}; se ejecutará verificación/migración."
    elif version_ge "$CURRENT_VERSION" "$VERSION"; then
        NEED_CODE_UPDATE=0
        warn "La instalación local (${CURRENT_VERSION}) es más reciente que la release ${VERSION}. No se hará downgrade."
    fi
fi

# ============================================================
# 2. RESPALDO
# ============================================================
log "[2/15] Creando respaldo de seguridad"

BACKUP_DIR="/var/www/backup_auroxlink_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

if [[ -d "$APP_DIR" ]]; then
    cp -a "$APP_DIR"/. "$BACKUP_DIR"/
    ok "Respaldo completo: $BACKUP_DIR"
else
    fail "No existe la instalación AUROXLINK en $APP_DIR"
fi

WEB_OWNER="$(stat -c '%U' "$APP_DIR" 2>/dev/null || echo www-data)"
if [[ "$WEB_OWNER" == "root" || "$WEB_OWNER" == "UNKNOWN" || -z "$WEB_OWNER" ]]; then
    WEB_OWNER="www-data"
fi

# ============================================================
# 3. DEPENDENCIAS 1.8.5
# ============================================================
log "[3/15] Verificando dependencias requeridas"

wait_for_apt
DEBIAN_FRONTEND=noninteractive apt-get update -y -o DPkg::Lock::Timeout="$APT_TIMEOUT"

apt_install_safe \
    php php-cli php-curl php-zip php-xml libapache2-mod-php \
    git curl wget unzip bzip2 ca-certificates lsb-release psmisc cron sudo \
    network-manager wireless-tools alsa-utils \
    iproute2 procps usbutils \
    python3 python3-venv python3-pip \
    sox ffmpeg espeak-ng

command -v php >/dev/null 2>&1 || fail "PHP no está disponible."
command -v svxlink >/dev/null 2>&1 || warn "SvxLink no está disponible. AUROXLINK no cambiará su versión automáticamente."
php -r 'exit(class_exists("DOMDocument") ? 0 : 1);' || fail "PHP DOM/XML no está disponible."

# No instalamos/actualizamos svxlink-server aquí.
ok "Dependencias AUROXLINK verificadas sin alterar la versión de SvxLink."

if getent group audio >/dev/null 2>&1; then
    usermod -aG audio www-data
    ok "www-data pertenece/ha sido agregado al grupo audio."
fi

# Mantener compatibilidad histórica con instalaciones que usaban Tailscale.
if ! command -v tailscale >/dev/null 2>&1; then
    log "Instalando Tailscale para mantener compatibilidad AUROXLINK..."
    if curl -fsSL https://tailscale.com/install.sh | sh; then
        ok "Tailscale instalado."
    else
        warn "No se pudo instalar Tailscale. La actualización continuará."
    fi
fi

if command -v tailscale >/dev/null 2>&1; then
    systemctl enable --now tailscaled >/dev/null 2>&1 || warn "No se pudo activar tailscaled."
fi

# ============================================================
# 4. CÓDIGO DESDE GITHUB
# ============================================================
log "[4/15] Actualizando código AUROXLINK"

if (( NEED_CODE_UPDATE == 1 )); then
    rm -rf "$TMP_ROOT"
    mkdir -p "$EXTRACT_DIR"

    GITHUB_URL="https://github.com/telecov/auroxlink/archive/refs/tags/${LATEST_TAG}.zip"
    wget -q --show-progress "$GITHUB_URL" -O "$DOWNLOAD_ZIP"
    [[ -s "$DOWNLOAD_ZIP" ]] || fail "No se pudo descargar ${LATEST_TAG}."

    unzip -q "$DOWNLOAD_ZIP" -d "$EXTRACT_DIR"
    SOURCE_ROOT="$(detect_source_root "$EXTRACT_DIR")" || fail "El paquete descargado no contiene index.php."

    REQUIRED=(
        "index.php"
        "settings.php"
        "sismografo.php"
        "includes/environment.php"
        "includes/seismic-data.php"
        "includes/seismic-lib.php"
        "includes/save-seismic.php"
        "includes/test-seismic-rf.php"
        "scripts/seismic-monitor.php"
        "install_svxlink_latest.sh"
        "svxlink_update_worker.sh"
        "update_auroxlink.sh"
    )

    for rel in "${REQUIRED[@]}"; do
        [[ -f "$SOURCE_ROOT/$rel" ]] || fail "La release ${LATEST_TAG} no contiene: $rel"
    done

    # Validaciones antes de tocar el webroot.
    php -l "$SOURCE_ROOT/index.php" >/dev/null
    php -l "$SOURCE_ROOT/settings.php" >/dev/null
    php -l "$SOURCE_ROOT/sismografo.php" >/dev/null
    php -l "$SOURCE_ROOT/scripts/seismic-monitor.php" >/dev/null
    bash -n "$SOURCE_ROOT/update_auroxlink.sh"
    bash -n "$SOURCE_ROOT/install_svxlink_latest.sh"
    bash -n "$SOURCE_ROOT/svxlink_update_worker.sh"

    # Sustitución limpia: evita dejar archivos obsoletos.
    find "$APP_DIR" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
    cp -a "$SOURCE_ROOT"/. "$APP_DIR"/
    CODE_UPDATED=1
    ok "Código ${LATEST_TAG} instalado."
else
    ok "No fue necesario reemplazar el código web."
fi

# ============================================================
# 5. RESTAURAR DATOS DEL USUARIO
# ============================================================
log "[5/15] Restaurando configuración y datos persistentes"

restore_file() {
    local rel="$1"
    if [[ -f "$BACKUP_DIR/$rel" ]]; then
        mkdir -p "$(dirname "$APP_DIR/$rel")"
        cp -a "$BACKUP_DIR/$rel" "$APP_DIR/$rel"
        printf '  - Restaurado: %s\n' "$rel"
    fi
}

restore_dir() {
    local rel="$1"
    if [[ -d "$BACKUP_DIR/$rel" ]]; then
        mkdir -p "$APP_DIR/$rel"
        cp -a "$BACKUP_DIR/$rel"/. "$APP_DIR/$rel"/
        printf '  - Restaurado: %s/\n' "$rel"
    fi
}

restore_file "telegram_config.json"
restore_file "estilos.json"
restore_file "data/qsls.json"
restore_file "data/eventos.json"

restore_dir "data_actividades"
restore_dir "qsl"
restore_dir "includes/backups"
restore_dir "includes/logs"

# Personalización gráfica histórica y actual.
shopt -s nullglob
for f in "$BACKUP_DIR"/auroxlink_banner.*; do
    cp -a "$f" "$APP_DIR/$(basename "$f")"
    printf '  - Restaurado: %s\n' "$(basename "$f")"
done
for f in "$BACKUP_DIR"/img/auroxlink_banner.* "$BACKUP_DIR"/img/admin.*; do
    [[ -f "$f" ]] || continue
    mkdir -p "$APP_DIR/img"
    cp -a "$f" "$APP_DIR/img/$(basename "$f")"
    printf '  - Restaurado: img/%s\n' "$(basename "$f")"
done
shopt -u nullglob

ok "Datos persistentes restaurados."

# ============================================================
# 6. CONFIGURACIÓN SÍSMICA - MERGE
# ============================================================
log "[6/15] Migrando configuración sísmica"

mkdir -p "$APP_DIR/data"

# Crear defaults si la release no los incluye.
if [[ ! -f "$SEISMIC_CONFIG" ]]; then
cat > "$SEISMIC_CONFIG" <<'JSON'
{
    "enabled": true,
    "latitude": -29.9027,
    "longitude": -71.2519,
    "refresh_seconds": 60,
    "provider": "auto",
    "display": {
        "min_magnitude": 2.5,
        "radius_km": 3000
    },
    "rf": {
        "enabled": false,
        "command_pty": "/dev/shm/simplex_logic_ctrl",
        "local": {"enabled": true, "radius_km": 200, "min_magnitude": 3.5},
        "regional": {"enabled": true, "radius_km": 500, "min_magnitude": 4.2},
        "wide": {"enabled": true, "radius_km": 1000, "min_magnitude": 5.0},
        "national": {"enabled": true, "min_magnitude": 6.0},
        "repetitions": 1,
        "pre_tone": true
    },
    "tts": {
        "engine": "piper",
        "voice": "es_ES-davefx-medium",
        "model": "/opt/auroxlink/piper/voices/es_ES-davefx-medium.onnx",
        "length_scale": 1.0,
        "fallback_voice": "es",
        "fallback_speed": 145
    }
}
JSON
fi

if [[ ! -f "$SEISMIC_STATE" ]]; then
cat > "$SEISMIC_STATE" <<'JSON'
{
    "initialized": false,
    "processed_events": [],
    "announced_events": [],
    "last_check": null,
    "last_event": null,
    "last_rf_event": null,
    "provider_resolved": null
}
JSON
fi

# Los valores existentes del usuario ganan sobre los defaults nuevos.
python3 - "$SEISMIC_CONFIG" "$BACKUP_DIR/data/seismic_config.json" <<'PY'
import json, sys
from pathlib import Path

dst = Path(sys.argv[1])
old = Path(sys.argv[2])

def merge(base, overlay):
    if isinstance(base, dict) and isinstance(overlay, dict):
        out = dict(base)
        for k, v in overlay.items():
            out[k] = merge(out[k], v) if k in out else v
        return out
    return overlay

base = json.loads(dst.read_text(encoding="utf-8"))
if old.exists():
    try:
        prev = json.loads(old.read_text(encoding="utf-8"))
        base = merge(base, prev)
    except Exception as e:
        print(f"AVISO: no se pudo migrar configuración sísmica anterior: {e}")

provider = str(base.get("provider", "auto")).lower()
if provider not in {"auto", "csn", "usgs"}:
    base["provider"] = "auto"

dst.write_text(json.dumps(base, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
PY

python3 - "$SEISMIC_STATE" "$BACKUP_DIR/data/seismic_state.json" <<'PY'
import json, sys
from pathlib import Path

dst = Path(sys.argv[1])
old = Path(sys.argv[2])

base = json.loads(dst.read_text(encoding="utf-8"))
if old.exists():
    try:
        prev = json.loads(old.read_text(encoding="utf-8"))
        if isinstance(prev, dict):
            base.update(prev)
    except Exception as e:
        print(f"AVISO: no se pudo migrar estado sísmico anterior: {e}")

base.setdefault("provider_resolved", None)
base.setdefault("last_rf_event", None)
base.setdefault("processed_events", [])
base.setdefault("announced_events", [])
dst.write_text(json.dumps(base, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
PY

python3 -m json.tool "$SEISMIC_CONFIG" >/dev/null || fail "seismic_config.json inválido."
python3 -m json.tool "$SEISMIC_STATE" >/dev/null || fail "seismic_state.json inválido."

ok "Configuración sísmica migrada preservando los valores del usuario."

# ============================================================
# 7. PERMISOS WEB Y AUDIO
# ============================================================
log "[7/15] Aplicando permisos seguros"

mkdir -p \
    "$APP_DIR/data" \
    "$APP_DIR/data_actividades/historial" \
    "$APP_DIR/qsl" \
    "$APP_DIR/includes/logs" \
    "$APP_DIR/includes/backups" \
    "$APP_DIR/img" \
    /tmp/auroxlink_logs

chown -R "$WEB_OWNER":www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 775 {} +
find "$APP_DIR" -type f -exec chmod 664 {} +

for script in \
    "$APP_DIR/install_auroxlink.sh" \
    "$APP_DIR/install_svxlink_latest.sh" \
    "$APP_DIR/svxlink_update_worker.sh" \
    "$APP_DIR/update_auroxlink.sh"
do
    [[ -f "$script" ]] && chmod 775 "$script"
done

for dir in \
    "$APP_DIR/data" \
    "$APP_DIR/data_actividades" \
    "$APP_DIR/data_actividades/historial" \
    "$APP_DIR/qsl" \
    "$APP_DIR/includes/logs" \
    "$APP_DIR/includes/backups" \
    "$APP_DIR/img" \
    /tmp/auroxlink_logs
do
    mkdir -p "$dir"
    chown -R "$WEB_OWNER":www-data "$dir"
    chmod 2775 "$dir"
    find "$dir" -type f -exec chmod 664 {} + 2>/dev/null || true
done

chown "$WEB_OWNER":www-data "$SEISMIC_CONFIG"
chmod 664 "$SEISMIC_CONFIG"
chown svxlink:www-data "$SEISMIC_STATE" 2>/dev/null || true
chmod 664 "$SEISMIC_STATE"

ok "Permisos web y directorios de runtime preparados."

# ============================================================
# 8. ACTUALIZADORES PROTEGIDOS
# ============================================================
log "[8/15] Instalando workers protegidos"

mkdir -p "$LIBEXEC_DIR" "$SVX_UPDATE_STATE"

for script in update_auroxlink.sh install_svxlink_latest.sh svxlink_update_worker.sh; do
    [[ -f "$APP_DIR/$script" ]] || fail "Falta $script en AUROXLINK."
    install -o root -g root -m 0755 "$APP_DIR/$script" "$LIBEXEC_DIR/$script"
done

chown root:www-data "$SVX_UPDATE_STATE"
chmod 0750 "$SVX_UPDATE_STATE"

ok "Actualizadores protegidos instalados en $LIBEXEC_DIR."

# ============================================================
# 9. SUDOERS
# ============================================================
log "[9/15] Actualizando permisos sudo del panel"

touch "$SUDOERS_FILE"
chmod 440 "$SUDOERS_FILE"

# Retirar mecanismo inseguro heredado.
sed -i '\|/usr/bin/bash /tmp/update_auroxlink.sh|d' "$SUDOERS_FILE"

SUDO_LINES=(
"www-data ALL=(root) NOPASSWD: /bin/systemctl start svxlink"
"www-data ALL=(root) NOPASSWD: /bin/systemctl stop svxlink"
"www-data ALL=(root) NOPASSWD: /bin/systemctl restart svxlink"
"www-data ALL=(root) NOPASSWD: /bin/systemctl reset-failed svxlink"
"www-data ALL=(root) NOPASSWD: /bin/systemctl is-active --quiet svxlink"
"www-data ALL=(root) NOPASSWD: /usr/bin/systemctl start svxlink"
"www-data ALL=(root) NOPASSWD: /usr/bin/systemctl stop svxlink"
"www-data ALL=(root) NOPASSWD: /usr/bin/systemctl restart svxlink"
"www-data ALL=(root) NOPASSWD: /usr/bin/systemctl reset-failed svxlink"
"www-data ALL=(root) NOPASSWD: /usr/bin/systemctl is-active --quiet svxlink"
"www-data ALL=(root) NOPASSWD: /sbin/reboot"
"www-data ALL=(root) NOPASSWD: /usr/sbin/reboot"
"www-data ALL=(root) NOPASSWD: /usr/bin/nmcli"
"www-data ALL=(root) NOPASSWD: /sbin/iwlist"
"www-data ALL=(root) NOPASSWD: /usr/sbin/iwlist"
"www-data ALL=(root) NOPASSWD: /usr/bin/amixer"
"www-data ALL=(root) NOPASSWD: /usr/bin/alsactl"
"www-data ALL=(root) NOPASSWD: /usr/sbin/alsactl"
"www-data ALL=(root) NOPASSWD: /sbin/alsactl"
"www-data ALL=(root) NOPASSWD: /usr/bin/tailscale"
"www-data ALL=(root) NOPASSWD: /usr/sbin/tailscale"
"www-data ALL=(root) NOPASSWD: /usr/bin/bash /usr/local/libexec/auroxlink/update_auroxlink.sh"
"www-data ALL=(root) NOPASSWD: /usr/bin/bash /usr/local/libexec/auroxlink/svxlink_update_worker.sh"
)

for line in "${SUDO_LINES[@]}"; do
    ensure_sudo_line "$line"
done

chmod 440 "$SUDOERS_FILE"
visudo -cf "$SUDOERS_FILE" >/dev/null || fail "Error de sintaxis en $SUDOERS_FILE"

cat > "$SEISMIC_SUDOERS" <<'SUDO'
# AUROXLINK - prueba RF Sismógrafo
www-data ALL=(svxlink) NOPASSWD: /usr/bin/php /opt/auroxlink/scripts/seismic-monitor.php --test-rf
SUDO
chmod 440 "$SEISMIC_SUDOERS"
visudo -cf "$SEISMIC_SUDOERS" >/dev/null || fail "Error de sintaxis en $SEISMIC_SUDOERS"

ok "sudoers actualizado, incluido el actualizador de AUROXLINK."

# ============================================================
# 10. SONIDOS SVXLINK / ECHOLINK
# ============================================================
log "[10/15] Verificando sonidos SvxLink/EchoLink"

SOUNDS_DIR="/usr/share/svxlink/sounds"
SVX_PKG_VERSION="$(dpkg-query -W -f='${Version}' svxlink-server 2>/dev/null | cut -d- -f1 || true)"
SOUNDS_RELEASE=""

case "$SVX_PKG_VERSION" in
    25.05*) SOUNDS_RELEASE="25.05" ;;
    24.02*) SOUNDS_RELEASE="24.02" ;;
    19.09*) SOUNDS_RELEASE="19.09" ;;
esac

mkdir -p "$SOUNDS_DIR"

if [[ -n "$SOUNDS_RELEASE" ]]; then
    SOUNDS_ARCHIVE="svxlink-sounds-en_US-heather-16k-${SOUNDS_RELEASE}.tar.bz2"
    SOUNDS_URL="https://github.com/sm0svx/svxlink-sounds-en_US-heather/releases/download/${SOUNDS_RELEASE}/${SOUNDS_ARCHIVE}"

    if [[ ! -d "$SOUNDS_DIR/en_US-heather-16k" ]]; then
        if wget -q --show-progress "$SOUNDS_URL" -O "/tmp/$SOUNDS_ARCHIVE"; then
            tar -xjf "/tmp/$SOUNDS_ARCHIVE" -C "$SOUNDS_DIR"
            rm -f "/tmp/$SOUNDS_ARCHIVE"
            ok "Sonidos en_US Heather ${SOUNDS_RELEASE} instalados."
        else
            rm -f "/tmp/$SOUNDS_ARCHIVE"
            warn "No se pudieron descargar sonidos en_US ${SOUNDS_RELEASE}."
        fi
    fi
fi

if [[ -d "$SOUNDS_DIR/en_US-heather-16k" ]]; then
    ln -sfn en_US-heather-16k "$SOUNDS_DIR/en_US"
    ok "Alias en_US activo."
else
    warn "No se encontró paquete de sonidos Heather compatible."
fi

# ============================================================
# 11. PIPER + MONITOR SÍSMICO
# ============================================================
log "[11/15] Preparando Piper y monitor sísmico"

mkdir -p /opt/auroxlink "$PIPER_VOICES"

if [[ ! -x "${PIPER_VENV}/bin/python" ]]; then
    python3 -m venv "$PIPER_VENV"
fi

if [[ ! -x "$PIPER_BIN" ]]; then
    "${PIPER_VENV}/bin/pip" install --upgrade pip
    "${PIPER_VENV}/bin/pip" install piper-tts
fi

[[ -x "$PIPER_BIN" ]] || fail "Piper no quedó instalado."

if [[ ! -s "$PIPER_MODEL" ]]; then
    wget -q --show-progress "$PIPER_MODEL_URL" -O "${PIPER_MODEL}.tmp"
    mv "${PIPER_MODEL}.tmp" "$PIPER_MODEL"
fi

if [[ ! -s "$PIPER_MODEL_JSON" ]]; then
    wget -q "$PIPER_MODEL_JSON_URL" -O "${PIPER_MODEL_JSON}.tmp"
    mv "${PIPER_MODEL_JSON}.tmp" "$PIPER_MODEL_JSON"
fi

chmod -R a+rX "$PIPER_VOICES" "$PIPER_VENV"

MONITOR_SRC=""
for candidate in "$APP_DIR/scripts/seismic-monitor.php" "$APP_DIR/seismic-monitor.php"; do
    if [[ -f "$candidate" ]]; then
        MONITOR_SRC="$candidate"
        break
    fi
done

[[ -n "$MONITOR_SRC" ]] || fail "No se encontró seismic-monitor.php."

mkdir -p /opt/auroxlink/scripts
install -o root -g root -m 0755 "$MONITOR_SRC" "$SEISMIC_MONITOR_DST"
php -l "$SEISMIC_MONITOR_DST" >/dev/null || fail "Error PHP en monitor sísmico."

mkdir -p "$SEISMIC_AUDIO_DIR"
chown svxlink:svxlink "$SEISMIC_AUDIO_DIR" 2>/dev/null || true
chmod 775 "$SEISMIC_AUDIO_DIR"

ok "Piper y monitor sísmico preparados."

# ============================================================
# 12. COMMAND_PTY + CONFIGURACIONES SVXLINK
# ============================================================
log "[12/15] Integrando COMMAND_PTY sin reemplazar configuración SvxLink"

if [[ -f "$SVXLINK_CONF" ]]; then
    if python3 - "$SVXLINK_CONF" "$COMMAND_PTY" <<'PY'
import re, sys
from pathlib import Path

path = Path(sys.argv[1])
pty = sys.argv[2]
text = path.read_text(encoding="utf-8", errors="replace")
lines = text.splitlines()

active = None
section = None
for line in lines:
    s = line.strip()
    if s.startswith("[") and s.endswith("]"):
        section = s[1:-1].strip()
        continue
    if section == "GLOBAL":
        cleaned = s.lstrip("#").strip()
        if cleaned.startswith("LOGICS="):
            vals = [x.strip() for x in re.split(r"[,;]", cleaned.split("=",1)[1]) if x.strip()]
            if vals:
                active = vals[0]
                break

if not active and re.search(r"(?m)^\[SimplexLogic\]\s*$", text):
    active = "SimplexLogic"
if not active:
    raise SystemExit(2)
if not re.search(rf"(?m)^\[{re.escape(active)}\]\s*$", text):
    raise SystemExit(3)

out=[]
section=None
inserted=False
for line in lines:
    s=line.strip()
    if s.startswith("[") and s.endswith("]"):
        if section == active and not inserted:
            out.append(f"COMMAND_PTY={pty}")
            inserted=True
        section=s[1:-1].strip()
        out.append(line)
        continue
    if section == active and s.lstrip("#").strip().startswith("COMMAND_PTY="):
        if not inserted:
            out.append(f"COMMAND_PTY={pty}")
            inserted=True
        continue
    out.append(line)

if section == active and not inserted:
    out.append(f"COMMAND_PTY={pty}")
    inserted=True

if not inserted:
    raise SystemExit(4)

path.write_text("\n".join(out) + "\n", encoding="utf-8")
print(active)
PY
    then
        ok "COMMAND_PTY integrado en la lógica activa."
    else
        warn "No fue posible integrar COMMAND_PTY automáticamente. No se reemplazó svxlink.conf."
    fi

    chown root:www-data "$SVXLINK_CONF"
    chmod 664 "$SVXLINK_CONF"
else
    warn "No existe $SVXLINK_CONF."
fi

if [[ -f "$ECHOLINK_CONF" ]]; then
    chown root:www-data "$ECHOLINK_CONF"
    chmod 664 "$ECHOLINK_CONF"
fi

if [[ -d /etc/svxlink/svxlink.d ]]; then
    chown root:www-data /etc/svxlink/svxlink.d
    chmod 775 /etc/svxlink/svxlink.d
fi

# ============================================================
# 13. SYSTEMD + CRON
# ============================================================
log "[13/15] Instalando servicios AUROXLINK"

cat > "$SEISMIC_SERVICE" <<'SERVICE'
[Unit]
Description=AUROXLINK Seismic Monitor
After=network-online.target svxlink.service
Wants=network-online.target
Requires=svxlink.service

[Service]
Type=oneshot
User=svxlink
Group=svxlink
SupplementaryGroups=www-data
ExecStart=/usr/bin/php /opt/auroxlink/scripts/seismic-monitor.php
Nice=10
SERVICE

cat > "$SEISMIC_TIMER" <<'TIMER'
[Unit]
Description=AUROXLINK Seismic Monitor Timer

[Timer]
OnBootSec=30
OnUnitActiveSec=60
AccuracySec=5
Persistent=true

[Install]
WantedBy=timers.target
TIMER

chmod 644 "$SEISMIC_SERVICE" "$SEISMIC_TIMER"

cat > "$CRON_FILE" <<'CRON'
# AUROXLINK - estado diario por Telegram
0 12 * * * www-data /usr/bin/php /var/www/html/send_daily_status.php >> /tmp/estado_diario_cron.log 2>&1
CRON
chown root:root "$CRON_FILE"
chmod 0644 "$CRON_FILE"

cat > "$LEGACY_SERVICE" <<'SERVICE'
[Unit]
Description=AUROXLINK - Monitor de conexiones SVXLink y alertas Telegram
After=network-online.target svxlink.service
Wants=network-online.target

[Service]
Type=simple
ExecStart=/usr/bin/php /var/www/html/monitor_log_svx.php
Restart=always
RestartSec=5
User=www-data
Group=www-data
StandardOutput=append:/var/log/auroralink_monitor.log
StandardError=append:/var/log/auroralink_monitor_error.log

[Install]
WantedBy=multi-user.target
SERVICE
chown root:root "$LEGACY_SERVICE"
chmod 0644 "$LEGACY_SERVICE"

if [[ -f /var/log/svxlink ]]; then
    chmod 0644 /var/log/svxlink || true
fi

systemctl daemon-reload
systemctl enable apache2.service cron.service auroxlink-seismic.timer >/dev/null
systemctl restart apache2.service
systemctl restart cron.service
systemctl restart auroxlink-seismic.timer

if [[ -f "$APP_DIR/monitor_log_svx.php" ]]; then
    systemctl enable auroralink-monitor.service >/dev/null 2>&1 || true
    systemctl restart auroralink-monitor.service >/dev/null 2>&1 || warn "auroralink-monitor no pudo reiniciarse."
fi

# Reiniciar SvxLink solo para activar COMMAND_PTY; no se instala/cambia su versión.
if systemctl list-unit-files svxlink.service >/dev/null 2>&1; then
    systemctl enable svxlink.service >/dev/null 2>&1 || true
    if ! systemctl restart svxlink.service; then
        warn "SvxLink no inició. La actualización AUROXLINK continuará; revisar audio/configuración."
    fi
fi

# Esperar unos segundos para COMMAND_PTY.
PTY_OK=0
for _ in {1..10}; do
    if [[ -e "$COMMAND_PTY" ]]; then
        PTY_OK=1
        break
    fi
    sleep 1
done

if (( PTY_OK == 1 )); then
    ok "COMMAND_PTY activo: $COMMAND_PTY"
else
    warn "COMMAND_PTY aún no apareció; puede depender del arranque correcto de SvxLink."
fi

# Ejecutar una vez el monitor para inicializar estado, sin forzar RF histórico.
if systemctl start auroxlink-seismic.service; then
    ok "Monitor sísmico inicializado."
else
    warn "Primera ejecución sísmica falló; revisar journal."
fi

# ============================================================
# 14. MARCADOR DE MIGRACIÓN / VERSIÓN
# ============================================================
log "[14/15] Finalizando migración de versión"

mkdir -p "$MIGRATIONS_DIR"
chmod 755 "$MIGRATIONS_DIR"

# Si la versión instalada ya es 1.8.5 o superior, marcamos la migración base.
FINAL_VERSION="$VERSION"
if [[ -f "$APP_DIR/version.txt" ]]; then
    FILE_VERSION="$(tr -d '[:space:]' < "$APP_DIR/version.txt" || true)"
    [[ -n "$FILE_VERSION" ]] && FINAL_VERSION="$FILE_VERSION"
fi

# La release manda: si acabamos de actualizar código, aseguramos version.txt.
if (( CODE_UPDATED == 1 )); then
    printf '%s\n' "$VERSION" > "$APP_DIR/version.txt"
    chown "$WEB_OWNER":www-data "$APP_DIR/version.txt"
    chmod 664 "$APP_DIR/version.txt"
    FINAL_VERSION="$VERSION"
fi

if [[ -n "$FINAL_VERSION" ]] && version_ge "$FINAL_VERSION" "1.8.5"; then
    touch "$MIGRATION_185"
    chmod 644 "$MIGRATION_185"
    ok "Migración AUROXLINK 1.8.5 marcada como completada."
fi

rm -rf "$TMP_ROOT"

# ============================================================
# 15. VERIFICACIÓN FINAL
# ============================================================
log "[15/15] Verificación final"

FAILS=0

check_file() {
    local file="$1" label="$2"
    if [[ -f "$file" ]]; then
        ok "$label"
    else
        printf '⚠️  %s: falta\n' "$label"
        FAILS=$((FAILS+1))
    fi
}

check_file "$APP_DIR/index.php" "Web AUROXLINK"
check_file "$APP_DIR/settings.php" "Settings"
check_file "$APP_DIR/sismografo.php" "Sismógrafo"
check_file "$APP_DIR/includes/seismic-data.php" "Backend CSN/USGS"
check_file "$APP_DIR/includes/save-seismic.php" "Guardado sísmico"
check_file "$SEISMIC_MONITOR_DST" "Monitor sísmico"
check_file "$PIPER_MODEL" "Voz Piper"
check_file "$LIBEXEC_DIR/update_auroxlink.sh" "Actualizador protegido AUROXLINK"
check_file "$LIBEXEC_DIR/svxlink_update_worker.sh" "Worker protegido SvxLink"

if systemctl is-active auroxlink-seismic.timer >/dev/null 2>&1; then
    ok "Timer sísmico activo"
else
    warn "Timer sísmico no está activo."
    FAILS=$((FAILS+1))
fi

if id -nG www-data 2>/dev/null | tr ' ' '\n' | grep -qx audio; then
    ok "www-data pertenece al grupo audio"
else
    warn "www-data no pertenece al grupo audio."
    FAILS=$((FAILS+1))
fi

if runuser -u www-data -- sudo -n -l /usr/bin/bash /usr/local/libexec/auroxlink/update_auroxlink.sh >/dev/null 2>&1; then
    ok "Web autorizada para ejecutar actualizador AUROXLINK"
else
    warn "Falta autorización sudo para actualizador AUROXLINK."
    FAILS=$((FAILS+1))
fi

if runuser -u www-data -- sudo -n -l /usr/bin/bash /usr/local/libexec/auroxlink/svxlink_update_worker.sh >/dev/null 2>&1; then
    ok "Web autorizada para ejecutar worker SvxLink"
else
    warn "Falta autorización sudo para worker SvxLink."
    FAILS=$((FAILS+1))
fi

if [[ -f "$MIGRATION_185" ]]; then
    ok "Migración base 1.8.5 completa"
else
    warn "No existe marcador de migración 1.8.5."
    FAILS=$((FAILS+1))
fi

apache2ctl configtest >/dev/null || fail "Apache presenta errores de configuración."

printf '\n============================================================\n'
if (( FAILS == 0 )); then
    printf '🎉 AUROXLINK %s ACTUALIZADO Y MIGRADO CORRECTAMENTE\n' "$FINAL_VERSION"
else
    printf '⚠️  AUROXLINK %s actualizado con %s advertencia(s) de verificación\n' "$FINAL_VERSION" "$FAILS"
fi
printf '============================================================\n'
printf 'Respaldo        : %s\n' "$BACKUP_DIR"
printf 'Versión final   : %s\n' "$FINAL_VERSION"
printf 'Código renovado : %s\n' "$([[ $CODE_UPDATED -eq 1 ]] && echo SI || echo NO)"
printf 'Migración 1.8.5 : %s\n' "$([[ -f "$MIGRATION_185" ]] && echo OK || echo PENDIENTE)"
printf 'Sismógrafo      : systemctl status auroxlink-seismic.timer --no-pager\n'
printf 'Prueba RF       : sudo -u svxlink php /opt/auroxlink/scripts/seismic-monitor.php --test-rf\n'
printf '\n'
printf 'Gracias por usar AUROXLINK.\n'
printf 'Román Carvajal Rodríguez · CE2RDP · Telecoviajero\n'
printf '============================================================\n'

exit "$([[ $FAILS -eq 0 ]] && echo 0 || echo 1)"
