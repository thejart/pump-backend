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
    /** @var bool */
    private $debug = false;

    public function __construct($envFile) {
        error_log("Wipe log: " . date('Y-m-d H:i:s'));

        parent::__construct($envFile, true);
        $this->hour = (int)date("H"); // 00 through 23
        $this->day = (int)date("N");  // 1 through 7, Mon through Sun
    }

    public function shouldText() {
        $this->notifications = [];

        if ($this->debug) {
            $this->notifications[] = "This is a test";
        }

        if ($this->isMysqlDown) {
            $this->isAnAlert = true;
            $this->notifications[] = "MySQL is down!";
            return true;
        }

        // If this is a weekly job run, prepare a summary message and exit method
        if ($this->day == 6 && $this->hour < 12) {
			//$this->notifications[] = $this->getOldMessage();
			$this->notifications[] = $this->getWeeklyMessage();

            error_log("[Notifying] " . implode("; ", $this->notifications));
            return true;
        }

        // ...otherwise we're checking for alert-worthy events since the last, relatively frequent job run
        $healthCheckCount = $this->numberOfHealthChecksInLastXHours(self::CRONJOB_CADENCE_IN_HOURS);

        // The healthcheck occurs hourly, the cron'd wipecheck job runs every 12 hours.
        // Taking the nano's imprecise clock into account, we should expect at least 11 checks
        if ($healthCheckCount < self::HEALTHCHECK_COUNT_THRESHOLD) {
            $this->notifications[] = "Too few healthchecks (only {$healthCheckCount} in the past " . self::CRONJOB_CADENCE_IN_HOURS . " hours)";
            $this->isAnAlert = true;
        }

        if (!$this->hasHadRecentPumping()) {
            $this->notifications[] = "No recent pump events in the past " . self::NO_PUMPING_THRESHOLD_IN_DAYS . " days";
            $this->isAnAlert = true;
        }

        // Log alert notifications
        if (!empty($this->notifications)) {
            if ($this->isAnAlert) {
                error_log("[ALERTING] " . implode("; ", $this->notifications));
            } else {
                error_log("[Notifying] " . implode("; ", $this->notifications));
            }
        }

        return $this->isAnAlert;
    }

    public function getMessage() {
        if ($this->isAnAlert) {
            return "[POOP ALERT!]\n" . implode('; ', $this->notifications);
        } else {
            return "[weekly summary]\n" . implode('; ', $this->notifications);
        }
    }

	private function getOldMessage() : string {
		$numberOfEventsInLastWeek = count($this->getXDaysOfRecentEvents(self::SUMMARY_TEXT_CADENCE_IN_DAYS));
		$totalReboots = $this->getRebootCountInXDays(self::SUMMARY_TEXT_CADENCE_IN_DAYS);
		$cycleStats = $this->getRecentCycleStats();

		return "{$totalReboots} reboots and {$numberOfEventsInLastWeek} pump events this week.\n" .
			"{$cycleStats['daysBetweenReboots']} days between recent reboots after {$cycleStats['eventCount']} events";
	}

	private function getWeeklyMessage() : string {
		$healthAndStartupChecks = 0;
		$events = $this->getXDaysOfRecentEvents(self::SUMMARY_TEXT_CADENCE_IN_DAYS);
		foreach ($events as $event) {
			if ($event->type == self::EVENT_TYPE_HEALTHCHECK || $event->type == self::EVENT_TYPE_STARTUP) {
				$healthAndStartupChecks++;
			}
		}

		$uptime = sprintf("%.1f%%",100 * $healthAndStartupChecks / (self::SUMMARY_TEXT_CADENCE_IN_DAYS * 24));
		$totalReboots = $this->getRebootCountInXDays(self::SUMMARY_TEXT_CADENCE_IN_DAYS);
		$lastRebootTimestamp = $this->getMostRecentEventsOfEachType()[self::EVENT_TYPE_STARTUP];
		$lastRebootString = date("jS @ g:ia", strtotime($lastRebootTimestamp));

		return "{$uptime} uptime with {$totalReboots} reboots. Last rebooted on the {$lastRebootString}";
	}
}
