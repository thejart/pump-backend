<?php
require __DIR__ . '/BaseShit.class.php';

class WipeCheck extends BaseShit {
    /** @var array */
    private $alerts = [];

    public function __construct() {
        parent::__construct(true);
    }

    public function shouldTextAlert() {
        $this->alerts = [];

        if ($this->getCurrentCalloutCount() >= self::CALLOUT_LIMIT) {
            $this->alerts[] = "Reaching callout limit";
        }
        if (!$this->hasHadRecentHealthCheck()) {
            $this->alerts[] = "No recent healthcheck";
        }
        if (!$this->hasHadRecentPumping()) {
            $this->alerts[] = "No recent pumping";
        }

        return count($this->alerts) ? true : false;
    }

    public function getMessage() {
        return "[POOP ALERT!] " . implode(';', $this->alerts);
    }
}