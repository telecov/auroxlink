<?php
    require 'includes/environment.php';

    // Oculta todos los errores excepto los fatales
    error_reporting(E_ERROR | E_PARSE);
    // Opcional: desactiva la visualización de errores en pantalla
    ini_set('display_errors', 0);

    function getTemperature() {
        $temp = @file_get_contents('/sys/class/thermal/thermal_zone0/temp');
        return $temp ? round($temp / 1000, 1) . ' °C' : 'No disponible';
    }

    function getUptime() {
        return shell_exec("uptime -p");
    }

    function getMemory() {
        $free = shell_exec("free -m");
        preg_match("/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/", $free, $mem);
        preg_match("/Swap:\s+(\d+)\s+(\d+)\s+(\d+)/", $free, $swap);
        return [
            'mem' => isset($mem[1]) ? "$mem[2] MB / $mem[1] MB" : 'No disponible',
            'swap' => isset($swap[1]) ? "$swap[2] MB / $swap[1] MB" : 'No disponible'
        ];
    }

    function getDisk() {
        return shell_exec("df -h /");
    }

    function getServiceStatus() {
        $status = trim(shell_exec('systemctl is-active svxlink'));
        return $status === 'active' ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>';
    }

    function getLastLogLines($lines = 15) {
        return shell_exec("tail -n $lines /var/log/svxlink");
    }

    function getSystemVersion() {
        return trim(shell_exec('uname -a'));
    }
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <title><?php echo $titleSite; ?> - Estado Nodo</title>
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
                    <h2 class="fs-4 titulo m-0">Estado del Nodo <?php echo getServiceStatus(); ?></h2>
                </div>
                <div class="row mt-3">
                    <!-- CARD UPTIME -->
                    <div class="col-md-3">
                        <div class="card p-3 mb-3">
                            <h6>🕒<strong> Uptime</strong></h6>
                            <p><?php echo getUptime(); ?></p>
                        </div>
                    </div>

                    <!-- CARD TEMP CPU -->
                    <div class="col-md-3">
                        <div class="card p-3 mb-3">
                            <h6>🌡️ <strong>Temp. CPU</strong></h6>
                            <p><?php echo getTemperature(); ?></p>
                        </div>
                    </div>

                    <!-- CARD RAM -->
                    <div class="col-md-3">
                        <div class="card p-3 mb-3">
                            <h6>🧠 <strong>RAM Usada</strong></h6>
                            <p><?php echo getMemory()['mem']; ?></p>
                        </div>
                    </div>

                    <!-- CARD SWAP -->
                    <div class="col-md-3">
                        <div class="card p-3 mb-3">
                            <h6>💾 <strong>SWAP Usada</strong></h6>
                            <p><?php echo getMemory()['swap']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- CARD GRAFICO CPU TEMPERATURA -->
                <div class="card p-3 mb-3">
                    <h6>🖥️ <strong>Uso de CPU y Temperatura en tiempo real</strong></h6>
                    <canvas id="cpuChart" height="100"></canvas>
                    <div class="mt-2">
                        <strong>Último CPU:</strong> <span id="cpuValue">--</span>% |
                        <strong>Temp:</strong> <span id="tempValue">--</span>°C
                    </div>
                </div>

                <!-- CONTROLES SVXLINK -->
                <div class="card p-3 mb-3">
                    <h6>⚙️ <strong>Controles del Servicio SVXLink</strong></h6>
                    <form method="post">
                        <div class="form-group mb-2">
                            <label for="password">Contraseña:</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" name="action" value="start" class="btn btn-success">Iniciar</button>
                        <button type="submit" name="action" value="stop" class="btn btn-danger">Detener</button>
                        <button type="submit" name="action" value="restart" class="btn btn-warning">Reiniciar</button>
                        <button type="submit" name="action" value="reboot" class="btn btn-secondary" onclick="return confirm('¿Estás seguro de que deseas reiniciar el sistema completo?');">Reiniciar Raspberry</button>
                    </form>

                    <?php
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && md5($_POST['password']) === $clave_acceso) {
                            $accion = $_POST['action'];
                            if ($accion === 'start') shell_exec('sudo /bin/systemctl start svxlink');
                            if ($accion === 'stop') shell_exec('sudo /bin/systemctl stop svxlink');
                            if ($accion === 'restart') shell_exec('sudo /bin/systemctl restart svxlink');
                            if ($accion === 'reboot') shell_exec('sudo /sbin/reboot');
                            echo "<div class='alert alert-info mt-2'>Acción '$accion' ejecutada correctamente.</div>";
                        }
                    ?>
                </div>

                <div class="card p-3 mb-3">
                    <h6>💽 <strong>Espacio en Disco</strong></h6>
                    <pre><?php echo getDisk(); ?></pre>
                </div>

                <div class="card p-3 mb-3">
                    <h6>📋 <strong>Últimas líneas del log SVXLink</strong></h6>
                    <pre style="max-height: 200px; overflow-y: auto; background-color: #f5f5f5; padding: 10px;">
                        <?php echo getLastLogLines(); ?>
                    </pre>
                </div>

                <div class="card p-3 mb-3">
                    <h6>📦 <strong>Versión del Sistema</strong></h6>
                    <p><code><?php echo getSystemVersion(); ?></code></p>
                </div>

            </div>
        </div>
    </div>

    <!-- Scripts de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        const ctx = document.getElementById('cpuChart').getContext('2d');
        const cpuChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'CPU (%)',
                        data: [],
                        borderColor: 'rgba(255,99,132,1)',
                        backgroundColor: 'rgba(255,99,132,0.2)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                    label: 'Temp (°C)',
                    data: [],
                    borderColor: 'rgba(54,162,235,1)',
                    backgroundColor: 'rgba(54,162,235,0.2)',
                    fill: true,
                    tension: 0.3
                    }
                ]
            },
            options: {
                animation: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        function fetchCpuAndTemp() {
            const now = new Date().toLocaleTimeString();
            $.get('get_cpu.php', function(cpuData) {
                $.get('get_temp.php', function(tempData) {

                    if (cpuChart.data.labels.length > 20) {
                        cpuChart.data.labels.shift();
                        cpuChart.data.datasets[0].data.shift();
                        cpuChart.data.datasets[1].data.shift();
                    }

                    cpuChart.data.labels.push(now);
                    cpuChart.data.datasets[0].data.push(parseFloat(cpuData));
                    cpuChart.data.datasets[1].data.push(parseFloat(tempData));
                    cpuChart.update();

                    document.getElementById('cpuValue').innerText = parseFloat(cpuData).toFixed(1);
                    document.getElementById('tempValue').innerText = parseFloat(tempData).toFixed(1);
                });
            });
        }

        setInterval(fetchCpuAndTemp, 2000);
    </script>
</body>
</html>
