<?php

class BaseShit {
    const EVENT_TYPE_STARTUP = 1;
    const EVENT_TYPE_PUMPING = 2;
    const EVENT_TYPE_HEALTHCHECK = 3;
    const EVENT_TYPE_WASHING_MACHINE = 4;

    const CALLOUT_LIMIT = 250;        // the nano 33 iot seems to crap out around 300 HTTP callouts
    const HEALTHCHECK_THRESHOLD = 13; // hours (i.e. the healthcheck should occur every 12 hours, plus some wiggle room)
    const PUMPING_THRESHOLD = 3;      // days (i.e. there should be a pumping event every 3 days under normal circumstances)

    /** @var PDO */
    private $pdo;

    // Twilio Secrets
    private $account_sid;
    private $auth_token;
    private $twilio_number;
    private $text_number;

    public function __construct($shouldParseTwilioSecrets = false) {
        if ($shouldParseTwilioSecrets) {
            list($mysqlDatabase, $mysqlUsername, $mysqlPassword, $this->account_sid, $this->auth_token, $this->twilio_number, $this->text_number) = $this->setupEnvironment();
        } else {
            list($mysqlDatabase, $mysqlUsername, $mysqlPassword) = $this->setupEnvironment();
        }

        $this->pdo = new PDO("mysql:host=127.0.0.1;dbname=". $mysqlDatabase, $mysqlUsername, $mysqlPassword);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function insertPumpEvent() {
        $query = $this->pdo->prepare("
            INSERT INTO pump_events
            (x_value, y_value, z_value, type, timestamp)
            values (:x_value, :y_value, :z_value, :type, now())
        ");

        $query->execute([
            ':x_value' => $this->xValue,
            ':y_value' => $this->yValue,
            ':z_value' => $this->zValue,
            ':type' => $this->type
        ]);

        if ($query->rowCount()) {
            return true;
        }

        return false;
    }

    public function getMostRecentEventsOfEachType() {
        $results = [];
        $types = [self::EVENT_TYPE_STARTUP, self::EVENT_TYPE_PUMPING, self::EVENT_TYPE_HEALTHCHECK];

        foreach ($types as $type) {
            $query = $this->pdo->prepare("
                SELECT type, timestamp FROM pump_events
                WHERE type=:type
                ORDER BY timestamp DESC
                LIMIT 1
              ");

            $query->execute([':type' => $type]);
            $result = $query->fetchAll(PDO::FETCH_OBJ);
            $results[$type] = $result[0]->timestamp;
        }

        return $results;
    }

    public function getCurrentCalloutCount() {
        $query = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM pump_events
            WHERE timestamp >= (
                SELECT timestamp
                FROM pump_events
                WHERE type=1
                ORDER BY timestamp DESC
                LIMIT 1
            )
        ");

        $query->execute();
        return (int)$query->fetchAll(PDO::FETCH_OBJ)[0]->count;
    }

    public function getAccountSid() {
        return $this->account_sid;
    }

    public function getAuthToken() {
        return $this->auth_token;
    }

    public function getTwilioNumber() {
        return $this->twilio_number;
    }

    public function getTextNumber() {
        return $this->text_number;
    }

    protected function getXDaysOfRecentEvents() {
        if ($this->viewWindow <= 0) {
            // Any non-positive value will result in gathering all events since the last startup signal
            $query = $this->pdo->prepare("
                SELECT id, x_value, y_value, z_value, type, timestamp
                FROM  pump_events
                WHERE timestamp >= '{$this->getMostRecentEventsOfEachType()[self::EVENT_TYPE_STARTUP]}'
                ORDER BY timestamp
            ");
        } else {
            $query = $this->pdo->prepare("
                SELECT id, x_value, y_value, z_value, type, timestamp
                FROM  pump_events
                WHERE timestamp > DATE_SUB(NOW(), INTERVAL {$this->viewWindow} DAY)
                ORDER BY timestamp
            ");
        }

        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    protected function getMaxAbsoluteValue($event) {
        // Startup and Healthcheck events have their gryoscopic data overwritten for visual aesthetic
        if ($event->type == self::EVENT_TYPE_STARTUP) {
            return 11;
        } elseif ($event->type == self::EVENT_TYPE_HEALTHCHECK) {
            return 1;
        }

        $maxAbsValue = abs($event->x_value);
        if (abs($event->y_value) > $maxAbsValue) {
            $maxAbsValue = abs($event->y_value);
        }
        if (abs($event->z_value) > $maxAbsValue) {
            $maxAbsValue = abs($event->z_value);
        }
        return $maxAbsValue;
    }

    protected function hasHadRecentHealthCheck() {
        $query = $this->pdo->prepare("
            SELECT COUNT(*) AS count
            FROM pump_events
            WHERE type=:type
            AND timestamp > DATE_SUB(NOW(), INTERVAL " . self::HEALTHCHECK_THRESHOLD . " HOUR)
        ");

        $query->execute([':type' => self::EVENT_TYPE_HEALTHCHECK]);
        return (int)$query->fetchAll(PDO::FETCH_OBJ)[0]->count;
    }

    protected function hasHadRecentPumping() {
        $query = $this->pdo->prepare("
            SELECT COUNT(*) AS count
            FROM pump_events
            WHERE type=:type
            AND timestamp > DATE_SUB(NOW(), INTERVAL " . self::PUMPING_THRESHOLD . " DAY)
        ");

        $query->execute([':type' => self::EVENT_TYPE_PUMPING]);
        return (int)$query->fetchAll(PDO::FETCH_OBJ)[0]->count;
    }

    protected function getRequestParam($field, $default = null) {
        return isset($_REQUEST[$field]) ? $_REQUEST[$field] : $default;
    }

    private function setupEnvironment() {
        try {
            $environmentFile = file_get_contents('secrets');
        } catch (Exception $e) {
            throw new Exception('Unable to read in environment file :'. $e->getMessage());
        }
        return explode("\n", $environmentFile);
    }
}