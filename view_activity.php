<?php
require 'includes/environment.php';
session_start();

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

date_default_timezone_set('America/Santiago');

/* =========================================================
   LANGUAGE LOADING
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
    $fallback = __DIR__ . "/data/lang/es.json";
    if (file_exists($fallback)) {
        $lang = json_decode(file_get_contents($fallback), true);
    }
}

if (!is_array($lang)) {
    $lang = [];
}

function t($key, $default = '')
{
    global $lang;
    return $lang[$key] ?? $default;
}

function h($txt)
{
    return htmlspecialchars((string)$txt, ENT_QUOTES, 'UTF-8');
}

function leerJsonSeguro($ruta, $default = null)
{
    if (!file_exists($ruta)) {
        return $default;
    }

    $raw = file_get_contents($ruta);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    $json = json_decode($raw, true);
    return is_array($json) ? $json : $default;
}

/* =========================================================
   VALIDAR ARCHIVO
========================================================= */
$file = basename($_GET['file'] ?? '');
$path = __DIR__ . "/data_actividades/historial/" . $file;

$data = null;
$error = '';

if ($file === '') {
    $error = t('view_activity_invalid_file', 'Archivo inválido.');
} elseif (!file_exists($path)) {
    $error = t('view_activity_not_found', 'No se encontró el archivo de actividad.');
} else {
    $data = leerJsonSeguro($path);
    if (!is_array($data)) {
        $error = t('view_activity_invalid_json', 'Archivo JSON inválido.');
    }
}

/* =========================================================
   NORMALIZAR DATOS
========================================================= */
if ($data && !isset($data['actividad'])) {
    $data['actividad'] = [];
}
if ($data && !isset($data['participantes']) || !is_array($data['participantes'] ?? null)) {
    $data['participantes'] = [];
}

$actividad = $data['actividad'] ?? [];
$participantes = $data['participantes'] ?? [];

$nombreActividad      = $actividad['nombre'] ?? t('activity_default_name', 'Actividad');
$tipoActividad        = $actividad['tipo'] ?? '';
$moduloActividad      = $actividad['modulo'] ?? '';
$descripcionActividad = $actividad['descripcion'] ?? '';
$fechaInicio          = $actividad['fecha_inicio'] ?? '';
$fechaFin             = $actividad['fecha_fin'] ?? '';
$fechaProgramada      = $actividad['fecha_programada'] ?? '';
$fechaEvento          = $actividad['fecha_evento'] ?? '';
$horaEvento           = $actividad['hora_evento'] ?? '';
$lugarActividad       = $actividad['lugar'] ?? '';
$frecuenciaActividad  = $actividad['frecuencia'] ?? '';
$modoActividad        = $actividad['modo'] ?? '';
$qslActividad         = $actividad['qsl'] ?? '';
$estadoActividad      = $actividad['estado'] ?? '';
$origenActividad      = $actividad['origen'] ?? 'manual';
?>
<!doctype html>
<html lang="<?= h($idioma); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($titleSite) ?> - <?= h(t('view_activity_title', 'Ver Actividad')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <style>
        body {
            background: #f4f7fb;
        }

        .card-clean {
            border: 0;
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(0,0,0,.07);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 12px;
        }

        .info-item {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 12px;
            border: 1px solid #e9ecef;
        }

        .info-item .label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .info-item .value {
            font-size: 15px;
            font-weight: 600;
            color: #212529;
            word-break: break-word;
        }

        .badge-origin {
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">

        <?php include 'includes/sidebar-menu.php'; ?>

        <div class="col-12 col-md-10 p-3">

            <div class="d-flex align-items-center mb-3">
                <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu">☰</button>
                <h3 class="m-0">📂 <?= h(t('view_activity_heading', 'Detalles de la Actividad')); ?></h3>
            </div>

            <div class="mb-3 d-flex gap-2 flex-wrap">
                <a href="activity_log.php" class="btn btn-secondary"><?= h(t('back_button', 'Volver')); ?></a>
                <?php if ($file !== '' && !$error): ?>
                    <a href="download_activity.php?file=<?= urlencode($file) ?>" class="btn btn-success"><?= h(t('download_json_button', 'Descargar JSON')); ?></a>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= h($error) ?></div>
            <?php else: ?>

                <div class="card card-clean p-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0"><?= h($nombreActividad) ?></h5>

                        <?php if ($origenActividad === 'qsl_generator'): ?>
                            <span class="badge bg-primary badge-origin">QSL Generator</span>
                        <?php else: ?>
                            <span class="badge bg-secondary badge-origin"><?= h(t('manual_origin', 'Manual')); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="info-grid mb-4">
                        <div class="info-item">
                            <div class="label"><?= h(t('activity_name', 'Nombre de actividad')); ?></div>
                            <div class="value"><?= h($nombreActividad !== '' ? $nombreActividad : '-') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="label"><?= h(t('activity_type', 'Tipo')); ?></div>
                            <div class="value"><?= h($tipoActividad !== '' ? $tipoActividad : '-') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="label"><?= h(t('activity_module', 'Módulo / Sala')); ?></div>
                            <div class="value"><?= h($moduloActividad !== '' ? $moduloActividad : '-') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="label"><?= h(t('qsl_date', 'Fecha')); ?></div>
                            <div class="value"><?= h($fechaEvento !== '' ? $fechaEvento : '-') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="label"><?= h(t('qsl_time', 'Hora')); ?></div>
                            <div class="value"><?= h($horaEvento !== '' ? $horaEvento : '-') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="label"><?= h(t('qsl_place', 'Lugar')); ?></div>
                            <div class="value"><?= h($lugarActividad !== '' ? $lugarActividad : '-') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="label"><?= h(t('qsl_frequency', 'Frecuencia')); ?></div>
                            <div class="value"><?= h($frecuenciaActividad !== '' ? $frecuenciaActividad : '-') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="label"><?= h(t('qsl_mode', 'Modo')); ?></div>
                            <div class="value"><?= h($modoActividad !== '' ? $modoActividad : '-') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="label">QSL</div>
                            <div class="value"><?= h($qslActividad !== '' ? $qslActividad : '-') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="label"><?= h(t('activity_start_date', 'Fecha de inicio')); ?></div>
                            <div class="value"><?= h($fechaInicio !== '' ? $fechaInicio : '-') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="label"><?= h(t('activity_end_date', 'Fecha de término')); ?></div>
                            <div class="value"><?= h($fechaFin !== '' ? $fechaFin : '-') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="label"><?= h(t('activity_status', 'Estado')); ?></div>
                            <div class="value"><?= h($estadoActividad !== '' ? $estadoActividad : '-') ?></div>
                        </div>

                        <?php if ($fechaProgramada !== ''): ?>
                        <div class="info-item">
                            <div class="label"><?= h(t('scheduled_date', 'Programada')); ?></div>
                            <div class="value"><?= h($fechaProgramada) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="border rounded p-3 bg-light">
                        <strong><?= h(t('activity_description', 'Descripción')); ?>:</strong><br>
                        <?= $descripcionActividad !== '' ? nl2br(h($descripcionActividad)) : '-' ?>
                    </div>
                </div>

                <div class="card card-clean p-3">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="mb-0"><?= h(t('participant_list_title', 'Participantes registrados')); ?></h5>
                        <span class="badge text-bg-primary"><?= count($participantes); ?> <?= h(t('participant_count_label', 'participante(s)')); ?></span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <tr>
                                <th>#</th>
                                <th><?= h(t('table_time', 'Hora')); ?></th>
                                <th><?= h(t('table_callsign', 'Indicativo')); ?></th>
                                <th><?= h(t('table_name', 'Nombre')); ?></th>
                                <th><?= h(t('table_location', 'Ubicación')); ?></th>
                                <th><?= h(t('table_signal', 'Señal')); ?></th>
                                <th><?= h(t('table_mode', 'Modo')); ?></th>
                                <th><?= h(t('table_condition', 'Condición')); ?></th>
                                <th><?= h(t('table_power', 'Potencia')); ?></th>
                                <th><?= h(t('table_comment', 'Comentario')); ?></th>
                            </tr>

                            <?php foreach ($participantes as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= h($p['hora'] ?? '') ?></td>
                                <td><?= h($p['callsign'] ?? '') ?></td>
                                <td><?= h($p['nombre'] ?? '') ?></td>
                                <td><?= h($p['ubicacion'] ?? '') ?></td>
                                <td><?= h($p['senal'] ?? '') ?></td>
                                <td><?= h($p['modo'] ?? '') ?></td>
                                <td><?= h($p['condicion'] ?? '') ?></td>
                                <td><?= h($p['potencia'] ?? '') ?></td>
                                <td><?= h($p['comentario'] ?? '') ?></td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($participantes)): ?>
                            <tr>
                                <td colspan="10" class="text-muted"><?= h(t('participant_none_history', 'No se registraron participantes en esta actividad.')); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
