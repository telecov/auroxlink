<?php

header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

/* =========================================================
   AUROXLINK 1.8.5
   SEISMIC DATA PROVIDER
   - CSN Chile
   - USGS Worldwide
   - AUTO: CSN en Chile / USGS fuera de Chile
========================================================= */

const CSN_BASE_URL = 'https://www.sismologia.cl';
const CSN_HOME_URL = 'https://www.sismologia.cl/';

const USGS_FEED_URL =
    'https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/all_day.geojson';

const SEISMIC_CONFIG_FILE =
    __DIR__ . '/../data/seismic_config.json';

/* =========================================================
   HTTP GET
========================================================= */

function httpGet($url)
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'AUROXLINK/1.8.5 Seismic Monitor'
    ]);

    $response = curl_exec($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $error = curl_error($ch);

    curl_close($ch);

    if (
        $response === false ||
        $httpCode < 200 ||
        $httpCode >= 300
    ) {
        return [
            'success' => false,
            'error' => $error ?: "HTTP $httpCode",
            'content' => null
        ];
    }

    return [
        'success' => true,
        'error' => null,
        'content' => $response
    ];
}

/* =========================================================
   CONFIGURACIÓN
========================================================= */

function getSeismicConfig()
{
    $config = [
        'provider' => 'auto',
        'latitude' => -29.9027,
        'longitude' => -71.2519
    ];

    if (!is_file(SEISMIC_CONFIG_FILE)) {
        return $config;
    }

    $raw = @file_get_contents(SEISMIC_CONFIG_FILE);

    if ($raw === false) {
        return $config;
    }

    $json = json_decode($raw, true);

    if (!is_array($json)) {
        return $config;
    }

    if (isset($json['provider'])) {
        $provider = strtolower(trim((string)$json['provider']));

        if (in_array($provider, ['auto', 'csn', 'usgs'], true)) {
            $config['provider'] = $provider;
        }
    }

    if (isset($json['latitude']) && is_numeric($json['latitude'])) {
        $config['latitude'] = (float)$json['latitude'];
    }

    if (isset($json['longitude']) && is_numeric($json['longitude'])) {
        $config['longitude'] = (float)$json['longitude'];
    }

    return $config;
}

function isNodeInChile($latitude, $longitude)
{
    /*
     * Caja geográfica aproximada para decidir proveedor automático.
     * No se usa para determinar si un sismo pertenece políticamente a Chile.
     */
    return (
        $latitude >= -56.5 &&
        $latitude <= -17.0 &&
        $longitude >= -76.5 &&
        $longitude <= -66.0
    );
}

/* =========================================================
   NORMALIZAR TEXTO
========================================================= */

function cleanText($text)
{
    $text = html_entity_decode(
        $text,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $text = strip_tags($text);

    $text = preg_replace(
        '/\s+/u',
        ' ',
        $text
    );

    return trim($text);
}

/* =========================================================
   CSN — CÓDIGO ORIGINAL CONSERVADO
========================================================= */

function extractDetailValue(
    DOMXPath $xpath,
    $label
) {
    $nodes = $xpath->query(
        "//tr[td[1][contains(normalize-space(.), '$label')]]/td[2]"
    );

    if ($nodes && $nodes->length > 0) {
        return cleanText(
            $nodes->item(0)->textContent
        );
    }

    return null;
}

function getEventDetail($url)
{
    $response = httpGet($url);

    if (!$response['success']) {
        return null;
    }

    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    $dom->loadHTML(
        $response['content'],
        LIBXML_NOWARNING |
        LIBXML_NOERROR
    );

    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    $reference = extractDetailValue(
        $xpath,
        'Referencia'
    );

    $localTime = extractDetailValue(
        $xpath,
        'Hora Local'
    );

    $utcTime = extractDetailValue(
        $xpath,
        'Hora UTC'
    );

    $latitude = extractDetailValue(
        $xpath,
        'Latitud'
    );

    $longitude = extractDetailValue(
        $xpath,
        'Longitud'
    );

    $depth = extractDetailValue(
        $xpath,
        'Profundidad'
    );

    $magnitude = extractDetailValue(
        $xpath,
        'Magnitud'
    );

    if (
        $latitude === null ||
        $longitude === null ||
        $magnitude === null
    ) {
        return null;
    }

    preg_match(
        '/([0-9]+(?:\.[0-9]+)?)/',
        $magnitude,
        $magMatch
    );

    $magValue =
        isset($magMatch[1])
        ? (float)$magMatch[1]
        : null;

    preg_match(
        '/[0-9.]+\s*([A-Za-z]+)/',
        $magnitude,
        $typeMatch
    );

    $magType =
        isset($typeMatch[1])
        ? $typeMatch[1]
        : null;

    preg_match(
        '/([0-9]+(?:\.[0-9]+)?)/',
        $depth ?? '',
        $depthMatch
    );

    $depthValue =
        isset($depthMatch[1])
        ? (float)$depthMatch[1]
        : null;

    $eventId = basename(
        parse_url(
            $url,
            PHP_URL_PATH
        ),
        '.html'
    );

    return [
        'id' => 'csn-' . $eventId,
        'source' => 'CSN',
        'reference' => $reference,
        'local_time' => $localTime,
        'utc_time' => $utcTime,
        'latitude' => (float)$latitude,
        'longitude' => (float)$longitude,
        'depth' => $depthValue,
        'magnitude' => $magValue,
        'magnitude_type' => $magType,
        'detail_url' => $url
    ];
}

function getLatestEventLinks()
{
    $response = httpGet(
        CSN_HOME_URL
    );

    if (!$response['success']) {
        return [
            'success' => false,
            'error' => $response['error'],
            'links' => []
        ];
    }

    libxml_use_internal_errors(true);

    $dom = new DOMDocument();

    $dom->loadHTML(
        $response['content'],
        LIBXML_NOWARNING |
        LIBXML_NOERROR
    );

    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    $nodes = $xpath->query(
        "//a[contains(@href, '/sismicidad/informes/')]"
    );

    $links = [];

    foreach ($nodes as $node) {

        $href = trim(
            $node->getAttribute('href')
        );

        if (!$href) {
            continue;
        }

        if (
            !preg_match(
                '#/sismicidad/informes/[0-9]{4}/[0-9]{2}/[0-9]+\.html$#',
                $href
            )
        ) {
            continue;
        }

        if (
            strpos(
                $href,
                'http'
            ) === 0
        ) {
            $url = $href;
        } else {
            $url =
                CSN_BASE_URL .
                '/' .
                ltrim(
                    $href,
                    '/'
                );
        }

        if (
            !in_array(
                $url,
                $links,
                true
            )
        ) {
            $links[] = $url;
        }
    }

    return [
        'success' => true,
        'error' => null,
        'links' => $links
    ];
}

function getCsnEvents()
{
    $result = getLatestEventLinks();

    if (!$result['success']) {
        return [
            'success' => false,
            'error' =>
                'No fue posible consultar el Centro Sismológico Nacional: ' .
                $result['error'],
            'events' => []
        ];
    }

    $links = array_slice(
        $result['links'],
        0,
        15
    );

    $events = [];

    foreach ($links as $url) {

        $event = getEventDetail(
            $url
        );

        if ($event !== null) {
            $events[] = $event;
        }
    }

    if (count($events) === 0) {
        return [
            'success' => false,
            'error' => 'CSN no entregó eventos utilizables',
            'events' => []
        ];
    }

    return [
        'success' => true,
        'error' => null,
        'events' => $events
    ];
}

/* =========================================================
   USGS — GEOJSON MUNDIAL
========================================================= */

function getUsgsEvents()
{
    $response = httpGet(
        USGS_FEED_URL
    );

    if (!$response['success']) {
        return [
            'success' => false,
            'error' =>
                'No fue posible consultar USGS: ' .
                $response['error'],
            'events' => []
        ];
    }

    $json = json_decode(
        $response['content'],
        true
    );

    if (
        !is_array($json) ||
        !isset($json['features']) ||
        !is_array($json['features'])
    ) {
        return [
            'success' => false,
            'error' => 'USGS entregó una respuesta GeoJSON no válida',
            'events' => []
        ];
    }

    $events = [];

    foreach ($json['features'] as $feature) {

        if (
            !isset($feature['id']) ||
            !isset($feature['properties']) ||
            !isset($feature['geometry']['coordinates']) ||
            !is_array($feature['geometry']['coordinates'])
        ) {
            continue;
        }

        $properties = $feature['properties'];
        $coordinates = $feature['geometry']['coordinates'];

        if (
            count($coordinates) < 3 ||
            !isset($properties['mag']) ||
            !is_numeric($properties['mag'])
        ) {
            continue;
        }

        $longitude = (float)$coordinates[0];
        $latitude = (float)$coordinates[1];
        $depth = is_numeric($coordinates[2])
            ? (float)$coordinates[2]
            : null;

        $timestampMs =
            isset($properties['time']) &&
            is_numeric($properties['time'])
            ? (int)$properties['time']
            : null;

        $timestamp =
            $timestampMs !== null
            ? (int)floor($timestampMs / 1000)
            : null;

        /*
         * Por compatibilidad con AUROXLINK:
         * - utc_time siempre UTC.
         * - local_time usa la zona horaria configurada en PHP.
         *
         * Más adelante podemos agregar zona horaria del nodo al Settings.
         */
        $utcTime =
            $timestamp !== null
            ? gmdate('H:i:s d/m/Y', $timestamp)
            : null;

        $localTime =
            $timestamp !== null
            ? date('H:i:s d/m/Y', $timestamp)
            : null;

        $magType =
            isset($properties['magType'])
            ? (string)$properties['magType']
            : null;

        $reference =
            isset($properties['place'])
            ? cleanText((string)$properties['place'])
            : 'Ubicación no informada';

        $detailUrl = null;

        if (
            isset($properties['url']) &&
            filter_var($properties['url'], FILTER_VALIDATE_URL)
        ) {
            $detailUrl = $properties['url'];
        } elseif (
            isset($properties['detail']) &&
            filter_var($properties['detail'], FILTER_VALIDATE_URL)
        ) {
            $detailUrl = $properties['detail'];
        }

        $events[] = [
            'id' => 'usgs-' . (string)$feature['id'],
            'source' => 'USGS',
            'reference' => $reference,
            'local_time' => $localTime,
            'utc_time' => $utcTime,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'depth' => $depth,
            'magnitude' => (float)$properties['mag'],
            'magnitude_type' => $magType,
            'detail_url' => $detailUrl
        ];

        if (count($events) >= 15) {
            break;
        }
    }

    if (count($events) === 0) {
        return [
            'success' => false,
            'error' => 'USGS no entregó eventos utilizables',
            'events' => []
        ];
    }

    return [
        'success' => true,
        'error' => null,
        'events' => $events
    ];
}

/* =========================================================
   RESPUESTA
========================================================= */

function sendResponse(
    $providerRequested,
    $providerResolved,
    $source,
    array $events,
    $fallbackUsed = false,
    array $warnings = []
) {
    echo json_encode(
        [
            'success' => true,
            'provider' => $providerRequested,
            'provider_resolved' => $providerResolved,
            'fallback_used' => $fallbackUsed,
            'source' => $source,
            'source_short' => strtoupper($providerResolved),
            'generated_at' => date('c'),
            'count' => count($events),
            'events' => $events,
            'warnings' => $warnings
        ],
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

function sendError(
    $providerRequested,
    array $errors
) {
    http_response_code(502);

    echo json_encode(
        [
            'success' => false,
            'error' => 'No fue posible obtener datos sísmicos.',
            'generated_at' => date('c'),
            'provider' => $providerRequested,
            'errors' => $errors
        ],
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

/* =========================================================
   MAIN
========================================================= */

$config = getSeismicConfig();

$providerRequested =
    strtolower(
        trim(
            (string)(
                $_GET['provider'] ??
                $config['provider'] ??
                'auto'
            )
        )
    );

if (
    !in_array(
        $providerRequested,
        ['auto', 'csn', 'usgs'],
        true
    )
) {
    $providerRequested = 'auto';
}

/* ---------------------------------------------------------
   PROVEEDOR MANUAL: CSN
--------------------------------------------------------- */

if ($providerRequested === 'csn') {

    $result = getCsnEvents();

    if (!$result['success']) {
        sendError(
            'csn',
            [$result['error']]
        );
    }

    sendResponse(
        'csn',
        'csn',
        'Centro Sismológico Nacional - Universidad de Chile',
        $result['events']
    );
}

/* ---------------------------------------------------------
   PROVEEDOR MANUAL: USGS
--------------------------------------------------------- */

if ($providerRequested === 'usgs') {

    $result = getUsgsEvents();

    if (!$result['success']) {
        sendError(
            'usgs',
            [$result['error']]
        );
    }

    sendResponse(
        'usgs',
        'usgs',
        'United States Geological Survey',
        $result['events']
    );
}

/* ---------------------------------------------------------
   AUTO
--------------------------------------------------------- */

$nodeInChile = isNodeInChile(
    (float)$config['latitude'],
    (float)$config['longitude']
);

if ($nodeInChile) {

    /*
     * En Chile preferimos CSN.
     * Si CSN falla, USGS queda como respaldo.
     */

    $csn = getCsnEvents();

    if ($csn['success']) {
        sendResponse(
            'auto',
            'csn',
            'Centro Sismológico Nacional - Universidad de Chile',
            $csn['events']
        );
    }

    $usgs = getUsgsEvents();

    if ($usgs['success']) {
        sendResponse(
            'auto',
            'usgs',
            'United States Geological Survey',
            $usgs['events'],
            true,
            [
                'CSN no disponible: ' .
                $csn['error']
            ]
        );
    }

    sendError(
        'auto',
        [
            $csn['error'],
            $usgs['error']
        ]
    );
}

/*
 * Nodo fuera de Chile:
 * USGS es la fuente primaria.
 */

$usgs = getUsgsEvents();

if ($usgs['success']) {
    sendResponse(
        'auto',
        'usgs',
        'United States Geological Survey',
        $usgs['events']
    );
}

sendError(
    'auto',
    [$usgs['error']]
);
