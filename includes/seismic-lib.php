<?php

declare(strict_types=1);

const AUROXLINK_SEISMIC_CONFIG = '/var/www/html/data/seismic_config.json';
const AUROXLINK_SEISMIC_STATE  = '/var/www/html/data/seismic_state.json';
const AUROXLINK_SEISMIC_AUDIO_DIR = '/var/lib/auroxlink/seismic';

const AUROXLINK_PIPER_BIN =
    '/opt/auroxlink/piper-venv/bin/piper';

const AUROXLINK_PIPER_DEFAULT_MODEL =
    '/opt/auroxlink/piper/voices/es_ES-davefx-medium.onnx';

function seismicLoadJson(string $file): array
{
    if (!is_file($file)) {
        throw new RuntimeException("Archivo no encontrado: {$file}");
    }

    $raw = file_get_contents($file);

    if ($raw === false) {
        throw new RuntimeException("No se pudo leer: {$file}");
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        throw new RuntimeException(
            "JSON inválido en {$file}: " . json_last_error_msg()
        );
    }

    return $data;
}

function seismicSaveJson(string $file, array $data): void
{
    $dir = dirname($file);

    if (!is_dir($dir)) {
        throw new RuntimeException("Directorio no existe: {$dir}");
    }

    $json = json_encode(
        $data,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        throw new RuntimeException('No fue posible generar JSON');
    }

    $tmp = $file . '.tmp.' . getmypid();

    if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException("No fue posible escribir: {$tmp}");
    }

    if (!rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException("No fue posible reemplazar: {$file}");
    }
}

function seismicDistanceKm(
    float $lat1,
    float $lon1,
    float $lat2,
    float $lon2
): float {
    $earthRadius = 6371.0;

    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);

    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLon = deg2rad($lon2 - $lon1);

    $a =
        sin($deltaLat / 2) ** 2 +
        cos($lat1Rad) *
        cos($lat2Rad) *
        sin($deltaLon / 2) ** 2;

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
}

function seismicRfClassification(
    float $magnitude,
    float $distanceKm,
    array $config
): ?string {
    $rf = $config['rf'] ?? [];

    $local = $rf['local'] ?? [];

    if (
        ($local['enabled'] ?? false) &&
        $distanceKm <= (float)($local['radius_km'] ?? 0) &&
        $magnitude >= (float)($local['min_magnitude'] ?? PHP_FLOAT_MAX)
    ) {
        return 'local';
    }

    $regional = $rf['regional'] ?? [];

    if (
        ($regional['enabled'] ?? false) &&
        $distanceKm <= (float)($regional['radius_km'] ?? 0) &&
        $magnitude >= (float)($regional['min_magnitude'] ?? PHP_FLOAT_MAX)
    ) {
        return 'regional';
    }

    $wide = $rf['wide'] ?? [];

    if (
        ($wide['enabled'] ?? false) &&
        $distanceKm <= (float)($wide['radius_km'] ?? 0) &&
        $magnitude >= (float)($wide['min_magnitude'] ?? PHP_FLOAT_MAX)
    ) {
        return 'wide';
    }

    $national = $rf['national'] ?? [];

    if (
        ($national['enabled'] ?? false) &&
        $magnitude >= (float)($national['min_magnitude'] ?? PHP_FLOAT_MAX)
    ) {
        return 'national';
    }

    return null;
}

function seismicClassificationLabel(?string $classification): string
{
    return match ($classification) {
        'local'    => 'LOCAL',
        'regional' => 'REGIONAL',
        'wide'     => 'AMPLIA',
        'national' => 'NACIONAL',
        default    => 'SIN ALERTA',
    };
}

function seismicSpeechReference(string $reference): string
{
    $text = trim($reference);

    $directions = [
        'N'  => 'al norte',
        'S'  => 'al sur',
        'E'  => 'al este',
        'O'  => 'al oeste',
        'NE' => 'al noreste',
        'NO' => 'al noroeste',
        'SE' => 'al sureste',
        'SO' => 'al suroeste',
    ];

    foreach (['NE', 'NO', 'SE', 'SO', 'N', 'S', 'E', 'O'] as $abbr) {
        $replacement = $directions[$abbr];

        $text = preg_replace(
            '/\b' . preg_quote($abbr, '/') . '\s+de\b/ui',
            $replacement . ' de',
            $text
        ) ?? $text;
    }

    $text = preg_replace(
        '/\b([0-9]+)\s*km\b/ui',
        '$1 kilómetros',
        $text
    ) ?? $text;

    return $text;
}

function seismicMagnitudeForSpeech(float $magnitude): string
{
    $formatted = number_format($magnitude, 1, '.', '');

    return str_replace('.', ' punto ', $formatted);
}

function seismicBuildAnnouncement(array $event, float $distanceKm): string
{
    $magnitude = (float)($event['magnitude'] ?? 0);
    $depth = (int)round((float)($event['depth'] ?? 0));
    $reference = seismicSpeechReference(
        (string)($event['reference'] ?? 'ubicación no determinada')
    );

    return sprintf(
        'Atención. AUROXLINK informa evento sísmico. ' .
        'Magnitud %s. %s. Profundidad %d kilómetros. ' .
        'Información del Centro Sismológico Nacional.',
        seismicMagnitudeForSpeech($magnitude),
        $reference,
        $depth
    );
}

function seismicEnsureAudioDir(): void
{
    if (is_dir(AUROXLINK_SEISMIC_AUDIO_DIR)) {
        return;
    }

    if (
        !mkdir(
            AUROXLINK_SEISMIC_AUDIO_DIR,
            0775,
            true
        ) &&
        !is_dir(AUROXLINK_SEISMIC_AUDIO_DIR)
    ) {
        throw new RuntimeException(
            'No fue posible crear el directorio de audio'
        );
    }
}

function seismicResolvePiperModel(array $tts): string
{
    $configuredModel = trim(
        (string)($tts['model'] ?? '')
    );

    if ($configuredModel !== '') {
        return $configuredModel;
    }

    $voice = trim(
        (string)($tts['voice'] ?? '')
    );

    if ($voice !== '') {
        $candidate =
            '/opt/auroxlink/piper/voices/' .
            basename($voice) .
            '.onnx';

        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return AUROXLINK_PIPER_DEFAULT_MODEL;
}

function seismicGenerateAudioPiper(
    string $text,
    array $tts,
    string $raw,
    string $final
): void {
    $piperBin = AUROXLINK_PIPER_BIN;

    if (!is_file($piperBin) || !is_executable($piperBin)) {
        throw new RuntimeException(
            "Piper no disponible: {$piperBin}"
        );
    }

    $model = seismicResolvePiperModel($tts);

    if (!is_file($model)) {
        throw new RuntimeException(
            "Modelo Piper no encontrado: {$model}"
        );
    }

    $lengthScale = 1.0;

    if (isset($tts['length_scale'])) {
        $lengthScale = (float)$tts['length_scale'];
    } elseif (isset($tts['speed'])) {
        $speed = (float)$tts['speed'];

        if ($speed > 0 && $speed <= 5) {
            $lengthScale = $speed;
        }
    }

    $lengthScale = max(0.5, min($lengthScale, 2.5));

    $inputFile =
        AUROXLINK_SEISMIC_AUDIO_DIR .
        '/piper-input-' . getmypid() . '-' . bin2hex(random_bytes(3)) . '.txt';

    if (file_put_contents($inputFile, $text . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException(
            'No fue posible crear entrada temporal para Piper'
        );
    }

    $cmdPiper = sprintf(
        '%s -m %s -i %s -f %s --length-scale %s 2>&1',
        escapeshellarg($piperBin),
        escapeshellarg($model),
        escapeshellarg($inputFile),
        escapeshellarg($raw),
        escapeshellarg((string)$lengthScale)
    );

    exec($cmdPiper, $outputPiper, $codePiper);

    @unlink($inputFile);

    if ($codePiper !== 0 || !is_file($raw)) {
        throw new RuntimeException(
            'Error generando audio con Piper: ' .
            implode(' ', $outputPiper)
        );
    }

    $cmdSox = sprintf(
        'sox %s -r 16000 -c 1 -b 16 %s 2>&1',
        escapeshellarg($raw),
        escapeshellarg($final)
    );

    exec($cmdSox, $outputSox, $codeSox);

    @unlink($raw);

    if ($codeSox !== 0 || !is_file($final)) {
        throw new RuntimeException(
            'Error convirtiendo audio Piper con sox: ' .
            implode(' ', $outputSox)
        );
    }
}

function seismicGenerateAudioEspeak(
    string $text,
    array $tts,
    string $raw,
    string $final
): void {
    $voice = (string)($tts['fallback_voice'] ?? 'es');
    $speedValue = $tts['fallback_speed'] ?? 145;
    $speed = (int)$speedValue;

    if ($speed < 80 || $speed > 450) {
        $speed = 145;
    }

    $cmdEspeak = sprintf(
        'espeak-ng -v %s -s %d -w %s %s 2>&1',
        escapeshellarg($voice),
        $speed,
        escapeshellarg($raw),
        escapeshellarg($text)
    );

    exec($cmdEspeak, $outputEspeak, $codeEspeak);

    if ($codeEspeak !== 0 || !is_file($raw)) {
        throw new RuntimeException(
            'Error generando audio con espeak-ng: ' .
            implode(' ', $outputEspeak)
        );
    }

    $cmdSox = sprintf(
        'sox %s -r 16000 -c 1 -b 16 %s 2>&1',
        escapeshellarg($raw),
        escapeshellarg($final)
    );

    exec($cmdSox, $outputSox, $codeSox);

    @unlink($raw);

    if ($codeSox !== 0 || !is_file($final)) {
        throw new RuntimeException(
            'Error convirtiendo audio espeak-ng con sox: ' .
            implode(' ', $outputSox)
        );
    }
}

function seismicGenerateAudio(string $text, array $config): string
{
    $tts = $config['tts'] ?? [];

    $engine = strtolower(
        trim(
            (string)($tts['engine'] ?? 'espeak-ng')
        )
    );

    seismicEnsureAudioDir();

    $token = date('Ymd-His') . '-' . getmypid();

    $raw = AUROXLINK_SEISMIC_AUDIO_DIR .
        "/seismic-{$token}-raw.wav";

    $final = AUROXLINK_SEISMIC_AUDIO_DIR .
        "/seismic-{$token}.wav";

    if ($engine === 'piper') {
        try {
            seismicGenerateAudioPiper(
                $text,
                $tts,
                $raw,
                $final
            );

            return $final;

        } catch (Throwable $piperError) {
            @unlink($raw);
            @unlink($final);

            error_log(
                '[AUROXLINK SEISMIC] Piper falló, usando espeak-ng: ' .
                $piperError->getMessage()
            );

            seismicGenerateAudioEspeak(
                $text,
                $tts,
                $raw,
                $final
            );

            return $final;
        }
    }

    if ($engine === 'espeak-ng' || $engine === 'espeak') {
        seismicGenerateAudioEspeak(
            $text,
            $tts,
            $raw,
            $final
        );

        return $final;
    }

    throw new RuntimeException(
        "Motor TTS no soportado: {$engine}"
    );
}

function seismicTransmitFile(
    string $wav,
    string $commandPty,
    int $repetitions = 1
): void {
    if (!is_file($wav)) {
        throw new RuntimeException("Audio no encontrado: {$wav}");
    }

    if (!file_exists($commandPty)) {
        throw new RuntimeException(
            "COMMAND_PTY no disponible: {$commandPty}"
        );
    }

    $repetitions = max(1, min($repetitions, 5));

    for ($i = 1; $i <= $repetitions; $i++) {
        $handle = @fopen($commandPty, 'w');

        if ($handle === false) {
            throw new RuntimeException(
                "No fue posible abrir COMMAND_PTY: {$commandPty}"
            );
        }

        $command = sprintf(
            "EVENT ::playFile \"%s\"\n",
            str_replace('"', '', $wav)
        );

        if (fwrite($handle, $command) === false) {
            fclose($handle);

            throw new RuntimeException(
                'No fue posible enviar comando a SvxLink'
            );
        }

        fflush($handle);
        fclose($handle);

        if ($i < $repetitions) {
            $duration = seismicAudioDuration($wav);
            sleep(max(1, (int)ceil($duration + 2)));
        }
    }
}

function seismicAudioDuration(string $wav): float
{
    $cmd = sprintf(
        'soxi -D %s 2>/dev/null',
        escapeshellarg($wav)
    );

    $result = trim((string)shell_exec($cmd));

    if (!is_numeric($result)) {
        return 5.0;
    }

    return (float)$result;
}

function seismicCleanupAudio(int $olderThanSeconds = 3600): void
{
    if (!is_dir(AUROXLINK_SEISMIC_AUDIO_DIR)) {
        return;
    }

    foreach (
        glob(AUROXLINK_SEISMIC_AUDIO_DIR . '/seismic-*.wav') ?: []
        as $file
    ) {
        if (
            is_file($file) &&
            filemtime($file) !== false &&
            filemtime($file) < time() - $olderThanSeconds
        ) {
            @unlink($file);
        }
    }
}
