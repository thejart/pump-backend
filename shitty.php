<?php
require __DIR__ . '/lib/shit.class.php';

class ShitPumper extends Shit {
    protected $xValue;
    protected $yValue;
    protected $zValue;
    protected $type;

    public function __construct() {
        parent::__construct();

        $this->parseRequestParams();
    }

  protected function parseRequestParams() {
    $this->xValue = (float)$this->getRequestParam('x');
    $this->yValue = (float)$this->getRequestParam('y');
    $this->zValue = (float)$this->getRequestParam('z');

    if ($this->getRequestParam('shitstorm')) {
        $this->type = self::PUMPING;
    //} elseif ((bool)$this->getRequestParam('healthcheck', false)) {
    } else {
        if ($this->xValue > -0.009 && $this->xValue < 0.001 && $this->yValue > -0.009 && $this->yValue < 0.001 && $this->zValue > -0.009 && $this->zValue < 0.001) {
            $this->type = self::STARTUP;
        } else {
            $this->type = self::HEALTHCHECK;
        }
    }
  }
}

$shitpumper = new ShitPumper();

if ($shitpumper->insertPumpEvent()) {
    echo ":thumbsup:";
} else {
    echo ":poop:";
}
