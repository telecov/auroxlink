<?php
require __DIR__ . '/includes/environment.php';
require __DIR__ . '/telegram-alert.php';

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

/* =========================================================
   CARGA DE IDIOMA
========================================================= */
$configFile = __DIR__ . '/estilos.json';
$config = file_exists($configFile)
    ? json_decode(file_get_contents($configFile), true)
    : [];

$idioma = $config['idioma'] ?? 'es';

$langFile = __DIR__ . "/data/lang/{$idioma}.json";
$lang = [];
if (file_exists($langFile)) {
    $lang = json_decode(file_get_contents($langFile), true);
}
if (!is_array($lang)) {
    $lang = json_decode(file_get_contents(__DIR__ . "/data/lang/es.json"), true);
}
if (!is_array($lang)) {
    $lang = [];
}

function t($key, $default = '')
{
    global $lang;
    return $lang[$key] ?? $default;
}

/* =========================================================
   CONFIGURACIÓN
========================================================= */
$esperaInfoSegundos = 3;
$pendientes = [];
$estadoIndicativos = [];
$ultimaFecha = date('Y-m-d');

$log_file = '/var/log/svxlink';

if (!file_exists($log_file)) {
    die("Error: No se encontró el archivo de log.\n");
}

$handle = popen("tail -n 0 -F " . escapeshellarg($log_file), "r");

if (!$handle) {
    die("Error: No se pudo abrir el seguimiento del log.\n");
}

/* =========================================================
   FUNCIONES AUXILIARES
========================================================= */
function horaUtcActual()
{
    $dt = new DateTime('now', new DateTimeZone('UTC'));
    return $dt->format('Y-m-d H:i:s');
}

function normalizarLinea($line)
{
    return trim((string)$line);
}

function construirMensajeConexion(array $datos)
{
    $mensaje  = "📡 " . t('tg_new_connection_title', 'AUROXLINK - New Connection') . "\n";
    $mensaje .= "🔹 " . t('tg_callsign', 'Callsign') . ": {$datos['indicativo']}\n";

    if (!empty($datos['ubicacion'])) {
        $mensaje .= "📍 " . t('tg_location', 'Location') . ": {$datos['ubicacion']}\n";
    }

    if (!empty($datos['dispositivo'])) {
        $mensaje .= "📱 " . t('tg_device', 'Device') . ": {$datos['dispositivo']}\n";
    }

    $mensaje .= "🕑 " . t('tg_utc_time', 'UTC Time') . ": " . horaUtcActual();
    return $mensaje;
}

function construirMensajeDesconexion($indicativo)
{
    $mensaje  = "📴 " . t('tg_disconnect_title', 'AUROXLINK - Disconnection') . "\n";
    $mensaje .= "🔹 " . t('tg_callsign', 'Callsign') . ": {$indicativo}\n";
    $mensaje .= "🕑 " . t('tg_utc_time', 'UTC Time') . ": " . horaUtcActual();
    return $mensaje;
}

/* =========================================================
   BUCLE PRINCIPAL
========================================================= */
while (!feof($handle)) {
    $line = fgets($handle);

    if ($line !== false) {
        $line = normalizarLinea($line);

        /* =========================
           NUEVA CONEXIÓN
        ========================= */
        if (strpos($line, 'EchoLink QSO state changed to CONNECTED') !== false) {
            if (preg_match('/([A-Z0-9\-\*]+): EchoLink QSO state changed to CONNECTED/', $line, $matches)) {
                $indicativo = trim($matches[1]);

                if (!isset($estadoIndicativos[$indicativo]) || $estadoIndicativos[$indicativo] === false) {
                    $pendientes[$indicativo] = [
                        'hora_detectado' => time(),
                        'indicativo' => $indicativo,
                        'ubicacion' => '',
                        'dispositivo' => '',
                        'esperando_ubicacion' => false,
                        'completo' => false
                    ];

                    $estadoIndicativos[$indicativo] = true;
                }
            }
        }

        /* =========================
           CAPTURAR UBICACIÓN
        ========================= */
        if (preg_match('/^Station ([A-Z0-9\-\*]+)$/', $line, $matches)) {
            $indicativo = $matches[1];
            if (isset($pendientes[$indicativo])) {
                $pendientes[$indicativo]['esperando_ubicacion'] = true;
            }
        } elseif (preg_match('/^[A-Za-zÀ-ÿ0-9\s\-\.,\(\)\/]+$/u', $line) && !empty($line)) {
            foreach ($pendientes as $key => $p) {
                if (!empty($p['esperando_ubicacion']) && empty($p['ubicacion'])) {
                    $pendientes[$key]['ubicacion'] = trim($line);
                    $pendientes[$key]['esperando_ubicacion'] = false;
                    break;
                }
            }
        }

        /* =========================
           CAPTURAR DISPOSITIVO
        ========================= */
        if (strpos($line, 'is running EchoLink') !== false) {
            $dispositivo = '';

            if (preg_match('/on a (.+?),/', $line, $deviceMatch)) {
                $dispositivo = trim($deviceMatch[1]);
            } elseif (preg_match('/running EchoLink (.+)/', $line, $deviceMatch)) {
                $dispositivo = trim($deviceMatch[1]);
            }

            if ($dispositivo !== '') {
                foreach ($pendientes as $key => $p) {
                    if (empty($p['dispositivo'])) {
                        $pendientes[$key]['dispositivo'] = $dispositivo;
                        $pendientes[$key]['completo'] = true;
                        break;
                    }
                }
            }
        }

        /* =========================
           ENVIAR CONEXIONES PENDIENTES
        ========================= */
        foreach ($pendientes as $indicativo => $datos) {
            if (time() - $datos['hora_detectado'] >= $esperaInfoSegundos) {
                $mensaje = construirMensajeConexion($datos);
                enviarAlertaTelegram($mensaje);
                unset($pendientes[$indicativo]);
            }
        }

        /* =========================
           DESCONEXIÓN
        ========================= */
        if (strpos($line, 'EchoLink QSO state changed to DISCONNECTED') !== false) {
            if (preg_match('/([A-Z0-9\-\*]+): EchoLink QSO state changed to DISCONNECTED/', $line, $matches)) {
                $indicativo = trim($matches[1]);

                $mensaje = construirMensajeDesconexion($indicativo);
                enviarAlertaTelegram($mensaje);

                $estadoIndicativos[$indicativo] = false;
                unset($pendientes[$indicativo]);
            }
        }
    }

    usleep(300000);

    /* =========================
       RESET DIARIO
    ========================= */
    if (date('Y-m-d') !== $ultimaFecha) {
        $pendientes = [];
        $estadoIndicativos = [];
        $ultimaFecha = date('Y-m-d');
    }
}

pclose($handle);
