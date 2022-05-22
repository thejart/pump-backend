<?php
/**
 * ## HTTP ENDPOINT
 * 1. Parse xyz values, request type & timestamp
 * 2. Insert into database
 *
 * ## CRONJOB SCRIPT (DAILY?)
 * 1. Query out how many pumps have occurred over the last 24 hours
 * 2. Send SMS with findings
 *   a. [INFO: 3 pumps in the last day]
 *   b. [ALERT: 0 pumps in the last day!]
 *   b. [EMERGENCY: 0 healthchecks in the last day!]
 *
 * create table pump_events (
 *   id bigint NOT NULL AUTO_INCREMENT,
 *   x_value double(3,2) NOT NULL,
 *   y_value double(3,2) NOT NULL,
 *   z_value double(3,2) NOT NULL,
 *   type int NOT NULL,
 *   timestamp datetime NOT NULL,
 *   PRIMARY KEY (id),
 *   UNIQUE KEY (timestamp)
 * )
 *
 * type {
 *   1 : STARTUP,
 *   2 : PUMPING,
 *   3 : HEALTHCHECK
 * }
 *
 * eg. x=0.00, y=0.00, z=0.00, healthcheck=1   === STARTUP (1)
 *     x=-0.12, y=-5.00, z=-1.46, shitstorm=1  === PUMPING (2)
 *     x=1.83, y=-2.62, z=-2.14, healthcheck=1 === HEALTHCHECK (3)
 **/

class ShitPumper {
  const STARTUP = 1;
  const PUMPING = 2;
  const HEALTHCHECK = 3;

  /** @var PDO */
  protected $pdo;

  protected $xValue;
  protected $yValue;
  protected $zValue;
  protected $type;

  public function __construct() {
    list($mysqlDatabase, $mysqlUsername, $mysqlPassword) = $this->setupEnvironment();

    $this->pdo = new PDO("mysql:host=127.0.0.1;dbname=". $mysqlDatabase, $mysqlUsername, $mysqlPassword);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $this->parseRequestParams();
  }

  protected function setupEnvironment()
  {
      try {
          $environmentFile = file_get_contents('secrets');
      } catch (Exception $e) {
          throw new Exception('Unable to read in environment file :'. $e->getMessage());
      }
      return explode("\n", $environmentFile);
  }

  public function processInsertionRequest() {
    $query = $this->pdo->prepare("
      INSERT INTO pump_events
      (x_value, y_value, z_value, type, timestamp)
      values (:x_value, :y_value, :z_value, :type, now())
    ");

    $query->execute([
      ':x_value' => $this->xValue,
      ':y_value' => $this->yValue,
      ':z_value' => $this->zValue,
      ':type' => $this->type
    ]);

    if ($query->rowCount()) {
      return true;
    }

    return false;
  }

  protected function getRequestParam($field, $default = null) {
    return isset($_REQUEST[$field]) ? $_REQUEST[$field] : $default;
  }

  protected function parseRequestParams() {
    $this->xValue = (float)$this->getRequestParam('x');
    $this->yValue = (float)$this->getRequestParam('y');
    $this->zValue = (float)$this->getRequestParam('z');

    if ($this->getRequestParam('shitstorm')) {
      $this->type = self::PUMPING;
    //} elseif ((bool)$this->getRequestParam('healthcheck', false)) {
    } else {
      if ($this->xValue > -0.009 && $this->xValue < 0.001 && $this->yValue > -0.009 && $this->yValue < 0.001 && $this->zValue > -0.009 && $this->zValue < 0.001) {
        $this->type = self::STARTUP;
      } else {
        $this->type = self::HEALTHCHECK;
      }
    }
  }
}

$shitpumper = new ShitPumper();
if ($shitpumper->processInsertionRequest()) {
  echo ":thumbsup:";
} else {
  echo ":poop:";
}
