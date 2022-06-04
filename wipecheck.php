<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/WipeCheck.class.php';

use Twilio\Rest\Client;

$wipeCheck = new WipeCheck();
if ($wipeCheck->shouldTextAlert()) {
    $client = new Client($wipeCheck->getAccountSid(), $wipeCheck->getAuthToken());

    $client->messages->create(
        $wipeCheck->getTextNumber(), [
            'from' => $wipeCheck->getTwilioNumber(),
            'body' => $wipeCheck->getMessage()
        ]
    );
}