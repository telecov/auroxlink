<?php
    // Probar envío de mensaje a Telegram directamente, sin sesión

    $config_path = __DIR__ . '/../telegram_config.json';

    if (!file_exists($config_path)) {
        die('Archivo de configuración de Telegram no encontrado.');
    }

    $config = json_decode(file_get_contents($config_path), true);
    $token = $config['token'] ?? '';
    $chat_id = $config['chat_id'] ?? '';

    if (empty($token) || empty($chat_id)) {
        die('Token o Chat ID no configurados.');
    }

    $data = [
        'chat_id' => $chat_id,
        'text' => "✅ *Prueba exitosa de Telegram desde AUROXLINK!*",
        'parse_mode' => 'Markdown'
    ];

    $url = "https://api.telegram.org/bot$token/sendMessage";

    $options = [
        'http' => [
            'header'  => "Content-Type:application/x-www-form-urlencoded",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        echo "<h2>Error al enviar mensaje de prueba a Telegram.</h2>";
    } else {
        echo "<h2>✅ Mensaje de prueba enviado correctamente a tu canal de Telegram.</h2>";
    }

    echo '<a href="../custom.php" style="display:inline-block;margin-top:20px;padding:10px 20px;background:#0d47a1;color:white;text-decoration:none;border-radius:5px;">Volver</a>';
?>