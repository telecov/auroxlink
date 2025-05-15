<?php
$ip = $_SERVER['REMOTE_ADDR'];

if (!preg_match('/^(127\.0\.0\.1|::1|192\.168\.|10\.)/', $ip)) {
    http_response_code(403);
    echo "⛔ Acceso denegado para IP: $ip";
    exit;
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
  </style>
</head>
<body>
  <h4 class="mb-3">🔄 Ejecutando actualización automática de AUROXLINK...</h4>
  <div class="spinner-border text-warning mb-3" role="status">
    <span class="visually-hidden">Cargando...</span>
  </div>
  <p>Esto puede tardar unos segundos... No cierres esta ventana.</p>
  <hr>

  <?php
  // Descargar y ejecutar el update desde GitHub
  $sh_url = "https://raw.githubusercontent.com/telecov/auroxlink/main/update_auroxlink.sh";
  $tmp_path = "/tmp/update_auroxlink.sh";

  file_put_contents($tmp_path, file_get_contents($sh_url));
  chmod($tmp_path, 0755);

  echo "<h5>⏳ Detalles del proceso:</h5><pre>";
  $output = shell_exec("sudo /usr/bin/bash $tmp_path 2>&1");
  echo htmlspecialchars($output);
  echo "</pre>";
  ?>
</body>
</html>
