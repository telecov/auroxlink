<?php
require 'includes/environment.php';
session_start();

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

date_default_timezone_set('America/Santiago');
$hora_actual = date('H:i');

$por_hora = array_fill(0, 24, 0);
$por_dia = [];
$indicativos = [];
$paises_conectados = [];

$prefijos_paises = [
    'CA' => 'Chile',
    'CB' => 'Chile',
    'CC' => 'Chile',
    'CD' => 'Chile',
    'CE' => 'Chile',
    'XQ' => 'Chile',
    'XR' => 'Chile',
    '3G' => 'Chile',
    '3J' => 'Chile',
    'LU' => 'Argentina',
    'LW' => 'Argentina',
    'LR' => 'Argentina',
    'LS' => 'Argentina',
    'LQ' => 'Argentina',
    'AY' => 'Argentina',
    'AZ' => 'Argentina',
    'PP' => 'Brasil',
    'PQ' => 'Brasil',
    'PR' => 'Brasil',
    'PS' => 'Brasil',
    'PT' => 'Brasil',
    'PU' => 'Brasil',
    'PY' => 'Brasil',
    'ZZ' => 'Brasil',
    'EA' => 'España',
    'EB' => 'España',
    'EC' => 'España',
    'ED' => 'España',
    'EE' => 'España',
    'EF' => 'España',
    'K' => 'Estados Unidos',
    'N' => 'Estados Unidos',
    'W' => 'Estados Unidos',
    'AA' => 'Estados Unidos',
    'AB' => 'Estados Unidos',
    'AC' => 'Estados Unidos',
    'AD' => 'Estados Unidos',
    'AE' => 'Estados Unidos',
    'AF' => 'Estados Unidos',
    'AG' => 'Estados Unidos',
    'VA' => 'Canadá',
    'VE' => 'Canadá',
    'VO' => 'Canadá',
    'VY' => 'Canadá',
    'ZP' => 'Paraguay',
    'CX' => 'Uruguay',
    'HK' => 'Colombia',
    'HJ' => 'Colombia',
    '5J' => 'Colombia',
    '5K' => 'Colombia',
    'YV' => 'Venezuela',
    'YY' => 'Venezuela',
    'XE' => 'México',
    'XF' => 'México',
    'XH' => 'México',
    '4A' => 'México',
    'OA' => 'Perú',
    'OB' => 'Perú',
    'CP' => 'Bolivia',
    'HC' => 'Ecuador',
    'HD' => 'Ecuador',
    'CM' => 'Cuba',
    'CL' => 'Cuba',
    'CO' => 'Cuba',
    'T4' => 'Cuba',
    'HI' => 'República Dominicana',
    'KP4' => 'Puerto Rico',
    'HP' => 'Panamá',
    'YN' => 'Nicaragua',
    'HR' => 'Honduras',
    'TG' => 'Guatemala',
    'YS' => 'El Salvador',
    'TI' => 'Costa Rica',
    'DL' => 'Alemania',
    'DA' => 'Alemania',
    'DB' => 'Alemania',
    'DC' => 'Alemania',
    'DD' => 'Alemania',
    'DE' => 'Alemania',
    'DF' => 'Alemania',
    'DG' => 'Alemania',
    'DH' => 'Alemania',
    'G' => 'Reino Unido',
    'M' => 'Reino Unido',
    '2E' => 'Reino Unido',
    'MM' => 'Reino Unido',
    'GM' => 'Reino Unido',
    'GW' => 'Reino Unido',
    'F' => 'Francia',
    'I' => 'Italia',
    'IK' => 'Italia',
    'IZ' => 'Italia',
    'IW' => 'Italia',
    'JA' => 'Japón',
    'JF' => 'Japón',
    'JG' => 'Japón',
    'JI' => 'Japón',
    'JJ' => 'Japón',
    'JK' => 'Japón',
    'VK' => 'Australia',
    'ZL' => 'Nueva Zelanda'
];

$log_files = glob('/var/log/svxlink*');

foreach ($log_files as $log_file) {
    if (substr($log_file, -3) === '.gz') {
        $handle = gzopen($log_file, 'r');
        if ($handle) {
            while (!gzeof($handle)) {
                $line = gzgets($handle);
                procesar_linea($line);
            }
            gzclose($handle);
        }
    } else {
        $log_lines = @file($log_file);
        if ($log_lines) {
            foreach ($log_lines as $line) {
                procesar_linea($line);
            }
        }
    }
}

function procesar_linea($line)
{
    global $por_hora, $por_dia, $indicativos, $paises_conectados, $prefijos_paises;

    if (preg_match('/^([A-Za-z]{3})\s+([A-Za-z]{3})\s+(\d+)\s+(\d{2}):(\d{2}):(\d{2})/', $line, $matches)) {
        $mes = $matches[2];
        $dia_mes = $matches[3];
        $hora = (int) $matches[4];
        $fecha_dia = date("Y-m-d", strtotime("$mes $dia_mes"));

        if (!isset($por_dia[$fecha_dia])) {
            $por_dia[$fecha_dia] = 0;
        }

        if (strpos($line, 'EchoLink QSO state changed to CONNECTED') !== false) {
            if (preg_match('/(\w{3,}-?[LR]?): EchoLink QSO state changed to CONNECTED/', $line, $m_call)) {
                $indicativo = $m_call[1];
                $por_hora[$hora]++;
                $por_dia[$fecha_dia]++;
                $indicativos[$indicativo] = ($indicativos[$indicativo] ?? 0) + 1;

                $pais = t('traffic_unknown_country', 'Unknown');
                foreach (array_keys($prefijos_paises) as $prefijo) {
                    if (str_starts_with($indicativo, $prefijo)) {
                        $pais = $prefijos_paises[$prefijo];
                        break;
                    }
                }
                $paises_conectados[$pais] = ($paises_conectados[$pais] ?? 0) + 1;
            }
        }
    }
}

arsort($indicativos);
arsort($paises_conectados);

$usuarios_nuevos = 0;
$usuarios_recurrentes = 0;
foreach ($indicativos as $indicativo => $cantidad) {
    if ($cantidad > 1) {
        $usuarios_recurrentes++;
    } else {
        $usuarios_nuevos++;
    }
}

$total_usuarios = 0;
$total_nodos = 0;
foreach ($indicativos as $indic => $conteo) {
    if (str_ends_with($indic, '-L') || str_ends_with($indic, '-R')) {
        $total_nodos += $conteo;
    } else {
        $total_usuarios += $conteo;
    }
}
?>

<!doctype html>
<html lang="<?= htmlspecialchars($idioma); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titleSite) ?> - <?= t('menu_echolink_traffic', 'EchoLink Traffic'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <style>
        .card-hover:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: 0.3s ease-in-out;
        }

        .canvas-fila2 {
            flex-grow: 1;
            max-height: 250px;
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
                        data-bs-target="#mobileMenu" aria-controls="mobileMenu">☰</button>
                    <h2 class="fs-4 titulo m-0">📶 <?= t('traffic_title', 'EchoLink Traffic'); ?></h2>
                    <button class="btn btn-success ms-3" onclick="exportarData()">⬇️ <?= t('traffic_export_data', 'Export Data'); ?></button>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 p-3 card-hover">
                            <h5>🕒 <?= t('traffic_activity_by_hour', 'Activity by Hour'); ?></h5>
                            <canvas id="porHora"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 p-3 card-hover">
                            <h5>📅 <?= t('traffic_connections_by_day', 'Connections by Day'); ?></h5>
                            <canvas id="porDia"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 p-3 card-hover" style="max-height:300px; overflow-y:auto;">
                            <h5>🥇 <?= t('traffic_top_callsigns', 'Top Callsigns'); ?></h5>
                            <canvas id="topIndicativos"></canvas>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 p-3 card-hover" style="max-height:300px; overflow-y:auto;">
                            <h5>🌎 <?= t('traffic_country_distribution', 'Distribution by Country'); ?></h5>
                            <canvas id="porPaisBarras" class="canvas-fila2"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 p-3 card-hover">
                            <h5>🔄 <?= t('traffic_new_vs_returning', 'New vs Returning'); ?></h5>
                            <canvas id="nuevosVsRecurrentes" class="canvas-fila2"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 p-3 card-hover">
                            <h5>🖥️ <?= t('traffic_users_vs_nodes', 'Users vs Nodes'); ?></h5>
                            <canvas id="usuariosVsNodos" class="canvas-fila2"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const txtTxByHour = <?= json_encode(t('traffic_tx_by_hour', 'TX by Hour')); ?>;
        const txtConnections = <?= json_encode(t('traffic_connections', 'Connections')); ?>;
        const txtTxByCallsign = <?= json_encode(t('traffic_tx_by_callsign', 'TX by Callsign')); ?>;
        const txtTxByCountry = <?= json_encode(t('traffic_tx_by_country', 'TX by Country')); ?>;
        const txtQuantity = <?= json_encode(t('traffic_quantity', 'Quantity')); ?>;
        const txtNew = <?= json_encode(t('traffic_new', 'New')); ?>;
        const txtReturning = <?= json_encode(t('traffic_returning', 'Returning')); ?>;
        const txtUsers = <?= json_encode(t('traffic_users', 'Users')); ?>;
        const txtNodes = <?= json_encode(t('traffic_nodes', 'Nodes')); ?>;
        const txtTransmissions = <?= json_encode(t('traffic_transmissions', 'Transmissions')); ?>;

        new Chart(document.getElementById('porHora'), {
            type: 'bar',
            data: {
                labels: [...Array(24).keys()].map(h => h.toString().padStart(2, '0') + 'h'),
                datasets: [{
                    label: txtTxByHour,
                    data: <?= json_encode(array_values($por_hora)) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)'
                }]
            },
            options: {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: ctx => `${txtTransmissions}: ${ctx.raw}`
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('porDia'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_keys($por_dia)) ?>,
                datasets: [{
                    label: txtConnections,
                    data: <?= json_encode(array_values($por_dia)) ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true
                }]
            }
        });

        new Chart(document.getElementById('topIndicativos'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($indicativos)) ?>,
                datasets: [{
                    label: txtTxByCallsign,
                    data: <?= json_encode(array_values($indicativos)) ?>,
                    backgroundColor: 'rgba(153, 102, 255, 0.7)'
                }]
            }
        });

        new Chart(document.getElementById('porPaisBarras'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($paises_conectados)) ?>,
                datasets: [{
                    label: txtTxByCountry,
                    data: <?= json_encode(array_values($paises_conectados)) ?>,
                    backgroundColor: 'rgba(255, 159, 64, 0.7)'
                }]
            }
        });

        new Chart(document.getElementById('nuevosVsRecurrentes'), {
            type: 'bar',
            data: {
                labels: [txtNew, txtReturning],
                datasets: [{
                    label: txtQuantity,
                    data: [<?= $usuarios_nuevos ?>, <?= $usuarios_recurrentes ?>],
                    backgroundColor: ['#36A2EB', '#FF9F40']
                }]
            }
        });

        new Chart(document.getElementById('usuariosVsNodos'), {
            type: 'bar',
            data: {
                labels: [txtUsers, txtNodes],
                datasets: [{
                    label: txtQuantity,
                    data: [<?= $total_usuarios ?>, <?= $total_nodos ?>],
                    backgroundColor: ['#4BC0C0', '#FF6384']
                }]
            }
        });
    </script>

    <script>
        function exportarData() {
            const indicativos = <?= json_encode($indicativos) ?>;
            const prefijos = <?= json_encode($prefijos_paises) ?>;

            const detalleIndicativos = [];
            const usuariosPorPais = {};
            const nodosPorPais = {};

            for (const [indicativo, cantidad] of Object.entries(indicativos)) {
                let tipo = (indicativo.endsWith('-L') || indicativo.endsWith('-R')) ? <?= json_encode(t('traffic_node', 'Node')) ?> : <?= json_encode(t('traffic_user', 'User')) ?>;
                let clasificacion = (cantidad > 1) ? <?= json_encode(t('traffic_returning', 'Returning')) ?> : <?= json_encode(t('traffic_new', 'New')) ?>;

                let paisDetectado = <?= json_encode(t('traffic_unknown_country', 'Unknown')) ?>;
                for (const prefijo in prefijos) {
                    if (indicativo.startsWith(prefijo)) {
                        paisDetectado = prefijos[prefijo];
                        break;
                    }
                }

                detalleIndicativos.push({
                    indicativo,
                    conexiones: cantidad,
                    tipo,
                    clasificacion,
                    pais: paisDetectado
                });

                if (tipo === <?= json_encode(t('traffic_user', 'User')) ?>) {
                    usuariosPorPais[paisDetectado] = (usuariosPorPais[paisDetectado] || 0) + 1;
                } else {
                    nodosPorPais[paisDetectado] = (nodosPorPais[paisDetectado] || 0) + 1;
                }
            }

            const dataExport = {
                resumen: {
                    total_usuarios: <?= $total_usuarios ?>,
                    total_nodos: <?= $total_nodos ?>,
                    usuarios_nuevos: <?= $usuarios_nuevos ?>,
                    usuarios_recurrentes: <?= $usuarios_recurrentes ?>
                },
                conexiones_por_hora: <?= json_encode($por_hora) ?>,
                conexiones_por_dia: <?= json_encode($por_dia) ?>,
                distribucion_por_pais_total: <?= json_encode($paises_conectados) ?>,
                usuarios_por_pais: usuariosPorPais,
                nodos_por_pais: nodosPorPais,
                detalle_indicativos: detalleIndicativos
            };

            const blob = new Blob([JSON.stringify(dataExport, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'trafico_echolink_detallado.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
