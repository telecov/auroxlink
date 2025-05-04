<?php
    header("Content-Type: text/css");

    $style_json = file_get_contents('../estilos.json');
    $style = json_decode($style_json, true);

    // COLORES BASE
    $colorFondo = $style['color_fondo'] ?? '#e9ecef';
    $colorSidebar = $style['color_sidebar'] ?? '#212529';
    $colorTitulo = $style['color_titulo'] ?? '#000000';

?>

    .bg-body-auroxlink {
        background-color: <?= $colorSidebar ?>;
        position: sticky;
        overflow-y: auto;
    }
    .bg-body-content {
        background-color: <?= $colorFondo ?>;
    }
    .titulo {
        color: <?= $colorTitulo ?>;
    }