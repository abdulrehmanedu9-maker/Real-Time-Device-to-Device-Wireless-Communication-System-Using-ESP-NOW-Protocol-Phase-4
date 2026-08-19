# Real-Time Device-to-Device Wireless Communication System Using ESP-NOW Protocol

**Phase Four — Dual Mode Severity Classification: DBMS Decision Matrix and On-Device TinyML Inference**

An extension of Phase Three that gives the Sender and Receiver each an independent physical mode switch, letting them choose between the existing server-side decision matrix and a genuine on-device machine learning model, a Decision Tree Classifier trained offline with scikit-learn and exported to C++, running directly on the ESP8266 with no network round trip required.

## Overview

Phase Three decided severity in exactly one place, on the server, after the Receiver forwarded a confirmed detection over Wi-Fi and HTTP. Phase Four adds a second, independent way of deciding severity that runs entirely on the microcontrollers themselves. Each board now reads its own mode switch: in DBMS mode the system behaves exactly as it did in Phase Three, while in TinyML mode the Receiver runs a small embedded Decision Tree on the same distance, interval, and pattern anomaly values, and reacts instantly through a buzzer and an LED without waiting on any server call. This phase also reworks how the Sender decides when to report a detection, replacing a fixed confirmation window with a steady one-second sampling cadence, so the system is far more responsive to a moving object.

## Key Features

- Independent physical mode switch on both the Sender (pin D4) and the Receiver (pin D7), so each board can be tested in a different mode at the same time.
- DBMS mode, unchanged from Phase Three: the Receiver posts distance, interval, and pattern anomaly data to the server and reacts to the returned final status.
- TinyML mode: the Receiver runs a local copy of a trained Decision Tree Classifier and reacts immediately through a buzzer and a dedicated Yellow LED, with the Green and Red status LEDs forced off while this mode is active.
- Offline training pipeline using scikit-learn, exporting the trained model to plain C++ with micromlgen so it runs on the ESP8266 with no Python runtime, network connection, or scikit-learn dependency at inference time.
- Sender-side sampling redesign: a fixed one-second cadence, a glitch-rejecting recheck, and a distance-based duplicate suppression rule replace the earlier fixed confirmation window, making the system responsive to continuously moving objects.
- A dedicated tinyml_predictions table and logging endpoint, kept structurally separate from the DBMS pipeline's detection_events table, so rule-based and learned classification results are never mixed together.
- A synthetic, clearly labeled test data generator that builds realistic simulated driving sessions for training and demonstration, with simulated sensor noise so the trained model's accuracy stays in a defensible, realistic range.
- A Machine Learning dashboard page showing offline training performance, a confusion matrix, a per-class classification report, and a live, filterable, exportable log of on-device predictions.

## System Flow
```
Sender: 1s sampling cadence --> glitch recheck --> duplicate suppression
        --> (TinyML mode: local Decision Tree prediction included in packet)
        --> transmitted over ESP-NOW to Receiver

Receiver: reads its own mode switch on every loop pass
   DBMS mode   --> HTTP POST to receive_data.php --> server decision matrix --> Green/Red LEDs
   TinyML mode --> local Decision Tree prediction --> buzzer + Yellow LED (instant, no network)
                --> best-effort log to log_tinyml_result.php (after the reaction, never blocking it)
```
The two modes are mutually exclusive on the Receiver's output hardware, and the Sender and Receiver switches are independent of one another, so each board's behavior can be tested and demonstrated separately.

## Repository Structure

```text
Project
│
├── Documents/
│   ├── Real Time Device to Device Wireless Communication System Using ESP NOW Protocol (Phase 4).pdf
│   └── Real Time Device to Device Wireless Communication System Using ESP NOW Protocol (Phase 4).pptx
│
├── Live Demo/
│   └── Live Demo.mp4
│
├── Web Interface/
│   ├── export.php
│   ├── generate_test_data.py
│   ├── index.php
│   ├── log_tinyml_result.php
│   ├── ml_metrics.json
│   ├── schema_tinyml_predictions.sql
│   ├── style.css
│   └── train_tinyml_severity.py
│
├── receiver/
│   ├── model.h
│   └── receiver.ino
│
├── sender/
│   ├── model.h
│   └── sender.ino
│
├── Project Graphical Abstract(Phase 4).png
│
└── README.md
```

Note: config.php, receive_data.php, and the base database schema from Phase Two, along with schema_update_severity.sql from Phase Three, are still required and are documented in those phases' READMEs.

## Getting Started

1. Ensure the Phase Two and Phase Three backend, database, and dashboard are already set up and working.
2. Run schema_tinyml_predictions.sql against the existing obstacle_detection_system database to add the tinyml_predictions table.
3. Wire a mode switch to pin D4 on the Sender and pin D7 on the Receiver, using each board's internal pull-up resistor, so an open switch selects DBMS mode and a switch closed to ground selects TinyML mode.
4. Wire a buzzer and a Yellow LED to the Receiver for the TinyML mode reaction, alongside the existing Green and Red status LEDs.
5. Install the Python packages needed for training and synthetic data generation.

pip install scikit-learn mysql-connector-python micromlgen --break-system-packages

6. Run generate_test_data.py to populate the database with a large, clearly labeled synthetic dataset if there is not yet enough real detection history to train on.
7. Run train_tinyml_severity.py to train the Decision Tree, evaluate it on a held-out test set, and produce ml_metrics.json and model.h.
8. Copy the generated model.h into both the sender.ino and receiver.ino sketch folders, then upload each sketch to its board.
9. Place log_tinyml_result.php alongside the existing PHP backend files, and replace index.php, export.php, and style.css with the versions in this phase to see the new Machine Learning page and extended filtering.
10. Flip each board's switch to test DBMS mode and TinyML mode independently, and confirm the Receiver's buzzer and Yellow LED react immediately in TinyML mode with no dependency on network availability.

## On-Device Model

The Decision Tree is trained on three features already produced by the existing pipeline: ultrasonic distance, measured interval since the previous confirmed detection, and the pattern anomaly flag inherited from Phase Three. Its target label is the same three-class outcome, normal, warning, or critical, already used throughout the project. The tree depth is capped at five so the exported C++ stays small and fast enough for an ESP8266, and the resulting model reached a test set accuracy of ninety point eight five percent on a thirty-thousand-row synthetic dataset with simulated sensor noise.

The exported tree ends up deciding severity from distance alone, splitting at the same 7.5 and 14.5 centimeter boundaries used by the Phase Three severity zones, since the synthetic training labels are generated purely from distance with no exception for a pattern anomaly. The measured interval and pattern anomaly features remain part of the model's input signature so that a future retraining pass on differently labeled data, one where the anomaly flag is allowed to influence the label as it does in the live server-side decision matrix, could make use of them without any firmware change.

## Testing and Results

The on-device Decision Tree's live predictions were verified against the offline evaluation by filtering the Live TinyML Predictions dashboard table by each severity class in turn. Every filtered result was consistent with the thresholds confirmed in the offline confusion matrix, supporting the conclusion that the C++ export produced by micromlgen behaves identically to the scikit-learn model it was converted from, and that the Receiver's on-device inference path functions correctly end to end from ESP-NOW reception through to a logged, filterable dashboard row.

## Limitations

The Decision Tree's high test accuracy is a direct consequence of the synthetic training labels being a deterministic function of distance alone, and should not be read as a claim about performance on noisier, real-world sensor data with borderline or mislabeled cases. The tree currently uses only the distance feature, effectively rediscovering the same fixed thresholds as the Phase Three severity zones, since the measured interval and pattern anomaly features carry no influence over the training labels. The synthetic dataset's final status and the live server-side decision matrix's final status are computed differently, the former purely from distance, the latter from distance combined with the pattern anomaly flag, so the two classification paths are not directly comparable label for label without accounting for that difference. No formal row-by-row comparison has yet been made between the server-side decision matrix's classifications and the on-device Decision Tree's classifications for the same live events.

## Future Work

The clearest next step is retraining the Decision Tree on labels that match the live server-side decision matrix rather than a purely distance-based rule, which would let the pattern anomaly and interval features actually influence predictions and would make the on-device classifier a genuine embedded approximation of the DBMS logic. A direct comparison between detection_events.final_status and tinyml_predictions.predicted_severity for the same physical events would then give a meaningful measure of how well the embedded model approximates the server-side decision. A longer TinyML mode test session using a continuously moving object sampled at the full one-second cadence would give a more representative picture of the pattern anomaly check under realistic conditions. Future work already identified in earlier phases also remains relevant, including database transactions so a detection event and its sensor readings are always saved together, and formal precision and recall evaluation of the existing Isolation Forest models once enough labeled anomaly data has accumulated.

## Supervisor

**Israr Akhter**  
Slovak University of Technology in Bratislava

## Author

**Abdul Rehman**  
BS Computer Science  
Allama Iqbal Open University (AIOU)
