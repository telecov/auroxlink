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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

$styleFile = __DIR__ . '/estilos.json';
$telegramFile = __DIR__ . '/telegram_config.json';
$svxlinkFile = '/etc/svxlink/svxlink.conf';
$echolinkFile = '/etc/svxlink/svxlink.d/ModuleEchoLink.conf';

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    die('❌ No se recibió el archivo de backup.');
}

$tmpFile = $_FILES['backup_file']['tmp_name'];
$content = file_get_contents($tmpFile);
$data = json_decode($content, true);

if (!is_array($data)) {
    die('❌ El archivo no contiene un JSON válido.');
}

if (($data['meta']['format'] ?? '') !== 'auroxlink-backup-v2') {
    die('❌ El archivo no corresponde a un backup válido de AUROXLINK.');
}

/* =========================
   RESTAURAR ESTILOS
========================= */
$estilos = $data['estilos'] ?? null;
if (is_array($estilos)) {
    $estilosPermitidos = [
        'nombre_zona',
        'radioaficionado',
        'modo',
        'frecuencia',
        'offset',
        'tono',
        'ubicacion',
        'aprs_web',
        'titulo_dashboard',
        'indicativo',
        'ciudad',
        'utc_offset',
        'idioma',
        'color_sidebar',
        'color_fondo',
        'color_titulo',
        'logo',
        'foto_admin'
    ];

    $estilosFiltrados = [];
    foreach ($estilosPermitidos as $campo) {
        if (array_key_exists($campo, $estilos)) {
            $estilosFiltrados[$campo] = $estilos[$campo];
        }
    }

    if (!in_array($estilosFiltrados['idioma'] ?? 'es', ['es', 'en', 'pt'], true)) {
        $estilosFiltrados['idioma'] = 'es';
    }

    if (!file_put_contents($styleFile, json_encode($estilosFiltrados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        die('❌ No se pudo restaurar estilos.json');
    }
}

/* =========================
   RESTAURAR TELEGRAM
========================= */
$telegram = $data['telegram'] ?? null;
if (is_array($telegram)) {
    $telegramFiltrado = [
        'token' => $telegram['token'] ?? '',
        'chat_id' => $telegram['chat_id'] ?? ''
    ];

    if (!file_put_contents($telegramFile, json_encode($telegramFiltrado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        die('❌ No se pudo restaurar telegram_config.json');
    }
}

/* =========================
   RESTAURAR SVXLINK
========================= */
$svxlink_conf = $data['svxlink_conf'] ?? '';
if (is_string($svxlink_conf) && trim($svxlink_conf) !== '') {
    if (!file_put_contents($svxlinkFile, $svxlink_conf)) {
        die('❌ No se pudo restaurar svxlink.conf');
    }
}

/* =========================
   RESTAURAR ECHOLINK
========================= */
$echolink_conf = $data['echolink_conf'] ?? '';
if (is_string($echolink_conf) && trim($echolink_conf) !== '') {
    if (!file_put_contents($echolinkFile, $echolink_conf)) {
        die('❌ No se pudo restaurar ModuleEchoLink.conf');
    }
}

header('Location: custom.php?restore=ok');
exit;
