#!/usr/bin/env bash
set -Eeuo pipefail

echo "===> [AUROXLINK] Iniciando actualización de nueva versión..."

APP_DIR="/var/www/html"
REPO_URL="https://github.com/telecov/auroxlink.git"
BACKUP_DIR="/var/www/backup_auroxlink_$(date +%Y%m%d_%H%M)"
PENDRIVE_DIR="/mnt/usb"
TMP_DIR="/tmp/auroxlink_temp"
SUDOERS_FILE="/etc/sudoers.d/99-www-data-svxlink"
CRON_FILE="/etc/cron.d/auroxlink"
LIBEXEC_DIR="/usr/local/libexec/auroxlink"
APT_TIMEOUT=180

VERSION=""
CURRENT_VERSION=""
ZIP_LOCAL=""
ZIP_TMP=""
GITHUB_URL=""

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

log() {
  echo -e "$1"
}

fail() {
  log "❌ $1"
  exit 1
}

if [[ ${EUID} -ne 0 ]]; then
  fail "El actualizador debe ejecutarse como root."
fi

CURRENT_VERSION="$(
  tr -d '[:space:]' < "$APP_DIR/version.txt" 2>/dev/null || true
)"

log "🔎 Buscando última versión oficial de AUROXLINK..."

LATEST_TAG="$(
  git ls-remote --tags --refs "$REPO_URL" 'refs/tags/v*' 2>/dev/null |
  awk -F/ '{print $3}' |
  grep -E '^v[0-9]+([.][0-9]+)*$' |
  sort -V |
  tail -n 1
)"

[[ -n "$LATEST_TAG" ]] || fail "No fue posible determinar la última versión publicada."

VERSION="${LATEST_TAG#v}"

[[ "$VERSION" =~ ^[0-9]+([.][0-9]+)*$ ]] ||
  fail "Tag de versión inválido: $LATEST_TAG"

log "  - Versión instalada : ${CURRENT_VERSION:-desconocida}"
log "  - Última disponible : ${VERSION}"

if [[ "$CURRENT_VERSION" == "$VERSION" ]]; then
  log "✅ AUROXLINK ${VERSION} ya es la última versión instalada."
  exit 0
fi

if [[ -n "$CURRENT_VERSION" ]] &&    [[ "$(printf '%s\n%s\n' "$CURRENT_VERSION" "$VERSION" | sort -V | tail -n1)" == "$CURRENT_VERSION" ]]; then
  log "✅ AUROXLINK ${CURRENT_VERSION} es igual o más reciente que la versión publicada ${VERSION}."
  log "ℹ️ No se realizará downgrade."
  exit 0
fi

ZIP_LOCAL="$PENDRIVE_DIR/auroxlink_v${VERSION}.zip"
ZIP_TMP="/tmp/auroxlink_v${VERSION}.zip"
GITHUB_URL="https://github.com/telecov/auroxlink/archive/refs/tags/v${VERSION}.zip"

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

# ===> Paso 0: Determinar origen del ZIP
if [ -f "$ZIP_LOCAL" ]; then
  log "📦 Usando actualización desde PENDRIVE: $ZIP_LOCAL"
  cp -f "$ZIP_LOCAL" "$ZIP_TMP"
else
  log "🌐 No se encontró ZIP en pendrive. Descargando desde GitHub..."
  wget -q --show-progress "$GITHUB_URL" -O "$ZIP_TMP"
  if [ ! -f "$ZIP_TMP" ] || [ ! -s "$ZIP_TMP" ]; then
    fail "No se pudo descargar el ZIP desde GitHub."
  fi
fi

# ===> Paso 1: Respaldo
log "===> Paso 1: Respaldo en $BACKUP_DIR"
mkdir -p "$BACKUP_DIR"
cp -a "$APP_DIR"/. "$BACKUP_DIR"/

# ===> Paso 2: Dependencias
log "===> Paso 2: Instalando dependencias necesarias"
apt_update_safe
apt_install_safe php php-curl curl unzip wget ca-certificates lsb-release psmisc cron

PHP_VERSION_INSTALADA=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "desconocida")
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

# Buscar index.php en distintos niveles
ZIP_ROOT=""

if [ -f "$TMP_DIR/index.php" ]; then
  ZIP_ROOT="$TMP_DIR"
else
  ZIP_ROOT="$(find "$TMP_DIR" -type f -name 'index.php' | head -n 1 | xargs -r dirname)"
fi

if [ -z "$ZIP_ROOT" ] || [ ! -f "$ZIP_ROOT/index.php" ]; then
  log "📂 Contenido encontrado en el ZIP:"
  find "$TMP_DIR" -maxdepth 3 -type f | sed 's/^/  - /'
  fail "No se encontró un index.php válido dentro del ZIP."
fi

log "  - Carpeta raíz detectada: $ZIP_ROOT"

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

# Preservar banner personalizado guardado en la raíz.
shopt -s nullglob
for banner in "$BACKUP_DIR"/auroxlink_banner.*; do
  cp -f "$banner" "$APP_DIR/$(basename "$banner")"
  log "  - Restaurado: $(basename "$banner")"
done
shopt -u nullglob

# ===> Paso 7: Configurar cron
log "===> Paso 7: Configurando cron"

cat > "$CRON_FILE" <<'CRON'
# AUROXLINK - estado diario por Telegram
0 12 * * * www-data /usr/bin/php /var/www/html/send_daily_status.php >> /tmp/estado_diario_cron.log 2>&1
CRON

chown root:root "$CRON_FILE"
chmod 0644 "$CRON_FILE"

log "  - Cron AUROXLINK configurado en $CRON_FILE"

# ===> Paso 8: Asegurar cron activo
log "===> Paso 8: Asegurando cron.service activo"
sudo systemctl enable cron.service
sudo systemctl start cron.service
sudo systemctl restart cron.service

# ===> Paso 9: Carpeta de logs
log "===> Paso 9: Carpeta logs"
sudo mkdir -p /tmp/auroxlink_logs
sudo chown www-data:www-data /tmp/auroxlink_logs
sudo chmod 775 /tmp/auroxlink_logs

# ===> Paso 10: Permisos web
log "===> Paso 10: Corrigiendo permisos"
sudo chown -R www-data:www-data "$APP_DIR"
sudo find "$APP_DIR" -type d -exec chmod 755 {} \;
sudo find "$APP_DIR" -type f -exec chmod 644 {} \;

if [ -f "$APP_DIR/update_auroxlink.sh" ]; then
  sudo chmod +x "$APP_DIR/update_auroxlink.sh"
fi

# ===> Paso 10.5: Actualizadores privilegiados protegidos
log "===> Paso 10.5: Instalando componentes privilegiados protegidos"

mkdir -p "$LIBEXEC_DIR"

for script in   update_auroxlink.sh   install_svxlink_latest.sh   svxlink_update_worker.sh
do
  if [[ -f "$APP_DIR/$script" ]]; then
    install -o root -g root -m 0755       "$APP_DIR/$script"       "$LIBEXEC_DIR/$script"
  fi
done

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

# Eliminar mecanismo heredado que ejecutaba código root desde /tmp.
sed -i '\|/usr/bin/bash /tmp/update_auroxlink.sh|d' "$SUDOERS_FILE"

for linea in   "www-data ALL=(root) NOPASSWD: /usr/bin/bash /usr/local/libexec/auroxlink/update_auroxlink.sh"   "www-data ALL=(root) NOPASSWD: /usr/bin/bash /usr/local/libexec/auroxlink/svxlink_update_worker.sh"
do
  grep -Fxq "$linea" "$SUDOERS_FILE" 2>/dev/null ||
    echo "$linea" >> "$SUDOERS_FILE"
done

sudo chmod 440 "$SUDOERS_FILE"

if ! sudo visudo -cf "$SUDOERS_FILE" >/dev/null; then
  fail "El archivo sudoers tiene un problema de sintaxis."
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
systemctl reload apache2 2>/dev/null || true
sudo systemctl --no-pager --full status apache2 || true

# ===> Final
log "✅ AUROXLINK actualizado correctamente a la versión ${VERSION} - CE2RDP - TELECOVIAJERO"
log "ℹ️ Nota: la VPN Tailscale quedó en modo diferido y puede activarse después manualmente."
