<?php
require __DIR__ . '/vendor/autoload.php';
use Twilio\Rest\Client;

class ShitShow {
  const STARTUP = 1;
  const PUMPING = 2;
  const HEALTHCHECK = 3;

  const CALLOUT_LIMIT = 250;        // the nano 33 iot seems to crap out around 300 HTTP callouts
  const HEALTHCHECK_THRESHOLD = 13; // hours (i.e. the healthcheck should occur every 12 hours, plus some wiggle room)
  const PUMPING_THRESHOLD = 3;      // days (i.e. there should be a pumping event every 3 days under normal circumstances)

  /** @var PDO */
  protected $pdo;
  /** @var array */
  private $alerts = [];

  public function __construct() {
    list($mysqlDatabase, $mysqlUsername, $mysqlPassword) = $this->setupEnvironment();

    $this->pdo = new PDO("mysql:host=127.0.0.1;dbname=". $mysqlDatabase, $mysqlUsername, $mysqlPassword);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  public function setupEnvironment()
  {
      try {
          $environmentFile = file_get_contents('secrets');
      } catch (Exception $e) {
          throw new Exception('Unable to read in environment file :'. $e->getMessage());
      }
      return explode("\n", $environmentFile);
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

  protected function getCurrentCalloutCount() {
    $query = $this->pdo->prepare("
      SELECT COUNT(*) AS count
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
}

$shitShow = new ShitShow();

if ($shitShow->shouldTextAlert()) {
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

