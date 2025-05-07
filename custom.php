<?php
    $env_path = __DIR__ . '/includes/environment.php';

    session_start();

    if (file_exists($env_path)) {
        include $env_path;
    } else {
        die('Archivo de configuración no encontrado.');
    }

    if (!isset($_SESSION['autenticado'])) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clave']) && md5($_POST['clave']) === $clave_acceso) {
            $_SESSION['autenticado'] = true;
            header("Location: custom.php");
            exit;
        }
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Acceso Configuración</title>
            <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"></head>
            <body class="bg-light"><div class="container mt-5">
            <div class="row justify-content-center"><div class="col-md-4">
            <div class="card p-4"><h4>🔐 Ingreso seguro</h4>
            <form method="post"><input type="password" name="clave" class="form-control mb-3" placeholder="Contraseña" required>
            <button type="submit" class="btn btn-primary btn-block">Entrar</button></form></div></div></div></div></body></html>';
        exit;
    }

    require $env_path;
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', 0);
    
    $guardado_ok = false;
    $guardado_ip = false;
    $guardado_wifi = false;
    $error_red = '';
    
    function validarIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP);
    }
    
    function obtenerIPActual($interfaz = 'eth0') {
        $salida = shell_exec("ip -4 addr show $interfaz | grep -oP '(?<=inet\s)\d+(\.\d+){3}'");
        return trim($salida);
    }
    
    function escanearRedesWifi() {
        $salida = shell_exec("sudo /sbin/iwlist wlan0 scan 2>/dev/null");
        preg_match_all('/ESSID:\"([^\"]+)\"/', $salida, $coincidencias);
        return $coincidencias[1] ?? [];
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['guardar_estilo'])) {
            $nuevo_estilo = [
                'nombre_zona' => $_POST['nombre_zona'] ?? '',
                'radioaficionado' => $_POST['radioaficionado'] ?? '',
                'frecuencia' => $_POST['frecuencia'] ?? '',
                'titulo_dashboard' => $_POST['titulo_dashboard'] ?? '',
                'indicativo' => $_POST['indicativo'] ?? '',
                'ciudad' => $_POST['ciudad'] ?? '',
                'utc_offset' => $_POST['utc_offset'] ?? '',
                'color_sidebar' => $_POST['color_sidebar'] ?? '',
                'color_fondo' => $_POST['color_fondo'] ?? '',
                'color_titulo' => $_POST['color_titulo'] ?? '',
                'logo' => $_POST['logo_actual'] ?? 'auroxlink_banner.png'
            ];
    
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
                $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $nombre_archivo = 'auroxlink_banner.' . $ext;
                move_uploaded_file($_FILES['logo']['tmp_name'], $nombre_archivo);
                $nuevo_estilo['logo'] = $nombre_archivo;
            }
    
            file_put_contents('estilos.json', json_encode($nuevo_estilo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $guardado_ok = true;
        }
    
        if (isset($_POST['guardar_ip'])) {
            $interfaz = $_POST['interfaz'] ?? '';
            $ip = $_POST['ip'] ?? '';
            $gateway = $_POST['gateway'] ?? '';
            $dns = $_POST['dns'] ?? '';
    
    if ($interfaz && validarIP($ip) && validarIP($gateway) && validarIP($dns)) {
        $nombre_conexion = trim(shell_exec("nmcli -g GENERAL.CONNECTION device show $interfaz"));
    
        if ($nombre_conexion && $nombre_conexion !== "--") {
            $commands = [
                "sudo nmcli connection modify \"$nombre_conexion\" ipv4.method manual",
                "sudo nmcli connection modify \"$nombre_conexion\" ipv4.ignore-auto-dns yes",
                "sudo nmcli connection modify \"$nombre_conexion\" ipv4.ignore-auto-routes yes",
                "sudo nmcli connection modify \"$nombre_conexion\" ipv4.addresses $ip/24",
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
            $error_red = '❌ No se pudo detectar la conexión activa.';
        }
    } else {
        $error_red = '❌ Dirección IP, Gateway o DNS inválido.';
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
                    'token' => $token,
                    'chat_id' => $chat_id
                ];
                file_put_contents(__DIR__.'/telegram_config.json', json_encode($telegram_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    
        if (isset($_POST['reboot'])) {
            shell_exec('sudo reboot');
            exit;
        }
    
        if (isset($_POST['change_password'])) {
            $password_actual = $_POST['password_actual'];
            $password_nueva = $_POST['password_nueva'];
    
            if (md5($password_actual) === $clave_acceso) {
                $clave_nueva_hash = md5($password_nueva);
    
                $contenido = file_get_contents($env_path);
                $contenido_nuevo = preg_replace(
                    "/\\\$clave_acceso\s*=\s*'[^']*';/",
                    "\$clave_acceso = '" . $clave_nueva_hash . "';",
                    $contenido
                );
    
                if (file_put_contents($env_path, $contenido_nuevo)) {
                    $message = '✅ Contraseña actualizada correctamente.';
                } else {
                    $message = '⚠️ No se pudo escribir en el archivo <code>includes/environment.php</code>.';
                }
            } else {
                $message = '❌ Contraseña actual incorrecta.';
            }
        }
    }
    
    $ip_eth0 = obtenerIPActual('eth0');
    $ip_wlan0 = obtenerIPActual('wlan0');
    $redes_disponibles = escanearRedesWifi();
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
                    <!-- Título -->
                    <h2 class="fs-4 titulo m-0">🎨 Personalización del Sistema</h2>
                </div>
                <!-- Contenido -->

                <!-- ALERTAS -->
                <?php if ($guardado_ok): ?><div class="alert alert-success mt-2">✅ Personalización guardada correctamente.</div><?php endif; ?>
                <?php if ($guardado_ip): ?><div class="alert alert-info mt-2">✅ IP configurada y aplicada correctamente.</div><?php endif; ?>
                <?php if ($guardado_wifi): ?><div class="alert alert-info mt-2">📶 WiFi conectado exitosamente.</div><?php endif; ?>
                <?php if ($error_red): ?><div class="alert alert-danger mt-2"><?= $error_red ?></div><?php endif; ?>
                <?php if (isset($message)): ?><div class="alert alert-warning mt-2"><?= $message ?></div><?php endif; ?>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <!-- FORMULARIO DE PERSONALIZACIÓN VISUAL -->
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="guardar_estilo" value="1">
                            <div class="form-group mb-3">
                                <label>Nombre del Dashboard</label>
                                <input type="text" name="titulo_dashboard" class="form-control" value="<?= htmlspecialchars($style['titulo_dashboard'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label>Nombre del Radioaficionado</label>
                                <input type="text" name="radioaficionado" class="form-control" value="<?= htmlspecialchars($style['radioaficionado'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label>Frecuencia de tu Nodo</label>
                                <input type="text" name="frecuencia" class="form-control" value="<?= htmlspecialchars($style['frecuencia'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label>Nombre de Zona</label>
                                <input type="text" name="nombre_zona" class="form-control" value="<?= htmlspecialchars($style['nombre_zona'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label>Indicativo</label>
                                <input type="text" name="indicativo" class="form-control" value="<?= htmlspecialchars($style['indicativo'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label>Ciudad</label>
                                <input type="text" name="ciudad" class="form-control" value="<?= htmlspecialchars($style['ciudad'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label>UTC Offset</label>
                                <input type="text" name="utc_offset" class="form-control" value="<?= htmlspecialchars($style['utc_offset'] ?? '') ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label>Logo (opcional)</label>
                                <input type="hidden" name="logo_actual" value="<?= htmlspecialchars($style['imagen_logo'] ?? 'auroralink_banner.png') ?>">
                                <input type="file" name="logo" class="form-control-file">
                            </div>

                            <div class="form-group mb-3">
                                <label>Color Fondo</label>
                                <input type="color" name="color_fondo" class="form-control" value="<?= $colorFondo ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label>Color Sidebar</label>
                                <input type="color" name="color_sidebar" class="form-control" value="<?= $colorSidebar ?>">
                            </div>

                            <div class="form-group mb-3">
                                <label>Color Título</label>
                                <input type="color" name="color_titulo" class="form-control" value="<?= $colorTitulo ?>">
                            </div>

                            <button type="submit" class="btn btn-success btn-bloc mb-3">💾 Guardar Personalización</button>
                        </form>

                    </div>
                    <div class="col-md-6">
                        <!-- FORMULARIO DE IP -->
                        <form method="POST">
                            <input type="hidden" name="guardar_ip" value="1">
                            <h5>🌐 Configuración IP Manual</h5>

                            <div class="form-group mb-2">
                                <label>Interfaz</label>
                                <select name="interfaz" class="form-control">
                                <option value="eth0">eth0 (actual: <?= $ip_eth0 ?>)</option>
                                <option value="wlan0">wlan0 (actual: <?= $ip_wlan0 ?>)</option>
                                </select>
                            </div>

                            <input type="text" name="ip" placeholder="IP (192.168.x.x)" class="form-control mb-2" required>
                            <input type="text" name="gateway" placeholder="Gateway" class="form-control mb-2" required>
                            <input type="text" name="dns" placeholder="DNS" class="form-control mb-2" required>

                            <button type="submit" class="btn btn-primary btn-block mt-1">🌐 Guardar IP Estática</button>
                        </form>

                        <!-- BOTÓN DE REINICIO -->
                        <form method="POST" class="mt-3">
                            <button type="submit" name="reboot" class="btn btn-danger btn-block" onclick="return confirm('¿Seguro que deseas reiniciar la Raspberry Pi?')">🔁 Reiniciar Raspberry</button>
                        </form>
                            
                        <!-- FORMULARIO DE WIFI -->
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="guardar_wifi" value="1">
                            <h5>📶 Conectar WiFi</h5>

                            <div class="form-group mb-1">
                                <label>Red WiFi (SSID)</label>
                                <select name="ssid" class="form-control">
                                    <?php foreach ($redes_disponibles as $ssid): ?>
                                        <option value="<?= htmlspecialchars($ssid) ?>"><?= htmlspecialchars($ssid) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label>Contraseña</label>
                                <input type="password" name="wifi_password" class="form-control" placeholder="Clave de la red WiFi" required>
                            </div>

                            <button type="submit" class="btn btn-info btn-block">📶 Conectar WiFi</button>
                        </form>

                        <!-- FORMULARIO DE TELEGRAM -->
                        <form method="POST" class="mt-4">
                            <input type="hidden" name="guardar_telegram" value="1">
                            <h5>📱 Configuración de Telegram</h5>

                            <?php
                                $telegram_config = [];
                                if (file_exists(__DIR__.'/telegram_config.json')) {
                                    $telegram_config = json_decode(file_get_contents(__DIR__.'/telegram_config.json'), true) ?? [];
                                }
                                $token_actual = $telegram_config['token'] ?? '';
                                $chat_id_actual = $telegram_config['chat_id'] ?? '';
                            ?>

                            <div class="form-group mb-2">
                                <label>Bot Token</label>
                                <input type="text" name="token" class="form-control" value="<?= htmlspecialchars($token_actual) ?>" required>
                            </div>

                            <div class="form-group mb-2">
                                <label>Chat ID</label>
                                <input type="text" name="chat_id" class="form-control" value="<?= htmlspecialchars($chat_id_actual) ?>" required>
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 15px;">
                                <button type="submit" class="btn btn-info btn-block">💬 Guardar Configuración</button>
                                <a href="includes/test-telegram.php" class="btn btn-success" style="flex:1; text-align:center;">📨 Probar Telegram</a>
                            </div>
                        </form>

                        <!-- FORMULARIO DE CAMBIO CONTRASEÑA -->
                        <form method="POST" class="my-4">
                            <input type="hidden" name="change_password" value="1">
                            <h5>🔑 Cambiar Contraseña Dashboard</h5>

                            <div class="form-group">
                                <input type="password" name="password_actual" class="form-control mb-2" placeholder="Contraseña actual" required>
                                <input type="password" name="password_nueva" class="form-control mb-2" placeholder="Contraseña nueva" required>
                                <button type="submit" class="btn btn-block btn-danger">Cambiar contraseña</button>
                            </div>
                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Scripts de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
