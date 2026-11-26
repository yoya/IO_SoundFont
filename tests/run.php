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
    $o = $opts + array('startLoop' => 8, 'endLoop' => 24, 'eop' => 0);
    $info = chunk('ifil', pack('vv', 2, 1)).chunk('INAM', "TestBank\0\0").chunk('ICMT', "hello\0");
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

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
