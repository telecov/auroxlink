<?php
    include 'telegram_alert.php';

    $esperaInfoSegundos = 3;
    $pendientes = [];
    $estadoIndicativos = [];
    $ultimaFecha = date('Y-m-d');

    $log_file = '/var/log/svxlink';

    if (!file_exists($log_file)) {
        die("Error: No se encontró el archivo de log.\n");
    }

    $handle = popen("tail -n 0 -F " . escapeshellarg($log_file), "r");

    while (!feof($handle)) {
        $line = fgets($handle);

        if ($line !== false) {
            $line = trim($line);

            // Detectar nueva conexión
            if (strpos($line, 'EchoLink QSO state changed to CONNECTED') !== false) {
                if (preg_match('/([A-Z0-9\-]+): EchoLink QSO state changed to CONNECTED/', $line, $matches)) {
                    $indicativo = trim($matches[1]);

                    // Evita duplicar si ya estaba conectado
                    if (!isset($estadoIndicativos[$indicativo]) || $estadoIndicativos[$indicativo] === false) {
                        $pendientes[$indicativo] = [
                            'hora_detectado' => time(),
                            'indicativo' => $indicativo,
                            'ubicacion' => '',
                            'dispositivo' => '',
                            'completo' => false
                        ];

                        $estadoIndicativos[$indicativo] = true; // Marcar como conectado
                    }
                }
            }

            // Capturar ubicación (luego de "Station CALLSIGN")
            if (preg_match('/^Station ([A-Z0-9\-]+)$/', $line, $matches)) {
                $indicativo = $matches[1];
                if (isset($pendientes[$indicativo])) {
                    $pendientes[$indicativo]['ubicacion'] = '';
                }
            } elseif (preg_match('/^[A-Za-z\s\p{L}\-]+$/u', $line) && !empty($line)) {
                foreach ($pendientes as &$p) {
                    if (empty($p['ubicacion'])) {
                        $p['ubicacion'] = trim($line);
                        break;
                    }
                }
            }

            // Capturar dispositivo
            if (strpos($line, 'is running EchoLink') !== false) {
                if (preg_match('/on a (.+?),/', $line, $deviceMatch)) {
                    foreach ($pendientes as &$p) {
                        if (empty($p['dispositivo'])) {
                            $p['dispositivo'] = trim($deviceMatch[1]);
                            $p['completo'] = true;
                            break;
                        }
                    }
                } elseif (preg_match('/running EchoLink (.+)/', $line, $deviceMatch)) {
                    foreach ($pendientes as &$p) {
                        if (empty($p['dispositivo'])) {
                            $p['dispositivo'] = trim($deviceMatch[1]);
                            $p['completo'] = true;
                            break;
                        }
                    }
                }
            }

            // Procesar conexiones completas pasados 3 segundos
            
    foreach ($pendientes as $indicativo => $datos) {
        if (time() - $datos['hora_detectado'] >= $esperaInfoSegundos) {
            $fecha = date('Y-m-d H:i:s');
            $mensaje = "📡 AUROXLINK - Nueva Conexión\n";
            $mensaje .= "🔹 Indicativo: {$datos['indicativo']}\n";

            if (!empty($datos['ubicacion'])) {
                $mensaje .= "📍 Ubicación: {$datos['ubicacion']}\n";
            }

            if (!empty($datos['dispositivo'])) {
                $mensaje .= "📱 Dispositivo: {$datos['dispositivo']}\n";
            }

            $mensaje .= "🕑 Hora UTC: {$fecha}";
            enviarAlertaTelegram($mensaje);
            unset($pendientes[$indicativo]);
        }
    }

            // Detectar desconexiones
            if (strpos($line, 'EchoLink QSO state changed to DISCONNECTED') !== false) {
                if (preg_match('/([A-Z0-9\-]+): EchoLink QSO state changed to DISCONNECTED/', $line, $matches)) {
                    $indicativo = trim($matches[1]);
                    $fecha = date('Y-m-d H:i:s');
                    $mensaje = "📴 AUROXLINK - Desconexión\n";
                    $mensaje .= "🔹 Indicativo: {$indicativo}\n";
                    $mensaje .= "🕑 Hora UTC: {$fecha}";
                    enviarAlertaTelegram($mensaje);

                    // Permitir futuras conexiones de este indicativo
                    $estadoIndicativos[$indicativo] = false;
                }
            }
        }

        // Pausa breve para no saturar
        usleep(300000);

        // Reset diario
        if (date('Y-m-d') !== $ultimaFecha) {
            $pendientes = [];
            $estadoIndicativos = [];
            $ultimaFecha = date('Y-m-d');
        }
    }

    pclose($handle);
?>