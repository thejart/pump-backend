<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/shit.class.php';

use Twilio\Rest\Client;

class ShitShow extends Shit {
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

$shitShow = new ShitShow();

if ($shitShow->shouldTextAlert()) {
  $client = new Client($shitShow->getAccountSid(), $shitShow->getAuthToken());

  $client->messages->create(
      $shitShow->getTextNumber(),
      array(
          'from' => $shitShow->getTwilioNumber(),
          'body' => $shitShow->getMessage()
      )
  );
}

