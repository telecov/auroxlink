<?php
require 'includes/environment.php';
session_start();

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);
date_default_timezone_set('America/Santiago');

/* =========================================================
   CARGA DE IDIOMA
========================================================= */
$configFile = __DIR__ . '/estilos.json';
$config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];
$idioma = $config['idioma'] ?? 'es';
$langFile = __DIR__ . "/data/lang/{$idioma}.json";
$lang = [];
if (file_exists($langFile)) {
    $lang = json_decode(file_get_contents($langFile), true);
}
if (!is_array($lang)) {
    $lang = json_decode(file_get_contents(__DIR__ . '/data/lang/es.json'), true);
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
   LOGIN
========================================================= */
if (!isset($_SESSION['autenticado'])) {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_POST['clave']) &&
        md5($_POST['clave']) === $clave_acceso
    ) {
        $_SESSION['autenticado'] = true;
        header('Location: settings.php');
        exit;
    }

    echo '<!DOCTYPE html><html lang="' . htmlspecialchars($idioma) . '"><head>' .
        '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">' .
        '<title>' . t('settings_secure_access', 'Secure Access') . '</title>' .
        '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">' .
        '</head><body class="bg-light"><div class="container mt-5"><div class="row justify-content-center"><div class="col-md-4">' .
        '<div class="card p-4 shadow-sm"><h4>🔐 ' . t('settings_secure_login', 'Secure Login') . '</h4>' .
        '<form method="post"><input type="password" name="clave" class="form-control mb-3" placeholder="' . t('password', 'Password') . '" required>' .
        '<button type="submit" class="btn btn-primary btn-block mb-2">' . t('settings_enter', 'Enter') . '</button>' .
        '<a href="index.php" class="btn btn-outline-secondary btn-block">⬅️ ' . t('settings_back_dashboard', 'Back to Dashboard') . '</a>' .
        '</form></div></div></div></div></body></html>';
    exit;
}

/* =========================================================
   CONFIGURACION ECHOLINK
========================================================= */
$archivo_echolink = '/etc/svxlink/svxlink.d/ModuleEchoLink.conf';
$parametros_echolink = [
    'CALLSIGN','PASSWORD','SYSOPNAME','LOCATION','REJECT_INCOMING','DEFAULT_LANG',
    'PROXY_SERVER','PROXY_PORT','PROXY_PASSWORD','MAX_QSOS','MAX_CONNECTIONS',
    'LINK_IDLE_TIMEOUT','AUTOCON_ECHOLINK_ID'
];
$valores_echolink = [];
if (file_exists($archivo_echolink)) {
    $lineas_e = file($archivo_echolink);
    foreach ($parametros_echolink as $clave_e) {
        foreach ($lineas_e as $linea_e) {
            if (preg_match("/^\\s*(#?)\\s*{$clave_e}\\s*=\\s*(.*)/i", $linea_e, $match)) {
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
$archivo_svxlink = '/etc/svxlink/svxlink.conf';
$parametros_svxlink = [
    '[GLOBAL]' => ['LOCATION_INFO'],
    '[SimplexLogic]' => ['CALLSIGN'],
    '[Rx1]' => ['AUDIO_DEV','SQL_DET','SQL_START_DELAY','SQL_DELAY','PREAMP','SQL_HANGTIME','SERIAL_PORT','SERIAL_PIN'],
    '[Tx1]' => ['AUDIO_DEV','PTT_TYPE','PTT_PORT','PTT_PIN'],
    '[LocationInfo]' => ['CALLSIGN','APRS_SERVER_LIST','LON_POSITION','LAT_POSITION','FREQUENCY','TX_POWER','ANTENNA_GAIN','ANTENNA_HEIGHT','PATH','BEACON_INTERVAL','COMMENT']
];

$parametros_svxlink_lectura = $parametros_svxlink;
$parametros_svxlink_lectura['[SimplexLogic]'] = [
    'CALLSIGN','SHORT_IDENT_INTERVAL','LONG_IDENT_INTERVAL','SHORT_VOICE_ID_ENABLE','LONG_VOICE_ID_ENABLE',
    'SHORT_CW_ID_ENABLE','LONG_CW_ID_ENABLE','CW_AMP','CW_PITCH','CW_WPM'
];

$valores_svxlink = [];
$bloque_actual = null;
if (file_exists($archivo_svxlink)) {
    $lineas_s = file($archivo_svxlink);
    foreach ($lineas_s as $linea_s) {
        $linea_s = trim($linea_s);
        if (preg_match('/^\[(.*)\]/', $linea_s, $match)) {
            $bloque_actual = '[' . $match[1] . ']';
        } elseif ($bloque_actual && isset($parametros_svxlink_lectura[$bloque_actual])) {
            foreach ($parametros_svxlink_lectura[$bloque_actual] as $clave_s) {
                if (preg_match("/^\\s*(#?)\\s*{$clave_s}\\s*=\\s*(.*)/i", $linea_s, $match)) {
                    $valores_svxlink[$bloque_actual][$clave_s] = trim($match[2]);
                    $valores_svxlink["_enabled_{$bloque_actual}_{$clave_s}"] = ($match[1] !== '#');
                }
            }
        }
    }
}

/* =========================================================
   IDENTIFICACION CW
========================================================= */
$simplex = $valores_svxlink['[SimplexLogic]'] ?? [];
$cw_config = [
    'CALLSIGN' => $simplex['CALLSIGN'] ?? '',
    'SHORT_IDENT_INTERVAL' => $simplex['SHORT_IDENT_INTERVAL'] ?? '60',
    'LONG_IDENT_INTERVAL' => $simplex['LONG_IDENT_INTERVAL'] ?? '60',
    'SHORT_VOICE_ID_ENABLE' => $simplex['SHORT_VOICE_ID_ENABLE'] ?? '1',
    'LONG_VOICE_ID_ENABLE' => $simplex['LONG_VOICE_ID_ENABLE'] ?? '1',
    'SHORT_CW_ID_ENABLE' => $simplex['SHORT_CW_ID_ENABLE'] ?? '0',
    'LONG_CW_ID_ENABLE' => $simplex['LONG_CW_ID_ENABLE'] ?? '0',
    'CW_AMP' => $simplex['CW_AMP'] ?? '-6',
    'CW_PITCH' => $simplex['CW_PITCH'] ?? '800',
    'CW_WPM' => $simplex['CW_WPM'] ?? '20'
];
$cw_activo = ($cw_config['SHORT_CW_ID_ENABLE'] === '1' || $cw_config['LONG_CW_ID_ENABLE'] === '1');

/* =========================================================
   CONFIGURACION SISMOGRAFO
========================================================= */
$seismic_file = __DIR__ . '/data/seismic_config.json';
$seismic_config = [
    'enabled' => true,
    'provider' => 'auto',
    'latitude' => -29.9027,
    'longitude' => -71.2519,
    'refresh_seconds' => 60,
    'display' => [
        'min_magnitude' => 2.5,
        'radius_km' => 3000
    ],
    'rf' => [
        'enabled' => false,
        'command_pty' => '/dev/shm/simplex_logic_ctrl',
        'local' => ['enabled' => true, 'radius_km' => 200, 'min_magnitude' => 3.5],
        'regional' => ['enabled' => true, 'radius_km' => 500, 'min_magnitude' => 4.2],
        'wide' => ['enabled' => true, 'radius_km' => 1000, 'min_magnitude' => 5.0],
        'national' => ['enabled' => true, 'min_magnitude' => 6.0],
        'repetitions' => 1,
        'pre_tone' => true
    ],
    'tts' => [
        'engine' => 'piper',
        'voice' => 'es_ES-davefx-medium',
        'model' => '/opt/auroxlink/piper/voices/es_ES-davefx-medium.onnx',
        'length_scale' => 1.0,
        'fallback_voice' => 'es',
        'fallback_speed' => 145
    ]
];
if (file_exists($seismic_file)) {
    $loaded_seismic = json_decode(file_get_contents($seismic_file), true);
    if (is_array($loaded_seismic)) {
        $seismic_config = array_replace_recursive($seismic_config, $loaded_seismic);
    }
}
$seismic_rf_enabled = !empty($seismic_config['rf']['enabled']);
$seismic_error = $_SESSION['seismic_error'] ?? '';
unset($_SESSION['seismic_error']);

$seismic_test_ok = $_SESSION['seismic_test_ok'] ?? '';
unset($_SESSION['seismic_test_ok']);

$seismic_test_error = $_SESSION['seismic_test_error'] ?? '';
unset($_SESSION['seismic_test_error']);

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
            $tarjetas[] = ['numero' => $m[1], 'nombre' => trim($m[2]), 'descripcion' => trim($m[3])];
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
        if (strpos($salida, '[on]') !== false) return 'on';
        if (strpos($salida, '[off]') !== false) return 'off';
        return null;
    }
    if (strpos($salida, 'Playback') !== false || strpos($salida, 'Capture') !== false) {
        preg_match_all('/\[(\d+)%\]/', $salida, $matches);
        return $matches[1];
    }
    if (strpos($salida, 'on]') !== false || strpos($salida, 'off]') !== false) {
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
            if (in_array($valor, ['on','off'])) {
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
    shell_exec('sudo alsactl store 2>&1');
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
    <title><?= htmlspecialchars($titleSite); ?> - <?= t('menu_settings', 'Settings'); ?></title>
</head>
<body>
<div class="container-fluid bg-body-content">
<div class="row">
<?php require 'includes/sidebar-menu.php'; ?>
<div class="col-12 col-md-10 p-3">

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div class="d-flex align-items-center">
        <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">☰</button>
        <h2 class="fs-4 titulo m-0">⚙️ <?= t('menu_settings', 'Settings'); ?></h2>
    </div>
    <a href="index.php" class="btn btn-outline-secondary">⬅️ <?= t('settings_back_dashboard', 'Back to Dashboard'); ?></a>
</div>

<div class="row g-4">

<!-- =========================================================
     COLUMNA IZQUIERDA
========================================================= -->
<div class="col-12 col-xl-6">

<!-- ECHOLINK -->
<div class="card shadow-sm mb-4"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fs-4 titulo mb-1">🔗 <?= t('settings_echolink_module', 'EchoLink Module Settings'); ?></h2>
            <small class="text-muted">/etc/svxlink/svxlink.d/ModuleEchoLink.conf</small>
        </div>
        <a href="help.php" target="_blank" class="btn btn-sm text-white px-3 shadow-sm" style="background-color:#00b4d8;"><?= t('settings_view_help', 'View Help'); ?></a>
    </div>

    <form method="post" action="includes/save-config.php">
        <div class="row g-3">
        <?php foreach ($parametros_echolink as $clave_e):
            $esPassword = in_array($clave_e, ['PASSWORD','PROXY_PASSWORD']); ?>
            <div class="col-12 col-lg-6"><div class="border rounded p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                    <label for="<?= $clave_e; ?>" class="form-label fw-semibold mb-0"><?= htmlspecialchars($clave_e); ?></label>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="enable_<?= $clave_e; ?>" id="enable_<?= $clave_e; ?>" <?= ($valores_echolink["_enabled_{$clave_e}"] ?? true) ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="enable_<?= $clave_e; ?>"><?= t('active','Active'); ?></label>
                    </div>
                </div>
                <?php if ($clave_e === 'AUTOCON_ECHOLINK_ID'): ?>
                    <input type="text" class="form-control" name="<?= $clave_e; ?>" id="<?= $clave_e; ?>" value="<?= htmlspecialchars($valores_echolink[$clave_e] ?? ''); ?>" placeholder="<?= t('settings_node_id_placeholder','Node ID or 0 to disable'); ?>">
                    <small class="form-text text-muted d-block mt-2">🔗 <a href="https://www.echolink.org/logins.jsp" target="_blank"><?= t('settings_where_get_id','Where do I get this ID?'); ?></a></small>
                <?php elseif ($esPassword): ?>
                    <div class="input-group">
                        <input type="password" class="form-control" name="<?= $clave_e; ?>" id="<?= $clave_e; ?>" value="" placeholder="••••••••">
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('<?= $clave_e; ?>')">👁️</button>
                    </div>
                    <small class="form-text text-muted d-block mt-2"><?= t('settings_leave_blank_keep_password','Leave blank to keep current password'); ?></small>
                <?php else: ?>
                    <input type="text" class="form-control" name="<?= $clave_e; ?>" id="<?= $clave_e; ?>" value="<?= htmlspecialchars($valores_echolink[$clave_e] ?? ''); ?>">
                <?php endif; ?>
            </div></div>
        <?php endforeach; ?>
        </div>
        <div class="d-grid mt-4"><button type="submit" class="btn btn-primary">💾 <?= t('save_changes','Save changes'); ?></button></div>
    </form>
</div></div>

<!-- IDENTIFICACION CW -->
<div class="card shadow-sm mb-4"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fs-4 titulo mb-1">📻 <?= t('settings_cw_title','Identificación CW'); ?></h2>
            <small class="text-muted"><?= t('settings_cw_description','Configuración de identificación Morse en [SimplexLogic]'); ?></small>
        </div>
        <?php if ($cw_activo): ?><span class="badge bg-success"><?= t('settings_cw_active','CW ACTIVO'); ?></span>
        <?php else: ?><span class="badge bg-secondary"><?= t('settings_cw_inactive','CW DESACTIVADO'); ?></span><?php endif; ?>
    </div>
    <div class="alert alert-info py-2"><strong><?= t('settings_cw_callsign','Indicativo'); ?>:</strong> <?= htmlspecialchars($cw_config['CALLSIGN'] ?: 'Sin configurar'); ?></div>

    <form method="post" action="includes/save-svx.php">
        <?php foreach (['SHORT_IDENT_INTERVAL','LONG_IDENT_INTERVAL','SHORT_VOICE_ID_ENABLE','LONG_VOICE_ID_ENABLE','SHORT_CW_ID_ENABLE','LONG_CW_ID_ENABLE','CW_AMP','CW_PITCH','CW_WPM'] as $p): ?>
            <input type="hidden" name="enable_SimplexLogic_<?= $p; ?>" value="1">
        <?php endforeach; ?>

        <div class="border rounded p-3 mb-3">
            <h5 class="mb-3">⏱️ <?= t('settings_cw_short_ident','Identificación corta'); ?></h5>
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-3">
                    <input type="hidden" name="SimplexLogic_SHORT_VOICE_ID_ENABLE" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="SimplexLogic_SHORT_VOICE_ID_ENABLE" id="cw_short_voice" value="1" <?= $cw_config['SHORT_VOICE_ID_ENABLE'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="cw_short_voice">🔊 <?= t('settings_cw_voice','Voz'); ?></label>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <input type="hidden" name="SimplexLogic_SHORT_CW_ID_ENABLE" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="SimplexLogic_SHORT_CW_ID_ENABLE" id="cw_short" value="1" <?= $cw_config['SHORT_CW_ID_ENABLE'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="cw_short">📡 CW</label>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label for="cw_short_interval" class="form-label"><?= t('settings_cw_interval','Intervalo'); ?></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="cw_short_interval" name="SimplexLogic_SHORT_IDENT_INTERVAL" min="0" step="1" value="<?= htmlspecialchars($cw_config['SHORT_IDENT_INTERVAL']); ?>" required>
                        <span class="input-group-text"><?= t('settings_cw_minutes','minutos'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="border rounded p-3 mb-3">
            <h5 class="mb-3">⏱️ <?= t('settings_cw_long_ident','Identificación larga'); ?></h5>
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-3">
                    <input type="hidden" name="SimplexLogic_LONG_VOICE_ID_ENABLE" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="SimplexLogic_LONG_VOICE_ID_ENABLE" id="cw_long_voice" value="1" <?= $cw_config['LONG_VOICE_ID_ENABLE'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="cw_long_voice">🔊 <?= t('settings_cw_voice','Voz'); ?></label>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <input type="hidden" name="SimplexLogic_LONG_CW_ID_ENABLE" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="SimplexLogic_LONG_CW_ID_ENABLE" id="cw_long" value="1" <?= $cw_config['LONG_CW_ID_ENABLE'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="cw_long">📡 CW</label>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label for="cw_long_interval" class="form-label"><?= t('settings_cw_interval','Intervalo'); ?></label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="cw_long_interval" name="SimplexLogic_LONG_IDENT_INTERVAL" min="0" step="1" value="<?= htmlspecialchars($cw_config['LONG_IDENT_INTERVAL']); ?>" required>
                        <span class="input-group-text"><?= t('settings_cw_minutes','minutos'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="border rounded p-3 mb-3">
            <h5 class="mb-3">🎛️ <?= t('settings_cw_morse_config','Configuración Morse'); ?></h5>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label for="cw_wpm" class="form-label"><?= t('settings_cw_speed','Velocidad'); ?></label>
                    <div class="input-group"><input type="number" class="form-control" id="cw_wpm" name="SimplexLogic_CW_WPM" min="1" max="60" value="<?= htmlspecialchars($cw_config['CW_WPM']); ?>" required><span class="input-group-text">WPM</span></div>
                </div>
                <div class="col-12 col-md-4">
                    <label for="cw_pitch" class="form-label"><?= t('settings_cw_tone','Tono'); ?></label>
                    <div class="input-group"><input type="number" class="form-control" id="cw_pitch" name="SimplexLogic_CW_PITCH" min="100" max="3000" value="<?= htmlspecialchars($cw_config['CW_PITCH']); ?>" required><span class="input-group-text">Hz</span></div>
                </div>
                <div class="col-12 col-md-4">
                    <label for="cw_amp" class="form-label"><?= t('settings_cw_level','Nivel'); ?></label>
                    <div class="input-group"><input type="number" class="form-control" id="cw_amp" name="SimplexLogic_CW_AMP" min="-30" max="0" value="<?= htmlspecialchars($cw_config['CW_AMP']); ?>" required><span class="input-group-text">dB</span></div>
                </div>
            </div>
        </div>

        <div class="alert alert-secondary py-2"><small><?= t('settings_cw_auto_create','Si estos parámetros todavía no existen en'); ?> <strong>svxlink.conf</strong>, <?= t('settings_cw_auto_create_2','AUROXLINK los creará automáticamente dentro de'); ?> <strong>[SimplexLogic]</strong>. <strong><?= t('settings_cw_compatibility','Compatibilidad: SvxLink 1.7.0 (19.09)+'); ?></strong>.</small></div>
        <div class="d-grid"><button type="submit" class="btn btn-primary">💾 <?= t('settings_cw_save','Guardar configuración CW'); ?></button></div>
    </form>
</div></div>

<!-- SISMOGRAFO -->
<div id="seismic-settings" class="card shadow-sm mb-4"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fs-4 titulo mb-1">🌎 Sismógrafo y Alertas RF</h2>
            <small class="text-muted">Monitoreo sísmico multifuente, clasificación por distancia y anuncios automáticos mediante SvxLink</small>
        </div>
        <?php if ($seismic_rf_enabled): ?><span class="badge bg-success">RF ACTIVO</span><?php else: ?><span class="badge bg-secondary">RF DESACTIVADO</span><?php endif; ?>
    </div>

    <?php if (isset($_GET['seismic']) && $_GET['seismic'] === 'saved'): ?>
        <div class="alert alert-success">✅ Configuración sísmica guardada correctamente.</div>
    <?php endif; ?>
    <?php if ($seismic_error): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars($seismic_error); ?></div>
    <?php endif; ?>

    <?php if ($seismic_test_ok): ?>
        <div class="alert alert-success">🔊 <?= htmlspecialchars($seismic_test_ok); ?></div>
    <?php endif; ?>

    <?php if ($seismic_test_error): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars($seismic_test_error); ?></div>
    <?php endif; ?>

    <form method="post" action="includes/save-seismic.php">
        <div class="border rounded p-3 mb-3">
            <h5 class="mb-3">⚙️ Control general</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <input type="hidden" name="enabled" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="seismic_enabled" name="enabled" value="1" <?= !empty($seismic_config['enabled']) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="seismic_enabled">Habilitar monitor sísmico</label>
                    </div>
                    <small class="text-muted">Mantiene activo el módulo de monitoreo y clasificación sísmica.</small>
                </div>
                <div class="col-md-6">
                    <input type="hidden" name="rf_enabled" value="0">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="rf_enabled" name="rf_enabled" value="1" <?= $seismic_rf_enabled ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="rf_enabled">📻 Activar alertas automáticas por RF</label>
                    </div>
                    <small class="text-muted">Los eventos nuevos que cumplan las reglas serán enviados a SvxLink.</small>
                </div>
            </div>
        </div>

        <div class="border rounded p-3 mb-3">
            <h5 class="mb-3">🌐 Fuente de información sísmica</h5>
            <div class="row g-3 align-items-start">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="seismic_provider">Fuente principal</label>
                    <select id="seismic_provider" name="provider" class="form-select">
                        <option value="auto" <?= (($seismic_config['provider'] ?? 'auto') === 'auto') ? 'selected' : ''; ?>>Automática — CSN en Chile / USGS fuera de Chile</option>
                        <option value="csn" <?= (($seismic_config['provider'] ?? 'auto') === 'csn') ? 'selected' : ''; ?>>CSN — Chile</option>
                        <option value="usgs" <?= (($seismic_config['provider'] ?? 'auto') === 'usgs') ? 'selected' : ''; ?>>USGS — Mundial</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-light border mb-0 py-2 small">
                        <strong>AUTO:</strong> en Chile prioriza CSN y usa USGS si CSN falla. Fuera de Chile utiliza USGS. La selección manual siempre tiene prioridad.
                    </div>
                </div>
            </div>
        </div>

        <div class="border rounded p-3 mb-3">
            <h5 class="mb-3">📍 Ubicación y consulta</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Latitud del nodo</label>
                    <input type="number" step="0.000001" min="-90" max="90" name="latitude" class="form-control" value="<?= htmlspecialchars((string)$seismic_config['latitude']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Longitud del nodo</label>
                    <input type="number" step="0.000001" min="-180" max="180" name="longitude" class="form-control" value="<?= htmlspecialchars((string)$seismic_config['longitude']); ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Intervalo de consulta</label>
                    <div class="input-group"><input type="number" min="30" max="3600" name="refresh_seconds" class="form-control" value="<?= (int)$seismic_config['refresh_seconds']; ?>" required><span class="input-group-text">s</span></div>
                    <small class="text-muted">El timer actual consulta cada 60 s; este valor también controla la interfaz.</small>
                </div>
            </div>
        </div>

        <div class="border rounded p-3 mb-3">
            <h5 class="mb-3">🗺️ Visualización</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Magnitud mínima mostrada</label>
                    <input type="number" step="0.1" min="0" max="10" name="display_min_magnitude" class="form-control" value="<?= htmlspecialchars((string)$seismic_config['display']['min_magnitude']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Radio máximo mostrado</label>
                    <div class="input-group"><input type="number" step="1" min="50" max="10000" name="display_radius_km" class="form-control" value="<?= htmlspecialchars((string)$seismic_config['display']['radius_km']); ?>"><span class="input-group-text">km</span></div>
                </div>
            </div>
        </div>

        <div class="border rounded p-3 mb-3">
            <div class="mb-3"><h5 class="mb-1">📡 Niveles de alerta RF</h5><small class="text-muted">Cada nivel combina distancia real desde el nodo y magnitud mínima.</small></div>
            <div class="row g-3">
                <?php
                $zonas = [
                    'local' => ['titulo' => '🔴 LOCAL', 'max' => 5000],
                    'regional' => ['titulo' => '🟠 REGIONAL', 'max' => 5000],
                    'wide' => ['titulo' => '🔵 AMPLIA', 'max' => 10000]
                ];
                foreach ($zonas as $key => $meta):
                    $z = $seismic_config['rf'][$key];
                ?>
                <div class="col-md-6"><div class="border rounded p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong><?= $meta['titulo']; ?></strong>
                        <div class="form-check form-switch m-0">
                            <input type="hidden" name="<?= $key; ?>_enabled" value="0">
                            <input class="form-check-input" type="checkbox" name="<?= $key; ?>_enabled" value="1" <?= !empty($z['enabled']) ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label">Radio</label><div class="input-group"><input type="number" name="<?= $key; ?>_radius_km" class="form-control" min="1" max="<?= $meta['max']; ?>" value="<?= htmlspecialchars((string)$z['radius_km']); ?>"><span class="input-group-text">km</span></div></div>
                        <div class="col-6"><label class="form-label">Magnitud mínima</label><input type="number" step="0.1" min="0" max="10" name="<?= $key; ?>_min_magnitude" class="form-control" value="<?= htmlspecialchars((string)$z['min_magnitude']); ?>"></div>
                    </div>
                </div></div>
                <?php endforeach; ?>

                <div class="col-md-6"><div class="border rounded p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <strong>🇨🇱 NACIONAL</strong>
                        <div class="form-check form-switch m-0">
                            <input type="hidden" name="national_enabled" value="0">
                            <input class="form-check-input" type="checkbox" name="national_enabled" value="1" <?= !empty($seismic_config['rf']['national']['enabled']) ? 'checked' : ''; ?>>
                        </div>
                    </div>
                    <label class="form-label">Magnitud mínima global / nacional</label>
                    <input type="number" step="0.1" min="0" max="10" name="national_min_magnitude" class="form-control" value="<?= htmlspecialchars((string)$seismic_config['rf']['national']['min_magnitude']); ?>">
                    <small class="text-muted">Se evalúa independientemente de la distancia al nodo. Con USGS funciona como umbral GLOBAL.</small>
                </div></div>
            </div>
        </div>

        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">🗣️ Voz de alertas sísmicas</h5>
                    <small class="text-muted">Piper es la voz principal. Si falla, AUROXLINK utiliza espeak-ng automáticamente.</small>
                </div>
                <?php
                $tts_engine = strtolower((string)($seismic_config['tts']['engine'] ?? 'piper'));
                $piper_model = (string)($seismic_config['tts']['model'] ?? '/opt/auroxlink/piper/voices/es_ES-davefx-medium.onnx');
                $piper_bin = '/opt/auroxlink/piper-venv/bin/piper';
                $piper_ready = is_executable($piper_bin) && is_file($piper_model);
                ?>
                <?php if ($tts_engine === 'piper' && $piper_ready): ?>
                    <span class="badge bg-success">PIPER ACTIVO</span>
                <?php elseif ($tts_engine === 'piper'): ?>
                    <span class="badge bg-warning text-dark">PIPER NO DISPONIBLE</span>
                <?php else: ?>
                    <span class="badge bg-secondary">ESPEAK-NG</span>
                <?php endif; ?>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Motor de voz</label>
                    <select name="tts_engine" id="tts_engine" class="form-select">
                        <option value="piper" <?= $tts_engine === 'piper' ? 'selected' : ''; ?>>Piper — voz natural</option>
                        <option value="espeak-ng" <?= in_array($tts_engine, ['espeak-ng','espeak'], true) ? 'selected' : ''; ?>>espeak-ng — respaldo</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Voz Piper</label>
                    <input type="text" name="tts_voice" class="form-control"
                           value="<?= htmlspecialchars((string)($seismic_config['tts']['voice'] ?? 'es_ES-davefx-medium')); ?>">
                    <small class="text-muted">Modelo instalado actualmente: es_ES-davefx-medium</small>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Modelo Piper</label>
                    <input type="text" name="tts_model" class="form-control"
                           value="<?= htmlspecialchars($piper_model); ?>">
                    <small class="text-muted">Ruta del archivo .onnx usado para generar la locución.</small>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Velocidad Piper</label>
                    <div class="input-group">
                        <input type="number" name="tts_length_scale" class="form-control"
                               min="0.5" max="2.5" step="0.05"
                               value="<?= htmlspecialchars((string)($seismic_config['tts']['length_scale'] ?? 1.0)); ?>">
                        <span class="input-group-text">x</span>
                    </div>
                    <small class="text-muted">1.0 normal · menor = más rápida · mayor = más lenta.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Voz respaldo espeak-ng</label>
                    <input type="text" name="tts_fallback_voice" class="form-control"
                           value="<?= htmlspecialchars((string)($seismic_config['tts']['fallback_voice'] ?? 'es')); ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Velocidad respaldo</label>
                    <div class="input-group">
                        <input type="number" name="tts_fallback_speed" class="form-control"
                               min="80" max="450" step="1"
                               value="<?= (int)($seismic_config['tts']['fallback_speed'] ?? 145); ?>">
                        <span class="input-group-text">WPM</span>
                    </div>
                </div>
            </div>

            <div class="alert alert-secondary py-2 small mt-3 mb-0">
                <strong>Estado Piper:</strong>
                ejecutable <?= is_executable($piper_bin) ? '✅' : '❌'; ?> ·
                modelo <?= is_file($piper_model) ? '✅' : '❌'; ?> ·
                salida RF 16 kHz / mono / 16-bit.
            </div>
        </div>

        <div class="border rounded p-3 mb-3">
            <h5 class="mb-3">🔊 Transmisión</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Repeticiones</label>
                    <select name="rf_repetitions" class="form-select">
                        <?php for ($i=1; $i<=3; $i++): ?><option value="<?= $i; ?>" <?= ((int)$seismic_config['rf']['repetitions'] === $i) ? 'selected' : ''; ?>><?= $i; ?></option><?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">COMMAND_PTY</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($seismic_config['rf']['command_pty']); ?>" readonly>
                    <small class="text-muted">Gestionado por SvxLink.</small>
                </div>
                <div class="col-md-4 d-flex align-items-center">
                    <div class="form-check form-switch">
                        <input type="hidden" name="rf_pre_tone" value="0">
                        <input class="form-check-input" type="checkbox" id="rf_pre_tone" name="rf_pre_tone" value="1" <?= !empty($seismic_config['rf']['pre_tone']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="rf_pre_tone">Tono previo al anuncio</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info py-2 small mb-3">
            <strong>Lógica actual:</strong>
            LOCAL ≤ <?= (int)$seismic_config['rf']['local']['radius_km']; ?> km / M<?= htmlspecialchars((string)$seismic_config['rf']['local']['min_magnitude']); ?> ·
            REGIONAL ≤ <?= (int)$seismic_config['rf']['regional']['radius_km']; ?> km / M<?= htmlspecialchars((string)$seismic_config['rf']['regional']['min_magnitude']); ?> ·
            AMPLIA ≤ <?= (int)$seismic_config['rf']['wide']['radius_km']; ?> km / M<?= htmlspecialchars((string)$seismic_config['rf']['wide']['min_magnitude']); ?> ·
            GLOBAL/NACIONAL M<?= htmlspecialchars((string)$seismic_config['rf']['national']['min_magnitude']); ?>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary">
                💾 Guardar configuración sísmica
            </button>
        </div>
    </form>

    <hr class="my-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="fw-semibold">🔊 Prueba manual de audio / RF</div>
            <small class="text-muted">
                Genera la locución de prueba y la transmite mediante SvxLink.
                Funciona aunque las alertas RF automáticas estén desactivadas.
            </small>
        </div>

        <form
            method="post"
            action="includes/test-seismic-rf.php"
            class="m-0"
            onsubmit="return confirm('¿Transmitir prueba de audio por RF ahora?');"
        >
            <button type="submit" class="btn btn-outline-warning">
                🔊 Probar audio / RF
            </button>
        </form>
    </div>
</div></div>

<!-- AUDIO -->
<div class="card shadow-sm mb-4"><div class="card-body">
    <h2 class="fs-4 titulo mb-4">🎛️ <?= t('settings_audio_control','Audio Control'); ?> <?= htmlspecialchars($titleSite); ?></h2>
    <?php if ($audio_guardado_msg): ?><div class="alert alert-info"><?= htmlspecialchars($audio_guardado_msg); ?></div><?php endif; ?>
    <form method="post">
        <div class="mb-4">
            <label for="tarjeta" class="form-label fw-semibold"><?= t('settings_select_sound_card','Select Audio Card'); ?>:</label>
            <select class="form-select" name="tarjeta" id="tarjeta" onchange="this.form.submit()">
                <?php foreach ($tarjetas as $t): ?>
                    <option value="<?= htmlspecialchars($t['numero']); ?>" <?= ($t['numero'] == $tarjeta_seleccionada) ? 'selected' : ''; ?>><?= htmlspecialchars("Card {$t['numero']}: {$t['nombre']} [{$t['descripcion']}]"); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="row g-3">
            <?php foreach ($controles as $control): $estado = obtenerEstadoControl($tarjeta_seleccionada, $control); ?>
                <div class="col-12 col-lg-6"><div class="border rounded p-3 h-100">
                    <label class="form-label fw-semibold"><?= t('settings_control','Control'); ?>: <?= htmlspecialchars($control); ?></label>
                    <?php if (is_array($estado)): ?>
                        <input type="range" class="form-range" name="<?= htmlspecialchars(str_replace(' ','_',$control)); ?>" min="0" max="100" value="<?= htmlspecialchars($estado[0]); ?>">
                        <small class="text-muted"><?= t('settings_current_value','Current value'); ?>: <?= htmlspecialchars($estado[0]); ?>%</small>
                    <?php elseif (in_array($estado, ['on','off'])): ?>
                        <select class="form-select" name="<?= htmlspecialchars(str_replace(' ','_',$control)); ?>">
                            <option value="on" <?= $estado === 'on' ? 'selected' : ''; ?>><?= t('enabled','Enabled'); ?></option>
                            <option value="off" <?= $estado === 'off' ? 'selected' : ''; ?>><?= t('disabled','Disabled'); ?></option>
                        </select>
                    <?php endif; ?>
                </div></div>
            <?php endforeach; ?>
        </div>
        <div class="d-grid gap-2 mt-4">
            <button type="submit" name="aplicar" class="btn btn-success"><?= t('settings_apply_changes','Apply Changes'); ?></button>
            <button type="submit" name="guardar_audio" class="btn btn-secondary">💾 <?= t('settings_save_audio_config','Save audio settings'); ?></button>
        </div>
    </form>
</div></div>

<!-- ACTUALIZADOR -->
<?php require __DIR__ . '/includes/svxlink_update_panel.php'; ?>

</div>

<!-- =========================================================
     COLUMNA DERECHA - SVXLINK.CONF
========================================================= -->
<div class="col-12 col-xl-6">
<div class="card shadow-sm mb-4"><div class="card-body">
    <div class="mb-4">
        <h2 class="fs-4 titulo mb-1">⚙️ <?= t('settings_svxlink_conf','svxlink.conf Settings'); ?></h2>
        <small class="text-muted">/etc/svxlink/svxlink.conf</small>
    </div>

    <?php
    $meta_secciones = [
        '[GLOBAL]' => ['icono'=>'🌐','titulo'=>'GLOBAL','descripcion'=>'Parámetros generales de SvxLink'],
        '[SimplexLogic]' => ['icono'=>'📻','titulo'=>'SimplexLogic','descripcion'=>'Configuración principal de la lógica de radio'],
        '[Rx1]' => ['icono'=>'🎙️','titulo'=>'Receptor RX1','descripcion'=>'Audio, squelch y control del receptor'],
        '[Tx1]' => ['icono'=>'📡','titulo'=>'Transmisor TX1','descripcion'=>'Audio y control PTT del transmisor'],
        '[LocationInfo]' => ['icono'=>'📍','titulo'=>'LocationInfo','descripcion'=>'Datos APRS, ubicación y características de la estación']
    ];
    ?>

    <form method="post" action="includes/save-svx.php">
        <?php foreach ($parametros_svxlink as $seccion => $claves_s): if (!empty($claves_s)):
            $meta = $meta_secciones[$seccion] ?? ['icono'=>'⚙️','titulo'=>trim($seccion,'[]'),'descripcion'=>'']; ?>
            <div class="border rounded p-3 mb-4">
                <div class="mb-3">
                    <h5 class="mb-1"><?= $meta['icono']; ?> <?= htmlspecialchars($meta['titulo']); ?></h5>
                    <?php if (!empty($meta['descripcion'])): ?><small class="text-muted"><?= htmlspecialchars($meta['descripcion']); ?></small><?php endif; ?>
                </div>
                <div class="row g-3">
                    <?php foreach ($claves_s as $clave_s):
                        $nombreCampo = str_replace(['[',']'],'',$seccion) . '_' . $clave_s;
                        $activo = $valores_svxlink["_enabled_{$seccion}_{$clave_s}"] ?? true;
                        $valor = $valores_svxlink[$seccion][$clave_s] ?? '';
                        $anchoCompleto = in_array($clave_s, ['APRS_SERVER_LIST','PATH','COMMENT']);
                        $columna = $anchoCompleto ? 'col-12' : 'col-12 col-lg-6';
                    ?>
                    <div class="<?= $columna; ?>"><div class="border rounded p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                            <label class="form-label fw-semibold mb-0" for="<?= $nombreCampo; ?>"><?= htmlspecialchars($clave_s); ?></label>
                            <div class="form-check form-switch m-0">
                                <input class="form-check-input" type="checkbox" name="enable_<?= $nombreCampo; ?>" id="enable_<?= $nombreCampo; ?>" <?= $activo ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="enable_<?= $nombreCampo; ?>"><?= t('active','Active'); ?></label>
                            </div>
                        </div>
                        <input type="text" class="form-control" name="<?= $nombreCampo; ?>" id="<?= $nombreCampo; ?>" value="<?= htmlspecialchars($valor); ?>">
                    </div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; endforeach; ?>
        <div class="d-grid"><button type="submit" class="btn btn-primary">💾 <?= t('save_changes','Save changes'); ?></button></div>
    </form>
</div></div>
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
teleco@auroxlink:/var/www/html $
