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
    $shdr = name20('Smp').pack('VVVVVCcvv', 0, 32, $o['startLoop'], $o['endLoop'], 22050, 60, 0, 0, 1)
          . name20('EOS').pack('VVVVVCcvv', 0, 0, 0, 0, 0, 0, 0, 0, 0);
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
$wav = is_readable("$tmpdir/Smp.wav") ? file_get_contents("$tmpdir/Smp.wav") : null;
$smpl = '';
for ($i = 0; $i <= 32; $i++) {
    $smpl .= pack('v', $i * 100);
}
check('sfextractsample no loop: wav', IO_SoundFont_WaveData::makeWaveData($smpl, 1, 16, 22050), $wav);
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

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
