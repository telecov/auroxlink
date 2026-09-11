<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['autenticado'])) {
    http_response_code(403);
    exit('Acceso no autorizado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$php = '/usr/bin/php';
$monitor = '/opt/auroxlink/scripts/seismic-monitor.php';

if (!is_file($monitor)) {
    $_SESSION['seismic_test_error'] =
        'No se encontró seismic-monitor.php.';

    header(
        'Location: ../settings.php#seismic-settings'
    );
    exit;
}

/*
 * Comando fijo, sin parámetros enviados por el navegador.
 * Se ejecuta como usuario svxlink y solo en modo --test-rf.
 */
$command =
    '/usr/bin/sudo -n -u svxlink ' .
    escapeshellarg($php) . ' ' .
    escapeshellarg($monitor) .
    ' --test-rf 2>&1';

$output = [];
$returnCode = 1;

exec(
    $command,
    $output,
    $returnCode
);

$result = trim(
    implode(
        PHP_EOL,
        $output
    )
);

if ($returnCode === 0) {

    $_SESSION['seismic_test_ok'] =
        'Prueba de audio / RF enviada correctamente a SvxLink.';

} else {

    if (
        stripos($result, 'password is required') !== false ||
        stripos($result, 'a password is required') !== false ||
        stripos($result, 'not allowed to execute') !== false
    ) {
        $_SESSION['seismic_test_error'] =
            'Apache no tiene permiso para ejecutar la prueba como usuario svxlink.';
    } else {
        $_SESSION['seismic_test_error'] =
            'La prueba RF falló. Revise SvxLink y el journal del monitor sísmico.';
    }
}

header(
    'Location: ../settings.php#seismic-settings'
);

exit;
