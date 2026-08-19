-- Run this in phpMyAdmin's SQL tab, on the obstacle_detection_system database.
-- Adds a dedicated table for logging what the on-device TinyML (Decision
-- Tree) classifier decided, kept completely separate from detection_events
-- and its DBMS-computed severity_level/final_status. This table exists
-- purely so the dashboard can show evidence that TinyML mode is actually
-- running, it plays no role in the buzzer/LED decision itself, which
-- reacts instantly on-device before this row is ever written.

USE obstacle_detection_system;

CREATE TABLE IF NOT EXISTS tinyml_predictions (
    prediction_id INT PRIMARY KEY AUTO_INCREMENT,
    device_role ENUM('sender', 'receiver') NOT NULL,
    reading_value FLOAT NOT NULL,
    measured_interval_sec FLOAT NULL,
    is_pattern_anomaly BOOLEAN NOT NULL,
    predicted_severity ENUM('normal', 'warning', 'critical') NOT NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logged_at (logged_at),
    INDEX idx_predicted_severity (predicted_severity)
);
