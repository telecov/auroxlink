<?php
    function enviarAlertaTelegram($mensaje) {
        $config_path = __DIR__ . '/telegram_config.json';

        if (!file_exists($config_path)) {
            error_log('Archivo de configuración de Telegram no encontrado.');
            return false;
        }

        $config = json_decode(file_get_contents($config_path), true);

        $token = $config['token'] ?? '';
        $chat_id = $config['chat_id'] ?? '';

        if (empty($token) || empty($chat_id)) {
            error_log('Token o Chat ID de Telegram no configurados.');
            return false;
        }

        $data = [
            'chat_id' => $chat_id,
            'text' => $mensaje,
            'parse_mode' => 'Markdown'
        ];

        $url = "https://api.telegram.org/bot$token/sendMessage";

        $options = [
            'http' => [
                'header' => "Content-Type:application/x-www-form-urlencoded",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === FALSE) {
            error_log('Error enviando alerta a Telegram.');
        }

        return $result;
    }
?>