<?php

require_once 'IO/SoundFont.php';

$sfdata = file_get_contents($argv[1]);
$sf = new IO_SoundFont();
$sf->parse($sfdata);
$sf->dump();
