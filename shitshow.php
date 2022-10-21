<?php
require_once __DIR__ . '/lib/ShitShow.class.php';

/**
 * Other ideas:
 * - Allow time range selection in UI
 * - Display warnings if:
 *   - Healthcheck hasn't happened in the past 12 hours
 *   - Pumping hasn't happened in X days(?)
 */
$shitShow = new ShitShow('.env');
$recentEpoch = $shitShow->getMostRecentEventsOfEachType()[ShitShow::EVENT_TYPE_STARTUP];
$calloutCount = $shitShow->getCalloutCountSinceReboot();
list($startupData, $pumpingData, $healthcheckData) = $shitShow->getChartData();
list($deducedPumpingData, $deducedWashingData) = $shitShow->deduceWashingMachineEvents($pumpingData);

if ($shitShow->isMysqlDown) {
    echo "MySQL is down!";
    exit;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.18.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-adapter-moment/1.0.0/chartjs-adapter-moment.js"></script>

    <script type='text/javascript'>
      const hourFormat = 'MM/DD HH:mm';
      const dateFormat = 'ddd, MMM DD';
      const recentEpochString = moment('<?php echo $recentEpoch; ?>').fromNow();
      const viewingWindowString = '<?php echo ($shitShow->getViewWindow() > 0) ? "Viewing " . $shitShow->getViewWindow() . " days, " : ""; ?>';
      const title = 'Pump Events (' + viewingWindowString + 'Restarted ' + recentEpochString + ', Current request count: ' + <?php echo $calloutCount?> + ')';

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
            backgroundColor: "<?php echo $shitShow->getBackgroundColor(ShitShow::EVENT_TYPE_STARTUP); ?>",
            borderColor: "<?php echo $shitShow->getBorderColor(ShitShow::EVENT_TYPE_STARTUP); ?>",
            borderWidth: 1,
            barThickness: 5,
          },
          {
            label: 'Pumping Signals',
            data: <?php echo $shitShow->getViewDeducedEvents() ? 'deducedPumpingData' : 'pumpingData'; ?>,
            backgroundColor: "<?php echo $shitShow->getBackgroundColor(ShitShow::EVENT_TYPE_PUMPING); ?>",
            borderColor: "<?php echo $shitShow->getBorderColor(ShitShow::EVENT_TYPE_PUMPING); ?>",
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
            backgroundColor: "<?php echo $shitShow->getBackgroundColor(ShitShow::EVENT_TYPE_WASHING_MACHINE); ?>",
            borderColor: "<?php echo $shitShow->getBorderColor(ShitShow::EVENT_TYPE_WASHING_MACHINE); ?>",
            borderWidth: 1,
            barThickness: 10,
          }
      );
<?php endif; ?>
      data.datasets.push(
          {
            label: 'Healthcheck Signals',
            data: healthcheckData,
            backgroundColor: "<?php echo $shitShow->getBackgroundColor(ShitShow::EVENT_TYPE_HEALTHCHECK); ?>",
            borderColor: "<?php echo $shitShow->getBorderColor(ShitShow::EVENT_TYPE_HEALTHCHECK); ?>",
            borderWidth: 1,
            barThickness: 20,
          }
      );

      const config = {
        type: 'bar',
        data: data,
        options: {
          aspectRatio: 1.5,
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
    <div class="chart-container" style="position:relative; height:80vh; width:100vw; padding-left:10px; padding-right:10px;">
      <canvas id="pumpCanvas"></canvas>
    </div>
    <div class="navbar fixed-bottom">
      <a class="btn btn-outline-primary" href="<?php echo $shitShow->getFilename(); ?>" role="button">Reset</a>
      <a class="btn btn-outline-primary" href="<?php echo $shitShow->getFilename(); ?>?deduced=0" role="button">Raw Data</a>
      <a class="btn btn-outline-primary" href="<?php echo $shitShow->getFilename(); ?>?days=1" role="button">1 Day</a>
      <a class="btn btn-outline-primary" href="<?php echo $shitShow->getFilename(); ?>?days=-1" role="button">Full cycle</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.6/dist/umd/popper.min.js" integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>
  </body>
</html>
