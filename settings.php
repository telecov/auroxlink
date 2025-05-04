<?php
    require 'includes/environment.php';

    session_start();

    if (!isset($_SESSION['autenticado'])) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clave']) && md5($_POST['clave']) === $clave_acceso) {
            $_SESSION['autenticado'] = true;
            header("Location: settings.php");
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
    // CONFIGURACION ECHOLINK
    // Ruta real del archivo de configuración
    $archivo_echolink = "/etc/svxlink/svxlink.d/ModuleEchoLink.conf";
    $parametros_echolink = [
        "CALLSIGN", "PASSWORD", "SYSOPNAME", "LOCATION", "DEFAULT_LANG",
        "MAX_QSOS", "MAX_CONNECTIONS", "LINK_IDLE_TIMEOUT", "AUTOCON_ECHOLINK_ID"
    ];
    $valores_echolink = [];

    if (file_exists($archivo_echolink)) {
        $lineas_e = file($archivo_echolink);
        foreach ($parametros_echolink as $clave_e) {
            foreach ($lineas_e as $linea_e) {
                if (preg_match("/^\s*$clave_e\s*=\s*(.*)/i", $linea_e, $match)) {
                    $valores_echolink[$clave_e] = trim($match[1]);
                    break;
                }
            }
        }
    }

    // CONFIGURACION SVXLINK
    $archivo_svxlink = "/etc/svxlink/svxlink.conf";
    $parametros_svxlink = [
        "[SimplexLogic]" => ["CALLSIGN"],
        "[Rx1]" => ["AUDIO_DEV", "SQL_DET", "SQL_START_DELAY", "SQL_DELAY", "SQL_HANGTIME", "SERIAL_PORT", "SERIAL_PIN"],
        "[Tx1]" => ["AUDIO_DEV", "PTT_TYPE", "PTT_PORT", "PTT_PIN"],
        "[LocationInfo]" => []
    ];
    $valores_svxlink = [];
    $bloque_actual = null;

    if (file_exists($archivo_svxlink)) {
        $lineas_s = file($archivo_svxlink);
        foreach ($lineas_s as $linea_s) {
        $linea_s = trim($linea_s);
            if (preg_match("/^\[(.*)\]/", $linea_s, $match)) {
                $bloque_actual = "[" . $match[1] . "]";
            } elseif ($bloque_actual && isset($parametros_svxlink[$bloque_actual])) {
                foreach ($parametros_svxlink[$bloque_actual] as $clave_s) {
                    if (stripos($linea_s, $clave_s . "=") === 0) {
                        $valores_svxlink[$bloque_actual][$clave_s] = trim(explode("=", $linea_s, 2)[1]);
                    }
                }
            }
        }
    }

    // CONFIGURACION DE AUDIO
    // Funciones auxiliares
    function obtenerTarjetas() {
        $salida = shell_exec('aplay -l 2>/dev/null');
        $tarjetas = [];
        if ($salida) {
            preg_match_all('/card (\d+): ([^\[]+)\[([^\]]+)\]/', $salida, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $tarjetas[] = [
                    'numero' => $m[1],
                    'nombre' => trim($m[2]),
                    'descripcion' => trim($m[3])
                ];
            }
        }
        return $tarjetas;
    }

    function obtenerControles($card) {
        $salida = shell_exec("sudo amixer -c {$card} scontrols 2>/dev/null");
        $controles = [];
        if ($salida) {
            preg_match_all("/Simple mixer control '([^']+)'/", $salida, $matches);
            $controles = $matches[1] ?? [];
        }
        return $controles;
    }

    function obtenerEstadoControl($card, $control) {
        $salida = shell_exec("sudo amixer -c {$card} get '{$control}' 2>/dev/null");

        if (stripos($control, 'AGC') !== false || stripos($control, 'Auto Gain') !== false) {
            // Si el nombre del control contiene "AGC" o "Auto Gain", forzar a manejar como ON/OFF
            if (strpos($salida, '[on]') !== false) {
                return 'on';
            } elseif (strpos($salida, '[off]') !== false) {
                return 'off';
            } else {
                // fallback si no encuentra [on]/[off]
                return null;
            }
        }

        if (strpos($salida, 'Playback') !== false || strpos($salida, 'Capture') !== false) {
            preg_match_all('/\[(\d+)%\]/', $salida, $matches);
            return $matches[1];
        } elseif (strpos($salida, 'on]') !== false || strpos($salida, 'off]') !== false) {
            return (strpos($salida, '[on]') !== false) ? 'on' : 'off';
        }

        return null;
    }

    // Variables de trabajo
    $tarjetas = obtenerTarjetas();
    $tarjeta_seleccionada = isset($_POST['tarjeta']) ? intval($_POST['tarjeta']) : 2;
    $controles = obtenerControles($tarjeta_seleccionada);


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aplicar'])) {

        foreach ($controles as $control) {
                $campo = str_replace(' ', '_', $control);
            if (isset($_POST[$campo])) {
                $valor = $_POST[$campo];
                
                if (in_array($valor, ['on', 'off'])) {
                    // Para controles que son ON/OFF como Auto Gain Control
                    $comando = "sudo amixer -c {$tarjeta_seleccionada} sset '{$control}' {$valor}";
                } else {
                    // Para sliders de volumen
                    $comando = "sudo amixer -c {$tarjeta_seleccionada} sset '{$control}' {$valor}%";
                }

                // Ejecuta y muestra el comando ejecutado para debug
                shell_exec($comando);
            }
        }
        sleep(1);
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
    <title><?php echo $titleSite; ?> - Configuración</title>
</head>
<body>
    <div class="container-fluid bg-body-content">
        <div class="row">
            <?php require 'includes/sidebar-menu.php'; ?>

            <!-- Contenido principal -->
            <div class="col-12 col-md-10 p-3">
                <!-- Contenido -->
                 <div class="row">
                    <!-- CONFIGURACION ECHOLINK -->
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">
                                ☰ 
                            </button>
                            <h2 class="fs-4 pb-2 titulo m-0">🛠️ Configuración Módulo EchoLink</h2>
                        </div>
                        <form method="post" action="includes/save-config.php">
                            <?php foreach ($parametros_echolink as $clave_e): ?>
                            <div class="form-group my-3">
                                <label for="<?php echo $clave_e; ?>"><?php echo $clave_e; ?></label>
                                <?php if ($clave_e === "AUTOCON_ECHOLINK_ID"): ?>
                                <input type="text" class="form-control" name="<?php echo $clave_e; ?>" id="<?php echo $clave_e; ?>" value="<?php echo htmlspecialchars($valores_echolink[$clave_e] ?? ''); ?>" placeholder="ID nodo o 0 para deshabilitar">
                                <?php else: ?>
                                <input type="text" class="form-control" name="<?php echo $clave_e; ?>" id="<?php echo $clave_e; ?>" value="<?php echo htmlspecialchars($valores_echolink[$clave_e] ?? ''); ?>">
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary mb-3">Guardar cambios</button>
                        </form>

                        <!-- CONFIGURACION DE AUDIO -->
                        <h2 class="fs-4 pb-2 titulo mt-4">🎛️ Control de Audio <?php echo $titleSite; ?></h2>
                        <div class="card p-4 mb-4">
                            <form method="post">
                                <div class="mb-3">
                                    <label for="tarjeta" class="form-label">Seleccionar Tarjeta de Audio:</label>
                                    <select class="form-select" name="tarjeta" id="tarjeta" onchange="this.form.submit()">
                                        <?php foreach ($tarjetas as $t): ?>
                                            <option value="<?= htmlspecialchars($t['numero']) ?>" <?= ($t['numero'] == $tarjeta_seleccionada) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars("Card {$t['numero']}: {$t['nombre']} [{$t['descripcion']}]") ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php foreach ($controles as $control):
                                    $estado = obtenerEstadoControl($tarjeta_seleccionada, $control);
                                ?>
                                    <div class="card p-3 mb-3">
                                        <label for="<?= htmlspecialchars($control) ?>" class="form-label">Control: <?= htmlspecialchars($control) ?></label>
                                        <?php if (is_array($estado)): ?>
                                            <input type="range" class="form-range" id="<?= htmlspecialchars($control) ?>" name="<?= htmlspecialchars(str_replace(' ', '_', $control)) ?>" min="0" max="100" value="<?= htmlspecialchars($estado[0]) ?>">
                                            <div>Valor actual: <?= htmlspecialchars($estado[0]) ?>%</div>
                                        <?php elseif (in_array($estado, ['on', 'off'])): ?>
                                            <select class="form-select" name="<?= htmlspecialchars(str_replace(' ', '_', $control)) ?>">
                                                <option value="on" <?= ($estado === 'on') ? 'selected' : '' ?>>Activado</option>
                                                <option value="off" <?= ($estado === 'off') ? 'selected' : '' ?>>Desactivado</option>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <button type="submit" name="aplicar" class="btn btn-success w-100">Aplicar Cambios</button>
                            </form>
                        </div>
                    </div>

                    <!-- CONFIGURACION SVXLINK -->
                    <div class="col-md-6">
                        <h2 class="fs-4 pb-2 titulo">⚙️ Configuración de svxlink.conf</h2>
                        <form method="post" action="includes/save-svx.php">
                            <?php foreach ($parametros_svxlink as $seccion => $claves_s): ?>
                                <?php if (!empty($claves_s)): ?>
                                <h5 class="mt-4"><?php echo $seccion; ?></h5>
                                    <?php foreach ($claves_s as $clave_s): ?>
                                        <div class="form-group mb-3">
                                            <label for="<?php echo $seccion . '_' . $clave_s; ?>"><?php echo $clave_s; ?></label>
                                            <input type="text" class="form-control" name="<?php echo $clave_s; ?>" id="<?php echo $clave_s; ?>" value="<?php echo htmlspecialchars($valores_svxlink[$seccion][$clave_s] ?? ''); ?>">
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary mb-3">Guardar cambios</button>
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
