/*
  SENDER NODEMCU (ESP8266) - PHASE 4
  MAC Address of this device: 8C:AA:B5:52:BE:F4
  Sends detection status to Receiver MAC: A4:CF:12:FF:88:31

  Pin mapping used in this code:
  D1  Green LED   (through 220 ohm resistor)   [unchanged from Phase 3]
  D2  Red LED     (through 220 ohm resistor)   [unchanged from Phase 3]
  D3  Yellow LED  (through 220 ohm resistor)   [unchanged from Phase 3]
  D4  Mode switch (other leg to GND)           [NEW in Phase 4]
  D5  Ultrasonic HC-SR04 TRIG
  D6  Ultrasonic HC-SR04 ECHO
  D7  PIR Motion Sensor OUT
  G   Common ground for LED cathodes, ultrasonic GND, PIR GND, switch
  VU  Ultrasonic VCC (5V line)
  3V  PIR VCC

  Local LED logic (unchanged, for on-board indication only):
  Both ultrasonic and motion detect something  -> Red LED ON
  Only motion detects something                -> Yellow LED ON
  Only ultrasonic detects something             -> Green LED ON
  Nothing detected                              -> All LEDs OFF

  WIRELESS RULE - PART 1 (unchanged):
  A message is only ever considered when BOTH the ultrasonic sensor
  AND the PIR motion sensor detect something AT THE SAME TIME.

  WIRELESS RULE - PART 2 (changed in this update, per-second sampling):
  Instead of requiring the object to sit perfectly still for a full
  second before anything is sent, this Sender now samples once every
  SAMPLE_INTERVAL_MS (1 second). At each sample, it takes a quick
  double-reading 100ms apart to reject a single noisy glitch, then
  compares the resulting distance against the last distance it
  actually reported. If it is basically the same (within
  DISTANCE_TOLERANCE_CM), the sample is treated as a duplicate and
  ignored, nothing is sent. If it is different, that is treated as a
  new, real change and is sent immediately. This makes a moving
  object far more responsive than the old design, which could cancel
  and restart its confirmation indefinitely if the object never held
  still for a whole second.

  PATTERN ANOMALY CHECK (unchanged in concept from Phase 3, timing
  updated to match the new 1 second sampling cadence):
  Every reported detection's timing gap since the previous one is
  compared against the expected ~1 second interval, with a rolling
  history of the last 5 gaps. This is deterministic rule-based logic,
  not a trained model, and runs regardless of which mode below is
  active, because both modes need it.

  NEW IN PHASE 4 - MODE SWITCH (DBMS vs TinyML):
  D4 reads a physical switch, using the chip's internal pull-up, so no
  external resistor is needed on this pin (unlike D0/GPIO16, which was
  tried first and does not support a proper internal pull-up).

  Switch OFF (open, D4 reads HIGH) -> DBMS mode.
    Behaves exactly like Phase 3: distance, measuredIntervalSec, and
    patternAnomaly are sent to the Receiver, and severity is left for
    the server (receive_data.php) to compute using the distance-zone
    and pattern-anomaly decision matrix.

  Switch ON (closed to GND, D4 reads LOW) -> TinyML mode.
    In addition to the above, this Sender also runs its own on-device
    Decision Tree (model.h, trained offline with scikit-learn and
    converted to C++ with micromlgen) on the same three features
    (distance, measuredIntervalSec, patternAnomaly) and includes that
    prediction in the packet sent to the Receiver. This is genuine,
    if very small, machine learning running directly on the ESP8266,
    clearly separate from the DBMS's rule-based decision matrix.

  IMPORTANT: this Sender has no severity LED or buzzer itself, only
  the Receiver does. Sending its own TinyML prediction here is mainly
  for completeness and future use; what actually drives the
  Receiver's buzzer/LED is the Receiver's OWN switch and, in TinyML
  mode, the Receiver's OWN copy of this same model, evaluated on
  whatever it received, independent of what this Sender decided.

  Detection distance threshold for ultrasonic: 20 cm
  Data is sent to the receiver using ESP-NOW, no WiFi router or internet involved.
*/

#include <ESP8266WiFi.h>
#include <math.h>
extern "C" {
  #include <espnow.h>
  #include <user_interface.h>
}
#include "model.h"

/* IMPORTANT: this must match the WiFi channel your Receiver connects
   to at your home router. The Receiver's Serial Monitor prints the
   exact channel number right after it connects to WiFi, look for the
   line "WiFi channel in use: X" and put that same number here. */
#define WIFI_CHANNEL 6

#define GREEN_LED    D1
#define RED_LED      D2
#define YELLOW_LED   D3
#define MODE_SWITCH  D4
#define TRIG_PIN     D5
#define ECHO_PIN     D6
#define PIR_PIN      D7

#define DETECTION_DISTANCE_CM 20

/* Tolerance used to decide whether a new sample counts as the "same"
   distance as last time (duplicate, ignored) or a real change (sent
   immediately). Also used for the quick glitch-rejecting double-check. */
#define DISTANCE_TOLERANCE_CM 3.0

/* How often the Sender samples the sensors and decides whether to
   report, replaces the old CONFIRM_DURATION_MS + COOLDOWN_DURATION_MS
   pair with a single, simpler cadence. */
const unsigned long SAMPLE_INTERVAL_MS = 1000;

/* --- Pattern anomaly settings (thresholds unchanged, timing updated to match the new 1 second cadence) --- */
const unsigned long EXPECTED_INTERVAL_MS = 1000UL;   // object expected once every 1 second now
const int INTERVAL_TOLERANCE_PERCENT = 25;            // +/- 25% around the expected interval
const int PATTERN_HISTORY_SIZE = 5;                   // look at the last 5 confirmed intervals
const int PATTERN_ANOMALY_THRESHOLD = 3;              // 3 or more out of 5 deviating = anomaly

bool intervalDeviated[PATTERN_HISTORY_SIZE] = {false, false, false, false, false};
int intervalHistoryIndex = 0;
int intervalHistoryCount = 0;      // caps at PATTERN_HISTORY_SIZE
unsigned long previousSendTime = 0;
bool hasPreviousSend = false;

Eloquent::ML::Port::DecisionTree tinyMlModel;  // trained offline, see train_tinyml_severity.py

uint8_t receiverMac[] = {0xA4, 0xCF, 0x12, 0xFF, 0x88, 0x31};

typedef struct struct_message {
  bool anyDetected;
  bool motionDetected;
  bool ultrasonicDetected;
  float distance;
  bool patternAnomaly;
  float measuredIntervalSec;
  bool tinyMlMode;         // NEW Phase 4: true if this Sender's switch was ON when sent
  uint8_t tinyMlSeverity;  // NEW Phase 4: 0=normal, 1=warning, 2=critical; only meaningful if tinyMlMode is true
} struct_message;

struct_message outgoingData;

unsigned long lastSampleTime = 0;    /* drives the 1 second sampling cadence */
bool hasLastReported = false;        /* false until the first reading is ever reported */
float lastReportedDistance = 0;      /* the distance value we last actually sent */

void OnDataSent(uint8_t *mac_addr, uint8_t sendStatus) {
  Serial.print("Send status: ");
  Serial.println(sendStatus == 0 ? "Success" : "Failed");
}

float readDistanceCM() {
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);

  long duration = pulseIn(ECHO_PIN, HIGH, 30000);
  if (duration == 0) {
    return -1;
  }
  float distanceCM = duration * 0.0343 / 2.0;
  return distanceCM;
}

/* Measures the gap since the previous confirmed send, records whether
   that gap deviates from the expected interval beyond tolerance, and
   returns whether the last PATTERN_HISTORY_SIZE gaps together count as
   a pattern anomaly. measuredIntervalSecOut is filled with the actual
   gap in seconds (0 on the very first confirmed send, since there is
   no previous send yet to compare against). Unchanged from Phase 3. */
bool evaluateAndRecordInterval(unsigned long currentSendTime, float &measuredIntervalSecOut) {
  if (!hasPreviousSend) {
    hasPreviousSend = true;
    previousSendTime = currentSendTime;
    measuredIntervalSecOut = 0.0;
    return false;
  }

  unsigned long measuredIntervalMs = currentSendTime - previousSendTime;
  previousSendTime = currentSendTime;
  measuredIntervalSecOut = measuredIntervalMs / 1000.0;

  unsigned long lowBound  = EXPECTED_INTERVAL_MS - (EXPECTED_INTERVAL_MS * INTERVAL_TOLERANCE_PERCENT / 100);
  unsigned long highBound = EXPECTED_INTERVAL_MS + (EXPECTED_INTERVAL_MS * INTERVAL_TOLERANCE_PERCENT / 100);
  bool thisIntervalDeviates = (measuredIntervalMs < lowBound || measuredIntervalMs > highBound);

  intervalDeviated[intervalHistoryIndex] = thisIntervalDeviates;
  intervalHistoryIndex = (intervalHistoryIndex + 1) % PATTERN_HISTORY_SIZE;
  if (intervalHistoryCount < PATTERN_HISTORY_SIZE) intervalHistoryCount++;

  int deviationCount = 0;
  for (int i = 0; i < intervalHistoryCount; i++) {
    if (intervalDeviated[i]) deviationCount++;
  }

  bool historyIsFull = (intervalHistoryCount >= PATTERN_HISTORY_SIZE);
  return historyIsFull && (deviationCount >= PATTERN_ANOMALY_THRESHOLD);
}

void setup() {
  Serial.begin(115200);

  pinMode(GREEN_LED, OUTPUT);
  pinMode(RED_LED, OUTPUT);
  pinMode(YELLOW_LED, OUTPUT);
  pinMode(MODE_SWITCH, INPUT_PULLUP);
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  pinMode(PIR_PIN, INPUT);

  digitalWrite(GREEN_LED, LOW);
  digitalWrite(RED_LED, LOW);
  digitalWrite(YELLOW_LED, LOW);

  WiFi.mode(WIFI_STA);
  wifi_set_channel(WIFI_CHANNEL);
  Serial.print("Sender MAC Address: ");
  Serial.println(WiFi.macAddress());

  if (esp_now_init() != 0) {
    Serial.println("ESP-NOW init failed, restarting");
    ESP.restart();
  }

  esp_now_set_self_role(ESP_NOW_ROLE_CONTROLLER);
  esp_now_add_peer(receiverMac, ESP_NOW_ROLE_SLAVE, 1, NULL, 0);
  esp_now_register_send_cb(OnDataSent);

  Serial.println("Phase 4 ready. Switch OFF = DBMS mode, Switch ON = TinyML mode.");
}

void loop() {
  float distance = readDistanceCM();
  bool ultrasonicDetected = (distance > 0 && distance <= DETECTION_DISTANCE_CM);
  bool motionDetected = (digitalRead(PIR_PIN) == HIGH);
  bool bothDetected = ultrasonicDetected && motionDetected;

  /* Local Sender LEDs still react immediately, this part is unchanged */
  digitalWrite(GREEN_LED, LOW);
  digitalWrite(RED_LED, LOW);
  digitalWrite(YELLOW_LED, LOW);

  if (bothDetected) {
    digitalWrite(RED_LED, HIGH);
  } else if (motionDetected) {
    digitalWrite(YELLOW_LED, HIGH);
  } else if (ultrasonicDetected) {
    digitalWrite(GREEN_LED, HIGH);
  }

  /* Once every SAMPLE_INTERVAL_MS, decide whether to report anything */
  if (millis() - lastSampleTime >= SAMPLE_INTERVAL_MS) {
    lastSampleTime = millis();

    if (bothDetected) {
      /* Quick glitch check: take one more reading 100ms later and
         require it to roughly agree, instead of demanding a full
         second of stillness like before. */
      delay(100);
      float distanceRecheck = readDistanceCM();
      bool motionRecheck = (digitalRead(PIR_PIN) == HIGH);
      bool stillBothDetected = motionRecheck && (distanceRecheck > 0 && distanceRecheck <= DETECTION_DISTANCE_CM);
      bool readingIsStable = stillBothDetected && (fabs(distanceRecheck - distance) <= DISTANCE_TOLERANCE_CM);

      if (!readingIsStable) {
        Serial.println("Reading not stable across the quick recheck, skipped this second");
      } else {
        float confirmedDistance = distanceRecheck;
        bool isDuplicate = hasLastReported && (fabs(confirmedDistance - lastReportedDistance) <= DISTANCE_TOLERANCE_CM);

        if (isDuplicate) {
          Serial.print("Same distance as last reported (~");
          Serial.print(lastReportedDistance);
          Serial.println(" cm), ignored as duplicate");
        } else {
          /* Genuinely new distance, report it now */
          unsigned long sendTimeNow = millis();
          float measuredIntervalSec;
          bool patternAnomaly = evaluateAndRecordInterval(sendTimeNow, measuredIntervalSec);

          /* Phase 4: read the mode switch fresh for every reported event */
          bool tinyMlModeActive = (digitalRead(MODE_SWITCH) == LOW);  // LOW = closed = ON = TinyML

          outgoingData.anyDetected = true;
          outgoingData.motionDetected = true;
          outgoingData.ultrasonicDetected = true;
          outgoingData.distance = confirmedDistance;
          outgoingData.patternAnomaly = patternAnomaly;
          outgoingData.measuredIntervalSec = measuredIntervalSec;
          outgoingData.tinyMlMode = tinyMlModeActive;

          if (tinyMlModeActive) {
            float features[3] = { confirmedDistance, measuredIntervalSec, patternAnomaly ? 1.0f : 0.0f };
            int predictedClass = tinyMlModel.predict(features);
            outgoingData.tinyMlSeverity = (uint8_t) predictedClass;

            Serial.print("TinyML mode -> on-device prediction: ");
            Serial.println(tinyMlModel.idxToLabel(predictedClass));
          } else {
            outgoingData.tinyMlSeverity = 0;  // unused in DBMS mode
          }

          esp_now_send(receiverMac, (uint8_t *) &outgoingData, sizeof(outgoingData));

          Serial.print("REPORTED | Distance changed to: ");
          Serial.print(confirmedDistance);
          Serial.print(" cm | Interval since last report: ");
          Serial.print(measuredIntervalSec);
          Serial.print(" s | Pattern anomaly: ");
          Serial.print(patternAnomaly ? "YES" : "no");
          Serial.print(" | Mode: ");
          Serial.println(tinyMlModeActive ? "TinyML" : "DBMS");

          lastReportedDistance = confirmedDistance;
          hasLastReported = true;
        }
      }
    } else {
      Serial.print("Ultrasonic: ");
      Serial.print(ultrasonicDetected ? "DETECTED" : "clear");
      Serial.print(" | Distance: ");
      Serial.print(distance);
      Serial.print(" cm | Motion: ");
      Serial.print(motionDetected ? "DETECTED" : "clear");
      Serial.println(" | No detection this second");
    }
  }

  delay(100);
}
