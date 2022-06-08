<?php
require __DIR__ . '/../lib/WipeCheck.class.php';
use PHPUnit\Framework\TestCase;

final class WipeCheckTest extends TestCase
{
    private $envFile = '.env.testing';

    public function test_constructor_hasTwilioSecrets() {
        $wipeCheck = new WipeCheck($this->envFile);

        $this->assertNotNull($wipeCheck->getTwilioNumbers(), "twilio number should not be null");
        $this->assertNotNull($wipeCheck->getTextNumber(), "text number should not be null");
        $this->assertNotNull($wipeCheck->getAuthToken(), "auth token should not be null");
        $this->assertNotNull($wipeCheck->getAccountSid(), "account sid should not be null");
    }

    public function test_getMessage() {
        $wipeCheck = new WipeCheck($this->envFile);

        $this->assertEquals("[POOP ALERT!] ", $wipeCheck->getMessage(), "message didn't match expected");
    }

    // TODO: test shouldTextAlert()
}