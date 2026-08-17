<?php
require __DIR__ . '/includes/environment.php';
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['autenticado'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acceso no autorizado.']);
    exit;
}

$stateDir  = '/var/lib/auroxlink/svxlink-update';
$stateFile = $stateDir . '/state.json';
$logFile   = $stateDir . '/update.log';
$worker    = '/usr/local/libexec/auroxlink/svxlink_update_worker.sh';

function respond(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    exit;
}

function readState(string $stateFile): array
{
    if (!is_readable($stateFile)) {
        return [
            'status' => 'idle',
            'progress' => 0,
            'phase' => 'idle',
            'message' => 'Listo para comprobar y actualizar SvxLink.',
            'latest_version' => null,
            'started_at' => null,
            'finished_at' => null,
            'pid' => null,
        ];
    }

    $state = json_decode((string)file_get_contents($stateFile), true);
    if (!is_array($state)) {
        return [
            'status' => 'idle',
            'progress' => 0,
            'phase' => 'idle',
            'message' => 'No hay un estado de actualización válido.',
            'latest_version' => null,
            'started_at' => null,
            'finished_at' => null,
            'pid' => null,
        ];
    }

    if (($state['status'] ?? '') === 'running') {
        $pid = (int)($state['pid'] ?? 0);
        if ($pid <= 0 || !is_dir('/proc/' . $pid)) {
            $state['status'] = 'failed';
            $state['progress'] = min(99, max(0, (int)($state['progress'] ?? 0)));
            $state['phase'] = 'interrupted';
            $state['message'] = 'El proceso ya no está ejecutándose. Revisa el log antes de volver a intentar.';
        }
    }

    return $state;
}

function getNodeStatus(): array
{
    $configExists = is_file('/etc/svxlink/svxlink.conf');

    $serviceActive = trim((string)shell_exec(
        'systemctl is-active svxlink.service 2>/dev/null'
    )) === 'active';

    $mainPidRaw = trim((string)shell_exec(
        'systemctl show -p MainPID --value svxlink.service 2>/dev/null'
    ));

    $mainPid = ctype_digit($mainPidRaw)
        ? (int)$mainPidRaw
        : 0;

    $pidAlive =
        $mainPid > 0 &&
        is_dir('/proc/' . $mainPid);

    $ready =
        $configExists &&
        $serviceActive &&
        $pidAlive;

    $reason = null;

    if (!$configExists) {
        $reason = 'No existe /etc/svxlink/svxlink.conf.';
    } elseif (!$serviceActive) {
        $reason = 'svxlink.service no está activo.';
    } elseif (!$pidAlive) {
        $reason = 'SvxLink no tiene un proceso activo válido.';
    }

    return [
        'ready' => $ready,
        'service_active' => $serviceActive,
        'config_exists' => $configExists,
        'main_pid' => $mainPid > 0 ? $mainPid : null,
        'reason' => $reason,
    ];
}

function getVersions(): array
{
    $apt = trim((string)shell_exec(
        "dpkg-query -W -f='\${Version}' svxlink-server 2>/dev/null"
    ));

    $managed = '';
    if (is_readable('/etc/auroxlink/svxlink_version')) {
        $managed = trim((string)file_get_contents(
            '/etc/auroxlink/svxlink_version'
        ));
    }

    $current = '';
    if (is_link('/opt/auroxlink/svxlink/current')) {
        $current = trim((string)shell_exec(
            'readlink -f /opt/auroxlink/svxlink/current 2>/dev/null'
        ));
    }

    $currentVersion = $current !== ''
        ? basename(rtrim($current, '/'))
        : '';

    $exec = trim((string)shell_exec(
        'systemctl show -p ExecStart --value svxlink.service 2>/dev/null'
    ));

    $node = getNodeStatus();

    $managedActive =
        $managed !== '' &&
        $current !== '' &&
        $currentVersion === $managed &&
        is_executable('/opt/auroxlink/svxlink/current/bin/svxlink') &&
        str_contains(
            $exec,
            '/opt/auroxlink/svxlink/current/bin/svxlink'
        ) &&
        $node['ready'];

    return [
        'apt' => $apt !== '' ? $apt : null,
        'managed' => $managed !== '' ? $managed : null,
        'managed_active' => $managedActive,
        'current' => $current !== '' ? $current : null,
        'current_version' => $currentVersion !== ''
            ? $currentVersion
            : null,
        'service_active' => $node['service_active'],
    ];
}

function getLogTail(string $logFile): string
{
    if (!is_readable($logFile)) {
        return '';
    }

    return (string)shell_exec(
        '/usr/bin/tail -n 70 ' . escapeshellarg($logFile) . ' 2>/dev/null'
    );
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

if ($action === 'status') {
    respond([
        'ok' => true,
        'state' => readState($stateFile),
        'versions' => getVersions(),
        'node' => getNodeStatus(),
        'log' => getLogTail($logFile),
    ]);
}

if ($action !== 'start' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'Acción inválida.'], 400);
}

$token = (string)($_POST['csrf'] ?? '');
$sessionToken = (string)($_SESSION['svxlink_update_csrf'] ?? '');

if (
    $token === '' ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $token)
) {
    respond(['ok' => false, 'error' => 'Token de seguridad inválido.'], 403);
}

$currentState = readState($stateFile);
if (($currentState['status'] ?? '') === 'running') {
    respond([
        'ok' => false,
        'error' => 'Ya existe una actualización de SvxLink en ejecución.'
    ], 409);
}

$nodeStatus = getNodeStatus();

if (!$nodeStatus['ready']) {
    respond([
        'ok' => false,
        'error' =>
            'SvxLink no está operativo. Configura y deja funcionando el nodo antes de actualizar. ' .
            ($nodeStatus['reason'] ?? '')
    ], 409);
}

if (!is_file($worker)) {
    respond([
        'ok' => false,
        'error' => 'No se encontró el worker de actualización.'
    ], 500);
}

$cmd =
    'nohup sudo -n /usr/bin/bash ' . escapeshellarg($worker) .
    ' >/dev/null 2>&1 < /dev/null & echo $!';

$output = trim((string)shell_exec($cmd));

if ($output === '' || !ctype_digit($output)) {
    respond([
        'ok' => false,
        'error' => 'No se pudo iniciar el actualizador. Revisa sudoers.'
    ], 500);
}

respond([
    'ok' => true,
    'started' => true,
    'launcher_pid' => (int)$output,
]);
