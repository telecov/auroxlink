<?php
$archivo = "/etc/svxlink/svxlink.conf";

// Recibe todos los datos enviados desde configuracion-svx.php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $lineas = file($archivo);
    $nuevas_lineas = [];

    foreach ($lineas as $linea) {
        $modificado = false;
        $linea_trim = trim($linea);

        // Detectar si es una línea tipo CLAVE=VALOR (sin importar espacios)
        if (preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/i', $linea_trim, $match)) {
            $clave = $match[1];

            // Buscar si se envió en el formulario como clave única
            foreach ($_POST as $nombre => $valor) {
                if (strcasecmp($nombre, $clave) === 0) {
                    $nuevas_lineas[] = "$clave=$valor\n";
                    $modificado = true;
                    break;
                }
            }
        }

        if (!$modificado) {
            $nuevas_lineas[] = $linea;
        }
    }

    // Guardar el archivo
    file_put_contents($archivo, implode("", $nuevas_lineas));

    // Reiniciar servicio
    shell_exec("sudo systemctl restart svxlink");

    // Redirigir
    echo "<div style='padding:20px;font-family:sans-serif;'>✅ Cambios guardados, servicio reiniciado.<br><a href='../settings.php'>Volver a configuración de SVXLink</a></div>";
    exit;
}
?>
