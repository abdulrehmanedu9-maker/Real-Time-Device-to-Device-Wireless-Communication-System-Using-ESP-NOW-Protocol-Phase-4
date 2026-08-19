<?php
require "config.php";

$page = isset($_GET['page']) ? $_GET['page'] : 'overview';
$valid_pages = ['overview', 'today', 'devices', 'sensor_types', 'detection_events', 'sensor_readings', 'statistics', 'diagram', 'graphical_abstract', 'graphical_format', 'ai_anomalies', 'machine_learning'];
if (!in_array($page, $valid_pages)) {
    $page = 'overview';
}

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/* Renders a "Showing X-Y of Z" line plus First/Prev/Next/Last links.
   $extra_qs is the current filter query string (without page_num) so
   pagination links keep whatever filters are active. */
function render_pagination($current_page_num, $per_page, $total_rows, $extra_qs, $page_param_name = 'pg') {
    $total_pages = max(1, (int)ceil($total_rows / $per_page));
    $current_page_num = max(1, min($current_page_num, $total_pages));
    $start = $total_rows === 0 ? 0 : (($current_page_num - 1) * $per_page) + 1;
    $end = min($total_rows, $current_page_num * $per_page);

    $base = '?' . $extra_qs . '&' . $page_param_name . '=';
    echo '<div class="filter-bar" style="justify-content:space-between;padding:14px 20px;border-top:1px solid var(--border-hair);">';
    echo '<span class="mono" style="color:var(--text-muted);font-size:19px;">Showing ' . h($start) . '-' . h($end) . ' of ' . h(number_format($total_rows)) . ' rows</span>';
    echo '<div class="filter-bar">';
    $first_disabled = $current_page_num <= 1;
    echo '<a class="badge ' . ($first_disabled ? 'confirmed' : 'sender') . '" style="text-decoration:none;' . ($first_disabled ? 'opacity:0.4;pointer-events:none;' : '') . '" href="' . $base . '1">First</a>';
    echo '<a class="badge ' . ($first_disabled ? 'confirmed' : 'sender') . '" style="text-decoration:none;' . ($first_disabled ? 'opacity:0.4;pointer-events:none;' : '') . '" href="' . $base . h($current_page_num - 1) . '">Prev</a>';
    echo '<span class="mono" style="color:var(--text-primary);font-size:19px;padding:6px 4px;">Page ' . h($current_page_num) . ' / ' . h($total_pages) . '</span>';
    $last_disabled = $current_page_num >= $total_pages;
    echo '<a class="badge ' . ($last_disabled ? 'confirmed' : 'sender') . '" style="text-decoration:none;' . ($last_disabled ? 'opacity:0.4;pointer-events:none;' : '') . '" href="' . $base . h($current_page_num + 1) . '">Next</a>';
    echo '<a class="badge ' . ($last_disabled ? 'confirmed' : 'sender') . '" style="text-decoration:none;' . ($last_disabled ? 'opacity:0.4;pointer-events:none;' : '') . '" href="' . $base . h($total_pages) . '">Last</a>';
    echo '</div></div>';
}

$nav_items = [
    'overview'           => ['label' => 'Overview',            'glyph' => '01'],
    'today'              => ['label' => "Today's Detections",  'glyph' => '02'],
    'devices'            => ['label' => 'Devices',             'glyph' => '03'],
    'sensor_types'       => ['label' => 'Sensor Types',        'glyph' => '04'],
    'detection_events'   => ['label' => 'Detection Events',    'glyph' => '05'],
    'sensor_readings'    => ['label' => 'Sensor Readings',     'glyph' => '06'],
    'statistics'         => ['label' => 'Statistics',          'glyph' => '07'],
    'diagram'            => ['label' => 'Database Diagram',    'glyph' => '08'],
    'graphical_abstract' => ['label' => 'Graphical Abstract',  'glyph' => '09'],
    'graphical_format'   => ['label' => 'Graphical Format',    'glyph' => '10'],
    'ai_anomalies'       => ['label' => 'AI Anomaly Detection','glyph' => '11'],
    'machine_learning'   => ['label' => 'Machine Learning',    'glyph' => '12'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Obstacle Detection Console</title>
<script>
  (function() {
    var saved = localStorage.getItem('dashboardTheme');
    var theme = saved === 'light' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', theme);

    var savedSidebar = localStorage.getItem('dashboardSidebar');
    var sidebarState = savedSidebar === 'collapsed' ? 'collapsed' : 'expanded';
    document.documentElement.setAttribute('data-sidebar', sidebarState);
  })();
</script>
<link rel="stylesheet" href="style.css?v=7">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>

<!-- PWA / installable app support: this lets the dashboard be installed
     as an app on Android, desktop (Chrome/Edge), and be wrapped later
     for the Play Store or macOS with no changes to the dashboard itself. -->
<link rel="manifest" href="manifest.json">
<link rel="icon" href="icons/favicon-64.png">
<link rel="apple-touch-icon" href="icons/icon-192.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="ObstacleDetect">
<meta name="theme-color" content="#0D1317" id="themeColorMeta">
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('sw.js').catch(function() {});
    });
  }
</script>
</head>
<body>
<div class="mobile-topbar">
  <button class="hamburger-btn" onclick="toggleMobileSidebar()" aria-label="Open menu"></button>
  <div class="brand-text" style="font-size:15px;">Obstacle Detection</div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>
<div class="shell">

  <aside class="sidebar" id="sidebar">
    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleDesktopSidebar()" aria-label="Toggle sidebar" title="Open or close sidebar">
      <span></span><span></span><span></span>
    </button>

    <div class="sidebar-content">
      <div class="brand">
        <div class="brand-dot"></div>
        <div class="brand-text">Obstacle Detection<small>SENDER / RECEIVER NODEMCU</small></div>
      </div>

      <div class="theme-toggle">
        <span class="theme-toggle-label" id="themeLabel">
          <svg id="themeIcon" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
          Dark Mode
        </span>
        <button class="theme-switch" id="themeSwitch" onclick="toggleDashboardTheme()" aria-label="Toggle dark or light theme">
          <span class="knob"></span>
        </button>
      </div>

      <div class="nav-label">Console</div>
      <?php foreach ($nav_items as $key => $item): ?>
        <a class="nav-link <?= $page === $key ? 'active' : '' ?>" href="?page=<?= $key ?>" onclick="closeMobileSidebar()">
        <span class="glyph"><?= $item['glyph'] ?></span> <?= $item['label'] ?>
      </a>
    <?php endforeach; ?>
    </div>
  </aside>

  <main class="main">
    <?php

    if ($page === 'overview') {

        $total = $conn->query("SELECT COUNT(*) AS c FROM detection_events")->fetch_assoc()['c'];
        $today_count = $conn->query("SELECT COUNT(*) AS c FROM detection_events WHERE DATE(event_time) = CURDATE()")->fetch_assoc()['c'];
        $last_minute = $conn->query("SELECT COUNT(*) AS c FROM detection_events WHERE event_time >= NOW() - INTERVAL 60 SECOND")->fetch_assoc()['c'];
        $last_hour = $conn->query("SELECT COUNT(*) AS c FROM detection_events WHERE event_time >= NOW() - INTERVAL 1 HOUR")->fetch_assoc()['c'];
        $device_count = $conn->query("SELECT COUNT(*) AS c FROM devices")->fetch_assoc()['c'];
        $sensor_type_count = $conn->query("SELECT COUNT(*) AS c FROM sensor_types")->fetch_assoc()['c'];
        $critical_last_hour = $conn->query("SELECT COUNT(*) AS c FROM detection_events WHERE final_status = 'critical' AND event_time >= NOW() - INTERVAL 1 HOUR")->fetch_assoc()['c'];
        $date_range = $conn->query("SELECT MIN(event_time) AS earliest, MAX(event_time) AS latest FROM detection_events")->fetch_assoc();

        $status_counts = $conn->query("
            SELECT
                SUM(final_status = 'normal') AS normal_c,
                SUM(final_status = 'warning') AS warning_c,
                SUM(final_status = 'critical') AS critical_c,
                SUM(is_pattern_anomaly = 1) AS anomaly_c
            FROM detection_events
        ")->fetch_assoc();

        $device_split = ['sender' => 0, 'receiver' => 0];
        $device_result = $conn->query("
            SELECT d.device_role, COUNT(*) AS c
            FROM detection_events de JOIN devices d ON d.device_id = de.device_id
            GROUP BY d.device_role
        ");
        while ($row = $device_result->fetch_assoc()) {
            $device_split[$row['device_role']] = $row['c'];
        }

        $sensor_split = ['Ultrasonic' => 0, 'PIR Motion' => 0];
        $sensor_result = $conn->query("
            SELECT st.sensor_name, COUNT(*) AS c
            FROM sensor_readings sr JOIN sensor_types st ON st.sensor_type_id = sr.sensor_type_id
            GROUP BY st.sensor_name
        ");
        while ($row = $sensor_result->fetch_assoc()) {
            $sensor_split[$row['sensor_name']] = $row['c'];
        }

        $latest = $conn->query("
            SELECT de.event_id, de.event_time, de.final_status, de.is_pattern_anomaly, sr.reading_value
            FROM detection_events de
            JOIN sensor_readings sr ON sr.event_id = de.event_id
            JOIN sensor_types st ON st.sensor_type_id = sr.sensor_type_id
            WHERE st.sensor_name = 'Ultrasonic'
            ORDER BY de.event_id DESC LIMIT 1
        ")->fetch_assoc();
        ?>
        <div class="page-header">
          <div>
            <div class="page-eyebrow">System Status</div>
            <div class="page-title">Overview</div>
          </div>
          <div class="live-chip"><span class="live-dot"></span> Live from MySQL</div>
        </div>

        <div class="panel">
          <div class="radar-wrap">
            <div class="radar-scope">
              <div class="radar-ring r1"></div>
              <div class="radar-ring r2"></div>
              <div class="radar-ring r3"></div>
              <div class="radar-sweep"></div>
              <div class="radar-center"></div>
              <?php if ($latest): ?>
                <div class="radar-blip" style="left: 62%; top: 34%;"></div>
              <?php endif; ?>
            </div>
            <div class="radar-readout">
              <div class="label">Last Confirmed Distance</div>
              <div class="value">
                <?= $latest ? h(number_format($latest['reading_value'], 2)) : '--' ?><span>cm</span>
              </div>
              <div class="meta">
                <?= $latest ? 'Event #' . h($latest['event_id']) . ' at ' . h($latest['event_time']) : 'No detections recorded yet' ?>
                <?php if ($latest): ?>
                  <span class="badge <?= h($latest['final_status']) ?>" style="margin-left:8px;"><?= h($latest['final_status']) ?></span>
                  <?php if ($latest['is_pattern_anomaly']): ?>
                    <span class="badge critical" style="margin-left:4px;">pattern anomaly</span>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head"><div><h3>System Totals</h3><p>Every number below reflects the entire database, not just a recent window</p></div></div>
          <table>
            <thead><tr><th>Metric</th><th>Value</th></tr></thead>
            <tbody>
              <tr><td>Total Entries</td><td class="mono"><?= h(number_format($total)) ?> <span style="color:var(--text-faint);font-size:18px;">(<?= h(number_format($today_count)) ?> today)</span></td></tr>
              <tr><td>Last 60 Seconds</td><td class="mono" style="color:var(--amber);"><?= h(number_format($last_minute)) ?></td></tr>
              <tr><td>Last 1 Hour</td><td class="mono" style="color:var(--teal);"><?= h(number_format($last_hour)) ?></td></tr>
              <tr><td>Critical, Last Hour</td><td class="mono" style="color:var(--alert);"><?= h(number_format($critical_last_hour)) ?></td></tr>
              <tr><td>Registered Devices</td><td class="mono"><?= h($device_count) ?></td></tr>
              <tr><td>Registered Sensor Types</td><td class="mono"><?= h($sensor_type_count) ?></td></tr>
              <tr><td>Entries from Sender Car</td><td class="mono" style="color:var(--amber);"><?= h(number_format($device_split['sender'])) ?></td></tr>
              <tr><td>Entries from Receiver Car</td><td class="mono" style="color:var(--teal);"><?= h(number_format($device_split['receiver'])) ?></td></tr>
              <tr><td>Ultrasonic Readings</td><td class="mono"><?= h(number_format($sensor_split['Ultrasonic'])) ?></td></tr>
              <tr><td>PIR Motion Readings</td><td class="mono"><?= h(number_format($sensor_split['PIR Motion'])) ?></td></tr>
              <tr><td>Normal Severity</td><td class="mono" style="color:var(--teal);"><?= h(number_format($status_counts['normal_c'] ?? 0)) ?></td></tr>
              <tr><td>Warning Severity</td><td class="mono" style="color:var(--amber);"><?= h(number_format($status_counts['warning_c'] ?? 0)) ?></td></tr>
              <tr><td>Critical Severity</td><td class="mono" style="color:var(--alert);"><?= h(number_format($status_counts['critical_c'] ?? 0)) ?></td></tr>
              <tr><td>Pattern Anomalies</td><td class="mono" style="color:var(--alert);"><?= h(number_format($status_counts['anomaly_c'] ?? 0)) ?></td></tr>
              <tr>
                <td>Full Dataset Range</td>
                <td class="mono" style="color:var(--teal);">
                  <?php if ($total > 0 && $date_range['earliest']): ?>
                    <?= h(date('d M Y', strtotime($date_range['earliest']))) ?> &rarr; <?= h(date('d M Y', strtotime($date_range['latest']))) ?>
                  <?php else: ?>
                    No data yet
                  <?php endif; ?>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="panel" style="padding:14px 20px;">
          <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <span class="mono" style="color:var(--text-faint);font-size:16px;">STATUS KEY</span>
            <span class="badge critical">critical</span><span class="mono" style="color:var(--text-faint);font-size:17px;">0-7cm</span>
            <span class="badge warning">warning</span><span class="mono" style="color:var(--text-faint);font-size:17px;">8-14cm</span>
            <span class="badge normal">normal</span><span class="mono" style="color:var(--text-faint);font-size:17px;">15-20cm</span>
          </div>
        </div>
        <?php

    } elseif ($page === 'today') {

        $today_count = $conn->query("SELECT COUNT(*) AS c FROM detection_events WHERE DATE(event_time) = CURDATE()")->fetch_assoc()['c'];

        $today = $conn->query("
            SELECT de.event_id, de.event_time, de.final_status, d.device_name, sr.reading_value
            FROM detection_events de
            JOIN devices d ON d.device_id = de.device_id
            JOIN sensor_readings sr ON sr.event_id = de.event_id
            JOIN sensor_types st ON st.sensor_type_id = sr.sensor_type_id
            WHERE st.sensor_name = 'Ultrasonic' AND DATE(de.event_time) = CURDATE()
            ORDER BY de.event_id DESC LIMIT 1000
        ");
        ?>
        <div class="page-header">
          <div>
            <div class="page-eyebrow">Live Log</div>
            <div class="page-title">Today's Detections</div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div><h3><?= h(date('l, d F Y')) ?></h3><p>Resets automatically at midnight &mdash; <?= h(number_format($today_count)) ?> entries so far today</p></div>
            <a class="badge sender" style="text-decoration:none;" href="?page=detection_events">Full History &rarr;</a>
          </div>
          <?php if ($today->num_rows === 0): ?>
            <div class="empty-state">No detections recorded today yet.</div>
          <?php else: ?>
          <div style="overflow-x:auto;">
          <table>
            <thead><tr><th>Event ID</th><th>Device</th><th>Distance</th><th>Status</th><th>Time</th></tr></thead>
            <tbody>
              <?php while ($row = $today->fetch_assoc()): ?>
              <tr>
                <td class="mono">#<?= h($row['event_id']) ?></td>
                <td><?= h($row['device_name']) ?></td>
                <td class="mono"><?= h(number_format($row['reading_value'], 2)) ?> cm</td>
                <td><span class="badge <?= h($row['final_status']) ?>"><?= h($row['final_status']) ?></span></td>
                <td class="mono"><?= h($row['event_time']) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          </div>
          <?php endif; ?>
        </div>
        <?php

    } elseif ($page === 'devices') {

        $result = $conn->query("SELECT * FROM devices ORDER BY device_id");
        ?>
        <div class="page-header">
          <div><div class="page-eyebrow">Table 01</div><div class="page-title">Devices</div></div>
        </div>
        <div class="panel">
          <div class="panel-head"><div><h3>Registered NodeMCU Devices</h3><p>Every physical sender or receiver board known to the system</p></div></div>
          <table>
            <thead><tr><th>Device ID</th><th>Name</th><th>MAC Address</th><th>Role</th></tr></thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td class="mono">#<?= h($row['device_id']) ?></td>
                <td><?= h($row['device_name']) ?></td>
                <td class="mono"><?= h($row['mac_address']) ?></td>
                <td><span class="badge <?= h($row['device_role']) ?>"><?= h($row['device_role']) ?></span></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php

    } elseif ($page === 'sensor_types') {

        $result = $conn->query("SELECT * FROM sensor_types ORDER BY sensor_type_id");
        ?>
        <div class="page-header">
          <div><div class="page-eyebrow">Table 02</div><div class="page-title">Sensor Types</div></div>
        </div>
        <div class="panel">
          <div class="panel-head"><div><h3>Known Sensor Types</h3><p>New sensors, radar, camera, etc, are added here without changing any other table</p></div></div>
          <table>
            <thead><tr><th>Sensor Type ID</th><th>Name</th><th>Unit</th></tr></thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td class="mono">#<?= h($row['sensor_type_id']) ?></td>
                <td><?= h($row['sensor_name']) ?></td>
                <td class="mono"><?= h($row['unit']) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php

    } elseif ($page === 'detection_events') {

        $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
        $device_filter = isset($_GET['device']) ? intval($_GET['device']) : 0;
        $pattern_filter = isset($_GET['pattern_anomaly']) ? trim($_GET['pattern_anomaly']) : '';
        $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
        $time_from = isset($_GET['time_from']) ? trim($_GET['time_from']) : '';
        $time_to = isset($_GET['time_to']) ? trim($_GET['time_to']) : '';

        $devices_list = $conn->query("SELECT device_id, device_name FROM devices ORDER BY device_id");

        $sql = "
            SELECT de.event_id, de.event_time, de.is_confirmed, de.severity_level,
                   de.measured_interval_sec, de.is_pattern_anomaly, de.final_status,
                   d.device_name, d.device_role
            FROM detection_events de
            JOIN devices d ON d.device_id = de.device_id
            WHERE 1=1
        ";
        if ($device_filter > 0) $sql .= " AND d.device_id = " . intval($device_filter);
        if (in_array($status_filter, ['normal', 'warning', 'critical'], true)) {
            $sql .= " AND de.final_status = '" . $conn->real_escape_string($status_filter) . "'";
        }
        if (in_array($pattern_filter, ['yes', 'no'], true)) {
            $sql .= " AND de.is_pattern_anomaly = " . ($pattern_filter === 'yes' ? '1' : '0');
        }
        if ($date_from !== '') $sql .= " AND DATE(de.event_time) >= '" . $conn->real_escape_string($date_from) . "'";
        if ($date_to !== '') $sql .= " AND DATE(de.event_time) <= '" . $conn->real_escape_string($date_to) . "'";
        if ($time_from !== '') $sql .= " AND TIME(de.event_time) >= '" . $conn->real_escape_string($time_from) . "'";
        if ($time_to !== '') $sql .= " AND TIME(de.event_time) <= '" . $conn->real_escape_string($time_to) . "'";

        $count_sql = str_replace(
            "SELECT de.event_id, de.event_time, de.is_confirmed, de.severity_level,\n                   de.measured_interval_sec, de.is_pattern_anomaly, de.final_status,\n                   d.device_name, d.device_role",
            "SELECT COUNT(*) AS total",
            $sql
        );
        $total_rows = (int)$conn->query($count_sql)->fetch_assoc()['total'];

        $per_page = 100;
        $current_page_num = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
        $offset = ($current_page_num - 1) * $per_page;

        $sql .= " ORDER BY de.event_id DESC LIMIT " . $per_page . " OFFSET " . $offset;

        $result = $conn->query($sql);

        $filter_qs = http_build_query([
            'page' => 'detection_events',
            'status' => $status_filter, 'device' => $device_filter,
            'pattern_anomaly' => $pattern_filter,
            'date_from' => $date_from, 'date_to' => $date_to,
            'time_from' => $time_from, 'time_to' => $time_to,
        ]);
        ?>
        <div class="page-header">
          <div><div class="page-eyebrow">Table 03</div><div class="page-title">Detection Events</div></div>
        </div>
        <div class="panel" style="padding:16px 20px;">
          <h3 style="font-family:var(--font-display);font-size:19px;margin:0 0 10px 0;">What These Labels Mean</h3>
          <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <span class="badge critical">critical</span><span class="mono" style="color:#8B9AA1;font-size:17px;">0 to 7 cm, object very close</span>
            <span class="badge warning">warning</span><span class="mono" style="color:#8B9AA1;font-size:17px;">8 to 14 cm, object approaching</span>
            <span class="badge normal">normal</span><span class="mono" style="color:#8B9AA1;font-size:17px;">15 to 20 cm, safe distance</span>
          </div>
          <p style="color:#8B9AA1;font-size:17px;margin:12px 0 0 0;">Pattern Anomaly is marked yes when 3 or more of the last 5 confirmed detections were spaced more than 25 percent away from the expected 1 second gap. Final Status combines distance and pattern: a warning distance with a pattern anomaly is raised to critical, and a normal distance with a pattern anomaly is raised to warning.</p>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div><h3>Search This Table</h3><p>Filter by status, device, date, or time to find a specific set of events</p></div>
          </div>
          <form class="filter-bar" method="get" style="padding: 0 20px 20px 20px; flex-wrap: wrap;">
            <input type="hidden" name="page" value="detection_events">
            <select name="status">
              <option value="">All statuses</option>
              <option value="normal" <?= $status_filter === 'normal' ? 'selected' : '' ?>>Normal</option>
              <option value="warning" <?= $status_filter === 'warning' ? 'selected' : '' ?>>Warning</option>
              <option value="critical" <?= $status_filter === 'critical' ? 'selected' : '' ?>>Critical</option>
            </select>
            <select name="device">
              <option value="0">All devices</option>
              <?php while ($d = $devices_list->fetch_assoc()): ?>
                <option value="<?= $d['device_id'] ?>" <?= $device_filter === (int)$d['device_id'] ? 'selected' : '' ?>><?= h($d['device_name']) ?></option>
              <?php endwhile; ?>
            </select>
            <select name="pattern_anomaly">
              <option value="">Pattern anomaly: any</option>
              <option value="yes" <?= $pattern_filter === 'yes' ? 'selected' : '' ?>>Pattern anomaly: yes</option>
              <option value="no" <?= $pattern_filter === 'no' ? 'selected' : '' ?>>Pattern anomaly: no</option>
            </select>
            <span class="mono" style="color:#8B9AA1;font-size:16px;">Date</span>
            <input type="date" name="date_from" value="<?= h($date_from) ?>" title="From date">
            <input type="date" name="date_to" value="<?= h($date_to) ?>" title="To date">
            <span class="mono" style="color:#8B9AA1;font-size:16px;">Time</span>
            <input type="time" name="time_from" value="<?= h($time_from) ?>" title="From time">
            <input type="time" name="time_to" value="<?= h($time_to) ?>" title="To time">
            <button type="submit">Apply Filter</button>
          </form>
          <div class="panel-head" style="border-top: 1px solid var(--border-hair);">
            <div><h3 style="font-size:18px;">Export This Result</h3></div>
            <div class="filter-bar">
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=detection_events&format=csv&<?= $filter_qs ?>">CSV</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=detection_events&format=txt&<?= $filter_qs ?>">Text</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=detection_events&format=html&<?= $filter_qs ?>">HTML</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=detection_events&format=pdf&<?= $filter_qs ?>">PDF</a>
            </div>
          </div>

          <div style="padding:0 20px 18px 20px;">
            <div class="stat-card" style="display:flex;align-items:center;gap:14px;">
              <div>
                <div class="stat-label">Matching Entries</div>
                <div class="stat-value <?= $status_filter === 'critical' ? 'alert' : ($status_filter === 'warning' ? 'amber' : ($status_filter === 'normal' ? 'teal' : '')) ?>"><?= h(number_format($total_rows)) ?></div>
              </div>
              <div class="mono" style="color:var(--text-muted);font-size:19px;">
                <?php
                  $desc_parts = [];
                  if ($status_filter !== '') $desc_parts[] = "status = " . $status_filter;
                  if ($device_filter > 0) $desc_parts[] = "device filtered";
                  if ($date_from !== '' || $date_to !== '') $desc_parts[] = "date range applied";
                  if ($time_from !== '' || $time_to !== '') $desc_parts[] = "time range applied";
                  echo $desc_parts ? h(implode(', ', $desc_parts)) : 'across the whole database (no filter applied)';
                ?>
              </div>
            </div>
          </div>

          <?php if ($result->num_rows === 0): ?>
            <div class="empty-state">No events match this filter.</div>
          <?php else: ?>
          <table>
            <thead><tr><th>Event ID</th><th>Device</th><th>Confirmed</th><th>Severity</th><th>Interval</th><th>Pattern Anomaly</th><th>Final Status</th><th>Time</th></tr></thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td class="mono">#<?= h($row['event_id']) ?></td>
                <td><?= h($row['device_name']) ?></td>
                <td><?php if ($row['is_confirmed']): ?><span class="badge confirmed">confirmed</span><?php else: ?><span class="mono">pending</span><?php endif; ?></td>
                <td><span class="badge <?= h($row['severity_level']) ?>"><?= h($row['severity_level']) ?></span></td>
                <td class="mono"><?= $row['measured_interval_sec'] !== null ? h(number_format($row['measured_interval_sec'], 2)) . ' s' : '--' ?></td>
                <td><?php if ($row['is_pattern_anomaly']): ?><span class="badge critical">yes</span><?php else: ?><span class="mono">no</span><?php endif; ?></td>
                <td><span class="badge <?= h($row['final_status']) ?>"><?= h($row['final_status']) ?></span></td>
                <td class="mono"><?= h($row['event_time']) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php render_pagination($current_page_num, $per_page, $total_rows, $filter_qs); ?>
          <?php endif; ?>
        </div>
        <?php

    } elseif ($page === 'sensor_readings') {

        $device_filter = isset($_GET['device']) ? intval($_GET['device']) : 0;
        $sensor_filter = isset($_GET['sensor']) ? intval($_GET['sensor']) : 0;
        $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
        $time_from = isset($_GET['time_from']) ? trim($_GET['time_from']) : '';
        $time_to = isset($_GET['time_to']) ? trim($_GET['time_to']) : '';
        $distance_bucket = isset($_GET['distance_bucket']) ? trim($_GET['distance_bucket']) : '';
        $distance_from = isset($_GET['distance_from']) ? trim($_GET['distance_from']) : '';
        $distance_to = isset($_GET['distance_to']) ? trim($_GET['distance_to']) : '';

        $devices_list = $conn->query("SELECT device_id, device_name FROM devices ORDER BY device_id");
        $sensors_list = $conn->query("SELECT sensor_type_id, sensor_name FROM sensor_types ORDER BY sensor_type_id");

        $sql = "
            SELECT sr.reading_id, sr.event_id, d.device_name, st.sensor_name, sr.reading_value, st.unit, de.event_time
            FROM sensor_readings sr
            JOIN detection_events de ON de.event_id = sr.event_id
            JOIN devices d ON d.device_id = de.device_id
            JOIN sensor_types st ON st.sensor_type_id = sr.sensor_type_id
            WHERE 1=1
        ";
        if ($device_filter > 0) $sql .= " AND d.device_id = " . intval($device_filter);
        if ($sensor_filter > 0) $sql .= " AND st.sensor_type_id = " . intval($sensor_filter);
        if ($date_from !== '') $sql .= " AND DATE(de.event_time) >= '" . $conn->real_escape_string($date_from) . "'";
        if ($date_to !== '') $sql .= " AND DATE(de.event_time) <= '" . $conn->real_escape_string($date_to) . "'";
        if ($time_from !== '') $sql .= " AND TIME(de.event_time) >= '" . $conn->real_escape_string($time_from) . "'";
        if ($time_to !== '') $sql .= " AND TIME(de.event_time) <= '" . $conn->real_escape_string($time_to) . "'";

        // Distance filter: a custom from/to range takes priority; otherwise
        // the bucket dropdown builds a range automatically, bucket "3" means
        // "greater than 2 and up to 3", matching the pattern the user asked for.
        if ($distance_from !== '' || $distance_to !== '') {
            if ($distance_from !== '') $sql .= " AND sr.reading_value >= " . floatval($distance_from);
            if ($distance_to !== '') $sql .= " AND sr.reading_value <= " . floatval($distance_to);
        } elseif ($distance_bucket !== '' && is_numeric($distance_bucket)) {
            $bucket_n = floatval($distance_bucket);
            $sql .= " AND sr.reading_value > " . ($bucket_n - 1) . " AND sr.reading_value <= " . $bucket_n;
        }

        $count_sql = str_replace(
            "SELECT sr.reading_id, sr.event_id, d.device_name, st.sensor_name, sr.reading_value, st.unit, de.event_time",
            "SELECT COUNT(*) AS total",
            $sql
        );
        $total_rows = (int)$conn->query($count_sql)->fetch_assoc()['total'];

        $per_page = 100;
        $current_page_num = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
        $offset = ($current_page_num - 1) * $per_page;

        $sql .= " ORDER BY sr.reading_id DESC LIMIT " . $per_page . " OFFSET " . $offset;

        $result = $conn->query($sql);

        // Build the current filter querystring once, reused by the export buttons
        $filter_qs = http_build_query([
            'page' => 'sensor_readings',
            'device' => $device_filter, 'sensor' => $sensor_filter,
            'date_from' => $date_from, 'date_to' => $date_to,
            'time_from' => $time_from, 'time_to' => $time_to,
            'distance_bucket' => $distance_bucket,
            'distance_from' => $distance_from, 'distance_to' => $distance_to,
        ]);
        ?>
        <div class="page-header">
          <div><div class="page-eyebrow">Table 04, Joined View</div><div class="page-title">Sensor Readings</div></div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div><h3>Search This Table</h3><p>Filter by device, sensor, date, time, or distance to find a specific pattern</p></div>
          </div>
          <form class="filter-bar" method="get" style="padding: 0 20px 20px 20px; flex-wrap: wrap;">
            <input type="hidden" name="page" value="sensor_readings">
            <select name="device">
              <option value="0">All devices</option>
              <?php while ($d = $devices_list->fetch_assoc()): ?>
                <option value="<?= $d['device_id'] ?>" <?= $device_filter === (int)$d['device_id'] ? 'selected' : '' ?>><?= h($d['device_name']) ?></option>
              <?php endwhile; ?>
            </select>
            <select name="sensor">
              <option value="0">All sensors</option>
              <?php while ($s = $sensors_list->fetch_assoc()): ?>
                <option value="<?= $s['sensor_type_id'] ?>" <?= $sensor_filter === (int)$s['sensor_type_id'] ? 'selected' : '' ?>><?= h($s['sensor_name']) ?></option>
              <?php endwhile; ?>
            </select>
            <span class="mono" style="color:#8B9AA1;font-size:16px;">Date</span>
            <input type="date" name="date_from" value="<?= h($date_from) ?>" title="From date">
            <input type="date" name="date_to" value="<?= h($date_to) ?>" title="To date">
            <span class="mono" style="color:#8B9AA1;font-size:16px;">Time</span>
            <input type="time" name="time_from" value="<?= h($time_from) ?>" title="From time">
            <input type="time" name="time_to" value="<?= h($time_to) ?>" title="To time">
            <span class="mono" style="color:#8B9AA1;font-size:16px;">Distance bucket (cm)</span>
            <select name="distance_bucket">
              <option value="">None</option>
              <?php for ($b = 1; $b <= 20; $b++): ?>
                <option value="<?= $b ?>" <?= $distance_bucket === (string)$b ? 'selected' : '' ?>><?= $b - 1 ?> to <?= $b ?> cm</option>
              <?php endfor; ?>
            </select>
            <span class="mono" style="color:#8B9AA1;font-size:16px;">or custom range (cm)</span>
            <input type="number" step="0.1" name="distance_from" value="<?= h($distance_from) ?>" placeholder="min" style="width:70px;">
            <input type="number" step="0.1" name="distance_to" value="<?= h($distance_to) ?>" placeholder="max" style="width:70px;">
            <button type="submit">Apply Filter</button>
          </form>
          <div class="panel-head" style="border-top: 1px solid var(--border-hair);">
            <div><h3 style="font-size:18px;">Export This Result</h3></div>
            <div class="filter-bar">
              <a class="badge sender" style="text-decoration:none;" href="export.php?format=csv&<?= $filter_qs ?>">CSV</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?format=txt&<?= $filter_qs ?>">Text</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?format=html&<?= $filter_qs ?>">HTML</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?format=pdf&<?= $filter_qs ?>">PDF</a>
            </div>
          </div>

          <div style="padding:0 20px 18px 20px;">
            <div class="stat-card" style="display:flex;align-items:center;gap:14px;">
              <div>
                <div class="stat-label">Matching Readings</div>
                <div class="stat-value"><?= h(number_format($total_rows)) ?></div>
              </div>
              <div class="mono" style="color:var(--text-muted);font-size:19px;">
                <?php
                  $desc_parts = [];
                  if ($device_filter > 0) $desc_parts[] = "device filtered";
                  if ($sensor_filter > 0) $desc_parts[] = "sensor filtered";
                  if ($date_from !== '' || $date_to !== '') $desc_parts[] = "date range applied";
                  if ($time_from !== '' || $time_to !== '') $desc_parts[] = "time range applied";
                  if ($distance_bucket !== '' || $distance_from !== '' || $distance_to !== '') $desc_parts[] = "distance range applied";
                  echo $desc_parts ? h(implode(', ', $desc_parts)) : 'across the whole database (no filter applied)';
                ?>
              </div>
            </div>
          </div>

          <?php if ($result->num_rows === 0): ?>
            <div class="empty-state">No readings match this filter.</div>
          <?php else: ?>
          <table>
            <thead><tr><th>Reading ID</th><th>Event ID</th><th>Device</th><th>Sensor</th><th>Value</th><th>Time</th></tr></thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td class="mono">#<?= h($row['reading_id']) ?></td>
                <td class="mono">#<?= h($row['event_id']) ?></td>
                <td><?= h($row['device_name']) ?></td>
                <td><?= h($row['sensor_name']) ?></td>
                <td class="mono"><?= h(number_format($row['reading_value'], 2)) ?> <?= h($row['unit']) ?></td>
                <td class="mono"><?= h($row['event_time']) ?></td>
              </tr>
              <?php endwhile; ?>

            </tbody>
          </table>
          <?php render_pagination($current_page_num, $per_page, $total_rows, $filter_qs); ?>
          <?php endif; ?>
        </div>
        <?php

    } elseif ($page === 'statistics') {

        $per_minute = $conn->query("
            SELECT DATE_FORMAT(event_time, '%H:%i') AS bucket, COUNT(*) AS c
            FROM detection_events
            WHERE event_time >= NOW() - INTERVAL 15 MINUTE
            GROUP BY bucket ORDER BY bucket DESC
        ");
        $per_hour = $conn->query("
            SELECT DATE_FORMAT(event_time, '%d %b, %H:00') AS bucket, COUNT(*) AS c
            FROM detection_events
            WHERE event_time >= NOW() - INTERVAL 24 HOUR
            GROUP BY bucket ORDER BY bucket DESC
        ");

        $minute_rows = $per_minute->fetch_all(MYSQLI_ASSOC);
        $hour_rows = $per_hour->fetch_all(MYSQLI_ASSOC);
        $minute_max = max(array_merge([1], array_column($minute_rows, 'c')));
        $hour_max = max(array_merge([1], array_column($hour_rows, 'c')));
        ?>
        <div class="page-header">
          <div><div class="page-eyebrow">Derived From Table 03</div><div class="page-title">Statistics</div></div>
        </div>

        <div class="panel">
          <div class="panel-head"><div><h3>Entries Per Minute</h3><p>Last 15 minutes, grouped by minute</p></div></div>
          <?php if (empty($minute_rows)): ?>
            <div class="empty-state">No entries in the last 15 minutes.</div>
          <?php else: foreach ($minute_rows as $row): ?>
            <div class="bar-row">
              <div class="bar-label"><?= h($row['bucket']) ?></div>
              <div class="bar-track"><div class="bar-fill" style="width: <?= round(($row['c'] / $minute_max) * 100) ?>%"></div></div>
              <div class="bar-count"><?= h($row['c']) ?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <div class="panel">
          <div class="panel-head"><div><h3>Entries Per Hour</h3><p>Last 24 hours, grouped by hour</p></div></div>
          <?php if (empty($hour_rows)): ?>
            <div class="empty-state">No entries in the last 24 hours.</div>
          <?php else: foreach ($hour_rows as $row): ?>
            <div class="bar-row">
              <div class="bar-label"><?= h($row['bucket']) ?></div>
              <div class="bar-track"><div class="bar-fill" style="width: <?= round(($row['c'] / $hour_max) * 100) ?>%"></div></div>
              <div class="bar-count"><?= h($row['c']) ?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
        <?php

    } elseif ($page === 'diagram') {
        ?>
        <div class="page-header">
          <div><div class="page-eyebrow">Schema</div><div class="page-title">Database Diagram</div></div>
        </div>
        <div class="panel">
          <div class="panel-head"><div><h3>Entity Relationships</h3><p>How the four normalized tables connect to each other</p></div></div>
          <div class="erd-wrap">
            <div class="erd-box">
              <div class="erd-title">devices</div>
              <div class="erd-field pk">device_id (PK)</div>
              <div class="erd-field">device_name</div>
              <div class="erd-field">mac_address</div>
              <div class="erd-field">device_role</div>
            </div>
            <div class="erd-arrow">&#8594;</div>
            <div class="erd-box">
              <div class="erd-title">detection_events</div>
              <div class="erd-field pk">event_id (PK)</div>
              <div class="erd-field fk">device_id (FK)</div>
              <div class="erd-field">event_time</div>
              <div class="erd-field">is_confirmed</div>
            </div>
            <div class="erd-arrow">&#8594;</div>
            <div class="erd-box">
              <div class="erd-title">sensor_readings</div>
              <div class="erd-field pk">reading_id (PK)</div>
              <div class="erd-field fk">event_id (FK)</div>
              <div class="erd-field fk">sensor_type_id (FK)</div>
              <div class="erd-field">reading_value</div>
            </div>
            <div class="erd-arrow">&#8592;</div>
            <div class="erd-box">
              <div class="erd-title">sensor_types</div>
              <div class="erd-field pk">sensor_type_id (PK)</div>
              <div class="erd-field">sensor_name</div>
              <div class="erd-field">unit</div>
            </div>
          </div>
        </div>
        <?php

    } elseif ($page === 'graphical_abstract') {

        $by_sensor = $conn->query("
            SELECT st.sensor_name, COUNT(*) AS c
            FROM sensor_readings sr JOIN sensor_types st ON st.sensor_type_id = sr.sensor_type_id
            GROUP BY st.sensor_name
        ")->fetch_all(MYSQLI_ASSOC);

        $by_hour = $conn->query("
            SELECT DATE_FORMAT(event_time, '%d %b, %H:00') AS bucket, COUNT(*) AS c
            FROM detection_events
            WHERE event_time >= NOW() - INTERVAL 24 HOUR
            GROUP BY bucket ORDER BY bucket ASC
        ")->fetch_all(MYSQLI_ASSOC);

        $by_device = $conn->query("
            SELECT d.device_name, COUNT(*) AS c
            FROM detection_events de JOIN devices d ON d.device_id = de.device_id
            GROUP BY d.device_name
        ")->fetch_all(MYSQLI_ASSOC);
        ?>
        <div class="page-header">
          <div><div class="page-eyebrow">Visual Summary</div><div class="page-title">Graphical Abstract</div></div>
        </div>
        <div class="card-grid">
          <div class="panel" style="padding:20px;">
            <h3 style="margin:0 0 12px 0;font-family:'Space Grotesk',sans-serif;font-size:20px;">Readings by Sensor Type</h3>
            <canvas id="sensorPie" height="220"></canvas>
          </div>
          <div class="panel" style="padding:20px;">
            <h3 style="margin:0 0 12px 0;font-family:'Space Grotesk',sans-serif;font-size:20px;">Events by Device</h3>
            <canvas id="devicePie" height="220"></canvas>
          </div>
        </div>
        <div class="panel" style="padding:20px;">
          <h3 style="margin:0 0 12px 0;font-family:'Space Grotesk',sans-serif;font-size:20px;">Entries per Hour (last 24h)</h3>
          <canvas id="hourBar" height="90"></canvas>
        </div>
        <script>
        const sensorLabels = <?= json_encode(array_column($by_sensor, 'sensor_name')) ?>;
        const sensorCounts = <?= json_encode(array_map('intval', array_column($by_sensor, 'c'))) ?>;
        const deviceLabels = <?= json_encode(array_column($by_device, 'device_name')) ?>;
        const deviceCounts = <?= json_encode(array_map('intval', array_column($by_device, 'c'))) ?>;
        const hourLabels = <?= json_encode(array_column($by_hour, 'bucket')) ?>;
        const hourCounts = <?= json_encode(array_map('intval', array_column($by_hour, 'c'))) ?>;

        const palette = ['#E8A33D', '#3FA7A0', '#D9614F', '#8B9AA1'];

        new Chart(document.getElementById('sensorPie'), {
          type: 'pie',
          data: { labels: sensorLabels, datasets: [{ data: sensorCounts, backgroundColor: palette }] },
          options: { plugins: { legend: { labels: { color: '#C7CFD3' } } } }
        });
        new Chart(document.getElementById('devicePie'), {
          type: 'pie',
          data: { labels: deviceLabels, datasets: [{ data: deviceCounts, backgroundColor: palette }] },
          options: { plugins: { legend: { labels: { color: '#C7CFD3' } } } }
        });
        new Chart(document.getElementById('hourBar'), {
          type: 'bar',
          data: { labels: hourLabels, datasets: [{ label: 'Confirmed Events', data: hourCounts, backgroundColor: '#E8A33D' }] },
          options: { plugins: { legend: { display: false } }, scales: {
            x: { ticks: { color: '#8B9AA1' }, grid: { color: '#2A343A' } },
            y: { ticks: { color: '#8B9AA1' }, grid: { color: '#2A343A' }, beginAtZero: true }
          } }
        });
        </script>
        <?php

    } elseif ($page === 'graphical_format') {

        // Same filter set as the Sensor Readings page, reused here
        $device_filter = isset($_GET['device']) ? intval($_GET['device']) : 0;
        $sensor_filter = isset($_GET['sensor']) ? intval($_GET['sensor']) : 0;
        $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
        $time_from = isset($_GET['time_from']) ? trim($_GET['time_from']) : '';
        $time_to = isset($_GET['time_to']) ? trim($_GET['time_to']) : '';
        $distance_bucket = isset($_GET['distance_bucket']) ? trim($_GET['distance_bucket']) : '';
        $distance_from = isset($_GET['distance_from']) ? trim($_GET['distance_from']) : '';
        $distance_to = isset($_GET['distance_to']) ? trim($_GET['distance_to']) : '';
        $compare_a = isset($_GET['compare_a']) ? $_GET['compare_a'] : 'ultrasonic';
        $compare_b = isset($_GET['compare_b']) ? $_GET['compare_b'] : 'pir_motion';

        $devices_list = $conn->query("SELECT device_id, device_name FROM devices ORDER BY device_id");
        $sensors_list = $conn->query("SELECT sensor_type_id, sensor_name FROM sensor_types ORDER BY sensor_type_id");

        // Builds the WHERE clause fragment shared by every count query on this page
        function graphical_format_where($conn, $device_filter, $sensor_filter, $date_from, $date_to, $time_from, $time_to, $distance_bucket, $distance_from, $distance_to) {
            $w = " WHERE 1=1 ";
            if ($device_filter > 0) $w .= " AND d.device_id = " . intval($device_filter);
            if ($sensor_filter > 0) $w .= " AND st.sensor_type_id = " . intval($sensor_filter);
            if ($date_from !== '') $w .= " AND DATE(de.event_time) >= '" . $conn->real_escape_string($date_from) . "'";
            if ($date_to !== '') $w .= " AND DATE(de.event_time) <= '" . $conn->real_escape_string($date_to) . "'";
            if ($time_from !== '') $w .= " AND TIME(de.event_time) >= '" . $conn->real_escape_string($time_from) . "'";
            if ($time_to !== '') $w .= " AND TIME(de.event_time) <= '" . $conn->real_escape_string($time_to) . "'";
            if ($distance_from !== '' || $distance_to !== '') {
                if ($distance_from !== '') $w .= " AND sr.reading_value >= " . floatval($distance_from);
                if ($distance_to !== '') $w .= " AND sr.reading_value <= " . floatval($distance_to);
            } elseif ($distance_bucket !== '' && is_numeric($distance_bucket)) {
                $bn = floatval($distance_bucket);
                $w .= " AND sr.reading_value > " . ($bn - 1) . " AND sr.reading_value <= " . $bn;
            }
            return $w;
        }
        $where = graphical_format_where($conn, $device_filter, $sensor_filter, $date_from, $date_to, $time_from, $time_to, $distance_bucket, $distance_from, $distance_to);

        $base_from = "
            FROM sensor_readings sr
            JOIN detection_events de ON de.event_id = sr.event_id
            JOIN devices d ON d.device_id = de.device_id
            JOIN sensor_types st ON st.sensor_type_id = sr.sensor_type_id
        ";

        // Live counts for the ER-diagram boxes, respecting the filter above
        $count_readings = $conn->query("SELECT COUNT(*) AS c $base_from $where")->fetch_assoc()['c'];
        $count_events = $conn->query("SELECT COUNT(DISTINCT de.event_id) AS c $base_from $where")->fetch_assoc()['c'];
        $count_devices = $conn->query("SELECT COUNT(DISTINCT d.device_id) AS c $base_from $where")->fetch_assoc()['c'];
        $count_sensor_types = $conn->query("SELECT COUNT(DISTINCT st.sensor_type_id) AS c $base_from $where")->fetch_assoc()['c'];

        // Metrics available for the two-way comparison dropdowns
        function metric_count($conn, $base_from, $where, $extra) {
            $r = $conn->query("SELECT COUNT(*) AS c $base_from $where $extra")->fetch_assoc();
            return (int)$r['c'];
        }
        $metrics = [
            'ultrasonic'  => ['label' => 'Ultrasonic Readings',   'extra' => " AND st.sensor_name = 'Ultrasonic'"],
            'pir_motion'  => ['label' => 'PIR Motion Readings',   'extra' => " AND st.sensor_name = 'PIR Motion'"],
            'sender'      => ['label' => 'Sender Car Readings',   'extra' => " AND d.device_name = 'Sender Car'"],
            'receiver'    => ['label' => 'Receiver Car Readings', 'extra' => " AND d.device_name = 'Receiver Car'"],
        ];
        $compare_a_value = metric_count($conn, $base_from, $where, $metrics[$compare_a]['extra']);
        $compare_b_value = metric_count($conn, $base_from, $where, $metrics[$compare_b]['extra']);

        $filter_qs = http_build_query([
            'page' => 'graphical_format',
            'device' => $device_filter, 'sensor' => $sensor_filter,
            'date_from' => $date_from, 'date_to' => $date_to,
            'time_from' => $time_from, 'time_to' => $time_to,
            'distance_bucket' => $distance_bucket,
            'distance_from' => $distance_from, 'distance_to' => $distance_to,
            'compare_a' => $compare_a, 'compare_b' => $compare_b,
        ]);
        ?>
        <div class="page-header">
          <div><div class="page-eyebrow">Live Schema View</div><div class="page-title">Graphical Format</div></div>
        </div>

        <div class="panel">
          <div class="panel-head"><div><h3>Filter The Whole View</h3><p>Same filters as Sensor Readings \u2014 everything below updates together</p></div></div>
          <form class="filter-bar" method="get" style="padding: 0 20px 20px 20px; flex-wrap: wrap;">
            <input type="hidden" name="page" value="graphical_format">
            <select name="device">
              <option value="0">All devices</option>
              <?php $devices_list->data_seek(0); while ($d = $devices_list->fetch_assoc()): ?>
                <option value="<?= $d['device_id'] ?>" <?= $device_filter === (int)$d['device_id'] ? 'selected' : '' ?>><?= h($d['device_name']) ?></option>
              <?php endwhile; ?>
            </select>
            <select name="sensor">
              <option value="0">All sensors</option>
              <?php $sensors_list->data_seek(0); while ($s = $sensors_list->fetch_assoc()): ?>
                <option value="<?= $s['sensor_type_id'] ?>" <?= $sensor_filter === (int)$s['sensor_type_id'] ? 'selected' : '' ?>><?= h($s['sensor_name']) ?></option>
              <?php endwhile; ?>
            </select>
            <span class="mono" style="color:#8B9AA1;font-size:16px;">Date</span>
            <input type="date" name="date_from" value="<?= h($date_from) ?>">
            <input type="date" name="date_to" value="<?= h($date_to) ?>">
            <span class="mono" style="color:#8B9AA1;font-size:16px;">Time</span>
            <input type="time" name="time_from" value="<?= h($time_from) ?>">
            <input type="time" name="time_to" value="<?= h($time_to) ?>">
            <span class="mono" style="color:#8B9AA1;font-size:16px;">Distance bucket (cm)</span>
            <select name="distance_bucket">
              <option value="">None</option>
              <?php for ($b = 1; $b <= 20; $b++): ?>
                <option value="<?= $b ?>" <?= $distance_bucket === (string)$b ? 'selected' : '' ?>><?= $b - 1 ?> to <?= $b ?> cm</option>
              <?php endfor; ?>
            </select>
            <span class="mono" style="color:#8B9AA1;font-size:16px;">or custom range</span>
            <input type="number" step="0.1" name="distance_from" value="<?= h($distance_from) ?>" placeholder="min" style="width:70px;">
            <input type="number" step="0.1" name="distance_to" value="<?= h($distance_to) ?>" placeholder="max" style="width:70px;">
            <button type="submit">Apply Filter</button>
          </form>
        </div>

        <div class="panel">
          <div class="panel-head"><div><h3>Database, Live</h3><p>The same four tables as the schema diagram, each box showing how many matching rows exist right now</p></div></div>
          <div class="erd-wrap">
            <div class="erd-box">
              <div class="erd-title">devices</div>
              <div class="erd-field pk">device_id (PK)</div>
              <div class="erd-field">device_name</div>
              <div class="erd-field">mac_address</div>
              <div class="erd-field" style="color:#E8A33D;font-weight:600;">matching: <?= h($count_devices) ?></div>
            </div>
            <div class="erd-arrow">&#8594;</div>
            <div class="erd-box">
              <div class="erd-title">detection_events</div>
              <div class="erd-field pk">event_id (PK)</div>
              <div class="erd-field fk">device_id (FK)</div>
              <div class="erd-field">event_time</div>
              <div class="erd-field" style="color:#E8A33D;font-weight:600;">matching: <?= h($count_events) ?></div>
            </div>
            <div class="erd-arrow">&#8594;</div>
            <div class="erd-box">
              <div class="erd-title">sensor_readings</div>
              <div class="erd-field pk">reading_id (PK)</div>
              <div class="erd-field fk">event_id (FK)</div>
              <div class="erd-field fk">sensor_type_id (FK)</div>
              <div class="erd-field" style="color:#E8A33D;font-weight:600;">matching: <?= h($count_readings) ?></div>
            </div>
            <div class="erd-arrow">&#8592;</div>
            <div class="erd-box">
              <div class="erd-title">sensor_types</div>
              <div class="erd-field pk">sensor_type_id (PK)</div>
              <div class="erd-field">sensor_name</div>
              <div class="erd-field">unit</div>
              <div class="erd-field" style="color:#E8A33D;font-weight:600;">matching: <?= h($count_sensor_types) ?></div>
            </div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div><h3>Compare Two Metrics</h3><p>Pick any two, both respect the filters above</p></div>
            <form class="filter-bar" method="get">
              <?php foreach (['device','sensor','date_from','date_to','time_from','time_to','distance_bucket','distance_from','distance_to'] as $k): ?>
                <input type="hidden" name="<?= $k ?>" value="<?= h($_GET[$k] ?? '') ?>">
              <?php endforeach; ?>
              <input type="hidden" name="page" value="graphical_format">
              <select name="compare_a">
                <?php foreach ($metrics as $key => $m): ?>
                  <option value="<?= $key ?>" <?= $compare_a === $key ? 'selected' : '' ?>><?= h($m['label']) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="mono" style="color:#8B9AA1;">vs</span>
              <select name="compare_b">
                <?php foreach ($metrics as $key => $m): ?>
                  <option value="<?= $key ?>" <?= $compare_b === $key ? 'selected' : '' ?>><?= h($m['label']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit">Compare</button>
            </form>
          </div>
          <div class="card-grid" style="padding: 20px;">
            <div>
              <h3 style="margin:0 0 12px 0;font-family:'Space Grotesk',sans-serif;font-size:19px;color:#E7E9E4;">Bar Comparison</h3>
              <canvas id="compareBar" height="180"></canvas>
            </div>
            <div>
              <h3 style="margin:0 0 12px 0;font-family:'Space Grotesk',sans-serif;font-size:19px;color:#E7E9E4;">Share of Total</h3>
              <canvas id="comparePie" height="180"></canvas>
            </div>
          </div>
        </div>
        <script>
        new Chart(document.getElementById('compareBar'), {
          type: 'bar',
          data: {
            labels: [<?= json_encode($metrics[$compare_a]['label']) ?>, <?= json_encode($metrics[$compare_b]['label']) ?>],
            datasets: [{ data: [<?= $compare_a_value ?>, <?= $compare_b_value ?>], backgroundColor: ['#E8A33D', '#3FA7A0'] }]
          },
          options: { plugins: { legend: { display: false } }, scales: {
            x: { ticks: { color: '#8B9AA1' }, grid: { display: false } },
            y: { ticks: { color: '#8B9AA1' }, grid: { color: '#2A343A' }, beginAtZero: true }
          } }
        });
        new Chart(document.getElementById('comparePie'), {
          type: 'pie',
          data: {
            labels: [<?= json_encode($metrics[$compare_a]['label']) ?>, <?= json_encode($metrics[$compare_b]['label']) ?>],
            datasets: [{ data: [<?= $compare_a_value ?>, <?= $compare_b_value ?>], backgroundColor: ['#E8A33D', '#3FA7A0'] }]
          },
          options: { plugins: { legend: { labels: { color: '#C7CFD3' } } } }
        });
        </script>
        <?php

    } elseif ($page === 'ai_anomalies') {

        $pattern_anomalies = $conn->query("
            SELECT de.event_id, de.event_time, d.device_name, de.severity_level,
                   de.measured_interval_sec, de.final_status, sr.reading_value
            FROM detection_events de
            JOIN devices d ON d.device_id = de.device_id
            JOIN sensor_readings sr ON sr.event_id = de.event_id
            JOIN sensor_types st ON st.sensor_type_id = sr.sensor_type_id
            WHERE st.sensor_name = 'Ultrasonic' AND de.final_status IN ('warning', 'critical')
            ORDER BY de.event_id DESC LIMIT 50
        ");

        $sensor_anomalies = $conn->query("
            SELECT sa.reading_id, sr.reading_value, st.sensor_name, sa.anomaly_score, sa.is_anomaly, sa.checked_at
            FROM sensor_anomalies sa
            JOIN sensor_readings sr ON sr.reading_id = sa.reading_id
            JOIN sensor_types st ON st.sensor_type_id = sr.sensor_type_id
            WHERE sa.is_anomaly = 1
            ORDER BY sa.anomaly_score ASC
        ");
        $ip_anomalies = $conn->query("
            SELECT ip_address, request_count, anomaly_score, checked_at
            FROM ip_anomalies WHERE is_anomaly = 1
            ORDER BY anomaly_score ASC
        ");
        ?>
        <div class="page-header">
          <div><div class="page-eyebrow">Machine Learning</div><div class="page-title">AI Anomaly Detection</div></div>
        </div>

        <div class="panel" style="padding:16px 20px;">
          <h3 style="font-family:var(--font-display);font-size:19px;margin:0 0 10px 0;">What These Labels Mean</h3>
          <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <span class="badge critical">critical</span><span class="mono" style="color:#8B9AA1;font-size:17px;">0 to 7 cm, object very close</span>
            <span class="badge warning">warning</span><span class="mono" style="color:#8B9AA1;font-size:17px;">8 to 14 cm, object approaching</span>
            <span class="badge normal">normal</span><span class="mono" style="color:#8B9AA1;font-size:17px;">15 to 20 cm, safe distance</span>
          </div>
          <p style="color:#8B9AA1;font-size:17px;margin:12px 0 0 0;">Pattern Anomaly is marked yes when 3 or more of the last 5 confirmed detections were spaced more than 25 percent away from the expected 1 second gap.</p>
        </div>

        <div class="panel">
          <div class="panel-head"><div><h3>Flagged Events (Rule Based Pattern Check)</h3><p>Events where distance zone or the last 5 confirmed intervals pushed final_status to warning or critical. This is deterministic logic on the Sender and in receive_data.php, not a trained model.</p></div></div>
          <div class="panel-head" style="border-top: 1px solid var(--border-hair);">
            <div><h3 style="font-size:18px;">Export This Result</h3></div>
            <div class="filter-bar">
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_events&format=csv">CSV</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_events&format=txt">Text</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_events&format=html">HTML</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_events&format=pdf">PDF</a>
            </div>
          </div>
          <?php if ($pattern_anomalies->num_rows === 0): ?>
            <div class="empty-state">No warning or critical events recorded yet.</div>
          <?php else: ?>
          <table>
            <thead><tr><th>Event ID</th><th>Device</th><th>Distance</th><th>Severity</th><th>Interval</th><th>Final Status</th><th>Time</th></tr></thead>
            <tbody>
              <?php while ($row = $pattern_anomalies->fetch_assoc()): ?>
              <tr>
                <td class="mono">#<?= h($row['event_id']) ?></td>
                <td><?= h($row['device_name']) ?></td>
                <td class="mono"><?= h(number_format($row['reading_value'], 2)) ?> cm</td>
                <td><span class="badge <?= h($row['severity_level']) ?>"><?= h($row['severity_level']) ?></span></td>
                <td class="mono"><?= $row['measured_interval_sec'] !== null ? h(number_format($row['measured_interval_sec'], 2)) . ' s' : '--' ?></td>
                <td><span class="badge <?= h($row['final_status']) ?>"><?= h($row['final_status']) ?></span></td>
                <td class="mono"><?= h($row['event_time']) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>

        <div class="panel" style="padding:16px 20px;">
          <p style="color:#8B9AA1;font-size:18px;margin:0;">The two tables below are produced by an Isolation Forest model (scikit-learn), trained separately by <span class="mono">train_sensor_anomaly.py</span> and <span class="mono">train_ip_anomaly.py</span>. This page only displays results already written to the database; it does not run the model itself. These are statistical outlier checks and are independent of the rule based pattern check above.</p>
        </div>

        <div class="panel">
          <div class="panel-head"><div><h3>Flagged Sensor Readings (Data Cleaning AI)</h3><p>Readings the model considers statistically unusual compared to the rest of the data</p></div></div>
          <div class="panel-head" style="border-top: 1px solid var(--border-hair);">
            <div><h3 style="font-size:18px;">Export This Result</h3></div>
            <div class="filter-bar">
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_readings&format=csv">CSV</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_readings&format=txt">Text</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_readings&format=html">HTML</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_readings&format=pdf">PDF</a>
            </div>
          </div>
          <?php if ($sensor_anomalies->num_rows === 0): ?>
            <div class="empty-state">No sensor anomalies flagged yet. Run train_sensor_anomaly.py after collecting more data.</div>
          <?php else: ?>
          <table>
            <thead><tr><th>Reading ID</th><th>Sensor</th><th>Value</th><th>Anomaly Score</th><th>Checked At</th></tr></thead>
            <tbody>
              <?php while ($row = $sensor_anomalies->fetch_assoc()): ?>
              <tr>
                <td class="mono">#<?= h($row['reading_id']) ?></td>
                <td><?= h($row['sensor_name']) ?></td>
                <td class="mono"><?= h(number_format($row['reading_value'], 2)) ?></td>
                <td class="mono"><span class="badge confirmed"><?= h(number_format($row['anomaly_score'], 4)) ?></span></td>
                <td class="mono"><?= h($row['checked_at']) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>

        <div class="panel">
          <div class="panel-head"><div><h3>Flagged IP Addresses (Security AI)</h3><p>IPs whose request pattern looks unusual compared to normal traffic</p></div></div>
          <div class="panel-head" style="border-top: 1px solid var(--border-hair);">
            <div><h3 style="font-size:18px;">Export This Result</h3></div>
            <div class="filter-bar">
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_ips&format=csv">CSV</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_ips&format=txt">Text</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_ips&format=html">HTML</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=ai_flagged_ips&format=pdf">PDF</a>
            </div>
          </div>
          <?php if ($ip_anomalies->num_rows === 0): ?>
            <div class="empty-state">No IP anomalies flagged yet. Run train_ip_anomaly.py after some traffic has been logged.</div>
          <?php else: ?>
          <table>
            <thead><tr><th>IP Address</th><th>Request Count</th><th>Anomaly Score</th><th>Checked At</th></tr></thead>
            <tbody>
              <?php while ($row = $ip_anomalies->fetch_assoc()): ?>
              <tr>
                <td class="mono"><?= h($row['ip_address']) ?></td>
                <td class="mono"><?= h($row['request_count']) ?></td>
                <td class="mono"><span class="badge confirmed"><?= h(number_format($row['anomaly_score'], 4)) ?></span></td>
                <td class="mono"><?= h($row['checked_at']) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
        <?php

    } elseif ($page === 'machine_learning') {

        $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
        $device_role_filter = isset($_GET['device_role']) ? trim($_GET['device_role']) : '';
        $pattern_filter = isset($_GET['pattern_anomaly']) ? trim($_GET['pattern_anomaly']) : '';
        $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
        $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
        $time_from = isset($_GET['time_from']) ? trim($_GET['time_from']) : '';
        $time_to = isset($_GET['time_to']) ? trim($_GET['time_to']) : '';

        $totals = $conn->query("
            SELECT
                COUNT(*) AS total,
                SUM(predicted_severity = 'normal') AS normal_c,
                SUM(predicted_severity = 'warning') AS warning_c,
                SUM(predicted_severity = 'critical') AS critical_c,
                SUM(device_role = 'sender') AS sender_c,
                SUM(device_role = 'receiver') AS receiver_c
            FROM tinyml_predictions
        ")->fetch_assoc();

        // Training performance, read from ml_metrics.json if train_tinyml_severity.py has
        // been run and the file was copied into this project folder. This page only
        // displays those numbers, it never trains anything itself.
        $metrics_path = __DIR__ . '/ml_metrics.json';
        $ml_metrics = null;
        if (file_exists($metrics_path)) {
            $ml_metrics = json_decode(file_get_contents($metrics_path), true);
        }

        $sql = "
            SELECT prediction_id, device_role, reading_value, measured_interval_sec,
                   is_pattern_anomaly, predicted_severity, logged_at
            FROM tinyml_predictions
            WHERE 1=1
        ";
        if ($device_role_filter > 0 || $device_role_filter !== '') {
            if (in_array($device_role_filter, ['sender', 'receiver'], true)) {
                $sql .= " AND device_role = '" . $conn->real_escape_string($device_role_filter) . "'";
            }
        }
        if (in_array($status_filter, ['normal', 'warning', 'critical'], true)) {
            $sql .= " AND predicted_severity = '" . $conn->real_escape_string($status_filter) . "'";
        }
        if (in_array($pattern_filter, ['yes', 'no'], true)) {
            $sql .= " AND is_pattern_anomaly = " . ($pattern_filter === 'yes' ? '1' : '0');
        }
        if ($date_from !== '') $sql .= " AND DATE(logged_at) >= '" . $conn->real_escape_string($date_from) . "'";
        if ($date_to !== '') $sql .= " AND DATE(logged_at) <= '" . $conn->real_escape_string($date_to) . "'";
        if ($time_from !== '') $sql .= " AND TIME(logged_at) >= '" . $conn->real_escape_string($time_from) . "'";
        if ($time_to !== '') $sql .= " AND TIME(logged_at) <= '" . $conn->real_escape_string($time_to) . "'";

        $count_sql = str_replace(
            "SELECT prediction_id, device_role, reading_value, measured_interval_sec,\n                   is_pattern_anomaly, predicted_severity, logged_at",
            "SELECT COUNT(*) AS total",
            $sql
        );
        $total_rows = (int)$conn->query($count_sql)->fetch_assoc()['total'];

        $per_page = 100;
        $current_page_num = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
        $offset = ($current_page_num - 1) * $per_page;

        $sql .= " ORDER BY prediction_id DESC LIMIT " . $per_page . " OFFSET " . $offset;
        $result = $conn->query($sql);

        $filter_qs = http_build_query([
            'page' => 'machine_learning',
            'status' => $status_filter, 'device_role' => $device_role_filter,
            'pattern_anomaly' => $pattern_filter,
            'date_from' => $date_from, 'date_to' => $date_to,
            'time_from' => $time_from, 'time_to' => $time_to,
        ]);
        ?>
        <div class="page-header">
          <div><div class="page-eyebrow">On-Device Machine Learning</div><div class="page-title">Machine Learning</div></div>
        </div>

        <div class="panel" style="padding:16px 20px;">
          <h3 style="font-size:20px;margin:0 0 10px 0;">What This Page Shows</h3>
          <p style="color:var(--text-muted);font-size:19px;margin:0 0 10px 0;">A Decision Tree Classifier, trained offline with scikit-learn on distance, measured interval, and the pattern anomaly flag, then converted to plain C++ with micromlgen and embedded directly on both NodeMCU boards. This is genuine machine learning running on the microcontroller itself, no server involved when it makes a prediction, clearly separate from both the rule based DBMS decision matrix and the Isolation Forest statistical checks shown on the AI Anomaly Detection page.</p>
          <p style="color:var(--text-muted);font-size:19px;margin:0;">The buzzer and Yellow LED on the Receiver only react when <strong>both</strong> the Sender's and the Receiver's mode switches are set to TinyML at the same time. Every prediction logged below is a real, independent decision the Receiver made on its own, using only what it received over ESP-NOW.</p>
        </div>

        <div class="panel">
          <div class="panel-head"><div><h3>Training Performance</h3><p>Read from ml_metrics.json, produced by train_tinyml_severity.py</p></div></div>
          <?php if ($ml_metrics === null): ?>
            <div class="empty-state">No training metrics found yet. Run train_tinyml_severity.py, then copy the ml_metrics.json it creates into this project folder.</div>
          <?php else: ?>
          <div style="padding:20px;">
            <div class="card-grid" style="margin-bottom:20px;">
              <div class="stat-card">
                <div class="stat-label">Accuracy (Test Set)</div>
                <div class="stat-value teal"><?= h(number_format($ml_metrics['accuracy'] * 100, 2)) ?><span class="stat-unit">%</span></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Trained On</div>
                <div class="stat-value" style="font-size:22px;"><?= h(number_format($ml_metrics['total_rows'])) ?> rows</div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Train / Test Split</div>
                <div class="stat-value" style="font-size:22px;"><?= h(number_format($ml_metrics['train_rows'])) ?> / <?= h(number_format($ml_metrics['test_rows'])) ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Max Tree Depth</div>
                <div class="stat-value"><?= h($ml_metrics['max_tree_depth']) ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Trained At</div>
                <div class="stat-value" style="font-size:20px;"><?= h(date('d M Y, H:i', strtotime($ml_metrics['trained_at']))) ?></div>
              </div>
            </div>

            <h3 style="font-size:19px;margin:0 0 10px 0;">Confusion Matrix</h3>
            <p style="color:var(--text-faint);font-size:17px;margin:0 0 10px 0;">Rows are the actual label, columns are what the model predicted.</p>
            <table style="margin-bottom:24px;">
              <thead><tr><th>Actual \ Predicted</th><?php foreach ($ml_metrics['classes'] as $c): ?><th><?= h($c) ?></th><?php endforeach; ?></tr></thead>
              <tbody>
                <?php foreach ($ml_metrics['classes'] as $i => $rowClass): ?>
                <tr>
                  <td><span class="badge <?= h($rowClass) ?>"><?= h($rowClass) ?></span></td>
                  <?php foreach ($ml_metrics['confusion_matrix'][$i] as $j => $val): ?>
                    <td class="mono" style="<?= $i === $j ? 'color:var(--teal);font-weight:600;' : '' ?>"><?= h($val) ?></td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <h3 style="font-size:19px;margin:0 0 10px 0;">Classification Report</h3>
            <table>
              <thead><tr><th>Class</th><th>Precision</th><th>Recall</th><th>F1-Score</th><th>Support</th></tr></thead>
              <tbody>
                <?php foreach ($ml_metrics['classes'] as $c): $cr = $ml_metrics['classification_report'][$c] ?? null; if (!$cr) continue; ?>
                <tr>
                  <td><span class="badge <?= h($c) ?>"><?= h($c) ?></span></td>
                  <td class="mono"><?= h(number_format($cr['precision'], 3)) ?></td>
                  <td class="mono"><?= h(number_format($cr['recall'], 3)) ?></td>
                  <td class="mono"><?= h(number_format($cr['f1-score'], 3)) ?></td>
                  <td class="mono"><?= h((int)$cr['support']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <div class="card-grid">
          <div class="stat-card">
            <div class="stat-label">Total Predictions Logged</div>
            <div class="stat-value"><?= h(number_format($totals['total'])) ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Normal</div>
            <div class="stat-value teal"><?= h(number_format($totals['normal_c'] ?? 0)) ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Warning</div>
            <div class="stat-value amber"><?= h(number_format($totals['warning_c'] ?? 0)) ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Critical</div>
            <div class="stat-value alert"><?= h(number_format($totals['critical_c'] ?? 0)) ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">From Sender</div>
            <div class="stat-value amber"><?= h(number_format($totals['sender_c'] ?? 0)) ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">From Receiver</div>
            <div class="stat-value teal"><?= h(number_format($totals['receiver_c'] ?? 0)) ?></div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div><h3>Live TinyML Predictions</h3><p>Every on-device decision logged by log_tinyml_result.php, independent of the DBMS pipeline</p></div>
          </div>
          <form class="filter-bar" method="get" style="padding: 0 20px 20px 20px; flex-wrap: wrap;">
            <input type="hidden" name="page" value="machine_learning">
            <select name="status">
              <option value="">All severities</option>
              <option value="normal" <?= $status_filter === 'normal' ? 'selected' : '' ?>>Normal</option>
              <option value="warning" <?= $status_filter === 'warning' ? 'selected' : '' ?>>Warning</option>
              <option value="critical" <?= $status_filter === 'critical' ? 'selected' : '' ?>>Critical</option>
            </select>
            <select name="device_role">
              <option value="">All devices</option>
              <option value="sender" <?= $device_role_filter === 'sender' ? 'selected' : '' ?>>Sender</option>
              <option value="receiver" <?= $device_role_filter === 'receiver' ? 'selected' : '' ?>>Receiver</option>
            </select>
            <select name="pattern_anomaly">
              <option value="">Pattern anomaly: any</option>
              <option value="yes" <?= $pattern_filter === 'yes' ? 'selected' : '' ?>>Pattern anomaly: yes</option>
              <option value="no" <?= $pattern_filter === 'no' ? 'selected' : '' ?>>Pattern anomaly: no</option>
            </select>
            <span class="mono" style="color:var(--text-faint);font-size:16px;">Date</span>
            <input type="date" name="date_from" value="<?= h($date_from) ?>" title="From date">
            <input type="date" name="date_to" value="<?= h($date_to) ?>" title="To date">
            <span class="mono" style="color:var(--text-faint);font-size:16px;">Time</span>
            <input type="time" name="time_from" value="<?= h($time_from) ?>" title="From time">
            <input type="time" name="time_to" value="<?= h($time_to) ?>" title="To time">
            <button type="submit">Apply Filter</button>
          </form>

          <div class="panel-head" style="border-top: 1px solid var(--border-hair);">
            <div><h3 style="font-size:18px;">Export This Result</h3></div>
            <div class="filter-bar">
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=tinyml_predictions&format=csv&<?= $filter_qs ?>">CSV</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=tinyml_predictions&format=txt&<?= $filter_qs ?>">Text</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=tinyml_predictions&format=html&<?= $filter_qs ?>">HTML</a>
              <a class="badge sender" style="text-decoration:none;" href="export.php?source=tinyml_predictions&format=pdf&<?= $filter_qs ?>">PDF</a>
            </div>
          </div>

          <div style="padding:0 20px 18px 20px;">
            <div class="stat-card" style="display:flex;align-items:center;gap:14px;">
              <div>
                <div class="stat-label">Matching Entries</div>
                <div class="stat-value"><?= h(number_format($total_rows)) ?></div>
              </div>
              <div class="mono" style="color:var(--text-muted);font-size:19px;">
                <?php
                  $desc_parts = [];
                  if ($status_filter !== '') $desc_parts[] = "severity = " . $status_filter;
                  if ($device_role_filter !== '') $desc_parts[] = "device = " . $device_role_filter;
                  if ($date_from !== '' || $date_to !== '') $desc_parts[] = "date range applied";
                  if ($time_from !== '' || $time_to !== '') $desc_parts[] = "time range applied";
                  echo $desc_parts ? h(implode(', ', $desc_parts)) : 'across all logged TinyML predictions (no filter applied)';
                ?>
              </div>
            </div>
          </div>

          <?php if ($result->num_rows === 0): ?>
            <div class="empty-state">No TinyML predictions logged yet. Turn both switches to TinyML mode and let a detection happen.</div>
          <?php else: ?>
          <table>
            <thead><tr><th>ID</th><th>Device</th><th>Distance</th><th>Interval</th><th>Pattern Anomaly</th><th>Predicted Severity</th><th>Logged At</th></tr></thead>
            <tbody>
              <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td class="mono">#<?= h($row['prediction_id']) ?></td>
                <td><span class="badge <?= $row['device_role'] === 'sender' ? 'sender' : 'receiver' ?>"><?= h($row['device_role']) ?></span></td>
                <td class="mono"><?= h(number_format($row['reading_value'], 2)) ?> cm</td>
                <td class="mono"><?= $row['measured_interval_sec'] !== null ? h(number_format($row['measured_interval_sec'], 2)) . ' s' : '--' ?></td>
                <td><?php if ($row['is_pattern_anomaly']): ?><span class="badge critical">yes</span><?php else: ?><span class="mono">no</span><?php endif; ?></td>
                <td><span class="badge <?= h($row['predicted_severity']) ?>"><?= h($row['predicted_severity']) ?></span></td>
                <td class="mono"><?= h($row['logged_at']) ?></td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php render_pagination($current_page_num, $per_page, $total_rows, $filter_qs); ?>
          <?php endif; ?>
        </div>
        <?php

    }
    ?>
  </main>
</div>
<script>
  function updateThemeLabel() {
    var theme = document.documentElement.getAttribute('data-theme') || 'dark';
    var label = document.getElementById('themeLabel');
    var icon = document.getElementById('themeIcon');
    var metaTag = document.getElementById('themeColorMeta');
    if (metaTag) metaTag.setAttribute('content', theme === 'light' ? '#F4F6F7' : '#0D1317');
    if (!label || !icon) return;
    if (theme === 'light') {
      icon.innerHTML = '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>';
      label.childNodes[label.childNodes.length - 1].textContent = ' Light Mode';
    } else {
      icon.innerHTML = '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>';
      label.childNodes[label.childNodes.length - 1].textContent = ' Dark Mode';
    }
  }

  function toggleDashboardTheme() {
    var current = document.documentElement.getAttribute('data-theme') || 'dark';
    var next = current === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('dashboardTheme', next);
    updateThemeLabel();
  }

  function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('open');
  }

  function closeMobileSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
  }

  function toggleDesktopSidebar() {
    var html = document.documentElement;
    var isCollapsed = html.getAttribute('data-sidebar') === 'collapsed';
    var next = isCollapsed ? 'expanded' : 'collapsed';
    html.setAttribute('data-sidebar', next);
    localStorage.setItem('dashboardSidebar', next);
  }

  updateThemeLabel();
</script>
</body>
</html>
