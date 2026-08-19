<?php
/*
  Exports the currently filtered result set from whichever page it was
  linked from. Reuses the exact same filter logic as that page, so
  whatever the user is looking at on screen is exactly what gets
  exported.

  source=sensor_readings (default) or source=detection_events or
  source=tinyml_predictions or source=ai_flagged_events or
  source=ai_flagged_readings or source=ai_flagged_ips
  format=csv|txt|html|pdf
*/

require "config.php";

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$source = isset($_GET['source']) ? $_GET['source'] : 'sensor_readings';

// PDF building (TCPDF's writeHTML) holds the entire table in memory and
// walks it as one HTML document, so it needs a much lower row cap than
// CSV/Text/HTML, plus more memory and time headroom than PHP's default,
// or large exports fail silently as a blank page or a 500 error.
if ($format === 'pdf') {
    ini_set('memory_limit', '1024M');
    set_time_limit(300);
    $row_limit = 20000;
} else {
    $row_limit = 50000;
}

if ($source === 'detection_events') {

    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $device_filter = isset($_GET['device']) ? intval($_GET['device']) : 0;
    $pattern_filter = isset($_GET['pattern_anomaly']) ? trim($_GET['pattern_anomaly']) : '';
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    $time_from = isset($_GET['time_from']) ? trim($_GET['time_from']) : '';
    $time_to = isset($_GET['time_to']) ? trim($_GET['time_to']) : '';

    $sql = "
        SELECT de.event_id, d.device_name, de.is_confirmed, de.severity_level,
               de.measured_interval_sec, de.is_pattern_anomaly, de.final_status, de.event_time
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

    // 50,000 row safety cap: CSV/TXT can comfortably stream far more than
    // this, but HTML/PDF build the whole table in memory, so this keeps
    // every format safe even once the table holds millions of rows.
    // Row cap depends on export format, see $row_limit above: PDF gets a
    // much smaller, safer cap than CSV/Text/HTML.
    $sql .= " ORDER BY de.event_id DESC LIMIT " . $row_limit;
    $result = $conn->query($sql);
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $columns = ["Event ID", "Device", "Confirmed", "Severity", "Interval (s)", "Pattern Anomaly", "Final Status", "Time"];
    $file_base = "detection_events";

    $data_rows = [];
    foreach ($rows as $r) {
        $data_rows[] = [
            $r['event_id'],
            $r['device_name'],
            $r['is_confirmed'] ? 'confirmed' : 'pending',
            $r['severity_level'],
            $r['measured_interval_sec'] !== null ? $r['measured_interval_sec'] : '',
            $r['is_pattern_anomaly'] ? 'yes' : 'no',
            $r['final_status'],
            $r['event_time'],
        ];
    }

} elseif ($source === 'tinyml_predictions') {

    $status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $device_role_filter = isset($_GET['device_role']) ? trim($_GET['device_role']) : '';
    $pattern_filter = isset($_GET['pattern_anomaly']) ? trim($_GET['pattern_anomaly']) : '';
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    $time_from = isset($_GET['time_from']) ? trim($_GET['time_from']) : '';
    $time_to = isset($_GET['time_to']) ? trim($_GET['time_to']) : '';

    $sql = "
        SELECT prediction_id, device_role, reading_value, measured_interval_sec,
               is_pattern_anomaly, predicted_severity, logged_at
        FROM tinyml_predictions
        WHERE 1=1
    ";
    if (in_array($device_role_filter, ['sender', 'receiver'], true)) {
        $sql .= " AND device_role = '" . $conn->real_escape_string($device_role_filter) . "'";
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

    $sql .= " ORDER BY prediction_id DESC LIMIT " . $row_limit;
    $result = $conn->query($sql);
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $columns = ["ID", "Device", "Distance", "Interval (s)", "Pattern Anomaly", "Predicted Severity", "Logged At"];
    $file_base = "tinyml_predictions";

    $data_rows = [];
    foreach ($rows as $r) {
        $data_rows[] = [
            $r['prediction_id'],
            $r['device_role'],
            $r['reading_value'],
            $r['measured_interval_sec'] !== null ? $r['measured_interval_sec'] : '',
            $r['is_pattern_anomaly'] ? 'yes' : 'no',
            $r['predicted_severity'],
            $r['logged_at'],
        ];
    }

} elseif ($source === 'ai_flagged_events') {

    // Same query as the "Flagged Events (Rule Based Pattern Check)" block
    // on the AI Anomaly Detection page, without the on-screen LIMIT 50,
    // capped instead by the same row_limit as every other export.
    $sql = "
        SELECT de.event_id, d.device_name, sr.reading_value, de.severity_level,
               de.measured_interval_sec, de.final_status, de.event_time
        FROM detection_events de
        JOIN devices d ON d.device_id = de.device_id
        JOIN sensor_readings sr ON sr.event_id = de.event_id
        JOIN sensor_types st ON st.sensor_type_id = sr.sensor_type_id
        WHERE st.sensor_name = 'Ultrasonic' AND de.final_status IN ('warning', 'critical')
        ORDER BY de.event_id DESC LIMIT " . $row_limit;
    $result = $conn->query($sql);
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $columns = ["Event ID", "Device", "Distance", "Severity", "Interval (s)", "Final Status", "Time"];
    $file_base = "ai_flagged_events";

    $data_rows = [];
    foreach ($rows as $r) {
        $data_rows[] = [
            $r['event_id'],
            $r['device_name'],
            $r['reading_value'],
            $r['severity_level'],
            $r['measured_interval_sec'] !== null ? $r['measured_interval_sec'] : '',
            $r['final_status'],
            $r['event_time'],
        ];
    }

} elseif ($source === 'ai_flagged_readings') {

    // Same query as the "Flagged Sensor Readings (Data Cleaning AI)"
    // block on the AI Anomaly Detection page.
    $sql = "
        SELECT sa.reading_id, sr.reading_value, st.sensor_name, sa.anomaly_score, sa.checked_at
        FROM sensor_anomalies sa
        JOIN sensor_readings sr ON sr.reading_id = sa.reading_id
        JOIN sensor_types st ON st.sensor_type_id = sr.sensor_type_id
        WHERE sa.is_anomaly = 1
        ORDER BY sa.anomaly_score ASC LIMIT " . $row_limit;
    $result = $conn->query($sql);
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $columns = ["Reading ID", "Sensor", "Value", "Anomaly Score", "Checked At"];
    $file_base = "ai_flagged_sensor_readings";

    $data_rows = [];
    foreach ($rows as $r) {
        $data_rows[] = [
            $r['reading_id'],
            $r['sensor_name'],
            $r['reading_value'],
            $r['anomaly_score'],
            $r['checked_at'],
        ];
    }

} elseif ($source === 'ai_flagged_ips') {

    // Same query as the "Flagged IP Addresses (Security AI)" block on
    // the AI Anomaly Detection page.
    $sql = "
        SELECT ip_address, request_count, anomaly_score, checked_at
        FROM ip_anomalies WHERE is_anomaly = 1
        ORDER BY anomaly_score ASC LIMIT " . $row_limit;
    $result = $conn->query($sql);
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $columns = ["IP Address", "Request Count", "Anomaly Score", "Checked At"];
    $file_base = "ai_flagged_ip_addresses";

    $data_rows = [];
    foreach ($rows as $r) {
        $data_rows[] = [
            $r['ip_address'],
            $r['request_count'],
            $r['anomaly_score'],
            $r['checked_at'],
        ];
    }

} else {

    $device_filter = isset($_GET['device']) ? intval($_GET['device']) : 0;
    $sensor_filter = isset($_GET['sensor']) ? intval($_GET['sensor']) : 0;
    $date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
    $time_from = isset($_GET['time_from']) ? trim($_GET['time_from']) : '';
    $time_to = isset($_GET['time_to']) ? trim($_GET['time_to']) : '';
    $distance_bucket = isset($_GET['distance_bucket']) ? trim($_GET['distance_bucket']) : '';
    $distance_from = isset($_GET['distance_from']) ? trim($_GET['distance_from']) : '';
    $distance_to = isset($_GET['distance_to']) ? trim($_GET['distance_to']) : '';

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

    if ($distance_from !== '' || $distance_to !== '') {
        if ($distance_from !== '') $sql .= " AND sr.reading_value >= " . floatval($distance_from);
        if ($distance_to !== '') $sql .= " AND sr.reading_value <= " . floatval($distance_to);
    } elseif ($distance_bucket !== '' && is_numeric($distance_bucket)) {
        $bucket_n = floatval($distance_bucket);
        $sql .= " AND sr.reading_value > " . ($bucket_n - 1) . " AND sr.reading_value <= " . $bucket_n;
    }

    // Same 50,000 row safety cap as the detection_events export above.
    // Same dynamic row cap as the detection_events export above.
    $sql .= " ORDER BY sr.reading_id DESC LIMIT " . $row_limit;
    $result = $conn->query($sql);
    $rows = $result->fetch_all(MYSQLI_ASSOC);

    $columns = ["Reading ID", "Event ID", "Device", "Sensor", "Value", "Unit", "Time"];
    $file_base = "sensor_readings";

    $data_rows = [];
    foreach ($rows as $r) {
        $data_rows[] = [
            $r['reading_id'], $r['event_id'], $r['device_name'], $r['sensor_name'],
            $r['reading_value'], $r['unit'], $r['event_time'],
        ];
    }
}

if ($format === 'csv') {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=" . $file_base . ".csv");
    $out = fopen("php://output", "w");
    fputcsv($out, $columns);
    foreach ($data_rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

if ($format === 'txt') {
    header("Content-Type: text/plain");
    header("Content-Disposition: attachment; filename=" . $file_base . ".txt");
    echo implode("\t", $columns) . "\n";
    foreach ($data_rows as $r) {
        echo implode("\t", $r) . "\n";
    }
    exit;
}

if ($format === 'html') {
    header("Content-Type: text/html");
    header("Content-Disposition: attachment; filename=" . $file_base . ".html");
    echo "<html><head><meta charset='UTF-8'><title>" . h(ucwords(str_replace('_', ' ', $file_base))) . " Export</title></head><body>";
    echo "<h2>" . h(ucwords(str_replace('_', ' ', $file_base))) . " Export</h2><table border='1' cellpadding='6' cellspacing='0'>";
    echo "<tr>";
    foreach ($columns as $c) echo "<th>" . h($c) . "</th>";
    echo "</tr>";
    foreach ($data_rows as $r) {
        echo "<tr>";
        foreach ($r as $cell) echo "<td>" . h($cell) . "</td>";
        echo "</tr>";
    }
    echo "</table></body></html>";
    exit;
}

if ($format === 'pdf') {
    $tcpdf_path = __DIR__ . '/tcpdf/tcpdf.php';

    // Permanent safeguard: if TCPDF isn't installed, show a clear plain
    // text explanation instead of letting PHP crash with a raw 500 error.
    // This check runs every time, so this never breaks silently again,
    // no matter what else changes in the project later.
    if (!file_exists($tcpdf_path)) {
        http_response_code(500);
        header("Content-Type: text/plain");
        echo "PDF export is not available yet because the TCPDF library is missing.\n\n";
        echo "To fix this permanently, install it with Composer (recommended):\n";
        echo "    cd /var/www/html/obstacle_detection\n";
        echo "    composer require tecnickcom/tcpdf\n\n";
        echo "Or, without Composer, download TCPDF manually from:\n";
        echo "    https://github.com/tecnickcom/TCPDF\n";
        echo "and place the extracted folder here so this exact file exists:\n";
        echo "    " . $tcpdf_path . "\n\n";
        echo "CSV, plain text, and HTML export all work right now without TCPDF.\n";
        exit;
    }

    require_once $tcpdf_path;

    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Obstacle Detection Dashboard');
    $pdf->SetTitle(ucwords(str_replace('_', ' ', $file_base)) . ' Export');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    $html = "<h2>" . h(ucwords(str_replace('_', ' ', $file_base))) . " Export</h2>";
    if (count($data_rows) >= $row_limit) {
        $html .= "<p style='color:#B5423A;'>Showing the most recent " . number_format($row_limit) . " rows that match this filter. Narrow the filter (date range, status, device) or use the CSV/Text export for the complete result set.</p>";
    }
    $html .= "<table border='1' cellpadding='4'>
    <tr style='background-color:#eeeeee;'>";
    foreach ($columns as $c) $html .= "<th>" . h($c) . "</th>";
    $html .= "</tr>";
    foreach ($data_rows as $r) {
        $html .= "<tr>";
        foreach ($r as $cell) $html .= "<td>" . h($cell) . "</td>";
        $html .= "</tr>";
    }
    $html .= "</table>";

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($file_base . '.pdf', 'D');
    exit;
}

http_response_code(400);
echo "Unknown export format. Use format=csv, txt, html, or pdf.";
?>
