<?php
/*
 * generate the synthetic test data in this directory (SoundFont 2, built from the spec)
 * usage: php tests/data/make.php
 *   every run writes the same bytes.
 */

$dir = dirname(__FILE__);

function chunk($id, $data) {
    return $id.pack('V', strlen($data)).$data;
}

function chunkLIST($id, $data) {
    return 'LIST'.pack('V', strlen($data) + 4).$id.$data;
}

function name20($name) {
    return str_pad($name, 20, "\0");
}

// sfModulator bits: type(6) P D CC index(7)
function modsrc($type, $p, $d, $cc, $index) {
    return ($type << 10) | ($p << 9) | ($d << 8) | ($cc << 7) | $index;
}

function gen($oper, $amount) {
    return pack('vs', $oper, $amount);   // shAmount
}
function genw($oper, $amount) {
    return pack('vv', $oper, $amount);   // wAmount
}
function genr($oper, $lo, $hi) {
    return pack('vCC', $oper, $lo, $hi); // ranges
}
function mod($src, $dest, $amount, $amtsrc, $trans) {
    return pack('vvsvv', $src, $dest, $amount, $amtsrc, $trans);
}
function shdr($name, $start, $end, $startLoop, $endLoop, $rate, $pitch, $correction, $link, $type) {
    return name20($name).pack('VVVVVCcvv', $start, $end, $startLoop, $endLoop, $rate, $pitch, $correction, $link, $type);
}

/*
 * bank.sf2
 *   INFO: ifil 2.04, every string chunk the library knows, unknown ones
 *         (irom, iver, ICRD, IPRD)
 *   sdta: two samples, each followed by 46 zero sample points
 *   pdta: preset 'Piano' 0/0 (global zone + zone) -> instrument 'Piano Inst'
 *         (2 zones, sample A), preset 'Drums' 0/128 -> instrument 'Drum Inst'
 *         (sample B), signed generator amounts, modulators in pmod / imod.
 */
$info = chunk('ifil', pack('vv', 2, 4))
      . chunk('isng', "EMU8000\0")
      . chunk('INAM', "Synth Bank\0\0")
      . chunk('irom', "ROM\0")
      . chunk('iver', pack('vv', 1, 0))
      . chunk('ICRD', "2026\0\0")
      . chunk('IENG', "someone\0")
      . chunk('IPRD', "test\0\0")
      . chunk('ICOP', "(c) test\0\0")
      . chunk('ICMT', "comment\0")
      . chunk('ISFT', "make.php:\0");

$smpl = '';
for ($i = 0; $i < 32; $i++) {          // sample A: 0..31
    $smpl .= pack('v', ($i * 1000) & 0xFFFF);
}
$smpl .= str_repeat("\0\0", 46);
for ($i = 0; $i < 16; $i++) {          // sample B: 78..93
    $smpl .= pack('s', -100 * $i);
}
$smpl .= str_repeat("\0\0", 46);

$phdr = name20('Piano').pack('vvvVVV', 0, 0, 0, 0, 0, 0)
      . name20('Drums').pack('vvvVVV', 0, 128, 2, 0, 0, 0)
      . name20('EOP').pack('vvvVVV', 0, 0, 3, 0, 0, 0);
$pbag = pack('vv', 0, 0).pack('vv', 2, 1).pack('vv', 4, 1).pack('vv', 6, 2);
$pmod = mod(modsrc(0, 0, 0, 1, 91), 16, 200, 0, 0)            // CC91 -> reverbEffectsSend
      . mod(modsrc(1, 0, 1, 0, 2), 48, 960, 0, 2)             // velocity (concave, -) -> initialAttenuation
      . mod(0, 0, 0, 0, 0);
$pgen = gen(17, -250)        // pan (global zone)
      . gen(51, -12)         // coarseTune
      . genr(43, 0, 63)      // keyRange
      . genw(41, 0)          // instrument 'Piano Inst'
      . genr(44, 1, 127)     // velRange
      . genw(41, 1)          // instrument 'Drum Inst'
      . genw(0, 0);          // terminal
$inst = name20('Piano Inst').pack('v', 0)
      . name20('Drum Inst').pack('v', 2)
      . name20('EOI').pack('v', 3);
$ibag = pack('vv', 0, 0).pack('vv', 2, 0).pack('vv', 5, 0).pack('vv', 7, 1);
$imod = mod(modsrc(0, 1, 0, 0, 14), 52, -100, modsrc(0, 0, 0, 0, 16), 0) // pitch wheel -> fineTune
      . mod(0, 0, 0, 0, 0);
$igen = genr(43, 0, 59)      // keyRange
      . genw(53, 0)          // sampleID A
      . genr(43, 60, 127)
      . gen(58, -1)          // overridingRootKey -1 (none)
      . genw(53, 0)
      . gen(34, -12000)      // attackVolEnv
      . genw(53, 1)          // sampleID B
      . genw(0, 0);          // terminal
$shdr = shdr('SmpA', 0, 32, 8, 24, 44100, 60, -5, 0, 1)
      . shdr('SmpB', 78, 94, 80, 90, 22050, 36, 3, 0, 1)
      . shdr('EOS', 0, 0, 0, 0, 0, 0, 0, 0, 0);
$pdta = chunk('phdr', $phdr).chunk('pbag', $pbag).chunk('pmod', $pmod).chunk('pgen', $pgen)
      . chunk('inst', $inst).chunk('ibag', $ibag).chunk('imod', $imod).chunk('igen', $igen)
      . chunk('shdr', $shdr);
$body = 'sfbk'.chunkLIST('INFO', $info).chunkLIST('sdta', chunk('smpl', $smpl)).chunkLIST('pdta', $pdta);
$bank = 'RIFF'.pack('V', strlen($body)).$body;

$files = array();
$files['bank.sf2'] = $bank;

/*
 * broken inputs
 */
$files['truncated.sf2'] = substr($bank, 0, strpos($bank, 'pdta') + 100);  // inside pdta
$files['not_riff.sf2'] = 'RIFX'.substr($bank, 4);
$files['not_sfbk.sf2'] = substr($bank, 0, 8).'sfbX'.substr($bank, 12);
$files['not_list.sf2'] = substr($bank, 0, 12).'LISX'.substr($bank, 16);
$files['size_mismatch.sf2'] = $bank."\0\0";                                // RIFF size 2 bytes short

foreach ($files as $name => $data) {
    file_put_contents("$dir/$name", $data);
}
