<?php
    // Guardar configuración de Telegram directamente, sin sesión

    if (isset($_POST['token']) && isset($_POST['chat_id'])) {
        $token = trim($_POST['token']);
        $chat_id = trim($_POST['chat_id']);

        $config = [
            'token' => $token,
            'chat_id' => $chat_id
        ];

        file_put_contents(__DIR__.'/../telegram_config.json', json_encode($config, JSON_PRETTY_PRINT));

        header('Location: personalizacion.php');
        exit;
    } else {
        echo "Datos inválidos.";
    }
?>
