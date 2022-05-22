<?php

class ShitShow extends Shit {
  /** @var int */
  protected $viewWindow;

  public function __construct() {
    parent::__construct();

    $this->viewWindow = $this->getRequestParam('days', 7);
  }
  public function getViewWindow() {
    return $this->viewWindow;
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
