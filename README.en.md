IO_SoundFont
============

Japanese version: [README.md](README.md)

A PHP library for interpreting SoundFont 2 (.sf2) files. It requires IO_Bit.
It can dump the chunk structure, show the preset → instrument → sample tree, and write samples out as WAV files.
Writing (build) is not finished.
Not limited in use, free to copy and modify under the MIT license.
Please contact me if it does not work in your environment or if you want new features.

# License

MIT License

# Install

```
% composer require yoya/io_soundfont
```

# Usage

- sfdump.php (chunk structure dump)

```
% php vendor/yoya/io_soundfont/sample/sfdump.php test.sf2
RIFF=>sfbk
  LIST=>INFO
    ifil: (size:4)
      sfVersion: 2.01
    isng: (size:8)
      sfEngine: EMU8000
    INAM: (size:10)
      sfName: Test Bank
  LIST=>sdta
    smpl: (size:220)
      Samples/2: 110 (16bit Samples)
  LIST=>pdta
    phdr: (size:76)
  [0]  PresetName: Piano  Preset: 0  Bank: 0  PresetBagNdx: 0  Library: 0  Genre: 0  MorphologyGenre: 0
  [1]  PresetName: EOP  Preset: 0  Bank: 0  PresetBagNdx: 1  Library: 0  Genre: 0  MorphologyGenre: 0
(omit)
```

- sftree.php (tree from each preset down to its samples)

```
% php vendor/yoya/io_soundfont/sample/sftree.php test.sf2
Bank: idx:0
  Preset: idx:0 name:'Piano' bag:0=>0
    presetBag: ndx:0
      Gen: idx:0 
        genOper:41(instrument) Amount:0
          name:'Piano' bagNdx: 0=>0
            InstBag: idx:0
              Gen: idx:0 
                genOper:43(keyRange) Lo:0 Hi:127
              Gen: idx:1 
                genOper:53(sampleID) Amount:0
                name:'Sine' start:0 loop:16=>48 end:64 sampleRate:22050 originalPitch:60 pitchCorrection:0 link:0 type:1(monoSample)
      Mod: idx:0 
        Type:0 P:0 D:0 CC:1 Index:91 DestOper:16(reverbEffectsSend) Amount:200 AmdSrcOper:Type:0 P:0 D:0 CC:0 Index:0(No Controler) TransOper:0
```

- sfextractsample.php (writes the samples used by the presets to `<sample name>.wav` in the current directory. With a second argument in seconds, the loop section is repeated for that long)

```
% php vendor/yoya/io_soundfont/sample/sfextractsample.php test.sf2 3
Bank: idx:0
  Preset: idx:0 name:'Piano' bag:0=>0
    presetBag: ndx:0
name:'Piano' bagNdx: 0=>0
InstBag: idx:0
name:'Sine' start:0 loop:16=>48 end:64 sampleRate:22050 originalPitch:60 pitchCorrection:0 link:0 type:1
% ls *.wav
Sine.wav
```

- Using it from PHP (list presets and samples)

```php
<?php
require 'vendor/autoload.php';

$sf = new IO_SoundFont();
$sf->parse(file_get_contents('test.sf2'));
$sf->analyze();
foreach ($sf->pdtaMap['phdr'] as $bank => $presets) {
    foreach ($presets as $preset => $p) {
        echo "bank:$bank preset:$preset name:{$p['PresetName']}".PHP_EOL;
    }
}
foreach ($sf->pdtaMap['shdr'] as $idx => $s) {
    echo "sample[$idx]: {$s['SampleName']} rate:{$s['SampleRate']}".PHP_EOL;
}
```

```
% php presets.php
bank:0 preset:0 name:Piano
sample[0]: Sine rate:22050
sample[1]: EOS rate:0
```

# Design

| Class | Role |
|---|---|
| `IO_SoundFont` | The whole file. parse / dump / analyze / tree of the three LISTs (INFO, sdta, pdta) of RIFF `sfbk` |
| `IO_SoundFont_Chunk` | One sub-chunk (`ifil`, `INAM`, `smpl`, `phdr`, `pbag`, `pgen`, `inst`, `shdr`, ...). Interprets its contents and computes index ranges |
| `IO_SoundFont_Generator`, `IO_SoundFont_Modulator`, `IO_SoundFont_Sample` | Interpret and stringify generator, modulator and sample header records |
| `IO_SoundFont_WaveData` | Wraps PCM data into a WAV file |
| `IO_Bit` (separate package) | Byte and bit level reading and writing |

- `parse()` fills `sfbk` with, for each LIST, an array of sub-chunk ID → `IO_SoundFont_Chunk`. Each chunk holds its raw data (`data`).
- `parseChunkData()` (called by `dump()` and `analyze()`) interprets the data into an array of records (`detail`) and empties `data`.
- `analyze()` interprets the pdta chunks into `pdtaMap`.
  - `pdtaMap['phdr']` is indexed as `[bank][preset number]`. It does not include the terminal record (EOP).
  - The others (`pbag`, `inst`, `ibag`, `shdr`, ...) are record arrays, with the end of each index range computed from the next record, such as `_GenNdxEnd` and `_InstBagNdxEnd`.
- `tree()` walks the `analyze()` result as preset → bag → generator (→ instrument → bag → generator → sample) and prints it.

# Known limitations

- `build()` is not finished and does not work.
- Generator values (`Amount`) are read unsigned. Negative values of signed generators (fineTune, pan, ...) show as numbers near 65535.
- `dump()` prints INFO strings as they are, including the trailing NULs.
- `sfextractsample.php` uses the sample name from the file as the file name. Samples with the same name overwrite each other.

# Test

```
% php -d include_path=.:../IO_Bit tests/run.php
```

IO_Bit is loaded from `vendor/` (after composer install) or include_path.

# Reference

- SoundFont 2.01 Technical Specification
