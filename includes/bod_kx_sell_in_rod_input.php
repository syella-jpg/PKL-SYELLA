<?php
require_once __DIR__.'/bod_kx_sell_in_input_template.php';
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** Read the two requested monthly ROD columns, ignoring RF, buffers, cartons and annual totals. */
function parseBodKxRodInput(Worksheet $sheet,int $year): array {
    $text=[];$headerRows=[];$dataRows=[];
    foreach($sheet->getCellCollection()->getCoordinates() as $address){$cell=$sheet->getCell($address);[$letter,$row]=Coordinate::coordinateFromString($address);$column=Coordinate::columnIndexFromString($letter);$dataRows[$row]=true;
        if(is_string($cell->getValue())&&!$cell->isFormula()){$label=bodKxInputNorm($cell->getValue());$text[$row][$column]=$label;if($label==='ROD UNIT TERKECIL DARI UNIT PESAN')$headerRows[$row]=true;}
    }
    if(count($headerRows)!==1)throw new InvalidArgumentException('Header unit ROD tidak ditemukan atau berulang.');$subheader=array_key_first($headerRows);
    $aliases=['JAN'=>0,'JANUARI'=>0,'FEB'=>1,'FEBRUARI'=>1,'MAR'=>2,'MARET'=>2,'APR'=>3,'APRIL'=>3,'MEI'=>4,'MAY'=>4,'JUN'=>5,'JUNI'=>5,'JUL'=>6,'JULI'=>6,'AUG'=>7,'AGU'=>7,'AGT'=>7,'AGS'=>7,'AGUSTUS'=>7,'SEP'=>8,'SEPT'=>8,'SEPTEMBER'=>8,'OCT'=>9,'OKT'=>9,'OKTOBER'=>9,'NOV'=>10,'NOVEMBER'=>10,'DES'=>11,'DEC'=>11,'DESEMBER'=>11];
    $heading=null;foreach($text as $row=>$values)if($row<$subheader&&count(array_intersect(array_keys($aliases),$values))>=12)$heading=$heading===null?$row:max($heading,$row);
    if($heading===null)throw new InvalidArgumentException('Header bulan Januari–Desember pada ROD tidak lengkap.');
    $identity=[];$wanted=['TAHUN'=>'year','GROUP BRAND'=>'group','KODE'=>'code','NAMA'=>'name','NAMA PRODUK'=>'name','HARGA BELI'=>'price'];
    foreach([$text[$heading]??[],$text[$subheader]??[]] as $values)foreach($values as $column=>$label)if(isset($wanted[$label])){if(isset($identity[$wanted[$label]])&&$identity[$wanted[$label]]!==$column)throw new InvalidArgumentException('Kolom identitas ROD berulang.');$identity[$wanted[$label]]=$column;}
    if(count($identity)!==5)throw new InvalidArgumentException('ROD harus memiliki Tahun, Group Brand, Kode, Nama Produk, dan Harga Beli.');
    $columns=[];$month=null;$width=Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    for($c=1;$c<=$width;$c++){$title=$text[$heading][$c]??'';if($title!=='')$month=$aliases[$title]??null;if($month===null)continue;$label=$text[$subheader][$c]??'';$field=match($label){'ROD UNIT TERKECIL DARI UNIT PESAN'=>'units','NILAI ROD UNIT TERKECIL DARI UNIT PESAN'=>'rupiah',default=>null};if($field!==null){if(isset($columns[$month][$field]))throw new InvalidArgumentException('Kolom bulan ROD berulang.');$columns[$month][$field]=$c;}}
    if(count($columns)!==12)throw new InvalidArgumentException('Kolom ROD Januari–Desember tidak lengkap.');foreach($columns as $pair)if(count($pair)!==2)throw new InvalidArgumentException('Setiap bulan harus memiliki Unit ROD dan Nilai ROD.');ksort($columns);
    $input=['year'=>$year,'sales'=>[],'rod'=>[],'_rod_import'=>true];$seen=[];$has=false;ksort($dataRows);
    foreach($dataRows as $row=>$yes){if($row<=$subheader)continue;$group=bodKxInputGroupCode($sheet->getCell([$identity['group'],$row])->getValue(),true);if($group===null)continue;
        $code=trim((string)$sheet->getCell([$identity['code'],$row])->getFormattedValue());$name=trim((string)$sheet->getCell([$identity['name'],$row])->getValue());if(preg_match('/\b(?:SUB\s*TOTAL|TOTAL)\b/i',$code)||preg_match('/^(?:SUB\s*TOTAL|GRAND\s+TOTAL|TOTAL)\b/i',$name))continue;
        $units=[];$rowHas=false;foreach($columns as $m=>$pair){$value=bodKxInputCellNumber($sheet,$pair['units'],$row);$units[$m]=$value;$rowHas=$rowHas||$value!==null;}
        if($code===''&&$name===''&&!$rowHas)continue;if($code===''||$name===''||mb_strlen($code)>100||mb_strlen($name)>255)throw new InvalidArgumentException('Isi Kode dan Nama Produk pada ROD baris '.$row.'.');
        if(trim((string)$sheet->getCell([$identity['year'],$row])->getValue())!==(string)$year)throw new InvalidArgumentException('Tahun produk ROD tidak sesuai periode upload (baris '.$row.').');
        $price=bodKxInputCellNumber($sheet,$identity['price'],$row);if(($price===null&&$rowHas)||($price!==null&&$price<0))throw new InvalidArgumentException('Isi Harga Beli nol atau positif pada ROD baris '.$row.'.');
        foreach($units as $m=>$value)if($value!==null)bodKxInputNumber($value*$price,'hasil Nilai ROD baris '.$row);
        $key=$group.'|'.mb_strtoupper($code);if(isset($seen[$key]))throw new InvalidArgumentException('Kode produk ROD berulang pada kelompok yang sama (baris '.$row.').');$seen[$key]=true;
        $input['rod'][]=['product'=>$group,'code'=>$code,'name'=>$name,'price'=>$price,'units'=>$units];$has=$has||$rowHas;
    }
    if(!$has)throw new InvalidArgumentException('Unit ROD masih kosong. Belum ada target yang dapat dimasukkan ke template.');return $input;
}
