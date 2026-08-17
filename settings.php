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
        header("Location: settings.php");
        exit;
    }

    echo '<!DOCTYPE html>
    <html lang="' . htmlspecialchars($idioma) . '">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . t('settings_secure_access', 'Secure Access') . '</title>
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

/* =========================================================
   CONFIGURACION ECHOLINK
========================================================= */
$archivo_echolink = "/etc/svxlink/svxlink.d/ModuleEchoLink.conf";
$parametros_echolink = [
    "CALLSIGN",
    "PASSWORD",
    "SYSOPNAME",
    "LOCATION",
    "REJECT_INCOMING",
    "DEFAULT_LANG",
    "PROXY_SERVER",
    "PROXY_PORT",
    "PROXY_PASSWORD",
    "MAX_QSOS",
    "MAX_CONNECTIONS",
    "LINK_IDLE_TIMEOUT",
    "AUTOCON_ECHOLINK_ID"
];
$valores_echolink = [];

if (file_exists($archivo_echolink)) {
    $lineas_e = file($archivo_echolink);
    foreach ($parametros_echolink as $clave_e) {
        foreach ($lineas_e as $linea_e) {
            if (preg_match("/^\s*(#?)\s*{$clave_e}\s*=\s*(.*)/i", $linea_e, $match)) {
                $valores_echolink[$clave_e] = trim($match[2]);
                $valores_echolink["_enabled_{$clave_e}"] = ($match[1] !== '#');
                break;
            }
        }
    }
}

/* =========================================================
   CONFIGURACION SVXLINK
========================================================= */
$archivo_svxlink = "/etc/svxlink/svxlink.conf";
$parametros_svxlink = [
    "[GLOBAL]" => ["LOCATION_INFO"],
    "[SimplexLogic]" => ["CALLSIGN"],
    "[Rx1]" => ["AUDIO_DEV", "SQL_DET", "SQL_START_DELAY", "SQL_DELAY", "PREAMP", "SQL_HANGTIME", "SERIAL_PORT", "SERIAL_PIN"],
    "[Tx1]" => ["AUDIO_DEV", "PTT_TYPE", "PTT_PORT", "PTT_PIN"],
    "[LocationInfo]" => [
        "CALLSIGN",
        "APRS_SERVER_LIST",
        "LON_POSITION",
        "LAT_POSITION",
        "FREQUENCY",
        "TX_POWER",
        "ANTENNA_GAIN",
        "ANTENNA_HEIGHT",
        "PATH",
        "BEACON_INTERVAL",
        "COMMENT"
    ]
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
                if (preg_match("/^\s*(#?)\s*{$clave_s}\s*=\s*(.*)/i", $linea_s, $match)) {
                    $valores_svxlink[$bloque_actual][$clave_s] = trim($match[2]);
                    $valores_svxlink["_enabled_{$bloque_actual}_{$clave_s}"] = ($match[1] !== '#');
                }
            }
        }
    }
}

/* =========================================================
   CONFIGURACION DE AUDIO
========================================================= */
function obtenerTarjetas()
{
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

function obtenerControles($card)
{
    $salida = shell_exec("sudo amixer -c {$card} scontrols 2>/dev/null");
    $controles = [];
    if ($salida) {
        preg_match_all("/Simple mixer control '([^']+)'/", $salida, $matches);
        $controles = $matches[1] ?? [];
    }
    return $controles;
}

function obtenerEstadoControl($card, $control)
{
    $salida = shell_exec("sudo amixer -c {$card} get '{$control}' 2>/dev/null");

    if (stripos($control, 'AGC') !== false || stripos($control, 'Auto Gain') !== false) {
        if (strpos($salida, '[on]') !== false) {
            return 'on';
        } elseif (strpos($salida, '[off]') !== false) {
            return 'off';
        } else {
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

$tarjetas = obtenerTarjetas();
$tarjeta_seleccionada = isset($_POST['tarjeta']) ? intval($_POST['tarjeta']) : 2;
$controles = obtenerControles($tarjeta_seleccionada);

$audio_guardado_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aplicar'])) {
    foreach ($controles as $control) {
        $campo = str_replace(' ', '_', $control);
        if (isset($_POST[$campo])) {
            $valor = $_POST[$campo];

            if (in_array($valor, ['on', 'off'])) {
                $comando = "sudo amixer -c {$tarjeta_seleccionada} sset '{$control}' {$valor}";
            } else {
                $comando = "sudo amixer -c {$tarjeta_seleccionada} sset '{$control}' {$valor}%";
            }

            shell_exec($comando);
        }
    }

    sleep(1);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_audio'])) {
    shell_exec("sudo alsactl store 2>&1");
    $audio_guardado_msg = t('settings_audio_saved', 'Audio settings saved successfully.');
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($idioma); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <title><?php echo $titleSite; ?> - <?= t('menu_settings', 'Settings'); ?></title>
</head>

<body>
    <div class="container-fluid bg-body-content">
        <div class="row">
            <?php require 'includes/sidebar-menu.php'; ?>

            <div class="col-12 col-md-10 p-3">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas"
                            data-bs-target="#mobileMenu" aria-controls="mobileMenu">
                            ☰
                        </button>
                        <h2 class="fs-4 titulo m-0">⚙️ <?= t('menu_settings', 'Settings'); ?></h2>
                    </div>
                    <a href="index.php" class="btn btn-outline-secondary">⬅️ <?= t('settings_back_dashboard', 'Back to Dashboard'); ?></a>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="d-flex align-items-center justify-content-between mt-4 mb-3 w-100">
                                <h2 class="fs-4 titulo m-2">🛠️ <?= t('settings_echolink_module', 'EchoLink Module Settings'); ?></h2>
                                <a href="help.php" target="_blank" class="btn btn-sm text-white px-3 shadow-sm"
                                    style="background-color: #00b4d8;">
                                    <?= t('settings_view_help', 'View Help'); ?>
                                </a>
                            </div>
                        </div>

                        <form method="post" action="includes/save-config.php">
                            <?php foreach ($parametros_echolink as $clave_e): ?>
                                <?php
                                    $esPassword = in_array($clave_e, ['PASSWORD', 'PROXY_PASSWORD']);
                                ?>
                                <div class="form-group my-3">
                                    <label for="<?= $clave_e; ?>"><?= $clave_e; ?></label>

                                    <?php if ($clave_e === "AUTOCON_ECHOLINK_ID"): ?>
                                        <input type="text" class="form-control" name="<?= $clave_e ?>" id="<?= $clave_e ?>"
                                            value="<?= htmlspecialchars($valores_echolink[$clave_e] ?? '') ?>"
                                            placeholder="<?= t('settings_node_id_placeholder', 'Node ID or 0 to disable'); ?>">
                                        <small class="form-text text-muted mt-1">
                                            🔗 <a href="https://www.echolink.org/logins.jsp" target="_blank"><?= t('settings_where_get_id', 'Where do I get this ID?'); ?></a>
                                        </small>

                                    <?php elseif ($esPassword): ?>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="<?= $clave_e; ?>" id="<?= $clave_e; ?>"
                                                value="" placeholder="••••••••">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('<?= $clave_e; ?>')">
                                                👁️
                                            </button>
                                        </div>
                                        <small class="form-text text-muted mt-1">
                                            <?= t('settings_leave_blank_keep_password', 'Leave blank to keep current password'); ?>
                                        </small>

                                    <?php else: ?>
                                        <input type="text" class="form-control" name="<?= $clave_e; ?>" id="<?= $clave_e; ?>"
                                            value="<?= htmlspecialchars($valores_echolink[$clave_e] ?? ''); ?>">
                                    <?php endif; ?>

                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="enable_<?= $clave_e; ?>"
                                            id="enable_<?= $clave_e; ?>" <?= ($valores_echolink["_enabled_{$clave_e}"] ?? true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="enable_<?= $clave_e; ?>"><?= t('active', 'Active'); ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" class="btn btn-primary mb-3"><?= t('save_changes', 'Save changes'); ?></button>
                        </form>

                        <h2 class="fs-4 pb-2 titulo mt-4">🎛️ <?= t('settings_audio_control', 'Audio Control'); ?> <?php echo $titleSite; ?></h2>
                        <div class="card p-4 mb-4">
                            <?php if ($audio_guardado_msg): ?>
                                <div class="alert alert-info mt-2"><?= htmlspecialchars($audio_guardado_msg) ?></div>
                            <?php endif; ?>

                            <form method="post">
                                <div class="mb-3">
                                    <label for="tarjeta" class="form-label"><?= t('settings_select_sound_card', 'Select Audio Card'); ?>:</label>
                                    <select class="form-select" name="tarjeta" id="tarjeta"
                                        onchange="this.form.submit()">
                                        <?php foreach ($tarjetas as $t): ?>
                                            <option value="<?= htmlspecialchars($t['numero']) ?>"
                                                <?= ($t['numero'] == $tarjeta_seleccionada) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars("Card {$t['numero']}: {$t['nombre']} [{$t['descripcion']}]") ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <?php foreach ($controles as $control):
                                    $estado = obtenerEstadoControl($tarjeta_seleccionada, $control);
                                ?>
                                    <div class="card p-3 mb-3">
                                        <label for="<?= htmlspecialchars($control) ?>" class="form-label"><?= t('settings_control', 'Control'); ?>:
                                            <?= htmlspecialchars($control) ?></label>
                                        <?php if (is_array($estado)): ?>
                                            <input type="range" class="form-range" id="<?= htmlspecialchars($control) ?>"
                                                name="<?= htmlspecialchars(str_replace(' ', '_', $control)) ?>" min="0"
                                                max="100" value="<?= htmlspecialchars($estado[0]) ?>">
                                            <div><?= t('settings_current_value', 'Current value'); ?>: <?= htmlspecialchars($estado[0]) ?>%</div>
                                        <?php elseif (in_array($estado, ['on', 'off'])): ?>
                                            <select class="form-select"
                                                name="<?= htmlspecialchars(str_replace(' ', '_', $control)) ?>">
                                                <option value="on" <?= ($estado === 'on') ? 'selected' : '' ?>><?= t('enabled', 'Enabled'); ?></option>
                                                <option value="off" <?= ($estado === 'off') ? 'selected' : '' ?>><?= t('disabled', 'Disabled'); ?></option>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>

                                <button type="submit" name="aplicar" class="btn btn-success w-100"><?= t('settings_apply_changes', 'Apply Changes'); ?></button>
                                <button type="submit" name="guardar_audio" class="btn btn-secondary w-100 mt-2">💾
                                    <?= t('settings_save_audio_config', 'Save audio settings'); ?></button>
                            </form>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h2 class="fs-4 pb-2 titulo">⚙️ <?= t('settings_svxlink_conf', 'svxlink.conf Settings'); ?></h2>
                        <form method="post" action="includes/save-svx.php">
                            <?php foreach ($parametros_svxlink as $seccion => $claves_s): ?>
                                <?php if (!empty($claves_s)): ?>
                                    <h5 class="mt-4"><?= $seccion; ?></h5>
                                    <?php foreach ($claves_s as $clave_s): ?>
                                        <?php $nombreCampo = str_replace(['[', ']'], '', $seccion) . "_" . $clave_s; ?>
                                        <div class="form-group mb-3">
                                            <label for="<?= $nombreCampo ?>"><?= $clave_s; ?></label>
                                            <input type="text" class="form-control" name="<?= $nombreCampo ?>"
                                                id="<?= $nombreCampo ?>"
                                                value="<?= htmlspecialchars($valores_svxlink[$seccion][$clave_s] ?? '') ?>">
                                            <div class="form-check form-switch mt-1">
                                                <input class="form-check-input" type="checkbox" name="enable_<?= $nombreCampo ?>"
                                                    id="enable_<?= $nombreCampo ?>"
                                                    <?= ($valores_svxlink["_enabled_{$seccion}_{$clave_s}"] ?? true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="enable_<?= $nombreCampo ?>"><?= t('active', 'Active'); ?></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <button type="submit" class="btn btn-primary mb-3"><?= t('save_changes', 'Save changes'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(id) {
            const input = document.getElementById(id);
            input.type = (input.type === 'password') ? 'text' : 'password';
        }
    </script>
</body>
</html>
