<?php
require_once __DIR__ . '/BaseShit.class.php';

class ShitPumper extends BaseShit {
    private $xValue;
    private $yValue;
    private $zValue;
    private $type;
    private $isTestCall = false;

    public function __construct($envFile) {
        parent::__construct($envFile);
        $this->parseRequestParams();
    }

    private function parseRequestParams() {
        // Bail early if the request doesn't include required params
        if (is_null($this->getRequestParam('x')) || is_null($this->getRequestParam('y')) || is_null($this->getRequestParam('z'))) {
            $this->isTestCall = true;
            return;
        }

        $this->xValue = (float)$this->getRequestParam('x');
        $this->yValue = (float)$this->getRequestParam('y');
        $this->zValue = (float)$this->getRequestParam('z');

        if ($this->getRequestParam('shitstorm')) {
            $this->type = self::EVENT_TYPE_PUMPING;
        } else {
            // TODO: Send across a distinct startup signal (rather than all zeroes)
            if ($this->xValue > -0.009 && $this->xValue < 0.001 && $this->yValue > -0.009 && $this->yValue < 0.001 && $this->zValue > -0.009 && $this->zValue < 0.001) {
                $this->type = self::EVENT_TYPE_STARTUP;
            } else {
                $this->type = self::EVENT_TYPE_HEALTHCHECK;
            }
        }
    }

    public function insertCurrentPumpEvent() {
        if ($this->isTestCall) {
            return true;
        }

        return $this->insertPumpEvent($this->xValue, $this->yValue, $this->zValue, $this->type, date("Y-m-d H:i:s", strtotime('now')));
    }
}