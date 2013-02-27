<?php


$IO_SoundFont_Type_Sample_Type = array(
    1 => 'monoSample',
    2 => 'rightSample',
    4 => 'leftSample',
    8 => 'linkedSample',
    32769 => 'RomMonoSample',
    32770 => 'RomRightSample',
    );

class IO_SoundFont_Type_Sample {
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
    static function string($sample) {
        global $IO_SoundFont_Type_Sample_Type;
        $sampleName      = $sample['SampleName'];
        $start           = $sample['Start'];
        $startLoop       = $sample['StartLoop'];
        $endLoop         = $sample['EndLoop'];
        $end             = $sample['End'];
        $sampleRate      = $sample['SampleRate'];
        $originalPitch   = $sample['OriginalPitch'];
        $pitchCorrection = $sample['PitchCorrection'];
        $sampleLink      = $sample['SampleLink'];
        $sampleType      = $sample['SampleType'];
        $ret = "name:'$sampleName' start:$start loop:$startLoop=>$endLoop end:$end sampleRate:$sampleRate originalPitch:originalPitch pitchCorrection:$pitchCorrection link:$sampleLink type:sampleType";
        if (isset($IO_SoundFont_Type_Sample_Type[$sampleType])) {
            $ret .= '('.$IO_SoundFont_Type_Sample_Type[$sampleType].')';
        }
        return $ret;
    }
    static function build(&$writer, $sample) {
        $writer->putUI16LE('Oper');
        $writer->putUI16LE($sample);
    }
}
