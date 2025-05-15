<?php
// Seguridad básica: solo ejecutar desde una red local tipica
<?php
$ip = $_SERVER['REMOTE_ADDR'];

if (!preg_match('/^(127\.0\.0\.1|::1|192\.168\.|10\.)/', $ip)) {
    http_response_code(403);
    echo "Acceso denegado para IP: $ip";
    exit;
}

// Descargar y ejecutar el update desde GitHub
$sh_url = "https://raw.githubusercontent.com/telecov/auroxlink/main/update_auroxlink.sh";
$tmp_path = "/tmp/update_auroxlink.sh";

file_put_contents($tmp_path, file_get_contents($sh_url));
chmod($tmp_path, 0755);
$output = shell_exec("sudo bash $tmp_path 2>&1");

echo "<pre>$output</pre>";
