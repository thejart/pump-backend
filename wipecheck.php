<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/WipeCheck.class.php';

use Twilio\Rest\Client;

$wipeCheck = new WipeCheck();
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