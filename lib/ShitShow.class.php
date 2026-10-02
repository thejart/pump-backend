<?php
require_once __DIR__ . '/BaseShit.class.php';

class ShitShow extends BaseShit {
    const FIFTEEN_MINUTES = 900000; // in milliseconds
    const THREE_MINUTES = 180000;   // in milliseconds

    const BACKGROUND_OPTIONS = [
        self::EVENT_TYPE_STARTUP =>         "rgba(97, 148, 49, 0.4)",   // green
        self::EVENT_TYPE_PUMPING =>         "rgba(139, 69, 19, 0.4)",   // brown
        self::EVENT_TYPE_WASHING_MACHINE => "rgba(54, 162, 235, 0.4)",  // blue
        self::EVENT_TYPE_HEALTHCHECK =>     "rgba(201, 203, 207, 0.2)"  // grey
    ];
    const BORDER_OPTIONS = [
        self::EVENT_TYPE_STARTUP =>         "rgb(97, 148, 49)",     // green
        self::EVENT_TYPE_PUMPING =>         "rgb(139, 69, 19)",     // brown
        self::EVENT_TYPE_WASHING_MACHINE => "rgb(54, 162, 235)",    // blue
        self::EVENT_TYPE_HEALTHCHECK =>     "rgb(201, 203, 207)"    // grey
    ];

    /** @var int */
    protected $viewWindow;
    /** @var bool */
    protected $viewDeducedEvents;
    /** @var string */
    protected $filename;



    // ->x timestamps are in milliseconds.
    private const SECOND = 1000;
 
    // Pumps less than this far apart belong to the same session.
    // Drain bursts within one wash cycle can be ~20 minutes apart.
    private const SESSION_GAP = 25 * 60 * self::SECOND;
 
    // A session needs at least this many pumps to count as a wash.
    // Toilets, sinks and showers almost always trigger a single pump.
    private const MIN_PUMPS = 2;
 
    // Back-to-back pumps this close together mean the pit is refilling
    // faster than it empties, i.e. a large drain.
    private const QUICK_GAP = 90 * self::SECOND;
 
    // Bedding, Towels, Delicates and Tub Clean produce 2+ quick repeats.
    private const HEAVY_QUICK_REPEATS = 2;
 
    // The cycle typically finishes ~10 minutes after the last pump.
    private const END_OFFSET = 10 * 60 * self::SECOND;


    public function __construct($envFile) {
        parent::__construct($envFile);
        $this->viewWindow = (int)$this->getRequestParam('days', 30);
        $this->viewDeducedEvents = (bool)$this->getRequestParam('deduced', true);
        $this->filename = $_SERVER['SCRIPT_NAME'];
    }

    public function getChartData() {
        $startupData = [];
        $pumpingData = [];
        $healthcheckData = [];
        $start = null;
        $end = null;

        foreach ($this->getXDaysOfRecentEvents($this->viewWindow) as $event) {
            $graphedDatum = new stdClass();
            $graphedDatum->x = (int)$event->timestamp;
            $graphedDatum->y = $this->getMaxAbsoluteValue($event);

            if ($event->type == self::EVENT_TYPE_STARTUP) {
                $startupData[] = $graphedDatum;
            } elseif ($event->type == self::EVENT_TYPE_PUMPING) {
                $pumpingData[] = $graphedDatum;
            } else {
                $healthcheckData[] = $graphedDatum;
            }

            if (!$start) {
                $start = $graphedDatum->x;
            }
            $end = $graphedDatum->x;
        }

        return [$startupData, $pumpingData, $healthcheckData, $start, $end];
    }

    public function getBackgroundColor($type) {
        return self::BACKGROUND_OPTIONS[$type];
    }

    public function getBorderColor($type) {
        return self::BORDER_OPTIONS[$type];
    }

    public function getViewWindow() {
        return $this->viewWindow;
    }

    public function getViewDeducedEvents() {
        return $this->viewDeducedEvents;
    }

    public function getFilename() {
        return $this->filename;
    }

    /**
     * @param array $events pump events, each with ->x as a unix timestamp in milliseconds
     * @return array [$pumpingEvents, $washingEvents, $cycles]
     */
    public function deduceWashingMachineEvents(array $events): array
    {
        // Assumes $events is already sorted by ->x ascending.
 
        // 1. Group pumps into sessions separated by quiet gaps.
        $sessions = [];
        $current = [];
        foreach ($events as $event) {
            if ($current && $event->x - end($current)->x > self::SESSION_GAP) {
                $sessions[] = $current;
                $current = [];
            }
            $current[] = $event;
        }
        if ($current) {
            $sessions[] = $current;
        }
 
        // 2. Classify each session as a whole.
        $pumpingEvents = [];
        $washingEvents = [];
        $cycles = [];
 
        foreach ($sessions as $session) {
            if (count($session) < self::MIN_PUMPS) {
                array_push($pumpingEvents, ...$session);
                continue;
            }
 
            $quickRepeats = 0;
            for ($i = 1; $i < count($session); $i++) {
                if ($session[$i]->x - $session[$i - 1]->x <= self::QUICK_GAP) {
                    $quickRepeats++;
                }
            }
 
            $first = $session[0];
            // Change the magnitude of the washing event to twice the count of pump events per session
            // ...one pump event has a height of 2, hence the doubling
            $first->y = 2 * count($session);
            $last = end($session);
 
            $washingEvents[] = $first;
            $cycles[] = [
                'start'        => $first->x,
                'lastPump'     => $last->x,
                'estimatedEnd' => $last->x + self::END_OFFSET,
                'pumps'        => count($session),
                'heavy'        => $quickRepeats >= self::HEAVY_QUICK_REPEATS,
            ];
        }
 
        return [$pumpingEvents, $washingEvents, $cycles];
    }
}
