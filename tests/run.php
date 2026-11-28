<?php
/*
 * regression tests (no dependency)
 * usage: php tests/run.php
 *   IO_Bit is loaded from vendor/ (composer install) or include_path.
 */

$root = dirname(dirname(__FILE__));
if (is_readable($root.'/vendor/autoload.php')) {
    require_once $root.'/vendor/autoload.php';
}
set_include_path($root.PATH_SEPARATOR.get_include_path());
require_once 'IO/SoundFont.php';
require_once 'IO/SoundFont/WaveData.php';

$failed = 0;
$passed = 0;

function check($name, $expect, $actual) {
    global $failed, $passed;
    if ($expect === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL: $name".PHP_EOL;
    echo "  expect: ".var_export($expect, true).PHP_EOL;
    echo "  actual: ".var_export($actual, true).PHP_EOL;
}

function chunk($id, $data) {
    return $id.pack('V', strlen($data)).$data;
}

function chunkLIST($id, $data) {
    return 'LIST'.pack('V', strlen($data) + 4).$id.$data;
}

function name20($name) {
    return str_pad($name, 20, "\0");
}

/*
 * synthetic SF2: preset 'Piano' (0/0) -> instrument 'Inst' -> sample 'Smp'
 * The terminal records (EOP/EOI/EOS) follow the spec recommendation (zeros).
 */
function sf2($opts = array()) {
    $o = $opts + array('startLoop' => 8, 'endLoop' => 24, 'eop' => 0,
                       'info' => chunk('INAM', "TestBank\0\0").chunk('ICMT', "hello\0"));
    $info = chunk('ifil', pack('vv', 2, 1)).$o['info'];
    $smpl = '';
    for ($i = 0; $i < 64; $i++) {
        $smpl .= pack('v', $i * 100);
    }
    $phdr = name20('Piano').pack('vvvVVV', 0, 0, 0, 0, 0, 0)
          . name20('EOP').pack('vvvVVV', $o['eop'], $o['eop'], 1, 0, 0, 0);
    $pbag = pack('vv', 0, 0).pack('vv', 1, 1);
    // src = MIDI CC 91, dest = 16 (reverbEffectsSend), amount = 200
    $pmod = pack('vvsvv', 0x0080 | 91, 16, 200, 0, 0).pack('vvsvv', 0, 0, 0, 0, 0);
    $pgen = pack('vv', 41, 0).pack('vv', 0, 0);
    $inst = name20('Inst').pack('v', 0).name20('EOI').pack('v', 1);
    $ibag = pack('vv', 0, 0).pack('vv', 1, 0);
    $imod = pack('vvsvv', 0, 0, 0, 0, 0);
    $igen = pack('vv', 53, 0).pack('vv', 0, 0);
    $shdr = name20('Smp').pack('VVVVVCcvv', 0, 32, $o['startLoop'], $o['endLoop'], 22050, 60, 0, 0, 1);
    if (isset($o['twoSamples'])) { // two instrument zones, the 2nd sample is named $o['twoSamples']
        $inst = name20('Inst').pack('v', 0).name20('EOI').pack('v', 2);
        $ibag = pack('vv', 0, 0).pack('vv', 1, 0).pack('vv', 2, 0);
        $igen = pack('vv', 53, 0).pack('vv', 53, 1).pack('vv', 0, 0);
        $shdr .= name20($o['twoSamples']).pack('VVVVVCcvv', 33, 63, 33, 33, 22050, 60, 0, 0, 1);
    }
    $shdr .= name20('EOS').pack('VVVVVCcvv', 0, 0, 0, 0, 0, 0, 0, 0, 0);
    $pdta = chunk('phdr', $phdr).chunk('pbag', $pbag).chunk('pmod', $pmod).chunk('pgen', $pgen)
          . chunk('inst', $inst).chunk('ibag', $ibag).chunk('imod', $imod).chunk('igen', $igen)
          . chunk('shdr', $shdr);
    $body = 'sfbk'.chunkLIST('INFO', $info).chunkLIST('sdta', chunk('smpl', $smpl)).chunkLIST('pdta', $pdta);
    return 'RIFF'.pack('V', strlen($body)).$body;
}

// run php in a child process in directory $cwd
function runPHP($args, $cwd) {
    $cmd = array(PHP_BINARY, '-d', 'display_errors=stderr',
                 '-d', 'include_path='.get_include_path());
    $cmd = implode(' ', array_map('escapeshellarg', array_merge($cmd, $args)));
    $proc = proc_open($cmd, array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w')), $pipes, $cwd);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($proc);
    return array($status, $stdout, $stderr);
}

function parseSF2($data) {
    $sf = new IO_SoundFont();
    $sf->parse($data);
    return $sf;
}

/*
 * INFO strings and sample data are kept in detail
 */
$sf = parseSF2(sf2());
$sf->sfbk['INFO']['INAM']->parseChunkData();
check('INAM sfName', "TestBank\0\0", $sf->sfbk['INFO']['INAM']->detail['sfName']);
$sf->sfbk['INFO']['ICMT']->parseChunkData();
check('ICMT Comments', "hello\0", $sf->sfbk['INFO']['ICMT']->detail['Comments']);
$sf->sfbk['sdta']['smpl']->parseChunkData();
check('smpl Samples length', 128, strlen($sf->sfbk['sdta']['smpl']->detail['Samples']));

/*
 * modulator source index is 7 bits (MIDI CC 91)
 */
$sf = parseSF2(sf2());
$sf->sfbk['pdta']['pmod']->parseChunkData();
check('pmod src Index', array('Type' => 0, 'P' => 0, 'D' => 0, 'CC' => 1, 'Index' => 91),
      $sf->sfbk['pdta']['pmod']->detail[0]['sfModSrcOper']);

/*
 * the terminal EOP record (bank 0 / preset 0) does not hide the real preset
 */
$sf = parseSF2(sf2());
$sf->analyze();
$banks = $sf->pdtaMap['phdr'];
check('phdr banks', array(0), array_keys($banks));
check('phdr presets', array(0), array_keys($banks[0]));
check('phdr preset 0/0', array('Piano', 0, 0),
      array($banks[0][0]['PresetName'], $banks[0][0]['PresetBagNdx'], $banks[0][0]['_PresetBagNdxEnd']));
$sf = parseSF2(sf2(array('eop' => 255)));
$sf->analyze();
check('phdr banks (EOP 255/255)', array(0), array_keys($sf->pdtaMap['phdr']));

/*
 * inst wInstBagNdx is unsigned (WORD)
 */
$chunk = new IO_SoundFont_Chunk();
$chunk->id = 'inst';
$chunk->data = name20('X').pack('v', 0x8000);
$chunk->parseChunkData();
check('inst InstBagNdx unsigned', 0x8000, $chunk->detail[0]['InstBagNdx']);

/*
 * parse raises no deprecation (no dynamic property)
 */
$errors = array();
set_error_handler(function ($errno, $errstr) use (&$errors) { $errors[] = $errstr; return true; });
parseSF2(sf2());
restore_error_handler();
check('parse no deprecation', array(), $errors);

/*
 * sample/sfextractsample.php: looptime with an unlooped sample (EndLoop == StartLoop)
 */
$tmpdir = sys_get_temp_dir().'/iosoundfont'.getmypid();
mkdir($tmpdir);
file_put_contents("$tmpdir/noloop.sf2", sf2(array('startLoop' => 0, 'endLoop' => 0)));
list($status, $stdout, $stderr) = runPHP(array("$root/sample/sfextractsample.php", 'noloop.sf2', '1'), $tmpdir);
check('sfextractsample no loop: exit status', 0, $status);
$wav = is_readable("$tmpdir/0_Smp.wav") ? file_get_contents("$tmpdir/0_Smp.wav") : null;
$smpl = '';
for ($i = 0; $i <= 32; $i++) {
    $smpl .= pack('v', $i * 100);
}
check('sfextractsample no loop: wav', IO_SoundFont_WaveData::makeWaveData($smpl, 1, 16, 22050), $wav);
array_map('unlink', glob("$tmpdir/*"));

/*
 * sample/sfextractsample.php: two samples with the same name are both written
 */
file_put_contents("$tmpdir/dup.sf2", sf2(array('twoSamples' => 'Smp')));
list($status, $stdout, $stderr) = runPHP(array("$root/sample/sfextractsample.php", 'dup.sf2'), $tmpdir);
$files = array_map('basename', glob("$tmpdir/*.wav"));
sort($files);
check('sfextractsample same names: files', array('0_Smp.wav', '1_Smp.wav'), $files);
check('sfextractsample same names: different data', true,
      file_get_contents("$tmpdir/0_Smp.wav") !== file_get_contents("$tmpdir/1_Smp.wav"));
array_map('unlink', glob("$tmpdir/*"));

/*
 * sample/sfextractsample.php: path separators in a sample name are not used as paths
 */
file_put_contents("$tmpdir/sep.sf2", sf2(array('twoSamples' => "../a/b\\c\x01")));
list($status, $stdout, $stderr) = runPHP(array("$root/sample/sfextractsample.php", 'sep.sf2'), $tmpdir);
$files = array_map('basename', glob("$tmpdir/*.wav"));
sort($files);
check('sfextractsample path separators: files', array('0_Smp.wav', '1_.._a_b_c_.wav'), $files);
check('sfextractsample path separators: exit status', 0, $status);
array_map('unlink', glob("$tmpdir/*"));
rmdir($tmpdir);

/*
 * analyze: the last inst record ends at the last ibag record (not pbag)
 */
$sfbk = array('pdta' => array());
foreach (array('pbag' => 2, 'ibag' => 3) as $id => $n) {
    $chunk = new IO_SoundFont_Chunk();
    $chunk->id = $id;
    $chunk->detail = array_fill(0, $n, array('GenNdx' => 0, 'ModNdx' => 0));
    $sfbk['pdta'][$id] = $chunk;
}
$chunk = new IO_SoundFont_Chunk();
$chunk->id = 'inst';
$chunk->detail = array(array('InstName' => 'Inst', 'InstBagNdx' => 0),
                       array('InstName' => 'EOI', 'InstBagNdx' => 2));
$inst = $chunk->analyze($sfbk);
check('inst _InstBagNdxEnd', array(1, 2), array($inst[0]['_InstBagNdxEnd'], $inst[1]['_InstBagNdxEnd']));

/*
 * build: parse -> build is byte-identical (also after dump / analyze / tree)
 */
$allInfo = chunk('isng', "EMU8000\0").chunk('INAM', "TestBank\0\0").chunk('IENG', "someone\0")
         . chunk('ISFT', "tool:tool\0").chunk('ICMT', "hello\0").chunk('ICOP', "(c) 2026\0\0");
foreach (array('default' => sf2(), 'EOP 255' => sf2(array('eop' => 255)),
               'all INFO strings' => sf2(array('info' => $allInfo))) as $name => $data) {
    $sf = parseSF2($data);
    check("build $name", bin2hex($data), bin2hex($sf->build()));
    ob_start();
    $sf->dump();
    $sf->tree();
    ob_end_clean();
    check("build $name after dump and tree", bin2hex($data), bin2hex($sf->build()));
}

/*
 * build: edits to detail are written (parse -> edit -> build -> parse)
 */
$orig = sf2();
$sf = parseSF2($orig);
$sf->analyze(); // adds '_' keys to some records: not an edit
$sf->sfbk['INFO']['INAM']->parseChunkData();
$sf->sfbk['INFO']['INAM']->detail['sfName'] = 'Organ Bank';
$sf->sfbk['pdta']['phdr']->detail[0]['PresetName'] = 'Organ';
$sf->sfbk['pdta']['phdr']->detail[0]['Preset'] = 19;
$sf->sfbk['pdta']['pgen']->detail[1]['Amount'] = -5;    // startAddrsOffset (signed)
$sf->sfbk['pdta']['pmod']->detail[0]['modAmount'] = -300;
$sf->sfbk['pdta']['pmod']->detail[0]['sfModSrcOper']['Index'] = 93;
$sf->sfbk['pdta']['inst']->detail[0]['InstName'] = 'Organ';
$sf->sfbk['pdta']['shdr']->detail[0]['SampleRate'] = 44100;
$sf->sfbk['pdta']['shdr']->detail[0]['PitchCorrection'] = -10;
$sf2 = parseSF2($sf->build());
$sf2->analyze();
$sf2->sfbk['INFO']['INAM']->parseChunkData();
check('edit INAM', "Organ Bank\0\0", $sf2->sfbk['INFO']['INAM']->detail['sfName']);
check('edit phdr', array('Organ', 19), array($sf2->pdtaMap['phdr'][0][19]['PresetName'], $sf2->pdtaMap['phdr'][0][19]['Preset']));
check('edit pgen', -5, $sf2->pdtaMap['pgen'][1]['Amount']);
check('edit pgen instrument', 0, $sf2->pdtaMap['pgen'][0]['Amount']);
check('edit pmod', array(-300, 93, 1), array($sf2->pdtaMap['pmod'][0]['modAmount'],
                                             $sf2->pdtaMap['pmod'][0]['sfModSrcOper']['Index'],
                                             $sf2->pdtaMap['pmod'][0]['sfModSrcOper']['CC']));
check('edit inst', 'Organ', $sf2->pdtaMap['inst'][0]['InstName']);
check('edit shdr', array(44100, -10, 'Smp', 32), array($sf2->pdtaMap['shdr'][0]['SampleRate'], $sf2->pdtaMap['shdr'][0]['PitchCorrection'],
                                                       $sf2->pdtaMap['shdr'][0]['SampleName'], $sf2->pdtaMap['shdr'][0]['End']));
foreach (array('pbag', 'ibag', 'imod', 'igen') as $id) { // not edited: raw data
    check("edit: $id unchanged", bin2hex($sf->sfbk['pdta'][$id]->data), bin2hex($sf2->sfbk['pdta'][$id]->data));
}
check('edit: smpl unchanged', bin2hex(parseSF2($orig)->sfbk['sdta']['smpl']->data), bin2hex($sf2->sfbk['sdta']['smpl']->data));
// every record type built from detail without edits gives the same bytes
$sf = parseSF2($orig);
$sf->analyze();
foreach ($sf->sfbk as $listId => $chunkLIST) {
    foreach ($chunkLIST as $id => $chunk) {
        $chunk->parseChunkData();
        $chunk->_detailParsed = null; // force building from detail
        if (is_null($chunk->detail) === false) {
            check("build $id from detail", bin2hex($chunk->data), bin2hex($chunk->buildChunkData()));
        }
    }
}

/*
 * generator amounts: signed (shAmount) except the indexes instrument / sampleID
 */
$chunk = new IO_SoundFont_Chunk();
$chunk->id = 'igen';
$chunk->data = pack('vs', 52, -5).pack('vs', 17, -500).pack('vs', 33, -12000).pack('vs', 58, -1)
             . pack('vv', 53, 0x8000).pack('vv', 41, 40000).pack('vv', 14, 0xFFFF).pack('vCC', 43, 10, 100);
$chunk->parseChunkData();
$amounts = array();
foreach ($chunk->detail as $gen) {
    $amounts[] = isset($gen['Amount']) ? $gen['Amount'] : array($gen['Lo'], $gen['Hi']);
}
check('generator amounts', array(-5, -500, -12000, -1, 0x8000, 40000, 0xFFFF, array(10, 100)), $amounts);

/*
 * dump: INFO strings without the NUL padding (detail keeps the raw data)
 */
$sf = parseSF2(sf2());
ob_start();
$sf->dump();
$dump = ob_get_clean();
check('dump INAM no NUL', true, strpos($dump, "sfName: TestBank".PHP_EOL) !== false);
check('dump ICMT no NUL', true, strpos($dump, "Comments: hello".PHP_EOL) !== false);
check('dump keeps detail', "TestBank\0\0", $sf->sfbk['INFO']['INAM']->detail['sfName']);

/*
 * name tables work when the library is loaded inside a function (Composer autoload)
 */
$tmpfile = tempnam(sys_get_temp_dir(), 'iosoundfont');
file_put_contents($tmpfile, sf2());
list($status, $stdout, $stderr) = runPHP(array('-r', 'function load($f) { require_once $f; } load($argv[1]); $sf = new IO_SoundFont(); $sf->parse(file_get_contents($argv[2])); $sf->tree();',
                                               "$root/IO/SoundFont.php", $tmpfile), sys_get_temp_dir());
unlink($tmpfile);
check('tree in function scope: generator name', true, strpos($stdout, "genOper:41(instrument)") !== false);
check('tree in function scope: sample type', true, strpos($stdout, "type:1(monoSample)") !== false);
check('tree in function scope: modulator dest', true, strpos($stdout, "DestOper:16(reverbEffectsSend)") !== false);

/*
 * test data (tests/data/make.php): values from the SoundFont 2 spec / the generator
 */
$datadir = $root.'/tests/data';
$errors = array();
set_error_handler(function ($errno, $errstr) use (&$errors) { $errors[] = $errstr; return true; });
$bank = file_get_contents("$datadir/bank.sf2");
$sf = parseSF2($bank);
check('bank: LIST ids', array('INFO', 'sdta', 'pdta'), array_keys($sf->sfbk));
check('bank: INFO chunks', array('ifil', 'isng', 'INAM', 'irom', 'iver', 'ICRD', 'IENG', 'IPRD', 'ICOP', 'ICMT', 'ISFT'), array_keys($sf->sfbk['INFO']));
check('bank: sdta chunks', array('smpl'), array_keys($sf->sfbk['sdta']));
check('bank: pdta chunks', array('phdr', 'pbag', 'pmod', 'pgen', 'inst', 'ibag', 'imod', 'igen', 'shdr'), array_keys($sf->sfbk['pdta']));
$details = array();
foreach ($sf->sfbk as $listId => $chunkLIST) {
    foreach ($chunkLIST as $id => $chunk) {
        $chunk->parseChunkData();
        $details[$id] = $chunk->detail;
    }
}
check('bank: ifil', array('sfVersionMajor' => 2, 'sfVersionMinor' => 4), $details['ifil']);
check('bank: INFO strings', array("EMU8000\0", "Synth Bank\0\0", "someone\0", "(c) test\0\0", "comment\0", "make.php:\0"),
      array($details['isng']['sfEngine'], $details['INAM']['sfName'], $details['IENG']['sfEngineers'],
            $details['ICOP']['Copyright'], $details['ICMT']['Comments'], $details['ISFT']['sfTools']));
check('bank: unknown INFO chunks have no detail', array(null, null, null, null), array($details['irom'], $details['iver'], $details['ICRD'], $details['IPRD']));
check('bank: unknown INFO chunk data', array("ROM\0", pack('vv', 1, 0)), array($sf->sfbk['INFO']['irom']->data, $sf->sfbk['INFO']['iver']->data));
check('bank: smpl size', (32 + 46 + 16 + 46) * 2, strlen($details['smpl']['Samples']));
check('bank: phdr', array(array('Piano', 0, 0, 0), array('Drums', 0, 128, 2), array('EOP', 0, 0, 3)),
      array_map(function ($p) { return array($p['PresetName'], $p['Preset'], $p['Bank'], $p['PresetBagNdx']); }, $details['phdr']));
check('bank: phdr library / genre / morphology', array(0, 0, 0), array($details['phdr'][1]['Library'], $details['phdr'][1]['Genre'], $details['phdr'][1]['MorphologyGenre']));
check('bank: pbag', array(array('GenNdx' => 0, 'ModNdx' => 0), array('GenNdx' => 2, 'ModNdx' => 1), array('GenNdx' => 4, 'ModNdx' => 1), array('GenNdx' => 6, 'ModNdx' => 2)), $details['pbag']);
check('bank: pgen', array(array('sfGenOper' => 17, 'Amount' => -250), array('sfGenOper' => 51, 'Amount' => -12),
                          array('sfGenOper' => 43, 'Lo' => 0, 'Hi' => 63), array('sfGenOper' => 41, 'Amount' => 0),
                          array('sfGenOper' => 44, 'Lo' => 1, 'Hi' => 127), array('sfGenOper' => 41, 'Amount' => 1),
                          array('sfGenOper' => 0, 'Amount' => 0)), $details['pgen']);
check('bank: pmod CC91', array('sfModSrcOper' => array('Type' => 0, 'P' => 0, 'D' => 0, 'CC' => 1, 'Index' => 91), 'sfModDestOper' => 16, 'modAmount' => 200,
                               'sfModAmtSrcOper' => array('Type' => 0, 'P' => 0, 'D' => 0, 'CC' => 0, 'Index' => 0), 'sfModTransOper' => 0), $details['pmod'][0]);
check('bank: pmod velocity concave negative', array(array('Type' => 1, 'P' => 0, 'D' => 1, 'CC' => 0, 'Index' => 2), 48, 960, 2),
      array($details['pmod'][1]['sfModSrcOper'], $details['pmod'][1]['sfModDestOper'], $details['pmod'][1]['modAmount'], $details['pmod'][1]['sfModTransOper']));
check('bank: pmod terminal', 3, count($details['pmod']));
check('bank: inst', array(array('InstName' => 'Piano Inst', 'InstBagNdx' => 0), array('InstName' => 'Drum Inst', 'InstBagNdx' => 2), array('InstName' => 'EOI', 'InstBagNdx' => 3)), $details['inst']);
check('bank: ibag', array(array('GenNdx' => 0, 'ModNdx' => 0), array('GenNdx' => 2, 'ModNdx' => 0), array('GenNdx' => 5, 'ModNdx' => 0), array('GenNdx' => 7, 'ModNdx' => 1)), $details['ibag']);
check('bank: imod pitch wheel (bipolar) -> fineTune', array(array('Type' => 0, 'P' => 1, 'D' => 0, 'CC' => 0, 'Index' => 14), 52, -100, array('Type' => 0, 'P' => 0, 'D' => 0, 'CC' => 0, 'Index' => 16)),
      array($details['imod'][0]['sfModSrcOper'], $details['imod'][0]['sfModDestOper'], $details['imod'][0]['modAmount'], $details['imod'][0]['sfModAmtSrcOper']));
check('bank: igen', array(array('sfGenOper' => 43, 'Lo' => 0, 'Hi' => 59), array('sfGenOper' => 53, 'Amount' => 0),
                          array('sfGenOper' => 43, 'Lo' => 60, 'Hi' => 127), array('sfGenOper' => 58, 'Amount' => -1),
                          array('sfGenOper' => 53, 'Amount' => 0), array('sfGenOper' => 34, 'Amount' => -12000),
                          array('sfGenOper' => 53, 'Amount' => 1), array('sfGenOper' => 0, 'Amount' => 0)), $details['igen']);
check('bank: shdr A', array('SampleName' => 'SmpA', 'Start' => 0, 'End' => 32, 'StartLoop' => 8, 'EndLoop' => 24, 'SampleRate' => 44100,
                            'OriginalPitch' => 60, 'PitchCorrection' => -5, 'SampleLink' => 0, 'SampleType' => 1), $details['shdr'][0]);
check('bank: shdr B', array('SmpB', 78, 94, 80, 90, 22050, 36, 3),
      array($details['shdr'][1]['SampleName'], $details['shdr'][1]['Start'], $details['shdr'][1]['End'], $details['shdr'][1]['StartLoop'],
            $details['shdr'][1]['EndLoop'], $details['shdr'][1]['SampleRate'], $details['shdr'][1]['OriginalPitch'], $details['shdr'][1]['PitchCorrection']));
check('bank: shdr terminal', 'EOS', $details['shdr'][2]['SampleName']);
check('bank: sample B data', pack('s', -100), substr($details['smpl']['Samples'], 79 * 2, 2));
check('bank: parse warnings', array(), $errors);

// analyze: zone ranges from the next record's index
$sf = parseSF2($bank);
$sf->analyze();
$m = $sf->pdtaMap;
check('analyze: banks', array(0, 128), array_keys($m['phdr']));
check('analyze: preset bag ranges', array(array(0, 1), array(2, 2)),
      array(array($m['phdr'][0][0]['PresetBagNdx'], $m['phdr'][0][0]['_PresetBagNdxEnd']), array($m['phdr'][128][0]['PresetBagNdx'], $m['phdr'][128][0]['_PresetBagNdxEnd'])));
check('analyze: pbag gen / mod ranges', array(array(0, 1, 0, 0), array(2, 3, 1, 0), array(4, 5, 1, 1)),
      array_map(function ($b) { return array($b['GenNdx'], $b['_GenNdxEnd'], $b['ModNdx'], $b['_ModNdxEnd']); }, array_slice($m['pbag'], 0, 3)));
check('analyze: inst bag ranges', array(array(0, 1), array(2, 2)),
      array_map(function ($i) { return array($i['InstBagNdx'], $i['_InstBagNdxEnd']); }, array_slice($m['inst'], 0, 2)));
check('analyze: ibag gen / mod ranges', array(array(0, 1, 0, -1), array(2, 4, 0, -1), array(5, 6, 0, 0)),
      array_map(function ($b) { return array($b['GenNdx'], $b['_GenNdxEnd'], $b['ModNdx'], $b['_ModNdxEnd']); }, array_slice($m['ibag'], 0, 3)));

// build: byte-identical, also after dump / analyze / tree
$sf = parseSF2($bank);
check('bank: build', bin2hex($bank), bin2hex($sf->build()));
ob_start();
$sf->dump();
$sf->tree();
$out = ob_get_clean();
check('bank: build after dump and tree', bin2hex($bank), bin2hex($sf->build()));
check('bank: dump / tree warnings', array(), $errors);
check('bank: dump version', true, strpos($out, "      sfVersion: 2.04".PHP_EOL) !== false);
check('bank: dump unknown INFO chunk', true, strpos($out, "    irom: (size:4)".PHP_EOL."    iver: (size:4)".PHP_EOL) !== false);
check('bank: dump samples', true, strpos($out, "      Samples/2: 140 (16bit Samples)".PHP_EOL) !== false);
check('bank: dump signed generator', true, strpos($out, "  [5]  sfGenOper: 34  Amount: -12000".PHP_EOL) !== false);
check('bank: dump shdr', true, strpos($out, "SampleName: SmpA  Start: 0  End: 32  StartLoop: 8  EndLoop: 24  SampleRate: 44100  OriginalPitch: 60  PitchCorrection: -5") !== false);
check('bank: tree banks', array(true, true), array(strpos($out, "Bank: idx:0".PHP_EOL) !== false, strpos($out, "Bank: idx:128".PHP_EOL) !== false));
check('bank: tree presets', true, strpos($out, "  Preset: idx:0 name:'Piano' bag:0=>1".PHP_EOL) !== false
                                && strpos($out, "  Preset: idx:0 name:'Drums' bag:2=>2".PHP_EOL) !== false);
check('bank: tree instruments', true, strpos($out, "name:'Piano Inst' bagNdx: 0=>1".PHP_EOL) !== false
                                    && strpos($out, "name:'Drum Inst' bagNdx: 2=>2".PHP_EOL) !== false);
check('bank: tree generators', true, strpos($out, "genOper:17(pan) Amount:-250".PHP_EOL) !== false
                                   && strpos($out, "genOper:58(overridingRootKey) Amount:-1".PHP_EOL) !== false
                                   && strpos($out, "genOper:44(velRange) Lo:1 Hi:127".PHP_EOL) !== false);
check('bank: tree sample', true, strpos($out, "name:'SmpB' start:78 loop:80=>90 end:94 sampleRate:22050 originalPitch:36 pitchCorrection:3 link:0 type:1(monoSample)".PHP_EOL) !== false);
check('bank: tree modulators', true, strpos($out, "Type:0 P:1 D:0 CC:0 Index:14(Pitch Wheel) DestOper:52(fineTune) Amount:-100") !== false
                                   && strpos($out, "Type:1 P:0 D:1 CC:0 Index:2(Note-On Velocity) DestOper:48(initialAttenuation) Amount:960") !== false);
check('bank: tree sample count', 3, substr_count($out, "type:1(monoSample)"));

// edit: change records, build, parse again; other chunks keep their bytes
$sf = parseSF2($bank);
$sf->analyze();
$sf->sfbk['pdta']['pgen']->detail[0]['Amount'] = 300;              // pan
$sf->sfbk['pdta']['pgen']->detail[4]['Lo'] = 64;                   // velRange
$sf->sfbk['pdta']['phdr']->detail[1]['PresetName'] = 'Kit';
$sf->sfbk['pdta']['phdr']->detail[1]['Preset'] = 25;
$sf->sfbk['pdta']['igen']->detail[3]['Amount'] = 72;               // overridingRootKey
$sf->sfbk['pdta']['imod']->detail[0]['modAmount'] = -32768;
$sf->sfbk['pdta']['shdr']->detail[1]['PitchCorrection'] = -128;
$sf->sfbk['pdta']['shdr']->detail[1]['SampleName'] = 'SmpB2';
$sf->sfbk['INFO']['ICMT']->parseChunkData();
$sf->sfbk['INFO']['ICMT']->detail['Comments'] = 'edited';
$built = $sf->build();
check('edit: RIFF size', strlen($built) - 8, unpack('V', substr($built, 4, 4))[1]);
$sf2 = parseSF2($built);
$sf2->analyze();
$m = $sf2->pdtaMap;
check('edit: pgen', array(300, 64, 127), array($m['pgen'][0]['Amount'], $m['pgen'][4]['Lo'], $m['pgen'][4]['Hi']));
check('edit: phdr', array('Kit', 25, 2), array($m['phdr'][128][25]['PresetName'], $m['phdr'][128][25]['Preset'], $m['phdr'][128][25]['PresetBagNdx']));
check('edit: igen', 72, $m['igen'][3]['Amount']);
check('edit: imod', -32768, $m['imod'][0]['modAmount']);
check('edit: shdr', array('SmpB2', -128, 22050), array($m['shdr'][1]['SampleName'], $m['shdr'][1]['PitchCorrection'], $m['shdr'][1]['SampleRate']));
$sf2->sfbk['INFO']['ICMT']->parseChunkData();
check('edit: ICMT (zstr, even length)', "edited\0\0", $sf2->sfbk['INFO']['ICMT']->detail['Comments']);
$orig = parseSF2($bank);
foreach (array('INFO' => array('ifil', 'isng', 'INAM', 'irom', 'iver', 'ICRD', 'IENG', 'IPRD', 'ICOP', 'ISFT'), 'sdta' => array('smpl'),
               'pdta' => array('pbag', 'pmod', 'inst', 'ibag')) as $listId => $ids) {
    foreach ($ids as $id) {
        check("edit: $id unchanged", bin2hex($orig->sfbk[$listId][$id]->data), bin2hex($sf2->sfbk[$listId][$id]->data));
    }
}
check('edit warnings', array(), $errors);
restore_error_handler();

// broken input: an exception, no hang
set_time_limit(10);
foreach (array('truncated.sf2' => null, 'not_riff.sf2' => 'non RIFF fourcc',
               'not_sfbk.sf2' => 'non sfbk fourcc', 'not_list.sf2' => 'non LIST fourcc') as $name => $message) {
    list($status, $stdout, $stderr) = runPHP(array('-d', 'error_reporting='.E_ALL, '-r', 'require "IO/SoundFont.php"; $sf = new IO_SoundFont(); try { $sf->parse(file_get_contents($argv[1])); echo "parsed"; } catch (Exception $e) { echo get_class($e).":".$e->getMessage(); }', "$datadir/$name"), $root);
    if (is_null($message)) {
        check("$name: exception", 'IO_Bit_Exception:', substr($stdout, 0, 17));
    } else {
        check("$name: exception", "IO_SoundFont_Exception:$message", $stdout);
    }
    check("$name: no PHP warnings", false, strpos($stderr, 'Warning') !== false || strpos($stderr, 'Deprecated') !== false);
}
// RIFF size not matching the file length: a message on STDERR, parsed and built
list($status, $stdout, $stderr) = runPHP(array('-d', 'error_reporting='.E_ALL, '-r', 'require "IO/SoundFont.php"; $sf = new IO_SoundFont(); $sf->parse(file_get_contents($argv[1])); echo bin2hex($sf->build());', "$datadir/size_mismatch.sf2"), $root);
check('size_mismatch: message', "parse: illegal length(".(strlen($bank) + 2).") and RIFF size(".(strlen($bank) - 8).")\n", $stderr);
check('size_mismatch: build (without the trailing bytes)', bin2hex($bank), $stdout);
// every prefix of bank.sf2 throws (the three LISTs are required), no warnings
list($status, $stdout, $stderr) = runPHP(array('-d', 'error_reporting='.E_ALL, '-r', '
require "IO/SoundFont.php";
$warnings = 0;
set_error_handler(function () use (&$warnings) { $warnings++; return true; });
$data = file_get_contents($argv[1]);
$thrown = 0;
for ($n = 0; $n < strlen($data); $n++) {
    $sf = new IO_SoundFont();
    try {
        $sf->parse(substr($data, 0, $n));
    } catch (Exception $e) {
        $thrown++;
    }
}
echo "$thrown,$warnings";', "$datadir/bank.sf2"), $root);
check('bank prefixes: exceptions, warnings', strlen($bank).',0', $stdout);
set_time_limit(0);

/*
 * sample tools on the test data
 */
foreach (array('sfdump', 'sftree') as $tool) {
    list($status, $stdout, $stderr) = runPHP(array('-d', 'error_reporting='.E_ALL, "$root/sample/$tool.php", "$datadir/bank.sf2"), $root);
    check("$tool: exit status", 0, $status);
    check("$tool: stderr", '', $stderr);
    check("$tool: output", true, strpos($stdout, ($tool === 'sfdump') ? "RIFF=>sfbk".PHP_EOL."  LIST=>INFO" : "Bank: idx:128".PHP_EOL) !== false);
}
$tmpdir = sys_get_temp_dir().'/iosoundfont_data'.getmypid();
mkdir($tmpdir);
list($status, $stdout, $stderr) = runPHP(array('-d', 'error_reporting='.E_ALL, "$root/sample/sfextractsample.php", "$datadir/bank.sf2"), $tmpdir);
check('sfextractsample: exit status', 0, $status);
check('sfextractsample: stderr', '', $stderr);
$files = array_map('basename', glob("$tmpdir/*.wav"));
sort($files);
check('sfextractsample: files', array('0_SmpA.wav', '1_SmpB.wav'), $files);
foreach (array('0_SmpA.wav' => array(44100, 0, 32), '1_SmpB.wav' => array(22050, 78, 16)) as $file => $v) {
    $wav = file_get_contents("$tmpdir/$file");
    $fmt = unpack('a4riff/Vsize/a4wave/a4fmt/Vfmtsize/vformat/vchannels/Vrate/Vbytes/vblock/vbits/a4data/Vdatasize', $wav);
    check("sfextractsample $file: RIFF", array('RIFF', strlen($wav) - 8, 'WAVE', 'fmt ', 16, 'data', strlen($wav) - 44),
          array($fmt['riff'], $fmt['size'], $fmt['wave'], $fmt['fmt'], $fmt['fmtsize'], $fmt['data'], $fmt['datasize']));
    check("sfextractsample $file: PCM 16bit mono", array(1, 1, $v[0], $v[0] * 2, 2, 16),
          array($fmt['format'], $fmt['channels'], $fmt['rate'], $fmt['bytes'], $fmt['block'], $fmt['bits']));
    check("sfextractsample $file: sample points", substr($bank, strpos($bank, 'smpl') + 8 + $v[1] * 2, $v[2] * 2), substr($wav, 44, $v[2] * 2));
}
array_map('unlink', glob("$tmpdir/*"));
list($status, $stdout, $stderr) = runPHP(array('-d', 'error_reporting='.E_ALL, "$root/sample/sfextractsample.php", "$datadir/bank.sf2", '1'), $tmpdir);
check('sfextractsample looptime: exit status', 0, $status);
check('sfextractsample looptime: stderr', '', $stderr);
// SmpA: 8 points before the loop, the 16 point loop repeated for 1 second (44100 / 16 rounded up)
$wav = file_get_contents("$tmpdir/0_SmpA.wav");
$smplA = substr($bank, strpos($bank, 'smpl') + 8, 32 * 2);
check('sfextractsample looptime: attack + loop', substr($smplA, 0, 8 * 2).str_repeat(substr($smplA, 8 * 2, 16 * 2), (int)ceil(44100 / 16)),
      substr($wav, 44, (8 + 16 * (int)ceil(44100 / 16)) * 2));
array_map('unlink', glob("$tmpdir/*"));
rmdir($tmpdir);

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
