<?php
$menuItems = '
    <li class="nav-item"><a class="nav-link" href="index.php">' . t('menu_dashboard', 'DASHBOARD') . '</a></li>
    <li class="nav-item"><a class="nav-link" href="status-node.php">' . t('menu_node_status', 'Node Status') . '</a></li>
    <li class="nav-item"><a class="nav-link" href="echolink-traffic.php">' . t('menu_echolink_traffic', 'EchoLink Traffic') . '</a></li>
    <li class="nav-item"><a class="nav-link" href="connections.php">' . t('menu_active_connections', 'Active Connections') . '</a></li>
    <li class="nav-item"><a class="nav-link" href="qsl_generator.php">' . t('menu_qsl_manager', 'Activity and QSL Manager') . '</a></li>
    <li class="nav-item"><a class="nav-link" href="qsl_digital.php">' . t('menu_digital_qsl', 'Digital QSL') . '</a></li>
    <li class="nav-item"><a class="nav-link" href="activity_log.php">'. t('menu_activity_log', 'Activity Log'). '</a></li>
    <li class="nav-item"><a class="nav-link" href="settings.php">' . t('menu_settings', 'Settings') . '</a></li>
    <li class="nav-item"><a class="nav-link" href="custom.php">' . t('menu_customization', 'Customization') . '</a></li>
    <li class="nav-item"><a class="nav-link" href="about.php">' . t('menu_about', 'About') . ' ' . $titleSite . '</a></li>
';
?>

<!-- MENU PRINCIPAL -->
<div class="col-md-2 bg-body-auroxlink text-white d-none d-md-block position-sticky top-0 vh-100 overflow-auto">
    <h5 class="pt-3 fs-4"><?php echo $titleSite; ?></h5>
    <?php echo $version; ?>

    <div id="updateAlertSidebar" class="text-center px-2 py-1 mt-2"></div>
    <hr>
    <ul class="nav flex-column pb-3">
        <?php echo $menuItems; ?>
    </ul>
</div>

<!-- OFFCANVAS PARA MOVILES -->
<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
    <div class="offcanvas-header pb-0">
        <h5 class="offcanvas-title" id="mobileMenuLabel"><?php echo $titleSite; ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="<?= t('close', 'Close'); ?>"></button>
    </div>
    <div class="offcanvas-body pt-0">
        <p class="pb-0 mb-0"><i class="fs-6 text-white"><?php echo $version; ?></i></p>
        <div id="updateAlertMobile" class="text-center px-2 py-1 mt-2"></div>
        <hr>
        <ul class="nav flex-column">
            <?php echo $menuItems; ?>
        </ul>
    </div>
</div>

<style>
  .blinking {
    animation: blink 1.5s infinite;
  }
  @keyframes blink {
    0%   { background-color: #ffc107; }
    50%  { background-color: #fff3cd; }
    100% { background-color: #ffc107; }
  }

  .bg-body-auroxlink {
    background-color: #121821;
  }

  .nav-link {
    color: #d0d0d0 !important;
    font-weight: 500;
    border-radius: 6px;
    padding: 10px 14px;
    margin: 3px 6px;
    transition: all 0.2s ease-in-out;
  }

  .nav-link:hover {
    background-color: rgba(255, 255, 255, 0.08);
    color: #ffffff !important;
    transform: translateX(2px);
  }

  .nav {
    scrollbar-width: thin;
    scrollbar-color: #00bcd4 #121821;
  }
  .nav::-webkit-scrollbar {
    width: 6px;
  }
  .nav::-webkit-scrollbar-thumb {
    background-color: #00bcd4;
    border-radius: 3px;
  }
</style>

<script>
fetch('check_version.php')
  .then(res => res.json())
  .then(data => {
    const alerta1 = document.getElementById('updateAlertSidebar');
    const alerta2 = document.getElementById('updateAlertMobile');
    let contenido = '';

    const txtUpdated = <?= json_encode(t('sidebar_updated', 'AUROXLINK updated')); ?>;
    const txtNewVersion = <?= json_encode(t('sidebar_new_version', 'New AUROXLINK version')); ?>;
    const txtUpdate = <?= json_encode(t('sidebar_update_button', 'Update')); ?>;
    const txtConfirm = <?= json_encode(t('sidebar_confirm_update', 'Do you want to update AUROXLINK right now?')); ?>;

    if (data.local === data.remota) {
      contenido = `<div class="alert alert-success p-1 mb-1" style="font-size: 0.8rem;">✅ ${txtUpdated} (v${data.local})</div>`;
    } else {
      contenido = `
        <div class="alert alert-warning p-1 mb-1" style="font-size: 0.8rem;">
          🔔 ${txtNewVersion} v${data.remota}<br>
          <button onclick="actualizarAuroxlink()" class="btn btn-sm btn-warning blinking mt-1">${txtUpdate}</button>
        </div>`;
    }

    alerta1.innerHTML = contenido;
    alerta2.innerHTML = contenido;

    window._txtConfirmUpdateAurox = txtConfirm;
  });

function actualizarAuroxlink() {
  if (confirm(window._txtConfirmUpdateAurox || "Do you want to update AUROXLINK right now?")) {
    window.open('actualizar.php', '_blank');
  }
}
</script>
