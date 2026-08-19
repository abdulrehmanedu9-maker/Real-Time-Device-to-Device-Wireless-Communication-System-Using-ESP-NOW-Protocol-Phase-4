"""
TinyML Model Trainer: Decision Tree for Severity Classification

What this script does, in order:
1. Connects to the MySQL database and pulls three features that already
   exist for every confirmed event: the ultrasonic distance, the measured
   interval since the previous event, and the pattern anomaly flag. The
   label it learns to predict is final_status (normal / warning / critical),
   the same DBMS decision matrix output from Phase 3.
2. Splits the data into a training set and a held-out test set, trains a
   Decision Tree Classifier on the training set, and evaluates it on data
   it has never seen, printing accuracy, a confusion matrix, and a full
   classification report (precision/recall/F1 per class).
3. Saves those evaluation numbers to ml_metrics.json, so the dashboard's
   Machine Learning page can display real training performance rather
   than made-up numbers.
4. Uses micromlgen to convert the trained tree into plain C++ code and
   saves it as model.h, ready to be included in both sender.ino and
   receiver.ino for on-device inference.

Honesty note: this script trains on final_status, which itself came from
the data generator's distance-zone classification (0-7cm critical, 8-14cm
warning, 15-20cm normal), not from a human-labeled ground truth. Pattern
anomaly is included as an input feature purely so the exported model.h
keeps the same 3-feature predict(float*) signature already wired into
sender.ino/receiver.ino, but it no longer has any influence over the
label itself, anomaly detection is a separate job handled independently
by the Isolation Forest AI Anomaly Detection page. This is worth stating
plainly in any report or paper.

How to run this:
    pip install scikit-learn mysql-connector-python micromlgen --break-system-packages
    python3 train_tinyml_severity.py
"""

import json
import mysql.connector
import numpy as np
from datetime import datetime
from sklearn.tree import DecisionTreeClassifier
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, confusion_matrix, classification_report

DB_CONFIG = {
    "host": "localhost",
    "user": "Abdul-Rehman",
    "password": "Abdul321",
    "database": "obstacle_detection_system",
}

CLASSES = ["normal", "warning", "critical"]
MAX_TREE_DEPTH = 5          # kept shallow on purpose, so the exported C++ stays tiny and fast
DEFAULT_INTERVAL_SEC = 1.0  # used to fill the very first events NULL interval, matches sender.ino


def load_dataset(conn):
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT sr.reading_value AS distance,
               de.measured_interval_sec,
               de.is_pattern_anomaly,
               de.final_status
        FROM detection_events de
        JOIN sensor_readings sr ON sr.event_id = de.event_id
        JOIN sensor_types st ON st.sensor_type_id = sr.sensor_type_id
        WHERE st.sensor_name = 'Ultrasonic'
    """)
    rows = cursor.fetchall()
    cursor.close()
    return rows


def main():
    conn = mysql.connector.connect(**DB_CONFIG)
    rows = load_dataset(conn)
    conn.close()

    if len(rows) < 50:
        print(f"Only {len(rows)} labeled rows found. That is too few to train a "
              f"meaningful model, generate more data first (see generate_test_data.py) "
              f"or collect more real events.")
        return

    X = []
    y = []
    for r in rows:
        interval = r["measured_interval_sec"] if r["measured_interval_sec"] is not None else DEFAULT_INTERVAL_SEC
        X.append([r["distance"], interval, 1 if r["is_pattern_anomaly"] else 0])
        y.append(r["final_status"])

    X = np.array(X)
    y = np.array(y)

    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )

    clf = DecisionTreeClassifier(max_depth=MAX_TREE_DEPTH, random_state=42)
    clf.fit(X_train, y_train)

    y_pred = clf.predict(X_test)
    accuracy = accuracy_score(y_test, y_pred)
    cm = confusion_matrix(y_test, y_pred, labels=CLASSES)
    report = classification_report(y_test, y_pred, labels=CLASSES, output_dict=True, zero_division=0)

    print(f"Trained on {len(X_train)} rows, tested on {len(X_test)} rows.")
    print(f"Accuracy: {accuracy:.4f}")
    print("Confusion matrix (rows = actual, columns = predicted), order:", CLASSES)
    print(cm)
    print(classification_report(y_test, y_pred, labels=CLASSES, zero_division=0))

    metrics = {
        "trained_at": datetime.now().isoformat(),
        "total_rows": len(rows),
        "train_rows": len(X_train),
        "test_rows": len(X_test),
        "max_tree_depth": MAX_TREE_DEPTH,
        "accuracy": round(float(accuracy), 4),
        "classes": CLASSES,
        "confusion_matrix": cm.tolist(),
        "classification_report": report,
    }
    with open("ml_metrics.json", "w") as f:
        json.dump(metrics, f, indent=2)
    print("Saved training metrics to ml_metrics.json")

    try:
        from micromlgen import port
    except ImportError:
        print("\nmicromlgen is not installed, skipping C++ export.")
        print("Install it with: pip install micromlgen --break-system-packages")
        print("Then re-run this script to generate model.h")
        return

    classmap = {i: name for i, name in enumerate(CLASSES)}
    # micromlgen needs numeric class labels internally; refit on encoded y for export clarity
    y_train_encoded = np.array([CLASSES.index(label) for label in y_train])
    clf_for_export = DecisionTreeClassifier(max_depth=MAX_TREE_DEPTH, random_state=42)
    clf_for_export.fit(X_train, y_train_encoded)

    cpp_code = port(clf_for_export, classmap=classmap)
    with open("model.h", "w") as f:
        f.write(cpp_code)
    print("Saved on-device model to model.h")
    print("Copy model.h into both your sender.ino and receiver.ino sketch folders.")


if __name__ == "__main__":
    main()
