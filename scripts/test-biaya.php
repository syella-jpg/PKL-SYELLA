<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/biaya_workbook.php';
function verifyBiaya($ok,$message) { if(!$ok) throw new RuntimeException($message); }
foreach([2025,2026] as $year) {
    $file=$year===2025?'BIAYA 25 EDITED.ods':'biaya 26 edited.ods';
    $book=\PhpOffice\PhpSpreadsheet\IOFactory::load('/home/user/Downloads/RAW DATA!/BIAYA/'.$file);
    $data=parseBiayaWorkbook($book,$year);
    foreach(['target','actual'] as $type) {
        verifyBiaya(count($data[$type])===12,'Missing GB subtotals');
        foreach($data[$type] as $values) verifyBiaya($values===array_fill(0,12,null),'Empty source should stay blank');
    }
    $sheet=$book->getSheet(0); $expected=[];
    $layout=$year===2025?['target'=>[20,23,24],'actual'=>[38,40,42]]:['target'=>[1,4,6],'actual'=>[20,22,23]];
    foreach($layout as $type=>[$groupCol,$labelCol,$startCol]) {
        $gb=null;
        for($r=4;$r<=100;$r++) {
            $group=trim((string)$sheet->getCell([$groupCol,$r])->getValue());
            if(preg_match('/^([1-6])$/',$group,$m)) $gb=(int)$m[1];
            $label=trim((string)$sheet->getCell([$labelCol,$r])->getValue());
            if(!$gb||!in_array($label,['BIAYA EXISTING','BIAYA NEW PRODUCT'],true)) continue;
            $kind=$label==='BIAYA EXISTING'?'existing':'new';
            for($i=0;$i<12;$i++) {
                $v=($type==='target'?10000:20000)+$gb*100+($kind==='new'?50:0)+$i;
                $sheet->getCell([$startCol+$i,$r])->setValue($v);
                if($year===2025 && $type==='target') $sheet->getCell([6+$i,$r])->setValue(999999);
                $expected[$type][$kind.$gb][$i]=(float)$v;
            }
        }
    }
    verifyBiaya(parseBiayaWorkbook($book,$year)===$expected,'Wrong target/actual/GB/month');
    if($year===2025) {
        $sheet->setCellValue('X15',null);
        verifyBiaya(parseBiayaWorkbook($book,2025)['target']['existing1'][0]===null,'Initial target used as fallback');
        $sheet->setCellValue('X1','(TARGET)');
        try { parseBiayaWorkbook($book,2025); throw new RuntimeException('Missing rolling accepted'); } catch(InvalidArgumentException $e) {}
    }
    try { parseBiayaWorkbook($book,$year+1); throw new RuntimeException('Wrong year accepted'); } catch(InvalidArgumentException $e) {}
    echo "PASS $year: original ODS, all 288 monthly values, empty cells, period year and rolling selection.\n";
}
class BiayaQuery {
    private array $bounds;
    public function bind_param($types,&...$args) { $this->bounds=$args; }
    public function execute() {}
    public function get_result() { return $this; }
    public function fetch_assoc() {
        // Only October has an upload, and adjacent years have different values.
        foreach (['2026-10'=>600, '2025-10'=>100] as $period=>$value) {
            if ($period >= $this->bounds[0] && $period <= $this->bounds[1]) {
                return ['payload'=>json_encode(['target'=>['existing1'=>array_fill(0,12,$value)],'actual'=>['existing1'=>array_fill(0,12,$value*2)]])];
            }
        }
        return null;
    }
    public function close() {}
}
class BiayaConnection { public function prepare($sql) { return new BiayaQuery(); } }
foreach ([2025=>100,2026=>600] as $annualYear=>$expectedValue) {
    for ($month=1;$month<=12;$month++) {
        $period=sprintf('%d-%02d',$annualYear,$month);
        $annual=findBiayaRecord(new BiayaConnection(),$period);
        verifyBiaya(($annual['target']['existing1'][0]??null)===$expectedValue,'October upload missing or wrong year: '.$period);
    }
}
echo "PASS: October-only uploads available January–December, 2025/2026 strictly isolated.\n";
preg_match('/const TEMPLATE_BASE64 = "([A-Za-z0-9+\/=]+)";/',file_get_contents(__DIR__.'/../dashboard/report-templates.js'),$m);
$tmp=tempnam(sys_get_temp_dir(),'biaya-test-');file_put_contents($tmp,base64_decode($m[1]));
try { $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($tmp); } finally { unlink($tmp); }
$sheet=$book->getActiveSheet();
populateBiayaSheet($sheet,'2026-09',new BiayaConnection());
$found=0;
for($r=1;$r<=$sheet->getHighestDataRow();$r++) if($sheet->getCell('A'.$r)->getValue()==='TARGET BIAYA 2025') {
    $found++;
    verifyBiaya($sheet->getCell('D'.($r+2))->getValue()===100,'Target export');
    verifyBiaya($sheet->getCell('D'.($r+31))->getValue()===200,'Actual export');
    verifyBiaya($sheet->getCell('C'.($r+2))->getValue()==900,'YTD export');
    verifyBiaya($sheet->getCell('D'.($r+3))->getValue()===null,'Missing NP');
    verifyBiaya($sheet->getCell('A'.($r+29))->getValue()==='BIAYA TAHUN 2025','Table order');
}
verifyBiaya($found===1,'Missing target table');
echo "PASS: native export, TARGET BIAYA 2025 above BIAYA TAHUN 2025, YTD and missing NP.\n";

$fixture=new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$mc=$fixture->getActiveSheet();
$mc->setCellValue('A1','MCM TAHUN 2026');
$mc->setCellValue('B3','EXIST GB 1'); $mc->setCellValue('C3','=1+2');
$mc->setCellValue('D4','=3+4');
$mc->setCellValue('A6','TARGET SELL OUT 2025');
$mc->setCellValue('B8','EXIST GB 1'); $mc->setCellValue('C8',123);
clearMcmSheet($mc,2026);
verifyBiaya($mc->getCell('C3')->getValue()===null && $mc->getCell('D4')->getValue()===null,'MCM formulas remain');
verifyBiaya($mc->getCell('C8')->getValue()===123,'Following target was cleared');
verifyBiaya($mc->getCell('B3')->getValue()==='EXIST GB 1','MCM label cleared');
echo "PASS: MCM formulas/values cleared, labels and following target preserved.\n";
