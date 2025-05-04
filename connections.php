<?php
    require 'includes/environment.php';
    function getActiveConnections() {
        $logFile = '/var/log/svxlink';
        $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $estadoActual = [];
        $startIndex = 0;
    
        if (!$lines) return [];
    
        // Buscar último reinicio de SVXLink usando el módulo EchoLink
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (strpos($lines[$i], 'SimplexLogic: Loading module "ModuleEchoLink"') !== false) {
                $startIndex = $i;
                break;
            }
        }
    
        $slice = array_slice($lines, $startIndex);
        $nodoActivo = null;
    
        foreach ($slice as $line) {
            $line = trim($line);
    
            // Desconexión
            if (preg_match('/: (\S+): EchoLink QSO state changed to DISCONNECTED/', $line, $m)) {
                $cs = $m[1];
                unset($estadoActual[$cs]);
                foreach ($estadoActual as $k => $v) {
                    if (isset($v['desde']) && $v['desde'] === $cs) {
                        unset($estadoActual[$k]);
                    }
                }
            }
    
            // Conexión directa
            if (preg_match('/: (\S+): EchoLink QSO state changed to CONNECTED/', $line, $m)) {
                $cs = $m[1];
                $estadoActual[$cs] = [
                    'hora' => date('d/m/Y H:i:s'),
                    'tipo' => (strpos($cs, '-L') !== false || strpos($cs, '-R') !== false) ? 'Nodo independiente' : 'Estación independiente',
                    'desde' => null
                ];
            }
    
            // Info message recibido
            if (strpos($line, 'EchoLink info message received from') !== false &&
                preg_match('/from (\S+)/', $line, $m)) {
                $nodoActivo = $m[1];
                continue;
            }
    
            // Estaciones hijas del nodo
            if ($nodoActivo && isset($estadoActual[$nodoActivo])) {
                if (preg_match('/\d{4}:\s+([A-Z0-9\-]{3,})\s{2,}/', $line, $m)) {
                    $cs = trim($m[1]);
                    if (!isset($estadoActual[$cs])) {
                        $estadoActual[$cs] = [
                            'hora' => date('d/m/Y H:i:s'),
                            'tipo' => (strpos($cs, '-L') !== false || strpos($cs, '-R') !== false)
                                ? "Nodo hijo"
                                : "Estación hija",
                            'desde' => $nodoActivo
                        ];
                    }
                }
            }
        }
    
        return $estadoActual;
    }
    
    $active = getActiveConnections();
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <title><?php echo $titleSite; ?> - Conexiones Activas</title>
</head>
<body>
    <div class="container-fluid bg-body-content">
        <div class="row">
            <?php require 'includes/sidebar-menu.php'; ?>

            <!-- Contenido principal -->
            <div class="col-12 col-md-10 p-3 vh-100">
                <div class="d-flex align-items-center">
                    <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">
                        ☰
                    </button>
                    <!-- Título -->
                    <h2 class="fs-4 titulo m-0">🛰️ Conexiones Activas EchoLink</h2>
                </div>

                <?php if (count($active) > 0): ?>
                    <div class="row mt-3">
                        <?php foreach ($active as $callsign => $info): ?>
                            <?php
                                $esHija = !empty($info['desde']);
                                $tipo = $info['tipo'];
                                $colorClase = str_contains($tipo, 'Nodo') ? 'border-warning' : 'border-success';
                                $badgeClase = match($tipo) {
                                    'Nodo hijo' => 'bg-warning text-dark',
                                    'Estación hija' => 'bg-info text-dark',
                                    'Nodo independiente' => 'bg-dark text-white',
                                    default => 'bg-success'
                                };
                                $tamañoClase = $esHija ? 'p-2 small shadow-sm' : 'p-3';
                                $columnaClase = $esHija ? 'col-md-3' : 'col-md-4';
                            ?>
                            <div class="<?= $columnaClase; ?>">
                                <div class="card card-connection mb-3 border <?= $colorClase . ' ' . $tamañoClase; ?>">
                                    <h6 class="mb-1"><?= htmlspecialchars($callsign); ?></h6>
                                    <p class="mb-1"><strong>Tipo:</strong> <?= htmlspecialchars($tipo); ?></p>
                                    <?php if ($esHija): ?>
                                        <p class="mb-1"><strong>🔗 Vía:</strong> <?= htmlspecialchars($info['desde']); ?></p>
                                    <?php endif; ?>
                                    <p class="mb-1"><i class="bi bi-clock"></i> <?= htmlspecialchars($info['hora']); ?></p>
                                    <span class="badge <?= $badgeClase; ?>">Activo</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                <div class="alert alert-info text-center mt-3">
                    No hay conexiones activas en este momento.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
