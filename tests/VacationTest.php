<?php
require_once __DIR__ . '/../lib/BaseShit.class.php';
require_once __DIR__ . '/lib/FunctionalHelper.class.php';
use PHPUnit\Framework\TestCase;

final class VacationTest extends TestCase
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

        $this->helper->deleteAllVacations();
    }

    public function test_setVacationEndDate_insertsActiveRow() {
        $shit = new BaseShit($this->envFile);
        $endDate = date("Y-m-d H:i:s", strtotime('+7 days'));

        $this->assertFalse($shit->hasActiveVacation(), 'should start with no active vacation');

        $result = $shit->setVacationEndDate($endDate);
        $this->assertTrue($result, 'the insertion should have been successful');
        $this->assertTrue($shit->hasActiveVacation(), 'a vacation should now be active');
        $this->assertEquals($endDate, $shit->getActiveVacationEndDate(), 'the active end date should match');
        $this->assertEquals(1, $this->helper->getTotalNumberOfVacations(), 'there should be exactly one row');
    }

    public function test_setVacationEndDate_rejectsUnparseableDate() {
        $shit = new BaseShit($this->envFile);

        $result = $shit->setVacationEndDate('not a date');
        $this->assertFalse($result, 'an unparseable date should be rejected');
        $this->assertEquals(0, $this->helper->getTotalNumberOfVacations(), 'no row should be inserted');
    }

    public function test_getActiveVacationEndDate_pastReturnsNull() {
        $shit = new BaseShit($this->envFile);
        $this->helper->insertVacation(date("Y-m-d H:i:s", strtotime('-1 day')));

        $this->assertNull($shit->getActiveVacationEndDate(), 'a past vacation should not be active');
        $this->assertFalse($shit->hasActiveVacation(), 'a past vacation should not be active');
    }

    public function test_getActiveVacationEndDate_archivedReturnsNull() {
        $shit = new BaseShit($this->envFile);
        $this->helper->insertVacation(date("Y-m-d H:i:s", strtotime('+7 days')), 1);

        $this->assertNull($shit->getActiveVacationEndDate(), 'an archived vacation should not be active');
        $this->assertFalse($shit->hasActiveVacation(), 'an archived vacation should not be active');
    }

    public function test_clearVacation_softArchives() {
        $shit = new BaseShit($this->envFile);
        $this->helper->insertVacation(date("Y-m-d H:i:s", strtotime('+7 days')));
        $this->assertTrue($shit->hasActiveVacation(), 'precondition: a vacation is active');

        $shit->clearVacation();

        $this->assertFalse($shit->hasActiveVacation(), 'the vacation should no longer be active');
        $this->assertEquals(1, $this->helper->getTotalNumberOfVacations(), 'the row should be preserved (soft delete)');
    }

    public function test_setWhileActive_archivesOldAndKeepsOneActive() {
        $shit = new BaseShit($this->envFile);

        $this->assertTrue($shit->setVacationEndDate(date("Y-m-d H:i:s", strtotime('+7 days'))));
        $newEndDate = date("Y-m-d H:i:s", strtotime('+14 days'));
        $this->assertTrue($shit->setVacationEndDate($newEndDate));

        // The old active row is archived and a new one inserted: two rows total, but only one active.
        $this->assertEquals(2, $this->helper->getTotalNumberOfVacations(), 'history should be preserved');
        $this->assertEquals($newEndDate, $shit->getActiveVacationEndDate(), 'the most recent end date should be active');
    }
}
