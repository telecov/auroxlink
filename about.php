<?php
    require 'includes/environment.php';

    session_start();

    if ( md5($teleco) !== 'e84a85ae05830fa9dc95bcf6915445b7' || md5($hammer) !== '4504960fc54f592c90cbfcd703f8c306' ) {
        $_SESSION['integridad_modificada'] = true;
        die("Error: la firma fue modificada.");
    }
    ob_start();
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style/style.css.php">
    <link rel="shortcut icon" href="img/favicon.png" type="image/png">
    <title><?php echo $titleSite; ?> - About</title>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php require 'includes/sidebar-menu.php'; ?>

            <!-- Contenido principal -->
            <div class="col-12 col-md-10 p-3">
                <!-- Contenido -->
                <div class="about-box p-3 bg-white rounded shadow">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">
                            ☰ 
                        </button>
                        <h2 class="fs-4 titulo m-0">Acerca de AUROXLINK</h2>
                    </div>
                    <p class="text-muted pt-0">Sistema de Control Visual para SVXLink</p>

                    <p><strong>Auroxlink</strong> es una plataforma moderna y personalizable para visualizar, administrar y extender las capacidades del sistema <strong>SVXLink</strong>, orientada a radioaficionados y operadores de nodos. Esta interfaz fue desarrollada por <strong><?php echo $teleco; ?></strong>, conocido como <strong>TELECOVIAJERO</strong> en redes sociales, con el objetivo de acercar la tecnología a todos quienes forman parte del mundo de la radio.</p>

                    <p><strong>SVXLink</strong> es un motor robusto de comunicaciones de voz para Linux. Auroxlink se integra sobre este sistema para ofrecer una experiencia gráfica, intuitiva y en constante evolución.</p>

                    <img src="img/auroxlink.png" alt="Banner AuroxLink" class="img-fluid rounded mb-4">

                    <h4 class="mt-2">Origen del nombre</h4>
                    <p>El nombre <strong>Auroxlink</strong> nace como un homenaje a <strong>Aurora</strong>, mi hija, quien es mi inspiración y fuerza para seguir creando. Así como las auroras iluminan el cielo, este sistema busca iluminar las comunicaciones.</p>

                    <p><strong> X </strong> corresponde al corazon de este del sistema SVXlink.</p>
                    <p><strong> Link </strong> representa la conexión entre estaciones a través de Echolink, como también las personas y pasiones que dan vida a la radioafición.</p>

                    <h4>Reconocimiento a SVXLink</h4>
                    <p>Este sistema se basa en <strong>SVXLink</strong>, un software libre creado por <strong>Tobias Blomberg (SM0SVX)</strong>. Se distribuye bajo la <a href="https://www.gnu.org/licenses/old-licenses/gpl-2.0.html" target="_blank">Licencia GPLv2</a>. Puedes consultar su <a href="https://github.com/sm0svx/svxlink" target="_blank">repositorio oficial aquí</a>.</p>

                    <h4>Agradecimientos</h4>
                    <p>Un agradecimiento especial a <strong><?php echo $hammer ?></strong>, quien colaboró activamente de este proyecto como desarrollador, aportando ideas fundamentales para fortalecerlo y proyectarlo hacia el futuro.</p>

                    <h4 class="mt-4">Apoya este proyecto</h4>
                    <p>Si deseas apoyar el desarrollo de Auroxlink, puedes hacerlo aqui:</p>

                    <form action="https://www.paypal.com/donate" method="post" target="_blank">
                        <input type="hidden" name="hosted_button_id" value="SRA7QC84FAV3A" />
                        <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
                        <img src="https://www.paypal.com/en_CL/i/scr/pixel.gif" width="1" height="1" />
                    </form>

                    <p class="mt-4"><strong>CA2RDP - TELECOVIAJERO</strong><br>
                        Sígueme en <a href="https://instagram.com/telecoviajero" target="_blank">Instagram</a> y <a href="https://youtube.com/@telecoviajero" target="_blank">YouTube</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
    $html_output = ob_get_clean();

    if (strpos($html_output, $teleco) === false || strpos($html_output, $hammer) === false) {
        $_SESSION['integridad_eliminada'] = true;
        die("Error: la firma fue eliminada del HTML.");
    }

    echo $html_output;
?>