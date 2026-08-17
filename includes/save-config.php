<?php
// Ruta real del archivo
$archivo = "/etc/svxlink/svxlink.d/ModuleEchoLink.conf";
$parametros = [
    "CALLSIGN",
    "PASSWORD",
    "SYSOPNAME",
    "LOCATION",
    "REJECT_INCOMING",
    "DEFAULT_LANG",
    "MAX_QSOS",
    "MAX_CONNECTIONS",
    "LINK_IDLE_TIMEOUT",
    "AUTOCON_ECHOLINK_ID",
    "PROXY_SERVER",
    "PROXY_PORT",
    "PROXY_PASSWORD"
];

$logDir = __DIR__ . "/logs";
$logfile = $logDir . "/echolink_config_log.txt";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!file_exists($archivo)) {
        die("Error: no se encontró el archivo de configuración.");
    }

    $lineas = file($archivo);
    $nuevas_lineas = [];
    $cambios = [];

    foreach ($lineas as $linea) {
        $modificada = false;

        foreach ($parametros as $clave) {
            $nombre_checkbox = "enable_" . $clave;

            if (preg_match("/^\s*(#?)\s*" . preg_quote($clave, '/') . "\s*=\s*(.*)$/i", $linea, $match)) {
                $valor_actual = trim($match[2]);
                $valor_post = isset($_POST[$clave]) ? trim($_POST[$clave]) : "";
                $activo = isset($_POST[$nombre_checkbox]);

                // Si es un campo sensible y viene vacío, conservar el valor actual
                if (in_array($clave, ['PASSWORD', 'PROXY_PASSWORD']) && $valor_post === "") {
                    $valor_final = $valor_actual;
                    $cambios[] = ($activo ? "✅" : "❌") . " {$clave} -> [SIN CAMBIOS]";
                } else {
                    $valor_final = $valor_post;
                    $cambios[] = ($activo ? "✅" : "❌") . " {$clave} -> {$valor_final}";
                }

                $nuevas_lineas[] = ($activo ? "" : "#") . "{$clave}={$valor_final}\n";
                $modificada = true;
                break;
            }
        }

        if (!$modificada) {
            $nuevas_lineas[] = $linea;
        }
    }

    // Crear respaldo antes de guardar
    @copy($archivo, $archivo . ".bak");

    // Guardar archivo actualizado
    file_put_contents($archivo, implode("", $nuevas_lineas));

    // Crear carpeta de logs si no existe
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    // Registrar log
    $registro = date("Y-m-d H:i:s") . " - Cambios:\n" . implode("\n", $cambios) . "\n---\n";
    file_put_contents($logfile, $registro, FILE_APPEND);

    // Reiniciar servicio
    shell_exec("sudo systemctl restart svxlink 2>&1");

    echo "<div style='padding:20px;font-family:sans-serif;'>
            ✅ Cambios guardados, servicio reiniciado y log registrado.
            <br><br>
            <a href='../settings.php'>Volver a configuración</a>
          </div>";
    exit;
} else {
    echo "Acceso no permitido.";
}
?>
