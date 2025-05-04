<?php
require 'includes/environment.php';

date_default_timezone_set('America/Santiago');
$hora_actual = date('H:i');

$por_hora = array_fill(0, 24, 0);
$por_dia = [];
$indicativos = [];
$paises_conectados = [];

$prefijos_paises = [
// CHILE
    'CA' => 'Chile', 'CB' => 'Chile', 'CC' => 'Chile', 'CD' => 'Chile', 'CE' => 'Chile', 'XQ' => 'Chile', 'XR' => 'Chile', '3G' => 'Chile', '3J' => 'Chile',

    // ARGENTINA
    'LU' => 'Argentina', 'LW' => 'Argentina', 'LR' => 'Argentina', 'LS' => 'Argentina', 'LQ' => 'Argentina', 'AY' => 'Argentina', 'AZ' => 'Argentina',

    // BRASIL
    'PP' => 'Brasil', 'PQ' => 'Brasil', 'PR' => 'Brasil', 'PS' => 'Brasil', 'PT' => 'Brasil', 'PU' => 'Brasil', 'PY' => 'Brasil', 'ZZ' => 'Brasil',

    // ESPAÑA
    'EA' => 'España', 'EB' => 'España', 'EC' => 'España', 'ED' => 'España', 'EE' => 'España', 'EF' => 'España',

    // ESTADOS UNIDOS
    'K' => 'Estados Unidos', 'N' => 'Estados Unidos', 'W' => 'Estados Unidos',
    'AA' => 'Estados Unidos', 'AB' => 'Estados Unidos', 'AC' => 'Estados Unidos', 'AD' => 'Estados Unidos',
    'AE' => 'Estados Unidos', 'AF' => 'Estados Unidos', 'AG' => 'Estados Unidos',

    // CANADÁ
    'VA' => 'Canadá', 'VE' => 'Canadá', 'VO' => 'Canadá', 'VY' => 'Canadá',

    // PARAGUAY
    'ZP' => 'Paraguay',

    // URUGUAY
    'CX' => 'Uruguay',

    // COLOMBIA
    'HK' => 'Colombia', 'HJ' => 'Colombia', '5J' => 'Colombia', '5K' => 'Colombia',

    // VENEZUELA
    'YV' => 'Venezuela', 'YY' => 'Venezuela',

    // MÉXICO
    'XE' => 'México', 'XF' => 'México', 'XH' => 'México', '4A' => 'México',

    // PERÚ
    'OA' => 'Perú', 'OB' => 'Perú',

    // BOLIVIA
    'CP' => 'Bolivia',

    // ECUADOR
    'HC' => 'Ecuador', 'HD' => 'Ecuador',

    // CUBA
    'CM' => 'Cuba', 'CL' => 'Cuba', 'CO' => 'Cuba', 'T4' => 'Cuba',

    // REP. DOMINICANA
    'HI' => 'República Dominicana',

    // PUERTO RICO
    'KP4' => 'Puerto Rico',

    // PANAMÁ
    'HP' => 'Panamá',

    // NICARAGUA
    'YN' => 'Nicaragua',

    // HONDURAS
    'HR' => 'Honduras',

    // GUATEMALA
    'TG' => 'Guatemala',

    // EL SALVADOR
    'YS' => 'El Salvador',

    // COSTA RICA
    'TI' => 'Costa Rica',

    // ALEMANIA
    'DL' => 'Alemania', 'DA' => 'Alemania', 'DB' => 'Alemania', 'DC' => 'Alemania', 'DD' => 'Alemania', 'DE' => 'Alemania', 'DF' => 'Alemania', 'DG' => 'Alemania', 'DH' => 'Alemania',

    // REINO UNIDO
    'G' => 'Reino Unido', 'M' => 'Reino Unido', '2E' => 'Reino Unido', 'MM' => 'Reino Unido', 'GM' => 'Reino Unido', 'GW' => 'Reino Unido',

    // FRANCIA
    'F' => 'Francia',

    // ITALIA
    'I' => 'Italia', 'IK' => 'Italia', 'IZ' => 'Italia', 'IW' => 'Italia',

    // JAPÓN
    'JA' => 'Japón', 'JF' => 'Japón', 'JG' => 'Japón', 'JI' => 'Japón', 'JJ' => 'Japón', 'JK' => 'Japón',

    // AUSTRALIA
    'VK' => 'Australia',

    // NUEVA ZELANDA
    'ZL' => 'Nueva Zelanda'
];

// LEER TODOS LOS LOGS DISPONIBLES (.1, .2.gz, etc.)
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
        $log_lines = file($log_file);
        foreach ($log_lines as $line) {
            procesar_linea($line);
        }
    }
}

// FUNCIÓN QUE PROCESA CADA LÍNEA DE LOG
function procesar_linea($line) {
    global $por_hora, $por_dia, $indicativos, $paises_conectados, $prefijos_paises;

    if (preg_match('/^([A-Za-z]{3})\s+([A-Za-z]{3})\s+(\d+)\s+(\d{2}):(\d{2}):(\d{2})/', $line, $matches)) {
        $mes = $matches[2];
        $dia_mes = $matches[3];
        $hora = (int)$matches[4];
        $fecha_dia = date("Y-m-d", strtotime("$mes $dia_mes"));

        if (!isset($por_dia[$fecha_dia])) $por_dia[$fecha_dia] = 0;

        if (strpos($line, 'EchoLink QSO state changed to CONNECTED') !== false) {
            if (preg_match('/(\w{3,}-?[LR]?): EchoLink QSO state changed to CONNECTED/', $line, $m_call)) {
                $indicativo = $m_call[1];
                $por_hora[$hora]++;
                $por_dia[$fecha_dia]++;
                $indicativos[$indicativo] = ($indicativos[$indicativo] ?? 0) + 1;

                $prefijo = substr($indicativo, 0, 2);
                $pais = $prefijos_paises[$prefijo] ?? 'Desconocido';
                $paises_conectados[$pais] = ($paises_conectados[$pais] ?? 0) + 1;
            }
        }
    }
}

// ORDENAMIENTO Y CLASIFICACIÓN
arsort($indicativos);
arsort($paises_conectados);

// NUEVOS VS RECURRENTES
$usuarios_nuevos = 0;
$usuarios_recurrentes = 0;
foreach ($indicativos as $indicativo => $cantidad) {
    if ($cantidad > 1) $usuarios_recurrentes++;
    else $usuarios_nuevos++;
}

// USUARIOS VS NODOS
$total_usuarios = 0;
$total_nodos = 0;
foreach ($indicativos as $indic => $conteo) {
    if (str_ends_with($indic, '-L') || str_ends_with($indic, '-R')) $total_nodos += $conteo;
    else $total_usuarios += $conteo;
}
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titleSite ?> - Tráfico EchoLink</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <style>
        .card-hover:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: 0.3s ease-in-out; }
        .canvas-fila2 { flex-grow: 1; max-height: 250px; }
    </style>
</head>
<body>
<div class="container-fluid bg-body-content">
    <div class="row">
        <?php require 'includes/sidebar-menu.php'; ?>
        <div class="col-12 col-md-10 p-3">
            <div class="d-flex align-items-center">
                <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">☰</button>
                <h2 class="fs-4 titulo m-0">📶 Tráfico EchoLink</h2>
            </div>

            <!-- FILA 1 -->
            <div class="row mt-3">
                <div class="col-md-4 mb-3">
                    <div class="card h-100 p-3 card-hover">
                        <h5>🕒 Actividad por Hora</h5>
                        <canvas id="porHora"></canvas>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 p-3 card-hover">
                        <h5>📅 Conexiones por Día</h5>
                        <canvas id="porDia"></canvas>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 p-3 card-hover">
                        <h5>🥇 Top 10 Indicativos</h5>
                        <canvas id="topIndicativos"></canvas>
                    </div>
                </div>
            </div>

            <!-- FILA 2 -->
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card h-100 p-3 card-hover">
                        <h5>🌎 Distribución por País</h5>
                        <canvas id="porPaisBarras" class="canvas-fila2"></canvas>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 p-3 card-hover">
                        <h5>🔄 Nuevos vs Recurrentes</h5>
                        <canvas id="nuevosVsRecurrentes" class="canvas-fila2"></canvas>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 p-3 card-hover">
                        <h5>🖥️ Usuarios vs Nodos</h5>
                        <canvas id="usuariosVsNodos" class="canvas-fila2"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JS de Bootstrap y Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
new Chart(document.getElementById('porHora'), {
    type: 'bar',
    data: {
        labels: [...Array(24).keys()].map(h => h.toString().padStart(2, '0') + 'h'),
        datasets: [{
            label: 'TX por Hora',
            data: <?= json_encode(array_values($por_hora)) ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.7)'
        }]
    }
});
new Chart(document.getElementById('porDia'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($por_dia)) ?>,
        datasets: [{
            label: 'Conexiones',
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
        labels: <?= json_encode(array_keys(array_slice($indicativos, 0, 10, true))) ?>,
        datasets: [{
            label: 'TX por Indicativo',
            data: <?= json_encode(array_values(array_slice($indicativos, 0, 10, true))) ?>,
            backgroundColor: 'rgba(153, 102, 255, 0.7)'
        }]
    }
});
new Chart(document.getElementById('porPaisBarras'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($paises_conectados)) ?>,
        datasets: [{
            label: 'TX por País',
            data: <?= json_encode(array_values($paises_conectados)) ?>,
            backgroundColor: 'rgba(255, 159, 64, 0.7)'
        }]
    }
});
new Chart(document.getElementById('nuevosVsRecurrentes'), {
    type: 'bar',
    data: {
        labels: ['Nuevos', 'Recurrentes'],
        datasets: [{
            label: 'Cantidad',
            data: [<?= $usuarios_nuevos ?>, <?= $usuarios_recurrentes ?>],
            backgroundColor: ['#36A2EB', '#FF9F40']
        }]
    }
});
new Chart(document.getElementById('usuariosVsNodos'), {
    type: 'bar',
    data: {
        labels: ['Usuarios', 'Nodos'],
        datasets: [{
            label: 'Cantidad',
            data: [<?= $total_usuarios ?>, <?= $total_nodos ?>],
            backgroundColor: ['#4BC0C0', '#FF6384']
        }]
    }
});
</script>
</body>
</html>
