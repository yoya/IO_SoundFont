<?php

require_once 'IO/SoundFont.php';
require_once 'sample/makeWaveData.php';

if ($argc !== 2) {
    echo "Usage: php sfextractsample.php <sffile>".PHP_EOL;
    echo "ex) php sample/sfextractsample.php emuaps_8mb.sf2".PHP_EOL;
    exit(1);
}

$sfdata = file_get_contents($argv[1]);
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
            extractBag($presetBag, 'pgen', $genNdxStart, $genNdxEnd);
        }                
    }
}

function extractBag($bag, $genChunkId, $genNdxStart, $genNdxEnd) {
    global $sf;
    for ($genIdx = $genNdxStart ; $genIdx <= $genNdxEnd ; $genIdx++) {
        $gen = $sf->pdtaMap[$genChunkId][$genIdx];
//        echo "Gen: idx:$genIdx ".PHP_EOL;
        extractGenerator($gen);
    }
}

function extractGenerator($gen) {
    global $sf;
    $genOper = $gen['sfGenOper'];
    if ($genOper === 41) { // instrument
        $instIdx = $gen['Amount'];        
        $inst = $sf->pdtaMap['inst'][$instIdx];
        extractInstrument($inst) ;
    } else if ($genOper === 53) { // sampleID
        $sampleIdx = $gen['Amount'];
        $sample = $sf->pdtaMap['shdr'][$sampleIdx];
        echo IO_SoundFont_Sample::string($sample).PHP_EOL;
        //
        $name = $sample['SampleName'];
        $start = $sample['Start'];
        $end = $sample['End'];
        $sampleData = substr($sf->sfbk['sdta']['smpl']->data, $start * 2 , ($end-$start + 1) * 2);
        $sampleRate = $sample['SampleRate'];
        $nChannel = 1; // 1:monoral, 2:stereo
        $sampleBits = 16; // 8 or 16
        $waveData = makeWaveData($sampleData, $nChannel, $sampleBits, $sampleRate);
        file_put_contents($name.".wav", $waveData);
    }
}

function extractInstrument($inst) {
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
        extractBag($instBag, 'igen', $genNdxStart, $genNdxEnd);
    }
}
