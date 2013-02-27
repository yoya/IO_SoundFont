<?php

require_once 'IO/SoundFont/Type/Generator.php';
require_once 'IO/SoundFont/Type/Modulator.php';
require_once 'IO/SoundFont/Type/Sample.php';

class IO_SoundFont_Chunk {
    var $id = null;
    var $data = null;
    var $detail = null;
    function getDataSize() {
        return strlen($this->data);
    }
    function parse($reader) {
        $this->id = $reader->getData(4); // fourcc
        $size = $reader->getUI32LE();
        $this->data = $reader->getData($size);
    }
    function parseChunkData() {
        if (isset($this->detail)) {
            return ;
        }
        $detailData = array();
        $id = $this->id;
        $reader = new IO_Bit();
        $reader->input($this->data);
        $this->data = null; // XXX
        switch($id) {
            // INFO sub-chunks
        case 'ifil':
            $detailData['sfVersionMajor'] = $reader->getUI16LE();
            $detailData['sfVersionMinor'] = $reader->getUI16LE();
            break;
        case 'INAM':
            $detailData['sfName'] = $this->data; // String
            break;
        case 'isng':
            $detailData['sfEngine'] = $this->data; // String
            break;
        case 'IENG':
            $detailData['sfEngineers'] = $this->data; // String
            break;
        case 'ISFT':
            $detailData['sfTools'] = $this->data; // String
            break;
        case 'ICMT':
            $detailData['Comments'] = $this->data; // String
            break;
        case 'ICOP':
            $detailData['Copyright'] = $this->data; // String
            break;
            // sdta sub-chunks
        case 'smpl':
            $detailData['Samples'] = $this->data; // String
            break;
            // pdta sub-chunks
        case 'phdr':
            $detailData = array();
            while ($reader->hasNextData()) {
                $preset = array();
                $presetName = explode("\0", $reader->getData(20));
                $preset['PresetName'] = $presetName[0];
                $preset['Preset'] = $reader->getUI16LE();
                $preset['Bank'] = $reader->getUI16LE();
                $preset['PresetBagNdx'] = $reader->getUI16LE();
                $preset['Library'] = $reader->getUI32LE();
                $preset['Genre'] = $reader->getUI32LE();
                $preset['MorphologyGenre'] = $reader->getUI32LE();
                $detailData []= $preset;
            }
            break;
        case 'pbag':
            $detailData = array();
            while ($reader->hasNextData()) {
                $presetBag = array();
                $presetBag['GenNdx'] = $reader->getUI16LE();
                $presetBag['ModNdx'] = $reader->getUI16LE();
                $detailData []= $presetBag;
            }
            break;
        case 'pmod':
        case 'imod':
            $detailData = array();
            while ($reader->hasNextData()) {
                $sfMod = array();
                $sfMod['sfModSrcOper'] = $reader->getUI16LE();
                $sfMod['sfModDestOper'] = $reader->getUI16LE();
                $sfMod['modAmount'] = $reader->getSI16LE();
                $sfMod['sfModAmtSrcOper'] = $reader->getUI16LE();
                $sfMod['sfModTransOper'] = $reader->getUI16LE();
                $detailData []= $sfMod;
            }
            break;
        case 'pgen':
        case 'igen':
            $detailData = array();
            while ($reader->hasNextData()) {
                $detailData []= IO_SoundFont_Type_Generator::parse($reader);
            }
            break;
        case 'inst':
            $detailData = array();
            while ($reader->hasNextData()) {
                $sfInst = array();
                $InstName = explode("\0", $reader->getData(20));
                $sfInst['InstName'] = $InstName[0];
                $sfInst['InstBagNdx'] = $reader->getSI16LE();;
                $detailData []= $sfInst;
            }
            break;
        case 'ibag':
            $detailData = array();
            while ($reader->hasNextData()) {
                $presetBag = array();
                $presetBag['GenNdx'] = $reader->getUI16LE();
                $presetBag['ModNdx'] = $reader->getUI16LE();
                $detailData []= $presetBag;
            }
            break;
        case 'shdr':
            $detailData = array();
            while ($reader->hasNextData()) {
                $sfSample = array();
                $SampleName = explode("\0", $reader->getData(20));
                $sfSample['SampleName'] = $SampleName[0];
                $sfSample['Start'] = $reader->getUI32LE();
                $sfSample['End'] = $reader->getUI32LE();
                $sfSample['StartLoop'] = $reader->getUI32LE();
                $sfSample['EndLoop'] = $reader->getUI32LE();
                $sfSample['SampleRate'] = $reader->getUI32LE();
                $sfSample['OriginalPitch'] = $reader->getUI8();
                $sfSample['PitchCorrection'] = $reader->getSI8();
                $sfSample['SampleLink'] = $reader->getUI16LE();
                $sfSample['SampleType'] = $reader->getUI16LE();
                $detailData []= $sfSample;
            }
            break;
        default:
                return ; // nothing to do
            break;
        }
        $this->detail = $detailData;
    }
    function dump() {
        $id = $this->id;
        $data = $this->data;
        $size = strlen($data);
        echo "    $id: (size:$size)".PHP_EOL;
        $this->parseChunkData();
        if (isset($this->detail)) {
            $this->dumpChunkData();
        }
    }
    function dumpChunkData() {
        $id = $this->id;
        $detailData = $this->detail;
        switch ($id) {
            // INFO sub-chunks
        case 'ifil':
            $version = sprintf("%d.%02d", $detailData['sfVersionMajor'],
                               $detailData['sfVersionMinor']);
            echo "      sfVersion: $version".PHP_EOL;
            break;
        case 'INAM':
        case 'isng':
        case 'isng':
        case 'IENG':
        case 'ISFT':
        case 'ICMT':
        case 'ICOP':
            foreach ($detailData as $key => $value) {
                echo "      $key: $value".PHP_EOL;
            }
            break;
            // sdta sub-chunks
        case 'smpl':
            $samples_2 = strlen($detailData['Samples']) / 2;
            echo "      Samples/2: $samples_2 (16bit Samples)".PHP_EOL;
            break;
            // pdta sub-chunks
        case 'phdr':
        case 'pbag':
        case 'pmod':
        case 'pgen':
        case 'inst':
        case 'ibag':
        case 'imod':
        case 'igen':
        case 'shdr':
            foreach ($detailData as $idx => $entry) {
                echo "  [$idx]";
                foreach ($entry as $key => $value) {
                    if (is_array($value)) {
                        $kvlist = array();
                        foreach ($value as $k => $v) {
                            $kvlist []= "$k:$v";
                        }
                        echo "  $key:[".join(",", $kvlist)."]";
                    } else {
                        echo "  $key: $value";
                    }
                }
                echo PHP_EOL;
            }
            break;
        default:
            echo "Unknown Data".PHP_EOL;
            break;
        }
    }
    function analyze($sfbk) {
        
        $id = $this->id;
        $detail =& $this->detail;
        switch ($id) {
        case 'phdr':
            $banks = array();
            foreach ($detail as $idx => $entry) {
                $bankId = $entry['Bank'];
                $presetId = $entry['Preset'];
                if (isset($banks[$bankId]) === false) {
                    $banks[$bankId] = array();
                }
                if (isset($detail[$idx+1])) {
                    $entry['_PresetBagNdxEnd'] = $detail[$idx+1]['PresetBagNdx'] - 1;
                } else {
                    $entry['_PresetBagNdxEnd'] = count($sfbk['pdta']['pbag']->detail) - 1;
                }
                $banks[$bankId][$presetId] = $entry;
            }
            return $banks; // return !!!
        case 'pbag':
            foreach ($detail as $idx => &$entry) {
                if (isset($detail[$idx+1])) {
                    $entry['_GenNdxEnd'] = $detail[$idx+1]['GenNdx'] - 1;
                    $entry['_ModNdxEnd'] = $detail[$idx+1]['ModNdx'] - 1;
                } else {
                    $entry['_GenNdxEnd'] = count($sfbk['pdta']['pgen']->detail) - 1;
                    $entry['_ModNdxEnd'] = count($sfbk['pdta']['pmod']->detail) - 1;
                }
            }
            break;
        case 'inst':
            foreach ($detail as $idx => &$entry) {
                if (isset($detail[$idx+1])) {
                    $entry['_InstBagNdxEnd'] = $detail[$idx+1]['InstBagNdx'] - 1;
                } else {
                    $entry['_InstBagNdxEnd'] = count($sfbk['pdta']['pbag']->detail) - 1;
                }
            }
            break;
        case 'ibag':
            foreach ($detail as $idx => &$entry) {
                if (isset($detail[$idx+1])) {
                    $entry['_GenNdxEnd'] = $detail[$idx+1]['GenNdx'] - 1;
                    $entry['_ModNdxEnd'] = $detail[$idx+1]['ModNdx'] - 1;
                } else {
                    $entry['_GenNdxEnd'] = count($sfbk['pdta']['igen']->detail) - 1;
                    $entry['_ModNdxEnd'] = count($sfbk['pdta']['imod']->detail) - 1;
                }
            }
            break;
        }
        return $this->detail;
    }
}
