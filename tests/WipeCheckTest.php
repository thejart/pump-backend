<?php
require_once __DIR__ . '/../lib/WipeCheck.class.php';
use PHPUnit\Framework\TestCase;

final class WipeCheckTest extends TestCase
{
    private $envFile = '.env.testing';

    public function test_constructor_hasTextingSecrets() {
        $wipeCheck = new WipeCheck($this->envFile);

        $this->assertNotNull($wipeCheck->getTextbeltToken(), "textbelt token should not be null");
        $this->assertNotNull($wipeCheck->getTextNumbers(), "text number should not be null");
    }

    public function test_getMessage() {
        $wipeCheck = new WipeCheck($this->envFile);

        $this->assertEquals("[poop summary]\n", $wipeCheck->getMessage(), "message didn't match expected");
    }

    // TODO: test shouldText()
}