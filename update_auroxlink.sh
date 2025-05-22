#!/bin/bash
set -e

echo "===> [AUROXLINK] Iniciando actualización de nueva Versión..."

APP_DIR="/var/www/html"
BACKUP_DIR="/var/www/backup_auroxlink_$(date +%Y%m%d_%H%M)"
PENDRIVE_DIR="/mnt/usb"
ZIP_LOCAL="$PENDRIVE_DIR/auroxlink_v1.6.2.zip"
ZIP_TMP="/tmp/auroxlink_v1.6.2.zip"
GITHUB_URL="https://github.com/telecov/auroxlink/releases/download/v1.6.2/auroxlink_v1.6.2.zip"

PRESERVAR=(
  "telegram_config.json"
  "estilos.json"
  "data/qsls.json"
  "img/auroxlink_banner.png"
  "img/admin.png"
)

# ===> Paso 0: Determinar origen de ZIP
if [ -f "$ZIP_LOCAL" ]; then
  echo "📦 Usando actualización desde PENDRIVE: $ZIP_LOCAL"
  cp "$ZIP_LOCAL" "$ZIP_TMP"
else
  echo "🌐 No se encontró ZIP en pendrive. Descargando desde GitHub..."
  wget "$GITHUB_URL" -O "$ZIP_TMP"
  if [ $? -ne 0 ] || [ ! -f "$ZIP_TMP" ]; then
    echo "❌ Error: No se pudo descargar el ZIP desde GitHub."
    exit 1
  fi
fi

# ===> Paso 1: Respaldo
echo "===> Paso 1: Respaldo en $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"
cp -r "$APP_DIR"/* "$BACKUP_DIR"

# ===> Paso 2: Dependencias
echo "===> Paso 2: Instalando dependencias necesarias"
sudo apt update -y
sudo apt install -y php8.2-curl curl unzip

# ===> Paso 3: Tailscale
echo "===> Paso 3: Instalando Tailscale desde script oficial"
curl -fsSL https://tailscale.com/install.sh | sh

# ===> Paso 4: Descomprimir
echo "===> Paso 4: Descomprimiendo actualización"
mkdir -p /tmp/auroxlink_temp
unzip -o "$ZIP_TMP" -d /tmp/auroxlink_temp
if [ $? -ne 0 ]; then
  echo "❌ Error al descomprimir el archivo ZIP."
  exit 1
fi

# ===> Paso 5: Copiar archivos
echo "===> Paso 5: Instalando nueva versión"
cp -r /tmp/auroxlink_temp/* "$APP_DIR"

# ===> Paso 6: Restaurar archivos personalizados
echo "===> Paso 6: Restaurando archivos personalizados"
for archivo in "${PRESERVAR[@]}"; do
  if [ -f "$BACKUP_DIR/$archivo" ]; then
    cp -f "$BACKUP_DIR/$archivo" "$APP_DIR/$archivo"
    echo "  - Restaurado: $archivo"
  fi
done

# ===> Paso 7: Configurar cron
echo "===> Paso 7: Configurando cron"
CRON_ENTRY="00 12 * * * /usr/bin/php /var/www/html/send_daily_status.php >> /tmp/estado_diario_cron.log 2>&1"
(crontab -l 2>/dev/null | grep -F "$CRON_ENTRY") || (
  (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
  echo "  - Entrada cron agregada."
)

# ===> Paso 8: Activar cron
echo "===> Paso 8: Asegurando cron.service activo"
sudo systemctl enable cron.service
sudo systemctl start cron.service
sudo systemctl restart cron.service

# ===> Paso 9: Crear carpeta de logs
echo "===> Paso 9: Carpeta logs"
sudo mkdir -p /tmp/auroxlink_logs
sudo chmod 777 /tmp/auroxlink_logs

# ===> Paso 10: Permisos correctos
echo "===> Paso 10: Corrigiendo permisos"
sudo chown -R www-data:www-data "$APP_DIR"
sudo find "$APP_DIR" -type d -exec chmod 755 {} \;
sudo find "$APP_DIR" -type f -exec chmod 644 {} \;

# ===> Paso 11: sudoers para servicios necesarios
echo "===> Paso 11: sudoers para servicios necesarios"
SUDOERS_FILE="/etc/sudoers.d/99-www-data-svxlink"
PERMISOS=(
  "/usr/bin/alsactl"
  "/usr/bin/tailscale"
  "/usr/sbin/tailscale"
)

if [ ! -f "$SUDOERS_FILE" ]; then
  sudo touch "$SUDOERS_FILE"
  sudo chmod 440 "$SUDOERS_FILE"
fi

for permiso in "${PERMISOS[@]}"; do
  LINEA="www-data ALL=(ALL) NOPASSWD: $permiso"
  if ! grep -Fxq "$LINEA" "$SUDOERS_FILE"; then
    echo "$LINEA" | sudo tee -a "$SUDOERS_FILE" > /dev/null
  fi
done
sudo chmod 440 "$SUDOERS_FILE"

# ===> Paso 12: Activar tailscaled
echo "===> Paso 12: Activando tailscaled"
sudo systemctl enable --now tailscaled

# ===> Paso 13: Conexión VPN con clave o manual
echo "===> Paso 13: Preparando clave VPN"
sudo mkdir -p /etc/auroxlink
sudo chown www-data:www-data /etc/auroxlink
sudo chmod 700 /etc/auroxlink

if [ -f /etc/auroxlink/tailscale.key ]; then
  echo "  - Conectando VPN con authkey"
  sudo tailscale up --authkey=$(cat /etc/auroxlink/tailscale.key) --ssh --shields-up=false
else
  echo "⚠️ No se encontró clave Tailscale. Intentando conexión manual..."
  sudo tailscale up --ssh || echo "⚠️ Autenticación manual requerida: ejecuta 'sudo tailscale up'"
fi

# ===> Paso 14: Limpieza
echo "===> Paso 14: Limpieza temporal"
rm -rf /tmp/auroxlink_temp "$ZIP_TMP"

# ===> Paso 15: Reiniciar servicios AUROXLINK
echo "===> Paso 15: Reiniciando servicios AUROXLINK"
sudo systemctl daemon-reexec
sudo systemctl daemon-reload
sudo systemctl restart auroralink-monitor.service

# ===> Paso 16: Verificar estado Apache
echo "===> Paso 16: Verificando estado Apache"
sudo systemctl status apache2 --no-pager

# ===> Final
echo "✅ AUROXLINK actualizado correctamente a la versión 1.6.2 - 73 de CA2RDP - TELECOVIAJERO"
