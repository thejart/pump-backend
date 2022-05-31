<?php
require __DIR__ . '/lib/shit.class.php';

class ShitShow extends Shit {
    const FIFTEEN_MINUTES = 900;
    const THREE_MINUTES = 180;

    const BACKGROUND_OPTIONS = [
        self::EVENT_TYPE_STARTUP =>         "rgba(54, 162, 235, 0.4)",  // blue
        self::EVENT_TYPE_PUMPING =>         "rgba(139, 69, 19, 0.4)",   // brown
        self::EVENT_TYPE_WASHING_MACHINE => "rgba(97, 148, 49, 0.4)",   // green
        self::EVENT_TYPE_HEALTHCHECK =>     "rgba(201, 203, 207, 0.2)"  // grey
    ];
    const BORDER_OPTIONS = [
        self::EVENT_TYPE_STARTUP =>         "rgb(54, 162, 235)",    // blue
        self::EVENT_TYPE_PUMPING =>         "rgb(139, 69, 19)",     // brown
        self::EVENT_TYPE_WASHING_MACHINE => "rgb(97, 148, 49)",     // green
        self::EVENT_TYPE_HEALTHCHECK =>     "rgb(201, 203, 207)"    // grey
    ];

    /** @var int */
    protected $viewWindow;
    /** @var bool */
    protected $viewDeducedEvents;

    public function __construct() {
        parent::__construct();
        $this->viewWindow = $this->getRequestParam('days', 7);
        $this->viewDeducedEvents = $this->getRequestParam('deduced', true);
    }

    public function getChartData() {
        $startupData = [];
        $pumpingData = [];
        $healthcheckData = [];

        foreach ($this->getXDaysOfRecentEvents() as $event) {
            $graphedDatum = new stdClass();
            $graphedDatum->x = $event->timestamp;
            $graphedDatum->y = $this->getMaxAbsoluteValue($event);

            if ($event->type == ShitShow::EVENT_TYPE_STARTUP) {
                $startupData[] = $graphedDatum;
            } elseif ($event->type == ShitShow::EVENT_TYPE_PUMPING) {
                $pumpingData[] = $graphedDatum;
            } else {
                $healthcheckData[] = $graphedDatum;
            }
        }

        return [$startupData, $pumpingData, $healthcheckData];
    }

    public function getBackgroundColor($type) {
        return self::BACKGROUND_OPTIONS[$type];
    }

    public function getBorderColor($type) {
        return self::BORDER_OPTIONS[$type];
    }

    public function getViewWindow() {
        return $this->viewWindow;
    }

    public function getViewDeducedEvents() {
        return $this->viewDeducedEvents;
    }

    public function deduceWashingMachineEvents($events) {
        $skipEventCounter = 0;
        $pumpingEvents = [];
        $washingEvents = [];

        foreach ($events as $i => $event) {
            if ($skipEventCounter) {
                $skipEventCounter--;
                continue;
            }

            if (!isset($events[$i+2])) {
                $pumpingEvents[] = $event;
                continue;
            }

            // if event[i+2] is within 15 minutes of event[i] AND event[i+1] is within 3 minutes of event[i],
            // we probably have a washing machine event
            if (strtotime($events[$i+2]->x) - strtotime($event->x) <= self::FIFTEEN_MINUTES &&
                    strtotime($events[$i+1]->x) - strtotime($event->x) <= self::THREE_MINUTES) {
                $washingEvents[] = $event;
                $skipEventCounter = 2;
            } else {
                $pumpingEvents[] = $event;
            }
        }

        return [$pumpingEvents, $washingEvents];
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
$recentEpoch = $shitShow->getMostRecentEventsOfEachType()[ShitShow::EVENT_TYPE_STARTUP];
$calloutCount = $shitShow->getCurrentCalloutCount();
list($startupData, $pumpingData, $healthcheckData) = $shitShow->getChartData();
list($deducedPumpingData, $deducedWashingData) = $shitShow->deduceWashingMachineEvents($pumpingData);
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.6/dist/umd/popper.min.js" integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>

    <script type='text/javascript'>
      const hourFormat = 'MM/DD HH:mm';
      const dateFormat = 'ddd, MMM DD';
      const recentEpochString = moment("<?php echo $recentEpoch; ?>").fromNow();
      const title = 'Pump Events (Viewing <?php echo $shitShow->getViewWindow(); ?> days, Restarted ' + recentEpochString + ', Current request count: ' + <?php echo $calloutCount?> + ')';

      const startupData = <?php echo json_encode($startupData); ?>;
      const pumpingData = <?php echo json_encode($pumpingData); ?>;
      const healthcheckData = <?php echo json_encode($healthcheckData); ?>;

      const deducedPumpingData = <?php echo json_encode($deducedPumpingData); ?>;
      const deducedWashingData = <?php echo json_encode($deducedWashingData); ?>;

      const data = {
        datasets: [
          {
            label: 'Startup Signals',
            data: startupData,
            backgroundColor: "<?php echo $shitShow->getBackgroundColor(Shit::EVENT_TYPE_STARTUP); ?>",
            borderColor: "<?php echo $shitShow->getBorderColor(Shit::EVENT_TYPE_STARTUP); ?>",
            borderWidth: 1,
            barThickness: 5,
          },
          {
            label: 'Pumping Signals',
            data: <?php echo $shitShow->getViewDeducedEvents() ? 'deducedPumpingData' : 'pumpingData'; ?>,
            backgroundColor: "<?php echo $shitShow->getBackgroundColor(Shit::EVENT_TYPE_PUMPING); ?>",
            borderColor: "<?php echo $shitShow->getBorderColor(Shit::EVENT_TYPE_PUMPING); ?>",
            borderWidth: 1,
            barThickness: 10,
          }
        ]
      };
<?php if ($shitShow->getViewDeducedEvents()): ?>
      data.datasets.push(
          {
            label: 'Washing Machine Signals',
            data: deducedWashingData,
            backgroundColor: "<?php echo $shitShow->getBackgroundColor(Shit::EVENT_TYPE_WASHING_MACHINE); ?>",
            borderColor: "<?php echo $shitShow->getBorderColor(Shit::EVENT_TYPE_WASHING_MACHINE); ?>",
            borderWidth: 1,
            barThickness: 10,
          }
      );
<?php endif; ?>
      data.datasets.push(
          {
            label: 'Healthcheck Signals',
            data: healthcheckData,
            backgroundColor: "<?php echo $shitShow->getBackgroundColor(Shit::EVENT_TYPE_HEALTHCHECK); ?>",
            borderColor: "<?php echo $shitShow->getBorderColor(Shit::EVENT_TYPE_HEALTHCHECK); ?>",
            borderWidth: 1,
            barThickness: 20,
          }
      );

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

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.6/dist/umd/popper.min.js" integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>
  </body>
</html>
