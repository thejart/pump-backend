<?php

class Shit {
    const STARTUP = 1;
    const PUMPING = 2;
    const HEALTHCHECK = 3;

    const CALLOUT_LIMIT = 250;        // the nano 33 iot seems to crap out around 300 HTTP callouts
    const HEALTHCHECK_THRESHOLD = 13; // hours (i.e. the healthcheck should occur every 12 hours, plus some wiggle room)
    const PUMPING_THRESHOLD = 3;      // days (i.e. there should be a pumping event every 3 days under normal circumstances)

    /** @var PDO */
    protected $pdo;

    // Twilio Secrets
    protected $account_sid;
    protected $auth_token;
    protected $twilio_number;
    protected $text_number;

    public function __construct($shouldParseTwilioSecrets = false) {
        if ($shouldParseTwilioSecrets) {
            list($mysqlDatabase, $mysqlUsername, $mysqlPassword, $this->account_sid, $this->auth_token, $this->twilio_number, $this->text_number) = $this->setupEnvironment();
        } else {
            list($mysqlDatabase, $mysqlUsername, $mysqlPassword) = $this->setupEnvironment();
        }

        $this->pdo = new PDO("mysql:host=127.0.0.1;dbname=". $mysqlDatabase, $mysqlUsername, $mysqlPassword);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function setupEnvironment() {
        try {
            $environmentFile = file_get_contents('secrets');
        } catch (Exception $e) {
            throw new Exception('Unable to read in environment file :'. $e->getMessage());
        }
        return explode("\n", $environmentFile);
    }

    public function getMostRecentsOfEachType() {
        $results = [];
        $types = [self::STARTUP, self::PUMPING, self::HEALTHCHECK];

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

    public function getDaysOfRecentEvents() {
        $query = $this->pdo->prepare("
            SELECT id, x_value, y_value, z_value, type, timestamp FROM  pump_events
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL {$this->viewWindow} DAY)
            ORDER BY timestamp
        ");

        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
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

    protected function hasHadRecentHealthCheck() {
        $query = $this->pdo->prepare("
            SELECT COUNT(*) AS count
            FROM pump_events
            WHERE type=:type
            AND timestamp > DATE_SUB(NOW(), INTERVAL " . self::HEALTHCHECK_THRESHOLD . " HOUR)
        ");

        $query->execute([':type' => self::HEALTHCHECK]);
        return (int)$query->fetchAll(PDO::FETCH_OBJ)[0]->count;
    }

    protected function hasHadRecentPumping() {
        $query = $this->pdo->prepare("
            SELECT COUNT(*) AS count
            FROM pump_events
            WHERE type=:type
            AND timestamp > DATE_SUB(NOW(), INTERVAL " . self::PUMPING_THRESHOLD . " DAY)
        ");

        $query->execute([':type' => self::PUMPING]);
        return (int)$query->fetchAll(PDO::FETCH_OBJ)[0]->count;
    }

    public function getMaxAbsoluteValue($event) {
        // Pumping events have a threshold of 5, so arbitrarily setting these event types well below that
        // creates a visual distinction (in addition to the different colors)
        if ($event->type == self::STARTUP) {
            return 2;
        } elseif ($event->type == self::HEALTHCHECK) {
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

    protected function getRequestParam($field, $default = null) {
        return isset($_REQUEST[$field]) ? $_REQUEST[$field] : $default;
    }
}
