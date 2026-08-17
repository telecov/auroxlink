<?php
$archivo = __DIR__ . '/data/eventos.json';
$input = json_decode(file_get_contents('php://input'), true);
$index = $input['index'] ?? -1;

if ($index >= 0 && file_exists($archivo)) {
    $eventos = json_decode(file_get_contents($archivo), true);
    array_splice($eventos, $index, 1);
    file_put_contents($archivo, json_encode($eventos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Evento eliminado correctamente";
} else {
    http_response_code(400);
    echo "Índice inválido";
}
