<?php
/** Four product groups, monthly TARGET/REAL UNIT/HNA columns, server-recomputed totals. */
function ethicalProductBlocks(bool $legacy = false, bool $withReal = true): array {
    $blocks=[];
    $title=6;
    foreach(['existing','streamline','np_previous','np_current'] as $kind){
        $count=$legacy?250:($kind==='existing'?100:50);$headers=$withReal?4:3;
        $blocks[$kind]=['title'=>$title,'header'=>$title+1,'first'=>$title+$headers,'last'=>$title+$headers+$count-1,'total'=>$title+$headers+$count];
        $title+=$count+($withReal?8:7);
    }
    return $blocks;
}
function ethicalProductLabels(int $year): array {
    return ['existing'=>'EXISTING','streamline'=>'STREAMLINE','np_previous'=>'NP '.($year-1),'np_current'=>'NP '.$year];
}
function buildEthicalSourceTemplate(string $period): \PhpOffice\PhpSpreadsheet\Spreadsheet {
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$m))throw new InvalidArgumentException('Periode ETHICAL tidak valid.');
    $year=(int)$m[1];$month=(int)$m[2];$months=unitSellOutInputMonths();
    $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$book->getActiveSheet();$s->setTitle('PRODUK NAS');$s->setShowGridlines(false);
    $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
    foreach([1=>'ETHICAL — TARGET UNIT & HNA — TAHUN '.$year,2=>'PERIODE LAPORAN: '.$months[$month-1].' '.$year,3=>'Isi nama produk dan TARGET dan REAL UNIT/HNA per bulan pada sel kuning. Subtotal dan ringkasan dihitung otomatis.',4=>'Upload sekali di ETHICAL untuk menyimpan TARGET dan REAL UNIT/HNA.'] as $r=>$label){$s->setCellValue('A'.$r,$label);$s->mergeCells('A'.$r.':AB'.$r);}
    $s->getStyle('A1')->getFont()->setSize(19)->setBold(true);$s->getRowDimension(1)->setRowHeight(36);
    $s->getColumnDimension('A')->setWidth(8);$s->getColumnDimension('B')->setWidth(20);$s->getColumnDimension('C')->setWidth(36);
    for($c=4;$c<=59;$c++)$s->getColumnDimensionByColumn($c)->setWidth(17);
    $labels=ethicalProductLabels($year);$blocks=ethicalProductBlocks();
    $dark=function(int $r,string $color)use($s){$s->getStyle('A'.$r.':BG'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB($color);$s->getStyle('A'.$r.':BG'.$r)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$s->getRowDimension($r)->setRowHeight(28);};
    foreach($blocks as $kind=>$b){
        $s->setCellValue('A'.$b['title'],'PRODUK '.$labels[$kind]);$s->mergeCells('A'.$b['title'].':BG'.$b['title']);$dark($b['title'],'FF345C50');
        foreach(['NO.','KODE PRODUK','NAMA PRODUK'] as $c=>$label){$s->setCellValue([$c+1,$b['header']],$label);$col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c+1);$s->mergeCells($col.$b['header'].':'.$col.($b['header']+2));}
        foreach([...$months,'YTD '.$months[$month-1],'TOTAL FY'] as $i=>$label){
            $c=4+$i*4;$left=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);$right=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c+3);
            $s->setCellValue([$c,$b['header']],$label);$s->mergeCells($left.$b['header'].':'.$right.$b['header']);
            foreach(['TARGET','REAL'] as $j=>$type){$start=$c+$j*2;$l=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($start);$r=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($start+1);$s->setCellValue([$start,$b['header']+1],$type);$s->mergeCells($l.($b['header']+1).':'.$r.($b['header']+1));$s->setCellValue([$start,$b['header']+2],'UNIT');$s->setCellValue([$start+1,$b['header']+2],'HNA');}
        }
        foreach([0,1,2] as $offset)$dark($b['header']+$offset,'FF29354A');
        $s->getStyle('B'.$b['first'].':AY'.$b['last'])->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF3C4');
        for($r=$b['first'];$r<=$b['last'];$r++){
            $s->setCellValue('A'.$r,$r-$b['first']+1);
            foreach([0,1,2,3] as $offset){$ytd=[];$fy=[];for($i=0;$i<12;$i++){$cell=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4+$i*4+$offset).$r;$fy[]=$cell;if($i<$month)$ytd[]=$cell;}$s->setCellValue([52+$offset,$r],'=SUM('.implode(',',$ytd).')');$s->setCellValue([56+$offset,$r],'=SUM('.implode(',',$fy).')');}
        }
        $s->setCellValue('A'.$b['total'],'TOTAL '.$labels[$kind]);$s->mergeCells('A'.$b['total'].':C'.$b['total']);
        for($c=4;$c<=59;$c++){$col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);$s->setCellValue([$c,$b['total']],'=SUM('.$col.$b['first'].':'.$col.$b['last'].')');}
        $s->getStyle('A'.$b['total'].':BG'.$b['total'])->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');$s->getStyle('A'.$b['total'].':BG'.$b['total'])->getFont()->setBold(true);
        $s->getStyle('D'.$b['first'].':BG'.$b['total'])->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');
        $s->getStyle('A'.$b['header'].':BG'.$b['total'])->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFDDE4EE');
        $rule=(new \PhpOffice\PhpSpreadsheet\Cell\DataValidation())->setType('decimal')->setOperator('between')->setFormula1('-99999999999999999')->setFormula2('99999999999999999')->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle('stop')->setError('Isi angka, termasuk negatif untuk koreksi.');$s->setDataValidation('D'.$b['first'].':AY'.$b['last'],$rule);
    }
    $summary=$blocks['np_current']['total']+10;
    foreach(['unit','hna','actual_unit','actual_hna'] as $metric){
        $s->setCellValue('A'.$summary,'RINGKASAN '.(str_starts_with($metric,'actual_')?'REAL ':'TARGET ').strtoupper(str_replace('actual_','',$metric)));$s->mergeCells('A'.$summary.':Q'.$summary);$dark($summary,'FF345C50');
        $s->setCellValue('A'.($summary+1),'SUBTOTAL PRODUK NAS');$s->mergeCells('A'.($summary+1).':C'.($summary+1));foreach([...$months,'YTD '.$months[$month-1],'TOTAL FY'] as $c=>$label)$s->setCellValue([$c+4,$summary+1],$label);$dark($summary+1,'FF29354A');
        foreach(array_keys($blocks) as $i=>$kind){$r=$summary+2+$i;$s->setCellValue('A'.$r,'TOTAL '.$labels[$kind]);$s->mergeCells('A'.$r.':C'.$r);for($j=0;$j<14;$j++){$col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(4+$j*4+array_search($metric,['unit','hna','actual_unit','actual_hna'],true));$s->setCellValue([$j+4,$r],'='.$col.$blocks[$kind]['total']);}}
        foreach(['EXISTING','NP','TOTAL ETHICAL'] as $i=>$label){$r=$summary+7+$i;$s->setCellValue('A'.$r,$label);$s->mergeCells('A'.$r.':C'.$r);for($c=4;$c<=17;$c++){$col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);$a=$i===0?$summary+2:($i===1?$summary+4:$summary+7);$s->setCellValue([$c,$r],'='.$col.$a.'+'.$col.($a+1));}}
        $s->getStyle('D'.($summary+2).':Q'.($summary+9))->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');$s->getStyle('A'.($summary+2).':Q'.($summary+9))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');$summary+=13;
    }
    $s->freezePane('D10');$s->setSelectedCell('C10');$s->getSheetView()->setZoomScale(75);$s->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
    $meta=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,'_TEMPLATE');$book->addSheet($meta);$meta->fromArray([['KONIMEX_ETHICAL_SOURCE_V6'],['ETHICAL'],[$year],[$period]]);$meta->setSheetState('hidden');$s->removeColumn('B');$s->insertNewColumnBefore('C',2);
    $s->getColumnDimension('C')->setWidth(18);$s->getColumnDimension('D')->setWidth(18);
    foreach($blocks as $block){
        foreach(['C'=>'Kategori','D'=>'HNA'] as $col=>$label){$s->setCellValue($col.$block['header'],$label);$s->mergeCells($col.$block['header'].':'.$col.($block['header']+2));$s->getStyle($col.$block['header'].':'.$col.($block['header']+2))->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');$s->getStyle($col.$block['header'])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');}
        $s->getStyle('D'.$block['first'].':D'.$block['last'])->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');$priceRule=clone $rule;$priceRule->setOperator('greaterThanOrEqual')->setFormula1('0')->setFormula2('')->setError('HNA produk harus nol atau positif.');$s->setDataValidation('D'.$block['first'].':D'.$block['last'],$priceRule);
        $s->getStyle('C'.$block['first'].':D'.$block['last'])->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF3C4');
        for($r=$block['first'];$r<=$block['last'];$r++)for($m=0;$m<12;$m++)foreach([0,2] as $offset){$unit=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5+$m*4+$offset);$s->setCellValue([6+$m*4+$offset,$r],'=D'.$r.'*'.$unit.$r);$s->getStyle(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6+$m*4+$offset).$r)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');}
    }
    $s->setCellValue('A3','Isi nama produk, Kategori, HNA produk, dan UNIT bulanan. HNA bulanan = HNA produk × UNIT.');$s->freezePane('E10');$s->setSelectedCell('B10');return $book;
}
function parseEthicalProductSource(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $period): array {
    $year=(int)substr($period,0,4);if((int)$book->getSheetByName('_TEMPLATE')->getCell('A3')->getValue()!==$year)throw new InvalidArgumentException('Tahun template ETHICAL berbeda dari periode upload.');
    $s=$book->getSheetByName('PRODUK NAS');if(!$s)throw new InvalidArgumentException('Sheet PRODUK NAS tidak ditemukan.');
    $version=$book->getSheetByName('_TEMPLATE')->getCell('A1')->getValue();$priced=$version==='KONIMEX_ETHICAL_SOURCE_V6';$withoutCode=in_array($version,['KONIMEX_ETHICAL_SOURCE_V5','KONIMEX_ETHICAL_SOURCE_V6'],true);$base=$priced?5:($withoutCode?3:4);$withReal=in_array($version,['KONIMEX_ETHICAL_SOURCE_V4','KONIMEX_ETHICAL_SOURCE_V5','KONIMEX_ETHICAL_SOURCE_V6'],true);$step=$withReal?4:2;$metrics=$withReal?['unit'=>0,'hna'=>1,'actual_unit'=>2,'actual_hna'=>3]:['unit'=>0,'hna'=>1];
    $inputs=[];$products=[];$any=false;$labels=ethicalProductLabels($year);
    foreach(ethicalProductBlocks($book->getSheetByName('_TEMPLATE')->getCell('A1')->getValue()==='KONIMEX_ETHICAL_SOURCE_V2',$withReal) as $kind=>$b){
        if($s->getCell('A'.$b['title'])->getValue()!=='PRODUK '.$labels[$kind])throw new InvalidArgumentException('Label kelompok produk ETHICAL berubah.');
        foreach(unitSellOutInputMonths() as $i=>$month){$c=$base+$i*$step;if($s->getCell([$c,$b['header']])->getValue()!==$month)throw new InvalidArgumentException('Kolom bulan ETHICAL berubah.');foreach($metrics as $metric=>$offset){if($s->getCell([$c+$offset,$b['header']+($withReal?2:1)])->getValue()!==(str_contains($metric,'hna')?'HNA':'UNIT'))throw new InvalidArgumentException('Kolom UNIT/HNA ETHICAL berubah.');}if($withReal&&($s->getCell([$c,$b['header']+1])->getValue()!=='TARGET'||$s->getCell([$c+2,$b['header']+1])->getValue()!=='REAL'))throw new InvalidArgumentException('Kolom TARGET/REAL ETHICAL berubah.');}
        foreach($metrics as $metric=>$offset)$inputs[$metric][$kind]=array_fill(0,12,null);
        $products[$kind]=[];$seen=[];
        for($r=$b['first'];$r<=$b['last'];$r++){
            $name=trim((string)$s->getCell(($withoutCode?'B':'C').$r)->getValue());$code=$withoutCode?'':trim((string)$s->getCell('B'.$r)->getValue());$row=['code'=>$code,'name'=>$name];$has=false;if($priced){$row['category']=trim((string)$s->getCell('C'.$r)->getValue());$row['price']=ethicalSourceNumber($s->getCell('D'.$r)->getCalculatedValue(),'D'.$r);}
            foreach($metrics as $metric=>$offset){$row[$metric]=[];for($m=0;$m<12;$m++){$cell=$s->getCell([$base+$m*$step+$offset,$r]);try{$value=$priced&&str_contains($metric,'hna')&&$cell->getDataType()===\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA?null:$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus produk ETHICAL tidak dapat dihitung pada '.$cell->getCoordinate().'.');}$v=ethicalSourceNumber($value,$cell->getCoordinate(),true);if($priced&&str_contains($metric,'hna')){
                    $unitMetric=$metric==='hna'?'unit':'actual_unit';$units=$row[$unitMetric][$m];
                    if($row['price']!==null)$v=$units===null?null:$row['price']*$units;
                    elseif($cell->getDataType()===\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA){if($units!==null&&$units!=0)throw new InvalidArgumentException('Isi HNA produk pada baris '.$r.'.');$v=$units===null?null:0.0;}
                    $v=ethicalSourceNumber($v,$cell->getCoordinate(),true);
                }$row[$metric][]=$v;if($v!==null){$has=true;$inputs[$metric][$kind][$m]=($inputs[$metric][$kind][$m]??0)+$v;}}}
            if(!$has&&$name===''&&$code==='')continue;
            if($name==='')throw new InvalidArgumentException('Isi NAMA PRODUK pada baris '.$r.'.');
            $key=strtolower($code!==''?'code:'.$code:'name:'.$name);if(isset($seen[$key]))throw new InvalidArgumentException('Produk berulang dalam kelompok '.$labels[$kind].' pada baris '.$r.'.');$seen[$key]=true;
            $products[$kind][]=$row;$any=$any||$has;
        }
    }
    if(!$any)throw new InvalidArgumentException('Template ETHICAL masih kosong. Isi minimal satu nilai TARGET atau REAL UNIT/HNA.');
    $inputs['_products']=$products;return ['year'=>$year,'inputs'=>$inputs,'summary'=>ethicalSourceSummary($inputs)];
}
function buildEthicalSourceWithHistory(string $period,?array $record): \PhpOffice\PhpSpreadsheet\Spreadsheet {
    $book=buildEthicalSourceTemplate($period);if(substr($period,5,2)==='01'||!$record||(int)$record['report_year']!==(int)substr($period,0,4))return $book;
    $inputs=json_decode($record['payload'],true,512,JSON_THROW_ON_ERROR);$s=$book->getSheetByName('PRODUK NAS');
    foreach(ethicalProductBlocks() as $kind=>$b){
        $products=$inputs['_products'][$kind]??null;
        if($products===null){$products=[['code'=>'','name'=>'Subtotal tersimpan sebelumnya','unit'=>$inputs['unit'][$kind]??array_fill(0,12,null),'hna'=>$inputs['hna'][$kind]??array_fill(0,12,null),'actual_unit'=>$inputs['actual_unit'][$kind]??array_fill(0,12,null),'actual_hna'=>$inputs['actual_hna'][$kind]??array_fill(0,12,null)]];}
        if(count($products)>$b['last']-$b['first']+1)throw new RuntimeException('Jumlah produk ETHICAL tersimpan melebihi kapasitas kelompok '.$kind.'.');
        foreach($products as $i=>$p){$r=$b['first']+$i;$s->setCellValueExplicit('B'.$r,$p['name'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);$s->setCellValueExplicit('C'.$r,$p['category']??'',\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);if(isset($p['price']))$s->setCellValue('D'.$r,$p['price']);foreach(['unit'=>0,'hna'=>1,'actual_unit'=>2,'actual_hna'=>3] as $metric=>$offset)foreach(($p[$metric]??array_fill(0,12,null)) as $m=>$v)if($v!==null&&($offset%2===0||!isset($p['price'])))$s->setCellValue([5+$m*4+$offset,$r],(float)$v);}
    }
    return $book;
}
