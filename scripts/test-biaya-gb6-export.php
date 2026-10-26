<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/biaya_workbook.php';
$source=\PhpOffice\PhpSpreadsheet\IOFactory::load('/home/user/Downloads/RAW DATA!/BIAYA/biaya 26 edited.ods');
$source->getSheet(0)->setCellValue('F70',120.5); // Existing GB6, product number is a formula.
$source->getSheet(0)->setCellValue('F71',30); // New product GB6.
$products=parseBiayaProducts($source,2026);
class Gb6ExportConnection {
    public function __construct(public array $products) {}
    public function prepare($sql) {
        return new class($this->products) {
            private int $year; private array $rows=[];
            public function __construct(private array $products) {}
            public function bind_param($types,&$year) { $this->year=$year; }
            public function execute() {
                if($this->year!==2026) return;
                foreach($this->products as $p) {
                    $key=$p['type'].':'.$p['gb'].':'.$p['category'];
                    if(!isset($this->rows[$key])) $this->rows[$key]=['jenis'=>$p['type'],'gb'=>$p['gb'],'kategori'=>$p['category']];
                    foreach(['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'] as $i=>$m) {
                        $v=$p['values'][$i]; $old=$this->rows[$key][$m]??null;
                        $this->rows[$key][$m]=$old===null && $v===null ? null : ($old??0)+($v??0);
                    }
                }
                $this->rows=array_values($this->rows);
            }
            public function get_result() { return $this; }
            public function fetch_assoc() { return array_shift($this->rows); }
            public function close() {}
        };
    }
}
preg_match('/const TEMPLATE_BASE64 = "([A-Za-z0-9+\/=]+)";/',file_get_contents(__DIR__.'/../dashboard/report-templates.js'),$match);
$temp=tempnam(sys_get_temp_dir(),'gb6-');
try { file_put_contents($temp,base64_decode($match[1])); $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($temp); }
finally { unlink($temp); }
$sheet=$book->getSheet(0);
populateBiayaSheet($sheet,'2026-09',new Gb6ExportConnection($products));
$section=false; $found=0;
for($r=1;$r<=$sheet->getHighestDataRow();$r++) {
    $title=$sheet->getCell('A'.$r)->getValue();
    if($title==='TARGET BIAYA 2026') $section=true;
    elseif($section && is_string($title) && str_starts_with($title,'BIAYA TAHUN')) break;
    if(!$section) continue;
    $label=trim((string)$sheet->getCell('B'.$r)->getValue());
    $expected=['EXIST GB 6'=>120.5,'NP GB 6'=>30.0,'TOTAL GB 6'=>150.5][$label]??null;
    if($expected===null) continue;
    foreach(['C','D','P'] as $column) {
        if((float)$sheet->getCell($column.$r)->getValue()!==$expected) throw new RuntimeException('Wrong GB6 export: '.$label.' '.$column);
    }
    $found++;
}
if($found!==3) throw new RuntimeException('GB6 target rows missing');
echo "PASS: GB6 formula product IDs -> database aggregation -> TARGET BIAYA 2026 EXIST/NP/TOTAL, January/YTD/annual total.\n";
$source->getSheet(0)->setCellValue('F54',9000);
$source->getSheet(0)->setCellValue('W70',8000);
$products=parseBiayaProducts($source,2026);
$temp=tempnam(sys_get_temp_dir(),'gb5-');
try { file_put_contents($temp,base64_decode($match[1])); $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($temp); }
finally { unlink($temp); }
$sheet=$book->getSheet(0);
populateBiayaSheet($sheet,'2026-09',new Gb6ExportConnection($products));
$section='';$checks=0;
for($r=1;$r<=$sheet->getHighestDataRow();$r++) {
    $heading=$sheet->getCell('A'.$r)->getValue();
    if(is_string($heading) && preg_match('/^(?:(?:REVENUE|BIAYA|MCM) TAHUN|TARGET SELL OUT|TARGET BIAYA) 20\d{2}$/',$heading))$section=$heading;
    $label=trim((string)$sheet->getCell('B'.$r)->getValue());
    if($section==='TARGET BIAYA 2026' && $label==='EXIST GB 5') {
        if((float)$sheet->getCell('D'.$r)->getValue()!==9.0)throw new RuntimeException('GB5 target scaling');$checks++;
    }
    if($section==='BIAYA TAHUN 2026' && $label==='EXIST GB 6') {
        if((float)$sheet->getCell('D'.$r)->getValue()!==8000.0)throw new RuntimeException('Actual must remain unscaled');$checks++;
    }
}
if($checks!==2)throw new RuntimeException('Missing verification rows');
echo "PASS: GB5 target divided by 1000, GB6 actual unchanged.\n";
