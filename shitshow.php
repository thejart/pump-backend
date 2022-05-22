<?php

class ShitShow {
  const STARTUP = 1;
  const PUMPING = 2;
  const HEALTHCHECK = 3;

  /** @var PDO */
  protected $pdo;
  /** @var int */
  protected $viewWindow;

  public function __construct() {
    list($mysqlDatabase, $mysqlUsername, $mysqlPassword) = $this->setupEnvironment();

    $this->pdo = new PDO("mysql:host=127.0.0.1;dbname=". $mysqlDatabase, $mysqlUsername, $mysqlPassword);
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $this->viewWindow = $this->getRequestParam('days', 7);
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

  public function getMostRecentsOfEachType() {
    $results = [];
    $types = [self::STARTUP, self::PUMPING, self::HEALTHCHECK];

    foreach ($types as $type) {
      $query = $this->pdo->prepare("
        SELECT type, timestamp FROM pump_events
        WHERE type=:type
        ORDER BY timestamp DESC
        LIMIT 1
      ");

      $query->execute([':type' => $type]);
      $result = $query->fetchAll(PDO::FETCH_OBJ);
      $results[$type] = $result[0]->timestamp;
    }

    return $results;
  }

  public function getDaysOfRecentEvents() {
    $query = $this->pdo->prepare("
      SELECT id, x_value, y_value, z_value, type, timestamp FROM  pump_events
      WHERE timestamp > DATE_SUB(NOW(), INTERVAL {$this->viewWindow} DAY)
      ORDER BY timestamp
    ");

    $query->execute();
    return $query->fetchAll(PDO::FETCH_OBJ);
  }

  public function getCurrentCalloutCount() {
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

  public function getMaxAbsoluteValue($event) {
    // Pumping events have a threshold of 5, so arbitrarily setting these event types well below that
    // creates a visual distinction (in addition to the different colors)
    if ($event->type == self::STARTUP) {
      return 2;
    } elseif ($event->type == self::HEALTHCHECK) {
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

  public function getViewWindow() {
    return $this->viewWindow;
  }

  protected function getRequestParam($field, $default = null) {
    return isset($_REQUEST[$field]) ? $_REQUEST[$field] : $default;
  }
}

/**
 * Other ideas:
 * - Allow time range selection in UI
 * - Display warnings if:
 *   - Healthcheck hasn't happened in the past 12 hours
 *   - Pumping hasn't happened in X days(?)
 */
$shitShow = new ShitShow();
$events = $shitShow->getDaysOfRecentEvents();
$recentEpoch = $shitShow->getMostRecentsOfEachType()[ShitShow::STARTUP];
$calloutCount = $shitShow->getCurrentCalloutCount();

$backgroundOptions = [
  ShitShow::STARTUP => "rgba(75, 192, 192, 0.2)",     // green
  ShitShow::PUMPING => "rgba(54, 162, 235, 0.2)",     // blue
  ShitShow::HEALTHCHECK => "rgba(201, 203, 207, 0.2)" // grey
];
$borderOptions = [
  ShitShow::STARTUP => "rgb(75, 192, 192)",     // green
  ShitShow::PUMPING => "rgb(54, 162, 235)",     // blue
  ShitShow::HEALTHCHECK => "rgb(201, 203, 207)" // grey
];

$startupData = [];
$pumpingData = [];
$healthcheckData = [];
foreach ($events as $event) {
  $eventObject = new stdClass();
  $eventObject->x = $event->timestamp;
  $eventObject->y = $shitShow->getMaxAbsoluteValue($event);

  if ($event->type == ShitShow::STARTUP) {
    $startupData[] = $eventObject;
  } elseif ($event->type == ShitShow::PUMPING) {
    $pumpingData[] = $eventObject;
  } else {
    $healthcheckData[] = $eventObject;
  }
}
?>

<html>
  <head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.18.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-adapter-moment/1.0.0/chartjs-adapter-moment.js"></script>
    <script type='text/javascript'>
      const hourFormat = 'MM/DD HH:mm';
      const dateFormat = 'ddd, MMM DD';
      const recentEpoch = moment("<?php echo $recentEpoch; ?>").fromNow();
      const title = 'Pump Events (Viewing <?php echo $shitShow->getViewWindow(); ?> days, Restarted ' + recentEpoch + ', Current request count: ' + <?php echo $calloutCount?> + ')';

      const startupData = <?php echo json_encode($startupData); ?>;
      const pumpingData = <?php echo json_encode($pumpingData); ?>;
      const healthcheckData = <?php echo json_encode($healthcheckData); ?>;

      const data = {
        datasets: [
          {
            label: 'Startup Signals',
            data: startupData,
            backgroundColor: "<?php echo $backgroundOptions[ShitShow::STARTUP]; ?>",
            backgroundColor: "<?php echo $borderOptions[ShitShow::STARTUP]; ?>",
            borderWidth: 1,
            barThickness: 15,
          },
          {
            label: 'Pumping Signals',
            data: pumpingData,
            backgroundColor: "<?php echo $backgroundOptions[ShitShow::PUMPING]; ?>",
            backgroundColor: "<?php echo $borderOptions[ShitShow::PUMPING]; ?>",
            borderWidth: 1,
            barThickness: 10,
          },
          {
            label: 'Healthcheck Signals',
            data: healthcheckData,
            backgroundColor: "<?php echo $backgroundOptions[ShitShow::HEALTHCHECK]; ?>",
            backgroundColor: "<?php echo $borderOptions[ShitShow::HEALTHCHECK]; ?>",
            borderWidth: 1,
            barThickness: 30,
          },
        ]
      };

      const config = {
        type: 'bar',
        data: data,
        options: {
          plugins: {
            title: {
              display: true,
              text: title
            }
          },
          scales: {
            x: {
              type: 'time',
              time: {
                unit: 'day',
                displayFormats: {
                  hour: hourFormat,
                  day: dateFormat
                }
              }
            }
          }
        },
      };

      window.onload = function() {
        const myChart = new Chart(
          document.getElementById('pumpCanvas'),
          config
        );
      }
    </script>
  </head>

  <body>
    <div class="chart-container" style="position: relative; height: 40vh; width: 80vw">
      <canvas id="pumpCanvas"></canvas>
    </div>
  </body>
</html>
