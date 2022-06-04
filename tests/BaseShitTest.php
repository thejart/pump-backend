<?php
require __DIR__ . '/../lib/BaseShit.class.php';
use PHPUnit\Framework\TestCase;

final class BaseShitTest extends TestCase
{
    private $envFile = '.env.testing';

    public function test_constructor_hasNoTwilioSecrets() {
        $baseShit = new BaseShit($this->envFile);

        $this->assertNull($baseShit->getTwilioNumber(), "twilio number should be null");
        $this->assertNull($baseShit->getTextNumber(), "text number should be null");
        $this->assertNull($baseShit->getAuthToken(), "auth token should be null");
        $this->assertNull($baseShit->getAccountSid(), "account sid should be null");
    }

    public function test_constructor_hasTwilioSecrets() {
        $baseShit = new BaseShit($this->envFile, true);

        $this->assertNotNull($baseShit->getTwilioNumber(), "twilio number should not be null");
        $this->assertNotNull($baseShit->getTextNumber(), "text number should not be null");
        $this->assertNotNull($baseShit->getAuthToken(), "auth token should not be null");
        $this->assertNotNull($baseShit->getAccountSid(), "account sid should not be null");
    }
}