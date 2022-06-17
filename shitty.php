<?php
require __DIR__ . '/lib/ShitPumper.class.php';

$shitpumper = new ShitPumper();
if ($shitpumper->insertCurrentPumpEvent()) {
    echo ":thumbsup:";
} else {
    echo ":poop:";
}