<?php
require 'includes/environment.php';
session_start();

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

function getTemperature()
{
  $temp = @file_get_contents('/sys/class/thermal/thermal_zone0/temp');
  return $temp ? round($temp / 1000, 1) : t('not_available', 'Not available');
}

function getUptime()
{
  return trim(shell_exec("uptime -p"));
}

function getMemory()
{
  $free = shell_exec("free -m");
  preg_match("/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/", $free, $mem);
  preg_match("/Swap:\s+(\d+)\s+(\d+)\s+(\d+)/", $free, $swap);
  return [
    'mem' => isset($mem[1]) ? "$mem[2] MB / $mem[1] MB" : t('not_available', 'Not available'),
    'mem_percent' => isset($mem[1]) ? round(($mem[2] / $mem[1]) * 100, 1) : 0,
    'swap' => isset($swap[1]) ? "$swap[2] MB / $swap[1] MB" : t('not_available', 'Not available'),
    'swap_percent' => isset($swap[1]) ? round(($swap[2] / $swap[1]) * 100, 1) : 0
  ];
}

function getDisk()
{
  return shell_exec("df -h /");
}

function getServiceStatus()
{
  $status = trim(shell_exec('systemctl is-active svxlink'));
  return $status === 'active'
    ? '<span class="badge bg-success">' . t('active', 'Active') . '</span>'
    : '<span class="badge bg-danger">' . t('inactive', 'Inactive') . '</span>';
}

function getLastLogLines($lines = 15)
{
  return shell_exec("tail -n $lines /var/log/svxlink");
}

function getSystemVersion()
{
  return trim(shell_exec('uname -a'));
}

$temp = getTemperature();
$mem = getMemory();
$uptime_seconds = (int) shell_exec("cut -d. -f1 /proc/uptime");
$porcentaje_uptime = min(round(($uptime_seconds / (7 * 86400)) * 100), 100);

$service_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['password'])) {
  if (md5($_POST['password']) === $clave_acceso) {
    $accion = $_POST['action'];
    if ($accion === 'start') {
      shell_exec('sudo /bin/systemctl start svxlink');
    }
    if ($accion === 'stop') {
      shell_exec('sudo /bin/systemctl stop svxlink');
    }
    if ($accion === 'restart') {
      shell_exec('sudo /bin/systemctl restart svxlink');
    }
    if ($accion === 'reboot') {
      shell_exec('sudo /sbin/reboot');
    }
    $service_message = t('statusnode_action_executed', 'Action executed successfully.') . ' (' . htmlspecialchars($accion) . ')';
  } else {
    $service_message = t('incorrect_current_password', 'Incorrect current password.');
  }
}
?>

<!doctype html>
<html lang="<?= htmlspecialchars($idioma); ?>">

<head>
  <meta charset="UTF-8">
  <title><?php echo $titleSite; ?> - <?= t('menu_node_status', 'Node Status'); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style/style.css.php">
  <link rel="shortcut icon" href="img/favicon.png" type="image/png">
  <style>
    body {
      background-color: #f0f2f5;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .card {
      border-radius: 20px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      border: none;
      transition: transform 0.2s ease;
    }

    .hover-effect:hover {
      transform: scale(1.01);
    }

    .card h6 {
      font-size: 16px;
      font-weight: 600;
      color: #333;
      margin-bottom: 10px;
    }

    .card p,
    .card pre {
      font-size: 14px;
      color: #555;
    }

    .progress {
      height: 10px;
      border-radius: 10px;
      margin-top: 5px;
    }

    .titulo {
      font-size: 24px;
      font-weight: bold;
      color: #222;
    }

    pre {
      background-color: #f8f9fa;
      border-radius: 10px;
      padding: 15px;
    }

    .btn {
      border-radius: 10px !important;
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
          <h2 class="fs-4 titulo m-0"><?= t('menu_node_status', 'Node Status'); ?> <?php echo getServiceStatus(); ?></h2>
        </div>

        <div class="row mt-3">
          <div class="col-md-3">
            <div class="card p-3 mb-3 hover-effect text-center">
              <h6>⏱️ <strong><?= t('uptime', 'Uptime'); ?></strong></h6>
              <div style="font-size: 15px; color: #888;"><?= t('statusnode_system_on_since', 'System has been running for'); ?></div>
              <div style="font-size: 18px; font-weight: bold; color: #2e7d32;"><?= htmlspecialchars(getUptime()); ?></div>
            </div>
          </div>

          <div class="col-md-3">
            <div class="card p-3 mb-3 hover-effect">
              <h6>🌡️ <strong><?= t('statusnode_cpu_temp', 'CPU Temp.'); ?></strong></h6>
              <p><?= htmlspecialchars((string)$temp) ?></p>
              <?php if ($temp !== t('not_available', 'Not available')): ?>
                <div class="progress">
                  <div class="progress-bar bg-danger" style="width: <?= min(round(($temp / 85) * 100), 100) ?>%;"></div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="col-md-3">
            <div class="card p-3 mb-3 hover-effect">
              <h6>🧠 <strong><?= t('statusnode_ram_used', 'RAM Used'); ?></strong></h6>
              <p><?= htmlspecialchars($mem['mem']); ?></p>
              <div class="progress">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $mem['mem_percent']; ?>%"></div>
              </div>
            </div>
          </div>

          <div class="col-md-3">
            <div class="card p-3 mb-3 hover-effect">
              <h6>💾 <strong><?= t('statusnode_swap_used', 'SWAP Used'); ?></strong></h6>
              <p><?= htmlspecialchars($mem['swap']); ?></p>
              <div class="progress">
                <div class="progress-bar bg-secondary" role="progressbar" style="width: <?= $mem['swap_percent']; ?>%"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="card p-3 mb-3">
          <h6>🖥️ <strong><?= t('statusnode_realtime_cpu_temp', 'Real-time CPU Usage and Temperature'); ?></strong></h6>
          <canvas id="cpuChart" height="100"></canvas>
          <div class="mt-2">
            <strong><?= t('statusnode_last_cpu', 'Last CPU'); ?>:</strong> <span id="cpuValue">--</span>% |
            <strong><?= t('statusnode_temp', 'Temp'); ?>:</strong> <span id="tempValue">--</span>°C
          </div>
        </div>

        <div class="card p-3 mb-3">
          <h6>⚙️ <strong><?= t('statusnode_service_controls', 'SVXLink Service Controls'); ?></strong></h6>
          <form method="post">
            <div class="form-group mb-2">
              <label for="password"><?= t('password', 'Password'); ?>:</label>
              <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="action" value="start" class="btn btn-success"><?= t('statusnode_start', 'Start'); ?></button>
            <button type="submit" name="action" value="stop" class="btn btn-danger"><?= t('statusnode_stop', 'Stop'); ?></button>
            <button type="submit" name="action" value="restart" class="btn btn-warning"><?= t('statusnode_restart', 'Restart'); ?></button>
            <button type="submit" name="action" value="reboot" class="btn btn-secondary"
              onclick="return confirm('<?= t('statusnode_confirm_reboot_server', 'Are you sure you want to reboot the entire system?'); ?>');">
              <?= t('statusnode_reboot_server', 'Reboot Server'); ?>
            </button>
          </form>

          <?php if ($service_message): ?>
            <div class="alert alert-info mt-2"><?= htmlspecialchars($service_message) ?></div>
          <?php endif; ?>
        </div>

        <div class="card p-3 mb-3">
          <h6>💽 <strong><?= t('statusnode_disk_space', 'Disk Space'); ?></strong></h6>
          <pre><?php echo htmlspecialchars(getDisk()); ?></pre>
        </div>

        <div class="card p-3 mb-3">
          <h6>📋 <strong><?= t('statusnode_last_log_lines', 'Last SVXLink Log Lines'); ?></strong></h6>
          <pre style="max-height: 200px; overflow-y: auto;"><?php echo htmlspecialchars(getLastLogLines()); ?></pre>
        </div>

        <div class="card p-3 mb-3">
          <h6>📦 <strong><?= t('statusnode_system_version', 'System Version'); ?></strong></h6>
          <p><code><?php echo htmlspecialchars(getSystemVersion()); ?></code></p>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    const txtCpuLabel = <?= json_encode(t('statusnode_chart_cpu', 'CPU (%)')); ?>;
    const txtTempLabel = <?= json_encode(t('statusnode_chart_temp', 'Temp (°C)')); ?>;

    const ctx = document.getElementById('cpuChart').getContext('2d');
    const cpuChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: [],
        datasets: [
          {
            label: txtCpuLabel,
            data: [],
            borderColor: 'rgba(255,99,132,1)',
            backgroundColor: 'rgba(255,99,132,0.2)',
            fill: true,
            tension: 0.3
          },
          {
            label: txtTempLabel,
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
      $.get('get_cpu.php', function (cpuData) {
        $.get('get_temp.php', function (tempData) {
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
    fetchCpuAndTemp();
  </script>
</body>
</html>
