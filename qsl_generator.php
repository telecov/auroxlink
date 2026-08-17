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
   CARGAR EVENTOS
========================================================= */
$eventos_path = __DIR__ . '/data/eventos.json';
if (!file_exists($eventos_path)) {
    file_put_contents($eventos_path, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
$eventos = json_decode(file_get_contents($eventos_path), true) ?? [];
?>
<!doctype html>
<html lang="<?= htmlspecialchars($idioma); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($titleSite) ?> - <?= t('qsl_generator_title', 'QSL Generator'); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style/style.css.php">
  <link rel="shortcut icon" href="img/favicon.png" type="image/png">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <style>
    #preview {
      margin-top: 10px;
      border: 2px dashed #00ffaa;
      background-color: #000;
      position: relative;
      font-family: 'Courier New', monospace;
      max-width: 100%;
      padding: 0;
    }

    .qsl-data {
      position: absolute;
      bottom: 5px;
      left: 10px;
      right: 10px;
      font-size: 13px;
      background-color: rgba(0, 0, 0, 0.6);
      padding: 6px;
      border-radius: 6px;
      color: white;
    }

    .qsl-data table {
      width: 100%;
      border-collapse: collapse;
      color: #fff;
    }

    .qsl-data th,
    .qsl-data td {
      border: 1px solid #fff;
      padding: 4px;
      text-align: center;
    }

    .qsl-data th {
      background-color: rgba(255, 255, 255, 0.2);
    }

    .qsl-data p {
      margin: 3px 0 2px;
    }
  </style>
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

        <div class="about-box p-3 bg-white rounded shadow">
          <div class="d-flex align-items-center justify-content-between">
            <h2 class="fs-4 titulo m-0"><?= t('qsl_manager_title', 'Activity and QSL Manager'); ?></h2>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#eventoQSLModal">➕ <?= t('qsl_new_event', 'New Event'); ?></button>
          </div>
          <p class="text-muted pt-0"><?= t('qsl_manager_subtitle', 'Manage your activities and generate digital QSLs'); ?></p>

          <div class="table-responsive mt-4" style="max-height: 240px; overflow-y: auto;">
            <table class="table table-bordered table-hover">
              <thead class="table-light">
                <tr>
                  <th>📌 <?= t('qsl_event', 'Event'); ?></th>
                  <th>📅 <?= t('qsl_date', 'Date'); ?></th>
                  <th>⏰ <?= t('qsl_time', 'Time'); ?></th>
                  <th>📍 <?= t('qsl_place', 'Place'); ?></th>
                  <th>📡 <?= t('qsl_frequency', 'Frequency'); ?></th>
                  <th>🎙️ <?= t('qsl_mode', 'Mode'); ?></th>
                  <th>📨 <?= t('qsl_qsl', 'QSL'); ?></th>
                  <th>🗑️</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($eventos as $i => $ev): ?>
                  <tr>
                    <td><?= htmlspecialchars($ev['titulo']) ?></td>
                    <td><?= htmlspecialchars($ev['fecha']) ?></td>
                    <td><?= htmlspecialchars($ev['hora']) ?></td>
                    <td><?= htmlspecialchars($ev['lugar'] ?? '') ?></td>
                    <td><?= htmlspecialchars($ev['frecuencia'] ?? '') ?></td>
                    <td><?= htmlspecialchars($ev['modo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($ev['qsl'] ?? 'No') ?></td>
                    <td><button class="btn btn-sm btn-danger" onclick="eliminarEvento(<?= (int)$i ?>)">X</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="mt-5">
            <h5 class="mb-3">🎨 <?= t('qsl_generator_heading', 'Digital QSL Generator'); ?></h5>
            <select id="eventoAsociado" class="form-select mb-2">
              <option value=""><?= t('qsl_link_event_optional', 'Link to event (optional)'); ?></option>
              <?php foreach ($eventos as $ev):
                $valor = htmlspecialchars($ev['titulo']) . " ({$ev['fecha']} {$ev['hora']})";
              ?>
                <option value="<?= $valor ?>"><?= $valor ?></option>
              <?php endforeach; ?>
            </select>

            <div class="row">
              <div class="col-md-6">
                <input type="file" class="form-control mb-2" id="fondo" accept="image/*">
                <input id="indicativo" type="text" class="form-control mb-2" placeholder="<?= t('qsl_callsign_destination', 'Destination callsign'); ?>">
                <input id="fechaQSL" type="date" class="form-control mb-2">
                <input id="horaQSL" type="time" class="form-control mb-2">
                <input id="frecuenciaQSL" type="text" class="form-control mb-2" placeholder="<?= t('qsl_frequency', 'Frequency'); ?>">
                <input id="modoQSL" type="text" class="form-control mb-2" placeholder="<?= t('qsl_mode', 'Mode'); ?>">
                <input id="rstQSL" type="text" class="form-control mb-2" placeholder="RST">
                <input id="qthQSL" type="text" class="form-control mb-2" placeholder="QTH">
                <textarea id="mensajeQSL" class="form-control mb-2" rows="2" placeholder="<?= t('qsl_message', 'Message'); ?>"></textarea>
                <button onclick="generarQSL()" class="btn btn-success w-100">🧾 <?= t('qsl_generate', 'Generate QSL'); ?></button>
                <button onclick="descargarQSL()" class="btn btn-outline-dark mt-2 w-100">⬇️ <?= t('qsl_download_png', 'Download PNG'); ?></button>
                <button onclick="guardarQSL()" class="btn btn-primary mt-2 w-100">💾 <?= t('qsl_save_qsl', 'Save QSL'); ?></button>
              </div>
              <div class="col-md-6">
                <div class="bg-dark p-2 rounded border border-success">
                  <h6 class="text-white text-center"><?= t('qsl_preview', 'QSL Preview'); ?></h6>
                  <div id="preview"></div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="eventoQSLModal" tabindex="-1" aria-labelledby="eventoQSLModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form class="modal-content" id="formEventoQSL">
        <div class="modal-header">
          <h5 class="modal-title" id="eventoQSLModalLabel">📢 <?= t('qsl_register_event', 'Register Event'); ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close', 'Close'); ?>"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">📌 <?= t('qsl_event_name', 'Event name'); ?></label>
          <input type="text" class="form-control mb-2" name="titulo" required>

          <label class="form-label">📅 <?= t('qsl_date', 'Date'); ?></label>
          <input type="date" class="form-control mb-2" name="fecha" required>

          <label class="form-label">⏰ <?= t('qsl_time', 'Time'); ?></label>
          <input type="time" class="form-control mb-2" name="hora" required>

          <label class="form-label">📍 <?= t('qsl_place', 'Place'); ?></label>
          <input type="text" class="form-control mb-2" name="lugar">

          <label class="form-label">📡 <?= t('qsl_frequency', 'Frequency'); ?></label>
          <input type="text" class="form-control mb-2" name="frecuencia">

          <label class="form-label">🎙️ <?= t('qsl_mode', 'Mode'); ?></label>
          <input type="text" class="form-control mb-2" name="modo">

          <label class="form-label">✉️ <?= t('qsl_will_be_delivered', 'Will QSL be delivered?'); ?></label>
          <select class="form-control mb-2" name="qsl">
            <option value="Sí"><?= t('yes', 'Yes'); ?></option>
            <option value="No"><?= t('no', 'No'); ?></option>
          </select>

          <label class="form-label">📝 <?= t('qsl_details', 'Details'); ?></label>
          <textarea class="form-control mb-2" name="mensaje" rows="3"></textarea>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">📨 <?= t('qsl_send_register', 'Send and Register'); ?></button>
        </div>
      </form>
    </div>
  </div>

  <script>
    const txtUploadBackground = <?= json_encode(t('qsl_upload_background_required', 'You must upload a background image for the QSL.')); ?>;
    const txtEventNotSpecified = <?= json_encode(t('qsl_event_not_specified', 'Not specified')); ?>;
    const txtConfirmQsoWith = <?= json_encode(t('qsl_confirm_qso_with', 'CONFIRMS QSO WITH')); ?>;
    const txtDay = <?= json_encode(t('qsl_day', 'DAY')); ?>;
    const txtMonth = <?= json_encode(t('qsl_month', 'MONTH')); ?>;
    const txtYear = <?= json_encode(t('qsl_year', 'YEAR')); ?>;
    const txtUtc = <?= json_encode(t('qsl_utc', 'UTC')); ?>;
    const txtMhz = <?= json_encode(t('qsl_mhz', 'MHz')); ?>;
    const txtMode = <?= json_encode(t('qsl_mode', 'Mode')); ?>;
    const txtQth = <?= json_encode(t('qsl_qth', 'QTH')); ?>;
    const txtMsg = <?= json_encode(t('qsl_msg', 'MSG')); ?>;
    const txtFrom = <?= json_encode(t('qsl_from', "73's from")); ?>;
    const txtOk = <?= json_encode(t('qsl_ok', 'OK')); ?>;
    const txtError = <?= json_encode(t('qsl_error', 'Error')); ?>;
    const txtDeleteConfirm = <?= json_encode(t('qsl_delete_confirm', 'Delete this event?')); ?>;
    const txtDeleteError = <?= json_encode(t('qsl_delete_error', 'Error deleting')); ?>;

    function generarQSL() {
      const indicativo = document.getElementById('indicativo').value;
      const fechaTexto = document.getElementById('fechaQSL').value;
      const fecha = fechaTexto ? new Date(fechaTexto + 'T00:00:00') : null;
      const hora = document.getElementById('horaQSL').value;
      const frecuencia = document.getElementById('frecuenciaQSL').value;
      const modo = document.getElementById('modoQSL').value;
      const rst = document.getElementById('rstQSL').value;
      const qth = document.getElementById('qthQSL').value;
      const mensaje = document.getElementById('mensajeQSL').value;
      const evento = document.getElementById('eventoAsociado').value;
      const fondoInput = document.getElementById('fondo');
      const preview = document.getElementById('preview');

      if (!fondoInput.files.length) {
        alert("⚠️ " + txtUploadBackground);
        return;
      }

      const reader = new FileReader();
      reader.onload = function (e) {
        const fondoHTML = `<img id="qslImage" src="${e.target.result}" alt="Fondo QSL" class="img-fluid rounded">`;
        const datosHTML = `
          <div class="qsl-data">
            <p><strong>${<?= json_encode(t('qsl_event', 'Event')); ?>}:</strong> ${evento || txtEventNotSpecified}</p>
            <table>
              <tr>
                <th>${txtConfirmQsoWith}</th>
                <th>${txtDay}</th>
                <th>${txtMonth}</th>
                <th>${txtYear}</th>
                <th>${txtUtc}</th>
                <th>${txtMhz}</th>
                <th>RST</th>
                <th>${txtMode}</th>
              </tr>
              <tr>
                <td>${indicativo}</td>
                <td>${fecha ? fecha.getDate() : ''}</td>
                <td>${fecha ? fecha.getMonth() + 1 : ''}</td>
                <td>${fecha ? fecha.getFullYear() : ''}</td>
                <td>${hora}</td>
                <td>${frecuencia}</td>
                <td>${rst}</td>
                <td>${modo}</td>
              </tr>
            </table>
            <p><strong>${txtQth}:</strong> ${qth}</p>
            <p><strong>${txtMsg}:</strong> ${mensaje}</p>
            <p style="text-align:right">${txtFrom} <strong><?= htmlspecialchars($nombreZona); ?></strong></p>
          </div>`;
        preview.innerHTML = fondoHTML + datosHTML;
      };
      reader.readAsDataURL(fondoInput.files[0]);
    }

    function descargarQSL() {
      html2canvas(document.getElementById("preview")).then(canvas => {
        const enlace = document.createElement("a");
        enlace.href = canvas.toDataURL("image/png");
        enlace.download = "qsl_digital.png";
        enlace.click();
      });
    }

    function guardarQSL() {
      html2canvas(document.getElementById("preview")).then(canvas => {
        const evento = document.getElementById("eventoAsociado").value;
        const dataURL = canvas.toDataURL("image/png");
        fetch("qsl_save.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ imagen: dataURL, evento })
        })
          .then(res => res.text())
          .then(msg => {
            alert("✅ " + msg);
            window.location.href = "qsl_digital.php";
          })
          .catch(err => alert("❌ " + txtError + ": " + err));
      });
    }

    document.getElementById('formEventoQSL').addEventListener('submit', function (e) {
      e.preventDefault();
      const datos = Object.fromEntries(new FormData(this).entries());
      fetch('qsl_send_event.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos)
      })
        .then(res => res.text())
        .then(msg => {
          alert("✅ " + msg);
          location.reload();
        })
        .catch(err => alert("❌ " + txtError + ": " + err));
    });

    function eliminarEvento(index) {
      if (confirm(txtDeleteConfirm)) {
        fetch('delete_event.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ index: index })
        })
          .then(res => res.text())
          .then(msg => {
            alert("✅ " + msg);
            location.reload();
          })
          .catch(err => alert("❌ " + txtDeleteError + ": " + err));
      }
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
