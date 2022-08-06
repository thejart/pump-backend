<?php
require_once __DIR__ . '/lib/Flush.class.php';

$shitpumper = new Flush('.env');
if ($shitpumper->insertCurrentPumpEvent()) {
    echo ":thumbsup:";
} else {
    echo ":poop:";
}