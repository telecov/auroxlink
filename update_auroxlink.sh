#!/bin/bash
set -euo pipefail

echo "===> [AUROXLINK] Iniciando actualización de nueva versión..."

APP_DIR="/var/www/html"
BACKUP_DIR="/var/www/backup_auroxlink_$(date +%Y%m%d_%H%M)"
PENDRIVE_DIR="/mnt/usb"
ZIP_LOCAL="$PENDRIVE_DIR/auroxlink_v1.7.zip"
ZIP_TMP="/tmp/auroxlink_v1.7.zip"
TMP_DIR="/tmp/auroxlink_temp"
GITHUB_URL="https://github.com/telecov/auroxlink/releases/download/v1.7/auroxlink_v1.7.zip"
SUDOERS_FILE="/etc/sudoers.d/99-www-data-svxlink"

PRESERVAR=(
  "telegram_config.json"
  "estilos.json"
  "data/qsls.json"
  "img/auroxlink_banner.png"
  "img/admin.png"
)

PERMISOS=(
  "/usr/bin/alsactl"
  "/usr/bin/tailscale"
  "/usr/sbin/tailscale"
)

APT_TIMEOUT=180

log() {
  echo -e "$1"
}

fail() {
  log "❌ $1"
  exit 1
}

wait_for_apt() {
  local timeout="${1:-180}"
  local waited=0

  while sudo fuser /var/lib/dpkg/lock >/dev/null 2>&1 || \
        sudo fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1 || \
        sudo fuser /var/lib/apt/lists/lock >/dev/null 2>&1 || \
        sudo fuser /var/cache/apt/archives/lock >/dev/null 2>&1; do
    log "⏳ Esperando que APT/DPKG queden libres... (${waited}s/${timeout}s)"
    sleep 5
    waited=$((waited + 5))

    if [ "$waited" -ge "$timeout" ]; then
      fail "Tiempo de espera agotado: APT/DPKG siguen bloqueados."
    fi
  done
}

apt_update_safe() {
  wait_for_apt "$APT_TIMEOUT"
  sudo apt-get update -y -o DPkg::Lock::Timeout="$APT_TIMEOUT"
}

apt_install_safe() {
  wait_for_apt "$APT_TIMEOUT"
  sudo dpkg --configure -a || true
  wait_for_apt "$APT_TIMEOUT"
  sudo apt-get install -y -o DPkg::Lock::Timeout="$APT_TIMEOUT" "$@"
}

detect_php_version() {
  php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "desconocida"
}

download_zip() {
  if [ -f "$ZIP_LOCAL" ]; then
    log "📦 Usando actualización desde PENDRIVE: $ZIP_LOCAL"
    cp -f "$ZIP_LOCAL" "$ZIP_TMP"
  else
    log "🌐 No se encontró ZIP en pendrive. Descargando desde GitHub..."
    wget -q --show-progress "$GITHUB_URL" -O "$ZIP_TMP"
  fi

  [ -f "$ZIP_TMP" ] && [ -s "$ZIP_TMP" ] || fail "No se pudo obtener el ZIP de actualización."
}

restore_preserved_files() {
  log "===> Paso 6: Restaurando archivos personalizados"
  for archivo in "${PRESERVAR[@]}"; do
    if [ -f "$BACKUP_DIR/$archivo" ]; then
      mkdir -p "$(dirname "$APP_DIR/$archivo")"
      cp -f "$BACKUP_DIR/$archivo" "$APP_DIR/$archivo"
      log "  - Restaurado: $archivo"
    else
      log "  - No existía en backup: $archivo"
    fi
  done
}

setup_cron() {
  log "===> Paso 7: Configurando cron"
  local CRON_ENTRY="00 12 * * * /usr/bin/php /var/www/html/send_daily_status.php >> /tmp/estado_diario_cron.log 2>&1"

  if crontab -l 2>/dev/null | grep -Fq "$CRON_ENTRY"; then
    log "  - Entrada cron ya existe."
  else
    (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
    log "  - Entrada cron agregada."
  fi
}

setup_sudoers() {
  log "===> Paso 11: sudoers para servicios necesarios"

  sudo touch "$SUDOERS_FILE"

  for permiso in "${PERMISOS[@]}"; do
    local LINEA="www-data ALL=(ALL) NOPASSWD: $permiso"
    if ! sudo grep -Fxq "$LINEA" "$SUDOERS_FILE" 2>/dev/null; then
      echo "$LINEA" | sudo tee -a "$SUDOERS_FILE" >/dev/null
      log "  - Permiso agregado: $permiso"
    else
      log "  - Permiso ya existía: $permiso"
    fi
  done

  sudo chmod 440 "$SUDOERS_FILE"

  if ! sudo visudo -cf "$SUDOERS_FILE" >/dev/null; then
    fail "El archivo sudoers tiene un problema de sintaxis."
  fi
}

restart_services() {
  log "===> Paso 15: Reiniciando servicios AUROXLINK"
  sudo systemctl daemon-reexec
  sudo systemctl daemon-reload

  if systemctl list-unit-files | grep -q "^auroralink-monitor.service"; then
    sudo systemctl restart auroralink-monitor.service
    log "  - auroralink-monitor.service reiniciado"
  else
    log "⚠️ auroralink-monitor.service no existe en este sistema. Se omite reinicio."
  fi

  log "===> Paso 16: Verificando estado Apache"
  sudo systemctl restart apache2
  sudo systemctl --no-pager --full status apache2 || true
}

cleanup_temp() {
  log "===> Paso 14: Limpieza temporal"
  rm -rf "$TMP_DIR" "$ZIP_TMP"
}

# ===> Paso 0: Obtener ZIP
download_zip

# ===> Paso 1: Respaldo
log "===> Paso 1: Respaldo en $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"
cp -a "$APP_DIR"/. "$BACKUP_DIR"/

# ===> Paso 2: Dependencias
log "===> Paso 2: Instalando dependencias necesarias"
apt_update_safe
apt_install_safe php php-curl curl unzip wget ca-certificates lsb-release

PHP_VERSION_INSTALADA="$(detect_php_version)"
log "  - PHP detectado: $PHP_VERSION_INSTALADA"

# ===> Paso 3: Instalar Tailscale sin conectar
log "===> Paso 3: Instalando Tailscale desde script oficial"
wait_for_apt "$APT_TIMEOUT"
curl -fsSL https://tailscale.com/install.sh | sudo sh || log "⚠️ No se pudo instalar/actualizar Tailscale. La actualización continuará."

# ===> Paso 4: Descomprimir
log "===> Paso 4: Descomprimiendo actualización"
rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"
unzip -o "$ZIP_TMP" -d "$TMP_DIR" >/dev/null

ZIP_ROOT="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1)"
[ -n "$ZIP_ROOT" ] || fail "No se encontró contenido válido dentro del ZIP."

# ===> Paso 5: Instalar nueva versión
log "===> Paso 5: Instalando nueva versión"
cp -a "$ZIP_ROOT"/. "$APP_DIR"/

# ===> Paso 6: Restaurar archivos personalizados
restore_preserved_files

# ===> Paso 7: Configurar cron
setup_cron

# ===> Paso 8: Asegurar cron activo
log "===> Paso 8: Asegurando cron.service activo"
sudo systemctl enable cron.service
sudo systemctl start cron.service
sudo systemctl restart cron.service

# ===> Paso 9: Carpeta de logs
log "===> Paso 9: Carpeta logs"
sudo mkdir -p /tmp/auroxlink_logs
sudo chmod 777 /tmp/auroxlink_logs

# ===> Paso 10: Permisos web
log "===> Paso 10: Corrigiendo permisos"
sudo chown -R www-data:www-data "$APP_DIR"
sudo find "$APP_DIR" -type d -exec chmod 755 {} \;
sudo find "$APP_DIR" -type f -exec chmod 644 {} \;

if [ -f "$APP_DIR/update_auroxlink.sh" ]; then
  sudo chmod +x "$APP_DIR/update_auroxlink.sh"
fi

# ===> Paso 11: sudoers
setup_sudoers

# ===> Paso 12: Activar tailscaled, sin conectar VPN
log "===> Paso 12: Activando tailscaled"
sudo systemctl enable --now tailscaled || log "⚠️ No se pudo activar tailscaled. La actualización continuará."

# ===> Paso 13: Preparar VPN sin bloquear actualización
log "===> Paso 13: Preparando VPN (modo diferido)"
sudo mkdir -p /etc/auroxlink
sudo chown root:root /etc/auroxlink
sudo chmod 700 /etc/auroxlink

if [ -f /etc/auroxlink/tailscale.key ]; then
  log "⚠️ Se detectó una clave Tailscale, pero no se aplicará automáticamente durante este update."
  log "   Conéctala después manualmente con:"
  log "   sudo tailscale up --authkey=\$(cat /etc/auroxlink/tailscale.key) --ssh --shields-up=false"
else
  log "ℹ️ No hay clave Tailscale configurada. La actualización continúa sin VPN."
fi

# ===> Paso 14: Limpieza temporal
cleanup_temp

# ===> Paso 15 y 16: Reinicio de servicios
restart_services

# ===> Final
log "✅ AUROXLINK actualizado correctamente a la versión 1.7 - 73 de CA2RDP - TELECOVIAJERO"
log "ℹ️ Nota: la VPN Tailscale quedó en modo diferido y puede activarse después manualmente."
