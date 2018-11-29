IO_SoundFont
============

SoundFont parse % build library

# License

MIT License

# Install

```
% composer require yoya/io_soundfont
```

# Usage


- sfdump.php (binary structure dump)

```
% php vendor/yoya/io_soundfont/sample/sfdump.php input.sf2
  [3]  sfGenOper: 21  Amount: 57563
RIFF=>sfbk
  LIST=>INFO
    ifil: (size:4)
      sfVersion: 2.01
    isng: (size:8)
      sfEngine:
    INAM: (size:46)
(omit)
```

- sftree.php (semantec dump)

```
php vendor/yoya/io_soundfont/sample/sftree.php input.sf2
Bank: idx:0
  Preset: idx:47 name:'Timpani' bag:0=>0
    presetBag: ndx:0
      Gen: idx:0
        genOper:41(instrument) Amount:0
          name:'Timpani' bagNdx: 0=>1
            InstBag: idx:0
              Gen: idx:0
                genOper:43(keyRange) Lo:0 Hi:41
```

- sfextractsample.php (get wavesample)

```
% php vendor/yoya/io_soundfont/sample/sfextractsample.php
Usage: php sfextractsample.php <sffile> [<looptime>]
ex) php sample/sfextractsample.php emuaps_8mb.sf2 3
```
