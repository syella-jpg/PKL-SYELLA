<?php
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
function isBodKxRodSheet(Worksheet $sheet): bool {
    foreach($sheet->rangeToArray('A2:CX2',null,false,false)[0] as $v)
        if(strtoupper(preg_replace('/\s+/u',' ',trim((string)$v)))==='NILAI ROD UNIT TERKECIL DARI UNIT PESAN')return true;
    return false;
}
function parseBodKxRodTargets(Worksheet $sheet,int $year): array {
    $norm=fn($v)=>strtoupper(preg_replace('/\s+/u',' ',trim((string)$v)));
    $aliases=['JAN'=>1,'JANUARI'=>1,'FEB'=>2,'FEBRUARI'=>2,'MAR'=>3,'MARET'=>3,'APR'=>4,'APRIL'=>4,'MEI'=>5,'MAY'=>5,'JUN'=>6,'JUNI'=>6,'JUL'=>7,'JULI'=>7,'AUG'=>8,'AGU'=>8,'AGT'=>8,'AGUSTUS'=>8,'SEP'=>9,'SEPT'=>9,'SEPTEMBER'=>9,'OKT'=>10,'OCT'=>10,'OKTOBER'=>10,'NOV'=>11,'NOVEMBER'=>11,'DES'=>12,'DEC'=>12,'DESEMBER'=>12];
    $rows=$sheet->toArray(null,false,false,false);$columns=[];$month=null;
    foreach(($rows[0]??[]) as $c=>$v){
        $heading=$norm($v);
        if(isset($aliases[$heading]))$month=$aliases[$heading];
        elseif($heading!==''&&preg_match('/TOTAL|TAHUN|THN/',$heading))$month=null;
        if($month&&$norm($rows[1][$c]??'')==='NILAI ROD UNIT TERKECIL DARI UNIT PESAN'){
            if(isset($columns[$month]))throw new InvalidArgumentException('Kolom nilai target bulan yang sama berulang.');
            $columns[$month]=$c;
        }
    }
    if(count($columns)!==12)throw new InvalidArgumentException('File ROD harus memiliki kolom Nilai ROD Unit Terkecil Dari Unit Pesan untuk Januari–Desember.');
    $totals=[];$seen=[];
    foreach(array_slice($rows,2,null,true) as $r=>$row){
        $brand=$norm($row[1]??'');$code=trim((string)($row[2]??''));
        if($code===''||preg_match('/\b(?:SUB\s*TOTAL|TOTAL)\b/i',$code))continue;
        if(preg_match('/^GB\s*([1-6])$/',$brand,$m))$product='gb'.$m[1];
        elseif(in_array($brand,['GB ET','GB ETH','GB ETHICAL','ETHICAL'],true))$product='ethical';
        else continue;
        if((string)($row[0]??'')!==(string)$year)throw new InvalidArgumentException('Tahun baris produk ROD tidak sesuai tahun upload '.$year.'.');
        $key=$product.'|'.$code;if(isset($seen[$key]))throw new InvalidArgumentException('Produk ROD berulang pada GB yang sama; periksa file.');$seen[$key]=true;
        foreach($columns as $month=>$c){
            $value=$row[$c]??null;
            $address=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c+1).($r+1);
            $cell=$sheet->getCell($address);
            if($cell->isFormula()){
                try{$value=$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus nilai ROD gagal dihitung pada '.$address.'.');}
            }
            if($value===null||$value===''||in_array(trim((string)$value),['-','–','—'],true)){
                if(!array_key_exists($month,$totals[$product]??[]))$totals[$product][$month]=null;
                continue;
            }
            if(!is_numeric($value))throw new InvalidArgumentException('Nilai ROD bukan angka pada '.$address.'.');
            $totals[$product][$month]=($totals[$product][$month]??0)+(float)$value;
        }
    }
    if(!$totals)throw new InvalidArgumentException('Produk per GB tidak ditemukan pada file ROD.');
    $result=[];
    foreach($totals as $product=>$months)foreach($months as $month=>$value)$result[]=['report_year'=>$year,'month'=>$month,'product'=>$product,'rupiah'=>$value===null?null:number_format($value,6,'.','')];
    return $result;
}
function ensureBodKxTargets(mysqli $conn):void {
    $q=$conn->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (?,?)');
    $old='bod_kx_sell_in_targets';$new='BOD KX SELL IN target';
    $q->bind_param('ss',$old,$new);$q->execute();$names=array_column($q->get_result()->fetch_all(MYSQLI_ASSOC),'TABLE_NAME');$q->close();
    if(in_array($old,$names,true)&&!in_array($new,$names,true)){
        $conn->query('RENAME TABLE `bod_kx_sell_in_targets` TO `BOD KX SELL IN target`');
        return;
    }
    if(in_array($old,$names,true)&&in_array($new,$names,true)){
        $count=(int)$conn->query('SELECT COUNT(*) AS n FROM `BOD KX SELL IN target`')->fetch_assoc()['n'];
        if($count!==0)throw new InvalidArgumentException('Tabel target lama dan baru sama-sama berisi data. Periksa migrasi sebelum menyimpan agar data tidak tertimpa.');
        // Preserve even the empty pre-created destination while atomically moving the populated table.
        $backup='bod_kx_target_empty_'.bin2hex(random_bytes(4));
        $conn->query('RENAME TABLE `BOD KX SELL IN target` TO `'.$backup.'`, `bod_kx_sell_in_targets` TO `BOD KX SELL IN target`');
        return;
    }
    $conn->query('CREATE TABLE IF NOT EXISTS `BOD KX SELL IN target` (report_year SMALLINT UNSIGNED NOT NULL,month TINYINT UNSIGNED NOT NULL,product VARCHAR(30) NOT NULL,rupiah DECIMAL(24,6) NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(report_year,month,product)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
