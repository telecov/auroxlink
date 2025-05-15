<?php
header('Content-Type: application/json');

// Versión local
$local = trim(file_get_contents(__DIR__ . '/version.txt'));

// Versión remota desde GitHub API
$context = stream_context_create(['http' => ['user_agent' => 'auroxlink']]);
$remote = json_decode(file_get_contents("https://api.github.com/repos/telecov/auroxlink/releases/latest", false, $context), true);
$tag = isset($remote['tag_name']) ? ltrim($remote['tag_name'], 'v') : '';

// Respuesta
echo json_encode([
    "local" => $local,
    "remota" => $tag,
    "disponible" => version_compare($tag, $local, '>')
]);
