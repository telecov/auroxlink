<?php
    $menuItems = '
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="index.php">Inicio</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="status-node.php">Estado Nodo</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="echolink-traffic.php">Tráfico EchoLink</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="connections.php">Conexiones</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="settings.php">Configuración</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="custom.php">Personalización</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="about.php">Acerca de '. $titleSite .'</a></li>
    ';
?>

<!-- MENU PRINCIPAL -->
<div class="col-md-2 bg-body-auroxlink text-white d-none d-md-block position-sticky top-0 vh-100 overflow-auto">
    <h5 class="pt-3 fs-4"><?php echo $titleSite; ?></h5>
    <p class="mb-1"><i class="fs-6 text-white">Versión: <?php echo $version; ?></i></p>
    <div id="updateBtnContainer" class="mt-1"></div>
    <hr>
    <ul class="nav flex-column pb-3">
        <?php echo $menuItems; ?>
    </ul>
</div>

<!-- OFFCANVAS PARA MOVILES -->
<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
    <div class="offcanvas-header pb-0">
        <h5 class="offcanvas-title" id="mobileMenuLabel"><?php echo $titleSite; ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body pt-0">
        <p class="mb-1"><i class="fs-6 text-white">Versión: <?php echo $version; ?></i></p>
        <div id="updateBtnMobile" class="mt-1"></div>
        <hr>
        <ul class="nav flex-column">
            <?php echo $menuItems; ?>
        </ul>
    </div>
</div>

<!-- SCRIPT DE DETECCIÓN Y BOTÓN -->
<script>
fetch('check_version.php')
  .then(res => res.json())
  .then(data => {
    if (data.disponible) {
      const btn = `<button onclick="actualizarAuroxlink()" class="btn btn-sm btn-warning w-100 mt-1">🔁 Actualizar a v${data.remota}</button>`;
      document.getElementById('updateBtnContainer').innerHTML = btn;
      document.getElementById('updateBtnMobile').innerHTML = btn;
    }
  });

function actualizarAuroxlink() {
  if (confirm("¿Deseas actualizar AUROXLINK ahora mismo?")) {
    window.open('actualizar.php', '_blank');
  }
}
</script>
teleco@AUROXLINK:/var/www/html$ sudo rm includes/sidebar-menu.php
teleco@AUROXLINK:/var/www/html$ sudo nano includes/sidebar-menu.php
teleco@AUROXLINK:/var/www/html$ sudo cat includes/sidebar-menu.php
<?php
    $menuItems = '
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="index.php">Inicio</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="status-node.php">Estado Nodo</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="echolink-traffic.php">Tráfico EchoLink</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="connections.php">Conexiones</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="settings.php">Configuración</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="custom.php">Personalización</a></li>
        <li class="nav-item"><a class="nav-link text-white fw-bold" href="about.php">Acerca de '. $titleSite .'</a></li>
    ';
?>

<!-- MENU PRINCIPAL -->
<div class="col-md-2 bg-body-auroxlink text-white d-none d-md-block position-sticky top-0 vh-100 overflow-auto">
    <h5 class="pt-3 fs-4"><?php echo $titleSite; ?></h5>
    <p class="mb-1"><i class="fs-6 text-white">Versión: <?php echo $version; ?></i></p>
    <div id="updateBtnContainer" class="mt-1"></div>
    <hr>
    <ul class="nav flex-column pb-3">
        <?php echo $menuItems; ?>
    </ul>
</div>

<!-- OFFCANVAS PARA MOVILES -->
<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
    <div class="offcanvas-header pb-0">
        <h5 class="offcanvas-title" id="mobileMenuLabel"><?php echo $titleSite; ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body pt-0">
        <p class="mb-1"><i class="fs-6 text-white">Versión: <?php echo $version; ?></i></p>
        <div id="updateBtnMobile" class="mt-1"></div>
        <hr>
        <ul class="nav flex-column">
            <?php echo $menuItems; ?>
        </ul>
    </div>
</div>

<!-- ESTILOS Y SCRIPT DE DETECCIÓN -->
<style>
  .blinking {
    animation: blink 1.5s infinite;
  }
  @keyframes blink {
    0%   { background-color: #ffc107; }
    50%  { background-color: #fff3cd; }
    100% { background-color: #ffc107; }
  }
</style>

<script>
fetch('check_version.php')
  .then(res => res.json())
  .then(data => {
    let content = '';
    if (data.local === data.remota) {
      content = `<div class="alert alert-success p-1 text-center" style="font-size: 0.8rem;">✅ AUROXLINK actualizado (v${data.local})</div>`;
    } else {
      content = `
        <div class="alert alert-warning p-1 text-center" style="font-size: 0.8rem;">
          🔔 AUROXLINK v${data.remota} disponible<br>
          <button onclick="actualizarAuroxlink()" class="btn btn-sm btn-warning blinking mt-1 w-100">Actualizar</button>
        </div>`;
    }

    document.getElementById('updateBtnContainer').innerHTML = content;
    document.getElementById('updateBtnMobile').innerHTML = content;
  });

function actualizarAuroxlink() {
  if (confirm("¿Deseas actualizar AUROXLINK ahora mismo?")) {
    window.open('actualizar.php', '_blank');
  }
}
</script>
