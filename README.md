# Pump Backend
So you've got yourself an Arduino board to monitor your evacuation pump and loaded the [pump monitor](https://github.com/thejart/pump-monitor) code on it. Congrats, that's half the equation! Now you need some backend code to monitor the monitor.

## Scripts Overview
- `shitty.php` This is the endpoint that the pump monitor's HTTP request will hit. It's responsible for parsing out the query params, determining the type of request (startup, pumping or healthcheck) and inserting a row into a database table.
- `shitshow.php` This is an endpoint used to display recent requests in a graph format (see below for more info).
- `wipecheck.php` This is an optional script that should be cron'd which will monitor recent usage and send a text message via Twilio (Note: You'll need a valid account, which they offer for free) if any thresholds have been met.

## Getting Started
1. Get the [pump monitor](https://github.com/thejart/pump-monitor) setup
2. Setup a webserver with a relational database
3. Create a table for storing pump events (see below)
4. Clone this repo in a web directory

---
### Table Schema
```
CREATE TABLE `pump_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `x_value` double(3,2) NOT NULL,
  `y_value` double(3,2) NOT NULL,
  `z_value` double(3,2) NOT NULL,
  `type` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
```

### secrets file
The secrets file contains all the personal data that needs to be kept out of source control. Make sure that it's readable by your webserver's user, but otherwise locked down.
```
<mysql database>
<mysql username>
<mysql password>
<twilio account sid>
<twilio auth token>
<twilio number>
<SMS number>
```

### shitshow.php / Chart.js
The shitshow.php endpoint uses Chart.js to display recent events. An optional GET parameter, `days` can be added to change the view of the chart.

<img width="799" alt="image" src="https://user-images.githubusercontent.com/1659844/169703906-0fe0fb0c-fb6f-4f5b-80de-666e4190048d.png">
