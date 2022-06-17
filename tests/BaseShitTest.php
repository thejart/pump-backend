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

        $this->assertNull($baseShit->getTwilioNumbers(), "twilio number should be null");
        $this->assertNull($baseShit->getTextNumber(), "text number should be null");
        $this->assertNull($baseShit->getAuthToken(), "auth token should be null");
        $this->assertNull($baseShit->getAccountSid(), "account sid should be null");
    }

    public function test_constructor_hasTwilioSecrets() {
        $baseShit = new BaseShit($this->envFile, true);

        $this->assertNotNull($baseShit->getTwilioNumbers(), "twilio number should not be null");
        $this->assertNotNull($baseShit->getTextNumber(), "text number should not be null");
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

    public function test_getMostRecentEventsOfEachType() {
        $shit = new BaseShit($this->envFile);
        $results = $shit->getMostRecentEventsOfEachType();

        $this->assertEquals('2022-06-14 14:10:26', $results[BaseShit::EVENT_TYPE_STARTUP], 'startup fail');
        $this->assertEquals('2022-06-15 08:49:16', $results[BaseShit::EVENT_TYPE_PUMPING], 'pumping fail');
        $this->assertEquals('2022-06-15 14:08:59', $results[BaseShit::EVENT_TYPE_HEALTHCHECK], 'healthcheck fail');
    }

    public function test_getCurrentCalloutCount() {
        // getCurrentCalloutCount() should count all pump events that have occurred since, and including, the most recent
        // startup event. no events before that should be counted.
        $this->insertIntoPumpEvents(1,2,3,BaseShit::EVENT_TYPE_STARTUP, $this->convertUnixTimestampToDbFormat(strtotime('now')));

        $shit = new BaseShit($this->envFile);
        $result = $shit->getCurrentCalloutCount();

        $this->assertEquals(1, $result, 'the number of callouts does not match expected');
    }

    public function test_getAccountSid() {
    }
    // et al getters/setters...

    public function test_getXDaysOfRecentEvents() {
    }

    public function test_getMaxAbsoluteValue() {
    }

    public function test_numberOfHealthChecksInLastXHours() {
    }

    public function test_hasHadRecentPumping() {
    }

    public function test_getRequestParam() {
    }

    private function setupPdo() {
        $parsedEnvFile = file_get_contents($this->envFile);
        list($mysqlDatabase, $mysqlUsername, $mysqlPassword) = explode("\n", $parsedEnvFile);
        $this->pdo = new PDO("mysql:host=127.0.0.1;dbname=". $mysqlDatabase, $mysqlUsername, $mysqlPassword);
    }

    private function convertUnixTimestampToDbFormat($unixTimestamp) {
        return date("Y-m-d H:i:s", $unixTimestamp);
    }

    private function insertIntoPumpEvents($x, $y, $z, $type, $timestamp) {
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