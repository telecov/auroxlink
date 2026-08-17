<?php
$env_path = __DIR__ . '/includes/environment.php';

session_start();

if (file_exists($env_path)) {
    include $env_path;
} else {
    die('Archivo de configuración no encontrado.');
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

if (!is_array($lang)) {
    $lang = [];
}

function t($key, $default = '')
{
    global $lang;
    return $lang[$key] ?? $default;
}

/* =========================================================
   LOGIN SEGURO
========================================================= */
if (!isset($_SESSION['autenticado'])) {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['clave']) &&
        md5($_POST['clave']) === $clave_acceso
    ) {
        $_SESSION['autenticado'] = true;
        header("Location: custom.php");
        exit;
    }

    echo '<!DOCTYPE html>
    <html lang="' . htmlspecialchars($idioma) . '">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . t('settings_secure_access', 'Configuration Access') . '</title>
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="card p-4 shadow-sm">
                        <h4>🔐 ' . t('settings_secure_login', 'Secure Login') . '</h4>
                        <form method="post">
                            <input type="password" name="clave" class="form-control mb-3" placeholder="' . t('password', 'Password') . '" required>
                            <button type="submit" class="btn btn-primary btn-block mb-2">' . t('settings_enter', 'Enter') . '</button>
                            <a href="index.php" class="btn btn-outline-secondary btn-block">⬅️ ' . t('settings_back_dashboard', 'Back to Dashboard') . '</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

require $env_path;

/* =========================================================
   CARGAR ESTILOS
========================================================= */
$styleFile = __DIR__ . '/estilos.json';
$style = [];

if (file_exists($styleFile)) {
    $style = json_decode(file_get_contents($styleFile), true) ?? [];
}

$colorFondo   = $style['color_fondo'] ?? '#e9ecef';
$colorSidebar = $style['color_sidebar'] ?? '#212529';
$colorTitulo  = $style['color_titulo'] ?? '#000000';

/* =========================================================
   MENSAJES
========================================================= */
$guardado_ok = false;
$guardado_ip = false;
$guardado_wifi = false;
$error_red = '';
$message = '';
$auth_output = '';

/* =========================================================
   FUNCIONES
========================================================= */
function validarIP($ip)
{
    return filter_var($ip, FILTER_VALIDATE_IP);
}

function obtenerInterfacesConIP()
{
    $salida = shell_exec("ip -4 -o addr show up scope global | awk '{print \$2, \$4}'");
    $interfaces = [];

    foreach (explode("\n", trim($salida)) as $linea) {
        if (trim($linea) === '') {
            continue;
        }

        $partes = explode(" ", trim($linea));
        if (count($partes) >= 2) {
            $iface = $partes[0];
            $ip = $partes[1];
            $ip_sola = explode("/", $ip)[0];
            $interfaces[$iface] = $ip_sola;
        }
    }

    return $interfaces;
}

function escanearRedesWifi()
{
    $salida = shell_exec("sudo /sbin/iwlist wlan0 scan 2>/dev/null");
    preg_match_all('/ESSID:\"([^\"]+)\"/', $salida, $coincidencias);
    return $coincidencias[1] ?? [];
}

/* =========================================================
   TAILSCALE ESTADO
========================================================= */
$ip = trim(shell_exec("tailscale ip -4 2>/dev/null"));
$hostname = gethostname();

$status_raw = shell_exec("tailscale status --json 2>/dev/null");
$status_data = json_decode($status_raw, true);
$online = isset($status_data['Self']['Online']) && $status_data['Self']['Online'];

$status = $online
    ? '<span class="text-success">🟢 ' . t('online', 'Online') . '</span>'
    : '<span class="text-danger">🔴 Offline</span>';

/* =========================================================
   PROCESAR FORMULARIOS
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vpn_disconnect'])) {
    shell_exec("sudo tailscale down 2>&1");
    $status = '<span class="text-danger">🔴 ' . t('manual_disconnect', 'Manually disconnected') . '</span>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['guardar_estilo'])) {
        $nuevo_estilo = [
            'nombre_zona'      => $_POST['nombre_zona'] ?? '',
            'radioaficionado'  => $_POST['radioaficionado'] ?? '',
            'modo'             => $_POST['modo'] ?? '',
            'frecuencia'       => $_POST['frecuencia'] ?? '',
            'offset'           => $_POST['offset'] ?? '',
            'tono'             => $_POST['tono'] ?? '',
            'ubicacion'        => $_POST['ubicacion'] ?? '',
            'aprs_web'         => $_POST['aprs_web'] ?? '',
            'titulo_dashboard' => $_POST['titulo_dashboard'] ?? '',
            'indicativo'       => $_POST['indicativo'] ?? '',
            'ciudad'           => $_POST['ciudad'] ?? '',
            'utc_offset'       => $_POST['utc_offset'] ?? '',
            'idioma'           => $_POST['idioma'] ?? 'es',
            'color_sidebar'    => $_POST['color_sidebar'] ?? '#212529',
            'color_fondo'      => $_POST['color_fondo'] ?? '#e9ecef',
            'color_titulo'     => $_POST['color_titulo'] ?? '#000000',
            'logo'             => $_POST['logo_actual'] ?? 'auroralink_banner.png',
            'foto_admin'       => $_POST['foto_admin_actual'] ?? 'img/admin.png'
        ];

        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $nombre_archivo = 'auroxlink_banner.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/' . $nombre_archivo)) {
                $nuevo_estilo['logo'] = $nombre_archivo;
            }
        }

        if (isset($_FILES['foto_admin']) && $_FILES['foto_admin']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['foto_admin']['name'], PATHINFO_EXTENSION));
            $nombre_archivo_admin = 'admin.' . $ext;
            if (move_uploaded_file($_FILES['foto_admin']['tmp_name'], __DIR__ . '/img/' . $nombre_archivo_admin)) {
                $nuevo_estilo['foto_admin'] = 'img/' . $nombre_archivo_admin;
            }
        }

        file_put_contents($styleFile, json_encode($nuevo_estilo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $style = $nuevo_estilo;

        $colorFondo   = $style['color_fondo'] ?? '#e9ecef';
        $colorSidebar = $style['color_sidebar'] ?? '#212529';
        $colorTitulo  = $style['color_titulo'] ?? '#000000';

        $guardado_ok = true;
    }

    if (isset($_POST['guardar_ip'])) {
        $interfaz = $_POST['interfaz'] ?? '';
        $ipManual = $_POST['ip'] ?? '';
        $gateway = $_POST['gateway'] ?? '';
        $dns = $_POST['dns'] ?? '';

        if ($interfaz && validarIP($ipManual) && validarIP($gateway) && validarIP($dns)) {
            $nombre_conexion = trim(shell_exec("nmcli -g GENERAL.CONNECTION device show " . escapeshellarg($interfaz)));

            if ($nombre_conexion && $nombre_conexion !== "--") {
                $commands = [
                    "sudo nmcli connection modify \"$nombre_conexion\" ipv4.method manual",
                    "sudo nmcli connection modify \"$nombre_conexion\" ipv4.ignore-auto-dns yes",
                    "sudo nmcli connection modify \"$nombre_conexion\" ipv4.ignore-auto-routes yes",
                    "sudo nmcli connection modify \"$nombre_conexion\" ipv4.addresses $ipManual/24",
                    "sudo nmcli connection modify \"$nombre_conexion\" ipv4.gateway $gateway",
                    "sudo nmcli connection modify \"$nombre_conexion\" ipv4.dns $dns",
                    "sudo nmcli device disconnect \"$interfaz\"",
                    "sudo nmcli device connect \"$interfaz\""
                ];

                foreach ($commands as $cmd) {
                    shell_exec($cmd);
                }

                $guardado_ip = true;
            } else {
                $error_red = '❌ ' . t('cannot_detect_active_connection', 'Could not detect the active connection.');
            }
        } else {
            $error_red = '❌ ' . t('invalid_ip_gateway_dns', 'Invalid IP address, gateway or DNS.');
        }
    }

    if (isset($_POST['guardar_wifi'])) {
        if (!empty($_POST['ssid']) && !empty($_POST['wifi_password'])) {
            $ssid = escapeshellarg($_POST['ssid']);
            $pass = escapeshellarg($_POST['wifi_password']);
            shell_exec("sudo nmcli dev wifi connect $ssid password $pass");
            $guardado_wifi = true;
        }
    }

    if (isset($_POST['guardar_telegram'])) {
        $token = $_POST['token'] ?? '';
        $chat_id = $_POST['chat_id'] ?? '';

        if ($token && $chat_id) {
            $telegram_config = [
                'token'   => $token,
                'chat_id' => $chat_id
            ];

            file_put_contents(
                __DIR__ . '/telegram_config.json',
                json_encode($telegram_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            $message = '✅ ' . t('telegram_saved', 'Telegram settings saved successfully.');
        }
    }

    if (isset($_POST['reboot'])) {
        shell_exec('sudo reboot');
        exit;
    }

    if (isset($_POST['change_password'])) {
        $password_actual = $_POST['password_actual'] ?? '';
        $password_nueva = $_POST['password_nueva'] ?? '';

        if (md5($password_actual) === $clave_acceso) {
            $clave_nueva_hash = md5($password_nueva);

            $contenido = file_get_contents($env_path);
            $contenido_nuevo = preg_replace(
                "/\\\$clave_acceso\s*=\s*'[^']*';/",
                "\$clave_acceso = '" . $clave_nueva_hash . "';",
                $contenido
            );

            if (file_put_contents($env_path, $contenido_nuevo)) {
                $message = '✅ ' . t('password_updated', 'Password updated successfully.');
            } else {
                $message = '⚠️ ' . t('cannot_write_environment', 'Could not write to includes/environment.php.');
            }
        } else {
            $message = '❌ ' . t('incorrect_current_password', 'Incorrect current password.');
        }
    }

    if (isset($_POST['authkey'])) {
        $authkey_text = trim($_POST['authkey']);
        $authkey = escapeshellarg($authkey_text);

        if (!is_dir('/etc/auroxlink')) {
            mkdir('/etc/auroxlink', 0700, true);
        }

        file_put_contents('/etc/auroxlink/tailscale.key', $authkey_text);
        $auth_output = shell_exec("sudo tailscale down; sudo tailscale up --authkey=$authkey --ssh --shields-up=false 2>&1");

        $ip = trim(shell_exec("tailscale ip -4 2>/dev/null"));
        $status_raw = shell_exec("tailscale status --json 2>/dev/null");
        $status_data = json_decode($status_raw, true);
        $online = isset($status_data['Self']['Online']) && $status_data['Self']['Online'];

        $status = $online
            ? '<span class="text-success">🟢 ' . t('online', 'Online') . '</span>'
            : '<span class="text-danger">🔴 Offline</span>';
    }
}

$interfaces_disponibles = obtenerInterfacesConIP();
$redes_disponibles = escanearRedesWifi();

$telegram_config = [];
if (file_exists(__DIR__ . '/telegram_config.json')) {
    $telegram_config = json_decode(file_get_contents(__DIR__ . '/telegram_config.json'), true) ?? [];
}
$token_actual = $telegram_config['token'] ?? '';
$chat_id_actual = $telegram_config['chat_id'] ?? '';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($idioma); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <title><?php echo $titleSite; ?> - <?= t('menu_customization', 'Customization'); ?></title>
</head>
<body>
    <div class="container-fluid bg-body-content">
        <div class="row">
            <?php require 'includes/sidebar-menu.php'; ?>

            <div class="col-12 col-md-10 p-3">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">
                            ☰
                        </button>
                        <h2 class="fs-4 titulo m-0">🎨 <?= t('custom_system_customization', 'System Customization'); ?></h2>
                    </div>
                    <a href="index.php" class="btn btn-outline-secondary">⬅️ <?= t('settings_back_dashboard', 'Back to Dashboard'); ?></a>
                </div>

                <?php if ($guardado_ok): ?>
                    <div class="alert alert-success mt-3">✅ <?= t('custom_saved_successfully', 'Customization saved successfully.'); ?></div>
                <?php endif; ?>

                <?php if ($guardado_ip): ?>
                    <div class="alert alert-info mt-3">✅ <?= t('ip_saved_successfully', 'IP configured and applied successfully.'); ?></div>
                <?php endif; ?>

                <?php if ($guardado_wifi): ?>
                    <div class="alert alert-info mt-3">📶 <?= t('wifi_connected_successfully', 'WiFi connected successfully.'); ?></div>
                <?php endif; ?>

                <?php if ($error_red): ?>
                    <div class="alert alert-danger mt-3"><?= $error_red ?></div>
                <?php endif; ?>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-warning mt-3"><?= $message ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['restore']) && $_GET['restore'] === 'ok'): ?>
                    <div class="alert alert-success mt-3">✅ <?= t('backup_restore_success', 'Backup restored successfully.'); ?></div>
                <?php endif; ?>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="guardar_estilo" value="1">

                            <div class="form-group mb-3">
                                <label><?= t('custom_dashboard_name', 'Dashboard Name'); ?></label>
                                <input type="text" name="titulo_dashboard" class="form-control" value="<?= htmlspecialchars($style['titulo_dashboard'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_ham_name', 'Radio Amateur Name'); ?></label>
                                <input type="text" name="radioaficionado" class="form-control" value="<?= htmlspecialchars($style['radioaficionado'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('mode', 'Mode'); ?></label>
                                <input type="text" name="modo" class="form-control" value="<?= htmlspecialchars($style['modo'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_node_frequency', 'Node Frequency'); ?></label>
                                <input type="text" name="frecuencia" class="form-control" value="<?= htmlspecialchars($style['frecuencia'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('offset', 'Offset'); ?></label>
                                <input type="text" name="offset" class="form-control" value="<?= htmlspecialchars($style['offset'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('tone', 'Tone'); ?></label>
                                <input type="text" name="tono" class="form-control" value="<?= htmlspecialchars($style['tono'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_system_location', 'System Location'); ?></label>
                                <input type="text" name="ubicacion" class="form-control" value="<?= htmlspecialchars($style['ubicacion'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_aprs_server', 'APRS Web Server'); ?></label>
                                <input type="text" name="aprs_web" class="form-control" value="<?= htmlspecialchars($style['aprs_web'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_zone_name', 'Zone Name'); ?></label>
                                <input type="text" name="nombre_zona" class="form-control" value="<?= htmlspecialchars($style['nombre_zona'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('callsign', 'Callsign'); ?></label>
                                <input type="text" name="indicativo" class="form-control" value="<?= htmlspecialchars($style['indicativo'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('location', 'City'); ?></label>
                                <input type="text" name="ciudad" class="form-control" value="<?= htmlspecialchars($style['ciudad'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_utc_offset', 'UTC Offset'); ?></label>
                                <input type="text" name="utc_offset" class="form-control" value="<?= htmlspecialchars($style['utc_offset'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_system_language', 'System Language'); ?></label>
                                <select name="idioma" class="form-control">
                                    <option value="es" <?= (($style['idioma'] ?? 'es') === 'es') ? 'selected' : '' ?>>Español</option>
                                    <option value="en" <?= (($style['idioma'] ?? 'es') === 'en') ? 'selected' : '' ?>>English</option>
                                    <option value="pt" <?= (($style['idioma'] ?? 'es') === 'pt') ? 'selected' : '' ?>>Português</option>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_banner_logo', 'Banner Logo (PNG/JPG)'); ?></label>
                                <input type="hidden" name="logo_actual" value="<?= htmlspecialchars($style['logo'] ?? 'auroralink_banner.png') ?>">
                                <input type="file" name="logo" class="form-control">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_admin_photo', 'Admin Photo (PNG/JPG)'); ?></label>
                                <input type="hidden" name="foto_admin_actual" value="<?= htmlspecialchars($style['foto_admin'] ?? 'img/admin.png') ?>">
                                <input type="file" name="foto_admin" class="form-control">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_background_color', 'Background Color'); ?></label>
                                <input type="color" name="color_fondo" class="form-control form-control-color" value="<?= htmlspecialchars($colorFondo) ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_sidebar_color', 'Sidebar Color'); ?></label>
                                <input type="color" name="color_sidebar" class="form-control form-control-color" value="<?= htmlspecialchars($colorSidebar) ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('custom_title_color', 'Title Color'); ?></label>
                                <input type="color" name="color_titulo" class="form-control form-control-color" value="<?= htmlspecialchars($colorTitulo) ?>">
                            </div>

                            <button type="submit" class="btn btn-success mb-3">💾 <?= t('custom_save_customization', 'Save Customization'); ?></button>
                        </form>

                        <div class="card mt-4 mb-4">
                            <div class="card-body">
                                <h5 class="card-title">🗂️ <?= t('backup_restore_title', 'Backup and Restore'); ?></h5>
                                <p class="mb-3"><?= t('backup_restore_desc', 'Download a backup copy of customization and Telegram, or restore it from a file.'); ?></p>

                                <div class="d-grid gap-2 mb-3">
                                    <a href="backup_config.php" class="btn btn-outline-primary">
                                        ⬇️ <?= t('backup_download', 'Download configuration backup'); ?>
                                    </a>
                                </div>

                                <form method="POST" action="restore_config.php" enctype="multipart/form-data">
                                    <div class="form-group mb-3">
                                        <label><?= t('backup_restore_label', 'Restore from backup file (.json)'); ?></label>
                                        <input type="file" name="backup_file" class="form-control" accept=".json,application/json" required>
                                    </div>
                                    <button type="submit" class="btn btn-warning"
                                        onclick="return confirm('<?= t('backup_restore_confirm', 'Are you sure you want to restore the configuration? This will overwrite styles and Telegram.'); ?>');">
                                        ♻️ <?= t('backup_restore_button', 'Restore backup'); ?>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-body">
                                <h5 class="card-title">🔒 <?= t('custom_tailscale_vpn', 'Tailscale VPN Connection'); ?></h5>

                                <?php if (!empty($auth_output)): ?>
                                    <div class="alert alert-info">
                                        <strong><?= t('result', 'Result'); ?>:</strong>
                                        <pre class="mb-0"><?= htmlspecialchars($auth_output) ?></pre>
                                    </div>
                                <?php endif; ?>

                                <form method="POST" class="mb-3">
                                    <label class="form-label">🔑 <?= t('custom_tailscale_authkey', 'Tailscale AuthKey'); ?></label>
                                    <input type="text" name="authkey" class="form-control mb-2" placeholder="tskey-..." required>
                                    <button class="btn btn-primary">🔐 <?= t('custom_connect_vpn', 'Connect VPN'); ?></button>
                                </form>

                                <form method="POST" class="mb-3">
                                    <input type="hidden" name="vpn_disconnect" value="1">
                                    <button class="btn btn-danger mt-2">🔓 <?= t('custom_disconnect_vpn', 'Disconnect VPN'); ?></button>
                                </form>

                                <form method="POST" class="mb-3">
                                    <button type="button" class="btn btn-outline-info mt-0" data-bs-toggle="modal" data-bs-target="#modalAyudaVPN">
                                        📘 <?= t('custom_vpn_tutorial', 'VPN Tutorial'); ?>
                                    </button>
                                </form>

                                <ul class="list-group">
                                    <li class="list-group-item">📛 Hostname: <strong><?= htmlspecialchars($hostname) ?></strong></li>
                                    <li class="list-group-item">🌐 IP Tailscale: <strong><?= htmlspecialchars($ip ?: t('not_connected', 'Not connected')) ?></strong></li>
                                    <li class="list-group-item">📶 <?= t('status', 'Status'); ?>: <strong><?= $status ?></strong></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <form method="POST">
                            <input type="hidden" name="guardar_ip" value="1">
                            <h5>🌐 <?= t('custom_manual_ip_config', 'Manual IP Configuration'); ?></h5>

                            <div class="form-group mb-2">
                                <label><?= t('custom_interface', 'Interface'); ?></label>
                                <select name="interfaz" class="form-control">
                                    <?php foreach ($interfaces_disponibles as $iface => $ip_actual): ?>
                                        <option value="<?= htmlspecialchars($iface) ?>">
                                            <?= htmlspecialchars($iface) ?> (<?= t('current', 'current'); ?>: <?= htmlspecialchars($ip_actual) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <input type="text" name="ip" placeholder="IP (192.168.x.x)" class="form-control mb-2" required>
                            <input type="text" name="gateway" placeholder="Gateway" class="form-control mb-2" required>
                            <input type="text" name="dns" placeholder="DNS" class="form-control mb-2" required>

                            <button type="submit" class="btn btn-primary mt-1">🌐 <?= t('custom_save_static_ip', 'Save Static IP'); ?></button>
                        </form>

                        <form method="POST" class="mt-3">
                            <button type="submit" name="reboot" class="btn btn-danger" onclick="return confirm('<?= t('custom_confirm_reboot', 'Are you sure you want to reboot the Raspberry Pi?'); ?>')">
                                🔁 <?= t('custom_reboot_raspberry', 'Reboot Raspberry'); ?>
                            </button>
                        </form>

                        <form method="POST" class="mt-4">
                            <input type="hidden" name="guardar_wifi" value="1">
                            <h5>📶 <?= t('custom_connect_wifi', 'Connect WiFi'); ?></h5>

                            <div class="form-group mb-1">
                                <label><?= t('custom_wifi_network', 'WiFi Network (SSID)'); ?></label>
                                <select name="ssid" class="form-control">
                                    <?php foreach ($redes_disponibles as $ssid): ?>
                                        <option value="<?= htmlspecialchars($ssid) ?>"><?= htmlspecialchars($ssid) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label><?= t('password', 'Password'); ?></label>
                                <input type="password" name="wifi_password" class="form-control" placeholder="<?= t('custom_wifi_password_placeholder', 'WiFi network password'); ?>" required>
                            </div>

                            <button type="submit" class="btn btn-info">📶 <?= t('custom_connect_wifi', 'Connect WiFi'); ?></button>
                        </form>

                        <form method="POST" class="mt-4">
                            <input type="hidden" name="guardar_telegram" value="1">
                            <h5>📱 <?= t('custom_telegram_settings', 'Telegram Settings'); ?></h5>

                            <div class="form-group mb-2">
                                <label>Bot Token</label>
                                <input type="text" name="token" class="form-control" value="<?= htmlspecialchars($token_actual) ?>" required>
                            </div>

                            <div class="form-group mb-2">
                                <label>Chat ID</label>
                                <input type="text" name="chat_id" class="form-control" value="<?= htmlspecialchars($chat_id_actual) ?>" required>
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 15px;">
                                <button type="submit" class="btn btn-info" style="flex:1;">💬 <?= t('custom_save_settings', 'Save Settings'); ?></button>
                                <a href="includes/test-telegram.php" class="btn btn-success" style="flex:1; text-align:center;">📩 <?= t('custom_test_telegram', 'Test Telegram'); ?></a>
                            </div>
                        </form>

                        <form method="POST" class="my-4">
                            <input type="hidden" name="change_password" value="1">
                            <h5>🔑 <?= t('custom_change_access_password', 'Change Secure Access Password'); ?></h5>

                            <div class="form-group">
                                <input type="password" name="password_actual" class="form-control mb-2" placeholder="<?= t('custom_current_password', 'Current password'); ?>" required>
                                <input type="password" name="password_nueva" class="form-control mb-2" placeholder="<?= t('custom_new_password', 'New password'); ?>" required>
                                <button type="submit" class="btn btn-danger">🔐 <?= t('custom_change_password', 'Change password'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAyudaVPN" tabindex="-1" aria-labelledby="modalAyudaVPNLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAyudaVPNLabel">🚀 <?= t('custom_vpn_tutorial_title', 'Tutorial: Enable VPN in AuroxLink'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close', 'Close'); ?>"></button>
                </div>
                <div class="modal-body">
                    <ol>
                        <li><?= t('custom_vpn_step_1', 'Go to tailscale.com and create an account.'); ?></li>
                        <li><?= t('custom_vpn_step_2', 'It will ask you to add a new device, skip that step.'); ?></li>
                        <li><?= t('custom_vpn_step_3', 'Go to AuthKeys and generate your tskey-... key.'); ?></li>
                        <li><?= t('custom_vpn_step_4', 'Paste that key into AuroxLink and click Connect VPN.'); ?></li>
                        <li><?= t('custom_vpn_step_5', 'Refresh the page and enter again.'); ?></li>
                        <li><?= t('custom_vpn_step_6', 'The status should say ONLINE.'); ?></li>
                        <li><?= t('custom_vpn_step_7', 'If everything is fine, you can now go to your devices.'); ?></li>
                        <li><?= t('custom_vpn_step_8', 'Install Tailscale on your phone or PC.'); ?></li>
                        <li><?= t('custom_vpn_step_9', 'From another device, open http://100.x.x.x.'); ?></li>
                    </ol>
                    <p><strong><?= t('ready', 'Done'); ?>!</strong> <?= t('custom_vpn_ready_text', 'You now have remote access to your node.'); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('close', 'Close'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
