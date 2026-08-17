<?php
$file = basename($_GET['file'] ?? '');
$path = __DIR__ . "/data_actividades/historial/" . $file;

if ($file === '' || !file_exists($path)) {
    http_response_code(404);
    exit('File not found');
}

header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));

readfile($path);
exit;
