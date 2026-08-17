<?php
session_start();
require 'includes/environment.php';

if (
    (isset($_SESSION['integridad_modificada']) && $_SESSION['integridad_modificada'] === true) ||
    (isset($_SESSION['integridad_eliminada']) && $_SESSION['integridad_eliminada'] === true)
) {
    die('Error: El sistema ha sido comprometido. No se puede continuar.');
}

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
$lang = file_exists($langFile)
    ? json_decode(file_get_contents($langFile), true)
    : json_decode(file_get_contents(__DIR__ . "/data/lang/es.json"), true);

function t($key, $default = '')
{
    global $lang;
    return $lang[$key] ?? $default;
}

function getSystemStats()
{
    $temp = @file_get_contents('/sys/class/thermal/thermal_zone0/temp');
    $uptime = shell_exec("uptime -p");
    $uptime_seconds = (int) shell_exec("cut -d. -f1 /proc/uptime");
    $mem = shell_exec("free -m");
    preg_match("/Mem:\s+(\d+)\s+(\d+)/", $mem, $match);
    $mem_info = isset($match[1]) ? intval($match[2]) . "MB / " . intval($match[1]) . "MB" : "N/A";
    $disk = shell_exec("df -h / | tail -1");
    $disk_info = preg_split('/\s+/', trim($disk));

    return [
        'temp_raw' => $temp,
        'cpu' => shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print 100 - $8}'"),
        'temp' => $temp ? round($temp / 1000, 1) . ' °C' : 'N/A',
        'uptime' => trim($uptime),
        'uptime_seconds' => $uptime_seconds,
        'memory' => $mem_info,
        'disk' => ($disk_info[2] ?? 'N/A') . ' ' . t('used_of', 'used of') . ' ' . ($disk_info[1] ?? 'N/A')
    ];
}

function getEstadoVPN()
{
    $json = @shell_exec("tailscale status --json 2>/dev/null");
    if (!$json) {
        return '🔴 ' . t('vpn_unavailable', 'VPN NOT AVAILABLE');
    }

    $data = json_decode($json, true);

    return isset($data['Self']['Online']) && $data['Self']['Online']
        ? '🟢 ' . t('vpn_active', 'VPN ACTIVE')
        : '🔴 ' . t('vpn_disconnected', 'VPN DISCONNECTED');
}

function getLastTxTime()
{
    $log = @file('/var/log/svxlink');
    if ($log) {
        foreach (array_reverse($log) as $line) {
            if (strpos($line, 'Tx1: Turning the transmitter ON') !== false) {
                $fecha_raw = substr($line, 0, 24);
                $timestamp = strtotime($fecha_raw);
                return $timestamp ? date('d/m/Y H:i:s', $timestamp) : $fecha_raw;
            }
        }
    }
    return 'N/A';
}

function getConexionActiva()
{
    $log = @file('/var/log/svxlink');
    if ($log) {
        foreach (array_reverse($log) as $line) {
            if (preg_match('/: (\*?[A-Z0-9\-]+\*?)\: EchoLink QSO state changed to CONNECTED/', $line, $m)) {
                $conexion = $m[1];
                if (str_starts_with($conexion, '*') && str_ends_with($conexion, '*')) {
                    return t('conference', 'Conference') . ': ' . trim($conexion, '*');
                } else {
                    return t('node', 'Node') . ': ' . $conexion;
                }
            }
        }
    }
    return t('no_active_connection', 'No active connection');
}

function getModuloEchoLink()
{
    $log = @file('/var/log/svxlink');
    if ($log) {
        foreach (array_reverse($log) as $line) {
            if (strpos($line, 'Activating module EchoLink') !== false) {
                $fecha_raw = substr($line, 0, 24);
                $timestamp = strtotime($fecha_raw);
                return $timestamp
                    ? '🟢 ' . t('echolink_online', 'ECHOLINK ONLINE') . ' ' . date('d/m/Y H:i', $timestamp)
                    : '🟢 ' . t('echolink_online', 'ECHOLINK ONLINE');
            }
        }
    }
    return '🔴 ' . t('echolink_offline', 'ECHOLINK OFFLINE');
}

function getEstadoAPRS()
{
    $log = @file('/var/log/svxlink');
    if ($log) {
        foreach (array_reverse($log) as $line) {
            if (strpos($line, 'Connected to APRS server') !== false) {
                return '🟢 ' . t('aprs_connected', 'APRS CONNECTED');
            }
        }
    }
    return '🔴 ' . t('aprs_disconnected', 'APRS DISCONNECTED');
}

function getLastConnections($limit = 50)
{
    $log = @file('/var/log/svxlink');
    $result = [];
    if ($log) {
        foreach (array_reverse($log) as $line) {
            if (preg_match('/(\S+): EchoLink QSO state changed to CONNECTED/', $line, $m)) {
                $fecha_raw = substr($line, 0, 15);
                $timestamp = strtotime($fecha_raw);
                $fecha_hora = $timestamp ? date('d/m/Y H:i:s', $timestamp) : t('unknown_date', 'Unknown date');
                $callsign = $m[1];
                $result[] = [$fecha_hora, $callsign];
                if (count($result) >= $limit) {
                    break;
                }
            }
        }
    }
    return $result;
}

function getTxCount()
{
    $log = @file('/var/log/svxlink');
    $count = 0;
    if ($log) {
        foreach ($log as $line) {
            if (strpos($line, 'Tx1: Turning the transmitter ON') !== false) {
                $count++;
            }
        }
    }
    return $count;
}

function getServiceStatus()
{
    $status = trim(shell_exec('systemctl is-active svxlink'));
    return $status === 'active'
        ? '🟢 ' . t('svxlink_operational', 'SVXLink OPERATIONAL')
        : '🔴 ' . t('svxlink_disconnected', 'SVXLink DISCONNECTED');
}

function getSvxlinkVersion()
{
    $line = shell_exec("svxlink 2>&1 | head -n 1");
    if ($line && preg_match('/^(SvxLink v[\d\.\@]+)/', trim($line), $matches)) {
        return $matches[1];
    }
    return t('unknown_version', 'Unknown version');
}

$stats = getSystemStats();
$txCount = getTxCount();
$lastConnections = getLastConnections();
$lastTx = getLastTxTime();
$tempValue = $stats['temp_raw'] ? round($stats['temp_raw'] / 1000, 1) : 0;
$statusNodo = getServiceStatus();
$mem = preg_match('/(\d+)MB \/ (\d+)MB/', $stats['memory'], $m) ? round($m[1] / $m[2] * 100) : 0;
?>
<!doctype html>
<html lang="<?= htmlspecialchars($idioma); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <title><?= $titleSite; ?> - <?= t('dashboard', 'Dashboard'); ?></title>
    <style>
        .card {
            transition: background 0.5s ease-in-out;
        }
    </style>
</head>

<body>
    <div class="container-fluid bg-body-content">
        <div class="row">
            <?php require 'includes/sidebar-menu.php'; ?>

            <div class="col-12 col-md-10 p-3">
                <div class="d-flex align-items-center">
                    <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#mobileMenu" aria-controls="mobileMenu">
                        ☰
                    </button>
                    <h2 class="fs-4 titulo m-0"><?= $tituloDashboard; ?></h2>
                </div>

                <img src="<?= $imagenLogo; ?>" alt="Logo" class="img-fluid mb-4 mt-2"
                    style="max-height: 150px; width: 100%; object-fit: cover; border-radius: 8px;">

                <!-- NODO ECHOLINK -->
                <div class="card p-4 mb-4 shadow-sm border-0"
                    style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; border-left: 6px solid #ffc107; border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-center mb-0">
                        <h5 class="mb-0">🔗 <?= t('echolink_node', 'EchoLink Node'); ?></h5>
                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            <span class="badge bg-light text-dark px-3 py-2 fw-semibold"><?= getModuloEchoLink(); ?></span>
                            <span class="badge bg-light text-dark px-3 py-2 fw-semibold"><?= $statusNodo; ?></span>
                            <span class="badge bg-light text-dark px-3 py-2 fw-semibold"><?= getEstadoVPN(); ?></span>
                            <a href="<?= $aprs_web; ?>" target="_blank"
                                class="badge bg-light text-dark px-3 py-2 fw-semibold"
                                title="<?= t('click_view_aprs', 'Click to view APRS-IS web'); ?>">
                                <?= getEstadoAPRS(); ?>
                            </a>
                            <a href="https://github.com/sm0svx/svxlink/releases" target="_blank"
                                class="badge bg-light text-dark px-3 py-2 fw-semibold"
                                title="<?= t('click_view_releases', 'Click to review the latest releases on GitHub'); ?>">
                                📦 <?= getSvxlinkVersion(); ?>
                            </a>
                        </div>
                    </div>
                    <p class="mb-1">📡 <?= t('callsign', 'Callsign'); ?>: <strong><?= $indicativo ?></strong></p>
                    <p class="mb-0">🛰️ <?= t('last_transmission', 'Last transmission'); ?>:
                        <strong><?= $lastTx ?></strong>
                    </p>
                </div>

                <div class="row">
                    <!-- TRANSMISIONES HOY -->
                    <div class="col-md-4 col-12 mb-3">
                        <div class="card h-100 p-4 shadow-sm d-flex flex-column align-items-center text-center"
                            style="height: 100%; border-left: 6px solid #dee2e6; background: #ffffff; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
                            <h5 class="mb-2" style="color: #045d56;">📡 <?= t('transmissions_today', 'Transmissions Today'); ?></h5>
                            <h1 style="font-size: 3rem; font-weight: 700; color: #0d6efd; margin-bottom: 0.5rem;">
                                <?= $txCount; ?>
                            </h1>
                            <p style="font-size: 0.95rem; color: #333;">🔢 <?= t('daily_total_accumulated', 'Total accumulated during the day'); ?></p>
                        </div>
                    </div>

                    <!-- TXRX DINÁMICO -->
                    <div class="col-md-4 col-12 mb-3 d-flex">
                        <div id="txrx-card" class="w-100"></div>
                    </div>

                    <!-- BIENVENIDA -->
                    <div class="col-md-4 col-12 mb-3">
                        <div class="card h-100 p-4 shadow-sm d-flex flex-column align-items-center text-center"
                            style="height: 100%; border-left: 6px solid #dee2e6; background: #ffffff; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
                            <img src="<?= $foto_admin ?>" alt="Admin" class="rounded-circle mb-3 shadow-sm"
                                style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #20c997; cursor: pointer;"
                                data-bs-toggle="modal" data-bs-target="#adminModal">

                            <div class="modal fade" id="adminModal" tabindex="-1" aria-labelledby="adminModalLabel"
                                aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="adminModalLabel"><?= t('node_admin', 'Node Administrator'); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="<?= t('close', 'Close'); ?>"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <img src="<?= $foto_admin ?>" alt="Foto Admin"
                                                class="img-fluid rounded shadow">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h5 class="mb-1" style="color: #045d56;">👨‍🔧 <?= t('welcome', 'Welcome'); ?>, <?= $radioaficionado ?>!</h5>
                            <p class="mb-1" style="font-size: 0.95rem;">
                                <strong>📈 <?= t('uptime', 'Uptime'); ?>:</strong> <?= $stats['uptime']; ?>
                            </p>
                            <p class="mb-0" style="font-size: 0.95rem;">
                                <strong>🕒 <?= t('since', 'Since'); ?>:</strong> <?= date("d/m/Y H:i", time() - $stats['uptime_seconds']); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- RELOJ UTC / LOCAL -->
                    <div class="col-md-4 col-12 mb-3">
                        <div class="card h-100 p-4 shadow-sm"
                            style="height: 75%; border-left: 6px solid transparent; border-radius: 12px;" id="horaCard">
                            <h5 class="mb-3">🕒 <?= t('current_time', 'Current Time'); ?></h5>
                            <h6>🌐 UTC</h6>
                            <p id="utcClock" style="font-size: 1.5rem; font-weight: bold;">--:--:--</p>
                            <h6>🇨🇱 <?= t('local_time', 'Local Time'); ?> (UTC<?= $utcOffset >= 0 ? '+' . $utcOffset : $utcOffset ?>)</h6>
                            <p id="localClock" style="font-size: 1.5rem; font-weight: bold;">--:--:--</p>
                        </div>
                    </div>

                    <!-- CLIMA ACTUAL -->
                    <div class="col-md-4 col-12 mb-3">
                        <div class="card h-100 p-4 shadow-sm"
                            style="height: 75%; border-left: 6px solid transparent; border-radius: 12px;"
                            id="climaCard">
                            <h5 class="mb-3">⛅ <?= t('current_weather', 'Current Weather'); ?></h5>
                            <div id="climaInfo" class="text-center" style="font-size: 1.3rem;">
                                <div id="climaIcon" style="font-size: 3rem; line-height: 1;">⛅</div>
                                <p id="climaTemp" style="font-size: 2.2rem; font-weight: bold; margin: 0;">--°C</p>
                                <p id="climaDesc" style="font-size: 1.2rem; font-weight: 500;"><?= t('loading_weather', 'Loading weather...'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- FRECUENCIA DEL NODO -->
                    <div class="col-md-4 col-12 mb-3">
                        <div class="card h-100 p-4 shadow-sm text-center d-flex flex-column justify-content-center align-items-center"
                            style="background: #ffffff; border-radius: 16px;">
                            <h5 class="mb-3" style="font-size: 1.4rem; font-weight: bold; color: #198754;">
                                📶 <?= t('system_frequency', 'System Frequency'); ?>
                            </h5>
                            <div class="mb-2" style="font-size: 1rem;">⚙️ <strong><?= t('mode', 'Mode'); ?>:</strong> <?= $modo; ?></div>
                            <div class="mb-2" style="font-size: 1rem;">📍 <strong><?= t('location', 'Location'); ?>:</strong> <?= $ubicacion; ?></div>
                            <h2 class="mb-2" style="font-size: 2rem; font-weight: 700; color: #0d6efd;">
                                <?= $frecuencia; ?>
                            </h2>
                            <p class="mb-0">🔌 <?= t('connected_to', 'Connected to'); ?>
                                <span class="mb-2 badge bg-info"><?= getConexionActiva(); ?></span>
                            </p>
                            <div class="d-flex justify-content-between w-100 px-4 mt-2">
                                <div class="text-start">↕️ <?= t('offset', 'offset'); ?> <strong><?= $offset; ?> kHz</strong></div>
                                <div class="text-end">🔒 <?= t('tone', 'tone'); ?> <strong><?= $tono; ?> Hz</strong></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- ACTIVIDAD DE ESTACIONES -->
                    <div class="col-lg-6 col-12 mb-3">
                        <div class="card h-100 p-4 shadow-sm d-flex flex-column"
                            style="border-radius: 12px; min-height: 100%;">
                            <h5>📈 <?= t('recent_station_activity', 'Recent Station Activity'); ?></h5>
                            <div style="max-height: 200px; overflow-y: auto;">
                                <table class="table table-sm mb-0">
                                    <thead class="sticky-top bg-light">
                                        <tr>
                                            <th><?= t('date_time', 'Date and Time'); ?></th>
                                            <th><?= t('callsign', 'Callsign'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lastConnections as $conn): ?>
                                            <tr>
                                                <td><?= $conn[0]; ?></td>
                                                <td>
                                                    <a href="https://www.qrz.com/db/<?= urlencode($conn[1]); ?>"
                                                        target="_blank"><?= htmlspecialchars($conn[1]); ?> 🔍</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- RECURSOS DEL SISTEMA -->
                    <div class="col-lg-6 col-12 mb-3">
                        <div class="card h-100 p-4 shadow-sm d-flex flex-column justify-content-center align-items-center"
                            style="border-radius: 12px; min-height: 100%;">
                            <h5 class="mb-3">🖥️ <?= t('system_resources', 'System Resources'); ?></h5>
                            <div class="row w-100">
                                <div class="col-5 d-flex justify-content-center align-items-center">
                                    <canvas id="tempGauge" width="50" height="50"></canvas>
                                </div>
                                <div class="col-7">
                                    <h6 class="mt-2">🧠 <?= t('cpu_usage', 'CPU Usage'); ?></h6>
                                    <p class="mb-1"><strong><?= round(floatval($stats['cpu']), 1); ?>%</strong></p>
                                    <div class="progress mb-3" style="height: 16px;">
                                        <div class="progress-bar bg-warning" role="progressbar"
                                            style="width: <?= round(floatval($stats['cpu']), 1); ?>%;">
                                            <?= round(floatval($stats['cpu']), 1); ?>%
                                        </div>
                                    </div>

                                    <h6>💾 <?= t('ram_memory', 'RAM Memory'); ?></h6>
                                    <p class="mb-1"><strong><?= $stats['memory']; ?></strong></p>
                                    <div class="progress mb-3" style="height: 16px;">
                                        <div class="progress-bar bg-info" role="progressbar"
                                            style="width: <?= $mem; ?>%;">
                                            <?= $mem; ?>%
                                        </div>
                                    </div>

                                    <h6>🗄 <?= t('disk', 'Disk'); ?></h6>
                                    <p class="mb-1"><strong><?= $stats['disk']; ?></strong></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script>
                        const tempValue = <?= $tempValue ?>;
                        new Chart(document.getElementById('tempGauge').getContext('2d'), {
                            type: 'doughnut',
                            data: {
                                datasets: [{
                                    data: [tempValue, 85 - tempValue],
                                    backgroundColor: [
                                        tempValue < 50 ? '#0d6efd' : (tempValue < 70 ? '#ffc107' : '#dc3545'),
                                        '#e9ecef'
                                    ],
                                    borderWidth: 0,
                                    cutout: '80%'
                                }]
                            },
                            options: {
                                plugins: {
                                    tooltip: { enabled: false },
                                    legend: { display: false },
                                    title: {
                                        display: true,
                                        text: `CPU: ${tempValue} °C`,
                                        color: '#000',
                                        font: { size: 14, weight: 'bold' }
                                    }
                                }
                            }
                        });
                    </script>

                    <footer class="text-center mt-4 mb-3 px-3" style="font-size: 0.8rem; color: #777;">
                        <hr>
                        <p class="mb-0">🚀 <?= t('developed_by', 'Developed by'); ?> <strong>Telecoviajero - CA2RDP</strong></p>
                        <p class="mb-0">
                            <a href="https://github.com/telecov/auroxlink" target="_blank"
                                style="color: #0d6efd; text-decoration: none;">
                                GitHub/AuroxLink
                            </a>
                        </p>
                        <p class="mt-1 mb-0" style="font-size: 0.75rem;">
                            © 2026 Telecoviajero - CA2RDP. <?= t('all_rights_reserved', 'All rights reserved'); ?>
                        </p>
                    </footer>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function cargarEstadoTxRx() {
            fetch('includes/status-txrx.php')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('txrx-card').innerHTML = html;
                    actualizarTiempoRelativo();
                });
        }

        setInterval(cargarEstadoTxRx, 5000);
        cargarEstadoTxRx();

        function actualizarTiempoRelativo() {
            const span = document.getElementById('txRelativeTime');
            if (!span) return;

            const ts = parseInt(span.dataset.timestamp);
            const ahora = Math.floor(Date.now() / 1000);
            const diff = ahora - ts;

            let texto = '';
            if (diff < 60) texto = `(<?= t('ago', 'ago'); ?> ${diff} <?= t('sec', 'sec'); ?>)`;
            else if (diff < 3600) texto = `(<?= t('ago', 'ago'); ?> ${Math.floor(diff / 60)} <?= t('min', 'min'); ?>)`;
            else if (diff < 86400) texto = `(<?= t('ago', 'ago'); ?> ${Math.floor(diff / 3600)} <?= t('hours', 'h'); ?>)`;
            else texto = `(<?= t('ago', 'ago'); ?> ${Math.floor(diff / 86400)} <?= t('days', 'days'); ?>)`;

            span.innerText = ' ' + texto;
        }

        setInterval(actualizarTiempoRelativo, 5000);
        actualizarTiempoRelativo();

        const utcOffset = <?= json_encode($utcOffset ?? "-4") ?>;

        function updateClocks() {
            const now = new Date();
            const utc = now.toISOString().substr(11, 8);
            document.getElementById('utcClock').innerText = '🕒 ' + utc;

            const offsetMs = Number(utcOffset) * 3600000;
            const local = new Date(now.getTime() + offsetMs);
            const localTime = local.toISOString().substr(11, 8);
            document.getElementById('localClock').innerText = '🕒 ' + localTime;

            const localHour = local.getHours();
            const horaCard = document.getElementById('localClock').closest('.card');

            if (localHour >= 5 && localHour < 8) {
                horaCard.style.background = 'linear-gradient(135deg, #ffe3b3, #ffb347)';
            } else if (localHour >= 8 && localHour < 16) {
                horaCard.style.background = 'linear-gradient(135deg, #d0e8ff, #a0c4ff)';
            } else if (localHour >= 16 && localHour < 19) {
                horaCard.style.background = 'linear-gradient(135deg, #ffafcc, #ffc8dd)';
            } else {
                horaCard.style.background = 'linear-gradient(135deg, #7d8a96, #2d5175)';
            }
        }

        setInterval(updateClocks, 1000);
        updateClocks();

        const ciudad = <?= json_encode($ciudad ?? "Santiago") ?>;

        function obtenerDescripcionClima(codigo) {
            const descripciones = {
                0: <?= json_encode(t('weather_clear', 'Clear sky')); ?>,
                1: <?= json_encode(t('weather_mainly_clear', 'Mainly clear')); ?>,
                2: <?= json_encode(t('weather_partly_cloudy', 'Partly cloudy')); ?>,
                3: <?= json_encode(t('weather_cloudy', 'Cloudy')); ?>,
                45: <?= json_encode(t('weather_fog', 'Fog')); ?>,
                51: <?= json_encode(t('weather_light_rain', 'Light rain')); ?>,
                61: <?= json_encode(t('weather_moderate_rain', 'Moderate rain')); ?>,
                71: <?= json_encode(t('weather_light_snow', 'Light snow')); ?>,
                80: <?= json_encode(t('weather_showers', 'Showers')); ?>
            };
            return descripciones[codigo] ?? <?= json_encode(t('unknown_weather', 'Unknown weather')); ?>;
        }

        function obtenerColorClima(codigo) {
            const colores = {
                0: 'linear-gradient(135deg, #fdfcfb, #e2d1c3)',
                1: 'linear-gradient(135deg, #f0e6d2, #d9d9d9)',
                2: 'linear-gradient(135deg, #dbe9f4, #c2d3e7)',
                3: 'linear-gradient(135deg, #c8d6e5, #8395a7)',
                45: 'linear-gradient(135deg, #d6d6d6, #aaaaaa)',
                51: 'linear-gradient(135deg, #b3d9ff, #99c2ff)',
                61: 'linear-gradient(135deg, #80bfff, #6699ff)',
                71: 'linear-gradient(135deg, #f0faff, #d0e7f9)',
                80: 'linear-gradient(135deg, #89c2d9, #3b6978)'
            };
            return colores[codigo] ?? 'linear-gradient(135deg, #ced4da, #adb5bd)';
        }

        function mostrarClima(lat, lon) {
            fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true`)
                .then(res => res.json())
                .then(data => {
                    const temp = data.current_weather.temperature;
                    const weathercode = data.current_weather.weathercode;
                    const descripcion = obtenerDescripcionClima(weathercode);
                    const climaCard = document.querySelector('#climaInfo').closest('.card');
                    climaCard.style.background = obtenerColorClima(weathercode);

                    document.getElementById("climaInfo").innerHTML = `
                        <p style="font-size: 2rem; font-weight: bold;">🌤️ ${temp}°C</p>
                        <p>${descripcion} - ${ciudad}</p>
                    `;
                })
                .catch(() => {
                    document.getElementById("climaInfo").innerHTML =
                        `<p><?= t('weather_unavailable', 'Weather unavailable'); ?></p>`;
                });
        }

        function obtenerClimaDesdeCiudad(nombreCiudad) {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(nombreCiudad)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length > 0) {
                        mostrarClima(data[0].lat, data[0].lon);
                    } else {
                        document.getElementById("climaInfo").innerText = <?= json_encode(t('city_not_found', 'City not found')); ?>;
                    }
                })
                .catch(() => {
                    document.getElementById("climaInfo").innerText = <?= json_encode(t('weather_unavailable', 'Weather unavailable')); ?>;
                });
        }

        obtenerClimaDesdeCiudad(ciudad);
    </script>
</body>
</html>
