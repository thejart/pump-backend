<?php
require __DIR__ . '/../lib/ShitPumper.class.php';
use PHPUnit\Framework\TestCase;

final class ShitPumperTest extends TestCase
{
    private $envFile = '.env.testing';

    public function test_constructor_hasNoTwilioSecrets() {
        $shitPumper = new ShitPumper($this->envFile);

        $this->assertNull($shitPumper->getTwilioNumbers(), "twilio number should be null");
        $this->assertNull($shitPumper->getTextNumber(), "text number should be null");
        $this->assertNull($shitPumper->getAuthToken(), "auth token should be null");
        $this->assertNull($shitPumper->getAccountSid(), "account sid should be null");
    }
}