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

/* =========================================================
   PATHS
========================================================= */
$dataDir = __DIR__ . '/data_actividades';
$activeFile = $dataDir . '/actividad_activa.json';
$historyDir = $dataDir . '/historial';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}
if (!is_dir($historyDir)) {
    mkdir($historyDir, 0775, true);
}

/* =========================================================
   HELPERS
========================================================= */
function h($t)
{
    return htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
}

function readJson($file, $default = null)
{
    if (!file_exists($file)) {
        return $default;
    }

    $raw = file_get_contents($file);
    if ($raw === false || trim($raw) === '') {
        return $default;
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

function saveJson($file, $data)
{
    $dir = dirname($file);

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return file_put_contents(
        $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    ) !== false;
}

/* =========================================================
   ADD PARTICIPANT
========================================================= */
if (isset($_POST['add'])) {
    $data = readJson($activeFile);

    if ($data) {
        $callsign = strtoupper(trim($_POST['callsign'] ?? ''));
        $name = trim($_POST['participant_name'] ?? '');

        if ($callsign !== '' && $name !== '') {
            if (!isset($data['participantes']) || !is_array($data['participantes'])) {
                $data['participantes'] = [];
            }

            $data['participantes'][] = [
                'id'         => uniqid('p_', true),
                'hora'       => date('H:i'),
                'callsign'   => $callsign,
                'nombre'     => $name,
                'ubicacion'  => trim($_POST['location'] ?? ''),
                'senal'      => trim($_POST['signal'] ?? ''),
                'modo'       => trim($_POST['mode'] ?? ''),
                'condicion'  => trim($_POST['condition'] ?? ''),
                'potencia'   => trim($_POST['power'] ?? ''),
                'comentario' => trim($_POST['comment'] ?? '')
            ];

            saveJson($activeFile, $data);
        }
    }

    header("Location: activity_log.php");
    exit;
}

/* =========================================================
   DELETE PARTICIPANT
========================================================= */
if (isset($_POST['delete_participant'])) {
    $participantId = $_POST['participant_id'] ?? '';
    $data = readJson($activeFile);

    if ($data && $participantId !== '') {
        $data['participantes'] = array_values(array_filter(
            $data['participantes'] ?? [],
            function ($p) use ($participantId) {
                return ($p['id'] ?? '') !== $participantId;
            }
        ));

        saveJson($activeFile, $data);
    }

    header("Location: activity_log.php");
    exit;
}

/* =========================================================
   FINISH ACTIVITY
========================================================= */
if (isset($_POST['finish'])) {
    $data = readJson($activeFile);

    if ($data) {
        if (!isset($data['actividad']) || !is_array($data['actividad'])) {
            $data['actividad'] = [];
        }

        $data['actividad']['estado'] = 'finished';
        $data['actividad']['fecha_fin'] = date('Y-m-d H:i:s');

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['actividad']['nombre'] ?? 'activity');
        $file = $historyDir . '/' . date('Ymd_His') . '_' . $safeName . '.json';

        if (saveJson($file, $data)) {
            if (file_exists($activeFile)) {
                unlink($activeFile);
            }
        }
    }

    header("Location: activity_log.php");
    exit;
}

/* =========================================================
   LOAD DATA
========================================================= */
$active = readJson($activeFile);
$files = glob($historyDir . '/*.json');
rsort($files);

/* =========================================================
   NORMALIZE ACTIVE DATA
========================================================= */
if ($active && !isset($active['actividad'])) {
    $active['actividad'] = [];
}
if ($active && !isset($active['participantes'])) {
    $active['participantes'] = [];
}

$actividad = $active['actividad'] ?? [];
$participantes = $active['participantes'] ?? [];

$nombreActividad       = $actividad['nombre'] ?? '';
$tipoActividad         = $actividad['tipo'] ?? '';
$moduloActividad       = $actividad['modulo'] ?? '';
$descripcionActividad  = $actividad['descripcion'] ?? '';
$fechaProgramada       = $actividad['fecha_programada'] ?? '';
$fechaEvento           = $actividad['fecha_evento'] ?? '';
$horaEvento            = $actividad['hora_evento'] ?? '';
$lugarActividad        = $actividad['lugar'] ?? '';
$frecuenciaActividad   = $actividad['frecuencia'] ?? '';
$modoActividad         = $actividad['modo'] ?? '';
$qslActividad          = $actividad['qsl'] ?? '';
$fechaInicio           = $actividad['fecha_inicio'] ?? '';
$fechaFin              = $actividad['fecha_fin'] ?? '';
$origenActividad       = $actividad['origen'] ?? 'qsl_generator';
?>
<!doctype html>
<html lang="<?= h($idioma); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($titleSite) ?> - <?= h(t('activity_log_title', 'Activity Log')); ?></title>
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
            box-shadow: 0 8px 24px rgba(0, 0, 0, .07);
        }

        .hero {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: #fff;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .10);
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
                <h3 class="m-0">📡 <?= h(t('activity_log_title', 'Activity Log')); ?></h3>
            </div>

            <div class="hero mb-4">
                <h4 class="mb-2"><?= h(t('activity_log_heading', 'Radio Activity Log')); ?></h4>
                <p class="mb-0"><?= h(t('activity_log_subtitle', 'Create activities in QSL Generator, register participants here and keep a historical record.')); ?></p>
            </div>

            <div class="row g-4">
                <div class="col-lg-4">

                    <?php if (!$active): ?>
                    <div class="card card-clean p-3 mb-4">
                        <div class="alert alert-info mb-0">
                            <strong><?= h(t('activity_no_active_title', 'No active activity')); ?></strong><br>
                            <?= h(t('activity_no_active_message', 'To start a new activity, create it first from QSL Generator. Once created, it will appear here automatically.')); ?>
                        </div>

                        <a href="qsl_generator.php" class="btn btn-primary w-100 mt-3">
                            <?= h(t('go_to_qsl_generator', 'Go to QSL Generator')); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($active): ?>
                    <div class="card card-clean p-3 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <div class="alert alert-success mb-0 flex-grow-1">
                                <strong><?= h(t('activity_active_label', 'Active activity')); ?>:</strong>
                                <?= h($nombreActividad) ?>
                            </div>

                            <span class="badge bg-primary badge-origin">QSL Generator</span>
                        </div>

                        <div class="info-grid mb-4">
                            <div class="info-item">
                                <div class="label"><?= h(t('activity_name', 'Activity Name')); ?></div>
                                <div class="value"><?= h($nombreActividad !== '' ? $nombreActividad : '-') ?></div>
                            </div>

                            <div class="info-item">
                                <div class="label"><?= h(t('activity_type', 'Type')); ?></div>
                                <div class="value"><?= h($tipoActividad !== '' ? $tipoActividad : '-') ?></div>
                            </div>

                            <div class="info-item">
                                <div class="label"><?= h(t('activity_module', 'Module / Room')); ?></div>
                                <div class="value"><?= h($moduloActividad !== '' ? $moduloActividad : '-') ?></div>
                            </div>

                            <div class="info-item">
                                <div class="label"><?= h(t('qsl_date', 'Date')); ?></div>
                                <div class="value"><?= h($fechaEvento !== '' ? $fechaEvento : '-') ?></div>
                            </div>

                            <div class="info-item">
                                <div class="label"><?= h(t('qsl_time', 'Time')); ?></div>
                                <div class="value"><?= h($horaEvento !== '' ? $horaEvento : '-') ?></div>
                            </div>

                            <div class="info-item">
                                <div class="label"><?= h(t('qsl_place', 'Place')); ?></div>
                                <div class="value"><?= h($lugarActividad !== '' ? $lugarActividad : '-') ?></div>
                            </div>

                            <div class="info-item">
                                <div class="label"><?= h(t('qsl_frequency', 'Frequency')); ?></div>
                                <div class="value"><?= h($frecuenciaActividad !== '' ? $frecuenciaActividad : '-') ?></div>
                            </div>

                            <div class="info-item">
                                <div class="label"><?= h(t('qsl_mode', 'Mode')); ?></div>
                                <div class="value"><?= h($modoActividad !== '' ? $modoActividad : '-') ?></div>
                            </div>

                            <div class="info-item">
                                <div class="label">QSL</div>
                                <div class="value"><?= h($qslActividad !== '' ? $qslActividad : '-') ?></div>
                            </div>

                            <div class="info-item">
                                <div class="label"><?= h(t('activity_start_date', 'Start Date')); ?></div>
                                <div class="value"><?= h($fechaInicio !== '' ? $fechaInicio : '-') ?></div>
                            </div>

                            <?php if ($fechaProgramada !== ''): ?>
                            <div class="info-item">
                                <div class="label"><?= h(t('scheduled_date', 'Scheduled')); ?></div>
                                <div class="value"><?= h($fechaProgramada) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($descripcionActividad !== ''): ?>
                        <div class="mb-4">
                            <label class="form-label fw-bold"><?= h(t('activity_description', 'Description')); ?></label>
                            <div class="border rounded p-3 bg-light">
                                <?= nl2br(h($descripcionActividad)); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <h5 class="mb-3"><?= h(t('participant_add_title', 'Add Participant')); ?></h5>

                        <form method="POST">
                            <label class="form-label"><?= h(t('participant_callsign', 'Callsign')); ?></label>
                            <input name="callsign" class="form-control mb-2" placeholder="CA2RDP" required>

                            <label class="form-label"><?= h(t('participant_name', 'Name')); ?></label>
                            <input name="participant_name" class="form-control mb-2" placeholder="<?= h(t('participant_name_placeholder', 'Operator name')); ?>" required>

                            <label class="form-label"><?= h(t('participant_location', 'Location')); ?></label>
                            <input name="location" class="form-control mb-2" placeholder="<?= h(t('participant_location_placeholder', 'City / Country')); ?>">

                            <label class="form-label"><?= h(t('participant_signal', 'Signal')); ?></label>
                            <input name="signal" class="form-control mb-2" placeholder="5/9">

                            <label class="form-label"><?= h(t('participant_mode', 'Mode')); ?></label>
                            <input name="mode" class="form-control mb-2" placeholder="FM / DMR / YSF / P25">

                            <label class="form-label"><?= h(t('participant_condition', 'Condition')); ?></label>
                            <input name="condition" class="form-control mb-2" placeholder="<?= h(t('participant_condition_placeholder', 'Mobile / Base / Portable')); ?>">

                            <label class="form-label"><?= h(t('participant_power', 'Power')); ?></label>
                            <input name="power" class="form-control mb-2" placeholder="5W / 25W / 50W">

                            <label class="form-label"><?= h(t('participant_comment', 'Comment')); ?></label>
                            <textarea name="comment" class="form-control mb-3" rows="3" placeholder="<?= h(t('participant_comment_placeholder', 'Observations about the contact')); ?>"></textarea>

                            <button name="add" class="btn btn-success w-100"><?= h(t('participant_add_button', 'Add Participant')); ?></button>
                        </form>

                        <form method="POST" class="mt-3" onsubmit="return confirm('<?= h(t('activity_finish_confirm', 'Are you sure you want to finish this activity?')); ?>');">
                            <button name="finish" class="btn btn-danger w-100"><?= h(t('activity_finish_button', 'Finish Activity')); ?></button>
                        </form>
                    </div>
                    <?php endif; ?>

                </div>

                <div class="col-lg-8">

                    <div class="card card-clean p-3 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="mb-0"><?= h(t('participant_list_title', 'Registered Participants')); ?></h5>
                            <?php if ($active): ?>
                                <span class="badge text-bg-primary"><?= count($participantes); ?> <?= h(t('participant_count_label', 'participant(s)')); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($active && !empty($participantes)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <tr>
                                    <th>#</th>
                                    <th><?= h(t('table_time', 'Time')); ?></th>
                                    <th><?= h(t('table_callsign', 'Callsign')); ?></th>
                                    <th><?= h(t('table_name', 'Name')); ?></th>
                                    <th><?= h(t('table_location', 'Location')); ?></th>
                                    <th><?= h(t('table_signal', 'Signal')); ?></th>
                                    <th><?= h(t('table_mode', 'Mode')); ?></th>
                                    <th><?= h(t('table_condition', 'Condition')); ?></th>
                                    <th><?= h(t('table_power', 'Power')); ?></th>
                                    <th><?= h(t('table_comment', 'Comment')); ?></th>
                                    <th><?= h(t('table_action', 'Action')); ?></th>
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
                                    <td>
                                        <form method="POST" onsubmit="return confirm('<?= h(t('participant_delete_confirm', 'Delete this participant?')); ?>');">
                                            <input type="hidden" name="participant_id" value="<?= h($p['id'] ?? ''); ?>">
                                            <button name="delete_participant" class="btn btn-sm btn-outline-danger"><?= h(t('participant_delete_button', 'Delete')); ?></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                        <?php elseif ($active): ?>
                            <div class="alert alert-light border mb-0"><?= h(t('participant_none_active', 'No participants have been registered yet.')); ?></div>
                        <?php else: ?>
                            <div class="alert alert-light border mb-0"><?= h(t('participant_none_no_activity', 'There is no active activity. Create one in QSL Generator to begin registering participants.')); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="card card-clean p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="mb-0"><?= h(t('history_title', 'Activity History')); ?></h5>
                            <a href="activities_backup.php" class="btn btn-dark btn-sm"><?= h(t('history_backup_button', 'Download Backup ZIP')); ?></a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <tr>
                                    <th><?= h(t('history_file', 'File')); ?></th>
                                    <th><?= h(t('history_actions', 'Actions')); ?></th>
                                </tr>

                                <?php foreach ($files as $f): ?>
                                <tr>
                                    <td><?= h(basename($f)) ?></td>
                                    <td>
                                        <a href="view_activity.php?file=<?= urlencode(basename($f)) ?>" class="btn btn-sm btn-primary"><?= h(t('history_view_button', 'View')); ?></a>
                                        <a href="download_activity.php?file=<?= urlencode(basename($f)) ?>" class="btn btn-sm btn-success"><?= h(t('history_download_button', 'Download')); ?></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($files)): ?>
                                <tr>
                                    <td colspan="2" class="text-muted"><?= h(t('history_empty', 'No historical activities yet.')); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
