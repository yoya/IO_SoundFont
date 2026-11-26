<?php

global $IO_SoundFont_Modulator_Index; // also when loaded inside a function (Composer autoload)
$IO_SoundFont_Modulator_Index = array( // C:0
    0 => 'No Controler',
    2 => 'Note-On Velocity',
    3 => 'Note-On Key Number',
    10 => 'Poly Pressure',
    13 => 'Channel Pressure',
    14 => 'Pitch Wheel',
    16 => 'Pitch Wheel Sensitivity',
    );

class IO_SoundFont_Modulator {
    var $id = null;
    var $data = null;

    static function parse($reader) {
        $sfModBits = $reader->getUI16LE();
        $type = $sfModBits >> 10;
        $p = ($sfModBits >> 9) & 1;
        $d = ($sfModBits >> 8) & 1;
        $cc = ($sfModBits >> 7) & 1;
        $index = $sfModBits & 0x7f;
        $sfMod = array(
            'Type' => $type,
            'P' => $p, 'D' => $d, 'CC' => $cc,
            'Index' => $index,
            );
        return $sfMod;
    }
    static function string($mod) {
        global $IO_SoundFont_Modulator_Index;
        $type = $mod['Type'];
        $p = $mod['P'];
        $d = $mod['D'];
        $cc = $mod['CC'];
        $index = $mod['Index'];
        $ret = "Type:$type P:$p D:$d CC:$cc Index:$index";
        if (($cc === 0) && isset($IO_SoundFont_Modulator_Index[$index])) {
            $ret .= '('.$IO_SoundFont_Modulator_Index[$index].')';
        }
        return $ret;
    }
    static function build(&$writer, $mod) {
        $sfModBits = ($mod['Type'] << 10) | ($mod['P'] << 9) | ($mod['D'] << 8) |
                     ($mod['CC'] << 7) | ($mod['Index'] & 0x7f);
        $writer->putUI16LE($sfModBits);
    }
}
