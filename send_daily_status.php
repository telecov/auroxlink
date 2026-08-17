<?php
require __DIR__ . '/includes/environment.php';

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

/* =========================================================
   CARGA DE IDIOMA
========================================================= */
$configFile = __DIR__ . '/estilos.json';
$config = file_exists($configFile)
    ? json_decode(file_get_contents($configFile), true)
    : [];

$idioma = $config['idioma'] ?? 'es';

$langFile = __DIR__ . "/data/lang/{$idioma}.json";
$lang = [];
if (file_exists($langFile)) {
    $lang = json_decode(file_get_contents($langFile), true);
}
if (!is_array($lang)) {
    $lang = json_decode(file_get_contents(__DIR__ . "/data/lang/es.json"), true);
}
if (!is_array($lang)) {
    $lang = [];
}

function t($key, $default = '')
{
    global $lang;
    return $lang[$key] ?? $default;
}

/* =========================================================
   ESCAPAR MARKDOWN V2
========================================================= */
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
$config_file = __DIR__ . '/telegram_config.json';
if (!file_exists($config_file)) {
    die("❌ Falta telegram_config.json\n");
}

$configTelegram = json_decode(file_get_contents($config_file), true);
$token = $configTelegram['token'] ?? '';
$chat_id = $configTelegram['chat_id'] ?? '';

if (!$token || !$chat_id) {
    die("❌ Faltan token o chat_id\n");
}

/* =========================================================
   NOMBRE DEL ADMIN
========================================================= */
$estilo_path = __DIR__ . '/estilos.json';
$admin = t('daily_admin', 'Administrator');

if (file_exists($estilo_path)) {
    $datos = json_decode(file_get_contents($estilo_path), true);
    $admin = $datos['radioaficionado'] ?? t('daily_admin', 'Administrator');
}

/* =========================================================
   OBTENER INFO DEL SISTEMA
========================================================= */
$uptime = trim(shell_exec("uptime -p"));

$temp_raw = @file_get_contents("/sys/class/thermal/thermal_zone0/temp");
$temperature = $temp_raw ? round($temp_raw / 1000, 1) . " °C" : t('not_available', 'Not available');

$cpu = trim(shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print $2 + $4}'"));
$cpu = ($cpu !== '') ? $cpu . " %" : t('not_available', 'Not available');

$mem_info = shell_exec("free -m");
preg_match("/Mem:\s+(\d+)\s+(\d+)/", $mem_info, $m);
$ram = isset($m[1], $m[2]) ? "{$m[2]} / {$m[1]} MB" : t('not_available', 'Not available');

$svx_status = trim(shell_exec("systemctl is-active svxlink")) === "active"
    ? "🟢 " . t('daily_svx_active', 'SVXLink: Active')
    : "🔴 " . t('daily_svx_stopped', 'SVXLink: Stopped');

$hostname = trim(shell_exec("hostname"));
$fecha_actual = date("Y-m-d H:i:s");

/* =========================================================
   ARMAR MENSAJE
========================================================= */
$mensaje  = "👋 " . t('daily_hello', 'Hello') . " *" . escaparMarkdown($admin) . "*\n";
$mensaje .= escaparMarkdown(t('daily_status_intro', 'Here is the daily status of your AUROXLINK node:')) . "\n\n";
$mensaje .= "🕓 *" . escaparMarkdown(t('uptime', 'Uptime')) . ":* `" . escaparMarkdown($uptime) . "`\n";
$mensaje .= "🌡️ *" . escaparMarkdown(t('daily_temperature', 'Temperature')) . ":* `" . escaparMarkdown($temperature) . "`\n";
$mensaje .= "🧠 *" . escaparMarkdown(t('ram_memory', 'RAM Memory')) . ":* `" . escaparMarkdown($ram) . "`\n";
$mensaje .= "⚙️ *" . escaparMarkdown(t('cpu_usage', 'CPU Usage')) . ":* `" . escaparMarkdown($cpu) . "`\n";
$mensaje .= escaparMarkdown($svx_status) . "\n";
$mensaje .= "\n📅 *" . escaparMarkdown(t('daily_date', 'Date')) . ":* `" . escaparMarkdown($fecha_actual) . "`";
$mensaje .= "\n💻 *" . escaparMarkdown(t('daily_node', 'Node')) . ":* `" . escaparMarkdown($hostname) . "`";

/* =========================================================
   ENVIAR A TELEGRAM
========================================================= */
$url = "https://api.telegram.org/bot$token/sendMessage";
$params = [
    'chat_id' => $chat_id,
    'text' => $mensaje,
    'parse_mode' => 'MarkdownV2'
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $params,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

/* =========================================================
   LOGS
========================================================= */
$log_dir = '/tmp/auroxlink_logs';
@mkdir($log_dir, 0777, true);

$log_ok = "$log_dir/estado_diario.log";
$log_err = "$log_dir/estado_diario_error.log";

function guardarLog($archivo, $mensaje, $max = 20)
{
    $lineas = file_exists($archivo) ? file($archivo, FILE_IGNORE_NEW_LINES) : [];
    $lineas[] = "[" . date("Y-m-d H:i:s") . "] " . $mensaje;
    $lineas = array_slice($lineas, -$max);
    file_put_contents($archivo, implode(PHP_EOL, $lineas) . PHP_EOL);
}

/* =========================================================
   RESULTADO
========================================================= */
if ($httpCode === 200) {
    guardarLog($log_ok, "✅ " . t('daily_log_sent', 'Daily status sent to') . " " . $admin);
} else {
    guardarLog($log_err, "❌ " . t('daily_log_error', 'Error sending daily status') . ": " . $response);
}
