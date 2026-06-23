# Pump Backend
So you've got yourself an Arduino board to monitor your evacuation pump and loaded the [pump monitor](https://github.com/thejart/pump-monitor) code on it? Congrats, that's half the equation! Now you need some backend code to monitor the monitor.

## Scripts Overview
- `flush.php` This is the endpoint that the pump monitor's HTTP request will hit. It's responsible for parsing out the query params, determining the type of request (startup, pumping or healthcheck) and inserting a row into a database table.
- `shitshow.php` This is an endpoint used to display recent requests in a graph format (see below for more info).
- `wipecheck.php` This is an optional script that should be cron'd. It will monitor recent usage and send a text message via [Textbelt](https://textbelt.com/) if any thresholds have been met.
- `vacation.php` This endpoint lets you set a "vacation end date" that suppresses the *no recent pumping* alert until that date passes (handy when nobody's home to fill the basin). The healthcheck and database-down alerts stay active so you still learn if the monitor itself dies while you're away. See below for usage.

## Getting Started
1. Get the [pump monitor](https://github.com/thejart/pump-monitor) setup
2. Setup a webserver with a relational database
3. Create a table for storing pump events (see below)
4. Clone this repo in a web directory
5. Create an .env file and lock it down (see below)

---
### Table Schema
```
CREATE TABLE `pump_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `x_value` double(4,2) NOT NULL,
  `y_value` double(4,2) NOT NULL,
  `z_value` double(4,2) NOT NULL,
  `type` int(11) NOT NULL,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
```

You also need a `vacation` table for the vacation-override feature (see `vacation.php` below):
```
CREATE TABLE `vacation` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `end_date` datetime NOT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
```
`end_date` is compared against the database server's `NOW()` (server-local time). Setting a new date archives any currently-active one (`is_archived`) rather than deleting it, so the table keeps a history of past vacations.

### .env file
The .env file contains all the personal data that needs to be kept out of source control. Make sure that it's readable by your webserver's user, but otherwise locked down (eg. `chown <youruser>:<webuser> .env && chmod 640 .env`).

**IMPORTANT:** Make sure this file isn't leaked to the world by your webserver!
```
<mysql database>
<mysql username>
<mysql password>
<pump/health call auth code>
<textbelt auth token>
<SMS (comma delimited) recipient numbers>
```

### vacation.php
Dispatches on an `action` query/POST param. Reading is public; setting and clearing require the same `authCode` used by the pump/health calls.
- `action=read` (default) Returns the current status as JSON: `{"active":bool,"end_date":string|null,"error":null}`.
- `action=set` Requires `authCode` and an `endDate` (`YYYY-MM-DD` is fine — set it to the day you *return*). Archives any active vacation and records the new one.
- `action=clear` Requires `authCode`. Soft-archives the active vacation.

```
curl 'https://your-host/vacation.php'
curl 'https://your-host/vacation.php?action=set&endDate=2026-07-15&authCode=<your auth code>'
curl 'https://your-host/vacation.php?action=clear&authCode=<your auth code>'
```

The `shitshow.php` dashboard also shows the current vacation status and provides a small form to set/clear it (you type the auth code into a password field; it's never embedded in the page). The form posts with `redirect=1`, which makes `vacation.php` redirect back to the dashboard instead of returning JSON.

### shitshow.php / Chart.js
The shitshow.php endpoint uses Chart.js to display recent events and accomodates a few optional GET parameters:
- `days` (Default: 7) Changes the number of days rendered in the chart
- `deduced` (Default: true) Toggles whether washing machine events are interpretted from the given pump events and displays them as a separate dataset. **Note:** This is admitedly *very* specific to my setup and should probably be 1) configurable and 2) not on by default, but hey, I'm the only one using this at the moment.

<img width="995" alt="image" src="https://user-images.githubusercontent.com/1659844/171009829-07affab9-a130-4471-92c3-644c3c40cca6.png">
