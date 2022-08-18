<?php
require_once __DIR__ . '/BaseShit.class.php';

class WipeCheck extends BaseShit {
    const SUMMARY_TEXT_CADENCE_IN_DAYS = 7;

    /** @var bool */
    private $isAnAlert = false;
    /** @var array */
    private $notifications = [];
    /** @var int */
    private $hour;
    /** @var int */
    private $day;

    public function __construct($envFile) {
        error_log("Wipe log: " . date('Y-m-d H:i:s'));

        parent::__construct($envFile, true);
        $this->hour = (int)date("H"); // 00 through 23
        $this->day = (int)date("N");  // 1 through 7, Mon through Sun
    }

    public function shouldTextAlert() {
        $this->notifications = [];

        // If this is a weekly job run, prepare a summary message and exit method
        if ($this->day == 6 && $this->hour < 12) {
            $numberOfEventsInLastWeek = count($this->getXDaysOfRecentEvents(self::SUMMARY_TEXT_CADENCE_IN_DAYS));
            $totalReboots = $this->getRebootCountInXDays(self::SUMMARY_TEXT_CADENCE_IN_DAYS);
            $daysBetweenRecentReboots = $this->getNumberOfDaysBetweenRecentReboots();

            $this->notifications[] = "{$totalReboots} reboots and {$numberOfEventsInLastWeek} pump events this week.\n" .
                "{$daysBetweenRecentReboots} days between recent reboots";
            error_log("[Notifying] " . implode("; ", $this->notifications));
            return true;
        }

        // ...otherwise we're checking for alert-worthy events since the last, relatively frequent job run
        $healthCheckCount = $this->numberOfHealthChecksInLastXHours(self::CRONJOB_CADENCE_IN_HOURS);

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

        // Log alert notifications
        if (!empty($this->notifications)) {
            // No need for a summary, get the alert out
            error_log("[ALERTING] " . implode("; ", $this->notifications));
        }

        return $this->isAnAlert;
    }

    public function getMessage() {
        if ($this->isAnAlert) {
            return "[POOP ALERT!]\n" . implode('; ', $this->notifications);
        } else {
            return "[poop summary]\n" . implode('; ', $this->notifications);
        }
    }
}
