<?php
function escaparMarkdown($text)
{
    $escape_chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    foreach ($escape_chars as $char) {
        $text = str_replace($char, '\\' . $char, $text);
    }
    return $text;
}

function enviarAlertaTelegram($mensaje)
{
    $config_path = __DIR__ . '/telegram_config.json';

    if (!file_exists($config_path)) {
        error_log('Telegram: archivo de configuración no encontrado.');
        return false;
    }

    $config = json_decode(file_get_contents($config_path), true);

    $token   = $config['token']   ?? '';
    $chat_id = $config['chat_id'] ?? '';
    $canal   = $config['canal']   ?? ''; // opcional

    if (empty($token) || empty($chat_id)) {
        error_log('Telegram: token o chat_id no configurados.');
        return false;
    }

    // Escapar Markdown correctamente
    $mensaje = escaparMarkdown($mensaje);

    $url = "https://api.telegram.org/bot$token/sendMessage";

    $payload = [
        'chat_id' => $chat_id,
        'text' => $mensaje,
        'parse_mode' => 'MarkdownV2'
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($payload),
            'timeout' => 10
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        error_log('Telegram: error enviando mensaje principal.');
    }

    // Enviar también al canal si existe
    if (!empty($canal)) {
        $payload['chat_id'] = $canal;
        $context = stream_context_create($options);
        @file_get_contents($url, false, $context);
    }

    return $result;
}
