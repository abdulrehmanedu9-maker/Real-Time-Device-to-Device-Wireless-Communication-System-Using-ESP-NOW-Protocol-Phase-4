"""
Test Data Generator v5: Session-Based Synthetic Detection Events

Important honesty note: everything this script inserts is SYNTHETIC data,
not real sensor readings. It exists purely so the dashboard, its filters,
its exports, and the AI/TinyML pages have enough volume to be tested and
demonstrated properly, and so the Decision Tree has enough BALANCED
labeled rows to train on. Nobody should present this data as if it came
from the physical Sender car.

WHAT CHANGED IN v5, AND WHY:
v4 generated severity directly from the same clean, non-overlapping
distance value that was also stored as the reading, so the model was
being trained and tested on data with zero measurement noise and zero
class overlap. A Decision Tree on data like that can and did reach
100.00% test accuracy, which is not a realistic number for any sensor
based classifier and would be a red flag in a research paper.

v5 separates the TRUE distance (which decides the real severity, ground
truth) from the MEASURED distance (the true distance plus simulated
ultrasonic sensor noise, see MEASUREMENT_NOISE_STD_CM). Only the noisy
measured distance is stored and is what the model actually trains and
is tested on; severity/final_status still comes from the true distance.
Near a zone boundary (7cm, 14cm) this occasionally makes the measured
reading land on the wrong side even though the true class did not
change, exactly what happens with a real HC-SR04. This is what pulls
accuracy down out of 100% into a defensible, realistic range (roughly
85-95%, tune MEASUREMENT_NOISE_STD_CM if you want it higher or lower).

WHAT CHANGED IN v4 (still true here):
Severity (final_status) is decided PURELY by the distance zone:
0-7cm critical, 8-14cm warning, 15-20cm normal, with no exceptions.
Earlier versions also let a timing pattern anomaly push a reading into
a more severe class (e.g. a normal-zone reading with an anomaly became
"warning"). Anomaly detection is a separate job, handled independently
by the Isolation Forest AI Anomaly Detection page; it is not the
TinyML Decision Tree's responsibility. is_pattern_anomaly is still
computed and stored on every row (that page still needs it), it just
has no effect on final_status.

WHAT CHANGED IN v3 (still true here):
The version before v3 picked TOTAL_EVENTS random timestamps anywhere
between April 1 and August 15, then sorted them. Across a 4.5 month span,
the real gap between two consecutive random timestamps averaged many
minutes, which is nowhere close to the expected ~2 second detection
interval. That made almost every event's measured_interval_sec look like
a pattern anomaly, which starved the "normal" class down to almost
nothing, exactly the imbalance that showed up in the trained model
(the tree never learned the plain "distance <= 7cm = critical" rule
because it barely ever saw a non-anomalous critical example).

v3, v4, and v5 all generate data as a series of separate "driving
sessions" instead. Each session:
  - starts at its own random moment somewhere between START_DATE and
    END_DATE, so the overall dataset is still spread realistically
    across the whole date range,
  - then produces a run of events spaced close to the real expected
    interval (EXPECTED_INTERVAL_SEC, with normal jitter and a small
    injected-anomaly rate), exactly like a real Sender would produce
    while it stays powered on and detecting objects continuously.
The first event of every session gets measured_interval_sec = NULL and
a reset pattern-anomaly history, because that is exactly what happens
on a real power-up, hasPreviousSend starts false in the firmware too.

How to run this:
    pip install mysql-connector-python --break-system-packages
    python3 generate_test_data.py
"""

import random
import mysql.connector
from datetime import datetime, timedelta

DB_CONFIG = {
    "host": "localhost",
    "user": "Abdul-Rehman",
    "password": "Abdul321",
    "database": "obstacle_detection_system",
}

TOTAL_EVENTS = 30000                    # change this to scale up, e.g. 100000 or 1000000
START_DATE = datetime(2026, 4, 1, 0, 0, 0)
END_DATE = datetime(2026, 8, 15, 23, 59, 59)

EXPECTED_INTERVAL_SEC = 1.0              # matches the sender's new 1-second sampling cadence
INTERVAL_TOLERANCE_PERCENT = 25
PATTERN_HISTORY_SIZE = 5
PATTERN_ANOMALY_THRESHOLD = 3
ANOMALY_INJECTION_RATE = 0.06            # 6% of gaps within a session are deliberately skewed
SENDER_MAC = "8C:AA:B5:52:BE:F4"
BATCH_SIZE = 2000                        # rows per executemany batch

SESSION_MIN_EVENTS = 30                  # how many confirmed detections a single "power-on" run has
SESSION_MAX_EVENTS = 400

# Simulated ultrasonic sensor measurement noise (standard deviation, in cm).
# The TRUE object distance is what actually determines severity (ground
# truth), but the value the sensor reports, and the only thing the model
# ever sees, has this much random error added to it, exactly like a real
# HC-SR04 does. This is what keeps the trained model's accuracy realistic
# instead of a suspicious 100 percent: near a zone boundary (7cm, 14cm),
# noise occasionally makes the reported distance land on the wrong side
# even though the true distance did not change class. Calibrated by
# simulation to land in roughly the 85-95 percent accuracy range; raise
# this value for a harder/noisier dataset (lower accuracy), lower it for
# an easier one (higher accuracy).
MEASUREMENT_NOISE_STD_CM = 2.0


def classify_severity(distance):
    if distance <= 7:
        return "critical"
    elif distance <= 14:
        return "warning"
    else:
        return "normal"


# NOTE: severity here is now decided PURELY by the distance zone above.
# Timing pattern anomalies are a separate job, handled independently by
# the Isolation Forest anomaly detection page, and are recorded in the
# is_pattern_anomaly column purely for that purpose. They no longer
# change final_status, so the TinyML Decision Tree learns a clean
# distance-only rule and a 19cm reading is always "normal", never
# bumped to "warning" just because a timing anomaly also happened.
def final_status_from_distance(severity):
    return severity


def random_true_distance():
    # Roughly: 70% normal, 22% warning, 8% critical. This is the object's
    # REAL position, ground truth, used to decide the true severity class.
    roll = random.random()
    if roll < 0.70:
        return round(random.uniform(15.0, 20.0), 2)
    elif roll < 0.92:
        return round(random.uniform(8.0, 14.0), 2)
    else:
        return round(random.uniform(0.5, 7.0), 2)


def noisy_measured_distance(true_distance):
    # What the ultrasonic sensor actually reports, and the only value the
    # model or the dashboard ever sees. A real HC-SR04 cannot report a
    # negative distance, so this is clipped at 0.
    measured = true_distance + random.gauss(0, MEASUREMENT_NOISE_STD_CM)
    return round(max(0.0, measured), 2)


def random_session_start():
    span_seconds = int((END_DATE - START_DATE).total_seconds())
    return START_DATE + timedelta(seconds=random.randint(0, span_seconds))


def build_sessions(total_events):
    """Splits total_events into a list of session lengths that sum to total_events."""
    sessions = []
    remaining = total_events
    while remaining > 0:
        length = min(remaining, random.randint(SESSION_MIN_EVENTS, SESSION_MAX_EVENTS))
        sessions.append(length)
        remaining -= length
    return sessions


def generate_session_rows(session_length, session_start):
    """Generates (event_time, severity, measured_interval, pattern_anomaly, final_status,
    measured_distance) rows for one session. severity/final_status come from the TRUE
    distance (ground truth); measured_distance is that same distance with simulated
    sensor noise added, and is the only distance value actually stored/trained on."""
    rows = []
    deviation_history = []
    current_time = session_start

    for i in range(session_length):
        true_distance = random_true_distance()
        severity = classify_severity(true_distance)       # ground truth, from the true position
        measured_distance = noisy_measured_distance(true_distance)  # what actually gets stored/trained on

        if i == 0:
            measured_interval = None
            this_deviates = False
        else:
            if random.random() < ANOMALY_INJECTION_RATE:
                measured_interval = round(EXPECTED_INTERVAL_SEC * random.uniform(1.6, 2.4), 2)
            else:
                measured_interval = round(EXPECTED_INTERVAL_SEC * random.uniform(0.9, 1.1), 2)

            low_bound = EXPECTED_INTERVAL_SEC * (1 - INTERVAL_TOLERANCE_PERCENT / 100)
            high_bound = EXPECTED_INTERVAL_SEC * (1 + INTERVAL_TOLERANCE_PERCENT / 100)
            this_deviates = measured_interval < low_bound or measured_interval > high_bound
            current_time += timedelta(seconds=measured_interval)

        deviation_history.append(this_deviates)
        if len(deviation_history) > PATTERN_HISTORY_SIZE:
            deviation_history.pop(0)

        pattern_anomaly = (
            len(deviation_history) >= PATTERN_HISTORY_SIZE
            and sum(deviation_history) >= PATTERN_ANOMALY_THRESHOLD
        )
        # pattern_anomaly is still recorded (for the separate Isolation
        # Forest anomaly detection page), but no longer changes severity.
        final_status = final_status_from_distance(severity)

        rows.append((current_time, severity, measured_interval, pattern_anomaly, final_status, measured_distance))

    return rows


def main():
    conn = mysql.connector.connect(**DB_CONFIG)
    conn.autocommit = False
    cursor = conn.cursor(dictionary=True)

    cursor.execute("SELECT device_id FROM devices WHERE mac_address = %s", (SENDER_MAC,))
    device_row = cursor.fetchone()
    if not device_row:
        print(f"Sender device with MAC {SENDER_MAC} not found in devices table. Add it first.")
        return
    device_id = device_row["device_id"]

    cursor.execute("SELECT sensor_type_id, sensor_name FROM sensor_types WHERE sensor_name IN ('Ultrasonic', 'PIR Motion')")
    sensor_ids = {row["sensor_name"]: row["sensor_type_id"] for row in cursor.fetchall()}
    if "Ultrasonic" not in sensor_ids or "PIR Motion" not in sensor_ids:
        print("Ultrasonic or PIR Motion sensor type missing from sensor_types table.")
        return
    ultrasonic_id = sensor_ids["Ultrasonic"]
    pir_id = sensor_ids["PIR Motion"]

    session_lengths = build_sessions(TOTAL_EVENTS)
    print(f"Generating {TOTAL_EVENTS} events across {len(session_lengths)} simulated driving sessions, "
          f"spread between {START_DATE.date()} and {END_DATE.date()}...")

    all_rows = []
    for length in session_lengths:
        session_start = random_session_start()
        all_rows.extend(generate_session_rows(length, session_start))

    # Insert in chronological order so event_id ordering matches event_time ordering,
    # which is what the dashboard and every export assume.
    all_rows.sort(key=lambda r: r[0])

    event_insert = """
        INSERT INTO detection_events
            (device_id, event_time, is_confirmed, severity_level, measured_interval_sec, is_pattern_anomaly, final_status)
        VALUES (%s, %s, TRUE, %s, %s, %s, %s)
    """
    reading_insert = """
        INSERT INTO sensor_readings (event_id, sensor_type_id, reading_value)
        VALUES (%s, %s, %s)
    """

    inserted = 0
    batch = []
    for row in all_rows:
        batch.append(row)
        if len(batch) >= BATCH_SIZE or row is all_rows[-1]:
            reading_rows = []
            for event_time, severity, interval, anomaly, status, dist in batch:
                cursor.execute(event_insert, (device_id, event_time, severity, interval, anomaly, status))
                event_id = cursor.lastrowid
                reading_rows.append((event_id, ultrasonic_id, dist))
                reading_rows.append((event_id, pir_id, 1.0))

            cursor.executemany(reading_insert, reading_rows)
            conn.commit()
            inserted += len(batch)
            print(f"Inserted {inserted} / {TOTAL_EVENTS} events...")
            batch = []

    # class balance summary, so you can see this version is no longer skewed
    counts = {"normal": 0, "warning": 0, "critical": 0}
    for _, _, _, _, status, _ in all_rows:
        counts[status] += 1
    print(f"Done. Class balance -> normal: {counts['normal']}, warning: {counts['warning']}, "
          f"critical: {counts['critical']}")

    cursor.close()
    conn.close()


if __name__ == "__main__":
    main()
