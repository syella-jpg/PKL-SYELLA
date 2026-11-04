<?php
require_once __DIR__.'/biaya_input_template.php';

function loadBiayaTemplateHistory(mysqli $conn,string $period): array
{
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$parts))throw new InvalidArgumentException('Periode template BIAYA tidak valid.');
    if($parts[2]==='01')return [];
    $year=(int)$parts[1];
    try {
        $q=$conn->prepare('SELECT b.*,u.report_period FROM biaya b LEFT JOIN biaya_uploads u ON u.report_year=b.report_year WHERE b.report_year=? ORDER BY b.gb,b.nomor_produk,b.jenis');
        $q->bind_param('i',$year);$q->execute();$records=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();return $records;
    }catch(mysqli_sql_exception $error){if($error->getCode()===1146)return [];throw $error;}
}

function buildBiayaTemplateWithHistory(string $period,array $records): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$parts))throw new InvalidArgumentException('Periode template BIAYA tidak valid.');
    $year=(int)$parts[1];$month=(int)$parts[2];
    if($month===1)return buildBiayaInputTemplate($period);
    $records=array_values(array_filter($records,static fn($r)=>(int)($r['report_year']??0)===$year&&in_array($r['jenis']??'',['target','actual'],true)));
    $products=[];
    foreach($records as $record){
        $gb=(int)$record['gb'];$number=(int)$record['nomor_produk'];
        if($gb<1||$gb>6||$number<1)throw new RuntimeException('Identitas produk BIAYA tersimpan tidak valid.');
        $key=$gb.':'.$number;
        if(!isset($products[$key])||$record['jenis']==='target')$products[$key]=$record;
    }
    $slots=[];foreach($products as $r)$slots[(int)$r['gb']]=($slots[(int)$r['gb']]??0)+1;
    $book=buildBiayaInputTemplate($period,$slots);if(!$products)return $book;
    $targetName=$year===2025?'TARGET ROLLING':'TARGET';$target=$book->getSheetByName($targetName);$actual=$book->getSheetByName('REALISASI');
    $available=[];
    for($r=6;$r<=$target->getHighestDataRow();$r++)$available[(int)$target->getCell('A'.$r)->getValue()][]=$r;
    $positions=[];
    foreach($products as $key=>$record){
        $r=array_shift($available[(int)$record['gb']]);$positions[$key]=$r;
        $target->setCellValue('B'.$r,$record['kategori']);$target->setCellValue('C'.$r,(int)$record['nomor_produk']);$target->setCellValue('D'.$r,$record['nama_produk']);
    }
    $months=['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];$source=null;
    foreach($records as $record){
        $r=$positions[(int)$record['gb'].':'.(int)$record['nomor_produk']];$sheet=$record['jenis']==='target'?$target:$actual;
        $saved=$record['report_period']??($year.'-12');
        if(!preg_match('/^'.$year.'-(0[1-9]|1[0-2])$/D',(string)$saved))throw new RuntimeException('Periode BIAYA tersimpan tidak sesuai tahun.');
        $source=$saved;
        foreach($months as $i=>$key){
            if($saved>$period&&$i+1>$month)continue;
            $value=$record[$key]??null;if($value===null||$value==='')continue;
            if(!is_numeric($value)||!is_finite((float)$value))throw new RuntimeException('Nilai BIAYA tersimpan tidak valid.');
            $value=(float)$value;
            // New input uses uniform Rp (000); undo the legacy GB5 storage scaling.
            if($year===2026&&$record['jenis']==='target'&&(int)$record['gb']===5)$value/=1000;
            $sheet->setCellValue([$i+5,$r],$value);
        }
    }
    $book->getSheetByName('_TEMPLATE')->setCellValue('A6',$source);
    return $book;
}
