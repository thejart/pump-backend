<?php
require_once __DIR__ . '/../lib/WipeCheck.class.php';
require_once __DIR__ . '/lib/FunctionalHelper.class.php';
use PHPUnit\Framework\TestCase;

final class WipeCheckTest extends TestCase
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
        $this->helper->deleteAllVacations();
    }

    public function test_constructor_hasTextingSecrets() {
        $wipeCheck = new WipeCheck($this->envFile);

        $this->assertNotNull($wipeCheck->getTextbeltToken(), "textbelt token should not be null");
        $this->assertNotNull($wipeCheck->getTextNumbers(), "text number should not be null");
    }

    public function test_getMessage() {
        $wipeCheck = new WipeCheck($this->envFile);

        $this->assertEquals("[poop summary]\n", $wipeCheck->getMessage(), "message didn't match expected");
    }

    public function test_shouldText_vacationSuppressesNoPumpingAlert() {
        // The weekly-summary branch (Saturday before noon) short-circuits the alert path
        if ((int)date("N") === 6 && (int)date("H") < 12) {
            $this->markTestSkipped('Weekly-summary window short-circuits the alert path');
        }

        // Seed enough recent healthchecks so the too-few-healthchecks alert won't fire,
        // and intentionally insert no pumping events.
        for ($i = 1; $i <= WipeCheck::HEALTHCHECK_COUNT_THRESHOLD; $i++) {
            $this->helper->insertIntoPumpEvents(WipeCheck::EVENT_TYPE_HEALTHCHECK, date("Y-m-d H:i:s", strtotime("-{$i} hours")));
        }

        // Without a vacation, the absence of pumping should be alert-worthy.
        $wipeCheck = new WipeCheck($this->envFile);
        $this->assertTrue($wipeCheck->shouldText(), 'no pumping should alert when not on vacation');

        // With an active vacation, the no-pumping alert is suppressed.
        $this->helper->insertVacation(date("Y-m-d H:i:s", strtotime('+7 days')));
        $wipeCheckOnVacation = new WipeCheck($this->envFile);
        $this->assertFalse($wipeCheckOnVacation->shouldText(), 'no pumping should be suppressed while on vacation');
    }

    public function test_shouldText_vacationDoesNotSuppressHealthcheckAlert() {
        if ((int)date("N") === 6 && (int)date("H") < 12) {
            $this->markTestSkipped('Weekly-summary window short-circuits the alert path');
        }

        // An active vacation must NOT silence the too-few-healthchecks alert (no healthchecks seeded).
        $this->helper->insertVacation(date("Y-m-d H:i:s", strtotime('+7 days')));

        $wipeCheck = new WipeCheck($this->envFile);
        $this->assertTrue($wipeCheck->shouldText(), 'too few healthchecks should still alert while on vacation');
    }
}