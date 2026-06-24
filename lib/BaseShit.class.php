<?php

class BaseShit {
    const EVENT_TYPE_STARTUP = 1;
    const EVENT_TYPE_PUMPING = 2;
    const EVENT_TYPE_HEALTHCHECK = 3;
    const EVENT_TYPE_WASHING_MACHINE = 4;

    const CRONJOB_CADENCE_IN_HOURS = 12;     // the cron job runs every 12 hours
    const HEALTHCHECK_COUNT_THRESHOLD = 11; // we should expect at least 11 healthchecks within 12 hours (given the nano's imprecise clock)
    const NO_PUMPING_THRESHOLD_IN_DAYS = 3; // days (i.e. there should be a pumping event every 3 days under normal circumstances)

    const AUTH_CHALLENGE_TTL_SECONDS = 600; // a texted confirmation code is valid for 10 minutes
    const AUTH_CHALLENGE_MAX_ATTEMPTS = 5;  // lock out (and require a new code) after this many wrong guesses

    /** @var PDO */
    private $pdo;

    /** @var string */
    protected $shitAuth;

    /** @var bool */
    public $isMysqlDown = false;

    // Texting secrets
    /** @var string */
    private $textbeltToken;
    /** @var string[] */
    private $text_numbers;

    public function __construct($envFile, $shouldParseTextingSecrets = false) {
        if ($shouldParseTextingSecrets) {
            list($mysqlDatabase, $mysqlUsername, $mysqlPassword, $this->shitAuth, $this->textbeltToken, $textNumbersString) = $this->setupEnvironment($envFile);
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

    public function getTextbeltToken() {
        return $this->textbeltToken;
    }

    public function getTextNumbers() {
        return $this->text_numbers;
    }

    // Sends an SMS via Textbelt to every configured recipient. Requires the texting secrets
    // to have been parsed (construct with $shouldParseTextingSecrets = true).
    public function sendText($message) {
        if (empty($this->text_numbers)) {
            error_log("No text recipients configured; skipping SMS");
            return;
        }

        foreach ($this->text_numbers as $number) {
            $number = trim($number);
            if ($number === '') {
                continue;
            }

            $handler = curl_init("https://textbelt.com/text");
            curl_setopt($handler, CURLOPT_POST, true);
            curl_setopt($handler, CURLOPT_POSTFIELDS, http_build_query([
                'phone' => $number,
                'message' => $message,
                'key' => $this->textbeltToken,
            ]));
            curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($handler);
            curl_close($handler);
            error_log("texting {$number}: {$response}");
        }
    }

    protected function getXDaysOfRecentEvents(int $numberOfDays) {
        if ($numberOfDays <= 0) {
            // Any non-positive value will result in gathering all events since the last startup signal
            $query = $this->pdo->prepare("
                SELECT id, x_value, y_value, z_value, type, UNIX_TIMESTAMP(timestamp) * 1000 as timestamp
                FROM  pump_events
                WHERE timestamp >= '{$this->getMostRecentEventsOfEachType()[self::EVENT_TYPE_STARTUP]}'
                ORDER BY timestamp
            ");
        } else {
            $query = $this->pdo->prepare("
                SELECT id, x_value, y_value, z_value, type, UNIX_TIMESTAMP(timestamp) * 1000 as timestamp
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

    // Returns the end_date of the currently active vacation (a future, unarchived row), or null if none.
    public function getActiveVacationEndDate() {
        $query = $this->pdo->prepare("
            SELECT end_date
            FROM vacation
            WHERE end_date > NOW()
            AND is_archived = 0
            ORDER BY end_date DESC
            LIMIT 1
        ");

        $query->execute();
        $result = $query->fetchAll(PDO::FETCH_OBJ);
        return $query->rowCount() ? $result[0]->end_date : null;
    }

    public function hasActiveVacation() {
        return !is_null($this->getActiveVacationEndDate());
    }

    // Sets a new vacation end date, archiving any existing active vacation first so that
    // at most one active (future, unarchived) row exists at a time.
    public function setVacationEndDate($endDate) {
        $parsedTimestamp = strtotime($endDate);
        if ($parsedTimestamp === false) {
            error_log("Unable to parse vacation end date: {$endDate}");
            return false;
        }

        $this->clearVacation();

        $query = $this->pdo->prepare("
            INSERT INTO vacation (end_date)
            VALUES (:end_date)
        ");

        try {
            $query->execute([':end_date' => date("Y-m-d H:i:s", $parsedTimestamp)]);
        } catch (PDOException $e) {
            error_log("Unable to insert vacation end date: {$endDate}");
            return false;
        }

        return (bool)$query->rowCount();
    }

    // Soft-deletes the active vacation (preserving history) by flagging it archived.
    public function clearVacation() {
        $query = $this->pdo->prepare("
            UPDATE vacation
            SET is_archived = 1
            WHERE end_date > NOW()
            AND is_archived = 0
        ");

        $query->execute();
    }

    // Stores a one-time confirmation code (hashed) bound to a pending action/payload. Only one
    // challenge is outstanding at a time, so any previous challenge is cleared first.
    public function createAuthChallenge($action, $payload, $code) {
        $this->clearAuthChallenges();

        $ttl = (int)self::AUTH_CHALLENGE_TTL_SECONDS;
        $query = $this->pdo->prepare("
            INSERT INTO auth_challenge (code_hash, action, payload, expires_at)
            VALUES (:code_hash, :action, :payload, DATE_ADD(NOW(), INTERVAL {$ttl} SECOND))
        ");

        try {
            $query->execute([
                ':code_hash' => hash('sha256', $code),
                ':action' => $action,
                ':payload' => $payload,
            ]);
        } catch (PDOException $e) {
            error_log("Unable to create auth challenge");
            return false;
        }

        return (bool)$query->rowCount();
    }

    // Validates a submitted code against the active (unexpired) challenge. On success the challenge
    // is consumed (deleted) and its row returned; on failure the attempt count is incremented and the
    // challenge is discarded once too many wrong guesses accumulate. Returns the challenge row or null.
    public function verifyAuthChallenge($code) {
        $query = $this->pdo->prepare("
            SELECT id, action, payload, code_hash, attempts
            FROM auth_challenge
            WHERE expires_at > NOW()
            ORDER BY id DESC
            LIMIT 1
        ");

        $query->execute();
        $rows = $query->fetchAll(PDO::FETCH_OBJ);
        if (!$rows) {
            return null;
        }
        $challenge = $rows[0];

        if (hash_equals($challenge->code_hash, hash('sha256', (string)$code))) {
            $this->deleteAuthChallenge($challenge->id);
            return $challenge;
        }

        if ($challenge->attempts + 1 >= self::AUTH_CHALLENGE_MAX_ATTEMPTS) {
            $this->deleteAuthChallenge($challenge->id);
        } else {
            $update = $this->pdo->prepare("UPDATE auth_challenge SET attempts = attempts + 1 WHERE id = :id");
            $update->execute([':id' => $challenge->id]);
        }

        return null;
    }

    // Seconds since the most recent challenge was created (any state), or null if none exist.
    // Used to rate-limit how often a new code can be texted.
    public function secondsSinceLastAuthChallenge() {
        $query = $this->pdo->prepare("
            SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS seconds
            FROM auth_challenge
            ORDER BY id DESC
            LIMIT 1
        ");

        $query->execute();
        $rows = $query->fetchAll(PDO::FETCH_OBJ);
        return $rows ? (int)$rows[0]->seconds : null;
    }

    public function clearAuthChallenges() {
        $this->pdo->prepare("DELETE FROM auth_challenge")->execute();
    }

    private function deleteAuthChallenge($id) {
        $query = $this->pdo->prepare("DELETE FROM auth_challenge WHERE id = :id");
        $query->execute([':id' => $id]);
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
