<?php
require 'includes/environment.php';
session_start();

// Protección de integridad
if (
    (isset($_SESSION['integridad_modificada']) && $_SESSION['integridad_modificada'] === true) ||
    (isset($_SESSION['integridad_eliminada']) && $_SESSION['integridad_eliminada'] === true)
){
    die('Error: El sistema ha sido comprometido. No se puede continuar.');
}

// Validación segura de hash
if (!hash_equals(md5($teleco), 'e84a85ae05830fa9dc95bcf6915445b7') ||
    !hash_equals(md5($hammer), '4504960fc54f592c90cbfcd703f8c306')) {
    $_SESSION['integridad_modificada'] = true;
    die("Error: la firma fue modificada.");
}

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

ob_start();
?>

<!doctype html>
<html lang="<?= htmlspecialchars($idioma); ?>">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($titleSite) ?> - <?= t('about_title', 'About AuroxLink'); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style/style.css.php">
  <link rel="shortcut icon" href="img/favicon.png" type="image/png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root{
      --bg:#f0f2f5;
      --card:#ffffff;
      --text:#222;
      --muted:#6c757d;
      --p:#555;
      --autBlue:#0d6efd;
      --autBlue2:#2b7bff;
      --autSoft: rgba(13,110,253,.10);
      --line: rgba(0,0,0,.06);
      --shadow: 0 4px 20px rgba(0,0,0,0.08);
    }

    body{
      background-color: var(--bg);
      font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      color: var(--text);
      overflow-x:hidden;
    }

    .titulo{
      font-size: 1.35rem;
      font-weight: 800;
      margin: 0;
      color: var(--text);
    }

    .subtitulo{
      color: var(--muted);
      margin: 0;
      font-size: .95rem;
    }

    .hero-aurora{
      border-radius:16px;
      padding: 20px;
      color:#fff;
      box-shadow: 0 10px 22px rgba(0,0,0,0.12);
      background: linear-gradient(135deg, var(--autBlue2), #0056b3);
      position: relative;
      overflow: hidden;
    }
    .hero-aurora::after{
      content:"";
      position:absolute;
      inset:-40%;
      background:
        radial-gradient(circle at 25% 35%, rgba(255,255,255,.12), transparent 55%),
        radial-gradient(circle at 70% 25%, rgba(255,255,255,.09), transparent 55%),
        radial-gradient(circle at 55% 70%, rgba(255,255,255,.07), transparent 60%);
      filter: blur(16px);
      opacity: .95;
      pointer-events:none;
      z-index:0;
    }
    .hero-aurora > *{ position:relative; z-index:1; }

    .infinity-watermark{
      position:absolute;
      right:-10px;
      top:-22px;
      font-size: 120px;
      color: rgba(255,255,255,.13);
      transform: rotate(8deg);
      user-select:none;
      pointer-events:none;
      z-index:1;
    }
    .infinity-watermark.small{
      left:-18px;
      bottom:-40px;
      top:auto;
      right:auto;
      font-size: 140px;
      opacity: .10;
      transform: rotate(-10deg);
    }

    .hero-badges .badge{
      background: rgba(255,255,255,.92) !important;
      color:#111 !important;
      font-weight:600;
      padding: .55rem .85rem;
      border-radius: 999px;
    }

    .aurox-card{
      background: var(--card);
      border: 1px solid var(--line);
      border-radius:16px;
      box-shadow: var(--shadow);
      padding: 20px;
      position: relative;
      overflow: hidden;
    }

    .aurox-card.glow-blue::before{
      content:"";
      position:absolute;
      left:0; right:0; top:0;
      height: 4px;
      background: linear-gradient(90deg, transparent, rgba(13,110,253,.65), transparent);
      opacity:.9;
    }

    .seccion-titulo{
      font-size: 1.05rem;
      font-weight: 800;
      color:#045d56;
      margin-bottom: 10px;
      display:flex;
      gap:10px;
      align-items:center;
    }
    .seccion-titulo i{
      font-size: 1.12rem;
      color: var(--autBlue);
    }

    p, li{
      color: var(--p);
      font-size: 0.98rem;
      line-height:1.6;
    }

    .img-banner{
      width:100%;
      max-height: 260px;
      object-fit: cover;
      border-radius: 12px;
      border: 1px solid rgba(0,0,0,.06);
      box-shadow: 0 6px 18px rgba(0,0,0,0.08);
    }

    .note{
      font-size:.92rem;
      color: var(--muted);
      margin-top:8px;
    }

    .pill{
      display:inline-flex;
      gap:8px;
      align-items:center;
      padding: 8px 12px;
      border-radius:999px;
      background:#f8f9fa;
      border:1px solid #e9ecef;
      color:#333;
      font-weight:700;
      font-size:.92rem;
    }
    .pill .dot{
      width:10px;
      height:10px;
      border-radius:50%;
      background: var(--autBlue);
      box-shadow: 0 0 10px rgba(13,110,253,.25);
    }

    .soft-hr{
      height:1px;
      border:0;
      background: linear-gradient(90deg, transparent, rgba(0,0,0,.12), transparent);
      margin: 12px 0;
    }

    .carta-aurora{
      background: linear-gradient(135deg, rgba(13,110,253,.08), rgba(13,110,253,.04));
      border: 1px solid rgba(13,110,253,.18);
      border-radius: 14px;
      padding: 14px 16px;
      box-shadow: 0 6px 16px rgba(0,0,0,0.06);
      position: relative;
      overflow: hidden;
    }

    .carta-aurora::after{
      content:"∞   ∞   ∞   ∞   ∞   ∞   ∞   ∞   ∞   ∞";
      position:absolute;
      left:-10%;
      top:10px;
      width:120%;
      font-size: 22px;
      letter-spacing: 18px;
      color: rgba(13,110,253,.14);
      transform: rotate(-6deg);
      white-space: nowrap;
      pointer-events:none;
      user-select:none;
    }
    .carta-aurora p{ color:#444; }

    .seal{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 7px 10px;
      border-radius: 999px;
      background: rgba(13,110,253,.10);
      border: 1px solid rgba(13,110,253,.18);
      color: #0b4fbf;
      font-weight: 800;
      font-size: .9rem;
    }
    .seal i{ color: var(--autBlue); }

    .social-icons a{
      font-size: 20px;
      margin: 0 10px;
      color: var(--autBlue);
      transition: transform .15s ease, opacity .15s ease;
      opacity: .95;
    }
    .social-icons a:hover{
      transform: translateY(-1px) scale(1.08);
      opacity: 1;
    }
  </style>
</head>

<body>
  <div class="container-fluid bg-body-content">
    <div class="row">
      <?php require 'includes/sidebar-menu.php'; ?>

      <div class="col-12 col-md-10 p-3">

        <div class="d-flex align-items-center mb-2">
          <button class="btn btn-dark d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">☰</button>
          <h2 class="fs-4 titulo m-0"><?= t('about_title', 'About AuroxLink'); ?></h2>
        </div>
        <p class="subtitulo mb-3"><?= t('about_subtitle', 'Visual Control System for SVXLink'); ?></p>

        <div class="hero-aurora mb-4">
          <div class="infinity-watermark">∞</div>
          <div class="infinity-watermark small">∞</div>

          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
              <h5 class="mb-1">🔵 <?= t('about_hero_title', 'AUROXLINK: clarity, control and community'); ?></h5>
              <p class="mb-0" style="opacity:.92;">
                <?= t('about_hero_text', 'A modern dashboard for SVXLink operators, designed to be simple, maintainable and beautiful.'); ?>
              </p>
            </div>
            <div class="hero-badges d-flex flex-wrap justify-content-end gap-2">
              <span class="badge">🔗 SVXLink</span>
              <span class="badge">🧩 <?= t('about_modular', 'Modular'); ?></span>
              <span class="badge"><i class="fa-solid fa-infinity"></i> <?= t('about_subtle', 'subtle'); ?></span>
            </div>
          </div>

          <hr class="soft-hr" style="background: linear-gradient(90deg, transparent, rgba(255,255,255,.35), transparent);">

          <div class="d-flex flex-wrap gap-2">
            <span class="pill"><span class="dot"></span> <?= t('about_dedicated_to_aurora', 'Dedicated to Aurora'); ?></span>
            <span class="pill"><i class="fa-solid fa-shield-halved"></i> <?= t('about_robustness', 'Robustness'); ?></span>
            <span class="pill"><i class="fa-solid fa-eye"></i> <?= t('about_visual', 'Visual'); ?></span>
          </div>
        </div>

        <div class="aurox-card glow-blue mb-3">
          <div class="seccion-titulo"><i class="fa-solid fa-globe"></i> <?= t('about_what_is', 'What is AuroxLink?'); ?></div>

          <p>
            <strong>AuroxLink</strong> <?= t('about_what_is_p1', 'is a modern and customizable platform to visualize, manage and extend the capabilities of the'); ?>
            <strong>SVXLink</strong>,
            <?= t('about_what_is_p1b', 'system, aimed at amateur radio operators and node operators.'); ?>
          </p>

          <p class="mb-2">
            <?= t('about_what_is_p2a', 'This interface was developed by'); ?>
            <strong><?= htmlspecialchars($teleco) ?></strong>,
            <?= t('about_what_is_p2b', 'known as'); ?>
            <strong>TELECOVIAJERO</strong>
            <?= t('about_what_is_p2c', 'on social media, with the goal of bringing technology closer to the community in a clear and practical way.'); ?>
          </p>

          <img src="img/auroxlink.png" alt="Banner AuroxLink" class="img-fluid img-banner mt-2">
          <div class="note"><?= t('about_note_design', 'Consistent design with the main dashboard: clean, readable and direct.'); ?></div>
        </div>

        <div class="aurox-card glow-blue mb-3">
          <div class="seccion-titulo"><i class="fa-solid fa-wand-magic-sparkles"></i> <?= t('about_name_origin', 'Origin of the name'); ?></div>

          <p>
            <?= t('about_name_origin_p1a', 'The name'); ?>
            <strong>AuroxLink</strong>
            <?= t('about_name_origin_p1b', 'was born as a tribute to'); ?>
            <strong>Aurora</strong>,
            <?= t('about_name_origin_p1c', 'my daughter, who is my inspiration and strength to keep creating. Just as auroras light up the sky, this system seeks to illuminate communications with an orderly and reliable visual experience.'); ?>
          </p>

          <ul class="mb-0">
            <li><strong>X</strong> <?= t('about_name_origin_x', 'represents the heart of the'); ?> <strong>SVXLink</strong> <?= t('about_system', 'system'); ?>.</li>
            <li><strong>Link</strong> <?= t('about_name_origin_link', 'symbolizes the connection between stations, operators and the passion for amateur radio.'); ?></li>
          </ul>
        </div>

        <div class="aurox-card glow-blue mb-3">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div class="seccion-titulo m-0"><i class="fa-solid fa-infinity"></i> <?= t('about_aurora_design', 'Aurora in the design (my why)'); ?></div>
            <span class="seal"><i class="fa-solid fa-heart"></i> Aurora inside</span>
          </div>

          <p class="mb-2">
            <?= t('about_aurora_p1a', 'This project is dedicated to'); ?>
            <strong>Aurora</strong>.
            <?= t('about_aurora_p1b', 'Not as a pretty phrase, but as a daily truth. She taught me that the world can feel different: that a tiny detail can be huge, and that a clear routine can be a form of peace.'); ?>
          </p>

          <div class="carta-aurora my-3">
            <p class="mb-2">
              <?= t('about_aurora_p2', 'Sometimes the road is difficult. There are meltdowns that exhaust, rejections that hurt, and a future that can feel overwhelming.'); ?>
            </p>
            <p class="mb-2">
              <?= t('about_aurora_p3', 'But something incredible also happens: a smile, a small sign of love, a look or a hug... and everything changes. Like when a signal becomes clean again and communication is finally understood.'); ?>
            </p>
            <p class="mb-0">
              <?= t('about_aurora_p4', 'That is why AUROXLINK tries to be like that: clear, stable, predictable and kind. A place where the noise goes down and connection becomes possible.'); ?>
            </p>
          </div>

          <p class="mb-2"><strong><?= t('about_guiding_principles', 'Principles that guide AUROXLINK:'); ?></strong></p>
          <ul class="mb-0">
            <li><strong><?= t('about_clarity', 'Clarity'); ?>:</strong> <?= t('about_clarity_text', 'important things are seen quickly.'); ?></li>
            <li><strong><?= t('about_predictability', 'Predictability'); ?>:</strong> <?= t('about_predictability_text', 'same patterns, same logic.'); ?></li>
            <li><strong><?= t('about_low_visual_noise', 'Low visual noise'); ?>:</strong> <?= t('about_low_visual_noise_text', 'less saturation, more calm.'); ?></li>
            <li><strong><?= t('about_kind_feedback', 'Kind feedback'); ?>:</strong> <?= t('about_kind_feedback_text', 'clear states without aggression.'); ?></li>
            <li><strong><?= t('about_accessibility', 'Accessibility'); ?>:</strong> <?= t('about_accessibility_text', 'readable, coherent and easy to use.'); ?></li>
          </ul>

          <hr class="soft-hr">

          <p class="mb-0" style="color:#045d56; font-weight:800;">
            🔵 ❤️ <?= t('about_dedicated_footer', 'Dedicated to Aurora — because a small gesture can change everything.'); ?>
          </p>
        </div>

        <div class="aurox-card glow-blue mb-3">
          <div class="seccion-titulo"><i class="fa-solid fa-screwdriver-wrench"></i> <?= t('about_recognition', 'Recognition to SVXLink'); ?></div>

          <p>
            <?= t('about_recognition_p1', 'This system is based on'); ?>
            <strong>SVXLink</strong>,
            <?= t('about_recognition_p1b', 'a free software created by'); ?>
            <strong>Tobias Blomberg (SM0SVX)</strong>.
          </p>

          <p class="mb-0">
            <?= t('about_recognition_p2', 'It is distributed under the'); ?>
            <a href="https://www.gnu.org/licenses/old-licenses/gpl-2.0.html" target="_blank" rel="noopener">Licencia GPLv2</a>.
            <?= t('about_recognition_p3', 'You can check its'); ?>
            <a href="https://github.com/sm0svx/svxlink" target="_blank" rel="noopener"><?= t('about_official_repo_here', 'official repository here'); ?></a>.
          </p>
        </div>

        <div class="aurox-card glow-blue mb-3">
          <div class="seccion-titulo"><i class="fa-solid fa-handshake"></i> <?= t('about_thanks', 'Acknowledgements'); ?></div>
          <p class="mb-0">
            <?= t('about_thanks_p1', 'Special thanks to'); ?>
            <strong><?= htmlspecialchars($hammer) ?></strong>,
            <?= t('about_thanks_p2', 'who actively collaborated in this project as a developer, contributing key ideas to strengthen it and project it into the future.'); ?>
          </p>
        </div>

        <div class="aurox-card glow-blue mb-3 text-center">
          <div class="seccion-titulo justify-content-center"><i class="fa-solid fa-heart"></i> <?= t('about_support_project', 'Support this project'); ?></div>

          <p class="mb-3"><?= t('about_support_text', 'If you want to support the development of AuroxLink and my content, you can do so by joining my YouTube Memberships:'); ?></p>

          <div class="d-grid mt-auto">
            <a href="https://www.youtube.com/channel/UCekZOnVxrOoDuJlFCgGKi9A/join"
               target="_blank" rel="noopener"
               class="btn btn-danger w-100">
              <i class="fab fa-youtube me-2"></i> <?= t('about_join_youtube_membership', 'Join YouTube Membership'); ?>
            </a>
          </div>

          <p class="fw-bold mt-4 mb-1">CA2RDP - TELECOVIAJERO</p>
          <p class="text-muted mb-2"><?= t('about_follow_me', 'Follow me on my social networks:'); ?></p>

          <div class="social-icons">
            <a href="https://youtube.com/@telecoviajero" target="_blank" rel="noopener" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
            <a href="https://instagram.com/telecoviajero" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="https://www.tiktok.com/@telecoviajero" target="_blank" rel="noopener" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
          </div>

          <hr class="soft-hr">

          <p class="mb-0" style="font-size:.92rem; color:#6c757d;">
            <i class="fa-solid fa-infinity" style="color:#0d6efd;"></i>
          </p>
        </div>

      </div>
    </div>
  </div>

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
