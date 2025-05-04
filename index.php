<?php
    session_start();
    require 'includes/environment.php';

    if (
        (isset($_SESSION['integridad_modificada']) && $_SESSION['integridad_modificada'] === true) ||
        (isset($_SESSION['integridad_eliminada']) && $_SESSION['integridad_eliminada'] === true)
    ){
        die('Error: El sistema ha sido comprometido. No se puede continuar.');
    }
    
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', 0);
    
    function getSystemStats() {
        $temp = @file_get_contents('/sys/class/thermal/thermal_zone0/temp');
        $uptime = shell_exec("uptime -p");
        $mem = shell_exec("free -m");
        preg_match("/Mem:\s+(\d+)\s+(\d+)/", $mem, $match);
        $mem_info = isset($match[1]) ? intval($match[2]) . "MB / " . intval($match[1]) . "MB" : "No disponible";
        $disk = shell_exec("df -h / | tail -1");
        $disk_info = preg_split('/\s+/', $disk);
    
        return [
            'temp_raw' => $temp,
            'temp' => $temp ? round($temp / 1000, 1) . ' °C' : 'No disponible',
            'uptime' => trim($uptime),
            'memory' => $mem_info,
            'disk' => $disk_info[2] . ' usados de ' . $disk_info[1]
        ];
    }
    
    function getLastTxTime() {
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
    
    function getLastConnections($limit = 50) {
        $log = @file('/var/log/svxlink');
        $result = [];
        if ($log) {
            foreach (array_reverse($log) as $line) {
                if (preg_match('/(\S+): EchoLink QSO state changed to CONNECTED/', $line, $m)) {
                    $hora_raw = substr($line, 0, 8);
                    $fecha_raw = substr($line, 0, 15);
                    $timestamp = strtotime($fecha_raw);
                    $hora = $timestamp ? date('H:i:s', $timestamp) : $hora_raw;
                    $callsign = $m[1];
                    $result[] = [$hora, $callsign];
                    if (count($result) >= $limit) break;
                }
            }
        }
        return $result;
    }
    
    function getTxCount() {
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
    
    function getServiceStatus() {
        $status = trim(shell_exec('systemctl is-active svxlink'));
        return $status === 'active' ? '🟢 Operativo' : '🔴 Detenido';
    }
    
    function getSvxlinkVersion() {
        $line = shell_exec("svxlink 2>&1 | head -n 1");
        if ($line && preg_match('/^(SvxLink v[\\d\\.\\@]+)/', trim($line), $matches)) {
            return $matches[1];
        }
        return 'Versión desconocida';
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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <title><?php echo $titleSite; ?> - Dashboard</title>
</head>
<body>
    <div class="container-fluid bg-body-content">
        <div class="row">
            <?php require 'includes/sidebar-menu.php'; ?>

            <!-- Contenido principal -->
            <div class="col-12 col-md-10 p-3">
                <div class="d-flex align-items-center">
                    <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">
                        ☰ 
                    </button>
                <!-- Contenido -->
                    <h2 class="fs-4 titulo m-0"><?= $tituloDashboard; ?></h2>
                </div>
                <img src="<?= $imagenLogo; ?>" alt="Logo" class="img-fluid mb-4 mt-2" style="max-height: 150px; width: 100%; object-fit: cover; border-radius: 8px;">
                <div class="card p-3 mb-4 shadow-sm border-0" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; border-radius: 16px;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">🔗 Nodo EchoLink</h5>
                        <span class="badge bg-light text-dark px-3 py-2 fw-semibold"><?php echo $statusNodo; ?></span>
                    </div>
                    <p class="mb-1">📡 Indicativo: <strong><?= $indicativo ?></strong></p>
                    <p class="mb-0">🛰️ Última transmisión: <strong><?= $lastTx ?></strong></p>
                    <p class="mb-1">🧭 <?= getSvxlinkVersion(); ?></p>
                </div>
                
                <div class="row">
                    <!-- TRANSMISIONES -->
                    <div class="col-md-4 col-12 mb-3">
                        <div class="card h-100 p-3">
                            <h6>📡 <strong>Transmisiones Hoy</strong></h6>
                            <p>🔢 <?php echo $txCount; ?> transmisiones</p>
                        </div>
                    </div>

                    <!-- ESTADO -->
                    <div class="col-md-4 col-12 mb-3">
                        <div class="card h-100 p-3" id="txrx-card"></div>
                    </div>

                    <!-- BIENVENIDA -->
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 p-3">
                            <h6>👨‍🔧 Bienvenido <?= $radioaficionado ?>!</h6>
                            <p>📈 <strong>Uptime actual:</strong> <?= $stats['uptime']; ?></p>
                            <p>🕒 <strong>Desde:</strong> <?= date("d/m/Y H:i", time() - $stats['uptime_seconds']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- RELOJ UTC -->
                    <div class="col-md-4 col-12 mb-3">
                        <div class="card h-100 p-3 text-center">
                            <h6>🌐 Reloj UTC</h6>
                            <p id="utcClock" style="font-size: 1.2rem; font-weight: bold;">--:--:--</p>
                            <h6>🇨🇱 Hora Local (<?= $utcOffset; ?>)</h6>
                            <p id="localClock" style="font-size: 1.2rem; font-weight: bold;">--:--:--</p>
                        </div>
                    </div>
                    <!-- CLIMA -->
                    <div class="col-md-4 col-12 mb-3">
                        <div class="card h-100 p-3 text-center">
                            <h6>⛅ Clima actual</h6>
                            <div id="climaInfo">Cargando clima...</div>
                        </div>
                    </div>
                    <!-- FRECUENCIA NODO -->
                    <div class="col-md-4 col-12 mb-3">
                        <div class="card h-100 p-3 text-center">
                            <h6>📶 FRECUENCIA NODO</h6>
                            <p>📻 Modo Simplex</p>
                            <h6><?= $frecuencia; ?></h6>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- ACTIVIDAD -->
                    <div class="col-md-6 col-12 mb-3">
                        <div class="card h-100 p-3">
                            <h6>📈 Actividad Reciente de Estaciones</h6>
                            <div style="max-height: 200px; overflow-y: auto;">
                                <table class="table table-sm mb-0">
                                    <thead class="sticky-top bg-light">
                                        <tr><th>Hora</th><th>Indicativo</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lastConnections as $conn): ?>
                                        <tr>
                                            <td><?php echo $conn[0]; ?></td>
                                            <td>
                                                <a href="https://www.qrz.com/db/<?php echo urlencode($conn[1]); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php echo htmlspecialchars($conn[1]); ?> 🔍
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- RECURSOS -->
                    <div class="col-md-6 col-12 mb-3">
                        <div class="card h-100 p-3">
                            <h6>🌡️ Temperatura y Recursos</h6>
                            <p>🌡 <?php echo $stats['temp']; ?></p>
                            <div class="progress mb-2">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo min(100, ($tempValue / 85) * 100); ?>%"></div>
                            </div>
                            <p>💾 Memoria: <?php echo $stats['memory']; ?></p>
                            <div class="progress mb-2">
                                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $mem; ?>%"></div>
                            </div>
                            <p>🗄 Disco: <?php echo $stats['disk']; ?></p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Scripts de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function cargarEstadoTxRx() {
            fetch('includes/status-txrx.php')
                .then(response => response.text())
                .then(html => {
                document.getElementById('txrx-card').innerHTML = html;
            });
        }
        setInterval(cargarEstadoTxRx, 5000);
        cargarEstadoTxRx();
    </script>

    <!-- Script Offset -->
    <script>
        const utcOffset = <?= json_encode($utcOffset ?? "-4") ?>;
        function updateClocks() {
            const now = new Date();
            const utc = now.toISOString().substr(11, 8);
            document.getElementById('utcClock').innerText = '🕒 ' + utc;
            const offsetMs = Number(utcOffset) * 60 * 60 * 1000;
            const local = new Date(now.getTime() + offsetMs);
            const localTime = local.toISOString().substr(11, 8);
            document.getElementById('localClock').innerText = '🕒 ' + localTime;
        }
        setInterval(updateClocks, 1000);
        updateClocks();
    </script>

    <!-- Script Clima -->
    <script>
        const ciudad = <?= json_encode($ciudad ?? "Santiago") ?>;
        function mostrarClima(lat, lon) {
            fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current_weather=true`)
                .then(res => res.json())
                .then(data => {
                    const temp = data.current_weather.temperature;
                    const weathercode = data.current_weather.weathercode;
                    const descripcion = obtenerDescripcionClima(weathercode);
                    document.getElementById("climaInfo").innerHTML = `
                        <p style="font-size: 1.3rem;"><strong>🌡️ ${temp}°C</strong></p>
                        <p>${descripcion} - ${ciudad}</p>
                    `;
                });
        }

        function obtenerClimaDesdeCiudad(nombreCiudad) {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(nombreCiudad)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.length > 0) {
                        const lat = data[0].lat;
                        const lon = data[0].lon;
                        mostrarClima(lat, lon);
                    } else {
                        document.getElementById("climaInfo").innerText = "No se encontró la ciudad.";
                    }
            });
        }

        function obtenerDescripcionClima(codigo) {
            const descripciones = {
                0: "Despejado", 1: "Principalmente despejado", 2: "Parcialmente nublado", 3: "Nublado",
                45: "Niebla", 51: "Lluvia ligera", 61: "Lluvia moderada", 71: "Nieve ligera", 80: "Chubascos"
            };
            return descripciones[codigo] ?? "Clima desconocido";
        }
        obtenerClimaDesdeCiudad(ciudad);

    </script>
</body>
</html>
