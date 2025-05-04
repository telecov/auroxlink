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
    <p class="pb-0 mb-0"><i class="fs-6 text-white"><?php echo $version; ?></i></p>
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
            <p class="pb-0 mb-0"><i class="fs-6 text-white"><?php echo $version; ?></i></p>
            <hr>
            <ul class="nav flex-column">
                <?php echo $menuItems; ?>
            </ul>
        </div>
    </div>