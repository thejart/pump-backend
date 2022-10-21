<?php

class BaseShit {
    const EVENT_TYPE_STARTUP = 1;
    const EVENT_TYPE_PUMPING = 2;
    const EVENT_TYPE_HEALTHCHECK = 3;
    const EVENT_TYPE_WASHING_MACHINE = 4;

    const CRONJOB_CADENCE_IN_HOURS = 12;     // the cron job runs every 12 hours
    const HEALTHCHECK_COUNT_THRESHOLD = 11; // we should expect at least 11 healthchecks within 12 hours (given the nano's imprecise clock)
    const NO_PUMPING_THRESHOLD_IN_DAYS = 3; // days (i.e. there should be a pumping event every 3 days under normal circumstances)

    /** @var PDO */
    private $pdo;

    /** @var string */
    protected $shitAuth;

    /** @var bool */
    public $isMysqlDown = false;

    // Twilio Secrets
    /** @var string */
    private $account_sid;
    /** @var string */
    private $auth_token;
    /** @var string */
    private $twilio_number;
    /** @var string[] */
    private $text_numbers;

    public function __construct($envFile, $shouldParseTwilioSecrets = false) {
        if ($shouldParseTwilioSecrets) {
            list($mysqlDatabase, $mysqlUsername, $mysqlPassword, $this->shitAuth, $this->account_sid, $this->auth_token, $this->twilio_number, $textNumbersString) = $this->setupEnvironment($envFile);
            $this->text_numbers = explode(",", $textNumbersString);
        } else {
            list($mysqlDatabase, $mysqlUsername, $mysqlPassword, $this->shitAuth) = $this->setupEnvironment($envFile);
        }

        try {
            $this->pdo = new PDO("mysql:host=127.0.0.1;dbname=". $mysqlDatabase, $mysqlUsername, $mysqlPassword);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            $this->isMysqlDown = true;
            error_log("Unable to connect to the database");
            // Seems hacky, but tests gotta test
            if (strpos($envFile, 'testing') === false) {
                exit;
            }
        }
    }

    public function insertPumpEvent($x, $y, $z, $type, $timestamp) {
        $query = $this->pdo->prepare("
            INSERT INTO pump_events
            (x_value, y_value, z_value, type, timestamp)
            values (:x_value, :y_value, :z_value, :type, :timestamp)
        ");

        try {
            $query->execute([
                ':x_value' => $x,
                ':y_value' => $y,
                ':z_value' => $z,
                ':type' => $type,
                ':timestamp' => $timestamp
            ]);
        } catch (PDOException $e) {
            error_log("Unable to insert pump event x:{$x}, y:{$y}, z:{$z}, type:{$type}, timestamp:{$timestamp}");
        }

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
            $results[$type] = $query->rowCount() ? $result[0]->timestamp : null;
        }

        return $results;
    }

    public function getRebootCountInXDays(int $numberOfDays) {
        $query = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM pump_events
            WHERE type=1
            AND timestamp >= DATE_SUB(NOW(), INTERVAL {$numberOfDays} DAY)
        ");

        $query->execute();
        return (int)$query->fetchAll(PDO::FETCH_OBJ)[0]->count;
    }

    public function getCalloutCountSinceReboot() {
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

    public function getTextNumbers() {
        return $this->text_numbers;
    }

    protected function getXDaysOfRecentEvents(int $numberOfDays) {
        if ($numberOfDays <= 0) {
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
                WHERE timestamp > DATE_SUB(NOW(), INTERVAL {$numberOfDays} DAY)
                ORDER BY timestamp
            ");
        }

        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }

    protected function getRecentCycleStats() {
        $currentRebootTimestamp = $this->getMostRecentEventsOfEachType()[self::EVENT_TYPE_STARTUP];
        $previousRebootTimestamp = $this->getPreviousRebootTimestamp($currentRebootTimestamp);
        $eventCount = $this->getEventCountBetweenTimestamps($previousRebootTimestamp, $currentRebootTimestamp);

        $query = $this->pdo->prepare("
            SELECT DATEDIFF('{$currentRebootTimestamp}', '{$previousRebootTimestamp}') AS days
        ");

        $query->execute();
        return [
            'daysBetweenReboots' => $query->fetchAll(PDO::FETCH_OBJ)[0]->days,
            'eventCount' => $eventCount
        ];
    }

    protected function getPreviousRebootTimestamp($rebootTimestamp) {
        $query = $this->pdo->prepare("
            SELECT timestamp
            FROM pump_events
            WHERE timestamp < '{$rebootTimestamp}'
            AND type=1
            ORDER BY timestamp DESC
            LIMIT 1
        ");

        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ)[0]->timestamp;
    }

    protected function getEventCountBetweenTimestamps($earlierTimestamp, $laterTimestamp) {
        $query = $this->pdo->prepare("
            SELECT COUNT(*) AS count
            FROM pump_events
            WHERE timestamp >= '{$earlierTimestamp}'
            AND timestamp < '{$laterTimestamp}'
        ");

        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ)[0]->count;
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

    protected function numberOfHealthChecksInLastXHours(int $numberOfHours) {
        // Startup events are the initial healthcheck, so they should be included
        $query = $this->pdo->prepare("
            SELECT COUNT(*) AS count
            FROM pump_events
            WHERE (type=:startup OR type=:healthcheck)
            AND timestamp > DATE_SUB(NOW(), INTERVAL {$numberOfHours} HOUR)
        ");

        $query->execute([':startup' => self::EVENT_TYPE_STARTUP, ':healthcheck' => self::EVENT_TYPE_HEALTHCHECK]);
        return (int)$query->fetchAll(PDO::FETCH_OBJ)[0]->count;
    }

    protected function hasHadRecentPumping() {
        $query = $this->pdo->prepare("
            SELECT COUNT(*) AS count
            FROM pump_events
            WHERE type=:type
            AND timestamp > DATE_SUB(NOW(), INTERVAL " . self::NO_PUMPING_THRESHOLD_IN_DAYS . " DAY)
        ");

        $query->execute([':type' => self::EVENT_TYPE_PUMPING]);
        return (int)$query->fetchAll(PDO::FETCH_OBJ)[0]->count;
    }

    protected function getRequestParam($field, $default = null) {
        return $_REQUEST[$field] ?? $default;
    }

    private function setupEnvironment($envFile) {
        try {
            $parsedEnvFile = file_get_contents($envFile);
        } catch (Exception $e) {
            error_log("Unable to parse credentials in {$envFile}");
            throw new Exception('Unable to read in environment file :'. $e->getMessage());
        }
        return explode("\n", $parsedEnvFile);
    }
}
