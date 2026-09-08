<?php

$archivo = "/etc/svxlink/svxlink.conf";
$logfile = __DIR__ . "/logs/svxlink_config_log.txt";

/*
 * Parámetros que AUROXLINK puede modificar.
 * Esto evita que un POST manipulado pueda insertar cualquier
 * parámetro arbitrario en svxlink.conf.
 */
$parametros_permitidos = [
    "GLOBAL" => [
        "LOCATION_INFO"
    ],

    "SimplexLogic" => [
        "CALLSIGN",

        // Identificación
        "SHORT_IDENT_INTERVAL",
        "LONG_IDENT_INTERVAL",

        // Identificación por voz
        "SHORT_VOICE_ID_ENABLE",
        "LONG_VOICE_ID_ENABLE",

        // Identificación CW
        "SHORT_CW_ID_ENABLE",
        "LONG_CW_ID_ENABLE",
        "CW_AMP",
        "CW_PITCH",
        "CW_WPM"
    ],

    "Rx1" => [
        "AUDIO_DEV",
        "SQL_DET",
        "SQL_START_DELAY",
        "SQL_DELAY",
        "PREAMP",
        "SQL_HANGTIME",
        "SERIAL_PORT",
        "SERIAL_PIN"
    ],

    "Tx1" => [
        "AUDIO_DEV",
        "PTT_TYPE",
        "PTT_PORT",
        "PTT_PIN"
    ],

    "LocationInfo" => [
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


function responderError($mensaje)
{
    echo "<div style='padding:20px;font-family:sans-serif;'>";
    echo "❌ " . htmlspecialchars($mensaje);
    echo "<br><br><a href='../settings.php'>Volver a configuración de SvxLink</a>";
    echo "</div>";
    exit;
}


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderError("Acceso no permitido.");
}


if (!file_exists($archivo)) {
    responderError("No existe /etc/svxlink/svxlink.conf");
}


if (!is_readable($archivo)) {
    responderError("No se puede leer svxlink.conf");
}


/*
 * Construir lista de cambios solicitados por la interfaz.
 */
$cambios_solicitados = [];

foreach ($parametros_permitidos as $seccion => $claves) {

    foreach ($claves as $clave) {

        $nombre = $seccion . "_" . $clave;
        $nombre_checkbox = "enable_" . $nombre;

        /*
         * Solo procesar parámetros efectivamente enviados por
         * el formulario.
         */
        if (!array_key_exists($nombre, $_POST)) {
            continue;
        }

        $valor = trim((string) $_POST[$nombre]);

        /*
         * Evitar saltos de línea que puedan alterar el archivo.
         */
        $valor = str_replace(["\r", "\n"], "", $valor);

        $activo = isset($_POST[$nombre_checkbox]);

        $cambios_solicitados[$seccion][$clave] = [
            "valor" => $valor,
            "activo" => $activo
        ];
    }
}


/*
 * Nada que modificar.
 */
if (empty($cambios_solicitados)) {
    responderError("No se recibieron parámetros para modificar.");
}


/*
 * Validaciones específicas CW.
 */
if (isset($cambios_solicitados["SimplexLogic"])) {

    $sl = $cambios_solicitados["SimplexLogic"];

    $booleanos = [
        "SHORT_VOICE_ID_ENABLE",
        "LONG_VOICE_ID_ENABLE",
        "SHORT_CW_ID_ENABLE",
        "LONG_CW_ID_ENABLE"
    ];

    foreach ($booleanos as $clave) {

        if (
            isset($sl[$clave]) &&
            !in_array($sl[$clave]["valor"], ["0", "1"], true)
        ) {
            responderError("$clave solamente puede ser 0 o 1.");
        }
    }


    foreach (["SHORT_IDENT_INTERVAL", "LONG_IDENT_INTERVAL"] as $clave) {

        if (
            isset($sl[$clave]) &&
            !preg_match('/^\d+$/', $sl[$clave]["valor"])
        ) {
            responderError("$clave debe ser un número entero.");
        }
    }


    if (
        isset($sl["CW_WPM"]) &&
        (
            !is_numeric($sl["CW_WPM"]["valor"]) ||
            (float)$sl["CW_WPM"]["valor"] <= 0
        )
    ) {
        responderError("CW_WPM debe ser mayor que 0.");
    }


    if (
        isset($sl["CW_PITCH"]) &&
        (
            !is_numeric($sl["CW_PITCH"]["valor"]) ||
            (float)$sl["CW_PITCH"]["valor"] <= 0
        )
    ) {
        responderError("CW_PITCH debe ser mayor que 0.");
    }


    if (
        isset($sl["CW_AMP"]) &&
        !is_numeric($sl["CW_AMP"]["valor"])
    ) {
        responderError("CW_AMP debe ser un valor numérico.");
    }


    /*
     * Validar relación intervalo corto / largo cuando ambos
     * son enviados por el formulario.
     */
    if (
        isset($sl["SHORT_IDENT_INTERVAL"]) &&
        isset($sl["LONG_IDENT_INTERVAL"])
    ) {

        $short = (int)$sl["SHORT_IDENT_INTERVAL"]["valor"];
        $long  = (int)$sl["LONG_IDENT_INTERVAL"]["valor"];

        if (
            $short > 0 &&
            $long > 0 &&
            ($long % $short) !== 0
        ) {
            responderError(
                "LONG_IDENT_INTERVAL debe ser múltiplo de SHORT_IDENT_INTERVAL."
            );
        }
    }
}


/*
 * Leer configuración.
 */
$lineas = file($archivo);

if ($lineas === false) {
    responderError("No se pudo leer svxlink.conf");
}


$nuevas_lineas = [];
$cambios_log = [];
$bloque_actual = null;
$encontrados = [];


/*
 * Insertar parámetros que no estaban originalmente presentes
 * antes de abandonar una sección.
 */
function insertarFaltantes(
    &$nuevas_lineas,
    $seccion,
    &$cambios_solicitados,
    &$encontrados,
    &$cambios_log
) {

    if (
        !$seccion ||
        !isset($cambios_solicitados[$seccion])
    ) {
        return;
    }

    foreach ($cambios_solicitados[$seccion] as $clave => $datos) {

        if (isset($encontrados[$seccion][$clave])) {
            continue;
        }

        $prefijo = $datos["activo"] ? "" : "#";
        $valor = $datos["valor"];

        $nuevas_lineas[] = "{$prefijo}{$clave}={$valor}\n";

        $estado = $datos["activo"] ? "✅" : "❌";

        $cambios_log[] =
            "{$estado} [{$seccion}] {$clave} -> {$valor} (NUEVO)";

        $encontrados[$seccion][$clave] = true;
    }
}


/*
 * Procesar archivo línea por línea.
 */
foreach ($lineas as $linea) {

    $linea_trim = trim($linea);

    /*
     * Inicio de nueva sección.
     */
    if (preg_match('/^\[(.*)\]$/', $linea_trim, $match)) {

        /*
         * Antes de cambiar de sección, insertar parámetros
         * pendientes en la sección anterior.
         */
        insertarFaltantes(
            $nuevas_lineas,
            $bloque_actual,
            $cambios_solicitados,
            $encontrados,
            $cambios_log
        );

        $bloque_actual = $match[1];

        $nuevas_lineas[] = $linea;

        continue;
    }


    /*
     * Detectar KEY=VALUE o #KEY=VALUE.
     */
    if (
        $bloque_actual &&
        preg_match(
            '/^\s*(#?)\s*([A-Z0-9_]+)\s*=\s*(.*)$/i',
            $linea_trim,
            $match
        )
    ) {

        $clave = strtoupper($match[2]);

        if (
            isset($cambios_solicitados[$bloque_actual][$clave])
        ) {

            $datos = $cambios_solicitados[$bloque_actual][$clave];

            $prefijo = $datos["activo"] ? "" : "#";
            $valor = $datos["valor"];

            $nuevas_lineas[] =
                "{$prefijo}{$clave}={$valor}\n";

            $estado = $datos["activo"] ? "✅" : "❌";

            $cambios_log[] =
                "{$estado} [{$bloque_actual}] {$clave} -> {$valor}";

            $encontrados[$bloque_actual][$clave] = true;

            continue;
        }
    }


    /*
     * Línea no modificada.
     */
    $nuevas_lineas[] = $linea;
}


/*
 * Insertar parámetros faltantes de la última sección.
 */
insertarFaltantes(
    $nuevas_lineas,
    $bloque_actual,
    $cambios_solicitados,
    $encontrados,
    $cambios_log
);


/*
 * Verificar que las secciones solicitadas realmente existían.
 */
foreach ($cambios_solicitados as $seccion => $claves) {

    foreach ($claves as $clave => $datos) {

        if (!isset($encontrados[$seccion][$clave])) {

            responderError(
                "No se encontró la sección [$seccion] en svxlink.conf."
            );
        }
    }
}


/*
 * Backup con fecha.
 */
$timestamp = date("Ymd_His");

$backup_dir = __DIR__ . "/backups";

if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0750, true)) {
        responderError("No fue posible crear el directorio de respaldos.");
    }
}

$backup = $backup_dir . "/svxlink.conf.bak_" . $timestamp;

if (!copy($archivo, $backup)) {
    responderError("No fue posible crear respaldo de svxlink.conf.");
}


/*
 * Guardar nuevo archivo.
 */
$resultado = file_put_contents(
    $archivo,
    implode("", $nuevas_lineas)
);

if ($resultado === false) {

    copy($backup, $archivo);

    responderError(
        "No fue posible guardar svxlink.conf."
    );
}


/*
 * Registrar cambios.
 */
if (!is_dir(__DIR__ . "/logs")) {
    mkdir(__DIR__ . "/logs", 0775, true);
}

$registro =
    date("Y-m-d H:i:s") .
    " - Cambios SVX:\n" .
    implode("\n", $cambios_log) .
    "\n---\n";

file_put_contents(
    $logfile,
    $registro,
    FILE_APPEND
);


/*
 * Reiniciar SvxLink.
 */
exec(
    "sudo systemctl restart svxlink 2>&1",
    $salida_restart,
    $codigo_restart
);


/*
 * Comprobar que realmente quedó funcionando.
 */
sleep(2);

exec(
    "sudo systemctl is-active --quiet svxlink",
    $salida_estado,
    $codigo_estado
);


if ($codigo_restart !== 0 || $codigo_estado !== 0) {

    /*
     * Rollback automático.
     */
    copy($backup, $archivo);

    exec(
        "sudo systemctl restart svxlink 2>&1",
        $salida_rollback,
        $codigo_rollback
    );

    responderError(
        "SvxLink no pudo iniciar con la nueva configuración. " .
        "AUROXLINK restauró automáticamente el respaldo anterior."
    );
}


echo "
<div style='padding:20px;font-family:sans-serif;'>
    ✅ Configuración guardada correctamente.
    <br>
    ✅ SvxLink reiniciado.
    <br>
    ✅ Servicio operativo.
    <br><br>
    <a href='../settings.php'>Volver a configuración de SvxLink</a>
</div>
";

exit;

?>
