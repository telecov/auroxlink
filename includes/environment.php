<?php
$style = json_decode(@file_get_contents(__DIR__ . '/../estilos.json'), true);

if (!is_array($style)) {
    $style = [];
}

$nombreZona = $style['nombre_zona'] ?? 'AUROXLINK';
$tituloDashboard = $style['titulo_dashboard'] ?? 'Dashboard Nodo EchoLink';
$indicativo = $style['indicativo'] ?? 'CA2RDP-L';
$radioaficionado = $style['radioaficionado'] ?? 'CA2RDP';
$ciudad = $style['ciudad'] ?? 'La Serena';
$modo = $style['modo'] ?? 'SIMPLEX';
$frecuencia = $style['frecuencia'] ?? '145.600';
$offset = $style['offset'] ?? '0';
$tono = $style['tono'] ?? '88.5';
$ubicacion = $style['ubicacion'] ?? 'Caleta San Pedro, La Serena';
$aprs_web = $style['aprs_web'] ?? 'https://aprs.fi/';
$utcOffset = $style['utc_offset'] ?? '-4';

$foto_admin = $style['foto_admin'] ?? 'img/admin.png';
$imagenLogo = $style['logo'] ?? 'auroxlink_banner.png';
$colorSidebar = $style['color_sidebar'] ?? '#2c3e50';
$colorFondo = $style['color_fondo'] ?? '#e9ecef';
$colorTitulo = $style['color_titulo'] ?? '#000000';

$clave_acceso = '0192023a7bbd73250516f069df18b500';

$teleco = 'Román - CA2RDP';
$hammer = 'Esteban - CA3EUO';

$titleSite = $nombreZona;
$versionFile = __DIR__ . '/../version.txt';
$versionNumber = file_exists($versionFile)
    ? trim(file_get_contents($versionFile))
    : '';

$version = 'Versión ' . ($versionNumber !== '' ? $versionNumber : 'desconocida');

$modo_sistema = $style['modo_sistema'] ?? 'echolink';
$log_path = ($modo_sistema === 'echolink')
    ? '/var/log/svxlink'
    : '/var/log/svxreflector';

if (!file_exists($log_path)) {
    error_log("⚠️ Archivo de log no encontrado en: $log_path");
}
?>
