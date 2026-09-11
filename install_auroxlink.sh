#!/usr/bin/env bash
set -Eeuo pipefail

# ============================================================
# AUROXLINK 1.8.5 - INSTALADOR OFICIAL
#
# Origen del código:
#   1) Si se entrega un ZIP por argumento, usa ese paquete.
#   2) Si encuentra un ZIP AUROXLINK en /media, /mnt o /run/media,
#      usa ese paquete.
#   3) Si no existe un ZIP local, descarga AUROXLINK desde GitHub.
#
# El ZIP local se conserva como método de instalación/prueba y respaldo.
# La instalación normal, sin ZIP, obtiene el código oficial desde GitHub.
#
# Uso normal:
#
#   sudo bash install_auroxlink.sh
#
# Uso opcional con ZIP local:
#
#   sudo bash install_auroxlink.sh /ruta/auroxlink.zip
#
# ============================================================

APP_NAME="AUROXLINK"
APP_DIR="/var/www/html"
APT_TIMEOUT=180

ZIP_ARG="${1:-}"
WORK_DIR="/tmp/auroxlink-install"
EXTRACT_DIR="${WORK_DIR}/extract"
PACKAGE_FILE="${WORK_DIR}/auroxlink.zip"

REPO_OWNER="telecov"
REPO_NAME="auroxlink"
REPO_URL="https://github.com/${REPO_OWNER}/${REPO_NAME}"
GITHUB_MAIN_ZIP="${REPO_URL}/archive/refs/heads/main.zip"

ZIP_FILE=""
SOURCE_DESC=""
NEED_GITHUB=0

SEISMIC_CONFIG="${APP_DIR}/data/seismic_config.json"
SEISMIC_STATE="${APP_DIR}/data/seismic_state.json"

SEISMIC_MONITOR_DST="/opt/auroxlink/scripts/seismic-monitor.php"
SEISMIC_AUDIO_DIR="/var/lib/auroxlink/seismic"

SEISMIC_SERVICE="/etc/systemd/system/auroxlink-seismic.service"
SEISMIC_TIMER="/etc/systemd/system/auroxlink-seismic.timer"
SEISMIC_SUDOERS="/etc/sudoers.d/99-auroxlink-seismic"

SVXLINK_CONF="/etc/svxlink/svxlink.conf"
COMMAND_PTY="/dev/shm/simplex_logic_ctrl"

PIPER_VENV="/opt/auroxlink/piper-venv"
PIPER_BIN="${PIPER_VENV}/bin/piper"
PIPER_VOICES="/opt/auroxlink/piper/voices"
PIPER_MODEL_NAME="es_ES-davefx-medium"
PIPER_MODEL="${PIPER_VOICES}/${PIPER_MODEL_NAME}.onnx"
PIPER_MODEL_JSON="${PIPER_MODEL}.json"

PIPER_MODEL_URL="https://huggingface.co/rhasspy/piper-voices/resolve/main/es/es_ES/davefx/medium/es_ES-davefx-medium.onnx"
PIPER_MODEL_JSON_URL="https://huggingface.co/rhasspy/piper-voices/resolve/main/es/es_ES/davefx/medium/es_ES-davefx-medium.onnx.json"

SUDOERS_FILE="/etc/sudoers.d/99-www-data-svxlink"
SERVICE_FILE="/etc/systemd/system/auroralink-monitor.service"
CRON_FILE="/etc/cron.d/auroxlink"

ADMIN_USER="${SUDO_USER:-root}"

log()  { printf '\n\033[1;36m%s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✅ %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m⚠️  %s\033[0m\n' "$*"; }
fail() { printf '\033[1;31m❌ %s\033[0m\n' "$*" >&2; exit 1; }

trap 'printf "\n❌ Error en la línea %s. Instalación detenida.\n" "$LINENO" >&2' ERR

if [[ ${EUID} -ne 0 ]]; then
    fail "Ejecuta como root: sudo bash install_auroxlink.sh"
fi

if ! id "$ADMIN_USER" >/dev/null 2>&1; then
    ADMIN_USER="root"
fi

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

    DEBIAN_FRONTEND=noninteractive \
        apt-get install -y \
        -o DPkg::Lock::Timeout="$APT_TIMEOUT" \
        "$@"
}

find_zip() {
    local candidate

    if [[ -n "$ZIP_ARG" ]]; then
        if [[ -f "$ZIP_ARG" ]]; then
            printf '%s\n' "$ZIP_ARG"
            return 0
        fi

        warn "No existe el ZIP indicado: $ZIP_ARG"
        warn "Se intentará continuar descargando AUROXLINK desde GitHub."
        return 1
    fi

    # Buscar primero nombres típicos.
    for base in /media /mnt /run/media; do
        [[ -d "$base" ]] || continue

        candidate="$(find "$base" -maxdepth 4 -type f \
            \( -iname 'auroxlink*.zip' -o -iname '*aurox*.zip' \) \
            2>/dev/null | head -n1 || true)"

        if [[ -n "$candidate" ]]; then
            printf '%s\n' "$candidate"
            return 0
        fi
    done

    # Buscar cualquier ZIP si no encontramos uno con nombre AUROXLINK.
    for base in /media /mnt /run/media; do
        [[ -d "$base" ]] || continue

        candidate="$(find "$base" -maxdepth 4 -type f -iname '*.zip' \
            2>/dev/null | head -n1 || true)"

        if [[ -n "$candidate" ]]; then
            printf '%s\n' "$candidate"
            return 0
        fi
    done

    return 1
}

detect_source_root() {
    local dir="$1"

    if [[ -f "$dir/index.php" ]]; then
        printf '%s\n' "$dir"
        return 0
    fi

    local found
    found="$(find "$dir" -maxdepth 3 -type f -name index.php -printf '%h\n' \
        2>/dev/null | head -n1 || true)"

    [[ -n "$found" ]] || return 1

    printf '%s\n' "$found"
}

cat <<'BANNER'

    _   _   _ ____   _____  __ _     ___ _   _ _  __
   / \ | | | |  _ \ / _ \ \/ /| |   |_ _| \ | | |/ /
  / _ \| | | | |_) | | | \  / | |    | ||  \| | ' /
 / ___ \ |_| |  _ <| |_| /  \ | |___ | || |\  | . \
/_/   \_\___/|_| \_\\___/_/\_\|_____|___|_| \_|_|\_\

          AUROXLINK 1.8.5 - INSTALADOR OFICIAL
             Instalador oficial AUROXLINK

BANNER

# ============================================================
# 1. DETERMINAR ORIGEN DEL CÓDIGO
# ============================================================

log "[1/13] Determinando origen del código AUROXLINK"

mkdir -p "$WORK_DIR"
rm -rf "$EXTRACT_DIR"
mkdir -p "$EXTRACT_DIR"
rm -f "$PACKAGE_FILE"

if ZIP_FILE="$(find_zip)"; then
    cp -f "$ZIP_FILE" "$PACKAGE_FILE"
    SOURCE_DESC="ZIP local: $ZIP_FILE"
    ok "Se utilizará paquete local: $ZIP_FILE"
else
    NEED_GITHUB=1
    SOURCE_DESC="GitHub: ${REPO_OWNER}/${REPO_NAME} (main)"
    ok "No se encontró ZIP local. Se descargará AUROXLINK desde GitHub."
fi

# ============================================================
# 2. DEPENDENCIAS BASE
# ============================================================

log "[2/13] Instalando dependencias base"

wait_for_apt

DEBIAN_FRONTEND=noninteractive \
    apt-get update -y \
    -o DPkg::Lock::Timeout="$APT_TIMEOUT"

apt_install_safe \
    apache2 \
    php php-cli php-curl php-zip php-xml libapache2-mod-php \
    git curl wget unzip bzip2 ca-certificates lsb-release psmisc cron sudo \
    network-manager wireless-tools alsa-utils \
    iproute2 procps usbutils \
    python3 python3-venv python3-pip \
    sox ffmpeg espeak-ng \
    svxlink-server

command -v php >/dev/null 2>&1 || fail "PHP no quedó instalado."
command -v svxlink >/dev/null 2>&1 || fail "SvxLink no quedó instalado."
php -r 'exit(class_exists("DOMDocument") ? 0 : 1);' \
    || fail "PHP DOMDocument no está disponible."

ok "Dependencias base instaladas."

# Si no había ZIP local, ahora que wget/unzip están disponibles descargamos
# el código oficial desde GitHub.
if (( NEED_GITHUB == 1 )); then
    log "Descargando AUROXLINK desde GitHub..."

    if ! wget -q --show-progress "$GITHUB_MAIN_ZIP" -O "$PACKAGE_FILE"; then
        rm -f "$PACKAGE_FILE"
        fail "No se pudo descargar AUROXLINK desde GitHub: $GITHUB_MAIN_ZIP"
    fi

    [[ -s "$PACKAGE_FILE" ]] || fail "La descarga desde GitHub quedó vacía."
    ok "Código AUROXLINK descargado desde GitHub."
fi

[[ -s "$PACKAGE_FILE" ]] || fail "No existe un paquete AUROXLINK válido para instalar."

# El panel web necesita poder enumerar dispositivos ALSA con aplay/arecord.
# En Debian/Raspberry Pi Apache corre como www-data, por lo que debe
# pertenecer al grupo audio. Sin esto Settings muestra "no soundcards found".
if getent group audio >/dev/null 2>&1; then
    usermod -aG audio www-data
    ok "Usuario www-data agregado al grupo audio."
else
    warn "No existe el grupo audio; no fue posible agregar www-data."
fi

# ============================================================
# 3. SONIDOS OFICIALES SVXLINK / ECHOLINK
# ============================================================

log "[3/13] Instalando paquete de sonidos SvxLink/EchoLink"

# Los módulos de SvxLink (incluido EchoLink) utilizan el árbol de sonidos
# de /usr/share/svxlink/sounds. Instalamos la voz oficial Heather en_US
# compatible con la versión base de svxlink-server instalada por APT.
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
        log "Descargando sonidos en_US Heather ${SOUNDS_RELEASE}"

        if wget -q --show-progress "$SOUNDS_URL" -O "/tmp/$SOUNDS_ARCHIVE"; then
            tar -xjf "/tmp/$SOUNDS_ARCHIVE" -C "$SOUNDS_DIR"
            rm -f "/tmp/$SOUNDS_ARCHIVE"
            ok "Sonidos SvxLink/EchoLink en_US ${SOUNDS_RELEASE} instalados."
        else
            rm -f "/tmp/$SOUNDS_ARCHIVE"
            warn "No se pudo descargar el paquete de sonidos ${SOUNDS_RELEASE}. La instalación continuará."
        fi
    else
        ok "Sonidos en_US Heather ya instalados."
    fi
else
    warn "No hay un paquete de sonidos mapeado para SvxLink ${SVX_PKG_VERSION:-desconocido}."
fi

# SvxLink espera normalmente el alias en_US.
if [[ -d "$SOUNDS_DIR/en_US-heather-16k" ]]; then
    ln -sfn en_US-heather-16k "$SOUNDS_DIR/en_US"
    ok "Alias de sonidos activo: $SOUNDS_DIR/en_US -> en_US-heather-16k"
fi

# ============================================================
# 4. EXTRAER ZIP
# ============================================================

log "[4/13] Extrayendo código AUROXLINK"

unzip -q "$PACKAGE_FILE" -d "$EXTRACT_DIR"

SOURCE_ROOT="$(detect_source_root "$EXTRACT_DIR")" \
    || fail "El paquete AUROXLINK no contiene index.php."

ok "Raíz detectada: $SOURCE_ROOT"

REQUIRED_FILES=(
    "$SOURCE_ROOT/index.php"
    "$SOURCE_ROOT/settings.php"
    "$SOURCE_ROOT/sismografo.php"
    "$SOURCE_ROOT/includes/environment.php"
    "$SOURCE_ROOT/includes/seismic-data.php"
    "$SOURCE_ROOT/includes/seismic-lib.php"
    "$SOURCE_ROOT/includes/save-seismic.php"
    "$SOURCE_ROOT/includes/test-seismic-rf.php"
)

for file in "${REQUIRED_FILES[@]}"; do
    [[ -f "$file" ]] || fail "Falta en el paquete AUROXLINK: ${file#$SOURCE_ROOT/}"
done

# ============================================================
# 5. INSTALACIÓN WEB LIMPIA
# ============================================================

log "[5/13] Instalando código AUROXLINK LIMPIO en ${APP_DIR}"

# Instalación nueva de AUROXLINK.
# Este instalador realiza una instalación limpia; las actualizaciones existentes
# deben hacerse mediante update_auroxlink.sh para preservar datos y configuración.
if [[ -d "$APP_DIR" ]] && find "$APP_DIR" -mindepth 1 -maxdepth 1 | grep -q .; then
    warn "Se eliminará el contenido existente de ${APP_DIR}; esta instalación es limpia."
fi

rm -rf "$APP_DIR"
mkdir -p "$APP_DIR"
cp -a "$SOURCE_ROOT"/. "$APP_DIR"/

mkdir -p \
    "$APP_DIR/data" \
    "$APP_DIR/data_actividades/historial" \
    "$APP_DIR/qsl" \
    "$APP_DIR/includes/logs" \
    "$APP_DIR/includes/backups" \
    "$APP_DIR/img" \
    /tmp/auroxlink_logs

# El ZIP/GitHub no debe entregar respaldos históricos en una instalación nueva.
# Dejamos el directorio vacío porque save-svx.php lo usa después para rollback
# transaccional al guardar cambios desde la Web.
find "$APP_DIR/includes/backups" -mindepth 1 -maxdepth 1 -exec rm -rf {} + 2>/dev/null || true

ok "Código local instalado desde cero, sin restaurar respaldos previos."

# ============================================================
# 6. PERMISOS WEB
# ============================================================

log "[6/13] Configurando permisos web"

# Política base AUROXLINK 1.8.5:
#   directorios 775, archivos 664, propietario ADMIN_USER:www-data.
# Evita los históricos 777/666 y mantiene escritura por grupo www-data.
chown -R "$ADMIN_USER":www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 775 {} +
find "$APP_DIR" -type f -exec chmod 664 {} +

# Solo los scripts que realmente se ejecutan conservan el bit ejecutable.
EXECUTABLE_SCRIPTS=(
    "$APP_DIR/install_auroxlink.sh"
    "$APP_DIR/install_svxlink_latest.sh"
    "$APP_DIR/svxlink_update_worker.sh"
    "$APP_DIR/update_auroxlink.sh"
)
for script in "${EXECUTABLE_SCRIPTS[@]}"; do
    [[ -f "$script" ]] && chmod 775 "$script"
done

# Componentes privilegiados del actualizador seguro de SvxLink.
# Se copian fuera del webroot y quedan bajo root para que la Web no pueda modificarlos.
SVX_LIBEXEC="/usr/local/libexec/auroxlink"
SVX_UPDATE_STATE="/var/lib/auroxlink/svxlink-update"

[[ -f "$APP_DIR/install_svxlink_latest.sh" ]] \
    || fail "Falta install_svxlink_latest.sh en el paquete AUROXLINK."
[[ -f "$APP_DIR/svxlink_update_worker.sh" ]] \
    || fail "Falta svxlink_update_worker.sh en el paquete AUROXLINK."

mkdir -p "$SVX_LIBEXEC" "$SVX_UPDATE_STATE"

install -o root -g root -m 0755 \
    "$APP_DIR/install_svxlink_latest.sh" \
    "$SVX_LIBEXEC/install_svxlink_latest.sh"

install -o root -g root -m 0755 \
    "$APP_DIR/svxlink_update_worker.sh" \
    "$SVX_LIBEXEC/svxlink_update_worker.sh"

if [[ -f "$APP_DIR/update_auroxlink.sh" ]]; then
    install -o root -g root -m 0755 \
        "$APP_DIR/update_auroxlink.sh" \
        "$SVX_LIBEXEC/update_auroxlink.sh"
fi

chown root:www-data "$SVX_UPDATE_STATE"
chmod 0750 "$SVX_UPDATE_STATE"

ok "Actualizador seguro de SvxLink instalado en $SVX_LIBEXEC."

WRITABLE_DIRS=(
    "$APP_DIR/qsl"
    "$APP_DIR/data"
    "$APP_DIR/data_actividades"
    "$APP_DIR/data_actividades/historial"
    "$APP_DIR/includes/logs"
    "$APP_DIR/img"
    "/tmp/auroxlink_logs"
)

for dir in "${WRITABLE_DIRS[@]}"; do
    mkdir -p "$dir"
    chown -R "$ADMIN_USER":www-data "$dir"
    # setgid: todo archivo nuevo hereda el grupo www-data
    chmod 2775 "$dir"
    find "$dir" -type f -exec chmod 664 {} + 2>/dev/null || true
done

mkdir -p "$APP_DIR/includes/backups"
chown -R "$ADMIN_USER":www-data "$APP_DIR/includes/backups"
chmod 2775 "$APP_DIR/includes/backups"
find "$APP_DIR/includes/backups" -type f -exec chmod 664 {} + 2>/dev/null || true

chmod 775 "$APP_DIR"

ok "Permisos web configurados."

# ============================================================
# 7. CONFIGURACIÓN SÍSMICA
# ============================================================

log "[7/13] Preparando configuración sísmica"

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
        "local": {
            "enabled": true,
            "radius_km": 200,
            "min_magnitude": 3.5
        },
        "regional": {
            "enabled": true,
            "radius_km": 500,
            "min_magnitude": 4.2
        },
        "wide": {
            "enabled": true,
            "radius_km": 1000,
            "min_magnitude": 5.0
        },
        "national": {
            "enabled": true,
            "min_magnitude": 6.0
        },
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

python3 -m json.tool "$SEISMIC_CONFIG" >/dev/null \
    || fail "seismic_config.json inválido."

python3 -m json.tool "$SEISMIC_STATE" >/dev/null \
    || fail "seismic_state.json inválido."

chown "$ADMIN_USER":www-data "$SEISMIC_CONFIG"
chmod 664 "$SEISMIC_CONFIG"

# seismicSaveJson() escribe primero un archivo temporal en data/ y luego
# hace rename(). El monitor corre como svxlink, por lo que data/ debe
# permitir escritura segura al grupo www-data y heredar dicho grupo.
chown "$ADMIN_USER":www-data "$APP_DIR/data"
chmod 2775 "$APP_DIR/data"

chown svxlink:www-data "$SEISMIC_STATE"
chmod 664 "$SEISMIC_STATE"

ok "Configuración sísmica preparada con permisos compartidos seguros."

# ============================================================
# 8. PIPER + VOZ
# ============================================================

log "[8/13] Instalando Piper y voz española"

mkdir -p /opt/auroxlink "$PIPER_VOICES"

if [[ ! -x "${PIPER_VENV}/bin/python" ]]; then
    python3 -m venv "$PIPER_VENV"
fi

"${PIPER_VENV}/bin/pip" install --upgrade pip
"${PIPER_VENV}/bin/pip" install --upgrade piper-tts

[[ -x "$PIPER_BIN" ]] || fail "Piper no quedó instalado."

if [[ ! -s "$PIPER_MODEL" ]]; then
    wget -q --show-progress "$PIPER_MODEL_URL" -O "${PIPER_MODEL}.tmp"
    mv "${PIPER_MODEL}.tmp" "$PIPER_MODEL"
fi

if [[ ! -s "$PIPER_MODEL_JSON" ]]; then
    wget -q "$PIPER_MODEL_JSON_URL" -O "${PIPER_MODEL_JSON}.tmp"
    mv "${PIPER_MODEL_JSON}.tmp" "$PIPER_MODEL_JSON"
fi

chmod -R a+rX "$PIPER_VOICES"
chmod -R a+rX "$PIPER_VENV"

sudo -u svxlink "$PIPER_BIN" --help >/dev/null 2>&1 \
    || fail "svxlink no puede ejecutar Piper."

sudo -u svxlink test -r "$PIPER_MODEL" \
    || fail "svxlink no puede leer el modelo Piper."

ok "Piper operativo."

# ============================================================
# 9. MONITOR + AUDIO
# ============================================================

log "[9/13] Instalando monitor sísmico"

MONITOR_SRC=""

for candidate in \
    "$APP_DIR/scripts/seismic-monitor.php" \
    "$APP_DIR/seismic-monitor.php"
do
    if [[ -f "$candidate" ]]; then
        MONITOR_SRC="$candidate"
        break
    fi
done

[[ -n "$MONITOR_SRC" ]] \
    || fail "El ZIP no contiene seismic-monitor.php."

mkdir -p /opt/auroxlink/scripts

install \
    -o root \
    -g root \
    -m 0755 \
    "$MONITOR_SRC" \
    "$SEISMIC_MONITOR_DST"

php -l "$SEISMIC_MONITOR_DST" >/dev/null \
    || fail "seismic-monitor.php tiene errores."

mkdir -p "$SEISMIC_AUDIO_DIR"
chown svxlink:svxlink "$SEISMIC_AUDIO_DIR"
chmod 775 "$SEISMIC_AUDIO_DIR"

ok "Monitor sísmico instalado."

# ============================================================
# 10. COMMAND_PTY
# ============================================================

log "[10/13] Configurando COMMAND_PTY"

[[ -f "$SVXLINK_CONF" ]] || fail "No existe $SVXLINK_CONF"

python3 - "$SVXLINK_CONF" "$COMMAND_PTY" <<'PY'
import re
import sys
from pathlib import Path

path = Path(sys.argv[1])
pty = sys.argv[2]

text = path.read_text(encoding="utf-8", errors="replace")
lines = text.splitlines()

# Detectar LOGICS= en [GLOBAL]. No inventamos una lógica incompleta.
active_logic = None
section = None

for line in lines:
    stripped = line.strip()

    if stripped.startswith("[") and stripped.endswith("]"):
        section = stripped[1:-1].strip()
        continue

    if section == "GLOBAL":
        cleaned = stripped.lstrip("#").strip()
        if cleaned.startswith("LOGICS="):
            value = cleaned.split("=", 1)[1].strip()
            logics = [x.strip() for x in re.split(r"[,;]", value) if x.strip()]
            if logics:
                active_logic = logics[0]
                break

# Compatibilidad con instalaciones AUROXLINK existentes.
if not active_logic and re.search(r"(?m)^\[SimplexLogic\]\s*$", text):
    active_logic = "SimplexLogic"

if not active_logic:
    raise SystemExit(
        "No se pudo detectar la lógica activa de SvxLink. "
        "Configure LOGICS= en [GLOBAL] antes de activar COMMAND_PTY."
    )

if not re.search(rf"(?m)^\[{re.escape(active_logic)}\]\s*$", text):
    raise SystemExit(
        f"LOGICS apunta a '{active_logic}', pero no existe la sección [{active_logic}] "
        "en svxlink.conf."
    )

out = []
section = None
inserted = False

for line in lines:
    stripped = line.strip()

    if stripped.startswith("[") and stripped.endswith("]"):
        if section == active_logic and not inserted:
            out.append(f"COMMAND_PTY={pty}")
            inserted = True

        section = stripped[1:-1].strip()
        out.append(line)
        continue

    if section == active_logic:
        cleaned = stripped.lstrip("#").strip()
        if cleaned.startswith("COMMAND_PTY="):
            if not inserted:
                out.append(f"COMMAND_PTY={pty}")
                inserted = True
            continue

    out.append(line)

if section == active_logic and not inserted:
    out.append(f"COMMAND_PTY={pty}")
    inserted = True

if not inserted:
    raise SystemExit(f"No se pudo escribir COMMAND_PTY en [{active_logic}].")

path.write_text("\n".join(out) + "\n", encoding="utf-8")
print(f"COMMAND_PTY configurado en lógica activa: [{active_logic}]")
PY

# Archivos de configuración editables desde AUROXLINK Web.
chown root:www-data "$SVXLINK_CONF"
chmod 664 "$SVXLINK_CONF"

ECHOLINK_CONF="/etc/svxlink/svxlink.d/ModuleEchoLink.conf"
if [[ -f "$ECHOLINK_CONF" ]]; then
    chown root:www-data "$ECHOLINK_CONF"
    chmod 664 "$ECHOLINK_CONF"
fi

# El directorio se mantiene administrado por root, pero accesible al grupo web.
if [[ -d /etc/svxlink/svxlink.d ]]; then
    chown root:www-data /etc/svxlink/svxlink.d
    chmod 775 /etc/svxlink/svxlink.d
fi

ok "COMMAND_PTY y permisos de configuración SvxLink/EchoLink preparados."

# ============================================================
# 11. SYSTEMD + SUDOERS
# ============================================================

log "[11/13] Creando servicios systemd"

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

cat > "$SEISMIC_SUDOERS" <<'SUDOERS'
# AUROXLINK - prueba RF Sismógrafo
www-data ALL=(svxlink) NOPASSWD: /usr/bin/php /opt/auroxlink/scripts/seismic-monitor.php --test-rf
SUDOERS

chmod 440 "$SEISMIC_SUDOERS"
visudo -cf "$SEISMIC_SUDOERS" >/dev/null \
    || fail "Error en sudoers sísmico."

# Sudoers principal del panel.
cat > "$SUDOERS_FILE" <<'SUDOERS'
# AUROXLINK - permisos requeridos por el panel web
www-data ALL=(root) NOPASSWD: /bin/systemctl start svxlink
www-data ALL=(root) NOPASSWD: /bin/systemctl stop svxlink
www-data ALL=(root) NOPASSWD: /bin/systemctl restart svxlink
www-data ALL=(root) NOPASSWD: /bin/systemctl reset-failed svxlink
www-data ALL=(root) NOPASSWD: /bin/systemctl is-active --quiet svxlink
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl start svxlink
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl stop svxlink
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl restart svxlink
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl reset-failed svxlink
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl is-active --quiet svxlink
www-data ALL=(root) NOPASSWD: /sbin/reboot
www-data ALL=(root) NOPASSWD: /usr/sbin/reboot
www-data ALL=(root) NOPASSWD: /usr/bin/nmcli
www-data ALL=(root) NOPASSWD: /sbin/iwlist
www-data ALL=(root) NOPASSWD: /usr/sbin/iwlist
www-data ALL=(root) NOPASSWD: /usr/bin/amixer
www-data ALL=(root) NOPASSWD: /usr/bin/alsactl
www-data ALL=(root) NOPASSWD: /usr/sbin/alsactl
www-data ALL=(root) NOPASSWD: /sbin/alsactl
www-data ALL=(root) NOPASSWD: /usr/bin/tailscale
www-data ALL=(root) NOPASSWD: /usr/sbin/tailscale
www-data ALL=(root) NOPASSWD: /usr/bin/bash /usr/local/libexec/auroxlink/update_auroxlink.sh
www-data ALL=(root) NOPASSWD: /usr/bin/bash /usr/local/libexec/auroxlink/svxlink_update_worker.sh
SUDOERS

chmod 440 "$SUDOERS_FILE"
visudo -cf "$SUDOERS_FILE" >/dev/null \
    || fail "Error en sudoers principal."

ok "Systemd y sudoers preparados."

# ============================================================
# 12. SERVICIOS
# ============================================================

log "[12/13] Activando servicios"

systemctl daemon-reload

systemctl enable apache2.service
systemctl restart apache2.service

systemctl enable cron.service
systemctl restart cron.service

systemctl enable svxlink.service || true

if ! systemctl restart svxlink.service; then
    warn "SvxLink no inició. Configura audio/CALLSIGN y vuelve a probar."
fi

systemctl enable auroxlink-seismic.timer
systemctl restart auroxlink-seismic.timer

sleep 2

if [[ -e "$COMMAND_PTY" ]]; then
    ok "COMMAND_PTY activo: $COMMAND_PTY"
else
    warn "COMMAND_PTY todavía no apareció."
fi

if systemctl start auroxlink-seismic.service; then
    ok "Primera ejecución del monitor sísmico completada."
else
    warn "La primera ejecución sísmica falló. Revisar journal."
fi

apache2ctl configtest >/dev/null \
    || fail "Apache presenta errores de configuración."

ok "Servicios activados."

# Instalación limpia 1.8.5: dejar marcada la migración base como completa.
mkdir -p /var/lib/auroxlink/migrations
touch /var/lib/auroxlink/migrations/1.8.5.done
chmod 755 /var/lib/auroxlink/migrations
chmod 644 /var/lib/auroxlink/migrations/1.8.5.done
ok "Migración base AUROXLINK 1.8.5 marcada como completa."

# ============================================================
# 13. VERIFICACIÓN FINAL
# ============================================================

log "[13/13] Verificación final"

FAILS=0

check_file() {
    local f="$1"
    local name="$2"

    if [[ -f "$f" ]]; then
        ok "$name"
    else
        warn "$name: falta"
        FAILS=1
    fi
}

check_file "$APP_DIR/index.php" "AUROXLINK Web"
check_file "$APP_DIR/settings.php" "Settings"
check_file "$APP_DIR/sismografo.php" "Sismógrafo"
check_file "$APP_DIR/includes/seismic-data.php" "Backend sísmico CSN/USGS"
check_file "$APP_DIR/includes/seismic-lib.php" "Librería sísmica"
check_file "$APP_DIR/includes/save-seismic.php" "Guardado sísmico"
check_file "$APP_DIR/includes/test-seismic-rf.php" "Prueba RF"
check_file "$SEISMIC_MONITOR_DST" "Monitor sísmico"
check_file "$SEISMIC_CONFIG" "Configuración sísmica"
check_file "$SEISMIC_STATE" "Estado sísmico"
check_file "$PIPER_MODEL" "Voz Piper"
check_file "$PIPER_MODEL_JSON" "JSON voz Piper"
check_file "$SEISMIC_SERVICE" "Servicio sísmico"
check_file "$SEISMIC_TIMER" "Timer sísmico"

if php -r 'exit(class_exists("DOMDocument") ? 0 : 1);'; then
    ok "PHP DOM/XML"
else
    warn "PHP DOM/XML no disponible"
    FAILS=1
fi

if sudo -u svxlink "$PIPER_BIN" --help >/dev/null 2>&1; then
    ok "Piper accesible por svxlink"
else
    warn "Piper no accesible por svxlink"
    FAILS=1
fi

if systemctl is-enabled auroxlink-seismic.timer >/dev/null 2>&1; then
    ok "Timer sísmico habilitado"
else
    warn "Timer sísmico NO habilitado"
    FAILS=1
fi

if systemctl is-active auroxlink-seismic.timer >/dev/null 2>&1; then
    ok "Timer sísmico activo"
else
    warn "Timer sísmico NO activo"
    FAILS=1
fi

if systemctl show auroxlink-seismic.service -p SupplementaryGroups --value 2>/dev/null | grep -qw 'www-data'; then
    ok "Servicio sísmico con grupo suplementario www-data"
else
    warn "Falta SupplementaryGroups=www-data en el servicio sísmico"
    FAILS=1
fi

WRITE_TEST="$APP_DIR/data/.auroxlink-seismic-write-test-$$"
if runuser -u svxlink -g www-data -- touch "$WRITE_TEST" 2>/dev/null; then
    rm -f "$WRITE_TEST"
    ok "svxlink puede crear archivos temporales en data/"
else
    warn "svxlink NO puede escribir en $APP_DIR/data"
    FAILS=1
fi

# Validaciones de escritura que necesita el panel web desde una instalación limpia.
for target in \
    "$APP_DIR/includes/logs" \
    "$APP_DIR/includes/backups" \
    "$APP_DIR/data" \
    "$SVXLINK_CONF" \
    "/etc/svxlink/svxlink.d/ModuleEchoLink.conf"; do
    if [[ -e "$target" ]] && runuser -u www-data -- test -w "$target"; then
        ok "www-data puede escribir: $target"
    elif [[ ! -e "$target" ]]; then
        warn "No existe para validar: $target"
        FAILS=1
    else
        warn "www-data NO puede escribir: $target"
        FAILS=1
    fi
done

# Verificar instalación de sonidos SvxLink/EchoLink.
if [[ -d "/usr/share/svxlink/sounds/en_US-heather-16k" ]] && [[ -L "/usr/share/svxlink/sounds/en_US" ]]; then
    ok "Sonidos SvxLink/EchoLink en_US instalados y enlazados"
else
    warn "Paquete de sonidos SvxLink/EchoLink en_US no disponible"
fi

# El panel Settings debe poder enumerar tarjetas de audio como www-data.
if id -nG www-data 2>/dev/null | tr ' ' '\n' | grep -qx 'audio'; then
    ok "www-data pertenece al grupo audio"
else
    warn "www-data NO pertenece al grupo audio"
    FAILS=1
fi

if runuser -u www-data -- aplay -l >/dev/null 2>&1 || runuser -u www-data -- arecord -l >/dev/null 2>&1; then
    ok "www-data puede consultar dispositivos ALSA"
else
    # No se marca como fallo fatal: una instalación puede no tener USB de audio conectado.
    warn "www-data no detecta tarjetas ALSA en este momento (puede no haber audio conectado)."
fi

# save-svx.php usa estas órdenes. Deben funcionar sin pedir contraseña.
if runuser -u www-data -- sudo -n /usr/bin/systemctl is-active --quiet svxlink >/dev/null 2>&1; then
    ok "sudoers web: systemctl is-active permitido sin contraseña"
else
    rc=$?
    # RC=3 significa servicio inactivo, pero sudoers sí permitió ejecutar la orden.
    if [[ $rc -eq 3 ]]; then
        ok "sudoers web: is-active permitido (SvxLink actualmente inactivo)"
    else
        warn "sudoers web: is-active no está autorizado correctamente (RC=$rc)"
        FAILS=1
    fi
fi

# save-svx.php también limpia el estado failed antes de reiniciar.
if runuser -u www-data -- sudo -n /usr/bin/systemctl reset-failed svxlink >/dev/null 2>&1; then
    ok "sudoers web: systemctl reset-failed permitido sin contraseña"
else
    warn "sudoers web: reset-failed no está autorizado correctamente"
    FAILS=1
fi

# El módulo web de actualización de SvxLink depende de copias protegidas en libexec.
if [[ -x "/usr/local/libexec/auroxlink/svxlink_update_worker.sh" ]]; then
    ok "Worker protegido de actualización SvxLink instalado"
else
    warn "Falta worker protegido de actualización SvxLink"
    FAILS=1
fi

if [[ -x "/usr/local/libexec/auroxlink/install_svxlink_latest.sh" ]]; then
    ok "Instalador protegido de SvxLink instalado"
else
    warn "Falta instalador protegido de SvxLink"
    FAILS=1
fi

if runuser -u www-data -- sudo -n -l /usr/bin/bash /usr/local/libexec/auroxlink/update_auroxlink.sh >/dev/null 2>&1; then
    ok "sudoers web: actualizador AUROXLINK autorizado sin contraseña"
else
    warn "sudoers web: actualizador AUROXLINK no autorizado correctamente"
    FAILS=1
fi

if runuser -u www-data -- sudo -n -l /usr/bin/bash /usr/local/libexec/auroxlink/svxlink_update_worker.sh >/dev/null 2>&1; then
    ok "sudoers web: worker SvxLink autorizado sin contraseña"
else
    warn "sudoers web: worker SvxLink no autorizado correctamente"
    FAILS=1
fi

if [[ -f /var/lib/auroxlink/migrations/1.8.5.done ]]; then
    ok "Migración base AUROXLINK 1.8.5 completa"
else
    warn "Falta marcador de migración AUROXLINK 1.8.5"
    FAILS=1
fi

# Validar que el JSON nuevo realmente incluye proveedor multifuente.
if python3 - "$SEISMIC_CONFIG" <<'PYCFG'
import json, sys
with open(sys.argv[1], encoding="utf-8") as f:
    cfg = json.load(f)
provider = str(cfg.get("provider", "auto")).lower()
raise SystemExit(0 if provider in {"auto", "csn", "usgs"} else 1)
PYCFG
then
    ok "Proveedor sísmico válido: AUTO / CSN / USGS"
else
    warn "Proveedor sísmico inválido en seismic_config.json"
    FAILS=1
fi

LOCAL_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"

printf '\n============================================================\n'

if (( FAILS == 0 )); then
    printf '🎉 AUROXLINK 1.8.5 INSTALADO CORRECTAMENTE\n'
else
    printf '⚠️  AUROXLINK instalado con advertencias\n'
fi

printf '============================================================\n'
printf 'Fuente          : %s\n' "$SOURCE_DESC"
printf 'Web             : http://%s/\n' "${LOCAL_IP:-IP_DEL_NODO}"
printf 'Sismógrafo      : http://%s/sismografo.php\n' "${LOCAL_IP:-IP_DEL_NODO}"
printf 'Settings        : http://%s/settings.php#seismic-settings\n' "${LOCAL_IP:-IP_DEL_NODO}"
printf 'Piper           : %s\n' "$PIPER_MODEL_NAME"
printf 'Modelo          : %s\n' "$PIPER_MODEL"
printf 'Audio RF        : 16 kHz / mono / 16-bit\n'
printf 'COMMAND_PTY     : %s\n' "$COMMAND_PTY"
printf 'Monitor         : %s\n' "$SEISMIC_MONITOR_DST"
printf 'Timer           : cada 60 segundos\n'
printf 'Proveedor       : AUTO por defecto (CSN Chile / USGS mundial)\n'
printf 'RF automático   : desactivado por defecto en instalación nueva\n'
printf '\n'
printf 'Diagnóstico:\n'
printf '  systemctl status svxlink --no-pager\n'
printf '  systemctl status auroxlink-seismic.timer --no-pager\n'
printf '  systemctl status auroxlink-seismic.service --no-pager\n'
printf '  systemctl list-timers auroxlink-seismic.timer --all\n'
printf '  journalctl -u auroxlink-seismic.service -n 50 --no-pager\n'
printf '\n'
printf 'Prueba RF manual:\n'
printf '  sudo -u svxlink php /opt/auroxlink/scripts/seismic-monitor.php --test-rf\n'
printf '\n'
printf '============================================================\n'
printf '\n'
printf 'Gracias por usar AUROXLINK.\n'
printf 'Proyecto desarrollado por Román Carvajal Rodríguez · CE2RDP · Telecoviajero.\n'
printf '\n'
printf 'Si AUROXLINK te resulta útil y quieres aportar al desarrollo y mantenimiento\n'
printf 'de este y otros proyectos, puedes apoyar suscribiéndote como Miembro del\n'
printf 'canal de YouTube Telecoviajero.\n'
printf '\n'
printf 'YouTube: https://www.youtube.com/@Telecoviajero\n'
printf '\n'
printf '73 de CE2RDP y gracias por ser parte de AUROXLINK.\n'
printf '============================================================\n'

rm -rf "$WORK_DIR"
