<?php
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
require_once __DIR__.'/unit_sell_out_ib_targets.php';
require_once __DIR__.'/unit_sell_out_ethical_targets.php';
require_once __DIR__.'/unit_sell_out_input_template.php';

class UnitSellOutTargetFilter implements IReadFilter {
    public function readCell($columnAddress, $row, $worksheetName = ''): bool {
        $limit=in_array(strtoupper(trim($worksheetName)),['GB 1-6','ETHICAL'],true)?5100:1000;
        return $row <= $limit && Coordinate::columnIndexFromString($columnAddress) <= (strtoupper(trim($worksheetName))==='GMEB'?27:(strtoupper(trim($worksheetName))==='HRU'?26:20));
    }
}
function readUnitSellOutTargetBook(string $path): Spreadsheet {
    $reader=IOFactory::createReaderForFile($path);
    if(!($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Ods)&&!($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx))throw new InvalidArgumentException('Gunakan workbook ODS atau XLSX.');
    $names=array_values(array_filter($reader->listWorksheetNames($path),fn($name)=>preg_match('/^(?:TARGET\s+GB\s*[1-6]|HRU|GMEB|TARGET UNIT SELL OUT|GB 1-6|ETHICAL|IB|_TEMPLATE)$/i',trim($name))));
    if(!$names)throw new InvalidArgumentException('Sheet TARGET GB 1–6, HRU, atau GMEB tidak ditemukan. Sheet BUDGET tidak digunakan.');
    $reader->setLoadSheetsOnly($names);$reader->setReadFilter(new UnitSellOutTargetFilter());$reader->setReadDataOnly(true);$reader->setReadEmptyCells(false);
    return $reader->load($path);
}
function parseUnitSellOutTargets(Spreadsheet $book,int $year): array {
    if(isUnitSellOutInputTemplate($book))return parseUnitSellOutInputTemplate($book,$year)['records'];
    $norm=fn($v)=>strtoupper(trim(preg_replace('/\s+/u',' ',(string)$v)));
    $months=['JANUARI'=>1,'JAN'=>1,'FEBRUARI'=>2,'FEB'=>2,'MARET'=>3,'MAR'=>3,'APRIL'=>4,'APR'=>4,'MEI'=>5,'MAY'=>5,'JUNI'=>6,'JUN'=>6,'JULI'=>7,'JUL'=>7,'AGUSTUS'=>8,'AGU'=>8,'AGT'=>8,'AUG'=>8,'SEPTEMBER'=>9,'SEPT'=>9,'SEP'=>9,'OKTOBER'=>10,'OKT'=>10,'OCT'=>10,'NOVEMBER'=>11,'NOPEMBER'=>11,'NOV'=>11,'NOP'=>11,'DESEMBER'=>12,'DES'=>12,'DEC'=>12];
    $result=[];$seen=[];
    foreach($book->getAllSheets() as $sheet){
        if(!preg_match('/^TARGET\s+GB\s*([1-6])$/i',trim($sheet->getTitle()),$match))continue;
        $gb=(int)$match[1];if(isset($seen[$gb]))throw new InvalidArgumentException('Sheet TARGET GB yang sama berulang.');$seen[$gb]=true;
        $last=min(1000,$sheet->getHighestDataRow());$rows=$sheet->rangeToArray('A1:T'.$last,null,false,false);
        $header=null;$columns=[];
        foreach(array_slice($rows,0,10,true) as $r=>$row){
            foreach($row as $c=>$v){
                if(($months[$norm($v)]??0)!==1)continue;
                $candidate=[];
                for($m=1;$m<=12;$m++)if(($months[$norm($row[$c+$m-1]??'')]??0)===$m)$candidate[$m]=$c+$m-1;
                if(count($candidate)===12){$header=$r;$columns=$candidate;break 2;}
            }
        }
        if($header===null)throw new InvalidArgumentException('Kolom Januari–Desember blok UNIT tidak ditemukan pada TARGET GB '.$gb.'.');
        $years=[];
        foreach(array_slice($rows,0,max(7,$header+1)) as $row)foreach($row as $v)if(is_string($v)&&$v!==''&&$v[0]!=='='&&preg_match_all('/\b20\d{2}\b/',$v,$found))foreach($found[0] as $y)$years[(int)$y]=true;
        if(!isset($years[$year])||count($years)!==1)throw new InvalidArgumentException('Tahun TARGET GB '.$gb.' tidak sesuai periode upload '.$year.'.');
        $summary=[];
        for($r=$header+1;$r<count($rows);$r++){
            $labels=trim(implode(' ',array_map($norm,array_slice($rows[$r],0,min($columns)))));
            if(preg_match('/^(?:TOTAL\s*\(\s*(?:RP|RUPIAH)|RUPIAH\b)/',$labels))break;
            $category=null;$priority=0;
            foreach(array_slice($rows[$r],0,min($columns)) as $labelValue){
                $label=preg_replace('/[.\x{2013}\x{2014}-]+/u',' ',$norm($labelValue));$label=preg_replace('/\s+/',' ',$label);
                if($gb<=4){
                    if(!preg_match('/\bKATEGORI\s+([ABCD])\b/',$label,$found))continue;
                    $category=$found;$priority=2;break;
                }
                if(!preg_match('/^TOTAL\b.*\b(?:KATEGORI|KAT)\s+([ABCD])\b/',$label,$found))continue;
                $category=$found;$priority=preg_match('/^TOTAL\s+(?:PRODUK\s+)?KATEGORI\s+[ABCD]\b/',$label)?2:1;break;
            }
            if($category===null)continue;
            $key=$category[1];$current=$summary[$key]['priority']??0;
            if($priority>$current)$summary[$key]=['priority'=>$priority,'rows'=>[$r]];
            elseif($priority===$current){
                if($priority===2)throw new InvalidArgumentException('Ringkasan kategori '.$key.' berulang pada blok UNIT GB '.$gb.'.');
                $summary[$key]['rows'][]=$r;
            }
        }
        foreach(['existing'=>['A','B','C'],'new'=>['D']] as $kind=>$categories)foreach($columns as $month=>$c){
            $total=array_diff($categories,array_keys($summary))?0.0:null;
            foreach($categories as $category)foreach($summary[$category]['rows']??[] as $r){
                $address=Coordinate::stringFromColumnIndex($c+1).($r+1);$cell=$sheet->getCell($address);
                try{$value=$cell->isFormula()?$cell->getCalculatedValue():$cell->getValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus UNIT tidak dapat dihitung pada TARGET GB '.$gb.' sel '.$address.'.');}
                if($value===null||$value===''||in_array(trim((string)$value),['-','–','—'],true))continue;
                if(!is_numeric($value))throw new InvalidArgumentException('Nilai UNIT bukan angka pada TARGET GB '.$gb.' sel '.$address.'.');
                $total=($total??0)+(float)$value;
            }
            $result[]=['report_year'=>$year,'product'=>'gb'.$gb,'kind'=>$kind,'month'=>$month,'units'=>$total===null?null:number_format($total,6,'.','')];
        }
    }
    foreach($book->getAllSheets() as $sheet)if($norm($sheet->getTitle())==='HRU')$result=array_merge($result,parseUnitSellOutIbTargets($sheet,$year));
    foreach($book->getAllSheets() as $sheet)if($norm($sheet->getTitle())==='GMEB')$result=array_merge($result,parseUnitSellOutEthicalTargets($sheet,$year));
    if(!$result)throw new InvalidArgumentException('Sheet TARGET GB yang sesuai tidak ditemukan.');
    return $result;
}
function ensureUnitSellOutTargets(mysqli $conn):void {
    $q=$conn->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('unit_sell_out_targets','target unit sell out')");
    $tables=array_column($q->fetch_all(MYSQLI_ASSOC),'TABLE_NAME');$q->free();
    if(in_array('unit_sell_out_targets',$tables,true)){
        if(in_array('target unit sell out',$tables,true))throw new InvalidArgumentException('Tabel target unit sell out dan tabel lama sama-sama tersedia. Periksa migrasi agar data tidak tertimpa.');
        $conn->query('RENAME TABLE `unit_sell_out_targets` TO `target unit sell out`');
    }
    $conn->query('CREATE TABLE IF NOT EXISTS `target unit sell out` (report_year SMALLINT UNSIGNED NOT NULL,product VARCHAR(30) NOT NULL,kind VARCHAR(12) NOT NULL,month TINYINT UNSIGNED NOT NULL,units DECIMAL(24,6) NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(report_year,product,kind,month)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
