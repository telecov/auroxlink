#!/bin/bash
set -e

echo "===> [AUROXLINK] Iniciando actualización de nueva versión..."

APP_DIR="/var/www/html"
BACKUP_DIR="/var/www/backup_auroxlink_$(date +%Y%m%d_%H%M)"
PENDRIVE_DIR="/mnt/usb"
ZIP_LOCAL="$PENDRIVE_DIR/auroxlink_v1.6.3.zip"
ZIP_TMP="/tmp/auroxlink_v1.6.3.zip"
TMP_DIR="/tmp/auroxlink_temp"
GITHUB_URL="https://github.com/telecov/auroxlink/releases/download/v1.6.3/auroxlink_v1.6.3.zip"
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

# ===> Función auxiliar
log() {
  echo -e "$1"
}

# ===> Paso 0: Determinar origen del ZIP
if [ -f "$ZIP_LOCAL" ]; then
  log "📦 Usando actualización desde PENDRIVE: $ZIP_LOCAL"
  cp -f "$ZIP_LOCAL" "$ZIP_TMP"
else
  log "🌐 No se encontró ZIP en pendrive. Descargando desde GitHub..."
  wget -q --show-progress "$GITHUB_URL" -O "$ZIP_TMP"
  if [ ! -f "$ZIP_TMP" ] || [ ! -s "$ZIP_TMP" ]; then
    log "❌ Error: No se pudo descargar el ZIP desde GitHub."
    exit 1
  fi
fi

# ===> Paso 1: Respaldo
log "===> Paso 1: Respaldo en $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"
cp -a "$APP_DIR"/. "$BACKUP_DIR"/

# ===> Paso 2: Dependencias
log "===> Paso 2: Instalando dependencias necesarias"
sudo apt-get update -y
sudo apt-get install -y php8.2-curl curl unzip wget

# ===> Paso 3: Instalar Tailscale sin conectar
log "===> Paso 3: Instalando Tailscale desde script oficial"
curl -fsSL https://tailscale.com/install.sh | sh || log "⚠️ No se pudo instalar/actualizar Tailscale. La actualización continuará."

# ===> Paso 4: Descomprimir
log "===> Paso 4: Descomprimiendo actualización"
rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"
unzip -o "$ZIP_TMP" -d "$TMP_DIR" >/dev/null

# Detectar carpeta raíz real del ZIP
ZIP_ROOT="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1)"

if [ -z "$ZIP_ROOT" ]; then
  log "❌ Error: No se encontró contenido válido dentro del ZIP."
  exit 1
fi

# ===> Paso 5: Instalar nueva versión
log "===> Paso 5: Instalando nueva versión"
cp -a "$ZIP_ROOT"/. "$APP_DIR"/

# ===> Paso 6: Restaurar archivos personalizados
log "===> Paso 6: Restaurando archivos personalizados"
for archivo in "${PRESERVAR[@]}"; do
  if [ -f "$BACKUP_DIR/$archivo" ]; then
    mkdir -p "$(dirname "$APP_DIR/$archivo")"
    cp -f "$BACKUP_DIR/$archivo" "$APP_DIR/$archivo"
    log "  - Restaurado: $archivo"
  fi
done

# ===> Paso 7: Configurar cron
log "===> Paso 7: Configurando cron"
CRON_ENTRY="00 12 * * * /usr/bin/php /var/www/html/send_daily_status.php >> /tmp/estado_diario_cron.log 2>&1"
(
  crontab -l 2>/dev/null | grep -F "$CRON_ENTRY"
) || (
  (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
  log "  - Entrada cron agregada."
)

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

# Dar permisos de ejecución al propio script de actualización si existe
if [ -f "$APP_DIR/update_auroxlink.sh" ]; then
  sudo chmod +x "$APP_DIR/update_auroxlink.sh"
fi

# ===> Paso 11: sudoers para servicios necesarios
log "===> Paso 11: sudoers para servicios necesarios"

if [ ! -f "$SUDOERS_FILE" ]; then
  sudo touch "$SUDOERS_FILE"
fi

for permiso in "${PERMISOS[@]}"; do
  LINEA="www-data ALL=(ALL) NOPASSWD: $permiso"
  if ! sudo grep -Fxq "$LINEA" "$SUDOERS_FILE" 2>/dev/null; then
    echo "$LINEA" | sudo tee -a "$SUDOERS_FILE" >/dev/null
  fi
done

sudo chmod 440 "$SUDOERS_FILE"

# Validar sintaxis sudoers
if ! sudo visudo -cf "$SUDOERS_FILE" >/dev/null; then
  log "❌ Error: El archivo sudoers tiene un problema de sintaxis."
  exit 1
fi

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
log "===> Paso 14: Limpieza temporal"
rm -rf "$TMP_DIR" "$ZIP_TMP"

# ===> Paso 15: Reiniciar servicios AUROXLINK
log "===> Paso 15: Reiniciando servicios AUROXLINK"
sudo systemctl daemon-reexec
sudo systemctl daemon-reload

if systemctl list-unit-files | grep -q "^auroralink-monitor.service"; then
  sudo systemctl restart auroralink-monitor.service
  log "  - auroralink-monitor.service reiniciado"
else
  log "⚠️ auroralink-monitor.service no existe en este sistema. Se omite reinicio."
fi

# ===> Paso 16: Verificar Apache
log "===> Paso 16: Verificando estado Apache"
sudo systemctl restart apache2
sudo systemctl --no-pager --full status apache2 || true

# ===> Final
log "✅ AUROXLINK actualizado correctamente a la versión 1.6.3 - 73 de CA2RDP - TELECOVIAJERO"
log "ℹ️ Nota: la VPN Tailscale quedó en modo diferido y puede activarse después manualmente."
