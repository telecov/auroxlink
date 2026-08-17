<?php
require 'includes/environment.php';

$data = json_decode(file_get_contents("php://input"), true);
$imagen = $data['imagen'] ?? null;
$evento = $data['evento'] ?? 'No asociado';

if (!$imagen) {
    http_response_code(400);
    echo "❌ No se recibió imagen";
    exit;
}

$dir_img = __DIR__ . '/qsl';
$dir_data = __DIR__ . '/data';
$archivo_json = $dir_data . '/qsls.json';

// Crear carpetas si no existen
if (!is_dir($dir_img))
    mkdir($dir_img, 0775, true);
if (!is_dir($dir_data))
    mkdir($dir_data, 0775, true);

// Nombre de archivo
$nombre_archivo = 'qsl_' . date('Ymd_His') . '.png';
$ruta_completa = $dir_img . '/' . $nombre_archivo;

// Decodificar imagen
$imagen_binaria = base64_decode(preg_replace('#^data:image/\\w+;base64,#i', '', $imagen));
if (!$imagen_binaria) {
    http_response_code(500);
    echo "❌ Error al decodificar imagen";
    exit;
}

// Guardar imagen
file_put_contents($ruta_completa, $imagen_binaria);

// Guardar entrada en JSON
$registro = [
    'archivo' => $nombre_archivo,
    'evento' => $evento,
    'fecha' => date("Y-m-d"),
    'hora' => date("H:i:s")
];

$qsls = file_exists($archivo_json) ? json_decode(file_get_contents($archivo_json), true) : [];
$qsls[] = $registro;
file_put_contents($archivo_json, json_encode($qsls, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "QSL guardada: $nombre_archivo con evento: $evento";
