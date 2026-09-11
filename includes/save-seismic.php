<?php
declare(strict_types=1);
session_start();
if (!isset($_SESSION['autenticado'])) { http_response_code(403); exit('Acceso denegado.'); }

$configFile = dirname(__DIR__) . '/data/seismic_config.json';

function backError(string $m): never {
    $_SESSION['seismic_error']=$m;
    header('Location: ../settings.php#seismic-settings');
    exit;
}
function b(string $n): bool { return isset($_POST[$n]) && (string)$_POST[$n]==='1'; }
function f(string $n,float $min,float $max): float {
    if (!isset($_POST[$n]) || !is_numeric($_POST[$n])) backError("Valor inválido: {$n}");
    $v=(float)$_POST[$n];
    if ($v<$min || $v>$max) backError("Valor fuera de rango: {$n}");
    return $v;
}
function i(string $n,int $min,int $max): int {
    if (!isset($_POST[$n]) || filter_var($_POST[$n],FILTER_VALIDATE_INT)===false) backError("Valor inválido: {$n}");
    $v=(int)$_POST[$n];
    if ($v<$min || $v>$max) backError("Valor fuera de rango: {$n}");
    return $v;
}

$current=[];
if (is_file($configFile)) {
    $d=json_decode((string)file_get_contents($configFile),true);
    if (is_array($d)) $current=$d;
}
$provider=strtolower(trim((string)($_POST['provider'] ?? 'auto')));
if (!in_array($provider,['auto','csn','usgs'],true)) backError('Fuente sísmica inválida.');

$tts=is_array($current['tts'] ?? null) ? $current['tts'] : [];
$engine=strtolower(trim((string)($_POST['tts_engine'] ?? ($tts['engine'] ?? 'piper'))));
if (!in_array($engine,['piper','espeak-ng'],true)) $engine='piper';

$config=[
    'enabled'=>b('enabled'),
    'provider'=>$provider,
    'latitude'=>f('latitude',-90,90),
    'longitude'=>f('longitude',-180,180),
    'refresh_seconds'=>i('refresh_seconds',30,3600),
    'display'=>[
        'min_magnitude'=>f('display_min_magnitude',0,10),
        'radius_km'=>f('display_radius_km',50,20000)
    ],
    'rf'=>[
        'enabled'=>b('rf_enabled'),
        'command_pty'=>(string)($current['rf']['command_pty'] ?? '/dev/shm/simplex_logic_ctrl'),
        'local'=>['enabled'=>b('local_enabled'),'radius_km'=>f('local_radius_km',1,5000),'min_magnitude'=>f('local_min_magnitude',0,10)],
        'regional'=>['enabled'=>b('regional_enabled'),'radius_km'=>f('regional_radius_km',1,5000),'min_magnitude'=>f('regional_min_magnitude',0,10)],
        'wide'=>['enabled'=>b('wide_enabled'),'radius_km'=>f('wide_radius_km',1,10000),'min_magnitude'=>f('wide_min_magnitude',0,10)],
        'national'=>['enabled'=>b('national_enabled'),'min_magnitude'=>f('national_min_magnitude',0,10)],
        'repetitions'=>i('rf_repetitions',1,3),
        'pre_tone'=>b('rf_pre_tone')
    ],
    'tts'=>[
        'engine'=>$engine,
        'voice'=>trim((string)($_POST['tts_voice'] ?? ($tts['voice'] ?? 'es_ES-davefx-medium'))),
        'model'=>trim((string)($_POST['tts_model'] ?? ($tts['model'] ?? '/opt/auroxlink/piper/voices/es_ES-davefx-medium.onnx'))),
        'length_scale'=>max(0.5,min(2.0,(float)($_POST['tts_length_scale'] ?? ($tts['length_scale'] ?? 1.0)))),
        'fallback_voice'=>trim((string)($_POST['tts_fallback_voice'] ?? ($tts['fallback_voice'] ?? 'es'))),
        'fallback_speed'=>max(80,min(300,(int)($_POST['tts_fallback_speed'] ?? ($tts['fallback_speed'] ?? 145))))
    ]
];

if ($config['rf']['regional']['radius_km'] < $config['rf']['local']['radius_km']) backError('El radio REGIONAL no puede ser menor que LOCAL.');
if ($config['rf']['wide']['radius_km'] < $config['rf']['regional']['radius_km']) backError('El radio AMPLIA no puede ser menor que REGIONAL.');

$json=json_encode($config,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
if ($json===false || @file_put_contents($configFile,$json.PHP_EOL,LOCK_EX)===false) {
    backError('No fue posible guardar seismic_config.json. Revisa permisos.');
}
header('Location: ../settings.php?seismic=saved#seismic-settings');
exit;
