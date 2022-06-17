<?php
require __DIR__ . '/../lib/BaseShit.class.php';
use PHPUnit\Framework\TestCase;

final class BaseShitTest extends TestCase
{
    private $envFile = '.env.testing';
    private $pdo;

    protected function setUp(): void {
        parent::setUp();

        $this->setupPdo();
    }

    protected function tearDown(): void {
        parent::tearDown();

        $query = $this->pdo->prepare("DELETE FROM pump_events");
        $query->execute();
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
        $this->assertEquals(0, $this->getTotalNumberOfEvents(), 'the event count does not match expected');

        $result = $shit->insertPumpEvent(1, 2, 3, BaseShit::EVENT_TYPE_STARTUP, $this->convertUnixTimestampToDbFormat(strtotime('-1 hour')));
        $this->assertTrue($result, 'the insertion should have been successful');
        $this->assertEquals(1, $this->getTotalNumberOfEvents(), 'the event count does not match expected');

        $result = $shit->insertPumpEvent(1, 2, 3, BaseShit::EVENT_TYPE_HEALTHCHECK, $this->convertUnixTimestampToDbFormat(strtotime('-30 minutes')));
        $this->assertTrue($result, 'the insertion should have been successful');
        $this->assertEquals(2, $this->getTotalNumberOfEvents(), 'the event count does not match expected');

        $timestamp = $this->convertUnixTimestampToDbFormat(strtotime('now'));
        $result = $shit->insertPumpEvent(1, 2, 3, BaseShit::EVENT_TYPE_PUMPING, $timestamp);
        $this->assertTrue($result, 'the insertion should have been successful');
        $this->assertEquals(3, $this->getTotalNumberOfEvents(), 'the event count does not match expected');

        // This insertion should fail, leaving the event count the same
        $result = $shit->insertPumpEvent(1, 2, 3, BaseShit::EVENT_TYPE_PUMPING, $timestamp);
        $this->assertFalse($result, 'the insertion should have been failed');
        $this->assertEquals(3, $this->getTotalNumberOfEvents(), 'the event count does not match expected');
    }

    public function test_getMostRecentEventsOfEachType_noData() {
        $shit = new BaseShit($this->envFile);
        $results = $shit->getMostRecentEventsOfEachType();

        $this->assertNull($results[BaseShit::EVENT_TYPE_STARTUP], 'event result type should be null');
        $this->assertNull($results[BaseShit::EVENT_TYPE_PUMPING], 'event result type should be null');
        $this->assertNull($results[BaseShit::EVENT_TYPE_HEALTHCHECK], 'event result type should be null');
    }

    public function test_getMostRecentEventsOfEachType() {
        // insert multiple events of each type, keeping track of most recent
        $recentStartupTimestamp = $this->convertUnixTimestampToDbFormat(strtotime('-5 minute'));
        $recentHealthcheckTimestamp = $this->convertUnixTimestampToDbFormat(strtotime('-3 minute'));
        $recentPumpingTimestamp = $this->convertUnixTimestampToDbFormat(strtotime('-1 minute'));
        $this->insertIntoPumpEvents(BaseShit::EVENT_TYPE_STARTUP, $recentStartupTimestamp);
        $this->insertIntoPumpEvents(BaseShit::EVENT_TYPE_HEALTHCHECK, $recentHealthcheckTimestamp);
        $this->insertIntoPumpEvents(BaseShit::EVENT_TYPE_PUMPING, $recentPumpingTimestamp);

        // insert some older, red herring events of each type
        $this->insertIntoPumpEvents(BaseShit::EVENT_TYPE_STARTUP, $this->convertUnixTimestampToDbFormat(strtotime('-60 minutes')));
        $this->insertIntoPumpEvents(BaseShit::EVENT_TYPE_HEALTHCHECK, $this->convertUnixTimestampToDbFormat(strtotime('-50 minutes')));
        $this->insertIntoPumpEvents(BaseShit::EVENT_TYPE_HEALTHCHECK, $this->convertUnixTimestampToDbFormat(strtotime('-40 minutes')));
        $this->insertIntoPumpEvents(BaseShit::EVENT_TYPE_PUMPING, $this->convertUnixTimestampToDbFormat(strtotime('-30 minutes')));
        $this->insertIntoPumpEvents(BaseShit::EVENT_TYPE_PUMPING, $this->convertUnixTimestampToDbFormat(strtotime('-20 minutes')));
        $this->insertIntoPumpEvents(BaseShit::EVENT_TYPE_PUMPING, $this->convertUnixTimestampToDbFormat(strtotime('-10 minutes')));

        $result = $this->getTotalNumberOfEvents();
        $this->assertEquals(9, $result, 'the total number of events did not match expected');

        $shit = new BaseShit($this->envFile);
        $results = $shit->getMostRecentEventsOfEachType();

        $this->assertEquals($recentStartupTimestamp, $results[BaseShit::EVENT_TYPE_STARTUP], 'startup fail');
        $this->assertEquals($recentPumpingTimestamp, $results[BaseShit::EVENT_TYPE_PUMPING], 'pumping fail');
        $this->assertEquals($recentHealthcheckTimestamp, $results[BaseShit::EVENT_TYPE_HEALTHCHECK], 'healthcheck fail');
    }

    public function test_getCurrentCalloutCount() {
        // getCurrentCalloutCount() should count all pump events that have occurred since, and including, the most recent
        // startup event. no events before that should be counted.
        $this->insertIntoPumpEvents(BaseShit::EVENT_TYPE_STARTUP, $this->convertUnixTimestampToDbFormat(strtotime('now')));

        $shit = new BaseShit($this->envFile);
        $result = $shit->getCurrentCalloutCount();

        $this->assertEquals(1, $result, 'the number of callouts does not match expected');
    }

    private function setupPdo() {
        // No need to reinitialize the pdo between each test method
        if ($this->pdo) {
           return;
        }

        $parsedEnvFile = file_get_contents($this->envFile);
        list($mysqlDatabase, $mysqlUsername, $mysqlPassword) = explode("\n", $parsedEnvFile);
        $this->pdo = new PDO("mysql:host=127.0.0.1;dbname=". $mysqlDatabase, $mysqlUsername, $mysqlPassword);
    }

    private function convertUnixTimestampToDbFormat($unixTimestamp) {
        return date("Y-m-d H:i:s", $unixTimestamp);
    }

    private function insertIntoPumpEvents($type, $timestamp, $x = 1, $y = 2, $z = 3) {
        $query = $this->pdo->prepare("
            INSERT INTO pump_events
            (x_value, y_value, z_value, type, timestamp)
            VALUES (:x, :y, :z, :type, :timestamp)
        ");

        $query->execute([
            ':x' => $x,
            ':y' => $y,
            ':z' => $z,
            ':type' => $type,
            ':timestamp' => $timestamp
        ]);
    }

    private function getTotalNumberOfEvents() {
        $query = $this->pdo->prepare("
            SELECT COUNT(*) AS count
            FROM pump_events
        ");

        $query->execute();
        return (int)$query->fetchAll(PDO::FETCH_OBJ)[0]->count;
    }
}