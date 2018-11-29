<?php

require_once 'IO/SoundFont.php';
require_once 'IO/SoundFont/WaveData.php';

if ($argc < 2) {
    echo "Usage: php sfextractsample.php <sffile> [<looptime>]".PHP_EOL;
    echo "ex) php sample/sfextractsample.php emuaps_8mb.sf2 3".PHP_EOL;
    exit(1);
}

$sfdata = file_get_contents($argv[1]);
if ($argc == 2) {
    $looptime = null;
} else {
    $looptime = $argv[2];
}

$sf = new IO_SoundFont();
$sf->parse($sfdata);
$sf->analyze();
$banks = $sf->pdtaMap['phdr'];
foreach ($banks as $bankIdx => $bank) {
    echo "Bank: idx:$bankIdx".PHP_EOL;
    foreach ($bank as $presetIdx => $preset) {
        $presetName = $preset['PresetName'];
        $presetBagNdxStart = $preset['PresetBagNdx'];
        $presetBagNdxEnd = $preset['_PresetBagNdxEnd'];
        echo "  Preset: idx:$presetIdx name:'$presetName' bag:$presetBagNdxStart=>$presetBagNdxEnd".PHP_EOL;
        for ($presetBagNdx = $presetBagNdxStart ; $presetBagNdx <= $presetBagNdxEnd ; $presetBagNdx++) {
            echo "    presetBag: ndx:$presetBagNdx".PHP_EOL;
            $presetBag = $sf->pdtaMap['pbag'][$presetBagNdx];
            $genNdxStart = $presetBag['GenNdx'];
            $genNdxEnd   = $presetBag['_GenNdxEnd'];
            $modNdxStart = $presetBag['ModNdx'];
            $modNdxEnd   = $presetBag['_ModNdxEnd'];
            extractBag($presetBag, 'pgen', $genNdxStart, $genNdxEnd, $looptime);
        }                
    }
}

function extractBag($bag, $genChunkId, $genNdxStart, $genNdxEnd, $looptime) {
    global $sf;
    for ($genIdx = $genNdxStart ; $genIdx <= $genNdxEnd ; $genIdx++) {
        $gen = $sf->pdtaMap[$genChunkId][$genIdx];
//        echo "Gen: idx:$genIdx ".PHP_EOL;
        extractGenerator($gen, $looptime);
    }
}

function extractGenerator($gen, $looptime) {
    global $sf;
    $genOper = $gen['sfGenOper'];
    if ($genOper === 41) { // instrument
        $instIdx = $gen['Amount'];        
        $inst = $sf->pdtaMap['inst'][$instIdx];
        extractInstrument($inst, $looptime) ;
    } else if ($genOper === 53) { // sampleID
        $sampleIdx = $gen['Amount'];
        $sample = $sf->pdtaMap['shdr'][$sampleIdx];
        echo IO_SoundFont_Sample::string($sample).PHP_EOL;
        //
        $name  = $sample['SampleName'];
        $sampleRate = $sample['SampleRate'];

        $start = $sample['Start'];
        $end   = $sample['End'];
        $data = $sf->sfbk['sdta']['smpl']->data;
        if (is_null($looptime)) {
            $sampleData = substr($data, $start * 2 , ($end-$start + 1) * 2);
        } else {
            $startLoop = $sample['StartLoop'];
            $endLoop   = $sample['EndLoop'];
            $loopCount = $looptime * $sampleRate / ($endLoop - $startLoop /*+ 1*/);
            $loopCount = ceil($loopCount); // round up
            $sampleData = substr($data, $start * 2 , ($startLoop - $start) * 2);
            $sampleData .= str_repeat(substr($data, $startLoop * 2 , ($endLoop - $startLoop /*+ 1*/) * 2), $loopCount);
            $sampleData .= substr($data, ($endLoop /*+ 1*/) * 2 , ($end - $endLoop + 1) * 2);
        }
        $nChannel = 1; // 1:monoral, 2:stereo
        $sampleBits = 16; // 8 or 16
        $waveData = IO_SoundFont_WaveData::makeWaveData($sampleData, $nChannel, $sampleBits, $sampleRate);
        file_put_contents($name.".wav", $waveData);
    }
}

function extractInstrument($inst, $looptime) {
    global $sf;
    $instName = $inst['InstName'];
    $instBagNdxStart = $inst['InstBagNdx'];
    $instBagNdxEnd = $inst['_InstBagNdxEnd'];
    echo "name:'$instName' bagNdx: $instBagNdxStart=>$instBagNdxEnd".PHP_EOL;
    for ($instBagIdx = $instBagNdxStart ; $instBagIdx <= $instBagNdxEnd  ; $instBagIdx++) {
        echo "InstBag: idx:$instBagIdx".PHP_EOL;
        $instBag = $sf->pdtaMap['ibag'][$instBagIdx];
        $genNdxStart = $instBag['GenNdx'];
        $genNdxEnd   = $instBag['_GenNdxEnd'];
        $modNdxStart = $instBag['ModNdx'];
        $modNdxEnd   = $instBag['_ModNdxEnd'];
        extractBag($instBag, 'igen', $genNdxStart, $genNdxEnd, $looptime);
    }
}
