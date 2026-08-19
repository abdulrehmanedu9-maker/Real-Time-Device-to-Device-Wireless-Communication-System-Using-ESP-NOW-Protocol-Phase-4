/*
  RECEIVER NODEMCU (ESP8266) - PHASE 4
  MAC Address of this device: A4:CF:12:FF:88:31
  Receives detection status from Sender MAC: 8C:AA:B5:52:BE:F4

  Pin mapping used in this code:
  D1  Green LED   (unchanged from Phase 3, connection/idle indicator)
  D2  Red LED     (unchanged from Phase 3, connection/idle indicator)
  D3  Yellow LED  (NEW Phase 4: on when the current severity is "normal")
  D6  Buzzer      (NEW Phase 4: active buzzer, warning = 1 beep, critical = 2 beeps)
  D7  Mode switch (NEW Phase 4: other leg to GND, internal pull-up used)
  G   Common ground for LED cathodes, buzzer, switch

  Green/Red LED LOGIC (DBMS mode only, see below):
  No data currently arriving from the Sender -> Green LED ON (idle/safe state)
  Data currently being received                -> Red LED ON
  This has nothing to do with severity, it just shows whether the
  Receiver is currently hearing from the Sender at all.

  NEW IN PHASE 4 - MODE SWITCH (DBMS vs TinyML), INDEPENDENT of the
  Sender's own switch. This Receiver decides for itself where its
  severity comes from, and the two modes are now completely
  mode-exclusive on the LEDs:

  Switch OFF (open, D7 reads HIGH) -> DBMS mode.
    Only Green and Red LEDs are used, Yellow LED and Buzzer are held
    off the entire time this mode is active. The received distance/
    interval/pattern data is POSTed to receive_data.php, which
    computes severity using the distance-zone + pattern-anomaly
    decision matrix and returns final_status in its JSON response.

  Switch ON (closed to GND, D7 reads LOW) -> TinyML mode.
    Only Yellow LED and Buzzer are used, Green and Red LEDs are held
    off the entire time this mode is active. The DBMS/HTTP call is
    skipped entirely (TinyML mode works without depending on the
    network or server); instead this Receiver runs its OWN copy of
    the same Decision Tree (model.h) on the distance/interval/
    pattern-anomaly it just received over ESP-NOW, independent of
    whatever severity the Sender itself predicted. The buzzer/LED
    react immediately to this local prediction, purely based on this
    Receiver's own switch, no longer dependent on the Sender's switch
    state. Only after reacting, a lightweight, best-effort log of that
    prediction is sent to log_tinyml_result.php purely so the
    dashboard can show that TinyML is actually running; this logging
    is not required for the buzzer/LED to work and fails silently if
    there is no WiFi.

  BUZZER / YELLOW LED REACTION (TinyML mode only):
  normal   -> Yellow LED blinks ON for 2 seconds, then OFF; buzzer silent
  warning  -> Yellow LED off, buzzer beeps ONCE
  critical -> Yellow LED off, buzzer beeps TWICE

  NOTEPAD LOGGING (unchanged from Phase 3):
  Every received detection line is also appended to /detection_log.txt
  on LittleFS.

  DATABASE CONNECTION (unchanged mechanism):
  Before uploading, three placeholder values below must be replaced
  with real ones: WIFI_SSID, WIFI_PASSWORD, and SERVER_IP.
*/

#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClient.h>
#include <LittleFS.h>
extern "C" {
  #include <espnow.h>
}
#include "model.h"

/* Replace these three placeholders with real values before uploading */
const char *WIFI_SSID     = "Abdul Rehman";
const char *WIFI_PASSWORD = "IshmalFatima";
const char *SERVER_IP     = "192.168.1.5";

const char *SERVER_PATH        = "/obstacle_detection/receive_data.php";
const char *TINYML_LOG_PATH    = "/obstacle_detection/log_tinyml_result.php";

#define GREEN_LED    D1
#define RED_LED      D2
#define YELLOW_LED   D3
#define BUZZER       D6
#define MODE_SWITCH  D7

const unsigned long noDataTimeout = 4000;
const char *LOG_FILE_PATH = "/detection_log.txt";

Eloquent::ML::Port::DecisionTree tinyMlModel;  // same trained model as the Sender's

typedef struct struct_message {
  bool anyDetected;
  bool motionDetected;
  bool ultrasonicDetected;
  float distance;
  bool patternAnomaly;
  float measuredIntervalSec;
  bool tinyMlMode;         // Sender's own switch state, logged but not used for our decision
  uint8_t tinyMlSeverity;  // Sender's own prediction, logged but not used for our decision
} struct_message;

struct_message incomingData;
unsigned long lastReceiveTime = 0;
bool hasReceivedOnce = false;

/* Used to hand off work from the ESP-NOW callback into the main loop,
   since callbacks should stay short and fast. */
volatile bool pendingUpload = false;
float pendingDistance = 0;
bool pendingPatternAnomaly = false;
float pendingMeasuredIntervalSec = 0;

void logToFile(const String &line) {
  File f = LittleFS.open(LOG_FILE_PATH, "a");
  if (!f) {
    Serial.println("Warning: could not open log file for writing");
    return;
  }
  f.println(line);
  f.close();
}

void OnDataRecv(uint8_t *mac, uint8_t *data, uint8_t len) {
  memcpy(&incomingData, data, sizeof(incomingData));
  lastReceiveTime = millis();
  hasReceivedOnce = true;

  String line = "[" + String(millis() / 1000) + "s] BOTH sensors detected on Sender | Combined Distance: " +
                String(incomingData.distance) + " cm | Interval: " +
                String(incomingData.measuredIntervalSec) + " s | Pattern anomaly: " +
                String(incomingData.patternAnomaly ? "YES" : "no") + " | Sender mode: " +
                String(incomingData.tinyMlMode ? "TinyML" : "DBMS") + " | Receiver LED: RED";

  Serial.println(line);
  logToFile(line);

  pendingDistance = incomingData.distance;
  pendingPatternAnomaly = incomingData.patternAnomaly;
  pendingMeasuredIntervalSec = incomingData.measuredIntervalSec;
  pendingUpload = true;
}

void beepBuzzerTimes(int times) {
  for (int i = 0; i < times; i++) {
    digitalWrite(BUZZER, HIGH);
    delay(200);
    digitalWrite(BUZZER, LOW);
    if (i < times - 1) delay(200);
  }
}

/* Turns the shared buzzer/Yellow LED reaction into one place. This now
   runs purely off the Receiver's own mode switch: TinyML mode on this
   Receiver always reacts to its own on-device prediction regardless
   of what mode the Sender happened to be in for that packet. */
void reactToSeverity(const String &severity) {
  if (severity == "critical") {
    digitalWrite(YELLOW_LED, LOW);
    beepBuzzerTimes(2);
  } else if (severity == "warning") {
    digitalWrite(YELLOW_LED, LOW);
    beepBuzzerTimes(1);
  } else if (severity == "normal") {
    digitalWrite(BUZZER, LOW);
    digitalWrite(YELLOW_LED, HIGH);
    delay(2000);              // blink for 2 seconds, then turn off
    digitalWrite(YELLOW_LED, LOW);
  } else {
    Serial.print("Unrecognized severity, ignoring: ");
    Serial.println(severity);
  }
}

/* Very small, purpose-built extractor for one specific flat JSON
   response shape (no nested objects/arrays), so this project does not
   need to pull in a full JSON library just to read one string field
   out of receive_data.php's response. Returns "" if the key is not
   found or the response is not in the expected shape. */
String extractJsonStringValue(const String &json, const String &key) {
  String pattern = "\"" + key + "\":\"";
  int start = json.indexOf(pattern);
  if (start == -1) return "";
  start += pattern.length();
  int end = json.indexOf('"', start);
  if (end == -1) return "";
  return json.substring(start, end);
}

/* DBMS mode: sends the confirmed distance plus the pattern anomaly
   data to receive_data.php so it can be classified and stored in
   MySQL, and returns the final_status the server computed so the
   buzzer/LED can react to it. Returns "" if the upload failed for any
   reason (no WiFi, no response, unexpected shape). */
String sendToDatabaseAndGetSeverity(float distance, bool patternAnomaly, float measuredIntervalSec) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi not connected, skipping database upload for this event");
    return "";
  }

  WiFiClient client;
  HTTPClient http;
  String url = "http://" + String(SERVER_IP) + String(SERVER_PATH);

  http.begin(client, url);
  http.addHeader("Content-Type", "application/json");

  String jsonBody = "{\"distance\":" + String(distance) +
                     ",\"motion\":1" +
                     ",\"pattern_anomaly\":" + String(patternAnomaly ? "true" : "false") +
                     ",\"measured_interval_sec\":" + String(measuredIntervalSec) + "}";
  int httpCode = http.POST(jsonBody);

  String severity = "";
  if (httpCode > 0) {
    String response = http.getString();
    Serial.print("Database upload response: ");
    Serial.println(response);
    severity = extractJsonStringValue(response, "final_status");
  } else {
    Serial.print("Database upload failed, HTTP error: ");
    Serial.println(http.errorToString(httpCode));
  }

  http.end();
  return severity;
}

/* TinyML mode: best-effort log of a prediction this Receiver already
   made and already reacted to. If this fails, it only affects what
   the dashboard can show, never the buzzer/LED, which already ran. */
void logTinyMlPrediction(float distance, float measuredIntervalSec, bool patternAnomaly, const char *severity) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi not connected, skipping TinyML log for this event");
    return;
  }

  WiFiClient client;
  HTTPClient http;
  String url = "http://" + String(SERVER_IP) + String(TINYML_LOG_PATH);

  http.begin(client, url);
  http.addHeader("Content-Type", "application/json");

  String jsonBody = "{\"device_role\":\"receiver\"" +
                     String(",\"distance\":") + String(distance) +
                     ",\"measured_interval_sec\":" + String(measuredIntervalSec) +
                     ",\"pattern_anomaly\":" + String(patternAnomaly ? "true" : "false") +
                     ",\"predicted_severity\":\"" + String(severity) + "\"}";
  int httpCode = http.POST(jsonBody);

  if (httpCode > 0) {
    Serial.print("TinyML log response: ");
    Serial.println(http.getString());
  } else {
    Serial.print("TinyML log failed, HTTP error: ");
    Serial.println(http.errorToString(httpCode));
  }

  http.end();
}

void setup() {
  Serial.begin(115200);

  pinMode(GREEN_LED, OUTPUT);
  pinMode(RED_LED, OUTPUT);
  pinMode(YELLOW_LED, OUTPUT);
  pinMode(BUZZER, OUTPUT);
  pinMode(MODE_SWITCH, INPUT_PULLUP);

  digitalWrite(GREEN_LED, HIGH);
  digitalWrite(RED_LED, LOW);
  digitalWrite(YELLOW_LED, LOW);
  digitalWrite(BUZZER, LOW);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  Serial.print("Connecting to WiFi");
  unsigned long wifiStartAttempt = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - wifiStartAttempt < 15000) {
    delay(500);
    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("");
    Serial.print("WiFi connected, IP address: ");
    Serial.println(WiFi.localIP());
    Serial.print("WiFi channel in use: ");
    Serial.println(WiFi.channel());
    Serial.println("IMPORTANT: the Sender must be set to this exact same channel, or ESP-NOW will stop working between them.");
  } else {
    Serial.println("");
    Serial.println("WiFi not connected, will keep retrying in the background. ESP-NOW still works without it, and so does TinyML mode.");
  }

  if (!LittleFS.begin()) {
    LittleFS.format();
    LittleFS.begin();
  }

  if (esp_now_init() != 0) {
    ESP.restart();
  }

  esp_now_set_self_role(ESP_NOW_ROLE_SLAVE);
  esp_now_register_recv_cb(OnDataRecv);

  Serial.println("Phase 4 ready. Switch OFF = DBMS mode, Switch ON = TinyML mode.");
}

void loop() {
  bool connectionLost = (millis() - lastReceiveTime > noDataTimeout);
  bool tinyMlModeActive = (digitalRead(MODE_SWITCH) == LOW);  // LOW = closed = ON = TinyML

  /* Mode-exclusive LEDs: TinyML mode only ever uses Yellow LED and
     Buzzer, Green and Red stay off the entire time it is active. DBMS
     mode only ever uses Green and Red, Yellow LED and Buzzer stay off
     the entire time it is active. This is enforced every loop cycle,
     not only when a new packet arrives, so switching the mode takes
     effect immediately even with no data flowing. */
  if (tinyMlModeActive) {
    digitalWrite(GREEN_LED, LOW);
    digitalWrite(RED_LED, LOW);
  } else {
    digitalWrite(YELLOW_LED, LOW);
    digitalWrite(BUZZER, LOW);

    if (!hasReceivedOnce || connectionLost || !incomingData.anyDetected) {
      digitalWrite(GREEN_LED, HIGH);
      digitalWrite(RED_LED, LOW);
    } else {
      digitalWrite(GREEN_LED, LOW);
      digitalWrite(RED_LED, HIGH);
    }
  }

  if (pendingUpload) {
    pendingUpload = false;

    if (tinyMlModeActive) {
      /* This Receiver's own model, evaluated on what it just
         received, purely based on this Receiver's own switch, no
         longer dependent on the Sender's switch state. Normal ->
         Yellow LED, warning/critical -> buzzer beeps (1 beep warning,
         2 beeps critical), handled inside reactToSeverity(). */
      float features[3] = { pendingDistance, pendingMeasuredIntervalSec, pendingPatternAnomaly ? 1.0f : 0.0f };
      int predictedClass = tinyMlModel.predict(features);
      const char *severity = tinyMlModel.idxToLabel(predictedClass);

      Serial.print("TinyML mode -> on-device prediction: ");
      Serial.println(severity);

      reactToSeverity(String(severity));  // buzzer/LED react immediately, no network wait

      logTinyMlPrediction(pendingDistance, pendingMeasuredIntervalSec, pendingPatternAnomaly, severity);
    }
    else {
      /* DBMS mode, exactly like Phase 3: only forward to the server
         and store the result. Yellow LED/Buzzer are already forced
         off above and are not touched by this path at all. */
      String severity = sendToDatabaseAndGetSeverity(pendingDistance, pendingPatternAnomaly, pendingMeasuredIntervalSec);
      if (severity != "") {
        Serial.print("DBMS mode -> server final_status: ");
        Serial.println(severity);
      }
    }
  }

  delay(100);
}
