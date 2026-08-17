<?php
require 'includes/environment.php';
session_start();

/* =========================================================
   PROTECCIÓN DE INTEGRIDAD
========================================================= */
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
   APRS
========================================================= */
function getAprsStatus($log_path)
{
    $lineas = @file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lineas) {
        return null;
    }

    $lineas = array_reverse($lineas);
    foreach ($lineas as $line) {
        if (str_contains($line, 'Connected to APRS server')) {
            preg_match('/Connected to APRS server (\d{1,3}(?:\.\d{1,3}){3}) on port (\d+)/', $line, $m);
            return [
                'ip' => $m[1] ?? 'Unknown',
                'puerto' => $m[2] ?? '???',
                'hora' => substr($line, 0, 24),
                'estado' => 'Activo'
            ];
        }
    }

    return null;
}

$aprsStatus = getAprsStatus($log_path);

/* =========================================================
   CONEXIONES ACTIVAS
========================================================= */
function getActiveConnections()
{
    $logFile = '/var/log/svxlink';
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return [];
    }

    $estadoActual = [];
    $startIndex = 0;

    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (strpos($lines[$i], 'SimplexLogic: Loading module "ModuleEchoLink"') !== false) {
            $startIndex = $i;
            break;
        }
    }

    $slice = array_slice($lines, $startIndex);
    $nodoActivo = null;
    $hijasTemporales = [];
    $conferenciaActual = null;
    $leyendoConferencia = false;
    $bufferConferencia = [];

    foreach ($slice as $index => $line) {
        $line = trim($line);

        if (preg_match('/Connecting to (\*\S+\*)/', $line, $m)) {
            $estadoActual = [];
            $conferenciaActual = null;
            $leyendoConferencia = false;
            $bufferConferencia = [];
            $nodoActivo = null;
            $hijasTemporales = [];
            continue;
        }

        if (preg_match('/Connecting to ([A-Z0-9]{3,}-[LR])/', $line, $m)) {
            $estadoActual = [];
            $conferenciaActual = null;
            $leyendoConferencia = false;
            $bufferConferencia = [];
            $nodoActivo = null;
            $hijasTemporales = [];
            continue;
        }

        if (preg_match('/: (\S+): EchoLink QSO state changed to DISCONNECTED/', $line, $m)) {
            $cs = $m[1];
            unset($estadoActual[$cs]);
            $estadoActual = array_filter($estadoActual, fn($info) => $info['desde'] !== $cs);
            continue;
        }

        if (preg_match('/: (\S+): EchoLink QSO state changed to CONNECTED/', $line, $m)) {
            $cs = $m[1];
            $esConf = str_starts_with($cs, '*') && str_ends_with($cs, '*');
            $esNodo = $esConf || str_contains($cs, '-L') || str_contains($cs, '-R');
            $estadoActual[$cs] = [
                'hora' => date('d/m/Y H:i:s'),
                'tipo' => $esConf ? 'Conferencia' : ($esNodo ? 'Nodo independiente' : 'Estación independiente'),
                'desde' => null
            ];
            continue;
        }

        if (preg_match('/--- EchoLink chat message received from (\*.+?\*) ---/', $line, $m)) {
            $conferenciaActual = $m[1];
            $leyendoConferencia = true;
            $bufferConferencia = [];
            continue;
        }

        if ($leyendoConferencia) {
            if ($conferenciaActual && !isset($estadoActual[$conferenciaActual])) {
                $estadoActual[$conferenciaActual] = [
                    'hora' => date('d/m/Y H:i:s'),
                    'tipo' => 'Conferencia',
                    'desde' => null
                ];
            }

            $bufferConferencia[] = $line;
            $indicativosDetectados = [];

            foreach ($bufferConferencia as $l) {
                if (preg_match('/: ([A-Z]{2,3}\d{1,4}[A-Z]{1,3}(?:-[LR])?)\b/', $l, $m)) {
                    $indicativosDetectados[] = $m[1];
                }
            }

            if (count($indicativosDetectados) >= 3) {
                $ultimas = array_slice($bufferConferencia, -5);
                $noIndicativos = 0;

                foreach ($ultimas as $l) {
                    if (!preg_match('/: ([A-Z]{2,3}\d{1,4}[A-Z]{1,3}(?:-[LR])?)\b/', $l)) {
                        $noIndicativos++;
                    }
                }

                if ($noIndicativos >= 3) {
                    foreach ($estadoActual as $k => $v) {
                        if (($v['desde'] ?? null) === $conferenciaActual) {
                            unset($estadoActual[$k]);
                        }
                    }

                    foreach ($indicativosDetectados as $cs) {
                        $estadoActual[$cs] = [
                            'hora' => date('d/m/Y H:i:s'),
                            'tipo' => 'Conferencia hija',
                            'desde' => $conferenciaActual
                        ];
                    }

                    $conferenciaActual = null;
                    $leyendoConferencia = false;
                    $bufferConferencia = [];
                }
            }

            continue;
        }

        if (
            strpos($line, 'EchoLink info message received from') !== false &&
            preg_match('/from (\S+)/', $line, $m)
        ) {
            $nodoActivo = $m[1];
            $estadoActual[$nodoActivo] = [
                'hora' => date('d/m/Y H:i:s'),
                'tipo' => (str_contains($nodoActivo, '-L') || str_contains($nodoActivo, '-R')) ? 'Nodo independiente' : 'Estación independiente',
                'desde' => null
            ];
            $hijasTemporales[$nodoActivo] = [];
            continue;
        }

        if ($nodoActivo && preg_match('/\d{4}:\s+([A-Z0-9\-\*]{3,})\s{2,}/', $line, $m)) {
            $cs = trim($m[1]);
            $hijasTemporales[$nodoActivo][] = $cs;
        }

        if (($index === array_key_last($slice) || strpos($slice[$index + 1] ?? '', 'EchoLink info message received from') !== false) && $nodoActivo) {
            foreach ($estadoActual as $k => $v) {
                if (($v['desde'] ?? null) === $nodoActivo) {
                    unset($estadoActual[$k]);
                }
            }

            foreach ($hijasTemporales[$nodoActivo] ?? [] as $cs) {
                if ($cs !== $nodoActivo) {
                    $estadoActual[$cs] = [
                        'hora' => date('d/m/Y H:i:s'),
                        'tipo' => (str_contains($cs, '-L') || str_contains($cs, '-R')) ? 'Nodo hijo' : 'Estación hija',
                        'desde' => $nodoActivo
                    ];
                }
            }

            $nodoActivo = null;
        }
    }

    return $estadoActual;
}

$active = getActiveConnections();
?>

<!doctype html>
<html lang="<?= htmlspecialchars($idioma); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <style>
        .connection-card {
            transition: opacity 0.5s ease-in-out, transform 0.5s ease-in-out;
        }
        .fade-in {
            opacity: 0;
            transform: translateY(10px);
            animation: fadeInUp 0.6s forwards;
        }
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
    <title><?= htmlspecialchars($titleSite); ?> - <?= t('menu_active_connections', 'Active Connections'); ?></title>
</head>
<body>
<div class="container-fluid bg-body-content">
    <div class="row">
        <?php require 'includes/sidebar-menu.php'; ?>
        <div class="col-12 col-md-10 p-3 vh-100">
            <div class="d-flex align-items-center">
                <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">
                    ☰
                </button>
                <h2 class="fs-4 titulo m-0">🛰️ <?= t('connections_title', 'Active EchoLink Connections'); ?></h2>
            </div>

            <div id="contenedor-conexiones">
            <?php if (count($active) > 0): ?>
                <div class="row mt-3">

                    <?php if ($aprsStatus): ?>
                        <div class="col-md-4 connection-card fade-in">
                            <div class="card card-connection mb-3 border border-info p-3">
                                <h6 class="mb-1">📡 <?= t('connections_aprs_status', 'APRS Status'); ?></h6>
                                <p class="mb-1"><strong><?= t('connections_server', 'Server'); ?>:</strong> <?= htmlspecialchars($aprsStatus['ip']); ?>:<?= htmlspecialchars($aprsStatus['puerto']); ?></p>
                                <p class="mb-1"><strong><?= t('connections_last_connection', 'Last connection'); ?>:</strong> <?= htmlspecialchars($aprsStatus['hora']); ?></p>
                                <span class="badge bg-success text-white">✅ <?= t('active', 'Active'); ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="col-md-4 connection-card fade-in">
                            <div class="card card-connection mb-3 border border-danger p-3">
                                <h6 class="mb-1">📡 <?= t('connections_aprs_status', 'APRS Status'); ?></h6>
                                <p class="mb-1"><?= t('connections_no_aprs', 'There is no APRS connection registered yet.'); ?></p>
                                <span class="badge bg-danger text-white">❌ <?= t('inactive', 'Inactive'); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($active as $callsign => $info): ?>
                        <?php
                        $esHija = !empty($info['desde']);
                        $tipo = $info['tipo'];

                        $tipoTraducido = match ($tipo) {
                            'Conferencia' => t('connections_type_conference', 'Conference'),
                            'Nodo independiente' => t('connections_type_independent_node', 'Independent node'),
                            'Estación independiente' => t('connections_type_independent_station', 'Independent station'),
                            'Nodo hijo' => t('connections_type_child_node', 'Child node'),
                            'Estación hija' => t('connections_type_child_station', 'Child station'),
                            'Conferencia hija' => t('connections_type_child_conference', 'Child conference'),
                            default => $tipo
                        };

                        $colorClase = match (true) {
                            str_contains($tipo, 'Conferencia') => 'border-primary',
                            str_contains($tipo, 'Nodo') => 'border-warning',
                            default => 'border-success'
                        };

                        $badgeClase = match ($tipo) {
                            'Nodo hijo' => 'bg-warning text-dark',
                            'Estación hija' => 'bg-info text-dark',
                            'Nodo independiente' => 'bg-dark text-white',
                            'Conferencia' => 'bg-primary text-white',
                            default => 'bg-success'
                        };

                        $colorClase = str_contains($tipo, 'Nodo') || $tipo === 'Conferencia' ? 'border-warning' : 'border-success';
                        $tamañoClase = $esHija ? 'p-2 small shadow-sm' : 'p-3';
                        $columnaClase = $esHija ? 'col-md-3' : 'col-md-4';
                        $tituloLlamada = $tipo === 'Conferencia' ? '🎙️ ' . $callsign : $callsign;

                        $uso = $info['uso_porcentaje'] ?? 0;
                        $textoUso = '';
                        if ($tipo === 'Conferencia') {
                            $colorUso = $uso >= 90 ? 'text-danger' : ($uso >= 60 ? 'text-warning' : 'text-success');
                            $textoUso = "<p class='mb-1 $colorUso'>👥 {$info['usuarios']} " . t('connections_connected', 'connected') . " ({$uso}%)</p>";
                        }
                        ?>
                        <div class="<?= $columnaClase; ?> connection-card fade-in" data-callsign="<?= htmlspecialchars($callsign); ?>">
                            <div class="card card-connection mb-3 border <?= $colorClase . ' ' . $tamañoClase; ?>">
                                <h6 class="mb-1"><?= htmlspecialchars($tituloLlamada); ?></h6>
                                <p class="mb-1"><strong><?= t('connections_type', 'Type'); ?>:</strong> <?= htmlspecialchars($tipoTraducido); ?></p>
                                <?php if ($esHija): ?>
                                    <p class="mb-1"><strong>🔗 <?= t('connections_via', 'Via'); ?>:</strong> <?= htmlspecialchars($info['desde']); ?></p>
                                <?php endif; ?>
                                <p class="mb-1"><i class="bi bi-clock"></i> <?= htmlspecialchars($info['hora']); ?></p>
                                <?= $textoUso ?>
                                <span class="badge <?= $badgeClase; ?>"><?= t('active', 'Active'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center mt-3">
                    <?= t('connections_none', 'There are no active connections at this time.'); ?>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
<script>
    setInterval(() => {
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nuevasTarjetas = doc.querySelectorAll('.connection-card');
                const nuevasIDs = Array.from(nuevasTarjetas).map(el => el.dataset.callsign);
                const actuales = document.querySelectorAll('.connection-card');

                actuales.forEach(card => {
                    const id = card.dataset.callsign;
                    if (!nuevasIDs.includes(id)) {
                        card.classList.add('opacity-50');
                        setTimeout(() => {
                            card.remove();
                        }, 1000);
                    }
                });

                const rowActual = document.getElementById('contenedor-conexiones').querySelector('.row');
                if (!rowActual) return;

                nuevasTarjetas.forEach(nueva => {
                    const id = nueva.dataset.callsign;
                    if (!document.querySelector(`[data-callsign="${id}"]`)) {
                        nueva.classList.add('fade-in');
                        rowActual.appendChild(nueva);
                    }
                });
            });
    }, 60000);
</script>
</body>
</html>
