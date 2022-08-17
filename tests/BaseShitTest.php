<?php
require_once __DIR__ . '/../lib/BaseShit.class.php';
require_once __DIR__ . '/lib/FunctionalHelper.class.php';
use PHPUnit\Framework\TestCase;

final class BaseShitTest extends TestCase
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

    public function test_constructor_hasNoTwilioSecrets() {
        $baseShit = new BaseShit($this->envFile);

        $this->assertNull($baseShit->getTwilioNumber(), "twilio number should be null");
        $this->assertNull($baseShit->getTextNumbers(), "text number should be null");
        $this->assertNull($baseShit->getAuthToken(), "auth token should be null");
        $this->assertNull($baseShit->getAccountSid(), "account sid should be null");
    }

    public function test_constructor_hasTwilioSecrets() {
        $baseShit = new BaseShit($this->envFile, true);

        $this->assertNotNull($baseShit->getTwilioNumber(), "twilio number should not be null");
        $this->assertNotNull($baseShit->getTextNumbers(), "text number should not be null");
        $this->assertNotNull($baseShit->getAuthToken(), "auth token should not be null");
        $this->assertNotNull($baseShit->getAccountSid(), "account sid should not be null");
    }

    public function test_insertPumpEvent() {
        $shit = new BaseShit($this->envFile);

        // There should initially be ZERO events in the database
        $this->assertEquals(0, $this->helper->getTotalNumberOfEvents(), 'the event count does not match expected');

        $result = $shit->insertPumpEvent(1, 2, 3, BaseShit::EVENT_TYPE_STARTUP, $this->helper->unixTimestampToDbFormat(strtotime('-1 hour')));
        $this->assertTrue($result, 'the insertion should have been successful');
        $this->assertEquals(1, $this->helper->getTotalNumberOfEvents(), 'the event count does not match expected');

        $result = $shit->insertPumpEvent(1, 2, 3, BaseShit::EVENT_TYPE_HEALTHCHECK, $this->helper->unixTimestampToDbFormat(strtotime('-30 minutes')));
        $this->assertTrue($result, 'the insertion should have been successful');
        $this->assertEquals(2, $this->helper->getTotalNumberOfEvents(), 'the event count does not match expected');

        $timestamp = $this->helper->unixTimestampToDbFormat(strtotime('now'));
        $result = $shit->insertPumpEvent(1, 2, 3, BaseShit::EVENT_TYPE_PUMPING, $timestamp);
        $this->assertTrue($result, 'the insertion should have been successful');
        $this->assertEquals(3, $this->helper->getTotalNumberOfEvents(), 'the event count does not match expected');

        // This insertion should fail, leaving the event count the same
        $result = $shit->insertPumpEvent(1, 2, 3, BaseShit::EVENT_TYPE_PUMPING, $timestamp);
        $this->assertFalse($result, 'the insertion should have been failed');
        $this->assertEquals(3, $this->helper->getTotalNumberOfEvents(), 'the event count does not match expected');
    }

    public function test_getMostRecentEventsOfEachType_noData() {
        $shit = new BaseShit($this->envFile);
        $results = $shit->getMostRecentEventsOfEachType();

        $this->assertNull($results[BaseShit::EVENT_TYPE_STARTUP], 'event result type should be null');
        $this->assertNull($results[BaseShit::EVENT_TYPE_PUMPING], 'event result type should be null');
        $this->assertNull($results[BaseShit::EVENT_TYPE_HEALTHCHECK], 'event result type should be null');
    }

    public function test_getMostRecentEventsOfEachType() {
        $recentStartupTimestamp = $this->helper->unixTimestampToDbFormat(strtotime('-5 minute'));
        $recentHealthcheckTimestamp = $this->helper->unixTimestampToDbFormat(strtotime('-3 minute'));
        $recentPumpingTimestamp = $this->helper->unixTimestampToDbFormat(strtotime('-1 minute'));
        $this->createTwoCyclesOfFixtureData($recentStartupTimestamp, $recentHealthcheckTimestamp, $recentPumpingTimestamp);

        $result = $this->helper->getTotalNumberOfEvents();
        $this->assertEquals(9, $result, 'the total number of events did not match expected');

        $shit = new BaseShit($this->envFile);
        $results = $shit->getMostRecentEventsOfEachType();

        $this->assertEquals($recentStartupTimestamp, $results[BaseShit::EVENT_TYPE_STARTUP], 'startup fail');
        $this->assertEquals($recentPumpingTimestamp, $results[BaseShit::EVENT_TYPE_PUMPING], 'pumping fail');
        $this->assertEquals($recentHealthcheckTimestamp, $results[BaseShit::EVENT_TYPE_HEALTHCHECK], 'healthcheck fail');
    }

    public function test_getCalloutCountSinceReboot_noData() {
        $shit = new BaseShit($this->envFile);
        $result = $shit->getCalloutCountSinceReboot();

        $this->assertEquals(0, $result, 'there should be zero callouts');
    }

    public function test_getCalloutCountSinceReboot() {
        // getCalloutCountSinceReboot() should count all pump events that have occurred since, and including, the most recent
        // startup event. no events before that should be counted.
        $recentStartupTimestamp = $this->helper->unixTimestampToDbFormat(strtotime('-9 minute'));
        $recentHealthcheckTimestamp = $this->helper->unixTimestampToDbFormat(strtotime('-8 minute'));
        $recentPumpingTimestamp = $this->helper->unixTimestampToDbFormat(strtotime('-7 minute'));
        $this->createTwoCyclesOfFixtureData($recentStartupTimestamp, $recentHealthcheckTimestamp, $recentPumpingTimestamp);

        $shit = new BaseShit($this->envFile);
        $result = $shit->getCalloutCountSinceReboot();

        $this->assertEquals(3, $result, 'the number of callouts does not match expected');
    }

    private function createTwoCyclesOfFixtureData($startupTimestamp, $healthcheckTimestamp, $pumpingTimestamp) {
        $this->helper->insertIntoPumpEvents(BaseShit::EVENT_TYPE_STARTUP, $startupTimestamp);
        $this->helper->insertIntoPumpEvents(BaseShit::EVENT_TYPE_HEALTHCHECK, $healthcheckTimestamp);
        $this->helper->insertIntoPumpEvents(BaseShit::EVENT_TYPE_PUMPING, $pumpingTimestamp);

        // insert some older, red herring events of each type
        $this->helper->insertIntoPumpEvents(BaseShit::EVENT_TYPE_STARTUP, $this->helper->unixTimestampToDbFormat(strtotime('-60 minutes')));
        $this->helper->insertIntoPumpEvents(BaseShit::EVENT_TYPE_HEALTHCHECK, $this->helper->unixTimestampToDbFormat(strtotime('-50 minutes')));
        $this->helper->insertIntoPumpEvents(BaseShit::EVENT_TYPE_HEALTHCHECK, $this->helper->unixTimestampToDbFormat(strtotime('-40 minutes')));
        $this->helper->insertIntoPumpEvents(BaseShit::EVENT_TYPE_PUMPING, $this->helper->unixTimestampToDbFormat(strtotime('-30 minutes')));
        $this->helper->insertIntoPumpEvents(BaseShit::EVENT_TYPE_PUMPING, $this->helper->unixTimestampToDbFormat(strtotime('-20 minutes')));
        $this->helper->insertIntoPumpEvents(BaseShit::EVENT_TYPE_PUMPING, $this->helper->unixTimestampToDbFormat(strtotime('-10 minutes')));
    }
}