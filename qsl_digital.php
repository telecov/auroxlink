<?php
require 'includes/environment.php';
session_start();

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

/* =========================================================
   CARGA DE IDIOMA
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
    $lang = json_decode(file_get_contents(__DIR__ . "/data/lang/es.json"), true);
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
   OBTENER ARCHIVOS QSL
========================================================= */
$qslFiles = glob("qsl/*.png");
usort($qslFiles, function ($a, $b) {
    return filemtime($b) - filemtime($a);
});
?>
<!doctype html>
<html lang="<?= htmlspecialchars($idioma); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="style/style.css.php">
  <link rel="shortcut icon" href="img/favicon.png" type="image/png">
  <title><?php echo htmlspecialchars($titleSite); ?> - <?= t('qsl_digital_title', 'Digital QSLs'); ?></title>
</head>

<body>
  <div class="container-fluid">
    <div class="row">
      <?php include 'includes/sidebar-menu.php'; ?>
      <div class="col-12 col-md-10 p-3">
        <div class="d-flex align-items-center">
          <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#mobileMenu">
            ☰
          </button>
          <h2 class="fs-4 titulo m-0">📁 <?= t('qsl_digital_title', 'Digital QSLs'); ?></h2>
        </div>

        <?php if (empty($qslFiles)): ?>
          <div class="alert alert-info mt-3"><?= t('qsl_no_saved', 'There are no saved QSLs yet.'); ?></div>
        <?php else: ?>
          <div class="row mt-3">
            <?php foreach ($qslFiles as $file): ?>
              <?php $nombre = basename($file); ?>
              <div class="col-md-4 mb-4">
                <div class="card border border-success shadow-sm">
                  <img src="qsl/<?php echo rawurlencode($nombre); ?>" class="card-img-top" alt="QSL" style="cursor:pointer"
                    onclick="mostrarQSL(<?php echo json_encode($nombre); ?>)">
                  <div class="card-body">
                    <h6 class="card-title">📡 <?php echo htmlspecialchars($nombre); ?></h6>
                    <a href="qsl/<?php echo rawurlencode($nombre); ?>" class="btn btn-sm btn-outline-success w-100 mb-2" download>
                      ⬇️ <?= t('qsl_download', 'Download'); ?>
                    </a>
                    <button onclick="eliminarQSL(<?php echo json_encode($nombre); ?>)" class="btn btn-sm btn-outline-danger w-100">
                      🗑️ <?= t('qsl_delete', 'Delete'); ?>
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Modal para mostrar QSL -->
  <div class="modal fade" id="modalQSL" tabindex="-1" aria-labelledby="modalQSLLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-dark text-white">
        <div class="modal-header">
          <h5 class="modal-title" id="modalQSLLabel">🔍 <?= t('qsl_preview_large', 'Large QSL Preview'); ?></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= t('close', 'Close'); ?>"></button>
        </div>
        <div class="modal-body text-center">
          <img id="modalQSLImage" src="" class="img-fluid rounded shadow mb-3" alt="QSL Preview">
          <div>
            <a id="shareTelegram" target="_blank" class="btn btn-outline-info me-2">📨 <?= t('qsl_share_telegram', 'Share on Telegram'); ?></a>
            <a id="shareDirectLink" target="_blank" class="btn btn-outline-secondary">🔗 <?= t('qsl_direct_link', 'Direct link'); ?></a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const txtDeleteConfirmQsl = <?= json_encode(t('qsl_delete_confirm_file', 'Are you sure you want to delete this QSL?')); ?>;

    function eliminarQSL(nombre) {
      if (confirm(txtDeleteConfirmQsl)) {
        fetch('qsl_clear.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'archivo=' + encodeURIComponent(nombre)
        })
          .then(res => res.text())
          .then(msg => {
            alert(msg);
            location.reload();
          });
      }
    }

    function mostrarQSL(nombre) {
      const ruta = 'qsl/' + encodeURIComponent(nombre);
      document.getElementById('modalQSLImage').src = ruta;
      document.getElementById('shareTelegram').href =
        'https://t.me/share/url?url=' + encodeURIComponent(window.location.origin + '/' + ruta);
      document.getElementById('shareDirectLink').href = ruta;
      new bootstrap.Modal(document.getElementById('modalQSL')).show();
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
