#!/bin/bash

echo "===> [AUROXLINK] Iniciando actualización automática a v1.6..."

APP_DIR="/var/www/html"
BACKUP_DIR="/var/www/backup_auroxlink_$(date +%Y%m%d_%H%M)"

PRESERVAR=(
  "telegram_config.json"
  "estilo.json"
  "data/qsls.json"
  "img/auroxlink_banner.png"
)

echo "===> Paso 1: Respaldando en $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"
cp -r "$APP_DIR"/* "$BACKUP_DIR"

echo "===> Paso 2: Instalando php8.2-curl"
sudo apt update -y
sudo apt install -y php8.2-curl

echo "===> Paso 3: Descargando AUROXLINK v1.6"
cd /tmp || exit 1
wget https://github.com/telecov/auroxlink/releases/download/v1.6/auroxlink_v1.6.zip -O auroxlink_v1.6.zip

echo "===> Paso 4: Descomprimiendo en carpeta temporal..."
mkdir -p /tmp/auroxlink_temp
unzip -o auroxlink_v1.6.zip -d /tmp/auroxlink_temp

echo "===> Paso 5: Copiando nueva versión a $APP_DIR"
cp -r /tmp/auroxlink_temp/* "$APP_DIR"

echo "===> Paso 6: Restaurando archivos personalizados..."
for archivo in "${PRESERVAR[@]}"; do
  if [ -f "$BACKUP_DIR/$archivo" ]; then
    cp -f "$BACKUP_DIR/$archivo" "$APP_DIR/$archivo"
    echo "  - Restaurado: $archivo"
  fi
done

echo "===> Paso 7: Configurando cron..."
CRON_ENTRY="00 12 * * * /usr/bin/php /var/www/html/send_daily_status.php >> /tmp/estado_diario_cron.log 2>&1"
(crontab -l 2>/dev/null | grep -F "$CRON_ENTRY") || (
  (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
  echo "  - Entrada de cron agregada."
)

echo "===> Paso 8: Asegurando cron.service activo..."
sudo systemctl enable cron.service
sudo systemctl start cron.service
sudo systemctl restart cron.service

echo "===> Paso 9: Creando /tmp/auroxlink_logs"
sudo mkdir -p /tmp/auroxlink_logs
sudo chmod 777 /tmp/auroxlink_logs

echo "===> Paso 10: Corrigiendo permisos..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 755 "$APP_DIR"

echo "===> Paso 11: Limpieza..."
rm -rf /tmp/auroxlink_v1.6.zip /tmp/auroxlink_temp

echo "✅ AUROXLINK actualizado correctamente a v1.6 - Disfruta esta nueva version 73 CA2RDP - TELECOVIAJERO"

