<?php
    $style = json_decode(@file_get_contents('estilos.json'), true);

    $nombreZona = $style['nombre_zona'] ?? 'AUROXLINK';
    $tituloDashboard = $style['titulo_dashboard'] ?? 'Dashboard Nodo EchoLink';
    $indicativo = $style['indicativo'] ?? 'CA2RDP-L';
    $radioaficionado = $style['radioaficionado'] ?? 'CA2RDP';
    $ciudad = $style['ciudad'] ?? 'Santiago';
    $frecuencia = $style['frecuencia'] ?? '145.600';
    $utcOffset = $style['utc_offset'] ?? '-4';

    $imagenLogo = $style['imagen_logo'] ?? 'auroxlink_banner.png';
    $colorSidebar = $style['color_sidebar'] ?? '#2c3e50';
    $colorFondo = $style['color_fondo'] ?? '#e9ecef';
    $colorTitulo = $style['color_titulo'] ?? '#000000';

    $clave_acceso = '0192023a7bbd73250516f069df18b500';

    $teleco = 'Román - CA2RDP';
    $hammer = 'Esteban - CA3EUO';

    $titleSite = 'AUROXLINK';
    $version = 'Versión 1.5';

?>