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
if ($shitShow->isMysqlDown) {
    echo "MySQL is down!";
    exit;
}

$recentEpoch = $shitShow->getMostRecentEventsOfEachType()[ShitShow::EVENT_TYPE_STARTUP];
$calloutCount = $shitShow->getCalloutCountSinceReboot();
list($startupData, $pumpingData, $healthcheckData, $start, $end) = $shitShow->getChartData();
list($deducedPumpingData, $deducedWashingData) = $shitShow->deduceWashingMachineEvents($pumpingData);

$activeVacationEndDate = $shitShow->getActiveVacationEndDate();

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="icon" type="image/x-icon" href="favicon.ico">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/css/bootstrap.min.css" integrity="sha384-GJzZqFGwb1QTTN6wy59ffF1BuGJpLSa9DkKMp0DgiMDm4iYMj70gZWKYbI706tWS" crossorigin="anonymous">

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/hammer.js/2.0.8/hammer.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-zoom/1.2.1/chartjs-plugin-zoom.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.18.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-adapter-moment/1.0.0/chartjs-adapter-moment.js"></script>

    <script type='text/javascript'>
      let myChart;

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
            },
            zoom: {
              limits: {
                x: {min: <?php echo $start?>, max: <?php echo $end?>, minRange: 86400000},
                y: {min: 0, max: 12, minRange: 12}
              },
              zoom: {
                mode: 'x',
                wheel: {
                  enabled: true
                },
                pinch: {
                  enabled: true
                },
                drag: {
                  enabled: true,
                  modifierKey: 'shift'
                }
              },
              pan: {
                enabled: true,
                mode: 'x'
              }
            }
          },
          scales: {
            y: {
              min: 0,
              max: 12,
            },
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
        }
      };

      window.onload = function() {
        myChart = new Chart(
          document.getElementById('pumpCanvas'),
          config
        );
      }

      function zoomIt() {
        myChart.zoomScale('x', {min: 1666497600000, max: <?php echo $end ?>}, 'default');
        //myChart.zoomScale('x', {min: 1666497600000, max: 1667102400000}, 'default');
        return true;
      }
    </script>
  </head>

  <body style="padding-top: 60px;">
    <div class="navbar fixed-top bg-light justify-content-end">
      <div class="form-inline">
<?php if (!is_null($activeVacationEndDate)): ?>
        <span class="badge badge-info mr-2">&#127958; On vacation until <?php echo date("M jS", strtotime($activeVacationEndDate)); ?></span>
<?php else: ?>
        <span class="badge badge-light mr-2">No active vacation</span>
<?php endif; ?>
        <input id="vacationDate" class="form-control form-control-sm mr-1" type="date">
        <button id="vacationSet" class="btn btn-sm btn-outline-success mr-1" type="button">Set</button>
        <button id="vacationClear" class="btn btn-sm btn-outline-danger" type="button">Clear</button>
      </div>
    </div>
    <div class="chart-container" style="position:relative; height:80vh; width:100vw; padding-left:10px; padding-right:10px;">
      <canvas id="pumpCanvas"></canvas>
    </div>
    <div class="navbar fixed-bottom">
      <a class="btn btn-outline-primary" href="<?php echo $shitShow->getFilename(); ?>" role="button">Reset</a>
      <a class="btn btn-outline-primary" href="<?php echo $shitShow->getFilename(); ?>?deduced=0" role="button">Raw Data</a>
      <a class="btn btn-outline-primary" href="<?php echo $shitShow->getFilename(); ?>?days=1" role="button">1 Day</a>
      <a class="btn btn-outline-primary" href="#" onclick="return zoomIt();" role="button">Zoom it</a>
      <!--
      <a class="btn btn-outline-primary" href="<?php echo $shitShow->getFilename(); ?>?days=-1" role="button">Full cycle</a>
      -->
    </div>

    <div class="modal fade" id="otpModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirm via text message</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body">
            <p id="otpMessage">A confirmation code was texted to you. Enter it below.</p>
            <input id="otpCode" class="form-control" type="text" inputmode="numeric" maxlength="6" placeholder="6-digit code" autocomplete="off">
            <div id="otpError" class="text-danger mt-2" style="display:none;"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            <button id="otpConfirm" type="button" class="btn btn-primary">Confirm</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.14.6/dist/umd/popper.min.js" integrity="sha384-wHAiFfRlMFy6i5SRaxvfOCifBUQy1xHdJ/yoi7FRNXMRBu5WHdZYu1hA6ZOblgut" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.1/dist/js/bootstrap.min.js" integrity="sha384-B0UglyR+jN6CkvvICOB2joaf5I4l3gm9GU6Hc1og6Ls7i6U/mkkaduKaBhlAXv9k" crossorigin="anonymous"></script>
    <script type="text/javascript">
      (function() {
        function postVacation(params) {
          return fetch('vacation.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(params).toString()
          }).then(function(response) { return response.json(); });
        }

        // "Set"/"Clear" don't mutate anything directly — they ask the backend to text a one-time
        // code, then the modal collects it and confirms the pending change.
        function requestCode(op) {
          var params = {action: 'request', op: op};
          if (op === 'set') {
            var chosenDate = document.getElementById('vacationDate').value;
            if (!chosenDate) { alert('Pick a date first.'); return; }
            params.endDate = chosenDate;
          }
          postVacation(params).then(function(result) {
            if (!result.ok) { alert(result.error || 'Unable to send a code.'); return; }
            document.getElementById('otpCode').value = '';
            document.getElementById('otpError').style.display = 'none';
            document.getElementById('otpMessage').textContent = result.message || 'A confirmation code was texted to you. Enter it below.';
            $('#otpModal').modal('show');
          }).catch(function() { alert('Network error. Please try again.'); });
        }

        document.getElementById('vacationSet').addEventListener('click', function() { requestCode('set'); });
        document.getElementById('vacationClear').addEventListener('click', function() {
          if (confirm('Clear the active vacation?')) { requestCode('clear'); }
        });
        document.getElementById('otpConfirm').addEventListener('click', function() {
          var code = document.getElementById('otpCode').value.trim();
          postVacation({action: 'confirm', code: code}).then(function(result) {
            if (result.ok) { window.location.reload(); return; }
            var errorBox = document.getElementById('otpError');
            errorBox.textContent = result.error || 'Invalid code.';
            errorBox.style.display = 'block';
          }).catch(function() { alert('Network error. Please try again.'); });
        });
      })();
    </script>
  </body>
</html>
