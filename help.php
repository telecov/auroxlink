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
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($idioma); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= t('help_title', 'AUROXLINK Help Center'); ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #0e1621;
      color: #d9e3ea;
      font-family: 'Segoe UI', sans-serif;
    }

    .section {
      background: linear-gradient(145deg, #11263c, #1b354d);
      border-left: 6px solid #00b4d8;
      border-radius: 14px;
      margin-bottom: 2rem;
      padding: 2rem;
      box-shadow: 0 4px 10px rgba(0, 180, 216, 0.1);
    }

    h1,
    h2,
    h3 {
      color: #00b4d8;
    }

    .table th {
      background-color: #00b4d8;
      color: #0e1621;
    }

    .table td {
      background-color: #1f2d3a;
      color: #d9e3ea;
    }

    .icon {
      margin-right: 8px;
      color: #00b4d8;
    }

    .desc {
      font-size: 0.92rem;
      color: #9fc0d3;
    }

    .aurox-badge {
      display: inline-block;
      background-color: #00b4d8;
      color: #0e1621;
      padding: 0.4rem 0.8rem;
      border-radius: 20px;
      font-weight: bold;
      font-size: 0.8rem;
    }

    .aurox-highlight {
      background-color: #1f2d3a;
      padding: 0.75rem 1rem;
      border-left: 5px solid #00b4d8;
      border-radius: 10px;
      margin-top: 1rem;
      font-size: 0.95rem;
      color: #ffffff;
    }
  </style>
</head>

<body>
  <div class="container py-5">
    <div class="section text-center">
      <h1><i class="bi bi-stars icon"></i><?= t('help_official_center', 'Official Help Center for'); ?> <strong>AUROXLINK</strong></h1>
      <div class="aurox-highlight mt-3">
        <strong><?= t('help_everything_title', 'Everything you need to know to get your AUROXLINK node running at 100%'); ?></strong><br>
        <?= t('help_everything_subtitle', 'explained for amateur radio operators'); ?> 🚀
      </div>
    </div>

    <div class="section">
      <h2><i class="bi bi-tools icon"></i><?= t('help_echolink_config', 'EchoLink Configuration'); ?> <span class="aurox-badge"><?= t('help_network_identity', 'Network Identity'); ?></span></h2>
      <p class="desc"><?= t('help_echolink_desc', 'Defines the main data that identifies your node on the EchoLink network. This includes your callsign, access password and connection parameters.'); ?></p>
      <table class="table table-bordered table-sm">
        <thead>
          <tr>
            <th><?= t('help_parameter', 'Parameter'); ?></th>
            <th><?= t('help_example', 'Example'); ?></th>
            <th><?= t('help_description', 'Description'); ?></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>CALLSIGN</td>
            <td>CE3XXX-L</td>
            <td><?= t('help_callsign_desc', 'Callsign registered in EchoLink (ends in -L or -R).'); ?></td>
          </tr>
          <tr>
            <td>PASSWORD</td>
            <td>ClaveSegura123</td>
            <td><?= t('help_password_desc', 'Password associated with your EchoLink callsign.'); ?></td>
          </tr>
          <tr>
            <td>SYSOPNAME</td>
            <td>Operador Juan</td>
            <td><?= t('help_sysop_desc', 'Name of the node administrator.'); ?></td>
          </tr>
          <tr>
            <td>LOCATION</td>
            <td>[AUROXLINK] 145.150MHz</td>
            <td><?= t('help_location_desc', 'Description with node location and frequency.'); ?></td>
          </tr>
          <tr>
            <td>DEFAULT_LANG</td>
            <td>es_CL</td>
            <td><?= t('help_default_lang_desc', 'Default language for system messages.'); ?></td>
          </tr>
          <tr>
            <td>REJECT_INCOMING</td>
            <td>^(CD2ABC|CE2ABC-L|CE2ABC-R)$</td>
            <td><?= t('help_reject_incoming_desc', 'Blocks incoming stations to your node. You can use one or more regular expressions, for example ^(CD2ABC|CE2ABC-L)$'); ?></td>
          </tr>
          <tr>
            <td>PROXY_SERVER</td>
            <td>proxy.miecholink.cl</td>
            <td><?= t('help_proxy_server_desc', 'Used if you are behind a strict NAT network.'); ?></td>
          </tr>
          <tr>
            <td>PROXY_PORT</td>
            <td>8100</td>
            <td><?= t('help_proxy_port_desc', 'Defines the connection port in case you use one different from 8100.'); ?></td>
          </tr>
          <tr>
            <td>PROXY_PASSWORD</td>
            <td>PUBLIC</td>
            <td><?= t('help_proxy_password_desc', 'Defines the proxy password if used.'); ?></td>
          </tr>
          <tr>
            <td>MAX_QSOS</td>
            <td>10</td>
            <td><?= t('help_max_qsos_desc', 'Maximum number of simultaneous QSOs.'); ?></td>
          </tr>
          <tr>
            <td>MAX_CONNECTIONS</td>
            <td>11</td>
            <td><?= t('help_max_connections_desc', 'Maximum number of allowed connections on your node.'); ?></td>
          </tr>
          <tr>
            <td>LINK_IDLE_TIMEOUT</td>
            <td>300</td>
            <td><?= t('help_idle_timeout_desc', 'Idle time before automatic disconnection.'); ?></td>
          </tr>
          <tr>
            <td>AUTOCON_ECHOLINK_ID</td>
            <td>123456</td>
            <td><?= t('help_autocon_desc', 'Automatically connects to a remote node on startup.'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="section">
      <h2><i class="bi bi-cpu icon"></i><?= t('help_technical_svxlink', 'SVXLink Technical Configuration'); ?> <span class="aurox-badge"><?= t('help_hardware_aprs', 'Hardware and APRS'); ?></span></h2>
      <p class="desc"><?= t('help_technical_desc', 'These parameters define how AUROXLINK interacts with your radio, sound interface and APRS location.'); ?></p>
      <table class="table table-bordered table-sm">
        <thead>
          <tr>
            <th><?= t('help_section', 'Section'); ?></th>
            <th><?= t('help_parameter', 'Parameter'); ?></th>
            <th><?= t('help_example', 'Example'); ?></th>
            <th><?= t('help_explanation', 'Explanation'); ?></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>[GLOBAL]</td>
            <td>LOCATION_INFO</td>
            <td>LocationInfo</td>
            <td><?= t('help_location_info_desc', 'Enables APRS position reporting.'); ?></td>
          </tr>

          <tr>
            <td>[SimplexLogic]</td>
            <td>CALLSIGN</td>
            <td>CA2XXX</td>
            <td><?= t('help_ham_callsign_desc', 'Amateur radio callsign.'); ?></td>
          </tr>

          <tr>
            <td>[Rx1]</td>
            <td>AUDIO_DEV</td>
            <td>alsa:plughw:1</td>
            <td><?= t('help_rx_audio_desc', 'Audio input device (receive).'); ?></td>
          </tr>
          <tr>
            <td>[Rx1]</td>
            <td>SQL_DET</td>
            <td>SERIAL</td>
            <td><?= t('help_sql_det_desc', 'Carrier detect method (VOX - GPIO - CTS - SERIAL_PIN - SIGLEV - USB).'); ?></td>
          </tr>
          <tr>
            <td>[Rx1]</td>
            <td>SQL_HANGTIME</td>
            <td>2000</td>
            <td><?= t('help_sql_hangtime_desc', 'Tail time in milliseconds before disconnecting the call.'); ?></td>
          </tr>
          <tr>
            <td>[Rx1]</td>
            <td>SERIAL_PORT</td>
            <td>/dev/ttyUSB0</td>
            <td><?= t('help_serial_port_desc', 'Physical detection port.'); ?></td>
          </tr>
          <tr>
            <td>[Rx1]</td>
            <td>SERIAL_PIN</td>
            <td>DSR</td>
            <td><?= t('help_serial_pin_desc', 'Activation mode (DSR - CTS - DCD).'); ?></td>
          </tr>
          <tr>
            <td>[Rx1]</td>
            <td>PREAMP</td>
            <td>-2</td>
            <td><?= t('help_preamp_desc', 'Preamp gain applied to the input audio before SVXLink processes it. Be careful: too much gain can saturate the audio and create distortion. It is recommended to test with small values first.'); ?></td>
          </tr>

          <tr>
            <td>[Tx1]</td>
            <td>AUDIO_DEV</td>
            <td>alsa:plughw:1</td>
            <td><?= t('help_tx_audio_desc', 'Audio device. Example .1 - .2 - .3'); ?></td>
          </tr>
          <tr>
            <td>[Tx1]</td>
            <td>PTT_TYPE</td>
            <td>SerialPin</td>
            <td><?= t('help_ptt_type_desc', 'Transmission control type (NONE - SerialPin - GPIO - EXE - USB).'); ?></td>
          </tr>
          <tr>
            <td>[Tx1]</td>
            <td>PTT_PORT</td>
            <td>/dev/ttyUSB0</td>
            <td><?= t('help_ptt_port_desc', 'Serial PTT activation.'); ?></td>
          </tr>
          <tr>
            <td>[Tx1]</td>
            <td>PTT_PIN</td>
            <td>DTRRTS</td>
            <td><?= t('help_ptt_pin_desc', 'Serial port pin used for PTT.'); ?></td>
          </tr>

          <tr>
            <td>[LocationInfo]</td>
            <td>CALLSIGN</td>
            <td>EL-CA2XXX</td>
            <td><?= t('help_aprs_callsign_desc', 'Node APRS callsign.'); ?></td>
          </tr>
          <tr>
            <td>[LocationInfo]</td>
            <td>APRS_SERVER_LIST</td>
            <td>aprs.server.com:10152</td>
            <td><?= t('help_aprs_server_desc', 'APRS reporting server.'); ?></td>
          </tr>
          <tr>
            <td>[LocationInfo]</td>
            <td>LAT/LON</td>
            <td>29.52.26S / 071.16.13W</td>
            <td><?= t('help_latlon_desc', 'Geographic location of the node.'); ?></td>
          </tr>
          <tr>
            <td>[LocationInfo]</td>
            <td>PATH</td>
            <td>WIDE1-1</td>
            <td><?= t('help_path_desc', 'APRS path for retransmitting packets.'); ?></td>
          </tr>
          <tr>
            <td>[LocationInfo]</td>
            <td>TX_POWER</td>
            <td>10</td>
            <td><?= t('help_tx_power_desc', 'Transmitter power in watts (informational only).'); ?></td>
          </tr>
          <tr>
            <td>[LocationInfo]</td>
            <td>ANTENNA_GAIN</td>
            <td>6</td>
            <td><?= t('help_antenna_gain_desc', 'Antenna gain in dBi (informational only).'); ?></td>
          </tr>
          <tr>
            <td>[LocationInfo]</td>
            <td>BEACON_INTERVAL</td>
            <td>10</td>
            <td><?= t('help_beacon_interval_desc', 'APRS beacon transmission interval.'); ?></td>
          </tr>
          <tr>
            <td>[LocationInfo]</td>
            <td>COMMENT</td>
            <td>[AUROXLINK]</td>
            <td><?= t('help_comment_desc', 'Text published on the APRS network.'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="section">
      <h2><i class="bi bi-person-lines-fill icon"></i><?= t('help_customization', 'AUROXLINK Customization'); ?> <span class="aurox-badge"><?= t('help_visual_identity', 'Visual Identity'); ?></span></h2>
      <p class="desc"><?= t('help_customization_desc', 'Here you can configure all visual and identity elements shown in the AUROXLINK dashboard.'); ?></p>
      <table class="table table-bordered table-sm">
        <thead>
          <tr>
            <th><?= t('help_field', 'Field'); ?></th>
            <th><?= t('help_example', 'Example'); ?></th>
            <th><?= t('help_description', 'Description'); ?></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= t('help_dashboard_name', 'Dashboard Name'); ?></td>
            <td>Mi Nodo EchoLink</td>
            <td><?= t('help_dashboard_name_desc', 'Top text of the main panel.'); ?></td>
          </tr>
          <tr>
            <td><?= t('help_operator_name', 'Radio Amateur'); ?></td>
            <td>Juan CE2XXX</td>
            <td><?= t('help_operator_name_desc', 'Name and callsign of the operator.'); ?></td>
          </tr>
          <tr>
            <td><?= t('help_mode', 'Mode'); ?></td>
            <td>SIMPLEX</td>
            <td><?= t('help_mode_desc', 'Operating type (SIMPLEX/DUPLEX).'); ?></td>
          </tr>
          <tr>
            <td><?= t('help_frequency', 'Frequency'); ?></td>
            <td>145.150 MHz</td>
            <td><?= t('help_frequency_desc', 'Operating frequency of the node.'); ?></td>
          </tr>
          <tr>
            <td><?= t('help_offset', 'Offset'); ?></td>
            <td>0.000</td>
            <td><?= t('help_offset_desc', 'Difference used in repeaters.'); ?></td>
          </tr>
          <tr>
            <td><?= t('help_tone', 'Tone'); ?></td>
            <td>88.5</td>
            <td><?= t('help_tone_desc', 'System CTCSS tone.'); ?></td>
          </tr>
          <tr>
            <td><?= t('help_location', 'Location'); ?></td>
            <td>Caleta San Pedro</td>
            <td><?= t('help_location_desc2', 'Visible geographic area.'); ?></td>
          </tr>
          <tr>
            <td><?= t('help_aprs_server', 'APRS Server'); ?></td>
            <td>http://miservidor.duckdns.org:8111</td>
            <td><?= t('help_aprs_server_desc2', 'Public tracking URL.'); ?></td>
          </tr>
          <tr>
            <td><?= t('help_logo_banner', 'Logo Banner'); ?></td>
            <td><?= t('help_upload_png', 'Upload PNG'); ?></td>
            <td><?= t('help_logo_banner_desc', 'Top image of the panel (upload it using the name [auroxlink_banner.png]).'); ?></td>
          </tr>
          <tr>
            <td><?= t('help_admin_photo', 'Admin Photo'); ?></td>
            <td><?= t('help_upload_png', 'Upload PNG'); ?></td>
            <td><?= t('help_admin_photo_desc', 'Administrator avatar (upload it using the name [admin.png]).'); ?></td>
          </tr>
          <tr>
            <td><?= t('help_colors', 'Colors'); ?></td>
            <td>#0e1621 / #00b4d8</td>
            <td><?= t('help_colors_desc', 'Background, sidebar and title colors.'); ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
