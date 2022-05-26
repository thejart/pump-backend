<?php
require __DIR__ . '/lib/shit.class.php';

class ShitShow extends Shit {
    const BACKGROUND_OPTIONS = [
        ShitShow::STARTUP => "rgba(54, 162, 235, 1)",       // blue
        ShitShow::PUMPING => "rgba(139, 69, 19, 0.4)",      // brown
        ShitShow::HEALTHCHECK => "rgba(201, 203, 207, 0.2)" // grey
    ];
    const BORDER_OPTIONS = [
        ShitShow::STARTUP => "rgb(54, 162, 235)",     // blue
        ShitShow::PUMPING => "rgb(139, 69, 19)",      // brown
        ShitShow::HEALTHCHECK => "rgb(201, 203, 207)" // grey
    ];

    /** @var int */
    protected $viewWindow;

    public function __construct() {
        parent::__construct();
        $this->viewWindow = $this->getRequestParam('days', 7);
    }

    public function getChartData() {
        $startupData = [];
        $pumpingData = [];
        $healthcheckData = [];

        foreach ($this->getXDaysOfRecentEvents() as $event) {
            $graphedDatum = new stdClass();
            $graphedDatum->x = $event->timestamp;
            $graphedDatum->y = $this->getMaxAbsoluteValue($event);

            if ($event->type == ShitShow::STARTUP) {
                $startupData[] = $graphedDatum;
            } elseif ($event->type == ShitShow::PUMPING) {
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
}

/**
 * Other ideas:
 * - Allow time range selection in UI
 * - Display warnings if:
 *   - Healthcheck hasn't happened in the past 12 hours
 *   - Pumping hasn't happened in X days(?)
 */
$shitShow = new ShitShow();
$recentEpoch = $shitShow->getMostRecentEventsOfEachType()[ShitShow::STARTUP];
$calloutCount = $shitShow->getCurrentCalloutCount();
list($startupData, $pumpingData, $healthcheckData) = $shitShow->getChartData();
?>

<html>
  <head>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.18.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-adapter-moment/1.0.0/chartjs-adapter-moment.js"></script>
    <script type='text/javascript'>
      const hourFormat = 'MM/DD HH:mm';
      const dateFormat = 'ddd, MMM DD';
      const recentEpochString = moment("<?php echo $recentEpoch; ?>").fromNow();
      const title = 'Pump Events (Viewing <?php echo $shitShow->getViewWindow(); ?> days, Restarted ' + recentEpochString + ', Current request count: ' + <?php echo $calloutCount?> + ')';

      const startupData = <?php echo json_encode($startupData); ?>;
      const pumpingData = <?php echo json_encode($pumpingData); ?>;
      const healthcheckData = <?php echo json_encode($healthcheckData); ?>;

      const data = {
        datasets: [
          {
            label: 'Startup Signals',
            data: startupData,
            backgroundColor: "<?php echo $shitShow->getBackgroundColor([ShitShow::STARTUP]); ?>",
            borderColor: "<?php echo $shitShow->getBorderColor([ShitShow::STARTUP]); ?>",
            borderWidth: 1,
            barThickness: 5,
          },
          {
            label: 'Pumping Signals',
            data: pumpingData,
            backgroundColor: "<?php echo $shitShow->getBackgroundColor([ShitShow::PUMPING]); ?>",
            borderColor: "<?php echo $shitShow->getBorderColor([ShitShow::PUMPING]); ?>",
            borderWidth: 1,
            barThickness: 10,
          },
          {
            label: 'Healthcheck Signals',
            data: healthcheckData,
            backgroundColor: "<?php echo $shitShow->getBackgroundColor([ShitShow::HEALTHCHECK]); ?>",
            borderColor: "<?php echo $shitShow->getBorderColor([ShitShow::HEALTHCHECK]); ?>",
            borderWidth: 1,
            barThickness: 20,
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