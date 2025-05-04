<?php
    // Ruta real del archivo
    $archivo = "/etc/svxlink/svxlink.d/ModuleEchoLink.conf";
    $parametros = ["CALLSIGN", "PASSWORD", "SYSOPNAME", "LOCATION", "DEFAULT_LANG", "MAX_QSOS", "MAX_CONNECTIONS", "LINK_IDLE_TIMEOUT", "AUTOCON_ECHOLINK_ID"];
    $logfile = __DIR__ . "/logs/echolink_config_log.txt";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Leer el archivo
        $lineas = file($archivo);
        $nuevas_lineas = [];
        $cambios = [];

        foreach ($lineas as $linea) {
            $es_parametro = false;
            foreach ($parametros as $clave) {
                if (preg_match("/^\s*$clave\s*=.*/i", $linea)) {
                    $nuevo_valor = trim($_POST[$clave]);
                    $cambios[] = "$clave -> $nuevo_valor";
                    $nuevas_lineas[] = "$clave=$nuevo_valor\n";
                    $es_parametro = true;
                    break;
                }
            }
            if (!$es_parametro) {
                $nuevas_lineas[] = $linea;
            }
        }

        // Crear respaldo
        copy($archivo, $archivo . ".bak");

        // Guardar cambios
        file_put_contents($archivo, implode("", $nuevas_lineas));

        // Registrar en log
        $registro = date("Y-m-d H:i:s") . " - Cambios realizados:\n" . implode("\n", $cambios) . "\n---\n";
        if (!is_dir(__DIR__ . "/logs")) mkdir(__DIR__ . "/logs", 0777, true);
        file_put_contents($logfile, $registro, FILE_APPEND);

        // Reiniciar servicio
        shell_exec("sudo systemctl restart svxlink");

        echo "<div style='padding:20px;font-family:sans-serif;'>✅ Cambios guardados, servicio reiniciado y log registrado.<br><a href='../settings.php'>Volver a configuración</a></div>";
        exit;
    } else {
        echo "Acceso no permitido.";
    }
?>
