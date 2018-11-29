<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/SoundFont.php';
}

$sfdata = file_get_contents($argv[1]);
$sf = new IO_SoundFont();
$sf->parse($sfdata);
$sf->dump();
