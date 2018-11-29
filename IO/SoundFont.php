<?php
  /*
   http://pwiki.awm.jp/~yoya/?SoundFont   
   */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}
require_once dirname(__FILE__).'/SoundFont/Exception.php';
require_once dirname(__FILE__).'/SoundFont/Chunk.php';
require_once dirname(__FILE__).'/SoundFont/Generator.php';
require_once dirname(__FILE__).'/SoundFont/Modulator.php';
require_once dirname(__FILE__).'/SoundFont/Sample.php';

class IO_SoundFont {
    var $pdtaMap = array(); // for analyze
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
        $sdta = $this->parseChunkLIST($reader);
        $pdta = $this->parseChunkLIST($reader);
        $this->sfbk = array(
            'INFO' => $info, 'sdta' => $sdta, 'pdta' => $pdta
            );
        return true;
    }
    
    function parseChunkLIST($reader) {
        $id = $reader->getData(4); // fourcc (LIST)
        if ($id !== 'LIST') {
            throw new IO_SoundFont_Exception("non LIST fourcc");
        }
        $size_LIST = $reader->getUI32LE();
        $id_LIST = $reader->getData(4); // 'INFO' or 'sdta' or 'pdta'
        $chunkLIST = array();
        for ($remain = $size_LIST - 4; $remain >= 8 ; $remain -= (8 + $size)) {
            $chunk = new IO_SoundFont_Chunk();
            $chunk->parse($reader);
            $chunkLIST[$chunk->id]= $chunk;
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
        $this->buildChunkLIST($writer, 'INFO', $this->sfbk['INFO']);
        $this->buildChunkLIST($writer, 'sdta', $this->sfbk['sdta']);
        $this->buildChunkLIST($writer, 'pdta', $this->sfbk['pdta']);
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

    function analyze() {
        $pdtaMap = array();
        foreach ($this->sfbk['pdta'] as $id => $pdtaEntry) {
            $this->sfbk['pdta'][$id]->parseChunkData();
        }
        foreach ($this->sfbk['pdta'] as $id => $pdtaEntry) {
            $this->pdtaMap[$id] = $pdtaEntry->analyze($this->sfbk);
        }
    }
    function tree() {
        if (isset($this->banks) === false) {
            $this->analyze();
        }
        $banks = $this->pdtaMap['phdr'];
        foreach ($banks as $bankIdx => $bank) {
            echo "Bank: idx:$bankIdx".PHP_EOL;
            foreach ($bank as $presetIdx => $preset) {
                $presetName = $preset['PresetName'];
                $presetBagNdxStart = $preset['PresetBagNdx'];
                $presetBagNdxEnd = $preset['_PresetBagNdxEnd'];
                echo "  Preset: idx:$presetIdx name:'$presetName' bag:$presetBagNdxStart=>$presetBagNdxEnd".PHP_EOL;
                for ($presetBagNdx = $presetBagNdxStart ; $presetBagNdx <= $presetBagNdxEnd ; $presetBagNdx++) {
                    echo "    presetBag: ndx:$presetBagNdx".PHP_EOL;
                    $presetBag = $this->pdtaMap['pbag'][$presetBagNdx];
                    $genNdxStart = $presetBag['GenNdx'];
                    $genNdxEnd   = $presetBag['_GenNdxEnd'];
                    $modNdxStart = $presetBag['ModNdx'];
                    $modNdxEnd   = $presetBag['_ModNdxEnd'];
                    $this->bagTree($presetBag, 'pgen', $genNdxStart, $genNdxEnd, 'pmod', $modNdxStart, $modNdxEnd, 3);
                }                
            }
        }
    }
    function bagTree($bag, $genChunkId, $genNdxStart, $genNdxEnd, $modChunkId, $modNdxStart, $modNdxEnd, $indentLevel) {
        $indentSpace = str_repeat("  ", $indentLevel);
        for ($genIdx = $genNdxStart ; $genIdx <= $genNdxEnd ; $genIdx++) {
            $gen = $this->pdtaMap[$genChunkId][$genIdx];
            echo $indentSpace."Gen: idx:$genIdx ".PHP_EOL;
            $this->generatorTree($gen, $indentLevel + 1);
        }
        for ($modIdx = $modNdxStart ; $modIdx <= $modNdxEnd ; $modIdx++) {
            echo $indentSpace."Mod: idx:$modIdx ".PHP_EOL;
            $mod = $this->pdtaMap[$modChunkId][$modIdx];
            $this->modulatorTree($mod, $indentLevel + 1);
        }
        
    }
    function generatorTree($gen, $indentLevel) {
        $indentSpace = str_repeat("  ", $indentLevel);
        echo $indentSpace.IO_SoundFont_Generator::string($gen).PHP_EOL;
        $genOper = $gen['sfGenOper'];
        if ($genOper === 41) { // instrument
            $instIdx = $gen['Amount'];
            $inst = $this->pdtaMap['inst'][$instIdx];
            $this->instrumentTree($inst, $indentLevel + 1) ;
        } else if ($genOper === 53) { // sampleID
            $sampleIdx = $gen['Amount'];
            $sample = $this->pdtaMap['shdr'][$sampleIdx];
            echo $indentSpace.IO_SoundFont_Sample::string($sample).PHP_EOL;           
        }
    }
    function modulatorTree($mod, $indentLevel) {
        global $IO_SoundFont_Generator_Summary;
        $indentSpace = str_repeat("  ", $indentLevel);
        $destOper = $mod['sfModDestOper'] ;
        $amount = $mod['modAmount'] ;
        $transOper = $mod['sfModTransOper'] ;
        $text = IO_SoundFont_Modulator::string($mod['sfModSrcOper']);
        $text .= " DestOper:$destOper";
        if (isset($IO_SoundFont_Generator_Summary[$destOper]['Name'])) {
            $text .= '('.$IO_SoundFont_Generator_Summary[$destOper]['Name'].')';
        }
        $text .= " Amount:$amount";
        $text .= ' AmdSrcOper:'.IO_SoundFont_Modulator::string($mod['sfModAmtSrcOper']);
        $text .= " TransOper:$transOper";
        echo $indentSpace.$text.PHP_EOL;
    }
    function instrumentTree($inst, $indentLevel) {
        $indentSpace = str_repeat("  ", $indentLevel);
        $instName = $inst['InstName'];
        $instBagNdxStart = $inst['InstBagNdx'];
        $instBagNdxEnd = $inst['_InstBagNdxEnd'];
        echo $indentSpace."name:'$instName' bagNdx: $instBagNdxStart=>$instBagNdxEnd".PHP_EOL;
        for ($instBagIdx = $instBagNdxStart ; $instBagIdx <= $instBagNdxEnd  ; $instBagIdx++) {
            echo $indentSpace."  InstBag: idx:$instBagIdx".PHP_EOL;
            $instBag = $this->pdtaMap['ibag'][$instBagIdx];
            $genNdxStart = $instBag['GenNdx'];
            $genNdxEnd   = $instBag['_GenNdxEnd'];
            $modNdxStart = $instBag['ModNdx'];
            $modNdxEnd   = $instBag['_ModNdxEnd'];
            $this->bagTree($instBag, 'igen', $genNdxStart, $genNdxEnd, 'imod', $modNdxStart, $modNdxEnd, $indentLevel + 2);
        }
    }
}
