<?php
require_once __DIR__ . '/../lib/ShitShow.class.php';
use PHPUnit\Framework\TestCase;

final class ShitShowTest extends TestCase
{
    private const SEC = 1000;
    private const MIN = 60 * self::SEC;

    // Arbitrary base time: 2026-09-08 00:00:00 UTC, in milliseconds.
    private const BASE = 1788825600000;

    private $envFile = '.env.testing';
    private $shitShow;

    protected function setUp(): void {
        parent::setUp();

        $this->shitShow = new ShitShow($this->envFile);
    }

    public function test_constructor_hasNoTextingSecrets() {
        $this->assertNull($this->shitShow->getTextbeltToken(), "textbelt token should be null");
        $this->assertNull($this->shitShow->getTextNumbers(), "text number should be null");
    }

    // ---- deduceWashingMachineEvents: basics -------------------------------

    public function test_deduceWashingMachineEvents_emptyInputReturnsEmptyArrays() {
        [$pumping, $washing, $cycles] = $this->deduce([]);

        $this->assertSame([], $pumping);
        $this->assertSame([], $washing);
        $this->assertSame([], $cycles);
    }

    public function test_deduceWashingMachineEvents_singlePumpIsPlumbingNotWashing() {
        $e = $this->event(0);
        [$pumping, $washing, $cycles] = $this->deduce([$e]);

        $this->assertSame([$e], $pumping);
        $this->assertSame([], $washing);
        $this->assertSame([], $cycles);
    }

    public function test_deduceWashingMachineEvents_isolatedPumpsAreAllPlumbing() {
        $events = $this->eventsAtSeconds([0, 3600, 7200, 30000]);
        [$pumping, $washing] = $this->deduce($events);

        $this->assertSame($events, $pumping);
        $this->assertSame([], $washing);
    }

    public function test_deduceWashingMachineEvents_returnsTwoValuesForBackwardCompatibleDestructuring() {
        [$pumping, $washing] = $this->deduce($this->eventsAtSeconds([0, 30]));

        $this->assertCount(0, $pumping);
        $this->assertCount(1, $washing);
    }

    // ---- deduceWashingMachineEvents: session grouping ---------------------

    public function test_deduceWashingMachineEvents_twoPumpsCloseTogetherAreOneWash() {
        // Light cycle, e.g. Jul 4 08:47:45 and 08:50:03.
        $events = $this->eventsAtSeconds([0, 138]);
        [$pumping, $washing, $cycles] = $this->deduce($events);

        $this->assertSame([], $pumping);
        $this->assertSame([$events[0]], $washing);
        $this->assertCount(1, $cycles);
        $this->assertSame(2, $cycles[0]['pumps']);
    }

    public function test_deduceWashingMachineEvents_pumpsTwelveMinutesApartAreStillOneWash() {
        // Missed by the old 3-minute rule, e.g. Aug 15 16:45:34 and 16:57:47.
        [$pumping, $washing] = $this->deduce($this->eventsAtSeconds([0, 733]));

        $this->assertSame([], $pumping);
        $this->assertCount(1, $washing);
    }

    public function test_deduceWashingMachineEvents_gapOfExactlyTwentyFiveMinutesStaysInSession() {
        $events = [$this->event(0), $this->event(25 * self::MIN)];
        [, $washing] = $this->deduce($events);

        $this->assertCount(1, $washing);
    }

    public function test_deduceWashingMachineEvents_gapJustOverTwentyFiveMinutesSplitsSession() {
        $events = [$this->event(0), $this->event(25 * self::MIN + 1)];
        [$pumping, $washing] = $this->deduce($events);

        $this->assertSame($events, $pumping);
        $this->assertSame([], $washing);
    }

    public function test_deduceWashingMachineEvents_heavyCycleIsCountedOnce() {
        // Regression: the old 3-event window flagged big loads several times.
        // Shape taken from Jul 26 (Bedding): five drain bursts over ~80 minutes.
        $events = $this->eventsAtSeconds([
            0, 29, 58, 88, 121, 226,
            1228, 1257, 1287, 1316, 1347, 1833,
            2275, 2306,
            3651, 3721,
            4560, 4590, 4619, 4652, 4837,
        ]);
        [$pumping, $washing, $cycles] = $this->deduce($events);

        $this->assertSame([], $pumping);
        $this->assertCount(1, $washing);
        $this->assertSame($events[0], $washing[0]);
        $this->assertSame(21, $cycles[0]['pumps']);
    }

    public function test_deduceWashingMachineEvents_backToBackCyclesAreSeparate() {
        // Jul 10: Bedding drains at 17:01 and 17:25 (24 min apart, same session),
        // last pump 17:30:58; Normal pumps start 18:09:10 (38 min later).
        $events = $this->eventsAtSeconds([
            0, 31, 60, 89, 120, 241,       // 17:01:06 - 17:05:07
            1467, 1498, 1527, 1556, 1587, 1792, // 17:25:33 - 17:30:58
            4084, 4147, 4613,               // 18:09:10 - 18:17:59
        ]);
        [$pumping, $washing, $cycles] = $this->deduce($events);

        $this->assertSame([], $pumping);
        $this->assertSame([$events[0], $events[12]], $washing);
        $this->assertSame([12, 3], array_column($cycles, 'pumps'));
        $this->assertSame([true, false], array_column($cycles, 'heavy'));
    }

    public function test_deduceWashingMachineEvents_washBetweenPlumbingEventsIsPartitionedCorrectly() {
        $before = $this->event(0);
        $wash = [$this->event(2 * 3600 * self::SEC), $this->event(2 * 3600 * self::SEC + 30 * self::SEC)];
        $after = $this->event(5 * 3600 * self::SEC);

        [$pumping, $washing] = $this->deduce([$before, ...$wash, $after]);

        $this->assertSame([$before, $after], $pumping);
        $this->assertSame([$wash[0]], $washing);
    }

    public function test_deduceWashingMachineEvents_everyPumpIsAccountedForExactlyOnce() {
        $events = $this->eventsAtSeconds([0, 4000, 4030, 4100, 9000, 20000, 20040, 21000, 40000]);
        [$pumping, , $cycles] = $this->deduce($events);

        $this->assertSame(
            count($events),
            count($pumping) + array_sum(array_column($cycles, 'pumps'))
        );
    }

    // ---- deduceWashingMachineEvents: cycle details ------------------------

    public function test_deduceWashingMachineEvents_cycleTimesAreInMilliseconds() {
        $events = $this->eventsAtSeconds([0, 40, 400]);
        [, , $cycles] = $this->deduce($events);

        $this->assertSame($events[0]->x, $cycles[0]['start']);
        $this->assertSame($events[2]->x, $cycles[0]['lastPump']);
        $this->assertSame($events[2]->x + 10 * self::MIN, $cycles[0]['estimatedEnd']);
    }

    public function test_deduceWashingMachineEvents_twoQuickRepeatsIsHeavy() {
        [, , $cycles] = $this->deduce($this->eventsAtSeconds([0, 30, 60]));
        $this->assertTrue($cycles[0]['heavy']);
    }

    public function test_deduceWashingMachineEvents_oneQuickRepeatIsLight() {
        // Typical Normal cycle: two pumps 30s apart, then one a few minutes later.
        [, , $cycles] = $this->deduce($this->eventsAtSeconds([0, 30, 400]));
        $this->assertFalse($cycles[0]['heavy']);
    }

    public function test_deduceWashingMachineEvents_quickRepeatBoundaryIsNinetySecondsInclusive() {
        [, , $atLimit] = $this->deduce($this->eventsAtSeconds([0, 90, 180]));
        [, , $overLimit] = $this->deduce($this->eventsAtSeconds([0, 91, 182]));

        $this->assertTrue($atLimit[0]['heavy']);
        $this->assertFalse($overLimit[0]['heavy']);
    }

    // ---- deduceWashingMachineEvents: real data ----------------------------

    public function test_deduceWashingMachineEvents_millisecondTimestampsFromRealData() {
        // Regression: constants were in seconds while ->x is in milliseconds,
        // so nothing was ever detected. Sep 8 slice from the live feed:
        // a Towels cycle (12:36 PM) and a Normal cycle (1:40 PM) EDT,
        // surrounded by single pumps.
        $xs = [
            1788833636000,
            1788883082000, 1788883114000, 1788883216000,
            1788884366000, 1788884398000, 1788884432000, 1788884682000,
            1788888510000, 1788888565000, 1788889107000,
            1788919091000,
        ];
        $events = array_map(fn($x) => (object) ['x' => $x, 'y' => 5.5], $xs);

        [$pumping, $washing, $cycles] = $this->deduce($events);

        $this->assertSame([$events[0], $events[11]], $pumping);
        $this->assertCount(2, $washing);
        $this->assertSame([7, 3], array_column($cycles, 'pumps'));
        $this->assertSame([true, false], array_column($cycles, 'heavy'));

        // Logged end times: 2026-09-08 12:36 and 13:40 EDT.
        $logged = [1788885360000, 1788889200000];
        foreach ($cycles as $i => $cycle) {
            $this->assertLessThanOrEqual(
                10 * self::MIN,
                abs($cycle['estimatedEnd'] - $logged[$i]),
                "Cycle $i estimated end is more than 10 minutes off"
            );
        }
    }

    // ---- helpers ----------------------------------------------------------

    /** Build an event at BASE + $offsetMs. */
    private function event(int $offsetMs, float $y = 5.5): object {
        return (object) ['x' => self::BASE + $offsetMs, 'y' => $y];
    }

    /** Build events from a list of offsets in seconds. */
    private function eventsAtSeconds(array $seconds): array {
        return array_map(fn($s) => $this->event($s * self::SEC), $seconds);
    }

    private function deduce(array $events): array {
        return $this->shitShow->deduceWashingMachineEvents($events);
    }
}
