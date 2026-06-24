<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/WipeCheck.class.php';

if (!isset($argv[1])) {
    echo "Usage: /path/to/php /path/to/wipecheck.php /path/to/.env\n";
    exit(0);
}
$envFullyQualifiedPath = $argv[1];

$wipeCheck = new WipeCheck($envFullyQualifiedPath);
if ($wipeCheck->shouldText()) {
    // Note: textbelt.com's free key will only allow one text message per day (if that!)
    $wipeCheck->sendText($wipeCheck->getMessage());
}
