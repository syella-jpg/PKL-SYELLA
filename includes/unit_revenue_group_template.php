<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
/** Group tables retain the same columns and units as the original compact template. */
function unitRevenueGroupBlocks(int $capacity=7,array $slots=[]): array {
    $groups=['1','2','3','4','5','6','ET'];$base=max(1,intdiv(max(7,$capacity),7));$extra=max(7,$capacity)%7;$title=5;$blocks=[];
    foreach($groups as $i=>$gb){$count=max($base+($i<$extra?1:0),(int)($slots[$gb]??0));$summary=$title+2+$count;$blocks[$gb]=['title'=>$title,'header'=>$title+1,'first'=>$title+2,'last'=>$summary-1,'total'=>$summary+($gb==='ET'?0:2)];if($gb!=='ET'){$blocks[$gb]['existing']=$summary;$blocks[$gb]['new']=$summary+1;}$title=$blocks[$gb]['total']+3;}return $blocks;
}
function buildUnitRevenueInputTemplate(string $period,?int $capacity=null,array $slots=[]): Spreadsheet {
    [$year,$month]=unitRevenueTemplatePeriod($period);$capacity??=$month===1?350:7;$months=array_slice(unitRevenueInputHeaders(),5,12);$blocks=unitRevenueGroupBlocks($capacity,$slots);$book=new Spreadsheet();$book->removeSheetByIndex(0);$book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
    foreach(['UNIT','REVENUE'] as $name){$s=new Worksheet($book,$name);$book->addSheet($s);$s->setShowGridlines(false);
        foreach([1=>'SELLING OUT — '.$name.' TAHUN '.$year,2=>'PERIODE LAPORAN: '.$months[$month-1].' '.$year,3=>$name==='UNIT'?'Isi identitas produk dan UNIT bulanan pada tabel GB yang sesuai. A/B/C/ABC = Existing; D/NP = New Product.':'Identitas produk mengikuti sheet UNIT. Isi REVENUE bulanan dalam Rupiah penuh, bukan ribuan.',4=>'Baris produk boleh ditambah/dikurangi sebelum subtotal. KAT E/E = Existing Ethical; NP = New Product Ethical.'] as $r=>$text){$s->setCellValue('A'.$r,$text);$s->mergeCells('A'.$r.':R'.$r);}$s->getStyle('A1')->getFont()->setBold(true)->setSize(18);$s->getStyle('A2:R4')->getFont()->getColor()->setARGB('FF64748B');$s->getStyle('A3:R4')->getAlignment()->setWrapText(true);$s->getRowDimension(1)->setRowHeight(36);$s->getRowDimension(3)->setRowHeight(32);$s->getRowDimension(4)->setRowHeight(32);
        foreach($blocks as $gb=>$b){$label=$gb==='ET'?'ETHICAL':'GB '.$gb;$s->setCellValue('A'.$b['title'],$label);$s->mergeCells('A'.$b['title'].':R'.$b['title']);$s->getStyle('A'.$b['title'].':R'.$b['title'])->getFill()->setFillType('solid')->getStartColor()->setARGB('FF345C50');$s->getStyle('A'.$b['title'])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$s->getRowDimension($b['title'])->setRowHeight(28);
            foreach(unitRevenueInputHeaders() as $c=>$v)$s->setCellValue([$c+1,$b['header']],$v);$s->getStyle('A'.$b['header'].':R'.$b['header'])->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');$s->getStyle('A'.$b['header'].':R'.$b['header'])->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$s->getRowDimension($b['header'])->setRowHeight(36);
            for($r=$b['first'];$r<=$b['last'];$r++){if($name==='UNIT'){$s->setCellValueExplicit('A'.$r,(string)$gb,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);if($gb==='ET')$s->setCellValue('E'.$r,'KAT E');}else foreach(range('A','E') as $col)$s->setCellValue($col.$r,"='UNIT'!".$col.$r);$s->setCellValue('R'.$r,'=SUM(F'.$r.':Q'.$r.')');}
            $s->getStyle('A'.$b['first'].':Q'.$b['last'])->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF8DD');if($name==='REVENUE')$s->getStyle('A'.$b['first'].':E'.$b['last'])->getFill()->getStartColor()->setARGB('FFEAF2FF');$s->getStyle('A'.$b['first'].':E'.$b['last'])->getNumberFormat()->setFormatCode($name==='UNIT'?'@':'0;-0;;@');
            $summaryRows=$gb==='ET'?['total'=>'TOTAL ETHICAL']:['existing'=>'TOTAL EXISTING','new'=>'TOTAL NEW PRODUCT','total'=>'TOTAL'];
            foreach($summaryRows as $kind=>$summaryLabel){$row=$b[$kind];$s->setCellValue('A'.$row,$summaryLabel);$s->mergeCells('A'.$row.':E'.$row);
                foreach(range('F','R') as $col){
                    if($gb==='ET')$formula='=SUM('.$col.$b['first'].':'.$col.$b['last'].')';
                    elseif($kind==='total')$formula='='.$col.$b['existing'].'+'.$col.$b['new'];
                    else{$terms=[];foreach($kind==='existing'?['A','B','C','ABC']:['D','NP'] as $category)$terms[]='SUMIF($E$'.$b['first'].':$E$'.$b['last'].',"'.$category.'",'.$col.'$'.$b['first'].':'.$col.'$'.$b['last'].')';$formula='='.implode('+',$terms);}
                    $s->setCellValue($col.$row,$formula);
                }
                $s->getStyle('A'.$row.':R'.$row)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF2FF');$s->getStyle('A'.$row.':R'.$row)->getFont()->setBold(true);
            }
            $s->getStyle('R'.$b['first'].':R'.$b['last'])->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF2FF');$s->getStyle('F'.$b['first'].':R'.$b['total'])->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');$s->getStyle('A'.$b['header'].':R'.$b['total'])->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFE1E5EB');
            if($name==='UNIT'){$category=(new DataValidation())->setType('list')->setFormula1('"A,B,C,ABC,D,E,KAT E,NP"')->setAllowBlank(true)->setShowDropDown(true);$s->setDataValidation('E'.$b['first'].':E'.$b['last'],$category);}
            $number=(new DataValidation())->setType('decimal')->setOperator('between')->setFormula1('-999999999999999')->setFormula2('999999999999999')->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle('stop')->setError('Isi angka atau biarkan kosong.');$s->setDataValidation('F'.$b['first'].':Q'.$b['last'],$number);
        }
        foreach(['A'=>12,'B'=>20,'C'=>24,'D'=>40,'E'=>14] as $col=>$width)$s->getColumnDimension($col)->setWidth($width);foreach(range('F','R') as $col)$s->getColumnDimension($col)->setWidth(16);$s->freezePane('F7');$s->getDefaultRowDimension()->setRowHeight(24);$s->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:R'.$blocks['ET']['total']);
    }
    $s=$book->getSheetByName('REVENUE');$title=$blocks['ET']['total']+3;$s->setCellValue('A'.$title,'ETHICAL — REVENUE MANUAL');$s->mergeCells('A'.$title.':R'.$title);$s->getStyle('A'.$title)->getFont()->setBold(true)->setSize(13);$header=$title+2;$s->setCellValue('A'.$header,'PARAMETER');foreach($months as $i=>$v)$s->setCellValue([$i+6,$header],$v);$s->setCellValue('R'.$header,'TOTAL');$s->getStyle('A'.$header.':R'.$header)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');$s->getStyle('A'.$header.':R'.$header)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    foreach(['existing'=>'EXIST','new'=>'NP','total'=>'TOTAL'] as $i=>$label){$r=$title+3+array_search($i,['existing','new','total'],true);$s->setCellValue('A'.$r,$label);$s->mergeCells('A'.$r.':E'.$r);foreach(range('F','Q') as $col){if($i==='total')$formula='='.$col.($r-2).'+'.$col.($r-1);else{$terms=[];foreach($i==='existing'?['E','KAT E','A','B','C','ABC']:['NP','D'] as $cat)$terms[]='SUMIF($E$'.$blocks['ET']['first'].':$E$'.$blocks['ET']['last'].',"'.$cat.'",'.$col.'$'.$blocks['ET']['first'].':'.$col.'$'.$blocks['ET']['last'].')';$formula='='.implode('+',$terms);}$s->setCellValue($col.$r,$formula);}$s->setCellValue('R'.$r,'=SUM(F'.$r.':Q'.$r.')');}$s->getStyle('F'.($title+3).':Q'.($title+5))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF8DD');$s->getStyle('F'.($title+3).':R'.($title+5))->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');$s->getPageSetup()->setPrintArea('A1:R'.($title+5));
    $meta=new Worksheet($book,'_TEMPLATE');$book->addSheet($meta);$meta->fromArray([['KONIMEX_UNIT_REVENUE_V2'],['UNIT & Revenue'],[$year],[$period],['ETHICAL_REVENUE_MANUAL_V1']]);$meta->setSheetState('hidden');$book->setActiveSheetIndex(0);return $book;
}
function unitRevenueGroupNorm($value): string {return strtoupper(trim(preg_replace('/\s+/u',' ',str_replace("\xc2\xa0",' ',(string)$value))));}
function locateUnitRevenueGroupTables(Worksheet $s): array {
    $titles=[];$totals=[];$headers=[];$text=[];$summaries=[];
    foreach($s->getCellCollection()->getCoordinates() as $address){
        $cell=$s->getCell($address);$value=$cell->getValue();
        if(!is_string($value)||str_starts_with($value,'='))continue;
        $label=unitRevenueGroupNorm($value);[$col,$row]=Coordinate::coordinateFromString($address);
        $column=Coordinate::columnIndexFromString($col);$text[$row][$column]=$label;
        if($label==='PRODUCT NAME')$headers[$row]=true;
        if(!$cell->isInMergeRange())continue;
        if(in_array($label,['TOTAL EXISTING','TOTAL NEW PRODUCT','TOTAL'],true))$summaries[$row]=$label;
        if(preg_match('/^(TOTAL\s+)?(?:GB\s*([1-6])|(ETHICAL))$/D',$label,$m)){
            $gb=!empty($m[2])?$m[2]:'ET';
            if(!empty($m[1])){
                if(isset($totals[$gb]))throw new InvalidArgumentException('Subtotal kelompok berulang di '.$s->getTitle().'.');
                $totals[$gb]=$row;
            }else{
                if(isset($titles[$gb]))throw new InvalidArgumentException('Judul kelompok berulang di '.$s->getTitle().'.');
                $titles[$gb]=$row;
            }
        }
    }
    $out=[];
    foreach(['1','2','3','4','5','6','ET'] as $gb){
        $title=$titles[$gb]??null;
        if($title===null)throw new InvalidArgumentException('Judul '.($gb==='ET'?'ETHICAL':'GB '.$gb).' tidak ditemukan pada '.$s->getTitle().'.');
        $end=$s->getHighestRow()+1;foreach($titles as $row)if($row>$title)$end=min($end,$row);
        $found=[];foreach($summaries as $row=>$label)if($row>$title&&$row<$end){
            if(isset($found[$label]))throw new InvalidArgumentException('Subtotal kelompok berulang di '.$s->getTitle().'.');
            $found[$label]=$row;
        }
        // Older grouped templates have only TOTAL GB n; both layouts remain readable.
        $total=$totals[$gb]??($found['TOTAL']??null);
        if($total===null||$total<=$title||$total>=$end)throw new InvalidArgumentException('Subtotal kelompok '.$gb.' tidak lengkap pada '.$s->getTitle().'.');
        $matches=array_values(array_filter(array_keys($headers),static fn($r)=>$r>$title&&$r<$total));
        if(count($matches)!==1)throw new InvalidArgumentException('Header produk kelompok '.$gb.' tidak lengkap atau berulang.');
        $header=$matches[0];$columns=[];
        foreach(unitRevenueInputHeaders() as $i=>$label){
            if($label==='TOTAL')continue;$positions=array_keys($text[$header]??[],$label,true);
            if(count($positions)!==1)throw new InvalidArgumentException('Header '.$label.' pada '.$s->getTitle().' kelompok '.$gb.' tidak lengkap atau berulang.');
            $columns[$i]=$positions[0];
        }
        $last=$total-1;$summaryRows=[];
        if(isset($found['TOTAL EXISTING'])||isset($found['TOTAL NEW PRODUCT'])){
            $existing=$found['TOTAL EXISTING']??null;$new=$found['TOTAL NEW PRODUCT']??null;
            if($existing===null||$new===null||$existing<=$header||$new<=$existing||$total<=$new)throw new InvalidArgumentException('Urutan subtotal Existing, New Product, dan Total pada kelompok '.$gb.' tidak lengkap.');
            $last=$existing-1;$summaryRows=['existing'=>$existing,'new'=>$new];
        }
        $out[$gb]=['title'=>$title,'header'=>$header,'first'=>$header+1,'last'=>$last,'total'=>$total,'columns'=>$columns]+$summaryRows;
    }
    return $out;
}
function locateUnitRevenueManualTable(Worksheet $s): array {
    $title=null;$rows=[];$header=null;$months=[];$labels=['EXIST'=>'existing','NP'=>'new','TOTAL'=>'total'];foreach($s->getCellCollection()->getCoordinates() as $address){$v=$s->getCell($address)->getValue();if(!is_string($v))continue;[$col,$r]=Coordinate::coordinateFromString($address);if(unitRevenueGroupNorm($v)==='ETHICAL — REVENUE MANUAL'){$title=$r;break;}}
    if($title===null)throw new InvalidArgumentException('Bagian Ethical — Revenue Manual tidak ditemukan.');
    foreach($s->getRowIterator($title+1) as $row){$r=$row->getRowIndex();$values=[];$cells=$row->getCellIterator();$cells->setIterateOnlyExistingCells(true);foreach($cells as $cell)$values[Coordinate::columnIndexFromString($cell->getColumn())]=unitRevenueGroupNorm($cell->getValue());
        if(in_array('PARAMETER',$values,true)){$header=$r;foreach(array_slice(unitRevenueInputHeaders(),5,12) as $m=>$label){$found=array_keys($values,$label,true);if(count($found)!==1)throw new InvalidArgumentException('Header bulan Ethical manual tidak lengkap.');$months[$m]=$found[0];}}
        if($header!==null&&$r>$header)foreach($values as $label)if(isset($labels[$label])){if(isset($rows[$labels[$label]]))throw new InvalidArgumentException('Baris Ethical manual berulang.');$rows[$labels[$label]]=$r;}
    }
    if(count($rows)!==3||$header===null)throw new InvalidArgumentException('Baris EXIST, NP, dan TOTAL Ethical manual belum lengkap.');return ['rows'=>$rows,'months'=>$months];
}
function unitRevenueGroupCellText(Spreadsheet $book,Worksheet $s,int $column,int $row): string {
    $cell=$s->getCell([$column,$row]);$value=$cell->getCalculatedValue();if($cell->isFormula()&&($value===0||$value===0.0)){
        if(preg_match("/^='([^']+)'!([A-Z]+[1-9][0-9]*)$/D",(string)$cell->getValue(),$match)){$source=$book->getSheetByName($match[1])?->getCell($match[2])->getValue();if($source===null||$source==='')return '';}
    }
    if($cell->isFormula()&&$value==='#REF!')return '';return trim((string)$value);
}
function unitRevenueGroupIdentityKey(array $identity): string {
    $identity[0]=normalizeUnitRevenueGroup($identity[0]);$identity[4]=preg_replace('/^KAT(?:EGORI)?\.?\s+/','',strtoupper($identity[4]));if($identity[4]==='NP')$identity[4]='D';return json_encode(array_map(static fn($v)=>mb_strtoupper((string)$v),$identity));
}
function parseUnitRevenueGroupTemplate(Spreadsheet $book,string $period): array {
    [$year]=unitRevenueTemplatePeriod($period);if((int)$book->getSheetByName('_TEMPLATE')->getCell('A3')->getValue()!==$year)throw new InvalidArgumentException('Tahun template UNIT & Revenue tidak sesuai periode upload.');$products=[];$hasInput=false;
    foreach(['UNIT'=>'qty','REVENUE'=>'rev'] as $name=>$field){$s=$book->getSheetByName($name);if(!$s)throw new InvalidArgumentException('Sheet '.$name.' tidak ditemukan.');$seen=[];
        foreach(locateUnitRevenueGroupTables($s) as $gb=>$table)for($r=$table['first'];$r<=$table['last'];$r++){$identity=[];foreach(range(0,4) as $i)$identity[]=unitRevenueGroupCellText($book,$s,$table['columns'][$i],$r);$values=[];$has=false;foreach(range(0,11) as $m){$cell=$s->getCell([$table['columns'][$m+5],$r]);$v=$cell->getCalculatedValue();if(is_string($v))$v=trim($v);if($v===null||$v===''||in_array($v,['-','–','—'],true))$v=null;elseif(!is_numeric($v)||!is_finite((float)$v))throw new InvalidArgumentException('Nilai tidak valid pada '.$name.' sel '.$cell->getCoordinate().'.');else{$v=(float)$v;$has=true;}$values[]=$v;}
            if($identity[1]===''&&$identity[2]===''&&$identity[3]===''&&!$has)continue;if($identity[0]==='')$identity[0]=(string)$gb;if(normalizeUnitRevenueGroup($identity[0])!==(string)$gb)throw new InvalidArgumentException('GB produk tidak sesuai kelompok '.$gb.' pada '.$name.' baris '.$r.'.');$key=unitRevenueGroupIdentityKey($identity);if(isset($seen[$key]))throw new InvalidArgumentException('Produk ganda pada '.$name.' baris '.$r.'.');$seen[$key]=true;
            if($field==='qty')$products[$key]=['identity'=>$identity,'qty'=>$values,'rev'=>array_fill(0,12,null)];else{if(!isset($products[$key]))throw new InvalidArgumentException('Identitas REVENUE berbeda dari UNIT pada baris '.$r.'.');$products[$key]['rev']=$values;}$hasInput=$hasInput||$has;
        }
    }
    $manual=locateUnitRevenueManualTable($book->getSheetByName('REVENUE'));$manualValues=[];$formulaFlags=[];foreach($manual['rows'] as $kind=>$r)foreach($manual['months'] as $m=>$column){$cell=$book->getSheetByName('REVENUE')->getCell([$column,$r]);$v=$cell->getCalculatedValue();if($v===null||$v==='')$v=null;elseif(!is_numeric($v)||!is_finite((float)$v))throw new InvalidArgumentException('Nilai Ethical manual tidak valid pada '.$cell->getCoordinate().'.');else{$v=(float)$v;if(!$cell->isFormula()||$v!=0)$hasInput=true;}$manualValues[$kind][$m]=$v;$formulaFlags[$kind][$m]=$cell->isFormula();}
    if(!$hasInput)throw new InvalidArgumentException('Template UNIT & Revenue masih kosong. Isi minimal satu nilai unit atau rupiah sebelum upload.');
    $normalized=buildUnitRevenueLegacyInputTemplate($period,max(1,count($products)));$i=6;foreach($products as $product){foreach($product['identity'] as $c=>$value)$normalized->getSheetByName('UNIT')->setCellValueExplicit([$c+1,$i],$value,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);foreach(['qty'=>'UNIT','rev'=>'REVENUE'] as $field=>$name)foreach($product[$field] as $m=>$v)if($v!==null)$normalized->getSheetByName($name)->setCellValue([$m+6,$i],$v);$i++;}
    $dest=unitRevenueEthicalManualRows($normalized);foreach($manualValues as $kind=>$values)foreach($values as $m=>$v)$normalized->getSheetByName('REVENUE')->setCellValue([$m+6,$dest[$kind]],$v);
    try{$records=parseUnitRevenueInputTemplate($normalized,$period);foreach($records as &$record)if(($record['__kind']??null)==='ethical_revenue')$record['formula_months']=$formulaFlags[$record['section']];unset($record);return $records;}finally{$normalized->disconnectWorksheets();}
}
