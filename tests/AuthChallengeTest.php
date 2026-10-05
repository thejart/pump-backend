<?php
require_once __DIR__ . '/../lib/BaseShit.class.php';
require_once __DIR__ . '/lib/FunctionalHelper.class.php';
use PHPUnit\Framework\TestCase;

final class AuthChallengeTest extends TestCase
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

        $this->helper->deleteAllAuthChallenges();
    }

    public function test_createAuthChallenge_insertsSingleRow() {
        $shit = new BaseShit($this->envFile);

        $this->assertTrue($shit->createAuthChallenge('set', '2026-07-15 00:00:00', '123456'));
        $this->assertEquals(1, $this->helper->getTotalNumberOfAuthChallenges(), 'exactly one challenge should exist');
    }

    public function test_createAuthChallenge_replacesPrevious() {
        $shit = new BaseShit($this->envFile);

        $shit->createAuthChallenge('set', '2026-07-15 00:00:00', '111111');
        $shit->createAuthChallenge('clear', null, '222222');

        // Only one challenge is ever outstanding.
        $this->assertEquals(1, $this->helper->getTotalNumberOfAuthChallenges(), 'the previous challenge should be replaced');
    }

    public function test_verifyAuthChallenge_correctCodeReturnsChallengeAndConsumesIt() {
        $shit = new BaseShit($this->envFile);
        $shit->createAuthChallenge('set', '2026-07-15 00:00:00', '424242');

        $challenge = $shit->verifyAuthChallenge('424242');
        $this->assertNotNull($challenge, 'a correct code should verify');
        $this->assertEquals('set', $challenge->action, 'the bound action should be returned');
        $this->assertEquals('2026-07-15 00:00:00', $challenge->payload, 'the bound payload should be returned');
        $this->assertEquals(0, $this->helper->getTotalNumberOfAuthChallenges(), 'the challenge should be consumed');

        // A consumed code cannot be reused.
        $this->assertNull($shit->verifyAuthChallenge('424242'), 'a consumed code should not verify again');
    }

    public function test_verifyAuthChallenge_wrongCodeFailsButKeepsChallenge() {
        $shit = new BaseShit($this->envFile);
        $shit->createAuthChallenge('clear', null, '424242');

        $this->assertNull($shit->verifyAuthChallenge('000000'), 'a wrong code should not verify');
        $this->assertEquals(1, $this->helper->getTotalNumberOfAuthChallenges(), 'the challenge should survive a wrong guess');

        // The correct code still works after a wrong attempt.
        $this->assertNotNull($shit->verifyAuthChallenge('424242'), 'the correct code should still verify');
    }

    public function test_verifyAuthChallenge_lockoutAfterMaxAttempts() {
        $shit = new BaseShit($this->envFile);
        $shit->createAuthChallenge('clear', null, '424242');

        for ($i = 0; $i < BaseShit::AUTH_CHALLENGE_MAX_ATTEMPTS; $i++) {
            $this->assertNull($shit->verifyAuthChallenge('000000'), 'wrong guesses should fail');
        }

        $this->assertEquals(0, $this->helper->getTotalNumberOfAuthChallenges(), 'too many wrong guesses should discard the challenge');
        $this->assertNull($shit->verifyAuthChallenge('424242'), 'the correct code is useless once locked out');
    }

    public function test_verifyAuthChallenge_expiredReturnsNull() {
        $shit = new BaseShit($this->envFile);
        $this->helper->insertAuthChallenge('424242', 'set', '2026-07-15 00:00:00', date("Y-m-d H:i:s", strtotime('-1 minute')));

        $this->assertNull($shit->verifyAuthChallenge('424242'), 'an expired code should not verify');
    }

    public function test_secondsSinceLastAuthChallenge() {
        $shit = new BaseShit($this->envFile);

        $this->assertNull($shit->secondsSinceLastAuthChallenge(), 'no challenges means null');

        $shit->createAuthChallenge('clear', null, '424242');
        $age = $shit->secondsSinceLastAuthChallenge();
        $this->assertNotNull($age, 'a just-created challenge has an age');
        $this->assertGreaterThanOrEqual(0, $age, 'age should be non-negative');
        $this->assertLessThan(60, $age, 'a fresh challenge should be seconds old');
    }
}
