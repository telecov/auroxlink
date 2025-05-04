<?php
    $log = @file('/var/log/svxlink');
    $estado = 'Desconocido';
    $cardClass = 'bg-secondary';
    if ($log) {
        foreach (array_reverse($log) as $line) {
            if (strpos($line, 'Tx1: Turning the transmitter ON') !== false) {
                $estado = 'TX Activo';
                $cardClass = 'bg-danger flash';
                break;
            }

            if (strpos($line, 'Tx1: Turning the transmitter OFF') !== false) {
                $estado = 'RX en Espera';
                $cardClass = 'bg-success';
                break;
            }
        }
    }
?>
<div class="card p-3 text-white text-center <?= $cardClass ?>">
    <h6>Estado TX/RX</h6>
    <span style="font-size: 1.3rem; font-weight: bold;"><?= $estado ?></span>
</div>
