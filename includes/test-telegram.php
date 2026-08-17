<?php
require __DIR__ . '/../includes/environment.php';

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

/* =========================================================
   CARGA DE IDIOMA
========================================================= */
$configFile = __DIR__ . '/../estilos.json';
$config = file_exists($configFile)
    ? json_decode(file_get_contents($configFile), true)
    : [];

$idioma = $config['idioma'] ?? 'es';

$langFile = __DIR__ . "/../data/lang/{$idioma}.json";
$lang = [];
if (file_exists($langFile)) {
    $lang = json_decode(file_get_contents($langFile), true);
}
if (!is_array($lang)) {
    $lang = json_decode(file_get_contents(__DIR__ . "/../data/lang/es.json"), true);
}
if (!is_array($lang)) {
    $lang = [];
}

function t($key, $default = '')
{
    global $lang;
    return $lang[$key] ?? $default;
}

function escaparMarkdown($texto)
{
    $texto = str_replace(
        ['\\', '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'],
        ['\\\\', '\\_', '\\*', '\\[', '\\]', '\\(', '\\)', '\\~', '\\`', '\\>', '\\#', '\\+', '\\-', '\\=', '\\|', '\\{', '\\}', '\\.', '\\!'],
        $texto
    );
    return $texto;
}

/* =========================================================
   CARGAR TELEGRAM
========================================================= */
$config_path = __DIR__ . '/../telegram_config.json';

if (!file_exists($config_path)) {
    die(t('tg_test_missing_config', 'Telegram configuration file not found.'));
}

$configTelegram = json_decode(file_get_contents($config_path), true);
$token = $configTelegram['token'] ?? '';
$chat_id = $configTelegram['chat_id'] ?? '';

if (empty($token) || empty($chat_id)) {
    die(t('tg_test_missing_data', 'Token or Chat ID not configured.'));
}

/* =========================================================
   MENSAJE DE PRUEBA
========================================================= */
$mensaje = "✅ *" . escaparMarkdown(t('tg_test_success_message', 'Successful Telegram test from AUROXLINK!')) . "*";

$data = [
    'chat_id' => $chat_id,
    'text' => $mensaje,
    'parse_mode' => 'MarkdownV2'
];

$url = "https://api.telegram.org/bot$token/sendMessage";

$options = [
    'http' => [
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
        'timeout' => 10
    ]
];

$context = stream_context_create($options);
$result = @file_get_contents($url, false, $context);

?>
<!doctype html>
<html lang="<?= htmlspecialchars($idioma); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titleSite ?? 'AUROXLINK'); ?> - Telegram</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            padding: 40px;
            text-align: center;
        }
        .box {
            max-width: 650px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }
        .btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #0d47a1;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }
        .ok {
            color: #198754;
        }
        .err {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="box">
        <?php if ($result === false): ?>
            <h2 class="err">❌ <?= t('tg_test_error', 'Error sending Telegram test message.'); ?></h2>
        <?php else: ?>
            <h2 class="ok">✅ <?= t('tg_test_sent_ok', 'Test message sent successfully to your Telegram.'); ?></h2>
        <?php endif; ?>

        <a href="../custom.php" class="btn">⬅️ <?= t('settings_back_dashboard', 'Back to Dashboard'); ?></a>
    </div>
</body>
</html>
