<?php

class IO_SoundFont_Chunk {
    var $id = null;
    var $data = null;
    function getDataSize() {
        return strlen($this->data);
    }
    function parse($reader) {
        $this->id = $reader->getData(4); // fourcc
        $size = $reader->getUI32LE();
        $this->data = $reader->getData($size);
    }
    function parseChunkData() {
        $detailData = array();
        $id = $this->id;
        $reader = new IO_Bit();
        $reader->input($this->data);
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
            default:
                return ; // nothing to do
            break;
        }
        $this->$id = $detailData;
    }
    function dump() {
        $id = $this->id;
        $data = $this->data;
        $size = strlen($data);
        echo "    $id: (size:$size)".PHP_EOL;
        $this->parseChunkData();
        if (isset($this->$id)) {
            $this->dumpChunkData();
        }
        
    }
    function dumpChunkData() {
        $id = $this->id;
        $detailData = $this->$id;
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
            // pdta sub-chunks
        case 'phdr':
        case 'pbag':
            foreach ($detailData as $idx => $entry) {
                echo "  [$idx]";
                foreach ($entry as $key => $value) {
                    echo "  $key: $value ";
                }
                echo PHP_EOL;
            }
            break;
        default:
            echo "Unknown Data".PHP_EOL;
            break;
        }
    }
}
