<?php
  /*
   http://pwiki.awm.jp/~yoya/?SoundFont   
   */

require_once 'IO/Bit.php';
require_once 'IO/SoundFont/Exception.php';
require_once 'IO/SoundFont/Chunk.php';

class IO_SoundFont {
    function parse($sfdata) {
        $reader = new IO_Bit();
        $reader->input($sfdata);
        $length = strlen($sfdata);
        $id = $reader->getData(4); // 'RIFF'
        if ($id !== 'RIFF') {
            throw new IO_SoundFont_Exception("non RIFF fourcc");
        }
        $size = $reader->getUI32LE();
        if ($length !== $size + 8) {
            fputs(STDERR, "parse: illegal length($length) and RIFF size($size)\n");
        }
        $id = $reader->getData(4); // 'sfbk'
        if ($id !== 'sfbk') {
            throw new IO_SoundFont_Exception("non sfbk fourcc");
        }
        $info = $this->parseChunkLIST($reader);
        $stda = $this->parseChunkLIST($reader);
        $pdta = $this->parseChunkLIST($reader);
        $this->sfbk = array(
            'INFO' => $info, 'sdta' => $stda, 'pdta' => $pdta
            );
        return true;
    }
    
    function parseChunkLIST($reader) {
        $id = $reader->getData(4); // fourcc (LIST)
        if ($id !== 'LIST') {
            throw new IO_SoundFont_Exception("non LIST fourcc");
        }
        $size_LIST = $reader->getUI32LE();
        $id_LIST = $reader->getData(4); // 'INFO' or 'stda' or 'pdta'
        $chunkLIST = array();
        for ($remain = $size_LIST - 4; $remain >= 8 ; $remain -= (8 + $size)) {
            $chunk = new IO_SoundFont_Chunk();
            $chunk->parse($reader);
            $chunkLIST []= $chunk;
            $size = $chunk->getDataSize();
        }
        /*
        if ($remain > 0) {
            fprintf(STDERR, "parseChunkLIST: remain(%d) > 0 data:%s)\n",
            $remain, $reader->getData($remain));
            
        }
        */
        return $chunkLIST;
    }

    function dump() {
        echo "RIFF=>sfbk".PHP_EOL;
        foreach ($this->sfbk as $list_id => $chunkLIST) {
            echo "  LIST=>$list_id".PHP_EOL;
            foreach ($chunkLIST as $chunk) {
                $chunk->dump();
            }
        }
    }

    function build() {
        $writer = new IO_Bit();
        $writer->putData('RIFF');
        $writer->putUI32LE(0); //
        $writer->putData('sfbk');
        $this->parseChunkLIST($writer, 'INFO', $this->sfbk['INFO']);
        $this->parseChunkLIST($writer, 'stda', $this->sfbk['stda']);
        $this->parseChunkLIST($writer, 'pdta', $this->sfbk['pdta']);
    }
    function buildChunkLIST($writer, $id_LIST, $chunkLIST) {
        $writer->putData('LIST');
        list($startOfSize, $dummy) = $writer->getOffset();
        $writer->putUI32LE(0); //
        $writer->putData($id_LIST);
        foreach ($chunkLIST as $chunk) {
            if (strlen($chunk['ID']) !== 4) {
                throw new IO_SoundFont_Exception("Illegal ID({$chunk['ID']}) found");
            }
            $writer->putData($chunk['ID']); 
            $writer->putUI32LE(strlen($chunk['Data']));
            $writer->putData($chunk['Data']);
        }
        list($nextOfData, $dummy) = $writer->getOffset();
        $size = $nextOfData - $startOfSize - 4;
        $writer->setUI32LE($size , $startOfSize);
        $writer->setOffset($nextOfData, 0);
    }
}
