<?php
// Seguridad básica: solo ejecutar desde localhost
if ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1') {
    http_response_code(403);
    echo "Acceso denegado.";
    exit;
}

// Descargar y ejecutar el update desde GitHub
$sh_url = "https://raw.githubusercontent.com/telecov/auroxlink/main/update_auroxlink.sh";
$tmp_path = "/tmp/update_auroxlink.sh";

file_put_contents($tmp_path, file_get_contents($sh_url));
chmod($tmp_path, 0755);
$output = shell_exec("sudo bash $tmp_path 2>&1");

echo "<pre>$output</pre>";
