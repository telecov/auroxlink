<?php
header('Content-Type: application/json; charset=utf-8');

$localFile = __DIR__ . '/version.txt';
$local = is_file($localFile) ? trim((string) file_get_contents($localFile)) : '';

$context = stream_context_create([
    'http' => [
        'user_agent' => 'AUROXLINK',
        'timeout' => 8
    ]
]);

$remote = @file_get_contents(
    'https://api.github.com/repos/telecov/auroxlink/releases/latest',
    false,
    $context
);

$tag = '';
if ($remote !== false) {
    $json = json_decode($remote, true);
    if (is_array($json) && !empty($json['tag_name'])) {
        $tag = ltrim((string) $json['tag_name'], 'vV');
    }
}

/*
 * Migración especial 1.8.5
 * ------------------------
 * Las instalaciones antiguas (por ejemplo 1.7.x) ejecutan primero el
 * actualizador protegido que ya tenían instalado. Ese actualizador puede
 * copiar AUROXLINK 1.8.5 y el nuevo update_auroxlink.sh, pero no conoce
 * todavía todos los componentes nuevos de 1.8.5.
 *
 * Si AUROXLINK ya muestra 1.8.5 pero falta este marcador, mantenemos visible
 * el botón Actualizar para que una segunda ejecución complete automáticamente
 * Piper, Sismógrafo, COMMAND_PTY, permisos, systemd, workers, etc.
 *
 * Las instalaciones nuevas creadas por el instalador 1.8.5 dejan el marcador
 * creado y no muestran este aviso.
 */
$migrationMarker = '/var/lib/auroxlink/migrations/1.8.5.done';
$migrationPending = false;

if ($local !== '' && version_compare($local, '1.8.5', '>=')) {
    $migrationPending = !is_file($migrationMarker);
}

$versionAvailable = (
    $tag !== '' &&
    $local !== '' &&
    version_compare($tag, $local, '>')
);

echo json_encode([
    'local' => $local,
    'remota' => $tag,
    'disponible' => ($versionAvailable || $migrationPending),
    'migration_pending' => $migrationPending
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
