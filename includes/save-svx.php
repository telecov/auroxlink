<?php
$archivo = "/etc/svxlink/svxlink.conf";
$logfile = __DIR__ . "/logs/svxlink_config_log.txt";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $lineas = file($archivo);
    $nuevas_lineas = [];
    $cambios = [];
    $bloque_actual = "";

    foreach ($lineas as $linea) {
        $linea_trim = trim($linea);
        $modificado = false;

        // Detectar sección [Seccion]
        if (preg_match('/^\[(.*)\]$/', $linea_trim, $match)) {
            $bloque_actual = $match[1];
            $nuevas_lineas[] = $linea;
            continue;
        }

        // Detectar líneas tipo (#)? CLAVE = valor
        if (preg_match('/^\s*(#?)\s*([A-Z0-9_]+)\s*=\s*(.*)$/i', $linea_trim, $match)) {
            $comentado = $match[1] === '#';
            $clave = $match[2];
            $nombre = $bloque_actual . "_" . $clave;
            $nombre_checkbox = "enable_" . $nombre;

            if (isset($_POST[$nombre])) {
                $nuevo_valor = trim($_POST[$nombre]);
                $activo = isset($_POST[$nombre_checkbox]);

                $nuevas_lineas[] = ($activo ? "" : "#") . "$clave=$nuevo_valor\n";
                $estado = $activo ? "✅" : "❌";
                $cambios[] = "$estado [$bloque_actual] $clave -> $nuevo_valor";
                $modificado = true;
            }
        }

        if (!$modificado) {
            $nuevas_lineas[] = $linea;
        }
    }

    // Crear respaldo
    copy($archivo, $archivo . ".bak");

    // Guardar
    file_put_contents($archivo, implode("", $nuevas_lineas));

    // Log
    if (!is_dir(__DIR__ . "/logs")) mkdir(__DIR__ . "/logs", 0777, true);
    $registro = date("Y-m-d H:i:s") . " - Cambios SVX:\n" . implode("\n", $cambios) . "\n---\n";
    file_put_contents($logfile, $registro, FILE_APPEND);

    // Reiniciar
    shell_exec("sudo systemctl restart svxlink");

    echo "<div style='padding:20px;font-family:sans-serif;'>✅ Cambios guardados, servicio reiniciado.<br><a href='../settings.php'>Volver a configuración de SVXLink</a></div>";
    exit;
} else {
    echo "Acceso no permitido.";
}
?>
