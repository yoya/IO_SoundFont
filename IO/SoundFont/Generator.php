<?php


$IO_SoundFont_Generator_Summary = array(
    0 => array('Name' => 'startAddrsOffset'),
    1 => array('Name' => 'endAddrsOffset'),
    2 => array('Name' => 'startLoopAddrsOffset'),
    3 => array('Name' => 'endloopAddrsOffset'),
    4 => array('Name' => 'startAddrsCoarseOffset'),
    5 => array('Name' => 'modLfoToPitch'),
    6 => array('Name' => 'vibLfoToPitch'),
    7 => array('Name' => 'modEnvToPitch'),
    8 => array('Name' => 'initialFilterFc'),
    9 => array('Name' => 'initialFilterQ'),
    10 => array('Name' => 'modLfoToFilterFc'),
    11 => array('Name' => 'modEnvToFilterFc'),
    12 => array('Name' => 'endAddrsCoarseOffset'),
    13 => array('Name' => 'modLfoToVolume'),
    14 => array('Name' => 'unused1'), // unused, reserved.
    15 => array('Name' => 'chorusEffectsSend'),
    16 => array('Name' => 'reverbEffectsSend'),
    17 => array('Name' => 'pan'),
    18 => array('Name' => 'unused2'), // unused, reserved.
    19 => array('Name' => 'unused3'), // unused, reserved.
    20 => array('Name' => 'unued4'),  // unused, reserved.
    21 => array('Name' => 'delayModLFO'),
    22 => array('Name' => 'freqModLFO'),
    23 => array('Name' => 'delayVibLFO'),
    24 => array('Name' => 'freqVibLFO'),
    25 => array('Name' => 'delayModEnv'),
    26 => array('Name' => 'attackModEnv'),
    27 => array('Name' => 'holdModEnv'),
    28 => array('Name' => 'decayModEnv'),
    29 => array('Name' => 'sustainModEnv'),
    30 => array('Name' => 'releaseModEnv'),
    31 => array('Name' => 'keynumToModEnvHold'),
    32 => array('Name' => 'keynumToMovEnvDecay'),
    33 => array('Name' => 'delayVolEnv'),
    34 => array('Name' => 'attackVolEnv'),
    35 => array('Name' => 'holdVolEnv'),
    36 => array('Name' => 'decayVolEnv'),
    37 => array('Name' => 'sustainVolEnv'),
    38 => array('Name' => 'releaseVolEnv'),
    39 => array('Name' => 'keunumToVolEnvHold'),
    40 => array('Name' => 'keynumToVolEnvDecay'),
    41 => array('Name' => 'instrument'), // link to instrument
    42 => array('Name' => 'reserved1'), // unused, reserved.
    43 => array('Name' => 'keyRange'),
    44 => array('Name' => 'velRange'),
    45 => array('Name' => 'startloopAddrsCoarseOffset'),
    46 => array('Name' => 'keynum'),
    47 => array('Name' => 'velocity'),
    48 => array('Name' => 'initialAttenuation'),
    49 => array('Name' => 'reserved2'), // reserved
    50 => array('Name' => 'endloopAddrsCoarseOffset'),
    51 => array('Name' => 'coarseTune'),
    52 => array('Name' => 'fineTune'),
    53 => array('Name' => 'sampleID'), // link to sample
    54 => array('Name' => 'sampleModes'),
    55 => array('Name' => 'reserved3'), // unused, reserved.
    56 => array('Name' => 'scaleTuning'),
    57 => array('Name' => 'exclusiveClass'),
    58 => array('Name' => 'overridingRootKey'),
    59 => array('Name' => 'unused5'), // unused, reserved.
    60 => array('Name' => 'endOper'), // unnsed, reserved.
    );

class IO_SoundFont_Generator {
    var $id = null;
    var $data = null;

    static function parse($reader) {
        $sfGen = array();
        $sfGenOper = $reader->getUI16LE();
        $sfGen['sfGenOper'] = $sfGenOper;
        if (($sfGenOper === 43) || ($sfGenOper === 44)) {
            $sfGen['Lo'] = $reader->getUI8();
            $sfGen['Hi'] = $reader->getUI8();
        } else {
            $sfGen['Amount'] = $reader->getUI16LE(); // XXX: SI ???
        }
        return $sfGen;
    }
    static function string($gen) {
        global $IO_SoundFont_Generator_Summary;
        $genOper = $gen['sfGenOper'];
        $ret = "genOper:$genOper";
        if (isset($IO_SoundFont_Generator_Summary[$genOper]['Name'])) {
            $ret .= '('.$IO_SoundFont_Generator_Summary[$genOper]['Name'].')';
        }
        if (isset($gen['Amount'])) {
            $ret .= " Amount:{$gen['Amount']}";
        } else if (isset($gen['Hi'])) {
            $ret .= " Lo:{$gen['Lo']} Hi:{$gen['Hi']}";
        }
        return $ret;
    }
    static function build(&$writer, $gen) {
        $writer->putUI16LE('Oper');
        $writer->putUI16LE($genBit);
    }
}
