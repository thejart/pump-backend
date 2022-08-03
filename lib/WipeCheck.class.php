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
        $totalCallouts = $this->getCurrentCalloutCount();
        $this->notifications = [];

        // The healthcheck runs hourly, the cron'd wipecheck job runs ever 12 hours.
        // Taking the nano's imprecise clock into account, we should expect at least 11 checks
        if ($this->numberOfHealthChecksInLastXHours(self::CRONJOB_FREQUENCY) < self::HEALTHCHECK_THRESHOLD) {
            $this->notifications[] = "Too few healthchecks";
            $this->isAnAlert = true;
        }
        if (!$this->hasHadRecentPumping()) {
            $this->notifications[] = "No recent pumping";
            $this->isAnAlert = true;
        }

        // This should only be a warning since the nano is programmed to reboot itself
        // TODO: Update the send logic to handle warnings
        if ($totalCallouts >= self::CALLOUT_LIMIT) {
            $this->notifications[] = "Reaching callout limit";
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
            $healthChecksInLastWeek = $this->numberOfHealthChecksInLastXHours(24 * 7);
            $numberOfEventsInLastWeek = count($this->getXDaysOfRecentEvents(7));

            $this->notifications[] = "{$numberOfEventsInLastWeek} pump events and" .
                                     //" w/ Y inferred washing machine events) and" .
                                     " {$healthChecksInLastWeek} health checks this week" .
                                     " with {$totalCallouts} total HTTP requests";
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
