<?php
require __DIR__ . '/lib/ShitPumper.class.php';

$shitpumper = new ShitPumper();
if ($shitpumper->insertPumpEvent()) {
    echo ":thumbsup:";
} else {
    echo ":poop:";
}