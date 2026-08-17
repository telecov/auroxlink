<?php
$ip = $_SERVER['REMOTE_ADDR'];

if (!preg_match('/^(127\.0\.0\.1|::1|192\.168\.|10\.)/', $ip)) {
    http_response_code(403);
    echo "⛔ Acceso denegado para IP: " . htmlspecialchars($ip);
    exit;
}

@set_time_limit(0);
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

function mostrarLinea($texto, $tipo = 'info')
{
    $clase = match($tipo) {
        'ok'    => 'text-success',
        'error' => 'text-danger',
        'warn'  => 'text-warning',
        default => 'text-light'
    };

    echo '<div class="' . $clase . '">' . htmlspecialchars($texto) . "</div>\n";
    echo str_repeat(' ', 4096); // ayuda a forzar el envío al navegador
    flush();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Actualizando AUROXLINK...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #121212;
            color: #f8f9fa;
            padding: 2rem;
            font-family: monospace;
        }
        .log-box {
            background: #000;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 1rem;
            min-height: 400px;
            max-height: 70vh;
            overflow-y: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>

<h4 class="mb-3">🔄 Ejecutando actualización automática de AUROXLINK...</h4>
<div class="spinner-border text-warning mb-3" role="status">
    <span class="visually-hidden">Cargando...</span>
</div>
<p>El proceso se mostrará en tiempo real.</p>
<hr>

<div class="log-box" id="log">
<?php

$updater = "/usr/local/libexec/auroxlink/update_auroxlink.sh";

mostrarLinea("[INFO] Iniciando proceso de actualización...");

if (!is_file($updater)) {
    mostrarLinea(
        "[ERROR] No se encontró el actualizador protegido: $updater",
        "error"
    );
    exit;
}

if (!is_executable($updater)) {
    mostrarLinea(
        "[ERROR] El actualizador protegido no es ejecutable.",
        "error"
    );
    exit;
}

/*
 * IMPORTANTE:
 * sudo puede colgarse si pide contraseña.
 * Por eso se usa -n (non-interactive).
 * Si sudo no está permitido sin contraseña, fallará de inmediato y lo verás.
 */
$comando = "sudo -n /usr/bin/bash " . escapeshellarg($updater) . " 2>&1";

mostrarLinea("[INFO] Ejecutando comando:");
mostrarLinea($comando);

$descriptores = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$proceso = proc_open($comando, $descriptores, $pipes);

if (!is_resource($proceso)) {
    mostrarLinea("[ERROR] No se pudo iniciar el proceso de actualización.", "error");
    exit;
}

fclose($pipes[0]);

stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);

mostrarLinea("[INFO] Mostrando salida en tiempo real...");
echo "<hr>\n";
flush();

$salidaVacia = true;

while (true) {
    $estado = proc_get_status($proceso);

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    if ($stdout !== false && $stdout !== '') {
        $salidaVacia = false;
        $lineas = explode("\n", $stdout);
        foreach ($lineas as $linea) {
            if (trim($linea) !== '') {
                mostrarLinea($linea);
            }
        }
    }

    if ($stderr !== false && $stderr !== '') {
        $salidaVacia = false;
        $lineas = explode("\n", $stderr);
        foreach ($lineas as $linea) {
            if (trim($linea) !== '') {
                mostrarLinea($linea, "error");
            }
        }
    }

    if (!$estado['running']) {
        break;
    }

    usleep(200000);
}

fclose($pipes[1]);
fclose($pipes[2]);

$codigoSalida = proc_close($proceso);

echo "<hr>\n";

if ($salidaVacia) {
    mostrarLinea("[WARN] El script terminó, pero no generó salida visible.", "warn");
}

if ($codigoSalida === 0) {
    mostrarLinea("[OK] Actualización completada correctamente.", "ok");
} else {
    mostrarLinea("[ERROR] La actualización terminó con código: $codigoSalida", "error");
    mostrarLinea("[SUGERENCIA] Si ves 'sudo: a password is required', debes permitir este comando en sudoers sin contraseña.", "warn");
}

?>
</div>

<script>
const logBox = document.getElementById('log');
const observer = new MutationObserver(() => {
    logBox.scrollTop = logBox.scrollHeight;
});
observer.observe(logBox, { childList: true, subtree: true, characterData: true });
</script>

</body>
</html>