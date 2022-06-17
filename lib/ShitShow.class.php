<?php
require __DIR__ . '/BaseShit.class.php';

class ShitShow extends BaseShit {
    const FIFTEEN_MINUTES = 900;
    const THREE_MINUTES = 180;

    const BACKGROUND_OPTIONS = [
        self::EVENT_TYPE_STARTUP =>         "rgba(54, 162, 235, 0.4)",  // blue
        self::EVENT_TYPE_PUMPING =>         "rgba(139, 69, 19, 0.4)",   // brown
        self::EVENT_TYPE_WASHING_MACHINE => "rgba(97, 148, 49, 0.4)",   // green
        self::EVENT_TYPE_HEALTHCHECK =>     "rgba(201, 203, 207, 0.2)"  // grey
    ];
    const BORDER_OPTIONS = [
        self::EVENT_TYPE_STARTUP =>         "rgb(54, 162, 235)",    // blue
        self::EVENT_TYPE_PUMPING =>         "rgb(139, 69, 19)",     // brown
        self::EVENT_TYPE_WASHING_MACHINE => "rgb(97, 148, 49)",     // green
        self::EVENT_TYPE_HEALTHCHECK =>     "rgb(201, 203, 207)"    // grey
    ];

    /** @var int */
    protected $viewWindow;
    /** @var bool */
    protected $viewDeducedEvents;
    /** @var string */
    protected $filename;

    public function __construct($envFile) {
        parent::__construct($envFile);
        $this->viewWindow = (int)$this->getRequestParam('days', 7);
        $this->viewDeducedEvents = (bool)$this->getRequestParam('deduced', true);
        $this->filename = $_SERVER['SCRIPT_NAME'];
    }

    public function getChartData() {
        $startupData = [];
        $pumpingData = [];
        $healthcheckData = [];

        foreach ($this->getXDaysOfRecentEvents($this->viewWindow) as $event) {
            $graphedDatum = new stdClass();
            $graphedDatum->x = $event->timestamp;
            $graphedDatum->y = $this->getMaxAbsoluteValue($event);

            if ($event->type == self::EVENT_TYPE_STARTUP) {
                $startupData[] = $graphedDatum;
            } elseif ($event->type == self::EVENT_TYPE_PUMPING) {
                $pumpingData[] = $graphedDatum;
            } else {
                $healthcheckData[] = $graphedDatum;
            }
        }

        return [$startupData, $pumpingData, $healthcheckData];
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

    public function deduceWashingMachineEvents($events) {
        $skipEventCounter = 0;
        $pumpingEvents = [];
        $washingEvents = [];

        foreach ($events as $i => $event) {
            if ($skipEventCounter) {
                $skipEventCounter--;
                continue;
            }

            if (!isset($events[$i+2])) {
                $pumpingEvents[] = $event;
                continue;
            }

            // if event[i+2] is within 15 minutes of event[i] AND event[i+1] is within 3 minutes of event[i],
            // we probably have a washing machine event
            if (strtotime($events[$i+2]->x) - strtotime($event->x) <= self::FIFTEEN_MINUTES &&
                strtotime($events[$i+1]->x) - strtotime($event->x) <= self::THREE_MINUTES) {
                $washingEvents[] = $event;
                $skipEventCounter = 2;
            } else {
                $pumpingEvents[] = $event;
            }
        }

        return [$pumpingEvents, $washingEvents];
    }
}