<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/biaya_workbook.php';
function checkRolling($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$file = $argv[1] ?? null;
if (!$file) throw new InvalidArgumentException('Pass BIAYA 25 EDITED.ods as the first argument.');
$book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
$data = parseBiayaWorkbook($book,2025);
foreach (['target','actual'] as $type) {
    checkRolling(count($data[$type]) === 12, 'Harus ada 12 subtotal '.$type);
    foreach ($data[$type] as $values) checkRolling(count($values)===12,'Harus ada 12 bulan');
}
$sheet=$book->getSheet(0);
// Different sentinels in all three blocks prove that the initial target,
// rolling target, actual and all month columns cannot be confused.
foreach (['target'=>[20,23,24], 'actual'=>[38,40,42]] as $type=>[$groupCol,$labelCol,$startCol]) {
    $gb=null;
    for($r=4;$r<=100;$r++) {
        $group=trim((string)$sheet->getCell([$groupCol,$r])->getValue());
        if(preg_match('/^([1-6])$/',$group,$m)) $gb=(int)$m[1];
        $label=trim((string)$sheet->getCell([$labelCol,$r])->getValue());
        if(!$gb || !in_array($label,['BIAYA EXISTING','BIAYA NEW PRODUCT'],true)) continue;
        $kind=$label==='BIAYA EXISTING'?'existing':'new';
        for($i=0;$i<12;$i++) {
            $v=($type==='target'?10000:20000)+$gb*100+($kind==='new'?50:0)+$i;
            $sheet->getCell([$startCol+$i,$r])->setValue($v);
            if($type==='target') $sheet->getCell([6+$i,$r])->setValue(999999);
            $expected[$type][$kind.$gb][$i]=(float)$v;
        }
    }
}
$parsed=parseBiayaWorkbook($book,2025);
checkRolling($parsed===$expected,'Pemetaan rolling/realisasi atau urutan bulan salah');
// An absent rolling value must remain empty, even with an initial target present.
$sheet->setCellValue('X15',null);
$parsed=parseBiayaWorkbook($book,2025);
checkRolling($parsed['target']['existing1'][0]===null,'Target awal dipakai sebagai fallback');
$sheet->setCellValue('X1','(TARGET)');
try { parseBiayaWorkbook($book,2025); throw new RuntimeException('Blok rolling yang hilang diterima'); } catch (InvalidArgumentException $e) {}
$sheet->setCellValue('X1','(TARGET ROLLING)');
$sheet->setCellValue('AI3','TOTAL');
try { parseBiayaWorkbook($book,2025); throw new RuntimeException('Header bulan rusak diterima'); } catch (InvalidArgumentException $e) {}
try { parseBiayaWorkbook($book,2026); throw new RuntimeException('Tahun berbeda diterima'); } catch (InvalidArgumentException $e) {}
echo "PASS: file asli 2025, 288 nilai rolling/realisasi GB 1–6 Jan–Des, target awal diabaikan, rolling kosong, blok/header/tahun tidak valid ditolak.\n";
