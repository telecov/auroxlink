<?php
$env_path = __DIR__ . '/includes/environment.php';
session_start();

if (file_exists($env_path)) {
    require $env_path;
} else {
    die('Archivo de configuración no encontrado.');
}

if (!isset($_SESSION['autenticado'])) {
    http_response_code(403);
    exit('Acceso denegado');
}

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

$styleFile = __DIR__ . '/estilos.json';
$telegramFile = __DIR__ . '/telegram_config.json';
$svxlinkFile = '/etc/svxlink/svxlink.conf';
$echolinkFile = '/etc/svxlink/svxlink.d/ModuleEchoLink.conf';

$backup = [
    'meta' => [
        'system' => 'AUROXLINK',
        'version' => $version ?? 'Desconocida',
        'generated_at' => date('Y-m-d H:i:s'),
        'format' => 'auroxlink-backup-v2'
    ],
    'estilos' => [],
    'telegram' => [],
    'svxlink_conf' => '',
    'echolink_conf' => ''
];

if (file_exists($styleFile)) {
    $backup['estilos'] = json_decode(file_get_contents($styleFile), true) ?? [];
}

if (file_exists($telegramFile)) {
    $backup['telegram'] = json_decode(file_get_contents($telegramFile), true) ?? [];
}

if (file_exists($svxlinkFile)) {
    $backup['svxlink_conf'] = file_get_contents($svxlinkFile);
}

if (file_exists($echolinkFile)) {
    $backup['echolink_conf'] = file_get_contents($echolinkFile);
}

$filename = 'auroxlink_backup_completo_' . date('Ymd_His') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
