<?php


$IO_SoundFont_Type_Generator_Summary = array(
    0 => array('Name' => 'startAddrsOffset'),
    41 => array('Name' => 'instrument'),
    53 => array('Name' => 'sampleID'),
    );

class IO_SoundFont_Type_Generator {
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
        global $IO_SoundFont_Type_Generator_Summary;
        $genOper = $gen['sfGenOper'];
        $ret = "genOper:$genOper";
        if (isset($IO_SoundFont_Type_Generator_Summary[$genOper]['Name'])) {
            $ret .= '('.$IO_SoundFont_Type_Generator_Summary[$genOper]['Name'].')';
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
