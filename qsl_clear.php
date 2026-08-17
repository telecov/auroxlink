<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $archivo = basename($_POST['archivo'] ?? '');
    $ruta = __DIR__ . "/qsl/$archivo";
    $meta = str_replace('.png', '.json', $ruta);

    if (file_exists($ruta)) {
        unlink($ruta);
        if (file_exists($meta)) unlink($meta);
        echo "✅ QSL eliminada correctamente.";
    } else {
        http_response_code(404);
        echo "❌ Archivo no encontrado.";
    }
} else {
    http_response_code(405);
    echo "Método no permitido.";
}
?>
