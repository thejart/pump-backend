<?php
require_once __DIR__ . '/BaseShit.class.php';

class WipeCheck extends BaseShit {
    /** @var bool */
    private $isAnAlert = false;
    /** @var array */
    private $notifications = [];
    /** @var int */
    private $hour;
    /** @var int */
    private $day;

    public function __construct($envFile) {
        error_log("Wipe log: " . date('Y-m-d h:i:s'));

        parent::__construct($envFile, true);
        $this->hour = (int)date("H"); // 00 through 23
        $this->day = (int)date("N");  // 1 through 7, Mon through Sun
    }

    public function shouldTextAlert() {
        $this->notifications = [];
        $healthCheckCount = $this->numberOfHealthChecksInLastXHours(self::CRONJOB_PERIOD_IN_HOURS);

        // The healthcheck occurs hourly, the cron'd wipecheck job runs every 12 hours.
        // Taking the nano's imprecise clock into account, we should expect at least 11 checks
        if ($healthCheckCount < self::HEALTHCHECK_COUNT_THRESHOLD) {
            $this->notifications[] = "Too few healthchecks (only {$healthCheckCount} in the past " . self::HEALTHCHECK_COUNT_THRESHOLD . " hours)";
            $this->isAnAlert = true;
        }
        if (!$this->hasHadRecentPumping()) {
            $this->notifications[] = "No recent pump events in the past " . self::NO_PUMPING_THRESHOLD_IN_DAYS . " days";
            $this->isAnAlert = true;
        }

        // Send an alert
        if ($this->isAnAlert) {
            // No need for a summary, get the alert out
            error_log("[ALERTING] " . implode("; ", $this->notifications));
            return true;
        }

        // ...or send a summary
        if ($this->day == 6 && $this->hour < 12) {
            $numberOfEventsInLastWeek = count($this->getXDaysOfRecentEvents(7));
            $totalCallouts = $this->getCurrentCalloutCount();

            $this->notifications[] = "{$numberOfEventsInLastWeek} pump events in the past week and " .
                                     "{$totalCallouts} total HTTP requests since reboot";
            error_log("[Notifying] " . implode("; ", $this->notifications));
            return true;
        }

        return false;
    }

    public function getMessage() {
        if ($this->isAnAlert) {
            return "[POOP ALERT!] " . implode('; ', $this->notifications);
        } else {
            return "[poop summary] " . implode('; ', $this->notifications);
        }
    }
}
