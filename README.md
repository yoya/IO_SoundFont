IO_SoundFont
============

English version: [README.en.md](README.en.md)

SoundFont 2 (.sf2) を解釈する為の PHP ライブラリです。IO_Bit が必要です。
チャンク構造のダンプ、プリセット → インストゥルメント → サンプルの木構造の表示、サンプルの WAV への書き出しができます。
parse した内容をそのまま .sf2 に書き出す (build) こともできます。
利用に制限はかけません。コピーも改変もご自由にどうぞ。MIT ライセンスにしました。
自分の環境だと動かないとか、機能が欲しいとかいった要望があれば連絡下さい。

# License

MIT License

# Install

```
% composer require yoya/io_soundfont
```

# Usage

- sfdump.php (チャンク構造のダンプ)

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

- sftree.php (プリセットからサンプルまでを辿った木構造)

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
        Type:0 P:0 D:0 CC:1 Index:91 DestOper:16(reverbEffectsSend) Amount:200 AmtSrcOper:Type:0 P:0 D:0 CC:0 Index:0(No Controller) TransOper:0
```

- sfextractsample.php (プリセットから使われているサンプルを `<サンプル番号>_<サンプル名>.wav` (名前の `/`, `\`, 制御文字は `_` に置き換え) としてカレントディレクトリに書き出す。2 つめの引数を秒数で与えると、ループ区間をその長さ分繰り返す)

```
% php vendor/yoya/io_soundfont/sample/sfextractsample.php test.sf2 3
Bank: idx:0
  Preset: idx:0 name:'Piano' bag:0=>0
    presetBag: ndx:0
name:'Piano' bagNdx: 0=>0
InstBag: idx:0
name:'Sine' start:0 loop:16=>48 end:64 sampleRate:22050 originalPitch:60 pitchCorrection:0 link:0 type:1
% ls *.wav
0_Sine.wav
```

- PHP から使う例 (プリセットとサンプルの一覧)

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

# 設計概要

| クラス | 役割 |
|---|---|
| `IO_SoundFont` | ファイル全体。RIFF `sfbk` の 3 つの LIST (INFO, sdta, pdta) の parse / build / dump / analyze / tree |
| `IO_SoundFont_Chunk` | サブチャンク 1 個 (`ifil`, `INAM`, `smpl`, `phdr`, `pbag`, `pgen`, `inst`, `shdr` など)。中身の解釈と、インデックスの範囲の計算 |
| `IO_SoundFont_Generator`, `IO_SoundFont_Modulator`, `IO_SoundFont_Sample` | ジェネレータ・モジュレータ・サンプルヘッダのレコードの解釈と文字列化 |
| `IO_SoundFont_WaveData` | PCM データを WAV ファイルにする |
| `IO_Bit` (別パッケージ) | バイト・ビット単位の読み書き |

- `parse()` は `sfbk` に、LIST ごとにサブチャンク ID → `IO_SoundFont_Chunk` の配列を入れます。各チャンクは生のデータ (`data`) を持ちます。
- `parseChunkData()` (`dump()` と `analyze()` が呼ぶ) は、データをレコードの配列 (`detail`) に解釈します。`data` はそのまま残ります。
- `analyze()` は pdta のチャンクを解釈して `pdtaMap` に入れます。
  - `pdtaMap['phdr']` は `[バンク][プリセット番号]` でプリセットを引ける形です。最後の終端レコード (EOP) は含みません。
  - そのほか (`pbag`, `inst`, `ibag`, `shdr` など) はレコードの配列で、`_GenNdxEnd` や `_InstBagNdxEnd` のように、次のレコードから求めたインデックスの終わりが付きます。
- ジェネレータの値 (`Amount`) は、インデックスの instrument, sampleID と未使用・予約のものは符号無し、それ以外は符号付きで読みます。
- `build()` は LIST と RIFF のサイズを付け直して書き出します。`parseChunkData()` の後で `detail` を書き換えたチャンクは `detail` から作り直し (名前は NUL で埋めます)、それ以外は `data` をそのまま書きます。何も変えなければ元と同じバイト列になります。
- `tree()` は `analyze()` の結果を、プリセット → バッグ → ジェネレータ (インストゥルメント → バッグ → ジェネレータ → サンプル) の順に辿って表示します。

# Test

```
% php -d include_path=.:../IO_Bit tests/run.php
```

IO_Bit は `vendor/` (composer install した場合) か include_path から読みます。

# Reference

- SoundFont 2.01 Technical Specification

## IO_Bit の利用箇所

RIFF のサイズや SoundFont の整数フィールドにリトルエンディアンの読み書きを使います。

[IO_Bit Wiki](https://github.com/yoya/IO_Bit/wiki)
