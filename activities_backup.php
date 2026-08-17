<?php
$dir = __DIR__ . "/data_actividades/historial";
$files = glob($dir . "/*.json");

if (!$files) {
    http_response_code(404);
    exit('No activity files found');
}

$zip = new ZipArchive();
$tmp = tempnam(sys_get_temp_dir(), 'zip');

if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
    http_response_code(500);
    exit('Could not create ZIP file');
}

foreach ($files as $f) {
    $zip->addFile($f, basename($f));
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="activities_backup.zip"');
header('Content-Length: ' . filesize($tmp));

readfile($tmp);
unlink($tmp);
exit;
