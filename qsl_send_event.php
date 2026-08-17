<?php
date_default_timezone_set('America/Santiago');

$archivo = __DIR__ . '/data/eventos.json';
$datosJSON = file_get_contents('php://input');
$datos = json_decode($datosJSON, true);

/* =========================================================
   HELPERS
========================================================= */
function leerJsonSeguro($ruta, $default = [])
{
    if (!file_exists($ruta)) {
        return $default;
    }

    $contenido = file_get_contents($ruta);
    if ($contenido === false || trim($contenido) === '') {
        return $default;
    }

    $json = json_decode($contenido, true);
    return is_array($json) ? $json : $default;
}

function guardarJsonSeguro($ruta, $datos)
{
    $directorio = dirname($ruta);

    if (!is_dir($directorio)) {
        mkdir($directorio, 0775, true);
    }

    return file_put_contents(
        $ruta,
        json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
}

/* =========================================================
   VALIDAR DATOS
========================================================= */
if (!is_array($datos) || !isset($datos['titulo'], $datos['fecha'], $datos['hora'])) {
    http_response_code(400);
    echo "Datos incompletos";
    exit;
}

$titulo     = trim($datos['titulo'] ?? '');
$fecha      = trim($datos['fecha'] ?? '');
$hora       = trim($datos['hora'] ?? '');
$lugar      = trim($datos['lugar'] ?? '');
$frecuencia = trim($datos['frecuencia'] ?? '');
$modo       = trim($datos['modo'] ?? '');
$qsl        = trim($datos['qsl'] ?? 'No');
$mensaje    = trim($datos['mensaje'] ?? '');

if ($titulo === '' || $fecha === '' || $hora === '') {
    http_response_code(400);
    echo "Datos incompletos";
    exit;
}

/* =========================================================
   GUARDAR EVENTO EN eventos.json
========================================================= */
$eventos = leerJsonSeguro($archivo, []);

$nuevoEvento = [
    'titulo'          => $titulo,
    'fecha'           => $fecha,
    'hora'            => $hora,
    'lugar'           => $lugar,
    'frecuencia'      => $frecuencia,
    'modo'            => $modo,
    'qsl'             => $qsl,
    'mensaje'         => $mensaje,
    'fecha_registro'  => date('Y-m-d H:i:s')
];

$eventos[] = $nuevoEvento;

if (!guardarJsonSeguro($archivo, $eventos)) {
    http_response_code(500);
    echo "No se pudo guardar el evento";
    exit;
}

/* =========================================================
   CREAR ACTIVIDAD ACTIVA PARA activity_log.php
   SOLO SI NO EXISTE UNA ACTIVIDAD YA ACTIVA
========================================================= */
$dataDir = __DIR__ . '/data_actividades';
$activeFile = $dataDir . '/actividad_activa.json';

if (!file_exists($activeFile)) {
    $actividadActiva = [
        'actividad' => [
            'nombre'           => $titulo,
            'tipo'             => ($modo !== '' ? $modo : 'Actividad radial'),
            'modulo'           => ($frecuencia !== '' ? $frecuencia : ($lugar !== '' ? $lugar : 'General')),
            'descripcion'      => $mensaje,
            'fecha_programada' => $fecha . ' ' . $hora,
            'fecha_evento'     => $fecha,
            'hora_evento'      => $hora,
            'lugar'            => $lugar,
            'frecuencia'       => $frecuencia,
            'modo'             => $modo,
            'qsl'              => $qsl,
            'fecha_inicio'     => date('Y-m-d H:i:s'),
            'estado'           => 'active',
            'origen'           => 'qsl_generator'
        ],
        'participantes' => []
    ];

    guardarJsonSeguro($activeFile, $actividadActiva);
}

/* =========================================================
   ENVIAR TELEGRAM
========================================================= */
$conf_file = __DIR__ . '/telegram_config.json';

if (file_exists($conf_file)) {
    $conf = json_decode(file_get_contents($conf_file), true);
    $token = $conf['token'] ?? '';
    $chat_id = $conf['chat_id'] ?? '';

    if ($token && $chat_id) {
        $msg = "📡 *Nuevo Evento Registrado*\n";
        $msg .= "📌 *" . $titulo . "*\n";
        $msg .= "📅 " . $fecha . " ⏰ " . $hora . "\n";
        $msg .= "📍 " . ($lugar !== '' ? $lugar : '-') . "\n";
        $msg .= "📡 " . ($frecuencia !== '' ? $frecuencia : '-') . " | 🎙️ " . ($modo !== '' ? $modo : '-') . "\n";
        $msg .= "✉️ QSL: " . ($qsl !== '' ? $qsl : 'No');

        $url = "https://api.telegram.org/bot{$token}/sendMessage?chat_id={$chat_id}&text=" . urlencode($msg) . "&parse_mode=Markdown";
        @file_get_contents($url);
    }
}

echo "Evento registrado correctamente";
