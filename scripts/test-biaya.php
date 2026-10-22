<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/biaya_workbook.php';
function checkBiaya($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$s = $book->getActiveSheet();
$s->setCellValue('E3',2026);
foreach (['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $i=>$month) {
    $s->getCell([6+$i,3])->setValue($month);
    $s->getCell([23+$i,3])->setValue($month);
}
$s->setCellValue('A4',1); $s->setCellValue('T4',1);
$s->setCellValue('D5','BIAYA EXISTING'); $s->setCellValue('V5','BIAYA EXISTING');
$s->setCellValue('D6','BIAYA NEW PRODUCT'); $s->setCellValue('V6','BIAYA NEW PRODUCT');
$s->setCellValue('F5',100); $s->setCellValue('W5',200); $s->setCellValue('F6',0);
$d = parseBiayaWorkbook($book,2026);
checkBiaya($d['target']['existing1'][0]===100.0,'Target kiri salah');
checkBiaya($d['actual']['existing1'][0]===200.0,'Realisasi tengah salah');
checkBiaya($d['target']['new1'][0]===0.0,'Nol eksplisit hilang');
checkBiaya($d['actual']['new1'][0]===null,'Data kosong harus null');
try { parseBiayaWorkbook($book,2025); throw new RuntimeException('Tahun salah diterima'); } catch (InvalidArgumentException $e) {}
if (isset($argv[1])) {
    $raw = \PhpOffice\PhpSpreadsheet\IOFactory::load($argv[1]);
    $actual = parseBiayaWorkbook($raw,2026);
    checkBiaya(count($actual['target'])===12 && count($actual['actual'])===12,'Subtotal GB 1–6 tidak lengkap');
    foreach ($actual as $rows) foreach ($rows as $values) checkBiaya($values===array_fill(0,12,null),'Rincian kosong pada file asli harus tetap kosong');
}
class BiayaTestQuery {
    public function bind_param($types, &...$args) {}
    public function execute() {}
    public function get_result() { return $this; }
    public function fetch_assoc() { return ['payload'=>json_encode(['target'=>['existing1'=>array_fill(0,12,100)],'actual'=>['existing1'=>array_fill(0,12,200)]])]; }
    public function close() {}
}
class BiayaTestConnection { public function prepare($sql) { return new BiayaTestQuery(); } }
preg_match('/const TEMPLATE_BASE64 = "([A-Za-z0-9+\/=]+)";/',file_get_contents(__DIR__.'/../dashboard/report-templates.js'),$m);
$tmp = tempnam(sys_get_temp_dir(),'biaya-test-'); file_put_contents($tmp,base64_decode($m[1]));
try { $b=\PhpOffice\PhpSpreadsheet\IOFactory::load($tmp); } finally { unlink($tmp); }
$sheet=$b->getActiveSheet();
populateBiayaSheet($sheet,'2026-09',new BiayaTestConnection());
$found=0;
for($r=1;$r<=$sheet->getHighestDataRow();$r++) if($sheet->getCell('A'.$r)->getValue()==='TARGET BIAYA 2026') {
    $found++;
    checkBiaya($sheet->getCell('D'.($r+2))->getValue()===100,'Target export salah');
    checkBiaya($sheet->getCell('D'.($r+31))->getValue()===200,'Aktual export salah');
    checkBiaya($sheet->getCell('C'.($r+2))->getValue()==900,'YTD target salah');
    checkBiaya($sheet->getCell('D'.($r+3))->getValue()===null,'NP kosong terisi');
    checkBiaya($sheet->getCell('A'.($r+29))->getValue()==='BIAYA TAHUN 2026','Urutan tabel salah');
}
checkBiaya($found===1,'TARGET BIAYA 2026 harus tepat satu');
echo "PASS: target/actual, missing cells, year validation, source ODS, native export placement and YTD.\n";
