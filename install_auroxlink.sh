#!/usr/bin/env bash
set -Eeuo pipefail

# =========================================================
# AUROXLINK - Instalador oficial v1.7+
# Compatible con Debian / Raspberry Pi OS / Ubuntu / Armbian
# =========================================================

APP_NAME="AUROXLINK"
REPO_URL="https://github.com/telecov/auroxlink.git"
BRANCH="main"
APP_DIR="/var/www/html"
SUDOERS_FILE="/etc/sudoers.d/99-www-data-svxlink"
SERVICE_FILE="/etc/systemd/system/auroralink-monitor.service"
CRON_FILE="/etc/cron.d/auroxlink"
APT_TIMEOUT=180
BACKUP_DIR="/var/www/backup_auroxlink_preinstall_$(date +%Y%m%d_%H%M%S)"
EXISTING_AUROXLINK=0

log()  { printf '\n\033[1;36m%s\033[0m\n' "$*"; }
ok()   { printf '\033[1;32m✅ %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m⚠️  %s\033[0m\n' "$*"; }
fail() { printf '\033[1;31m❌ %s\033[0m\n' "$*" >&2; exit 1; }

trap 'printf "\n❌ Error en la línea %s. Instalación detenida.\n" "$LINENO" >&2' ERR

if [[ ${EUID} -ne 0 ]]; then
  fail "Ejecuta el instalador como root, por ejemplo: curl -fsSL ... | sudo bash"
fi

ADMIN_USER="${SUDO_USER:-root}"
if ! id "$ADMIN_USER" >/dev/null 2>&1; then
  ADMIN_USER="root"
fi
ADMIN_GROUP="$(id -gn "$ADMIN_USER" 2>/dev/null || echo root)"

cat <<'BANNER'
    _   _   _ ____   _____  __ _     ___ _   _ _  __
   / \ | | | |  _ \ / _ \ \/ /| |   |_ _| \ | | |/ /
  / _ \| | | | |_) | | | \  / | |    | ||  \| | ' / 
 / ___ \ |_| |  _ <| |_| /  \ | |___ | || |\  | . \ 
/_/   \_\___/|_| \_\\___/_/\_\|_____|___|_| \_|_|\_\

              Instalador oficial AUROXLINK
BANNER

log "[1/13] Validando sistema operativo"
[[ -r /etc/os-release ]] || fail "No se encontró /etc/os-release"
# shellcheck disable=SC1091
source /etc/os-release
DISTRO="${PRETTY_NAME:-${ID:-Linux}}"
case " ${ID:-} ${ID_LIKE:-} " in
  *debian*|*ubuntu*) ok "Sistema compatible detectado: $DISTRO" ;;
  *) warn "Sistema no identificado como Debian/Ubuntu: $DISTRO. Se intentará continuar con APT." ;;
esac
command -v apt-get >/dev/null 2>&1 || fail "APT no está disponible en este sistema."

wait_for_apt() {
  local waited=0
  if ! command -v fuser >/dev/null 2>&1; then
    return 0
  fi
  while fuser /var/lib/dpkg/lock >/dev/null 2>&1 || \
        fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 || \
        fuser /var/lib/apt/lists/lock >/dev/null 2>&1 || \
        fuser /var/cache/apt/archives/lock >/dev/null 2>&1; do
    if (( waited >= APT_TIMEOUT )); then
      fail "APT/DPKG sigue bloqueado después de ${APT_TIMEOUT}s."
    fi
    printf '⏳ Esperando APT/DPKG... %ss/%ss\r' "$waited" "$APT_TIMEOUT"
    sleep 5
    waited=$((waited + 5))
  done
  printf '\n'
}

apt_update_safe() {
  wait_for_apt
  DEBIAN_FRONTEND=noninteractive apt-get update -y -o DPkg::Lock::Timeout="$APT_TIMEOUT"
}

apt_install_safe() {
  wait_for_apt
  dpkg --configure -a || true
  wait_for_apt
  DEBIAN_FRONTEND=noninteractive apt-get install -y -o DPkg::Lock::Timeout="$APT_TIMEOUT" "$@"
}

log "[2/13] Instalando dependencias de AUROXLINK v1.7"
apt_update_safe
apt_install_safe \
  apache2 \
  php php-cli php-curl libapache2-mod-php \
  git curl wget unzip bzip2 ca-certificates lsb-release psmisc cron sudo \
  network-manager wireless-tools alsa-utils \
  iproute2 procps usbutils \
  svxlink-server

command -v php >/dev/null 2>&1 || fail "PHP no quedó instalado."
php -m | grep -qi '^curl$' || fail "La extensión PHP cURL no está cargada."
command -v svxlink >/dev/null 2>&1 || fail "SVXLink no quedó instalado."
ok "Dependencias instaladas. PHP $(php -r 'echo PHP_VERSION;')"

log "[3/13] Instalando/actualizando Tailscale"
if ! command -v tailscale >/dev/null 2>&1; then
  if curl -fsSL https://tailscale.com/install.sh | sh; then
    ok "Tailscale instalado."
  else
    warn "No se pudo instalar Tailscale. AUROXLINK continuará sin VPN."
  fi
else
  ok "Tailscale ya está instalado: $(tailscale version 2>/dev/null | head -n1 || true)"
fi
if systemctl list-unit-files 2>/dev/null | grep -q '^tailscaled.service'; then
  systemctl enable --now tailscaled.service || warn "No se pudo iniciar tailscaled."
fi

log "[4/13] Preparando respaldo e instalación web"
if [[ -f "$APP_DIR/version.txt" || -f "$APP_DIR/includes/environment.php" || -d "$APP_DIR/.git" ]]; then
  EXISTING_AUROXLINK=1
  mkdir -p "$BACKUP_DIR"
  cp -a "$APP_DIR"/. "$BACKUP_DIR"/
  ok "Instalación anterior respaldada en $BACKUP_DIR"
fi

rm -rf "$APP_DIR"
git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
[[ -f "$APP_DIR/index.php" ]] || fail "El repositorio descargado no contiene index.php."
[[ -f "$APP_DIR/version.txt" ]] || fail "El repositorio descargado no contiene version.txt."
VERSION="$(tr -d '[:space:]' < "$APP_DIR/version.txt")"
ok "Código AUROXLINK v${VERSION} descargado desde main."

log "[5/13] Inicializando y/o restaurando datos de AUROXLINK"
mkdir -p \
  "$APP_DIR/data" \
  "$APP_DIR/data_actividades/historial" \
  "$APP_DIR/qsl" \
  "$APP_DIR/includes/logs" \
  "$APP_DIR/img" \
  /tmp/auroxlink_logs

copy_default_if_present() {
  local src="$1" dst="$2"
  if [[ -f "$src" ]]; then
    mkdir -p "$(dirname "$dst")"
    cp -f "$src" "$dst"
  fi
}

if (( EXISTING_AUROXLINK == 0 )); then
  # En una instalación nueva se usan plantillas limpias, no la configuración del desarrollador.
  copy_default_if_present "$APP_DIR/defaults/telegram_config.json" "$APP_DIR/telegram_config.json"
  copy_default_if_present "$APP_DIR/defaults/estilos.json" "$APP_DIR/estilos.json"
  copy_default_if_present "$APP_DIR/defaults/eventos.json" "$APP_DIR/data/eventos.json"
  copy_default_if_present "$APP_DIR/defaults/qsls.json" "$APP_DIR/data/qsls.json"
else
  # Preservar configuración y datos del nodo existente.
  PRESERVE_FILES=(
    "telegram_config.json"
    "estilos.json"
    "data/eventos.json"
    "data/qsls.json"
    "img/admin.png"
  )
  for rel in "${PRESERVE_FILES[@]}"; do
    if [[ -f "$BACKUP_DIR/$rel" ]]; then
      mkdir -p "$(dirname "$APP_DIR/$rel")"
      cp -f "$BACKUP_DIR/$rel" "$APP_DIR/$rel"
      ok "Restaurado: $rel"
    fi
  done

  for rel in data_actividades qsl; do
    if [[ -d "$BACKUP_DIR/$rel" ]]; then
      rm -rf "$APP_DIR/$rel"
      cp -a "$BACKUP_DIR/$rel" "$APP_DIR/$rel"
      ok "Restaurado directorio: $rel"
    fi
  done

  # La personalización v1.7 guarda el banner en la raíz de AUROXLINK.
  shopt -s nullglob
  for banner in "$BACKUP_DIR"/auroxlink_banner.*; do
    cp -f "$banner" "$APP_DIR/$(basename "$banner")"
    ok "Restaurado: $(basename "$banner")"
  done
  for admin_img in "$BACKUP_DIR"/img/admin.*; do
    cp -f "$admin_img" "$APP_DIR/img/$(basename "$admin_img")"
  done
  shopt -u nullglob
fi

# Fallbacks por si faltan archivos en una instalación o repositorio futuro.
[[ -f "$APP_DIR/telegram_config.json" ]] || printf '{\n    "token": "",\n    "chat_id": ""\n}\n' > "$APP_DIR/telegram_config.json"
[[ -f "$APP_DIR/data/eventos.json" ]] || printf '[]\n' > "$APP_DIR/data/eventos.json"
[[ -f "$APP_DIR/data/qsls.json" ]] || printf '[]\n' > "$APP_DIR/data/qsls.json"

log "[6/13] Instalando paquete de sonidos SVXLink en inglés"
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
      ok "Sonidos en_US ${SOUNDS_RELEASE} instalados."
    else
      rm -f "/tmp/$SOUNDS_ARCHIVE"
      warn "No se pudo descargar el paquete de sonidos ${SOUNDS_RELEASE}. La instalación continuará."
    fi
  fi
else
  warn "No hay un paquete de sonidos mapeado para SVXLink ${SVX_PKG_VERSION:-desconocido}. Se omite."
fi
if [[ -d "$SOUNDS_DIR/en_US-heather-16k" ]]; then
  ln -sfn en_US-heather-16k "$SOUNDS_DIR/en_US"
fi

log "[7/13] Configurando permisos web y SVXLink"
# Código: administrable por el usuario que ejecutó sudo, legible por Apache.
chown -R "$ADMIN_USER":www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR" -type f -exec chmod 644 {} +
chmod 755 "$APP_DIR/update_auroxlink.sh" 2>/dev/null || true
chmod 755 "$APP_DIR/install_auroxlink.sh" 2>/dev/null || true

# La aplicación v1.7 escribe estos archivos/directorios desde PHP.
WRITABLE_DIRS=(
  "$APP_DIR/qsl"
  "$APP_DIR/data_actividades"
  "$APP_DIR/data_actividades/historial"
  "$APP_DIR/includes/logs"
  "$APP_DIR/img"
  "/tmp/auroxlink_logs"
)
for dir in "${WRITABLE_DIRS[@]}"; do
  mkdir -p "$dir"
  chown -R www-data:www-data "$dir"
  chmod 775 "$dir"
done

WRITABLE_FILES=(
  "$APP_DIR/telegram_config.json"
  "$APP_DIR/estilos.json"
  "$APP_DIR/data/eventos.json"
  "$APP_DIR/data/qsls.json"
  "$APP_DIR/includes/environment.php"
)
for file in "${WRITABLE_FILES[@]}"; do
  if [[ -f "$file" ]]; then
    chown www-data:www-data "$file"
    chmod 664 "$file"
  fi
done

# custom.php puede subir el banner directamente en la raíz.
chmod 775 "$APP_DIR"

# Permitir al panel editar SVXLink manteniendo root como propietario.
for conf in /etc/svxlink/svxlink.conf /etc/svxlink/svxlink.d/ModuleEchoLink.conf; do
  if [[ -f "$conf" ]]; then
    chown root:www-data "$conf"
    chmod 664 "$conf"
    touch "$conf.bak"
    chown root:www-data "$conf.bak"
    chmod 664 "$conf.bak"
  fi
done

usermod -aG audio www-data || true
if getent group svxlink >/dev/null 2>&1; then
  usermod -aG svxlink www-data || true
fi

if [[ -f /var/log/svxlink ]]; then
  chmod 644 /var/log/svxlink || true
fi

log "[8/13] Configurando Tailscale para uso desde AUROXLINK"
mkdir -p /etc/auroxlink
chown root:www-data /etc/auroxlink
chmod 770 /etc/auroxlink
if [[ -f /etc/auroxlink/tailscale.key ]]; then
  chown root:www-data /etc/auroxlink/tailscale.key
  chmod 660 /etc/auroxlink/tailscale.key
fi

log "[9/13] Configurando sudoers de AUROXLINK"
cat > "$SUDOERS_FILE" <<'SUDOERS'
# AUROXLINK - permisos requeridos por el panel web
www-data ALL=(root) NOPASSWD: /bin/systemctl start svxlink
www-data ALL=(root) NOPASSWD: /bin/systemctl stop svxlink
www-data ALL=(root) NOPASSWD: /bin/systemctl restart svxlink
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl start svxlink
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl stop svxlink
www-data ALL=(root) NOPASSWD: /usr/bin/systemctl restart svxlink
www-data ALL=(root) NOPASSWD: /sbin/reboot
www-data ALL=(root) NOPASSWD: /usr/sbin/reboot
www-data ALL=(root) NOPASSWD: /usr/bin/nmcli
www-data ALL=(root) NOPASSWD: /sbin/iwlist
www-data ALL=(root) NOPASSWD: /usr/sbin/iwlist
www-data ALL=(root) NOPASSWD: /usr/bin/amixer
www-data ALL=(root) NOPASSWD: /usr/bin/alsactl
www-data ALL=(root) NOPASSWD: /usr/bin/tailscale
www-data ALL=(root) NOPASSWD: /usr/sbin/tailscale
www-data ALL=(root) NOPASSWD: /usr/bin/bash /tmp/update_auroxlink.sh
SUDOERS
chmod 440 "$SUDOERS_FILE"
visudo -cf "$SUDOERS_FILE" >/dev/null || fail "Error de sintaxis en $SUDOERS_FILE"
ok "sudoers validado correctamente."

log "[10/13] Creando servicio auroralink-monitor"
cat > "$SERVICE_FILE" <<'SERVICE'
[Unit]
Description=AUROXLINK - Monitor de conexiones SVXLink
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

log "[11/13] Configurando estado diario por cron"
cat > "$CRON_FILE" <<'CRON'
# AUROXLINK - estado diario por Telegram
0 12 * * * www-data /usr/bin/php /var/www/html/send_daily_status.php >> /tmp/estado_diario_cron.log 2>&1
CRON
chmod 644 "$CRON_FILE"

log "[12/13] Habilitando servicios"
systemctl daemon-reload
systemctl enable apache2.service
systemctl restart apache2.service
systemctl enable cron.service
systemctl restart cron.service
systemctl enable svxlink.service || true
if ! systemctl restart svxlink.service; then
  warn "SVXLink no inició. Esto puede ser normal hasta configurar audio/EchoLink desde AUROXLINK."
fi
if [[ -f /var/log/svxlink ]]; then
  chmod 644 /var/log/svxlink || true
fi

systemctl enable auroralink-monitor.service
if ! systemctl restart auroralink-monitor.service; then
  warn "auroralink-monitor no inició. Revisa /var/log/svxlink y la configuración de SVXLink."
fi

apache2ctl configtest >/dev/null || fail "La configuración de Apache tiene errores."

log "[13/13] Verificación final"
[[ -f "$APP_DIR/index.php" ]] || fail "Falta index.php"
[[ -f "$APP_DIR/telegram_config.json" ]] || fail "Falta telegram_config.json"
[[ -f "$APP_DIR/estilos.json" ]] || fail "Falta estilos.json"
[[ -f "$APP_DIR/data/eventos.json" ]] || fail "Falta data/eventos.json"
[[ -f "$APP_DIR/data/qsls.json" ]] || fail "Falta data/qsls.json"

LOCAL_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
COMMIT="$(git -C "$APP_DIR" rev-parse --short HEAD 2>/dev/null || echo n/a)"

printf '\n=========================================================\n'
printf '🎉 FELICIDADES — AUROXLINK v%s está instalado y listo.\n' "$VERSION"
printf '=========================================================\n'
printf 'Versión : %s\n' "$VERSION"
printf 'Commit  : %s\n' "$COMMIT"
printf 'Ruta    : %s\n' "$APP_DIR"
printf 'Usuario : %s\n' "$ADMIN_USER"
if [[ -n "$LOCAL_IP" ]]; then
  printf 'Web     : http://%s/\n' "$LOCAL_IP"
fi
printf '\nClave inicial del panel: admin123\n'
printf 'Cámbiala inmediatamente desde la configuración de AUROXLINK.\n'
printf '\nTailscale queda instalado pero no conectado hasta que lo configures.\n'
printf 'Si SVXLink no inició, configura primero CALLSIGN, EchoLink y audio.\n'

