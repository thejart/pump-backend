<?php
require_once __DIR__ . '/../lib/Flush.class.php';
require_once __DIR__ . '/lib/FunctionalHelper.class.php';
use PHPUnit\Framework\TestCase;

final class FlushTest extends TestCase
{
    private $envFile = '.env.testing';
    private $helper;

    protected function setUp(): void {
        parent::setUp();

        if (!$this->helper) {
            $parsedEnvFile = file_get_contents($this->envFile);
            list($mysqlDatabase, $mysqlUsername, $mysqlPassword) = explode("\n", $parsedEnvFile);
            $this->helper = new FunctionalHelper($mysqlDatabase, $mysqlUsername, $mysqlPassword);
        }
    }

    protected function tearDown(): void {
        parent::tearDown();

        $this->helper->deleteAllEvents();
    }

    public function test_constructor_hasNoTextingSecrets() {
        $shitPumper = new Flush($this->envFile);

        $this->assertNull($shitPumper->getTextbeltToken(), "textbelt token should be null");
        $this->assertNull($shitPumper->getTextNumbers(), "text number should be null");
    }

    public function test_insertCurrentPumpEvent_nonEvent() {
        $this->assertEquals(0, $this->helper->getTotalNumberOfEvents(), 'there should be zero events in the database');

        $shit = new Flush($this->envFile);
        $shit->insertCurrentPumpEvent();

        $this->assertEquals(0, $this->helper->getTotalNumberOfEvents(), 'there should be zero events in the database');
    }
}