#!/usr/bin/env bash
set -Eeuo pipefail

LIBEXEC_DIR="/usr/local/libexec/auroxlink"
UPDATER="${LIBEXEC_DIR}/install_svxlink_latest.sh"
STATE_DIR="/var/lib/auroxlink/svxlink-update"
STATE_FILE="${STATE_DIR}/state.json"
LOG_FILE="${STATE_DIR}/update.log"
LOCK_FILE="/run/auroxlink-svxlink-update.lock"

mkdir -p "$STATE_DIR"
chown root:www-data "$STATE_DIR"
chmod 0750 "$STATE_DIR"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  exit 10
fi

STARTED_AT="$(date -Iseconds)"
FINISHED_AT=""
LATEST_VERSION=""
FINALIZED=0
FIFO=""

write_state() {
  local status="$1"
  local progress="$2"
  local phase="$3"
  local message="$4"
  local finished_at="${5:-}"

  /usr/bin/php -r '
    $state = [
      "status" => $argv[2],
      "progress" => (int)$argv[3],
      "phase" => $argv[4],
      "message" => $argv[5],
      "latest_version" => $argv[6] !== "" ? $argv[6] : null,
      "started_at" => $argv[7],
      "finished_at" => $argv[8] !== "" ? $argv[8] : null,
      "pid" => (int)$argv[9]
    ];
    $tmp = $argv[1] . ".tmp";
    file_put_contents(
      $tmp,
      json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
    rename($tmp, $argv[1]);
  ' "$STATE_FILE" "$status" "$progress" "$phase" "$message" \
    "$LATEST_VERSION" "$STARTED_AT" "$finished_at" "$$"

  chown root:www-data "$STATE_FILE"
  chmod 0640 "$STATE_FILE"
}

finalize_unexpected() {
  local rc=$?
  [[ -n "${FIFO:-}" ]] && rm -f "$FIFO" 2>/dev/null || true

  if [[ "$FINALIZED" -eq 0 ]]; then
    FINISHED_AT="$(date -Iseconds)"
    write_state \
      "failed" 99 "failed" \
      "La actualización terminó de forma inesperada (código ${rc})." \
      "$FINISHED_AT" || true
  fi
}
trap finalize_unexpected EXIT
trap 'exit 130' INT TERM

if [[ ! -f "$UPDATER" ]]; then
  write_state "failed" 0 "failed" \
    "No se encontró install_svxlink_latest.sh." "$(date -Iseconds)"
  FINALIZED=1
  exit 1
fi

: > "$LOG_FILE"
chown root:www-data "$LOG_FILE"
chmod 0640 "$LOG_FILE"

write_state "running" 2 "starting" \
  "Iniciando actualización segura de SvxLink..."

FIFO="${STATE_DIR}/update.pipe"
rm -f "$FIFO"
mkfifo "$FIFO"
chmod 0600 "$FIFO"

/usr/bin/bash "$UPDATER" > "$FIFO" 2>&1 &
UPDATE_PID=$!

while IFS= read -r line; do
  clean_line="$(printf '%s' "$line" | sed -E $'s/\x1B\[[0-9;]*[mK]//g')"
  printf '%s\n' "$clean_line" >> "$LOG_FILE"

  case "$clean_line" in
    *"Consultando última release estable oficial de SvxLink"*)
      write_state "running" 5 "checking" \
        "Buscando la última release oficial de SvxLink..."
      ;;
    *"Última release oficial estable:"*)
      LATEST_VERSION="${clean_line##*: }"
      write_state "running" 10 "version_found" \
        "Versión oficial encontrada: ${LATEST_VERSION}"
      ;;
    *"Instalando paquete base y dependencias de compilación"*)
      write_state "running" 18 "dependencies" \
        "Preparando dependencias de compilación..."
      ;;
    *"Creando respaldo previo"*)
      write_state "running" 28 "backup" \
        "Creando respaldo antes de modificar SvxLink..."
      ;;
    *"Descargando SvxLink "*)
      [[ -z "$LATEST_VERSION" ]] && LATEST_VERSION="${clean_line##*SvxLink }"
      write_state "running" 38 "download" \
        "Descargando SvxLink ${LATEST_VERSION}..."
      ;;
    *"Configurando compilación de SvxLink "*)
      write_state "running" 48 "configure" \
        "Configurando compilación de SvxLink ${LATEST_VERSION}..."
      ;;
    *"Compilando con "*)
      write_state "running" 58 "compile" \
        "Compilando SvxLink. Esta etapa puede tardar varios minutos..."
      ;;
    *"Instalando build en staging seguro"*)
      write_state "running" 72 "staging" \
        "Preparando el build en un entorno seguro..."
      ;;
    *"Publicando release versionada sin activar"*)
      write_state "running" 78 "publish" \
        "Publicando la nueva release sin activarla todavía..."
      ;;
    *"Preparando prueba previa de compatibilidad"*)
      write_state "running" 84 "pretest" \
        "Preparando prueba de compatibilidad con la configuración actual..."
      ;;
    *"Deteniendo temporalmente el servicio actual para probar audio/configuración"*)
      write_state "running" 88 "testing" \
        "Probando la nueva versión con audio y configuración reales..."
      ;;
    *"Prueba previa exitosa:"*)
      write_state "running" 91 "test_ok" \
        "Prueba segura completada correctamente."
      ;;
    *"Activando SvxLink "*)
      write_state "running" 94 "activating" \
        "Activando SvxLink ${LATEST_VERSION}..."
      ;;
    *"Validando servicio activado"*)
      write_state "running" 97 "validating" \
        "Validando el servicio SvxLink activado..."
      ;;
    *"Rollback completado:"*)
      write_state "running" 99 "rollback" \
        "La actualización falló y AUROXLINK restauró la versión anterior."
      ;;
    *"AUROXLINK - SvxLink actualizado correctamente"*)
      write_state "running" 99 "finishing" \
        "Finalizando actualización..."
      ;;
    *"ya está instalado, activo y funcionando"*)
      write_state "running" 99 "finishing" \
        "La última release ya estaba instalada y activa."
      ;;
  esac
done < "$FIFO"

rm -f "$FIFO"
FIFO=""

set +e
wait "$UPDATE_PID"
RC=$?
set -e

FINISHED_AT="$(date -Iseconds)"

if [[ "$RC" -eq 0 ]]; then
  write_state "completed" 100 "completed" \
    "SvxLink ${LATEST_VERSION:-actual} quedó actualizado y operativo." \
    "$FINISHED_AT"
  FINALIZED=1
  exit 0
fi

# Conservar el porcentaje real alcanzado antes del fallo.
LAST_PROGRESS="$(
  /usr/bin/php -r '
    $s = @json_decode(@file_get_contents($argv[1]), true);
    echo is_array($s) ? (int)($s["progress"] ?? 0) : 0;
  ' "$STATE_FILE" 2>/dev/null || echo 0
)"

if ! [[ "$LAST_PROGRESS" =~ ^[0-9]+$ ]]; then
  LAST_PROGRESS=0
fi

# Un error nunca debe mostrarse como instalación completada al 100%.
if (( LAST_PROGRESS >= 100 )); then
  LAST_PROGRESS=99
fi

if grep -q "Rollback completado" "$LOG_FILE" 2>/dev/null; then
  write_state "failed" "$LAST_PROGRESS" "failed_rollback" \
    "La actualización falló. La versión anterior fue restaurada automáticamente." \
    "$FINISHED_AT"

elif grep -qE 'error: 429|HTTP.*429|returned error: 429' "$LOG_FILE" 2>/dev/null; then
  write_state "failed" "$LAST_PROGRESS" "failed" \
    "GitHub limitó temporalmente las solicitudes (HTTP 429). SvxLink activo no fue modificado." \
    "$FINISHED_AT"

elif grep -qE 'error: 504|HTTP.*504|returned error: 504' "$LOG_FILE" 2>/dev/null; then
  write_state "failed" "$LAST_PROGRESS" "failed" \
    "GitHub no respondió a tiempo (HTTP 504). SvxLink activo no fue modificado." \
    "$FINISHED_AT"

else
  write_state "failed" "$LAST_PROGRESS" "failed" \
    "La actualización terminó con error (código ${RC}). Revisa el log mostrado en AUROXLINK." \
    "$FINISHED_AT"
fi

FINALIZED=1
exit "$RC"
