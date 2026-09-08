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
    $lang = json_decode(
        file_get_contents(__DIR__ . "/data/lang/es.json"),
        true
    );
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

        header("Location: settings.php");
        exit;
    }

    echo '<!DOCTYPE html>

    <html lang="' . htmlspecialchars($idioma) . '">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>' .
            t('settings_secure_access', 'Secure Access') .
        '</title>

        <link
            rel="stylesheet"
            href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css"
        >

    </head>

    <body class="bg-light">

        <div class="container mt-5">

            <div class="row justify-content-center">

                <div class="col-md-4">

                    <div class="card p-4 shadow-sm">

                        <h4>
                            🔐 ' .
                            t(
                                'settings_secure_login',
                                'Secure Login'
                            ) .
                        '</h4>

                        <form method="post">

                            <input
                                type="password"
                                name="clave"
                                class="form-control mb-3"
                                placeholder="' .
                                    t(
                                        'password',
                                        'Password'
                                    ) .
                                '"
                                required
                            >

                            <button
                                type="submit"
                                class="btn btn-primary btn-block mb-2"
                            >
                                ' .
                                t(
                                    'settings_enter',
                                    'Enter'
                                ) .
                                '
                            </button>

                            <a
                                href="index.php"
                                class="btn btn-outline-secondary btn-block"
                            >
                                ⬅️ ' .
                                t(
                                    'settings_back_dashboard',
                                    'Back to Dashboard'
                                ) .
                                '
                            </a>

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
$archivo_echolink =
    "/etc/svxlink/svxlink.d/ModuleEchoLink.conf";

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

            if (
                preg_match(
                    "/^\s*(#?)\s*{$clave_e}\s*=\s*(.*)/i",
                    $linea_e,
                    $match
                )
            ) {

                $valores_echolink[$clave_e] =
                    trim($match[2]);

                $valores_echolink[
                    "_enabled_{$clave_e}"
                ] =
                    ($match[1] !== '#');

                break;
            }
        }
    }
}


/* =========================================================
   CONFIGURACION SVXLINK
========================================================= */
$archivo_svxlink =
    "/etc/svxlink/svxlink.conf";

/*
 * Parámetros que se muestran en la configuración general.
 */
$parametros_svxlink = [

    "[GLOBAL]" => [
        "LOCATION_INFO"
    ],

    "[SimplexLogic]" => [
        "CALLSIGN"
    ],

    "[Rx1]" => [
        "AUDIO_DEV",
        "SQL_DET",
        "SQL_START_DELAY",
        "SQL_DELAY",
        "PREAMP",
        "SQL_HANGTIME",
        "SERIAL_PORT",
        "SERIAL_PIN"
    ],

    "[Tx1]" => [
        "AUDIO_DEV",
        "PTT_TYPE",
        "PTT_PORT",
        "PTT_PIN"
    ],

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


/*
 * Parámetros adicionales que necesitamos LEER
 * para la tarjeta CW.
 *
 * No se muestran duplicados en la configuración general.
 */
$parametros_svxlink_lectura =
    $parametros_svxlink;

$parametros_svxlink_lectura[
    "[SimplexLogic]"
] = [

    "CALLSIGN",

    "SHORT_IDENT_INTERVAL",
    "LONG_IDENT_INTERVAL",

    "SHORT_VOICE_ID_ENABLE",
    "LONG_VOICE_ID_ENABLE",

    "SHORT_CW_ID_ENABLE",
    "LONG_CW_ID_ENABLE",

    "CW_AMP",
    "CW_PITCH",
    "CW_WPM"

];


$valores_svxlink = [];

$bloque_actual = null;


if (file_exists($archivo_svxlink)) {

    $lineas_s = file($archivo_svxlink);

    foreach ($lineas_s as $linea_s) {

        $linea_s = trim($linea_s);


        if (
            preg_match(
                "/^\[(.*)\]/",
                $linea_s,
                $match
            )
        ) {

            $bloque_actual =
                "[" . $match[1] . "]";

        } elseif (
            $bloque_actual &&
            isset(
                $parametros_svxlink_lectura[
                    $bloque_actual
                ]
            )
        ) {

            foreach (
                $parametros_svxlink_lectura[
                    $bloque_actual
                ]
                as $clave_s
            ) {

                if (
                    preg_match(
                        "/^\s*(#?)\s*{$clave_s}\s*=\s*(.*)/i",
                        $linea_s,
                        $match
                    )
                ) {

                    $valores_svxlink[
                        $bloque_actual
                    ][
                        $clave_s
                    ] =
                        trim($match[2]);


                    $valores_svxlink[
                        "_enabled_{$bloque_actual}_{$clave_s}"
                    ] =
                        ($match[1] !== '#');
                }
            }
        }
    }
}


/* =========================================================
   IDENTIFICACION CW
========================================================= */

$simplex =
    $valores_svxlink[
        "[SimplexLogic]"
    ]
    ?? [];


/*
 * Valores por defecto.
 *
 * Si todavía no existen en svxlink.conf,
 * AUROXLINK los mostrará en el panel.
 *
 * save-svx.php se encargará de crearlos
 * físicamente al guardar.
 */
$cw_config = [

    "CALLSIGN" =>
        $simplex["CALLSIGN"]
        ?? '',


    "SHORT_IDENT_INTERVAL" =>
        $simplex["SHORT_IDENT_INTERVAL"]
        ?? '60',


    "LONG_IDENT_INTERVAL" =>
        $simplex["LONG_IDENT_INTERVAL"]
        ?? '60',


    "SHORT_VOICE_ID_ENABLE" =>
        $simplex["SHORT_VOICE_ID_ENABLE"]
        ?? '1',


    "LONG_VOICE_ID_ENABLE" =>
        $simplex["LONG_VOICE_ID_ENABLE"]
        ?? '1',


    "SHORT_CW_ID_ENABLE" =>
        $simplex["SHORT_CW_ID_ENABLE"]
        ?? '0',


    "LONG_CW_ID_ENABLE" =>
        $simplex["LONG_CW_ID_ENABLE"]
        ?? '0',


    "CW_AMP" =>
        $simplex["CW_AMP"]
        ?? '-6',


    "CW_PITCH" =>
        $simplex["CW_PITCH"]
        ?? '800',


    "CW_WPM" =>
        $simplex["CW_WPM"]
        ?? '20'

];


$cw_activo =

    (
        $cw_config[
            "SHORT_CW_ID_ENABLE"
        ] === '1'
    )

    ||

    (
        $cw_config[
            "LONG_CW_ID_ENABLE"
        ] === '1'
    );


/* =========================================================
   CONFIGURACION DE AUDIO
========================================================= */

function obtenerTarjetas()
{

    $salida =
        shell_exec(
            'aplay -l 2>/dev/null'
        );

    $tarjetas = [];


    if ($salida) {

        preg_match_all(
            '/card (\d+): ([^\[]+)\[([^\]]+)\]/',
            $salida,
            $matches,
            PREG_SET_ORDER
        );


        foreach ($matches as $m) {

            $tarjetas[] = [

                'numero' =>
                    $m[1],

                'nombre' =>
                    trim($m[2]),

                'descripcion' =>
                    trim($m[3])

            ];
        }
    }

    return $tarjetas;
}


function obtenerControles($card)
{

    $salida =
        shell_exec(
            "sudo amixer -c {$card} scontrols 2>/dev/null"
        );


    $controles = [];


    if ($salida) {

        preg_match_all(
            "/Simple mixer control '([^']+)'/",
            $salida,
            $matches
        );


        $controles =
            $matches[1]
            ?? [];
    }


    return $controles;
}


function obtenerEstadoControl(
    $card,
    $control
) {

    $salida =
        shell_exec(
            "sudo amixer -c {$card} get '{$control}' 2>/dev/null"
        );


    if (
        stripos(
            $control,
            'AGC'
        ) !== false
        ||
        stripos(
            $control,
            'Auto Gain'
        ) !== false
    ) {

        if (
            strpos(
                $salida,
                '[on]'
            ) !== false
        ) {

            return 'on';

        } elseif (
            strpos(
                $salida,
                '[off]'
            ) !== false
        ) {

            return 'off';

        } else {

            return null;
        }
    }


    if (
        strpos(
            $salida,
            'Playback'
        ) !== false
        ||
        strpos(
            $salida,
            'Capture'
        ) !== false
    ) {

        preg_match_all(
            '/\[(\d+)%\]/',
            $salida,
            $matches
        );


        return $matches[1];


    } elseif (
        strpos(
            $salida,
            'on]'
        ) !== false
        ||
        strpos(
            $salida,
            'off]'
        ) !== false
    ) {

        return (
            strpos(
                $salida,
                '[on]'
            ) !== false
        )
            ? 'on'
            : 'off';
    }


    return null;
}


$tarjetas =
    obtenerTarjetas();


$tarjeta_seleccionada =

    isset($_POST['tarjeta'])

    ? intval(
        $_POST['tarjeta']
    )

    : 2;


$controles =
    obtenerControles(
        $tarjeta_seleccionada
    );


$audio_guardado_msg = '';


if (
    $_SERVER[
        'REQUEST_METHOD'
    ] === 'POST'
    &&
    isset(
        $_POST['aplicar']
    )
) {

    foreach (
        $controles
        as $control
    ) {

        $campo =
            str_replace(
                ' ',
                '_',
                $control
            );


        if (
            isset(
                $_POST[$campo]
            )
        ) {

            $valor =
                $_POST[$campo];


            if (
                in_array(
                    $valor,
                    [
                        'on',
                        'off'
                    ]
                )
            ) {

                $comando =
                    "sudo amixer -c {$tarjeta_seleccionada} sset '{$control}' {$valor}";

            } else {

                $comando =
                    "sudo amixer -c {$tarjeta_seleccionada} sset '{$control}' {$valor}%";
            }


            shell_exec(
                $comando
            );
        }
    }


    sleep(1);
}


if (
    $_SERVER[
        'REQUEST_METHOD'
    ] === 'POST'
    &&
    isset(
        $_POST[
            'guardar_audio'
        ]
    )
) {

    shell_exec(
        "sudo alsactl store 2>&1"
    );


    $audio_guardado_msg =
        t(
            'settings_audio_saved',
            'Audio settings saved successfully.'
        );
}

?>

<!doctype html>

<html lang="<?= htmlspecialchars($idioma); ?>">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css"
    >

    <link
        rel="stylesheet"
        href="style/style.css.php"
    >

    <link
        rel="shortcut icon"
        href="img/favicon.png"
        type="image/png"
    >

    <title>
        <?= htmlspecialchars($titleSite); ?>
        -
        <?= t(
            'menu_settings',
            'Settings'
        ); ?>
    </title>

</head>


<body>

<div class="container-fluid bg-body-content">

    <div class="row">

        <?php
        require
            'includes/sidebar-menu.php';
        ?>


        <div class="col-12 col-md-10 p-3">


            <!-- =====================================================
                 CABECERA
            ====================================================== -->

            <div
                class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"
            >

                <div
                    class="d-flex align-items-center"
                >

                    <button
                        class="btn btn-dark d-md-none me-2"
                        type="button"
                        data-bs-toggle="offcanvas"
                        data-bs-target="#mobileMenu"
                        aria-controls="mobileMenu"
                    >
                        ☰
                    </button>


                    <h2
                        class="fs-4 titulo m-0"
                    >
                        ⚙️
                        <?= t(
                            'menu_settings',
                            'Settings'
                        ); ?>
                    </h2>

                </div>


                <a
                    href="index.php"
                    class="btn btn-outline-secondary"
                >
                    ⬅️
                    <?= t(
                        'settings_back_dashboard',
                        'Back to Dashboard'
                    ); ?>
                </a>

            </div>



            <!-- =====================================================
                 DOS COLUMNAS
            ====================================================== -->

            <div class="row g-4">


                <!-- =================================================
                     COLUMNA IZQUIERDA
                ================================================== -->

                <div class="col-12 col-xl-6">


                    <!-- =================================================
                         ECHOLINK
                    ================================================== -->

                    <div
                        class="card shadow-sm mb-4"
                    >

                        <div
                            class="card-body"
                        >


                            <div
                                class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"
                            >

                                <div>

                                    <h2
                                        class="fs-4 titulo mb-1"
                                    >
                                        🔗
                                        <?= t(
                                            'settings_echolink_module',
                                            'EchoLink Module Settings'
                                        ); ?>
                                    </h2>


                                    <small
                                        class="text-muted"
                                    >
                                        /etc/svxlink/svxlink.d/ModuleEchoLink.conf
                                    </small>

                                </div>


                                <a
                                    href="help.php"
                                    target="_blank"
                                    class="btn btn-sm text-white px-3 shadow-sm"
                                    style="background-color: #00b4d8;"
                                >
                                    <?= t(
                                        'settings_view_help',
                                        'View Help'
                                    ); ?>
                                </a>

                            </div>



                            <form
                                method="post"
                                action="includes/save-config.php"
                            >


                                <div
                                    class="row g-3"
                                >


                                    <?php
                                    foreach (
                                        $parametros_echolink
                                        as $clave_e
                                    ):
                                    ?>

                                        <?php

                                        $esPassword =
                                            in_array(
                                                $clave_e,
                                                [
                                                    'PASSWORD',
                                                    'PROXY_PASSWORD'
                                                ]
                                            );

                                        ?>


                                        <div
                                            class="col-12 col-lg-6"
                                        >


                                            <div
                                                class="border rounded p-3 h-100"
                                            >


                                                <div
                                                    class="d-flex justify-content-between align-items-center mb-2 gap-2"
                                                >

                                                    <label
                                                        for="<?= $clave_e; ?>"
                                                        class="form-label fw-semibold mb-0"
                                                    >
                                                        <?= htmlspecialchars(
                                                            $clave_e
                                                        ); ?>
                                                    </label>


                                                    <div
                                                        class="form-check form-switch m-0"
                                                    >

                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            name="enable_<?= $clave_e; ?>"
                                                            id="enable_<?= $clave_e; ?>"

                                                            <?= (
                                                                $valores_echolink[
                                                                    "_enabled_{$clave_e}"
                                                                ]
                                                                ?? true
                                                            )
                                                                ? 'checked'
                                                                : '';
                                                            ?>
                                                        >


                                                        <label
                                                            class="form-check-label small"
                                                            for="enable_<?= $clave_e; ?>"
                                                        >
                                                            <?= t(
                                                                'active',
                                                                'Active'
                                                            ); ?>
                                                        </label>

                                                    </div>

                                                </div>



                                                <?php
                                                if (
                                                    $clave_e
                                                    ===
                                                    "AUTOCON_ECHOLINK_ID"
                                                ):
                                                ?>


                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="<?= $clave_e; ?>"
                                                        id="<?= $clave_e; ?>"

                                                        value="<?= htmlspecialchars(
                                                            $valores_echolink[
                                                                $clave_e
                                                            ]
                                                            ?? ''
                                                        ); ?>"

                                                        placeholder="<?= t(
                                                            'settings_node_id_placeholder',
                                                            'Node ID or 0 to disable'
                                                        ); ?>"
                                                    >


                                                    <small
                                                        class="form-text text-muted d-block mt-2"
                                                    >

                                                        🔗

                                                        <a
                                                            href="https://www.echolink.org/logins.jsp"
                                                            target="_blank"
                                                        >

                                                            <?= t(
                                                                'settings_where_get_id',
                                                                'Where do I get this ID?'
                                                            ); ?>

                                                        </a>

                                                    </small>



                                                <?php
                                                elseif (
                                                    $esPassword
                                                ):
                                                ?>


                                                    <div
                                                        class="input-group"
                                                    >


                                                        <input
                                                            type="password"
                                                            class="form-control"
                                                            name="<?= $clave_e; ?>"
                                                            id="<?= $clave_e; ?>"
                                                            value=""
                                                            placeholder="••••••••"
                                                        >


                                                        <button
                                                            class="btn btn-outline-secondary"
                                                            type="button"
                                                            onclick="togglePassword('<?= $clave_e; ?>')"
                                                        >
                                                            👁️
                                                        </button>


                                                    </div>


                                                    <small
                                                        class="form-text text-muted d-block mt-2"
                                                    >

                                                        <?= t(
                                                            'settings_leave_blank_keep_password',
                                                            'Leave blank to keep current password'
                                                        ); ?>

                                                    </small>



                                                <?php
                                                else:
                                                ?>


                                                    <input
                                                        type="text"
                                                        class="form-control"
                                                        name="<?= $clave_e; ?>"
                                                        id="<?= $clave_e; ?>"

                                                        value="<?= htmlspecialchars(
                                                            $valores_echolink[
                                                                $clave_e
                                                            ]
                                                            ?? ''
                                                        ); ?>"
                                                    >


                                                <?php
                                                endif;
                                                ?>


                                            </div>


                                        </div>


                                    <?php
                                    endforeach;
                                    ?>


                                </div>



                                <div
                                    class="d-grid mt-4"
                                >

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >

                                        💾
                                        <?= t(
                                            'save_changes',
                                            'Save changes'
                                        ); ?>

                                    </button>

                                </div>


                            </form>


                        </div>


                    </div>



                    <!-- =================================================
                         IDENTIFICACION CW
                    ================================================== -->

                    <div
                        class="card shadow-sm mb-4"
                    >

                        <div
                            class="card-body"
                        >


                            <div
                                class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2"
                            >


                                <div>

                                    <h2
                                        class="fs-4 titulo mb-1"
                                    >
                                        📻 Identificación CW
                                    </h2>


                                    <small
                                        class="text-muted"
                                    >
                                        Configuración de identificación Morse en [SimplexLogic]
                                    </small>

                                </div>



                                <?php
                                if ($cw_activo):
                                ?>

                                    <span
                                        class="badge bg-success"
                                    >
                                        CW ACTIVO
                                    </span>

                                <?php
                                else:
                                ?>

                                    <span
                                        class="badge bg-secondary"
                                    >
                                        CW DESACTIVADO
                                    </span>

                                <?php
                                endif;
                                ?>


                            </div>



                            <div
                                class="alert alert-info py-2"
                            >

                                <strong>
                                    Indicativo:
                                </strong>

                                <?= htmlspecialchars(
                                    $cw_config[
                                        "CALLSIGN"
                                    ]
                                    ?: 'Sin configurar'
                                ); ?>

                            </div>



                            <form
                                method="post"
                                action="includes/save-svx.php"
                            >


                                <!-- Parámetros deben quedar activos -->

                                <input
                                    type="hidden"
                                    name="enable_SimplexLogic_SHORT_IDENT_INTERVAL"
                                    value="1"
                                >

                                <input
                                    type="hidden"
                                    name="enable_SimplexLogic_LONG_IDENT_INTERVAL"
                                    value="1"
                                >

                                <input
                                    type="hidden"
                                    name="enable_SimplexLogic_SHORT_VOICE_ID_ENABLE"
                                    value="1"
                                >

                                <input
                                    type="hidden"
                                    name="enable_SimplexLogic_LONG_VOICE_ID_ENABLE"
                                    value="1"
                                >

                                <input
                                    type="hidden"
                                    name="enable_SimplexLogic_SHORT_CW_ID_ENABLE"
                                    value="1"
                                >

                                <input
                                    type="hidden"
                                    name="enable_SimplexLogic_LONG_CW_ID_ENABLE"
                                    value="1"
                                >

                                <input
                                    type="hidden"
                                    name="enable_SimplexLogic_CW_AMP"
                                    value="1"
                                >

                                <input
                                    type="hidden"
                                    name="enable_SimplexLogic_CW_PITCH"
                                    value="1"
                                >

                                <input
                                    type="hidden"
                                    name="enable_SimplexLogic_CW_WPM"
                                    value="1"
                                >



                                <!-- =====================================
                                     IDENTIFICACION CORTA
                                ====================================== -->

                                <div
                                    class="border rounded p-3 mb-3"
                                >


                                    <h5
                                        class="mb-3"
                                    >
                                        ⏱️ Identificación corta
                                    </h5>


                                    <div
                                        class="row g-3 align-items-end"
                                    >


                                        <div
                                            class="col-6 col-md-3"
                                        >


                                            <input
                                                type="hidden"
                                                name="SimplexLogic_SHORT_VOICE_ID_ENABLE"
                                                value="0"
                                            >


                                            <div
                                                class="form-check form-switch"
                                            >


                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="SimplexLogic_SHORT_VOICE_ID_ENABLE"
                                                    id="cw_short_voice"
                                                    value="1"

                                                    <?= (
                                                        $cw_config[
                                                            "SHORT_VOICE_ID_ENABLE"
                                                        ]
                                                        ===
                                                        '1'
                                                    )
                                                        ? 'checked'
                                                        : '';
                                                    ?>
                                                >


                                                <label
                                                    class="form-check-label"
                                                    for="cw_short_voice"
                                                >
                                                    🔊 Voz
                                                </label>


                                            </div>


                                        </div>



                                        <div
                                            class="col-6 col-md-3"
                                        >


                                            <input
                                                type="hidden"
                                                name="SimplexLogic_SHORT_CW_ID_ENABLE"
                                                value="0"
                                            >


                                            <div
                                                class="form-check form-switch"
                                            >


                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="SimplexLogic_SHORT_CW_ID_ENABLE"
                                                    id="cw_short"
                                                    value="1"

                                                    <?= (
                                                        $cw_config[
                                                            "SHORT_CW_ID_ENABLE"
                                                        ]
                                                        ===
                                                        '1'
                                                    )
                                                        ? 'checked'
                                                        : '';
                                                    ?>
                                                >


                                                <label
                                                    class="form-check-label"
                                                    for="cw_short"
                                                >
                                                    📡 CW
                                                </label>


                                            </div>


                                        </div>



                                        <div
                                            class="col-12 col-md-6"
                                        >


                                            <label
                                                for="cw_short_interval"
                                                class="form-label"
                                            >
                                                Intervalo
                                            </label>


                                            <div
                                                class="input-group"
                                            >


                                                <input
                                                    type="number"
                                                    class="form-control"
                                                    id="cw_short_interval"
                                                    name="SimplexLogic_SHORT_IDENT_INTERVAL"
                                                    min="0"
                                                    step="1"

                                                    value="<?= htmlspecialchars(
                                                        $cw_config[
                                                            "SHORT_IDENT_INTERVAL"
                                                        ]
                                                    ); ?>"

                                                    required
                                                >


                                                <span
                                                    class="input-group-text"
                                                >
                                                    minutos
                                                </span>


                                            </div>


                                        </div>


                                    </div>


                                </div>



                                <!-- =====================================
                                     IDENTIFICACION LARGA
                                ====================================== -->

                                <div
                                    class="border rounded p-3 mb-3"
                                >


                                    <h5
                                        class="mb-3"
                                    >
                                        ⏱️ Identificación larga
                                    </h5>


                                    <div
                                        class="row g-3 align-items-end"
                                    >


                                        <div
                                            class="col-6 col-md-3"
                                        >


                                            <input
                                                type="hidden"
                                                name="SimplexLogic_LONG_VOICE_ID_ENABLE"
                                                value="0"
                                            >


                                            <div
                                                class="form-check form-switch"
                                            >


                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="SimplexLogic_LONG_VOICE_ID_ENABLE"
                                                    id="cw_long_voice"
                                                    value="1"

                                                    <?= (
                                                        $cw_config[
                                                            "LONG_VOICE_ID_ENABLE"
                                                        ]
                                                        ===
                                                        '1'
                                                    )
                                                        ? 'checked'
                                                        : '';
                                                    ?>
                                                >


                                                <label
                                                    class="form-check-label"
                                                    for="cw_long_voice"
                                                >
                                                    🔊 Voz
                                                </label>


                                            </div>


                                        </div>



                                        <div
                                            class="col-6 col-md-3"
                                        >


                                            <input
                                                type="hidden"
                                                name="SimplexLogic_LONG_CW_ID_ENABLE"
                                                value="0"
                                            >


                                            <div
                                                class="form-check form-switch"
                                            >


                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    name="SimplexLogic_LONG_CW_ID_ENABLE"
                                                    id="cw_long"
                                                    value="1"

                                                    <?= (
                                                        $cw_config[
                                                            "LONG_CW_ID_ENABLE"
                                                        ]
                                                        ===
                                                        '1'
                                                    )
                                                        ? 'checked'
                                                        : '';
                                                    ?>
                                                >


                                                <label
                                                    class="form-check-label"
                                                    for="cw_long"
                                                >
                                                    📡 CW
                                                </label>


                                            </div>


                                        </div>



                                        <div
                                            class="col-12 col-md-6"
                                        >


                                            <label
                                                for="cw_long_interval"
                                                class="form-label"
                                            >
                                                Intervalo
                                            </label>


                                            <div
                                                class="input-group"
                                            >


                                                <input
                                                    type="number"
                                                    class="form-control"
                                                    id="cw_long_interval"
                                                    name="SimplexLogic_LONG_IDENT_INTERVAL"
                                                    min="0"
                                                    step="1"

                                                    value="<?= htmlspecialchars(
                                                        $cw_config[
                                                            "LONG_IDENT_INTERVAL"
                                                        ]
                                                    ); ?>"

                                                    required
                                                >


                                                <span
                                                    class="input-group-text"
                                                >
                                                    minutos
                                                </span>


                                            </div>


                                        </div>


                                    </div>


                                </div>



                                <!-- =====================================
                                     CONFIGURACION MORSE
                                ====================================== -->

                                <div
                                    class="border rounded p-3 mb-3"
                                >


                                    <h5
                                        class="mb-3"
                                    >
                                        🎛️ Configuración Morse
                                    </h5>


                                    <div
                                        class="row g-3"
                                    >


                                        <div
                                            class="col-12 col-md-4"
                                        >


                                            <label
                                                for="cw_wpm"
                                                class="form-label"
                                            >
                                                Velocidad
                                            </label>


                                            <div
                                                class="input-group"
                                            >


                                                <input
                                                    type="number"
                                                    class="form-control"
                                                    id="cw_wpm"
                                                    name="SimplexLogic_CW_WPM"
                                                    min="1"
                                                    max="60"

                                                    value="<?= htmlspecialchars(
                                                        $cw_config[
                                                            "CW_WPM"
                                                        ]
                                                    ); ?>"

                                                    required
                                                >


                                                <span
                                                    class="input-group-text"
                                                >
                                                    WPM
                                                </span>


                                            </div>


                                        </div>



                                        <div
                                            class="col-12 col-md-4"
                                        >


                                            <label
                                                for="cw_pitch"
                                                class="form-label"
                                            >
                                                Tono
                                            </label>


                                            <div
                                                class="input-group"
                                            >


                                                <input
                                                    type="number"
                                                    class="form-control"
                                                    id="cw_pitch"
                                                    name="SimplexLogic_CW_PITCH"
                                                    min="100"
                                                    max="3000"

                                                    value="<?= htmlspecialchars(
                                                        $cw_config[
                                                            "CW_PITCH"
                                                        ]
                                                    ); ?>"

                                                    required
                                                >


                                                <span
                                                    class="input-group-text"
                                                >
                                                    Hz
                                                </span>


                                            </div>


                                        </div>



                                        <div
                                            class="col-12 col-md-4"
                                        >


                                            <label
                                                for="cw_amp"
                                                class="form-label"
                                            >
                                                Nivel
                                            </label>


                                            <div
                                                class="input-group"
                                            >


                                                <input
                                                    type="number"
                                                    class="form-control"
                                                    id="cw_amp"
                                                    name="SimplexLogic_CW_AMP"
                                                    min="-30"
                                                    max="0"

                                                    value="<?= htmlspecialchars(
                                                        $cw_config[
                                                            "CW_AMP"
                                                        ]
                                                    ); ?>"

                                                    required
                                                >


                                                <span
                                                    class="input-group-text"
                                                >
                                                    dB
                                                </span>


                                            </div>


                                        </div>


                                    </div>


                                </div>



                                <div
                                    class="alert alert-secondary py-2"
                                >

                                    <small>

                                        Si estos parámetros todavía
                                        no existen en
                                        <strong>svxlink.conf</strong>,
                                        AUROXLINK los creará
                                        automáticamente dentro de
                                        <strong>[SimplexLogic]</strong>.
					<strong>Compatibilidad: SvxLink 1.7.0 (19.09)+</strong>.

                                    </small>

                                </div>



                                <div
                                    class="d-grid"
                                >

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        💾 Guardar configuración CW
                                    </button>

                                </div>


                            </form>


                        </div>


                    </div>



                    <!-- =================================================
                         AUDIO
                    ================================================== -->

                    <div
                        class="card shadow-sm mb-4"
                    >


                        <div
                            class="card-body"
                        >


                            <h2
                                class="fs-4 titulo mb-4"
                            >
                                🎛️
                                <?= t(
                                    'settings_audio_control',
                                    'Audio Control'
                                ); ?>

                                <?= htmlspecialchars(
                                    $titleSite
                                ); ?>
                            </h2>



                            <?php
                            if (
                                $audio_guardado_msg
                            ):
                            ?>

                                <div
                                    class="alert alert-info"
                                >
                                    <?= htmlspecialchars(
                                        $audio_guardado_msg
                                    ); ?>
                                </div>

                            <?php
                            endif;
                            ?>



                            <form
                                method="post"
                            >


                                <div
                                    class="mb-4"
                                >


                                    <label
                                        for="tarjeta"
                                        class="form-label fw-semibold"
                                    >

                                        <?= t(
                                            'settings_select_sound_card',
                                            'Select Audio Card'
                                        ); ?>:

                                    </label>



                                    <select
                                        class="form-select"
                                        name="tarjeta"
                                        id="tarjeta"
                                        onchange="this.form.submit()"
                                    >


                                        <?php
                                        foreach (
                                            $tarjetas
                                            as $t
                                        ):
                                        ?>

                                            <option

                                                value="<?= htmlspecialchars(
                                                    $t[
                                                        'numero'
                                                    ]
                                                ); ?>"

                                                <?= (
                                                    $t[
                                                        'numero'
                                                    ]
                                                    ==
                                                    $tarjeta_seleccionada
                                                )
                                                    ? 'selected'
                                                    : '';
                                                ?>
                                            >

                                                <?= htmlspecialchars(
                                                    "Card {$t['numero']}: {$t['nombre']} [{$t['descripcion']}]"
                                                ); ?>

                                            </option>

                                        <?php
                                        endforeach;
                                        ?>


                                    </select>


                                </div>



                                <div
                                    class="row g-3"
                                >


                                    <?php

                                    foreach (
                                        $controles
                                        as $control
                                    ):

                                        $estado =
                                            obtenerEstadoControl(
                                                $tarjeta_seleccionada,
                                                $control
                                            );

                                    ?>


                                        <div
                                            class="col-12 col-lg-6"
                                        >


                                            <div
                                                class="border rounded p-3 h-100"
                                            >


                                                <label
                                                    class="form-label fw-semibold"
                                                >

                                                    <?= t(
                                                        'settings_control',
                                                        'Control'
                                                    ); ?>:

                                                    <?= htmlspecialchars(
                                                        $control
                                                    ); ?>

                                                </label>



                                                <?php
                                                if (
                                                    is_array(
                                                        $estado
                                                    )
                                                ):
                                                ?>


                                                    <input
                                                        type="range"
                                                        class="form-range"

                                                        name="<?= htmlspecialchars(
                                                            str_replace(
                                                                ' ',
                                                                '_',
                                                                $control
                                                            )
                                                        ); ?>"

                                                        min="0"
                                                        max="100"

                                                        value="<?= htmlspecialchars(
                                                            $estado[
                                                                0
                                                            ]
                                                        ); ?>"
                                                    >


                                                    <small
                                                        class="text-muted"
                                                    >

                                                        <?= t(
                                                            'settings_current_value',
                                                            'Current value'
                                                        ); ?>:

                                                        <?= htmlspecialchars(
                                                            $estado[
                                                                0
                                                            ]
                                                        ); ?>%

                                                    </small>



                                                <?php
                                                elseif (
                                                    in_array(
                                                        $estado,
                                                        [
                                                            'on',
                                                            'off'
                                                        ]
                                                    )
                                                ):
                                                ?>


                                                    <select
                                                        class="form-select"

                                                        name="<?= htmlspecialchars(
                                                            str_replace(
                                                                ' ',
                                                                '_',
                                                                $control
                                                            )
                                                        ); ?>"
                                                    >


                                                        <option
                                                            value="on"

                                                            <?= (
                                                                $estado
                                                                ===
                                                                'on'
                                                            )
                                                                ? 'selected'
                                                                : '';
                                                            ?>
                                                        >
                                                            <?= t(
                                                                'enabled',
                                                                'Enabled'
                                                            ); ?>
                                                        </option>


                                                        <option
                                                            value="off"

                                                            <?= (
                                                                $estado
                                                                ===
                                                                'off'
                                                            )
                                                                ? 'selected'
                                                                : '';
                                                            ?>
                                                        >
                                                            <?= t(
                                                                'disabled',
                                                                'Disabled'
                                                            ); ?>
                                                        </option>


                                                    </select>


                                                <?php
                                                endif;
                                                ?>


                                            </div>


                                        </div>


                                    <?php
                                    endforeach;
                                    ?>


                                </div>



                                <div
                                    class="d-grid gap-2 mt-4"
                                >


                                    <button
                                        type="submit"
                                        name="aplicar"
                                        class="btn btn-success"
                                    >

                                        <?= t(
                                            'settings_apply_changes',
                                            'Apply Changes'
                                        ); ?>

                                    </button>


                                    <button
                                        type="submit"
                                        name="guardar_audio"
                                        class="btn btn-secondary"
                                    >

                                        💾
                                        <?= t(
                                            'settings_save_audio_config',
                                            'Save audio settings'
                                        ); ?>

                                    </button>


                                </div>


                            </form>


                        </div>


                    </div>



                    <!-- =================================================
                         ACTUALIZADOR
                    ================================================== -->

                    <?php

                    require
                        __DIR__
                        .
                        '/includes/svxlink_update_panel.php';

                    ?>


                </div>



                <!-- =====================================================
                     COLUMNA DERECHA
                ====================================================== -->

                <div
                    class="col-12 col-xl-6"
                >



                    <!-- =================================================
                         SVXLINK.CONF
                    ================================================== -->

                    <div
                        class="card shadow-sm mb-4"
                    >


                        <div
                            class="card-body"
                        >


                            <div
                                class="mb-4"
                            >


                                <h2
                                    class="fs-4 titulo mb-1"
                                >

                                    ⚙️

                                    <?= t(
                                        'settings_svxlink_conf',
                                        'svxlink.conf Settings'
                                    ); ?>

                                </h2>


                                <small
                                    class="text-muted"
                                >
                                    /etc/svxlink/svxlink.conf
                                </small>


                            </div>



                            <?php

                            $meta_secciones = [

                                "[GLOBAL]" => [

                                    "icono" =>
                                        "🌐",

                                    "titulo" =>
                                        "GLOBAL",

                                    "descripcion" =>
                                        "Parámetros generales de SvxLink"

                                ],


                                "[SimplexLogic]" => [

                                    "icono" =>
                                        "📻",

                                    "titulo" =>
                                        "SimplexLogic",

                                    "descripcion" =>
                                        "Configuración principal de la lógica de radio"

                                ],


                                "[Rx1]" => [

                                    "icono" =>
                                        "🎙️",

                                    "titulo" =>
                                        "Receptor RX1",

                                    "descripcion" =>
                                        "Audio, squelch y control del receptor"

                                ],


                                "[Tx1]" => [

                                    "icono" =>
                                        "📡",

                                    "titulo" =>
                                        "Transmisor TX1",

                                    "descripcion" =>
                                        "Audio y control PTT del transmisor"

                                ],


                                "[LocationInfo]" => [

                                    "icono" =>
                                        "📍",

                                    "titulo" =>
                                        "LocationInfo",

                                    "descripcion" =>
                                        "Datos APRS, ubicación y características de la estación"

                                ]

                            ];

                            ?>



                            <form
                                method="post"
                                action="includes/save-svx.php"
                            >


                                <?php

                                foreach (
                                    $parametros_svxlink
                                    as $seccion =>
                                    $claves_s
                                ):

                                ?>


                                    <?php
                                    if (
                                        !empty(
                                            $claves_s
                                        )
                                    ):
                                    ?>


                                        <?php

                                        $meta =
                                            $meta_secciones[
                                                $seccion
                                            ]
                                            ??
                                            [

                                                "icono" =>
                                                    "⚙️",

                                                "titulo" =>
                                                    trim(
                                                        $seccion,
                                                        "[]"
                                                    ),

                                                "descripcion" =>
                                                    ""

                                            ];

                                        ?>



                                        <div
                                            class="border rounded p-3 mb-4"
                                        >


                                            <div
                                                class="mb-3"
                                            >


                                                <h5
                                                    class="mb-1"
                                                >

                                                    <?= $meta[
                                                        "icono"
                                                    ]; ?>

                                                    <?= htmlspecialchars(
                                                        $meta[
                                                            "titulo"
                                                        ]
                                                    ); ?>

                                                </h5>



                                                <?php
                                                if (
                                                    !empty(
                                                        $meta[
                                                            "descripcion"
                                                        ]
                                                    )
                                                ):
                                                ?>


                                                    <small
                                                        class="text-muted"
                                                    >

                                                        <?= htmlspecialchars(
                                                            $meta[
                                                                "descripcion"
                                                            ]
                                                        ); ?>

                                                    </small>


                                                <?php
                                                endif;
                                                ?>


                                            </div>



                                            <div
                                                class="row g-3"
                                            >


                                                <?php

                                                foreach (
                                                    $claves_s
                                                    as $clave_s
                                                ):

                                                ?>


                                                    <?php

                                                    $nombreCampo =
                                                        str_replace(
                                                            [
                                                                '[',
                                                                ']'
                                                            ],
                                                            '',
                                                            $seccion
                                                        )
                                                        .
                                                        "_"
                                                        .
                                                        $clave_s;


                                                    $activo =

                                                        $valores_svxlink[
                                                            "_enabled_{$seccion}_{$clave_s}"
                                                        ]

                                                        ??
                                                        true;


                                                    $valor =

                                                        $valores_svxlink[
                                                            $seccion
                                                        ][
                                                            $clave_s
                                                        ]

                                                        ??
                                                        '';


                                                    $anchoCompleto =

                                                        in_array(
                                                            $clave_s,
                                                            [
                                                                "APRS_SERVER_LIST",
                                                                "PATH",
                                                                "COMMENT"
                                                            ]
                                                        );


                                                    $columna =

                                                        $anchoCompleto

                                                        ? "col-12"

                                                        : "col-12 col-lg-6";

                                                    ?>



                                                    <div
                                                        class="<?= $columna; ?>"
                                                    >


                                                        <div
                                                            class="border rounded p-3 h-100"
                                                        >


                                                            <div
                                                                class="d-flex justify-content-between align-items-center gap-2 mb-2"
                                                            >


                                                                <label
                                                                    class="form-label fw-semibold mb-0"
                                                                    for="<?= $nombreCampo; ?>"
                                                                >

                                                                    <?= htmlspecialchars(
                                                                        $clave_s
                                                                    ); ?>

                                                                </label>



                                                                <div
                                                                    class="form-check form-switch m-0"
                                                                >


                                                                    <input
                                                                        class="form-check-input"
                                                                        type="checkbox"

                                                                        name="enable_<?= $nombreCampo; ?>"

                                                                        id="enable_<?= $nombreCampo; ?>"

                                                                        <?= $activo
                                                                            ? 'checked'
                                                                            : '';
                                                                        ?>
                                                                    >


                                                                    <label
                                                                        class="form-check-label small"

                                                                        for="enable_<?= $nombreCampo; ?>"
                                                                    >

                                                                        <?= t(
                                                                            'active',
                                                                            'Active'
                                                                        ); ?>

                                                                    </label>


                                                                </div>


                                                            </div>



                                                            <input
                                                                type="text"
                                                                class="form-control"

                                                                name="<?= $nombreCampo; ?>"

                                                                id="<?= $nombreCampo; ?>"

                                                                value="<?= htmlspecialchars(
                                                                    $valor
                                                                ); ?>"
                                                            >


                                                        </div>


                                                    </div>


                                                <?php
                                                endforeach;
                                                ?>


                                            </div>


                                        </div>


                                    <?php
                                    endif;
                                    ?>


                                <?php
                                endforeach;
                                ?>



                                <div
                                    class="d-grid"
                                >


                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >

                                        💾
                                        <?= t(
                                            'save_changes',
                                            'Save changes'
                                        ); ?>

                                    </button>


                                </div>


                            </form>


                        </div>


                    </div>


                </div>


            </div>


        </div>


    </div>


</div>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
></script>


<script>

function togglePassword(id)
{
    const input =
        document.getElementById(id);

    input.type =
        (input.type === 'password')
        ? 'text'
        : 'password';
}

</script>


</body>

</html>
