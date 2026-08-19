<?php
/*
  This endpoint does ONE thing only: it logs a prediction that a device
  already made on its own, using its on-device TinyML (Decision Tree)
  model. It contains no classification logic itself, unlike
  receive_data.php, which computes severity_level and final_status from
  scratch. That separation is intentional: this table is a record of
  what TinyML decided, kept independent from what the DBMS pipeline
  decides for the same kind of event, so the two are never mixed up.

  This call happens AFTER the buzzer/LED have already reacted on the
  device, so if this request fails or the device has no network at
  that moment, the TinyML mode's real-time behavior is completely
  unaffected, only the dashboard's visibility into it is.

  Expected incoming data (sent as JSON in an HTTP POST request):
  {
    "device_role": "receiver",
    "distance": 12.5,
    "measured_interval_sec": 2.1,
    "pattern_anomaly": false,
    "predicted_severity": "normal"
  }
*/

require "config.php";

header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);

$device_role = isset($input["device_role"]) ? trim($input["device_role"]) : '';
$distance = isset($input["distance"]) ? floatval($input["distance"]) : null;
$measured_interval_sec = isset($input["measured_interval_sec"]) && $input["measured_interval_sec"] !== null
    ? floatval($input["measured_interval_sec"])
    : null;
$pattern_anomaly = isset($input["pattern_anomaly"]) ? (bool)$input["pattern_anomaly"] : false;
$predicted_severity = isset($input["predicted_severity"]) ? trim($input["predicted_severity"]) : '';

if (!in_array($device_role, ['sender', 'receiver'], true)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "device_role must be 'sender' or 'receiver'"]);
    exit;
}
if ($distance === null) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing distance value"]);
    exit;
}
if (!in_array($predicted_severity, ['normal', 'warning', 'critical'], true)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "predicted_severity must be normal, warning, or critical"]);
    exit;
}

$pattern_anomaly_int = $pattern_anomaly ? 1 : 0;

$stmt = $conn->prepare("
    INSERT INTO tinyml_predictions
        (device_role, reading_value, measured_interval_sec, is_pattern_anomaly, predicted_severity)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("sddis", $device_role, $distance, $measured_interval_sec, $pattern_anomaly_int, $predicted_severity);
$stmt->execute();

echo json_encode(["status" => "success", "prediction_id" => $conn->insert_id]);

$conn->close();
?>
