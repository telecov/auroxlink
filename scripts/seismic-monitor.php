#!/usr/bin/env php
<?php

declare(strict_types=1);
date_default_timezone_set('America/Santiago');

require_once '/var/www/html/includes/seismic-lib.php';

const SEISMIC_BACKEND_URL =
    'http://127.0.0.1/includes/seismic-data.php';

function monitorLog(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function fetchSeismicData(): array
{
    $ch = curl_init(SEISMIC_BACKEND_URL);

    if ($ch === false) {
        throw new RuntimeException('No fue posible iniciar cURL');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'AUROXLINK-Seismic-Monitor/1.8.5',
    ]);

    $body = curl_exec($ch);

    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException(
            "Error consultando backend sísmico: {$error}"
        );
    }

    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException(
            "Backend sísmico respondió HTTP {$httpCode}"
        );
    }

    $data = json_decode($body, true);

    if (!is_array($data)) {
        throw new RuntimeException('Respuesta JSON inválida del backend');
    }

    if (!($data['success'] ?? false)) {
        throw new RuntimeException('El backend sísmico reportó error');
    }

    $data['events'] =
        isset($data['events']) && is_array($data['events'])
        ? $data['events']
        : [];

    $data['provider_resolved'] =
        strtolower(trim((string)($data['provider_resolved'] ?? '')));

    $data['source_short'] =
        strtoupper(trim((string)($data['source_short'] ?? '')));

    /*
     * Compatibilidad con el backend CSN antiguo:
     * si no entrega provider_resolved, inferimos la fuente.
     */
    if ($data['provider_resolved'] === '') {
        $source = strtolower($data['source_short']);

        if (in_array($source, ['csn', 'usgs'], true)) {
            $data['provider_resolved'] = $source;
        }
    }

    if ($data['source_short'] === '' && $data['provider_resolved'] !== '') {
        $data['source_short'] = strtoupper($data['provider_resolved']);
    }

    return $data;
}

function eventId(array $event): string
{
    return trim((string)($event['id'] ?? ''));
}

function normalizeState(array $state): array
{
    $state['initialized'] =
        (bool)($state['initialized'] ?? false);

    $state['provider_resolved'] =
        strtolower(trim((string)($state['provider_resolved'] ?? '')));

    $state['processed_events'] =
        array_values(
            array_unique(
                array_filter($state['processed_events'] ?? [])
            )
        );

    $state['announced_events'] =
        array_values(
            array_unique(
                array_filter($state['announced_events'] ?? [])
            )
        );

    $state['last_check'] = $state['last_check'] ?? null;
    $state['last_event'] = $state['last_event'] ?? null;
    $state['last_rf_event'] = $state['last_rf_event'] ?? null;

    return $state;
}

function limitStateHistory(array &$state): void
{
    $state['processed_events'] =
        array_slice(
            array_values(array_unique($state['processed_events'])),
            -500
        );

    $state['announced_events'] =
        array_slice(
            array_values(array_unique($state['announced_events'])),
            -500
        );
}

function saveMonitorState(array &$state): void
{
    $state['last_check'] = date(DATE_ATOM);
    limitStateHistory($state);

    seismicSaveJson(
        AUROXLINK_SEISMIC_STATE,
        $state
    );
}

function seedCurrentEvents(
    array &$state,
    array $events,
    string $providerResolved
): void {
    foreach ($events as $event) {
        $id = eventId($event);

        if ($id !== '') {
            $state['processed_events'][] = $id;
        }
    }

    $state['initialized'] = true;
    $state['provider_resolved'] = $providerResolved;
    $state['last_event'] =
        isset($events[0]) ? eventId($events[0]) : $state['last_event'];

    saveMonitorState($state);
}

function testRf(array $config): void
{
    $pty = (string)(
        $config['rf']['command_pty']
        ?? '/dev/shm/simplex_logic_ctrl'
    );

    monitorLog('PRUEBA RF MANUAL');
    monitorLog("COMMAND_PTY: {$pty}");

    $text =
        'Atención. Prueba del monitor sísmico AUROXLINK. ' .
        'Sistema de transmisión por radio operativo.';

    monitorLog("Locución: {$text}");

    $wav = seismicGenerateAudio($text, $config);
    monitorLog("Audio generado: {$wav}");

    seismicTransmitFile($wav, $pty, 1);

    monitorLog('Comando enviado correctamente a SvxLink.');
}

function testSeismicEvent(
    array $config,
    string $requestedId
): void {
    $requestedId = trim($requestedId);

    if ($requestedId === '') {
        throw new RuntimeException(
            'Debe indicar un ID de evento sísmico.'
        );
    }

    monitorLog("PRUEBA DE EVENTO REAL: {$requestedId}");

    $data = fetchSeismicData();
    $events = $data['events'];
    $source = $data['source_short'] ?: 'DESCONOCIDA';

    monitorLog(
        "Eventos recibidos desde {$source}: " . count($events)
    );

    $selectedEvent = null;

    foreach ($events as $event) {
        if (eventId($event) === $requestedId) {
            $selectedEvent = $event;
            break;
        }
    }

    if ($selectedEvent === null) {
        throw new RuntimeException(
            "No se encontró {$requestedId} entre los eventos " .
            'actualmente entregados por el backend.'
        );
    }

    $nodeLat = (float)($config['latitude'] ?? 0);
    $nodeLon = (float)($config['longitude'] ?? 0);
    $magnitude = (float)($selectedEvent['magnitude'] ?? 0);
    $latitude = (float)($selectedEvent['latitude'] ?? 0);
    $longitude = (float)($selectedEvent['longitude'] ?? 0);
    $depth = (float)($selectedEvent['depth'] ?? 0);
    $reference =
        (string)($selectedEvent['reference'] ?? 'Ubicación desconocida');

    $distance = seismicDistanceKm(
        $nodeLat,
        $nodeLon,
        $latitude,
        $longitude
    );

    $classification = seismicRfClassification(
        $magnitude,
        $distance,
        $config
    );

    monitorLog(
        sprintf(
            '%s | M%.1f | Prof. %.1f km | Dist. %.0f km | %s | %s',
            $requestedId,
            $magnitude,
            $depth,
            $distance,
            seismicClassificationLabel($classification),
            $reference
        )
    );

    $realAnnouncement = seismicBuildAnnouncement(
        $selectedEvent,
        $distance
    );

    $announcement =
        'Atención. Esta es una prueba del sistema sísmico AUROXLINK. ' .
        $realAnnouncement .
        ' Fin de la prueba.';

    monitorLog("LOCUCIÓN DE PRUEBA: {$announcement}");

    $wav = seismicGenerateAudio($announcement, $config);
    monitorLog("Audio generado: {$wav}");

    $pty = (string)(
        $config['rf']['command_pty']
        ?? '/dev/shm/simplex_logic_ctrl'
    );

    monitorLog("COMMAND_PTY: {$pty}");

    seismicTransmitFile($wav, $pty, 1);

    monitorLog('Evento de prueba enviado correctamente a SvxLink.');
    monitorLog('seismic_state.json NO fue modificado.');
}

function runMonitor(): void
{
    $config = seismicLoadJson(AUROXLINK_SEISMIC_CONFIG);

    $state = normalizeState(
        seismicLoadJson(AUROXLINK_SEISMIC_STATE)
    );

    $data = fetchSeismicData();
    $events = $data['events'];

    $providerResolved =
        strtolower(trim((string)$data['provider_resolved']));

    $source =
        $data['source_short'] !== ''
        ? $data['source_short']
        : ($providerResolved !== '' ? strtoupper($providerResolved) : 'DESCONOCIDA');

    monitorLog(
        "Eventos recibidos desde {$source}: " . count($events)
    );

    if (!$events) {
        saveMonitorState($state);
        return;
    }

    /*
     * PRIMER ARRANQUE:
     * sembramos los eventos actuales sin transmitir históricos.
     */
    if (!$state['initialized']) {
        seedCurrentEvents(
            $state,
            $events,
            $providerResolved
        );

        monitorLog(
            'Primera ejecución: eventos existentes registrados.'
        );
        monitorLog(
            'NO se transmitieron eventos históricos.'
        );

        return;
    }

    /*
     * MIGRACIÓN SEGURA DESDE ESTADOS ANTERIORES:
     * El estado viejo no tenía provider_resolved.
     *
     * No consideramos esto un "cambio de proveedor"; simplemente
     * registramos la fuente actual. Los IDs CSN ya existentes se
     * mantienen y el procesamiento normal continúa.
     */
    if (
        $state['provider_resolved'] === '' &&
        $providerResolved !== ''
    ) {
        $state['provider_resolved'] = $providerResolved;
        saveMonitorState($state);

        monitorLog(
            "Fuente inicial registrada en estado: {$source}."
        );
    }

    /*
     * CAMBIO DE PROVEEDOR:
     * Si AUTO cambia CSN <-> USGS, los IDs pertenecen a otra fuente.
     * Sembramos toda la tanda actual como procesada y NO transmitimos
     * nada durante este ciclo. Esto evita ráfagas de eventos históricos.
     */
    if (
        $providerResolved !== '' &&
        $state['provider_resolved'] !== '' &&
        $providerResolved !== $state['provider_resolved']
    ) {
        $oldProvider = strtoupper($state['provider_resolved']);

        monitorLog(
            "CAMBIO DE PROVEEDOR: {$oldProvider} -> {$source}."
        );

        seedCurrentEvents(
            $state,
            $events,
            $providerResolved
        );

        monitorLog(
            'Eventos actuales registrados como ya procesados.'
        );
        monitorLog(
            'NO se transmitieron eventos RF durante el cambio de proveedor.'
        );

        return;
    }

    $nodeLat = (float)($config['latitude'] ?? 0);
    $nodeLon = (float)($config['longitude'] ?? 0);

    $rfEnabled =
        (bool)($config['rf']['enabled'] ?? false);

    $eventsChronological = array_reverse($events);

    foreach ($eventsChronological as $event) {
        $id = eventId($event);

        if ($id === '') {
            continue;
        }

        if (
            in_array(
                $id,
                $state['processed_events'],
                true
            )
        ) {
            continue;
        }

        $magnitude = (float)($event['magnitude'] ?? 0);
        $latitude = (float)($event['latitude'] ?? 0);
        $longitude = (float)($event['longitude'] ?? 0);
        $reference =
            (string)($event['reference'] ?? 'Ubicación desconocida');

        $distance = seismicDistanceKm(
            $nodeLat,
            $nodeLon,
            $latitude,
            $longitude
        );

        $classification = seismicRfClassification(
            $magnitude,
            $distance,
            $config
        );

        monitorLog(
            sprintf(
                '%s | M%.1f | %.0f km | %s | %s',
                $id,
                $magnitude,
                $distance,
                seismicClassificationLabel($classification),
                $reference
            )
        );

        /*
         * Si NO califica, queda procesado y guardado inmediatamente.
         */
        if ($classification === null) {
            $state['processed_events'][] = $id;
            $state['last_event'] = $id;

            saveMonitorState($state);

            monitorLog(
                "{$id}: no cumple criterios de alerta RF."
            );

            continue;
        }

        /*
         * Si RF está apagado, también queda procesado inmediatamente.
         */
        if (!$rfEnabled) {
            $state['processed_events'][] = $id;
            $state['last_event'] = $id;

            saveMonitorState($state);

            monitorLog(
                "{$id}: cumple criterio " .
                seismicClassificationLabel($classification) .
                ', pero RF está DESACTIVADO.'
            );

            continue;
        }

        /*
         * Defensa adicional: si ya figura como anunciado, no repetir RF.
         * También lo consolidamos como procesado.
         */
        if (
            in_array(
                $id,
                $state['announced_events'],
                true
            )
        ) {
            $state['processed_events'][] = $id;
            $state['last_event'] = $id;

            saveMonitorState($state);

            monitorLog(
                "{$id}: ya fue anunciado anteriormente."
            );

            continue;
        }

        /*
         * IMPORTANTE:
         * Para eventos RF NO marcamos processed_events antes de transmitir.
         * Si la generación/transmisión falla, el evento queda pendiente
         * y podrá reintentarse en el siguiente ciclo.
         */
        $announcement = seismicBuildAnnouncement(
            $event,
            $distance
        );

        monitorLog(
            "GENERANDO ALERTA RF: {$announcement}"
        );

        $wav = seismicGenerateAudio(
            $announcement,
            $config
        );

        $pty = (string)(
            $config['rf']['command_pty']
            ?? '/dev/shm/simplex_logic_ctrl'
        );

        $repetitions =
            (int)($config['rf']['repetitions'] ?? 1);

        seismicTransmitFile(
            $wav,
            $pty,
            $repetitions
        );

        /*
         * Sólo después de una transmisión sin excepción consolidamos
         * announced + processed y guardamos inmediatamente.
         */
        $state['announced_events'][] = $id;
        $state['processed_events'][] = $id;
        $state['last_event'] = $id;

        $state['last_rf_event'] = [
            'id' => $id,
            'time' => date(DATE_ATOM),
            'magnitude' => $magnitude,
            'distance_km' => round($distance, 1),
            'classification' => $classification,
            'reference' => $reference,
            'audio' => $wav,
        ];

        saveMonitorState($state);

        monitorLog(
            "{$id}: ALERTA RF enviada y estado guardado."
        );
    }

    /*
     * Actualiza last_check aunque no hayan aparecido eventos nuevos.
     */
    saveMonitorState($state);

    seismicCleanupAudio();
}

/* ---------------------------------------------------------
   MAIN
--------------------------------------------------------- */

try {
    $config = seismicLoadJson(
        AUROXLINK_SEISMIC_CONFIG
    );

    if (
        isset($argv[1]) &&
        $argv[1] === '--test-rf'
    ) {
        testRf($config);
        exit(0);
    }

    if (
        isset($argv[1]) &&
        $argv[1] === '--test-event'
    ) {
        if (
            !isset($argv[2]) ||
            trim((string)$argv[2]) === ''
        ) {
            throw new RuntimeException(
                'Uso: seismic-monitor.php --test-event csn-XXXXXX|usgs-XXXX'
            );
        }

        testSeismicEvent(
            $config,
            (string)$argv[2]
        );

        exit(0);
    }

    runMonitor();
    exit(0);

} catch (Throwable $e) {
    monitorLog(
        'ERROR: ' . $e->getMessage()
    );

    exit(1);
}
