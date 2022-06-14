<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/WipeCheck.class.php';

use Twilio\Rest\Client;

if (!isset($argv[1])) {
    echo "Usage: /path/to/php wipecheck.php /path/to/.env\n";
    exit(1);
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