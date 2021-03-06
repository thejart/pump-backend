<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../lib/WipeCheck.class.php';

use Twilio\Rest\Client;

if (!isset($argv[1])) {
    echo "Usage: /path/to/php /path/to/wipecheck.php /path/to/.env\n";
    exit(0);
}
$envFullyQualifiedPath = $argv[1];

$wipeCheck = new WipeCheck($envFullyQualifiedPath);
if ($wipeCheck->shouldTextAlert()) {
    $client = new Client($wipeCheck->getAccountSid(), $wipeCheck->getAuthToken());

    foreach ($wipeCheck->getTextNumbers() as $textNumber) {
        $client->messages->create(
            $textNumber, [
                'from' => $wipeCheck->getTwilioNumber(),
                'body' => $wipeCheck->getMessage()
            ]
        );
    }
}