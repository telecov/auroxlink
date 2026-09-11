<?php

require 'includes/environment.php';
require_once 'includes/seismic-lib.php';

session_start();

date_default_timezone_set('America/Santiago');

/*
|--------------------------------------------------------------------------
| AUROXLINK 1.8.5 - Sismógrafo
|--------------------------------------------------------------------------
| Fuentes de datos:
| CSN Chile / USGS Mundial / modo AUTO
|
| La página:
| - Obtiene eventos desde includes/seismic-data.php
| - Calcula distancia desde el nodo AUROXLINK
| - Usa la MISMA lógica RF que seismic-monitor.php
| - Muestra clasificación LOCAL / REGIONAL / AMPLIA / NACIONAL
| - No transmite RF directamente desde la interfaz
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Idioma AUROXLINK
|--------------------------------------------------------------------------
*/

$idioma = 'es';

$estilosFile = __DIR__ . '/estilos.json';

if (is_file($estilosFile)) {
    $estilosRaw = file_get_contents($estilosFile);
    $estilos = json_decode((string)$estilosRaw, true);

    if (is_array($estilos)) {
        $idioma = $estilos['idioma'] ?? 'es';
    }
}

$langFile = __DIR__ . '/data/lang/' . $idioma . '.json';

$lang = [];

if (is_file($langFile)) {
    $langRaw = file_get_contents($langFile);
    $decodedLang = json_decode((string)$langRaw, true);

    if (is_array($decodedLang)) {
        $lang = $decodedLang;
    }
}

if (!function_exists('t')) {
    function t(string $key, string $fallback = ''): string
    {
        global $lang;

        return $lang[$key] ?? ($fallback !== '' ? $fallback : $key);
    }
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function h(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function magnitudeClass(float $magnitude): string
{
    if ($magnitude >= 6.0) {
        return 'danger';
    }

    if ($magnitude >= 5.0) {
        return 'warning';
    }

    if ($magnitude >= 4.0) {
        return 'info';
    }

    return 'success';
}


function rfClassificationBadge(
    ?string $classification,
    bool $rfEnabled
): string {

    if ($classification === null) {
        return
            '<span class="badge text-bg-secondary">' .
            'SIN ALERTA' .
            '</span>';
    }

    $map = [
        'local' => [
            'class' => 'text-bg-danger',
            'label' => 'LOCAL',
        ],

        'regional' => [
            'class' => 'text-bg-warning',
            'label' => 'REGIONAL',
        ],

        'wide' => [
            'class' => 'text-bg-info',
            'label' => 'AMPLIA',
        ],

        'national' => [
            'class' => 'text-bg-danger',
            'label' => 'NACIONAL',
        ],
    ];

    $item = $map[$classification] ?? [
        'class' => 'text-bg-secondary',
        'label' => strtoupper(
            (string)$classification
        ),
    ];

    $suffix = $rfEnabled
        ? ''
        : ' · RF OFF';

    return sprintf(
        '<span class="badge %s">%s%s</span>',
        h($item['class']),
        h($item['label']),
        h($suffix)
    );
}


/*
|--------------------------------------------------------------------------
| Cargar configuración real
|--------------------------------------------------------------------------
*/

$configError = null;

try {

    $seismicConfig = seismicLoadJson(
        AUROXLINK_SEISMIC_CONFIG
    );

} catch (Throwable $e) {

    $configError = $e->getMessage();

    /*
     * Fallback seguro.
     * RF queda apagado.
     */
    $seismicConfig = [

        'enabled' => false,

        'latitude' => -29.9027,
        'longitude' => -71.2519,

        'refresh_seconds' => 60,

        'display' => [
            'min_magnitude' => 2.5,
            'radius_km' => 3000,
        ],

        'rf' => [

            'enabled' => false,

            'command_pty' =>
                '/dev/shm/simplex_logic_ctrl',

            'local' => [
                'enabled' => true,
                'radius_km' => 200,
                'min_magnitude' => 3.5,
            ],

            'regional' => [
                'enabled' => true,
                'radius_km' => 500,
                'min_magnitude' => 4.2,
            ],

            'wide' => [
                'enabled' => true,
                'radius_km' => 1000,
                'min_magnitude' => 5.0,
            ],

            'national' => [
                'enabled' => true,
                'min_magnitude' => 6.0,
            ],

            'repetitions' => 1,
            'pre_tone' => true,
        ],

        'tts' => [
            'engine' => 'espeak-ng',
            'voice' => 'es',
            'speed' => 145,
        ],
    ];
}


/*
|--------------------------------------------------------------------------
| Variables configuración
|--------------------------------------------------------------------------
*/

$moduleEnabled = (bool)(
    $seismicConfig['enabled'] ?? false
);

$nodeLat = (float)(
    $seismicConfig['latitude'] ?? -29.9027
);

$nodeLon = (float)(
    $seismicConfig['longitude'] ?? -71.2519
);

$refreshSeconds = max(
    30,
    (int)(
        $seismicConfig['refresh_seconds']
        ?? 60
    )
);

$displayMinMagnitude = (float)(
    $seismicConfig['display']['min_magnitude']
    ?? 2.5
);

$displayRadiusKm = (float)(
    $seismicConfig['display']['radius_km']
    ?? 3000
);

$rfEnabled = (bool)(
    $seismicConfig['rf']['enabled']
    ?? false
);


/*
|--------------------------------------------------------------------------
| Estado del monitor
|--------------------------------------------------------------------------
*/

$seismicState = [];

try {

    $seismicState = seismicLoadJson(
        AUROXLINK_SEISMIC_STATE
    );

} catch (Throwable $e) {

    $seismicState = [];
}

$lastCheck = $seismicState['last_check'] ?? null;
$lastRfEvent = $seismicState['last_rf_event'] ?? null;

$processedCount = count(
    $seismicState['processed_events'] ?? []
);

$announcedCount = count(
    $seismicState['announced_events'] ?? []
);


/*
|--------------------------------------------------------------------------
| Consultar backend sísmico multifuente
|--------------------------------------------------------------------------
*/

$seismicDataUrl =
    'http://127.0.0.1/includes/seismic-data.php';

$sourceOk = false;
$sourceError = null;
$sourceGeneratedAt = null;

$rawEvents = [];

$providerSelected =
    strtolower((string)($seismicConfig['provider'] ?? 'auto'));

$providerResolved = '';
$sourceShort = '';
$fallbackUsed = false;

$ch = curl_init($seismicDataUrl);

if ($ch !== false) {

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,

            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],

            CURLOPT_USERAGENT =>
                'AUROXLINK-Sismografo/1.8.5',
        ]
    );

    $response = curl_exec($ch);

    $httpCode = (int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    if ($response === false) {

        $sourceError = curl_error($ch);

    } elseif ($httpCode !== 200) {

        $sourceError =
            'HTTP ' . $httpCode;

    } else {

        $json = json_decode(
            $response,
            true
        );

        if (
            is_array($json) &&
            ($json['success'] ?? false)
        ) {

            $sourceOk = true;

            $rawEvents =
                $json['events'] ?? [];

            $sourceGeneratedAt =
                $json['generated_at']
                ?? null;

            $providerSelected =
                strtolower((string)($json['provider'] ?? ($seismicConfig['provider'] ?? 'auto')));

            $providerResolved =
                strtolower((string)($json['provider_resolved'] ?? ''));

            $sourceShort =
                strtoupper((string)($json['source_short'] ?? ''));

            $fallbackUsed =
                (bool)($json['fallback_used'] ?? false);

            if ($providerResolved === '' && in_array(strtolower($sourceShort), ['csn', 'usgs'], true)) {
                $providerResolved = strtolower($sourceShort);
            }

            if ($sourceShort === '' && $providerResolved !== '') {
                $sourceShort = strtoupper($providerResolved);
            }

        } else {

            $sourceError =
                'Respuesta JSON inválida';
        }
    }

    curl_close($ch);

} else {

    $sourceError =
        'No fue posible iniciar cURL';
}


/*
|--------------------------------------------------------------------------
| Procesar eventos
|--------------------------------------------------------------------------
*/

$events = [];

foreach ($rawEvents as $event) {

    if (
        !isset(
            $event['latitude'],
            $event['longitude'],
            $event['magnitude']
        )
    ) {
        continue;
    }

    $eventLat =
        (float)$event['latitude'];

    $eventLon =
        (float)$event['longitude'];

    $magnitude =
        (float)$event['magnitude'];

    $distanceKm = seismicDistanceKm(
        $nodeLat,
        $nodeLon,
        $eventLat,
        $eventLon
    );

    /*
     * Filtro visual.
     *
     * Esto NO modifica la decisión del servicio
     * seismic-monitor.php.
     */
    if (
        $magnitude <
        $displayMinMagnitude
    ) {
        continue;
    }

    /*
     * CSN se visualiza respecto del radio configurado para el nodo.
     * USGS es una fuente mundial: no recortamos sus eventos por el
     * radio local, de lo contrario un feed válido podría quedar vacío.
     * La distancia al nodo se sigue calculando para la clasificación RF.
     */
    if (
        $providerResolved !== 'usgs' &&
        $distanceKm > $displayRadiusKm
    ) {
        continue;
    }

    /*
     * MISMA función utilizada por el monitor.
     */
    $classification =
        seismicRfClassification(
            $magnitude,
            $distanceKm,
            $seismicConfig
        );

    $event['distance_km'] =
        round($distanceKm, 1);

    $event['rf_classification'] =
        $classification;

    $event['rf_label'] =
        seismicClassificationLabel(
            $classification
        );

    $event['rf_would_alert'] =
        ($classification !== null);

    $event['rf_active_alert'] =
        (
            $rfEnabled &&
            $classification !== null
        );

    $events[] = $event;
}


/*
|--------------------------------------------------------------------------
| KPIs
|--------------------------------------------------------------------------
*/

$eventCount = count($events);

$latestEvent =
    $events[0] ?? null;

$maxMagnitude = null;
$maxMagnitudeEvent = null;

$rfCandidateCount = 0;

foreach ($events as $event) {

    $mag =
        (float)($event['magnitude'] ?? 0);

    if (
        $maxMagnitude === null ||
        $mag > $maxMagnitude
    ) {

        $maxMagnitude =
            $mag;

        $maxMagnitudeEvent =
            $event;
    }

    if (
        $event['rf_classification']
        !== null
    ) {
        $rfCandidateCount++;
    }
}


/*
|--------------------------------------------------------------------------
| Configuración de zonas
|--------------------------------------------------------------------------
*/

$localCfg =
    $seismicConfig['rf']['local']
    ?? [];

$regionalCfg =
    $seismicConfig['rf']['regional']
    ?? [];

$wideCfg =
    $seismicConfig['rf']['wide']
    ?? [];

$nationalCfg =
    $seismicConfig['rf']['national']
    ?? [];

$ttsCfg =
    $seismicConfig['tts']
    ?? [];

?>
<!doctype html>
<html lang="<?= h($idioma) ?>">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        http-equiv="refresh"
        content="<?= (int)$refreshSeconds ?>"
    >

    <title>
        AUROXLINK · Sismógrafo
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    >

    <link
        rel="stylesheet"
        href="style/style.css.php"
    >

    <style>

        .seismic-page {
            padding-bottom: 40px;
        }

        .seismic-header {
            margin-bottom: 24px;
        }

        .seismic-subtitle {
            opacity: .72;
            margin-bottom: 0;
        }

        .seismic-kpi {
            height: 100%;
        }

        .seismic-kpi-value {
            font-size: 1.7rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .seismic-kpi-label {
            font-size: .82rem;
            opacity: .7;
            margin-top: 7px;
        }

        #seismicMap {
            width: 100%;
            height: 470px;
            border-radius: 10px;
            overflow: hidden;
        }

        .quake-mag {
            min-width: 56px;
            text-align: center;
            font-weight: 700;
        }

        .quake-reference {
            min-width: 220px;
        }

        .distance-number {
            font-weight: 700;
            white-space: nowrap;
        }

        .config-box {
            border: 1px solid
                rgba(127,127,127,.22);
            border-radius: 10px;
            padding: 16px;
            height: 100%;
        }

        .config-box-title {
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            opacity: .65;
            margin-bottom: 5px;
        }

        .config-box-value {
            font-size: 1.08rem;
            font-weight: 700;
        }

        .rf-rule {
            border: 1px solid
                rgba(127,127,127,.22);
            border-radius: 10px;
            padding: 16px;
            height: 100%;
        }

        .rf-rule-name {
            font-weight: 700;
            margin-bottom: 6px;
        }

        .rf-rule-value {
            font-size: 1.05rem;
        }

        .map-legend {
            font-size: .85rem;
        }

        .legend-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .node-dot {
            background: #0d6efd;
        }

        .status-line {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        .table-responsive {
            border-radius: 10px;
        }

        .seismic-small {
            font-size: .83rem;
        }

        .rf-off-note {
            font-size: .82rem;
        }

    </style>

</head>

<body>

<div class="d-flex">

    <?php
    if (
        is_file(
            __DIR__ .
            '/includes/sidebar-menu.php'
        )
    ) {
        include
            __DIR__ .
            '/includes/sidebar-menu.php';
    }
    ?>


    <main
        class="flex-grow-1 p-3 p-md-4 seismic-page"
    >

        <!-- =========================================================
             ENCABEZADO
        ========================================================== -->

        <div
            class="seismic-header d-flex justify-content-between align-items-start flex-wrap gap-3"
        >

            <div>

                <h2 class="mb-1">
                    🌎 Sismógrafo
                </h2>

                <p class="seismic-subtitle">

                    Monitoreo sísmico AUROXLINK ·
                    CSN Chile / USGS Mundial

                </p>

            </div>

            <div class="text-end">

                <div class="status-line">

                    <?php if ($sourceOk): ?>

                        <span
                            class="badge text-bg-success"
                        >
                            ● <?= h($sourceShort !== '' ? $sourceShort : 'FUENTE') ?> ONLINE
                        </span>

                    <?php else: ?>

                        <span
                            class="badge text-bg-danger"
                        >
                            ● <?= h($sourceShort !== '' ? $sourceShort : 'FUENTE') ?> SIN DATOS
                        </span>

                    <?php endif; ?>


                    <?php if ($rfEnabled): ?>

                        <span
                            class="badge text-bg-danger"
                        >
                            📻 RF ACTIVO
                        </span>

                    <?php else: ?>

                        <span
                            class="badge text-bg-secondary"
                        >
                            📻 RF OFF
                        </span>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <?php if ($configError !== null): ?>

            <div class="alert alert-danger">

                <strong>
                    Error de configuración:
                </strong>

                <?= h($configError) ?>

            </div>

        <?php endif; ?>


        <?php if (!$sourceOk): ?>

            <div class="alert alert-danger">

                <strong>
                    No fue posible obtener datos sísmicos.
                </strong>

                <?= h(
                    (string)$sourceError
                ) ?>

            </div>

        <?php endif; ?>


        <div class="card shadow-sm mb-4">
            <div class="card-body py-3">
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <div>
                        <span class="text-body-secondary small">Fuente seleccionada</span><br>
                        <strong><?= h(strtoupper($providerSelected)) ?></strong>
                    </div>
                    <div>
                        <span class="text-body-secondary small">Fuente activa</span><br>
                        <strong><?= h($sourceShort !== '' ? $sourceShort : '—') ?></strong>
                    </div>
                    <?php if ($fallbackUsed): ?>
                        <span class="badge text-bg-warning">FALLBACK ACTIVO</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>


        <!-- =========================================================
             KPI
        ========================================================== -->

        <div class="row g-3 mb-4">

            <div class="col-6 col-xl-3">

                <div
                    class="card seismic-kpi shadow-sm"
                >

                    <div class="card-body">

                        <div
                            class="seismic-kpi-value"
                        >
                            <?= $eventCount ?>
                        </div>

                        <div
                            class="seismic-kpi-label"
                        >
                            Eventos visibles
                        </div>

                    </div>

                </div>

            </div>


            <div class="col-6 col-xl-3">

                <div
                    class="card seismic-kpi shadow-sm"
                >

                    <div class="card-body">

                        <div
                            class="seismic-kpi-value"
                        >

                            <?php
                            if ($latestEvent !== null) {
                                echo
                                    'M' .
                                    number_format(
                                        (float)$latestEvent['magnitude'],
                                        1
                                    );
                            } else {
                                echo '—';
                            }
                            ?>

                        </div>

                        <div
                            class="seismic-kpi-label"
                        >
                            Último evento
                        </div>

                    </div>

                </div>

            </div>


            <div class="col-6 col-xl-3">

                <div
                    class="card seismic-kpi shadow-sm"
                >

                    <div class="card-body">

                        <div
                            class="seismic-kpi-value"
                        >

                            <?php
                            if ($maxMagnitude !== null) {

                                echo
                                    'M' .
                                    number_format(
                                        $maxMagnitude,
                                        1
                                    );

                            } else {

                                echo '—';
                            }
                            ?>

                        </div>

                        <div
                            class="seismic-kpi-label"
                        >
                            Mayor magnitud
                        </div>

                    </div>

                </div>

            </div>


            <div class="col-6 col-xl-3">

                <div
                    class="card seismic-kpi shadow-sm"
                >

                    <div class="card-body">

                        <div
                            class="seismic-kpi-value"
                        >
                            <?= $rfCandidateCount ?>
                        </div>

                        <div
                            class="seismic-kpi-label"
                        >
                            Candidatos RF
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =========================================================
             MAPA
        ========================================================== -->

        <div class="card shadow-sm mb-4">

            <div
                class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"
            >

                <div>

                    <strong>
                        Mapa de eventos recientes
                    </strong>

                </div>

                <div class="map-legend">

                    <span class="me-3">

                        <span
                            class="legend-dot node-dot"
                        ></span>

                        Nodo AUROXLINK

                    </span>

                    <span>
                        Radio visual:
                        <?= number_format(
                            $displayRadiusKm,
                            0,
                            ',',
                            '.'
                        ) ?>
                        km
                    </span>

                </div>

            </div>

            <div class="card-body">

                <div id="seismicMap"></div>

            </div>

        </div>


        <!-- =========================================================
             EVENTOS
        ========================================================== -->

        <div class="card shadow-sm mb-4">

            <div
                class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"
            >

                <div>

                    <strong>
                        Últimos eventos · <?= h($sourceShort !== '' ? $sourceShort : 'FUENTE') ?>
                    </strong>

                </div>

                <div
                    class="small text-body-secondary"
                >

                    <?php if ($sourceGeneratedAt): ?>

                        Consulta:
                        <?= h(
                            (string)$sourceGeneratedAt
                        ) ?>

                    <?php else: ?>

                        Actualización automática cada
                        <?= $refreshSeconds ?>
                        s

                    <?php endif; ?>

                </div>

            </div>


            <div class="card-body p-0">

                <div class="table-responsive">

                    <table
                        class="table table-hover mb-0"
                    >

                        <thead>

                        <tr>

                            <th>
                                Magnitud
                            </th>

                            <th>
                                Referencia
                            </th>

                            <th>
                                Distancia
                            </th>

                            <th>
                                Prof.
                            </th>

                            <th>
                                Hora local
                            </th>

                            <th>
                                Alerta RF
                            </th>

                        </tr>

                        </thead>

                        <tbody>

                        <?php if (!$events): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="text-center py-4 text-body-secondary"
                                >

                                    No hay eventos que cumplan
                                    el filtro de visualización.

                                </td>

                            </tr>

                        <?php endif; ?>


                        <?php foreach ($events as $event): ?>

                            <?php

                            $magnitude =
                                (float)(
                                    $event['magnitude']
                                    ?? 0
                                );

                            $depth =
                                (float)(
                                    $event['depth']
                                    ?? 0
                                );

                            $reference =
                                (string)(
                                    $event['reference']
                                    ?? 'Sin referencia'
                                );

                            $localTime =
                                (string)(
                                    $event['local_time']
                                    ?? '—'
                                );

                            $distance =
                                (float)(
                                    $event['distance_km']
                                    ?? 0
                                );

                            $classification =
                                $event[
                                    'rf_classification'
                                ] ?? null;

                            ?>

                            <tr>

                                <td>

                                    <span
                                        class="badge text-bg-<?= h(
                                            magnitudeClass(
                                                $magnitude
                                            )
                                        ) ?> quake-mag"
                                    >

                                        M<?= number_format(
                                            $magnitude,
                                            1
                                        ) ?>

                                    </span>

                                </td>


                                <td
                                    class="quake-reference"
                                >

                                    <div class="fw-semibold">

                                        <?= h(
                                            $reference
                                        ) ?>

                                    </div>

                                    <div
                                        class="small text-body-secondary"
                                    >

                                        <?= h(
                                            (string)(
                                                $event[
                                                    'magnitude_type'
                                                ]
                                                ?? ''
                                            )
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <span
                                        class="distance-number"
                                    >

                                        <?= number_format(
                                            $distance,
                                            0,
                                            ',',
                                            '.'
                                        ) ?>

                                        km

                                    </span>

                                </td>


                                <td>

                                    <?= number_format(
                                        $depth,
                                        0,
                                        ',',
                                        '.'
                                    ) ?>

                                    km

                                </td>


                                <td>

                                    <?= h($localTime) ?>

                                </td>


                                <td>

                                    <?= rfClassificationBadge(
                                        $classification,
                                        $rfEnabled
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>


        <!-- =========================================================
             CONFIGURACIÓN GENERAL
        ========================================================== -->

        <div class="card shadow-sm mb-4">

            <div class="card-header">

                <strong>
                    Configuración del nodo
                </strong>

            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                Latitud
                            </div>

                            <div
                                class="config-box-value"
                            >
                                <?= number_format(
                                    $nodeLat,
                                    4,
                                    '.',
                                    ''
                                ) ?>
                            </div>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                Longitud
                            </div>

                            <div
                                class="config-box-value"
                            >
                                <?= number_format(
                                    $nodeLon,
                                    4,
                                    '.',
                                    ''
                                ) ?>
                            </div>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                Magnitud mapa
                            </div>

                            <div
                                class="config-box-value"
                            >
                                ≥ M<?= number_format(
                                    $displayMinMagnitude,
                                    1
                                ) ?>
                            </div>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                Radio visual
                            </div>

                            <div
                                class="config-box-value"
                            >

                                <?= number_format(
                                    $displayRadiusKm,
                                    0,
                                    ',',
                                    '.'
                                ) ?>

                                km

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- =========================================================
             ALERTAS RF
        ========================================================== -->

        <div class="card shadow-sm mb-4">

            <div
                class="card-header d-flex justify-content-between align-items-center"
            >

                <strong>
                    📻 Alertas sísmicas por RF
                </strong>


                <?php if ($rfEnabled): ?>

                    <span
                        class="badge text-bg-success"
                    >
                        ACTIVADAS
                    </span>

                <?php else: ?>

                    <span
                        class="badge text-bg-secondary"
                    >
                        DESACTIVADAS
                    </span>

                <?php endif; ?>

            </div>


            <div class="card-body">

                <?php if ($rfEnabled): ?>

                    <div
                        class="alert alert-success"
                    >

                        📻 Las alertas sísmicas por RF
                        están activadas.

                        Los eventos nuevos que cumplan
                        estas reglas pueden ser enviados
                        a SvxLink.

                    </div>

                <?php else: ?>

                    <div
                        class="alert alert-warning"
                    >

                        📻 Las alertas RF están
                        <strong>desactivadas</strong>.

                        AUROXLINK sigue clasificando
                        los eventos para que puedas
                        verificar qué habría transmitido.

                    </div>

                <?php endif; ?>


                <div class="row g-3">

                    <!-- LOCAL -->

                    <div class="col-md-6 col-xl-3">

                        <div class="rf-rule">

                            <div class="rf-rule-name">
                                🔴 LOCAL
                            </div>

                            <div class="rf-rule-value">

                                ≤ <?= (int)(
                                    $localCfg['radius_km']
                                    ?? 0
                                ) ?>
                                km

                            </div>

                            <div>

                                Magnitud ≥ M<?= number_format(
                                    (float)(
                                        $localCfg[
                                            'min_magnitude'
                                        ] ?? 0
                                    ),
                                    1
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <!-- REGIONAL -->

                    <div class="col-md-6 col-xl-3">

                        <div class="rf-rule">

                            <div class="rf-rule-name">
                                🟠 REGIONAL
                            </div>

                            <div class="rf-rule-value">

                                ≤ <?= (int)(
                                    $regionalCfg[
                                        'radius_km'
                                    ] ?? 0
                                ) ?>
                                km

                            </div>

                            <div>

                                Magnitud ≥ M<?= number_format(
                                    (float)(
                                        $regionalCfg[
                                            'min_magnitude'
                                        ] ?? 0
                                    ),
                                    1
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <!-- AMPLIA -->

                    <div class="col-md-6 col-xl-3">

                        <div class="rf-rule">

                            <div class="rf-rule-name">
                                🔵 AMPLIA
                            </div>

                            <div class="rf-rule-value">

                                ≤ <?= (int)(
                                    $wideCfg['radius_km']
                                    ?? 0
                                ) ?>
                                km

                            </div>

                            <div>

                                Magnitud ≥ M<?= number_format(
                                    (float)(
                                        $wideCfg[
                                            'min_magnitude'
                                        ] ?? 0
                                    ),
                                    1
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <!-- NACIONAL -->

                    <div class="col-md-6 col-xl-3">

                        <div class="rf-rule">

                            <div class="rf-rule-name">
                                🇨🇱 NACIONAL
                            </div>

                            <div class="rf-rule-value">

                                Todo Chile

                            </div>

                            <div>

                                Magnitud ≥ M<?= number_format(
                                    (float)(
                                        $nationalCfg[
                                            'min_magnitude'
                                        ] ?? 0
                                    ),
                                    1
                                ) ?>

                            </div>

                        </div>

                    </div>

                </div>


                <hr>


                <div class="row g-3">

                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                COMMAND PTY
                            </div>

                            <div
                                class="small fw-semibold text-break"
                            >

                                <?= h(
                                    (string)(
                                        $seismicConfig[
                                            'rf'
                                        ][
                                            'command_pty'
                                        ]
                                        ??
                                        '/dev/shm/simplex_logic_ctrl'
                                    )
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                Repeticiones
                            </div>

                            <div
                                class="config-box-value"
                            >

                                <?= (int)(
                                    $seismicConfig[
                                        'rf'
                                    ][
                                        'repetitions'
                                    ]
                                    ?? 1
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                Motor TTS
                            </div>

                            <div
                                class="config-box-value"
                            >

                                <?= h(
                                    (string)(
                                        $ttsCfg['engine']
                                        ?? 'espeak-ng'
                                    )
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                Intervalo
                            </div>

                            <div
                                class="config-box-value"
                            >

                                <?= $refreshSeconds ?>
                                s

                            </div>

                        </div>

                    </div>

                </div>


                <div
                    class="mt-3 rf-off-note text-body-secondary"
                >

                    La transmisión automática es
                    ejecutada por
                    <code>
                        auroxlink-seismic.service
                    </code>.

                    Esta página solamente muestra
                    la clasificación y estado.

                </div>

            </div>

        </div>


        <!-- =========================================================
             ESTADO DEL MONITOR
        ========================================================== -->

        <div class="card shadow-sm">

            <div class="card-header">

                <strong>
                    Estado del monitor AUROXLINK
                </strong>

            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                Inicializado
                            </div>

                            <div
                                class="config-box-value"
                            >

                                <?php
                                echo
                                    (
                                        $seismicState[
                                            'initialized'
                                        ]
                                        ?? false
                                    )
                                    ? 'SÍ'
                                    : 'NO';
                                ?>

                            </div>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                Procesados
                            </div>

                            <div
                                class="config-box-value"
                            >
                                <?= $processedCount ?>
                            </div>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                Anunciados RF
                            </div>

                            <div
                                class="config-box-value"
                            >
                                <?= $announcedCount ?>
                            </div>

                        </div>

                    </div>


                    <div class="col-md-3">

                        <div class="config-box">

                            <div
                                class="config-box-title"
                            >
                                Última revisión
                            </div>

                            <div
                                class="small fw-semibold"
                            >

                                <?= $lastCheck
                                    ? h(
                                        (string)$lastCheck
                                    )
                                    : '—'
                                ?>

                            </div>

                        </div>

                    </div>

                </div>


                <?php if (
                    is_array($lastRfEvent)
                ): ?>

                    <div
                        class="alert alert-info mt-3 mb-0"
                    >

                        <strong>
                            Última transmisión RF:
                        </strong>

                        <?= h(
                            (string)(
                                $lastRfEvent['id']
                                ?? '—'
                            )
                        ) ?>

                        · M<?= number_format(
                            (float)(
                                $lastRfEvent[
                                    'magnitude'
                                ] ?? 0
                            ),
                            1
                        ) ?>

                        · <?= number_format(
                            (float)(
                                $lastRfEvent[
                                    'distance_km'
                                ] ?? 0
                            ),
                            0,
                            ',',
                            '.'
                        ) ?> km

                        · <?= h(
                            strtoupper(
                                (string)(
                                    $lastRfEvent[
                                        'classification'
                                    ] ?? ''
                                )
                            )
                        ) ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
></script>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
></script>


<script>

/*
|--------------------------------------------------------------------------
| Mapa
|--------------------------------------------------------------------------
*/

const nodeLat = <?= json_encode($nodeLat) ?>;
const nodeLon = <?= json_encode($nodeLon) ?>;
const providerResolved = <?= json_encode($providerResolved) ?>;

const events = <?= json_encode(
    $events,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
) ?>;


const map = L.map('seismicMap', {
    zoomControl: true
}).setView(
    [nodeLat, nodeLon],
    5
);


L.tileLayer(
    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    {
        maxZoom: 18,

        attribution:
            '&copy; OpenStreetMap contributors'
    }
).addTo(map);


/*
|--------------------------------------------------------------------------
| Nodo AUROXLINK
|--------------------------------------------------------------------------
*/

const nodeMarker = L.circleMarker(
    [nodeLat, nodeLon],
    {
        radius: 8,
        color: '#0d6efd',
        weight: 3,
        fillColor: '#0d6efd',
        fillOpacity: 0.85
    }
).addTo(map);

nodeMarker.bindPopup(
    '<strong>AUROXLINK</strong><br>' +
    'Nodo de referencia<br>' +
    nodeLat.toFixed(4) + ', ' +
    nodeLon.toFixed(4)
);


/*
|--------------------------------------------------------------------------
| Color por magnitud
|--------------------------------------------------------------------------
*/

function quakeColor(magnitude) {

    magnitude =
        parseFloat(magnitude);

    if (magnitude >= 6) {
        return '#dc3545';
    }

    if (magnitude >= 5) {
        return '#fd7e14';
    }

    if (magnitude >= 4) {
        return '#ffc107';
    }

    return '#198754';
}


/*
|--------------------------------------------------------------------------
| Eventos sísmicos
|--------------------------------------------------------------------------
*/

const quakeBounds = [
    [nodeLat, nodeLon]
];

events.forEach(event => {

    const lat =
        parseFloat(event.latitude);

    const lon =
        parseFloat(event.longitude);

    const mag =
        parseFloat(event.magnitude);

    const distance =
        parseFloat(event.distance_km);

    if (
        Number.isNaN(lat) ||
        Number.isNaN(lon)
    ) {
        return;
    }

    quakeBounds.push([lat, lon]);

    const color =
        quakeColor(mag);

    const radius =
        Math.max(
            5,
            4 + mag * 1.6
        );

    const marker =
        L.circleMarker(
            [lat, lon],
            {
                radius: radius,
                color: color,
                weight: 2,
                fillColor: color,
                fillOpacity: 0.60
            }
        ).addTo(map);

    const reference =
        event.reference ||
        'Sin referencia';

    const classification =
        event.rf_label ||
        'SIN ALERTA';

    marker.bindPopup(
        '<strong>M' +
        mag.toFixed(1) +
        '</strong><br>' +

        reference +
        '<br>' +

        '<strong>Distancia:</strong> ' +
        Math.round(distance) +
        ' km<br>' +

        '<strong>Profundidad:</strong> ' +
        Math.round(
            parseFloat(
                event.depth || 0
            )
        ) +
        ' km<br>' +

        '<strong>RF:</strong> ' +
        classification
    );

});

if (
    providerResolved === 'usgs' &&
    quakeBounds.length > 1
) {
    map.fitBounds(
        quakeBounds,
        {
            padding: [35, 35],
            maxZoom: 6
        }
    );
}


/*
|--------------------------------------------------------------------------
| Dibujar radio local RF
|--------------------------------------------------------------------------
*/

const localRadius =
    <?= json_encode(
        (float)(
            $localCfg['radius_km']
            ?? 0
        )
    ) ?>;

if (localRadius > 0) {

    L.circle(
        [nodeLat, nodeLon],
        {
            radius:
                localRadius * 1000,

            color: '#dc3545',
            weight: 1,
            opacity: 0.55,
            fillOpacity: 0.025,
            dashArray: '6,6'
        }
    ).addTo(map);

}


/*
|--------------------------------------------------------------------------
| Radio regional
|--------------------------------------------------------------------------
*/

const regionalRadius =
    <?= json_encode(
        (float)(
            $regionalCfg['radius_km']
            ?? 0
        )
    ) ?>;

if (regionalRadius > 0) {

    L.circle(
        [nodeLat, nodeLon],
        {
            radius:
                regionalRadius * 1000,

            color: '#fd7e14',
            weight: 1,
            opacity: 0.40,
            fillOpacity: 0.015,
            dashArray: '8,8'
        }
    ).addTo(map);

}

</script>

</body>
</html>
