<?php
require_once __DIR__ . '/../lib/ShitShow.class.php';
use PHPUnit\Framework\TestCase;

final class ShitShowTest extends TestCase
{
    private $envFile = '.env.testing';

    public function test_constructor_hasNoTwilioSecrets() {
        $shitShow = new ShitShow($this->envFile);

        $this->assertNull($shitShow->getTwilioNumber(), "twilio number should be null");
        $this->assertNull($shitShow->getTextNumbers(), "text number should be null");
        $this->assertNull($shitShow->getAuthToken(), "auth token should be null");
        $this->assertNull($shitShow->getAccountSid(), "account sid should be null");
    }
}