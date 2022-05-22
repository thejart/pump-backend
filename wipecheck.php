<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/shit.class.php';

use Twilio\Rest\Client;

class ShitShow extends Shit {
  /** @var array */
  private $alerts = [];

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
    // TODO: This method shouldn't be public, so do this differently
  list(,,,$account_sid, $auth_token, $twilio_number, $text_number) = $shitShow->setupEnvironment();
  $client = new Client($account_sid, $auth_token);

  $client->messages->create(
      // Where to send a text message (your cell phone?)
      $text_number,
      array(
          'from' => $twilio_number,
          'body' => $shitShow->getMessage()
      )
  );
}

