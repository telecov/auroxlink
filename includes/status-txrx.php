<?php
require __DIR__ . '/environment.php';

/* =========================
   CARGA DE IDIOMA
========================= */
$configFile = __DIR__ . '/../estilos.json';
$config = file_exists($configFile)
    ? json_decode(file_get_contents($configFile), true)
    : [];

$idioma = $config['idioma'] ?? 'es';

$langFile = __DIR__ . "/../data/lang/{$idioma}.json";
$lang = file_exists($langFile)
    ? json_decode(file_get_contents($langFile), true)
    : json_decode(file_get_contents(__DIR__ . "/../data/lang/es.json"), true);

function t($key, $default = '')
{
    global $lang;
    return $lang[$key] ?? $default;
}

$log = @file('/var/log/svxlink');
$estado = t('txrx_unknown', 'Unknown');
$cardClass = 'bg-secondary';
$lastTx = 'N/A';
$lastTxTimestamp = 0;
$duracionTx = '—';

if ($log) {
    $lastOnTime = null;
    $lastOffTime = null;

    foreach (array_reverse($log) as $line) {
        if (!$lastOffTime && strpos($line, 'Tx1: Turning the transmitter OFF') !== false) {
            $fecha_raw = substr($line, 0, 24);
            $timestamp = strtotime($fecha_raw);
            $lastOffTime = $timestamp;
        }

        if (!$lastOnTime && strpos($line, 'Tx1: Turning the transmitter ON') !== false) {
            $fecha_raw = substr($line, 0, 24);
            $timestamp = strtotime($fecha_raw);
            $lastOnTime = $timestamp;
            $lastTxTimestamp = $timestamp;
            $lastTx = $timestamp ? date('d/m/Y H:i:s', $timestamp) : t('not_available', 'Not available');
        }

        if ($lastOnTime && $lastOffTime) {
            break;
        }
    }

    if ($lastOnTime && $lastOffTime && $lastOffTime > $lastOnTime) {
        $duracionSegundos = $lastOffTime - $lastOnTime;

        if ($duracionSegundos < 60) {
            $duracionTx = $duracionSegundos . ' ' . t('sec', 'sec');
        } else {
            $min = floor($duracionSegundos / 60);
            $sec = $duracionSegundos % 60;
            $duracionTx = $min . ' ' . t('min', 'min') . ' ' . $sec . ' ' . t('sec', 'sec');
        }
    }

    foreach (array_reverse($log) as $line) {
        if (strpos($line, 'Tx1: Turning the transmitter ON') !== false) {
            $estado = t('tx_active', 'TX Active');
            $cardClass = 'bg-danger flash';
            break;
        }

        if (strpos($line, 'Tx1: Turning the transmitter OFF') !== false) {
            $estado = t('rx_standby', 'RX Standby');
            $cardClass = 'bg-success';
            break;
        }
    }
}
?>

<div class="card h-100 p-4 shadow-sm d-flex flex-column justify-content-center align-items-center text-center text-white <?= $cardClass ?>"
     style="border-left: 6px solid #dee2e6; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
    
    <h5 class="mb-2"><?= t('txrx_status', 'TX/RX Status'); ?></h5>

    <div style="font-size: 1.5rem; font-weight: 700;">
        <?= htmlspecialchars($estado) ?>
    </div>

    <hr class="my-3 w-100" style="border-top: 1px dashed rgba(255,255,255,0.3);">

    <small class="text-white-50 d-block mb-1">
        ⏱ <?= t('last_tx', 'Last TX'); ?>: <?= htmlspecialchars($lastTx) ?>
        <?php if ($lastTxTimestamp): ?>
            <span id="txRelativeTime" data-timestamp="<?= (int)$lastTxTimestamp ?>"></span>
        <?php endif; ?>
    </small>

    <small class="text-white-50 d-block">
        📏 <?= t('duration', 'Duration'); ?>: <?= htmlspecialchars($duracionTx) ?>
    </small>
</div>
