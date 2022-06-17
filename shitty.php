<?php
require_once __DIR__ . '/lib/ShitPumper.class.php';

$shitpumper = new ShitPumper('.env');
if ($shitpumper->insertCurrentPumpEvent()) {
    echo ":thumbsup:";
} else {
    echo ":poop:";
}