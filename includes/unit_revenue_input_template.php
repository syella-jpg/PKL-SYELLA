<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Border;

function unitRevenueTemplatePeriod(string $period): array
{
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$parts))throw new InvalidArgumentException('Periode UNIT & Revenue tidak valid.');
    return [(int)$parts[1],(int)$parts[2]];
}
function isUnitRevenueInputTemplate(Spreadsheet $book): bool
{
    return $book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue()==='KONIMEX_UNIT_REVENUE_V1';
}
function unitRevenueInputHeaders(): array
{
    return ['GB','PRODUCT CODE','PRINCIPAL PRODUCT CODE','PRODUCT NAME','KATEGORI','JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER','TOTAL'];
}
function buildUnitRevenueInputTemplate(string $period,int $capacity=1000): Spreadsheet
{
    [$year,$month]=unitRevenueTemplatePeriod($period);
    $capacity=max(1,min(10000-5,$capacity));$book=new Spreadsheet();$book->removeSheetByIndex(0);
    $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
    $months=array_slice(unitRevenueInputHeaders(),5,12);
    foreach(['UNIT','REVENUE'] as $name){
        $sheet=new Worksheet($book,$name);$book->addSheet($sheet);
        $sheet->setCellValue('A1','SELLING OUT — '.$name.' TAHUN '.$year);$sheet->mergeCells('A1:R1');
        $sheet->setCellValue('A2','PERIODE LAPORAN: '.$months[$month-1].' '.$year);$sheet->mergeCells('A2:R2');
        $sheet->setCellValue('A3',$name==='UNIT'?'Isi identitas produk dan jumlah UNIT per bulan. GB: 1–6 atau ET (Ethical). A/B/C/ABC = Existing; D = New Product; KAT E/E = Existing Ethical; NP = New Product.':'Identitas produk mengikuti sheet UNIT. Isi REVENUE per bulan dalam Rupiah penuh (bukan ribuan).');$sheet->mergeCells('A3:R3');
        $sheet->setCellValue('A4','Isi nilai bulanan, bukan kumulatif. TOTAL dan subtotal GB/Ethical dihitung otomatis oleh sistem. Baris yang tidak dipakai boleh kosong.');$sheet->mergeCells('A4:R4');
        $sheet->fromArray([unitRevenueInputHeaders()],null,'A5');
        for($r=6;$r<=5+$capacity;$r++){
            if($name==='REVENUE')foreach(['A','B','C','D','E'] as $col)$sheet->setCellValue($col.$r,"='UNIT'!".$col.$r);
            $sheet->setCellValue('R'.$r,'=SUM(F'.$r.':Q'.$r.')');
        }
        $end=5+$capacity;
        $sheet->getStyle('A6:Q'.$end)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF8DD');
        if($name==='REVENUE')$sheet->getStyle('A6:E'.$end)->getFill()->getStartColor()->setARGB('FFEAF2FF');
        $sheet->getStyle('R6:R'.$end)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF2FF');
        $sheet->getStyle('F6:R'.$end)->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');
        $sheet->getStyle('A6:E'.$end)->getNumberFormat()->setFormatCode($name==='REVENUE'?'0;-0;;@':'@');
        $sheet->getStyle('A5:R'.$end)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE1E5EB');
        $sheet->getStyle('A5:R5')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');
        $sheet->getStyle('A5:R5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:R1')->getFont()->setBold(true)->setSize(18);
        $sheet->getStyle('A2:R4')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF64748B'));
        $sheet->getStyle('A1:R'.$end)->getAlignment()->setVertical('center');
        $sheet->getStyle('A3:R5')->getAlignment()->setWrapText(true);
        foreach([1=>36,2=>24,3=>32,4=>28,5=>36] as $r=>$h)$sheet->getRowDimension($r)->setRowHeight($h);
        $sheet->getDefaultRowDimension()->setRowHeight(24);
        foreach(['A'=>12,'B'=>20,'C'=>24,'D'=>40,'E'=>14] as $col=>$width)$sheet->getColumnDimension($col)->setWidth($width);
        for($c=6;$c<=18;$c++)$sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(16);
        // One validation rule per range avoids scanning thousands of rules
        // for each new cell (quadratic work on large templates).
        if($name==='UNIT'){
            $gbRule=(new DataValidation())->setType(DataValidation::TYPE_LIST)->setFormula1('"GB 1,GB 2,GB 3,GB 4,GB 5,GB 6,GB ET"')->setShowDropDown(true)->setAllowBlank(true);
            $categoryRule=(new DataValidation())->setType(DataValidation::TYPE_LIST)->setFormula1('"A,B,C,ABC,D,E,KAT E,NP"')->setShowDropDown(true)->setAllowBlank(true);
            $sheet->setDataValidation('A6:A'.$end,$gbRule);
            $sheet->setDataValidation('E6:E'.$end,$categoryRule);
        }
        $numberRule=(new DataValidation())->setType(DataValidation::TYPE_DECIMAL)->setOperator(DataValidation::OPERATOR_BETWEEN)->setFormula1('-999999999999999')->setFormula2('999999999999999')->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)->setError('Isi angka atau biarkan kosong.');
        $sheet->setDataValidation('F6:Q'.$end,$numberRule);
        $sheet->freezePane('F6');$sheet->setShowGridlines(false);$sheet->setAutoFilter('A5:R'.$end);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:R'.$end)->setRowsToRepeatAtTopByStartAndEnd(5,5);
    }
    $revenue=$book->getSheetByName('REVENUE');
    $title=5+$capacity+3;
    $revenue->setCellValue('A'.$title,'ETHICAL — REVENUE MANUAL');$revenue->mergeCells('A'.$title.':R'.$title);
    $revenue->setCellValue('A'.($title+1),null);$revenue->mergeCells('A'.($title+1).':R'.($title+1));
    $revenue->setCellValue('A'.($title+2),'PARAMETER');
    foreach($months as $m=>$label)$revenue->setCellValue([$m+6,$title+2],$label);
    $revenue->setCellValue('R'.($title+2),'TOTAL');
    foreach(['existing'=>'EXIST','new'=>'NP','total'=>'TOTAL'] as $kind=>$label){
        $row=$title+3+array_search($kind,['existing','new','total'],true);
        $revenue->setCellValue('A'.$row,$label);$revenue->mergeCells('A'.$row.':E'.$row);
        foreach(range('F','Q') as $column){
            if($kind==='total')$formula='='.$column.($title+3).'+'.$column.($title+4);
            else {
                $categories=$kind==='existing'?['E','KAT E','A','B','C','ABC']:['NP','D'];
                $terms=[];
                foreach($categories as $category)$terms[]='SUMIFS('.$column.'$6:'.$column.'$'.$end.',$A$6:$A$'.$end.',"*ET*",$E$6:$E$'.$end.',"'.$category.'")';
                $formula='='.implode('+',$terms);
            }
            $revenue->setCellValue($column.$row,$formula);
        }
        $revenue->setCellValue('R'.$row,'=SUM(F'.$row.':Q'.$row.')');
        $revenue->getRowDimension($row)->setRowHeight(26);
    }
    $revenue->getStyle('A'.$title.':R'.$title)->getFont()->setBold(true)->setSize(13);
    $revenue->getStyle('A'.($title+2).':R'.($title+2))->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');
    $revenue->getStyle('A'.($title+2).':R'.($title+2))->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $revenue->getStyle('F'.($title+3).':Q'.($title+5))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF8DD');
    $revenue->getStyle('F'.($title+3).':R'.($title+5))->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');
    $revenue->getStyle('A'.($title+2).':R'.($title+5))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE1E5EB');
    $revenue->getPageSetup()->setPrintArea('A1:R'.($title+5));
    $revenue->setCellValue('A4','Isi nilai bulanan, bukan kumulatif. Rupiah Ethical dihitung dari kategori produk di bagian bawah. Subtotal GB dihitung otomatis.');
    $meta=new Worksheet($book,'_TEMPLATE');$book->addSheet($meta);$meta->fromArray([['KONIMEX_UNIT_REVENUE_V1'],['UNIT & Revenue'],[$year],[$period]]);$meta->setCellValue('A5','ETHICAL_REVENUE_MANUAL_V1');$meta->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $book->setActiveSheetIndex(0);return $book;
}
function unitRevenueEthicalManualRows(Spreadsheet $book): array
{
    $sheet=$book->getSheetByName('REVENUE');if(!$sheet)return [];
    foreach($sheet->getRowIterator() as $row){
        $r=$row->getRowIndex();
        if(trim((string)$sheet->getCell('A'.$r)->getValue())!=='ETHICAL — REVENUE MANUAL')continue;
        $out=[];
        foreach(['existing'=>'EXIST','new'=>'NP','total'=>'TOTAL'] as $kind=>$label){
            $at=$r+3+array_search($kind,['existing','new','total'],true);
            if(trim((string)$sheet->getCell('A'.$at)->getValue())!==$label)throw new InvalidArgumentException('Baris manual Ethical berubah. Gunakan susunan EXIST, NP, TOTAL.');
            $out[$kind]=$at;
        }
        return $out;
    }
    return [];
}
function normalizeUnitRevenueGroup($value): ?string
{
    $label=strtoupper(trim((string)$value));
    if(preg_match('/^(?:GB\s*)?([1-6])$/',$label,$m))return $m[1];
    if(preg_match('/^(?:GB\s*)?(?:ET|ETH|ETHICAL)$/',$label))return 'ET';
    return null;
}
function parseUnitRevenueInputTemplate(Spreadsheet $book,string $period): array
{
    [$year]=unitRevenueTemplatePeriod($period);
    if(!isUnitRevenueInputTemplate($book)||(int)$book->getSheetByName('_TEMPLATE')->getCell('A3')->getValue()!==$year)throw new InvalidArgumentException('Tahun template UNIT & Revenue tidak sesuai periode upload.');
    $unit=$book->getSheetByName('UNIT');$revenue=$book->getSheetByName('REVENUE');
    if(!$unit||!$revenue)throw new InvalidArgumentException('Gunakan template yang memiliki sheet UNIT dan REVENUE.');
    foreach([$unit,$revenue] as $sheet)foreach(unitRevenueInputHeaders() as $c=>$header)if(strtoupper(trim((string)$sheet->getCell([$c+1,5])->getValue()))!==$header)throw new InvalidArgumentException('Header template UNIT & Revenue berubah. Unduh template terbaru.');
    $records=[];$seen=[];$hasNumber=false;$manualRows=unitRevenueEthicalManualRows($book);
    $manualMode=$book->getSheetByName('_TEMPLATE')->getCell('A5')->getValue()==='ETHICAL_REVENUE_MANUAL_V1';
    if($manualMode&&!$manualRows)throw new InvalidArgumentException('Bagian rupiah Ethical manual tidak ditemukan.');
    $last=$manualRows?max($unit->getHighestDataRow(),min($manualRows)-6):max($unit->getHighestDataRow(),$revenue->getHighestDataRow());
    if($last>10000)throw new InvalidArgumentException('Template UNIT & Revenue maksimal 9.995 baris produk.');
    for($r=6;$r<=$last;$r++){
        $identity=[];foreach(range(1,5) as $c)$identity[]=trim((string)$unit->getCell([$c,$r])->getCalculatedValue());
        $arrays=[];$rowHasNumber=false;
        foreach(['qty'=>$unit,'rev'=>$revenue] as $kind=>$sheet){
            $values=[];
            for($c=6;$c<=17;$c++){
                $cell=$sheet->getCell([$c,$r]);$v=$cell->getCalculatedValue();if(is_string($v))$v=trim($v);
                if($v===null||$v===''||in_array($v,['-','–','—'],true))$v=null;
                elseif(!is_numeric($v)||!is_finite((float)$v))throw new InvalidArgumentException('Nilai tidak valid pada '.$sheet->getTitle().' sel '.$cell->getCoordinate().'.');
                else{$v=(float)$v;$rowHasNumber=true;}
                $values[]=$v;
            }
            $arrays[$kind]=$values;
        }
        if(!array_filter($identity,static fn($v)=>$v!=='')&&!$rowHasNumber)continue;
        [$rawGb,$code,$principal,$product,$category]=$identity;$gb=normalizeUnitRevenueGroup($rawGb);$category=strtoupper($category);$category=preg_replace('/^KAT(?:EGORI)?\.?\s+/','',$category);if($category==='NP')$category='D';
        if(!$gb||$product===''||!in_array($category,['A','B','C','ABC','D','E'],true))throw new InvalidArgumentException('Isi GB, nama produk, dan kategori A/B/C/ABC/D/E pada sheet UNIT baris '.$r.'.');
        if($category==='E' && $gb!=='ET')throw new InvalidArgumentException('Kategori E hanya digunakan untuk Existing Ethical (baris '.$r.').');
        foreach([$code,$principal,$product] as $label) {
            if(preg_match('/^(?:GB\s*[1-6]$|TOTAL\b|GRAND\s+TOTAL\b|(?:EXIST(?:ING)?|NP|NEW(?:\s+PRODUCT)?)\s+(?:ETH(?:ICAL)?|GB\s*(?:[1-6]|ET))$)/i',$label))throw new InvalidArgumentException('Isi identitas produk saja; label GB/subtotal dihitung sistem (baris '.$r.').');
        }
        foreach([[$gb,50],[$code,100],[$principal,100],[$product,255],[$category,100]] as [$v,$limit])if(mb_strlen($v)>$limit)throw new InvalidArgumentException('Identitas produk terlalu panjang pada baris '.$r.'.');
        // Linked identities may be overwritten accidentally; do not silently lose them.
        foreach(range(1,5) as $i){
            $cell=$revenue->getCell([$i,$r]);if($cell->isFormula())continue;
            $v=trim((string)$cell->getValue());
            if($v!==''&&$v!==$identity[$i-1])throw new InvalidArgumentException('Identitas REVENUE berbeda dari UNIT pada baris '.$r.'.');
        }
        $key=json_encode(array_map(static fn($v)=>mb_strtoupper($v),[$gb,$code,$principal,$product,$category]));if(isset($seen[$key]))throw new InvalidArgumentException('Produk ganda pada baris '.$r.'. Gabungkan nilai ke satu baris.');$seen[$key]=true;
        $records[]=['gb'=>$gb,'product_code'=>$code,'principal_product_code'=>$principal,'product_name'=>$product,'kategori'=>$category,...$arrays];
        $hasNumber=$hasNumber||$rowHasNumber;
    }
    foreach($manualRows as $kind=>$r){
        $values=[];
        for($c=6;$c<=17;$c++){
            $cell=$revenue->getCell([$c,$r]);$value=$cell->getCalculatedValue();if(is_string($value))$value=trim($value);
            if($value===null||$value==='')$value=null;
            elseif(!is_numeric($value)||!is_finite((float)$value))throw new InvalidArgumentException('Nilai Ethical tidak valid di REVENUE sel '.$cell->getCoordinate().'.');
            else{$value=(float)$value;$hasNumber=true;}
            $values[]=$value;
        }
        $records[]=['__kind'=>'ethical_revenue','section'=>$kind,'rev'=>$values];
    }
    if(!$hasNumber)throw new InvalidArgumentException('Template UNIT & Revenue masih kosong. Isi minimal satu nilai unit atau rupiah sebelum upload.');
    return $records;
}
function normalizeUnitRevenueInputTemplate(array $records): Spreadsheet
{
    $book=new Spreadsheet();$sheet=$book->getActiveSheet();$sheet->setTitle('DATA');
    $months=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGT','SEP','OKT','NOV','DES'];
    $headers=['GB','PRODUCT CODE','PRINCIPAL PRODUCT CODE','PRODUCT NAME','KATEGORI',...array_map(static fn($m)=>'QTY '.$m,$months),'QTY TOTAL',...array_map(static fn($m)=>'REV '.$m,$months),'REV TOTAL'];
    $sheet->fromArray([$headers],null,'A4');$out=[];$groups=[];$manual=[];
    $sum=static fn($values)=>array_sum(array_map(static fn($v)=>(float)($v??0),$values));
    foreach($records as $record){
        if(($record['__kind']??null)==='ethical_revenue'){$manual[$record['section']]=$record['rev'];continue;}
        $qty=array_map(static fn($v)=>(float)($v??0),$record['qty']);$rev=array_map(static fn($v)=>(float)($v??0),$record['rev']);
        $out[]=[$record['gb'],$record['product_code'],$record['principal_product_code'],$record['product_name'],$record['kategori'],...$qty,$sum($qty),...$rev,$sum($rev)];
        $gb=$record['gb'];$kind=in_array($record['kategori'],['D','NP'],true)?'new':'existing';
        if(!isset($groups[$gb]))$groups[$gb]=['existing'=>['qty'=>array_fill(0,12,0.0),'rev'=>array_fill(0,12,0.0)],'new'=>['qty'=>array_fill(0,12,0.0),'rev'=>array_fill(0,12,0.0)],'other'=>['qty'=>array_fill(0,12,0.0),'rev'=>array_fill(0,12,0.0)]];
        foreach(['qty'=>$qty,'rev'=>$rev] as $field=>$values)foreach($values as $m=>$v)$groups[$gb][$kind][$field][$m]+=$v;
    }
    $hasManualNumbers=false;foreach($manual as $values)foreach($values as $v)if($v!==null)$hasManualNumbers=true;
    if($hasManualNumbers&&!isset($groups['ET']))$groups['ET']=['existing'=>['qty'=>array_fill(0,12,0.0),'rev'=>array_fill(0,12,0.0)],'new'=>['qty'=>array_fill(0,12,0.0),'rev'=>array_fill(0,12,0.0)],'other'=>['qty'=>array_fill(0,12,0.0),'rev'=>array_fill(0,12,0.0)]];
    foreach($groups as $gb=>$kinds){
        foreach(['existing','new','total'] as $kind){
            $qty=$kind==='total'?array_map(static fn($a,$b,$c)=>$a+$b+$c,$kinds['existing']['qty'],$kinds['new']['qty'],$kinds['other']['qty']):$kinds[$kind]['qty'];
            $rev=$kind==='total'?array_map(static fn($a,$b,$c)=>$a+$b+$c,$kinds['existing']['rev'],$kinds['new']['rev'],$kinds['other']['rev']):$kinds[$kind]['rev'];
            if($gb==='ET'&&array_key_exists($kind,$manual))$rev=array_map(static fn($v)=>(float)($v??0),$manual[$kind]);
            $label=$gb==='ET'?(['existing'=>'EXIST ETH','new'=>'NP ETH','total'=>'TOTAL ETH'][$kind]):(['existing'=>'TOTAL EXISTING GB ','new'=>'TOTAL NEW PRODUCT GB ','total'=>'TOTAL GB '][$kind].$gb);
            $category=$gb==='ET'&&$kind==='total'?'TOTAL ETHICAL':'';
            $out[]=[(string)$gb,'','',$label,$category,...$qty,$sum($qty),...$rev,$sum($rev)];
        }
    }
    // Explicit identity strings preserve leading zeroes on spreadsheet reload.
    foreach($out as $i=>$values)foreach($values as $c=>$value){$cell=$sheet->getCell([$c+1,$i+5]);if($c<5)$cell->setValueExplicit((string)$value,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);else $cell->setValue($value);}
    return $book;
}
function ensureUnitRevenueTemplateStore($conn): void
{
    $conn->query('CREATE TABLE IF NOT EXISTS unit_revenue_input_snapshots (report_period CHAR(7) NOT NULL PRIMARY KEY,payload LONGBLOB NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function saveUnitRevenueTemplateSnapshot($conn,string $period,?array $records): void
{
    if($records===null){$q=$conn->prepare('DELETE FROM unit_revenue_input_snapshots WHERE report_period=?');$q->bind_param('s',$period);}
    else{$payload=gzencode(json_encode($records,JSON_THROW_ON_ERROR),6);$q=$conn->prepare('INSERT INTO unit_revenue_input_snapshots (report_period,payload) VALUES (?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),updated_at=CURRENT_TIMESTAMP');$q->bind_param('ss',$period,$payload);}
    $q->execute();$q->close();
}
