<?php

require_once dirname(__FILE__).'/Generator.php';
require_once dirname(__FILE__).'/Modulator.php';
require_once dirname(__FILE__).'/Sample.php';

class IO_SoundFont_Chunk {
    var $id = null;
    var $data = null;
    var $detail = null;
    var $_detailParsed = null; // detail as parsed, to see if it was edited
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
        $data = $this->data; // kept for build
        switch($id) {
            // INFO sub-chunks
        case 'ifil':
            $detailData['sfVersionMajor'] = $reader->getUI16LE();
            $detailData['sfVersionMinor'] = $reader->getUI16LE();
            break;
        case 'INAM':
            $detailData['sfName'] = $data; // String
            break;
        case 'isng':
            $detailData['sfEngine'] = $data; // String
            break;
        case 'IENG':
            $detailData['sfEngineers'] = $data; // String
            break;
        case 'ISFT':
            $detailData['sfTools'] = $data; // String
            break;
        case 'ICMT':
            $detailData['Comments'] = $data; // String
            break;
        case 'ICOP':
            $detailData['Copyright'] = $data; // String
            break;
            // sdta sub-chunks
        case 'smpl':
            $detailData['Samples'] = $data; // String
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
                $sfMod['sfModSrcOper'] = IO_SoundFont_Modulator::parse($reader);
                $sfMod['sfModDestOper'] = $reader->getUI16LE();
                $sfMod['modAmount'] = $reader->getSI16LE();
                $sfMod['sfModAmtSrcOper'] = IO_SoundFont_Modulator::parse($reader);
                $sfMod['sfModTransOper'] = $reader->getUI16LE();
                $detailData []= $sfMod;
            }
            break;
        case 'pgen':
        case 'igen':
            $detailData = array();
            while ($reader->hasNextData()) {
                
                $detailData []= IO_SoundFont_Generator::parse($reader);
            }
            break;
        case 'inst':
            $detailData = array();
            while ($reader->hasNextData()) {
                $sfInst = array();
                $InstName = explode("\0", $reader->getData(20));
                $sfInst['InstName'] = $InstName[0];
                $sfInst['InstBagNdx'] = $reader->getUI16LE();
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
        $this->_detailParsed = $detailData;
    }
    // record keys starting with '_' are added by analyze, not chunk data
    static function stripInternalKeys($detail) {
        foreach ($detail as $idx => $entry) {
            if (is_array($entry)) {
                foreach ($entry as $key => $value) {
                    if (is_string($key) && ($key[0] === '_')) {
                        unset($detail[$idx][$key]);
                    }
                }
            }
        }
        return $detail;
    }
    // chunk data for build: the raw data, or the data built from detail
    // when detail was edited after parseChunkData (or set without it)
    function buildChunkData() {
        if (is_null($this->detail) ||
            ((is_null($this->_detailParsed) === false) &&
             (self::stripInternalKeys($this->detail) === self::stripInternalKeys($this->_detailParsed)))) {
            return $this->data;
        }
        $detailData = $this->detail;
        $writer = new IO_Bit();
        switch ($this->id) {
            // INFO sub-chunks
        case 'ifil':
            $writer->putUI16LE($detailData['sfVersionMajor']);
            $writer->putUI16LE($detailData['sfVersionMinor']);
            break;
        case 'INAM':
        case 'isng':
        case 'IENG':
        case 'ISFT':
        case 'ICMT':
        case 'ICOP':
            $value = reset($detailData);
            // zstr: NUL terminated, even length
            if (substr($value, -1) !== "\0") {
                $value .= "\0";
            }
            if (strlen($value) % 2) {
                $value .= "\0";
            }
            $writer->putData($value);
            break;
            // sdta sub-chunks
        case 'smpl':
            $writer->putData($detailData['Samples']);
            break;
            // pdta sub-chunks
        case 'phdr':
            foreach ($detailData as $preset) {
                $writer->putData($preset['PresetName'], 20);
                $writer->putUI16LE($preset['Preset']);
                $writer->putUI16LE($preset['Bank']);
                $writer->putUI16LE($preset['PresetBagNdx']);
                $writer->putUI32LE($preset['Library']);
                $writer->putUI32LE($preset['Genre']);
                $writer->putUI32LE($preset['MorphologyGenre']);
            }
            break;
        case 'pbag':
        case 'ibag':
            foreach ($detailData as $bag) {
                $writer->putUI16LE($bag['GenNdx']);
                $writer->putUI16LE($bag['ModNdx']);
            }
            break;
        case 'pmod':
        case 'imod':
            foreach ($detailData as $sfMod) {
                IO_SoundFont_Modulator::build($writer, $sfMod['sfModSrcOper']);
                $writer->putUI16LE($sfMod['sfModDestOper']);
                $writer->putSI16LE($sfMod['modAmount']);
                IO_SoundFont_Modulator::build($writer, $sfMod['sfModAmtSrcOper']);
                $writer->putUI16LE($sfMod['sfModTransOper']);
            }
            break;
        case 'pgen':
        case 'igen':
            foreach ($detailData as $sfGen) {
                IO_SoundFont_Generator::build($writer, $sfGen);
            }
            break;
        case 'inst':
            foreach ($detailData as $sfInst) {
                $writer->putData($sfInst['InstName'], 20);
                $writer->putUI16LE($sfInst['InstBagNdx']);
            }
            break;
        case 'shdr':
            foreach ($detailData as $sfSample) {
                IO_SoundFont_Sample::build($writer, $sfSample);
            }
            break;
        default:
            return $this->data;
        }
        return $writer->output();
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
                $value = rtrim($value, "\0"); // zstr padding (detail keeps it)
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
            $lastIdx = count($detail) - 1; // terminal 'EOP' record
            foreach ($detail as $idx => $entry) {
                if ($idx === $lastIdx) {
                    break;
                }
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
                    $entry['_InstBagNdxEnd'] = count($sfbk['pdta']['ibag']->detail) - 1;
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
