<?php
/** Product-detail version: GB 1–6 and Ethical products, plus the two cumulative IB KPIs. */
function unitSellOutProductHeaders(): array {
    return ['GB','KODE PRODUK','NAMA PRODUK','KATEGORI','SATUAN',...unitSellOutInputMonths()];
}
function buildUnitSellOutLegacyProductTemplate(string $period,int $gbCapacity=1000,int $ethicalCapacity=200,bool $ethicalSummary=true,bool $splitGb=true): \PhpOffice\PhpSpreadsheet\Spreadsheet {
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$match))throw new InvalidArgumentException('Periode TARGET UNIT SELL OUT tidak valid.');
    $year=(int)$match[1];$month=(int)$match[2];$months=unitSellOutInputMonths();
    $gbCapacity=max(1,min(5000,$gbCapacity));$ethicalCapacity=max(1,min(5000,$ethicalCapacity));
    $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$book->removeSheetByIndex(0);
    $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11)->getColor()->setARGB('FF263449');
    $numeric=(new \PhpOffice\PhpSpreadsheet\Cell\DataValidation())->setType('decimal')->setOperator('greaterThanOrEqual')->setFormula1('0')->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle('stop')->setErrorTitle('Isi angka unit')->setError('Gunakan angka nol atau positif.');
    $splitGb=$splitGb&&$ethicalSummary;
    $sheets=$splitGb?array_fill_keys(['GB 1','GB 2','GB 3','GB 4','GB 5','GB 6'],$gbCapacity):($ethicalSummary?['GB 1-6'=>$gbCapacity]:['GB 1-6'=>$gbCapacity,'ETHICAL'=>$ethicalCapacity]);
    foreach($sheets as $name=>$capacity){
        $fixedGb=$splitGb?(int)substr($name,3):null;
        $sheet=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,$name);$book->addSheet($sheet);$ethical=$name==='ETHICAL';$end=5+$capacity;
        $sheet->setShowGridlines(false);$sheet->setCellValue('A1','TARGET UNIT SELL OUT — '.$name.' — TAHUN '.$year);$sheet->mergeCells('A1:S1');
        $sheet->setCellValue('A2','PERIODE LAPORAN: '.$months[$month-1].' '.$year);$sheet->mergeCells('A2:S2');
        $sheet->setCellValue('A3',$ethical?'Isi satu produk per baris. EXIST = Existing; NP = New Product. Target dalam UNIT per bulan.':($splitGb?'Isi produk '.$name.' saja. Kategori A/B/C = EXIST; D = NP. Target dalam UNIT per bulan.':'Isi satu produk per baris. Pilih GB 1–6 dan kategori A/B/C/D. EXIST = A+B+C; NP = D. Target dalam UNIT per bulan.'));$sheet->mergeCells('A3:S3');
        $sheet->setCellValue('A4','Sel kuning diisi. Kode produk dan satuan boleh kosong. Ringkasan otomatis tersedia di bawah rincian produk.');$sheet->mergeCells('A4:S4');
        foreach([...unitSellOutProductHeaders(),'YTD '.$months[$month-1],'TOTAL FY'] as $c=>$header)$sheet->setCellValue([$c+1,5],$header);
        foreach(['A'=>10,'B'=>20,'C'=>42,'D'=>16,'E'=>14] as $c=>$width)$sheet->getColumnDimension($c)->setWidth($width);
        foreach(range('F','S') as $c)$sheet->getColumnDimension($c)->setWidth(16);
        $sheet->getStyle('A6:Q'.$end)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF3C4');
        $sheet->getStyle('R6:S'.$end)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');
        $sheet->getStyle('B6:C'.$end)->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('F6:S'.$end)->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');
        $sheet->setDataValidation('F6:Q'.$end,clone $numeric);
        $categories=$ethical?'"EXIST,NP,KAT E,E,D"':'"A,B,C,D"';
        $categoryRule=(new \PhpOffice\PhpSpreadsheet\Cell\DataValidation())->setType('list')->setFormula1($categories)->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)->setErrorStyle('stop');
        $sheet->setDataValidation('D6:D'.$end,$categoryRule);
        if(!$ethical)$sheet->setDataValidation('A6:A'.$end,(new \PhpOffice\PhpSpreadsheet\Cell\DataValidation())->setType('list')->setFormula1($fixedGb!==null?'"'.$fixedGb.'"':'"1,2,3,4,5,6"')->setAllowBlank(true)->setShowDropDown(true)->setShowErrorMessage(true)->setErrorStyle('stop'));
        for($r=6;$r<=$end;$r++){
            if($fixedGb!==null)$sheet->setCellValue('A'.$r,$fixedGb);
            if($ethical){$sheet->setCellValue('A'.$r,'ET');$sheet->setCellValue('D'.$r,'EXIST');}
            $sheet->setCellValue('R'.$r,'=SUM(F'.$r.':'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5+$month).$r.')');
            $sheet->setCellValue('S'.$r,'=SUM(F'.$r.':Q'.$r.')');
        }
        $title=$end+3;$header=$title+1;
        $sheet->setCellValue('A'.$title,'RINGKASAN TARGET UNIT — OTOMATIS');$sheet->mergeCells('A'.$title.':S'.$title);
        foreach(['PARAMETER',...array_fill(0,4,''),...$months,'YTD '.$months[$month-1],'TOTAL FY'] as $c=>$v)if($v!=='')$sheet->setCellValue([$c+1,$header],$v);
        $sheet->mergeCells('A'.$header.':E'.$header);
        $summary=[];$row=$header+1;
        foreach($ethical?['ET']:($fixedGb!==null?[$fixedGb]:range(1,6)) as $gb){
            foreach(['EXIST','NP','TOTAL'] as $kind){
                $label=$kind.' '.($ethical?'ETHICAL':'GB '.$gb);$sheet->setCellValue('A'.$row,$label);$sheet->mergeCells('A'.$row.':E'.$row);$summary[$label]=$row;
                foreach(range('F','Q') as $c){
                    if($kind==='TOTAL')$formula='='.$c.($row-2).'+'.$c.($row-1);
                    else{
                        $cats=$ethical?($kind==='EXIST'?['EXIST','KAT E','E']:['NP','D']):($kind==='EXIST'?['A','B','C']:['D']);$terms=[];
                        foreach($cats as $cat)$terms[]='SUMIFS('.$c.'$6:'.$c.'$'.$end.','.($ethical?'':'$A$6:$A$'.$end.','.$gb.',').'$D$6:$D$'.$end.',"'.$cat.'")';
                        $formula='='.implode('+',$terms);
                    }
                    $sheet->setCellValue($c.$row,$formula);
                }
                $sheet->setCellValue('R'.$row,'=SUM(F'.$row.':'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5+$month).$row.')');$sheet->setCellValue('S'.$row,'=SUM(F'.$row.':Q'.$row.')');$row++;
            }
            $row++;
        }
        foreach([5,$header] as $r){$sheet->getStyle('A'.$r.':S'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');$sheet->getStyle('A'.$r.':S'.$r)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$sheet->getRowDimension($r)->setRowHeight(30);}
        $sheet->getStyle('A'.$title.':S'.$title)->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A'.($header+1).':S'.($row-1))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');
        $sheet->getStyle('F'.($header+1).':S'.($row-1))->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(19);$sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->freezePane('F6');$sheet->setAutoFilter('A5:S'.$end);$sheet->setSelectedCell($ethical||$splitGb?'C6':'A6');$sheet->getSheetView()->setZoomScale(85);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:S'.($row-1));
    }
    if($ethicalSummary)buildUnitSellOutEthicalSummarySheet($book,$period);
    // Reuse the already validated two-KPI cumulative IB layout from the summary template.
    $reference=buildUnitSellOutSummaryTemplate($period);$ib=$reference->getSheetByName('TARGET UNIT SELL OUT');
    $sheet=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,'IB');$book->addSheet($sheet);
    foreach(['A'=>32,'B'=>53] as $c=>$width)$sheet->getColumnDimension($c)->setWidth($width);foreach(range('C','P') as $c)$sheet->getColumnDimension($c)->setWidth(17);
    $sheet->setCellValue('A1','TARGET UNIT SELL OUT — IB — TAHUN '.$year);$sheet->mergeCells('A1:P1');$sheet->setCellValue('A2','PERIODE LAPORAN: '.$months[$month-1].' '.$year);$sheet->mergeCells('A2:P2');
    $sheet->setCellValue('A3','HRU → Financial → Revenue Growth → Value Selling In → TARGET. Isi dua target unit secara kumulatif.');$sheet->mergeCells('A3:P3');
    for($r=67;$r<=71;$r++)for($c=1;$c<=16;$c++){
        $from=$ib->getCell([$c,$r]);$target=$sheet->getCell([$c,$r-62]);$value=$from->getValue();
        if($from->isFormula())$value=preg_replace_callback('/(\$?[A-Z]+\$?)(6[7-9]|7[01])\b/',fn($m)=>$m[1].((int)$m[2]-62),$value);
        $target->setValue($value);
    }
    $sheet->getStyle('A5:P5')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');
    $sheet->getStyle('A5:P5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('C6:N7')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF3C4');
    $sheet->getStyle('C8:P9')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');
    $sheet->getStyle('O6:P7')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');
    $sheet->getStyle('C6:P9')->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');
    $reference->disconnectWorksheets();
    $sheet->setDataValidation('C6:N7',clone $numeric);$sheet->setShowGridlines(false);$sheet->freezePane('C6');$sheet->setSelectedCell('C6');$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(19);$sheet->getRowDimension(1)->setRowHeight(36);$sheet->getRowDimension(5)->setRowHeight(30);
    $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(1)->setPrintArea('A1:P9');
    $meta=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,'_TEMPLATE');$book->addSheet($meta);$meta->fromArray([[$splitGb?'KONIMEX_UNIT_SELL_OUT_V4':($ethicalSummary?'KONIMEX_UNIT_SELL_OUT_V3':'KONIMEX_UNIT_SELL_OUT_V2')],['TARGET UNIT SELL OUT'],[$year],[$period],[$gbCapacity],[$ethicalCapacity]]);$meta->setSheetState('hidden');$book->setActiveSheetIndex(0);
    return $book;
}
function unitSellOutProductCapacity(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $sheet): int {
    $value=$book->getSheetByName('_TEMPLATE')?->getCell($sheet==='ETHICAL'?'A6':'A5')->getValue();
    if(!is_numeric($value)||(int)$value<1||(int)$value>5000||(float)$value!==(float)(int)$value)throw new InvalidArgumentException('Kapasitas template berubah. Unduh kembali template.');
    return (int)$value;
}
function parseUnitSellOutProductTemplate(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,int $year,bool $extraInput=false,?int $normalizedCapacity=null): array {
    if(in_array($book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue(),['KONIMEX_UNIT_SELL_OUT_V5','KONIMEX_UNIT_SELL_OUT_V6'],true))return parseUnitSellOutCategoryTemplate($book,$year);
    if((int)$book->getSheetByName('_TEMPLATE')?->getCell('A3')->getValue()!==$year)throw new InvalidArgumentException('Tahun template berbeda dari periode upload.');
    $inputs=[];$monthly=[];$seen=[];$hasNumber=false;
    $splitGb=$book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue()==='KONIMEX_UNIT_SELL_OUT_V4';
    $ethicalSummary=$splitGb||$book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue()==='KONIMEX_UNIT_SELL_OUT_V3';
    foreach(['gb1','gb2','gb3','gb4','gb5','gb6','ethical'] as $group){$inputs[$group]=['__format'=>'products_v2','rows'=>[]];foreach(['existing','new'] as $kind)$monthly[$group][$kind]=array_fill(0,12,null);}
    foreach($splitGb?['GB 1','GB 2','GB 3','GB 4','GB 5','GB 6']:($ethicalSummary?['GB 1-6']:['GB 1-6','ETHICAL']) as $name){
        $sheet=$book->getSheetByName($name);if(!$sheet)throw new InvalidArgumentException('Sheet '.$name.' tidak ditemukan.');$ethical=$name==='ETHICAL';$capacity=$normalizedCapacity??unitSellOutProductCapacity($book,$name);
        foreach(unitSellOutProductHeaders() as $c=>$v)if($sheet->getCell([$c+1,5])->getValue()!==$v)throw new InvalidArgumentException('Kolom '.$name.' berubah. Gunakan template yang diunduh.');
        for($r=6;$r<=5+$capacity;$r++){
            $identity=[];for($c=1;$c<=5;$c++)$identity[]=trim((string)$sheet->getCell([$c,$r])->getCalculatedValue());
            $values=[];$hasValue=false;
            for($m=0;$m<12;$m++){
                $cell=$sheet->getCell([$m+6,$r]);try{$v=$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus tidak valid di '.$name.' '.$cell->getCoordinate().'.');}
                if($v===null||$v===''||in_array(trim((string)$v),['-','–','—'],true))$v=null;
                elseif(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||abs((float)$v)>=1e17)throw new InvalidArgumentException('Isi angka unit nol atau positif di '.$name.' '.$cell->getCoordinate().'.');
                else{$v=(float)$v;$hasValue=true;}
                $values[]=$v;
            }
            [$gb,$code,$product,$category,$unit]=$identity;
            if($splitGb){$expected=substr($name,3);if($gb!==''&&$gb!==$expected)throw new InvalidArgumentException('GB tidak sesuai dengan sheet '.$name.' pada baris '.$r.'.');$gb=$expected;}
            if($code===''&&$product===''&&$unit===''&&!$hasValue)continue;
            $category=strtoupper($category);
            if($product==='')throw new InvalidArgumentException('Isi nama produk pada '.$name.' baris '.$r.'.');
            if(strlen($code)>100||mb_strlen($product)>255||mb_strlen($unit)>80)throw new InvalidArgumentException('Identitas produk terlalu panjang pada '.$name.' baris '.$r.'.');
            if($ethical){
                if(!in_array($category,['EXIST','KAT E','E','NP','D'],true))throw new InvalidArgumentException('Pilih kategori EXIST atau NP pada ETHICAL baris '.$r.'.');
                $group='ethical';$kind=in_array($category,['NP','D'],true)?'new':'existing';
            }else{
                if(!preg_match('/^[1-6]$/D',$gb)||!in_array($category,['A','B','C','D'],true))throw new InvalidArgumentException('Pilih GB 1–6 dan kategori A/B/C/D pada GB 1-6 baris '.$r.'.');
                $group='gb'.$gb;$kind=$category==='D'?'new':'existing';
            }
            $key=$group.'|'.mb_strtoupper($code!==''?'CODE:'.$code:'NAME:'.$product.'|'.$category.'|'.$unit);
            if(isset($seen[$key]))throw new InvalidArgumentException('Produk ganda pada '.$name.' baris '.$r.'. Gabungkan menjadi satu baris.');$seen[$key]=true;
            $inputs[$group]['rows'][]=['code'=>$code,'name'=>$product,'category'=>$category,'unit'=>$unit,'months'=>$values];
            foreach($values as $m=>$v)if($v!==null){$monthly[$group][$kind][$m]=($monthly[$group][$kind][$m]??0)+$v;if(abs($monthly[$group][$kind][$m])>=1e18)throw new InvalidArgumentException('Jumlah target unit terlalu besar pada '.$name.'.');}
            $hasNumber=$hasNumber||$hasValue;
        }
    }
    if($ethicalSummary){
        $sheet=$book->getSheetByName('ETHICAL');if(!$sheet)throw new InvalidArgumentException('Sheet ETHICAL tidak ditemukan.');
        foreach(unitSellOutInputMonths() as $m=>$label)if($sheet->getCell([$m+3,5])->getValue()!==$label)throw new InvalidArgumentException('Kolom bulan ETHICAL berubah.');
        $inputs['ethical']=[];
        foreach(['existing'=>[6,'EXISTING PRODUCT (UNIT)'],'new'=>[7,'NEW PRODUCT (UNIT)']] as $kind=>[$r,$label]){
            if($sheet->getCell('A'.$r)->getValue()!==$label||$sheet->getCell('B'.$r)->getValue()!=='TARGET')throw new InvalidArgumentException('Gunakan baris TARGET Existing/New Product (Unit) pada ETHICAL.');
            for($m=0;$m<12;$m++){
                $cell=$sheet->getCell([$m+3,$r]);try{$v=$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus ETHICAL tidak dapat dihitung pada '.$cell->getCoordinate().'.');}
                if($v===null||$v===''||in_array(trim((string)$v),['-','–','—'],true))$v=null;
                elseif(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||abs((float)$v)>=1e17)throw new InvalidArgumentException('Isi angka unit nol atau positif pada ETHICAL '.$cell->getCoordinate().'.');
                else{$v=(float)$v;$hasNumber=true;}
                $inputs['ethical'][$kind][$m]=$v;$monthly['ethical'][$kind][$m]=$v;
            }
        }
    }
    // Missing GB categories are zero, while blanks on an existing product remain unknown.
    foreach($inputs as $group=>$parts){
        if(!preg_match('/^gb[1-6]$/D',$group)||!$parts['rows'])continue;
        $categories=array_column($parts['rows'],'category');
        if(!array_intersect(['A','B','C'],$categories))$monthly[$group]['existing']=array_fill(0,12,0.0);
        if(!in_array('D',$categories,true))$monthly[$group]['new']=array_fill(0,12,0.0);
    }
    $ib=$book->getSheetByName('IB');if(!$ib)throw new InvalidArgumentException('Sheet IB tidak ditemukan.');
    foreach(unitSellOutInputMonths() as $m=>$label)if($ib->getCell([$m+3,5])->getValue()!==($m>0?'S/D '.$label:$label))throw new InvalidArgumentException('Kolom kumulatif IB berubah.');
    foreach(['A'=>6,'B'=>7] as $kind=>$row){
        $label=$kind==='A'?'A — SALES REVENUE':'B — NEW BUSINESS';if($ib->getCell('A'.$row)->getValue()!==$label)throw new InvalidArgumentException('Label KPI IB berubah.');
        for($m=0;$m<12;$m++){
            $cell=$ib->getCell([$m+3,$row]);try{$v=$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus IB tidak dapat dihitung pada '.$cell->getCoordinate().'.');}
            if($v===null||$v===''||in_array(trim((string)$v),['-','–','—'],true))$v=null;
            elseif(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||abs((float)$v)>=1e17)throw new InvalidArgumentException('Isi angka unit nol atau positif pada IB '.$cell->getCoordinate().'.');
            else{$v=(float)$v;$hasNumber=true;}
            $inputs['ib'][$kind][$m]=$v;
        }
    }
    if(!$hasNumber&&!$extraInput)throw new InvalidArgumentException('Template TARGET UNIT SELL OUT masih kosong. Isi minimal satu target unit.');
    $records=[];
    foreach($monthly as $group=>$parts)foreach($parts as $kind=>$values)foreach($values as $m=>$v)$records[]=['report_year'=>$year,'product'=>$group,'kind'=>$kind,'month'=>$m+1,'units'=>$v===null?null:number_format($v,6,'.','')];
    $cumulative=[];for($m=0;$m<12;$m++)$cumulative[$m]=$inputs['ib']['A'][$m]===null||$inputs['ib']['B'][$m]===null?null:$inputs['ib']['A'][$m]+$inputs['ib']['B'][$m];
    for($m=0;$m<12;$m++){$prior=$m===0?0:$cumulative[$m-1];$v=$prior===null||$cumulative[$m]===null?null:$cumulative[$m]-$prior;$records[]=['report_year'=>$year,'product'=>'ib','kind'=>'total','month'=>$m+1,'units'=>$v===null?null:number_format($v,6,'.','')];}
    return ['records'=>$records,'inputs'=>$inputs];
}

function fillUnitSellOutProductHistory(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $period,array $history): void {
    if(in_array($book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue(),['KONIMEX_UNIT_SELL_OUT_V5','KONIMEX_UNIT_SELL_OUT_V6'],true)){fillUnitSellOutCategoryHistory($book,$period,$history);return;}
    if(substr($period,5,2)==='01')return;$year=(int)substr($period,0,4);$next=array_fill_keys(['GB 1-6','GB 1','GB 2','GB 3','GB 4','GB 5','GB 6','ETHICAL'],6);$splitGb=$book->getSheetByName('_TEMPLATE')->getCell('A1')->getValue()==='KONIMEX_UNIT_SELL_OUT_V4';
    foreach($history as $record){
        if((int)$record['report_year']!==$year)continue;$product=$record['product'];$parts=json_decode($record['payload'],true,512,JSON_THROW_ON_ERROR);
        if($product==='ib'){
            $sheet=$book->getSheetByName('IB');foreach(['A'=>6,'B'=>7] as $kind=>$r){if(!isset($parts[$kind])||!is_array($parts[$kind])||count($parts[$kind])!==12)throw new RuntimeException('Riwayat target IB tidak lengkap.');foreach($parts[$kind] as $m=>$v)if($v!==null)$sheet->setCellValue([$m+3,$r],(float)$v);}continue;
        }
        if($product==='ethical'&&in_array($book->getSheetByName('_TEMPLATE')->getCell('A1')->getValue(),['KONIMEX_UNIT_SELL_OUT_V3','KONIMEX_UNIT_SELL_OUT_V4'],true)){
            $values=['existing'=>array_fill(0,12,null),'new'=>array_fill(0,12,null)];
            if(($parts['__format']??'')==='products_v2'){
                foreach($parts['rows'] as $detail){
                    $kind=in_array($detail['category'],['NP','D'],true)?'new':'existing';
                    foreach($detail['months'] as $m=>$v)if($v!==null)$values[$kind][$m]=($values[$kind][$m]??0)+(float)$v;
                }
            }else foreach(['existing','new'] as $kind){
                if(!isset($parts[$kind])||!is_array($parts[$kind])||count($parts[$kind])!==12)throw new RuntimeException('Riwayat TARGET ETHICAL tidak lengkap.');
                $values[$kind]=$parts[$kind];
            }
            $sheet=$book->getSheetByName('ETHICAL');
            foreach(['existing'=>6,'new'=>7] as $kind=>$r)foreach($values[$kind] as $m=>$v)if($v!==null)$sheet->setCellValue([$m+3,$r],(float)$v);
            continue;
        }
        if(!preg_match('/^(gb[1-6]|ethical)$/D',$product))throw new RuntimeException('Kelompok riwayat target tidak dikenal.');
        if(($parts['__format']??'')!=='products_v2'){
            $book->getSheetByName($product==='ethical'?'ETHICAL':($splitGb?'GB '.substr($product,2):'GB 1-6'))->setCellValue('A4','Riwayat lama berisi jumlah kategori. Isi rincian produk di template ini; target lama tetap tersimpan sampai upload berikutnya.');continue;
        }
        $name=$product==='ethical'?'ETHICAL':($splitGb?'GB '.substr($product,2):'GB 1-6');$sheet=$book->getSheetByName($name);$end=5+unitSellOutProductCapacity($book,$name);
        foreach($parts['rows'] as $detail){
            $r=$next[$name]++;if($r>$end)throw new RuntimeException('Rincian tersimpan melebihi kapasitas template.');
            $sheet->setCellValue('A'.$r,$product==='ethical'?'ET':substr($product,2));
            foreach(['B'=>'code','C'=>'name','D'=>'category','E'=>'unit'] as $c=>$key)$sheet->getCell($c.$r)->setValueExplicit((string)$detail[$key],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            if(!is_array($detail['months'])||count($detail['months'])!==12)throw new RuntimeException('Riwayat bulanan produk tidak lengkap.');
            foreach($detail['months'] as $m=>$v)if($v!==null)$sheet->setCellValue([$m+6,$r],(float)$v);
        }
    }
}

function buildUnitSellOutProductWithHistory(string $period,array $history): \PhpOffice\PhpSpreadsheet\Spreadsheet {
    $capacities=[];$year=(int)substr($period,0,4);
    if(substr($period,5,2)!=='01')foreach($history as $record){
        if((int)$record['report_year']!==$year||$record['product']==='ib')continue;
        $parts=json_decode($record['payload'],true,512,JSON_THROW_ON_ERROR);
        if(preg_match('/^gb[1-6]$/D',$record['product'])&&($parts['__format']??'')==='products_v2')foreach($parts['rows'] as $detail){$cat=$detail['category'];$capacities[$record['product']][$cat]=($capacities[$record['product']][$cat]??0)+1;}
    }
    $book=buildUnitSellOutProductTemplate($period,1000,200,true,true,array_map(static fn($cats)=>array_map(static fn($count)=>max(50,$count),$cats),$capacities));
    fillUnitSellOutProductHistory($book,$period,$history);
    return $book;
}

/** Only the two GMEB TARGET inputs actually consumed by the UNIT table. */
function buildUnitSellOutEthicalSummarySheet(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $period): void {
    $year=(int)substr($period,0,4);$month=(int)substr($period,5,2);$months=unitSellOutInputMonths();
    $sheet=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,'ETHICAL');$book->addSheet($sheet);$sheet->setShowGridlines(false);
    $sheet->setCellValue('A1','TARGET UNIT SELL OUT — ETHICAL — TAHUN '.$year);$sheet->mergeCells('A1:P1');
    $sheet->setCellValue('A2','PERIODE LAPORAN: '.$months[$month-1].' '.$year);$sheet->mergeCells('A2:P2');
    $sheet->setCellValue('A3','Sumber: bagian ETHICAL → PRODUK NAS → TARGET UNIT');$sheet->mergeCells('A3:P3');
    $sheet->setCellValue('A4','Bagian ini terisi otomatis setelah upload sumber Ethical di bagian ETHICAL.');$sheet->mergeCells('A4:P4');
    foreach(['PARAMETER','JENIS',...$months,'YTD '.$months[$month-1],'TOTAL FY'] as $c=>$v)$sheet->setCellValue([$c+1,5],$v);
    $sheet->setCellValue('A6','EXISTING PRODUCT (UNIT)');$sheet->setCellValue('A7','NEW PRODUCT (UNIT)');$sheet->setCellValue('A8','TOTAL ETHICAL');
    $sheet->setCellValue('B6','TARGET');$sheet->setCellValue('B7','TARGET');$sheet->setCellValue('B8','OTOMATIS');
    foreach(range('C','N') as $c)$sheet->setCellValue($c.'8','='.$c.'6+'.$c.'7');
    foreach([6,7,8] as $r){$last=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($month+2);$sheet->setCellValue('O'.$r,'=SUM(C'.$r.':'.$last.$r.')');$sheet->setCellValue('P'.$r,'=SUM(C'.$r.':N'.$r.')');$sheet->getRowDimension($r)->setRowHeight(32);}
    $sheet->getColumnDimension('A')->setWidth(34);$sheet->getColumnDimension('B')->setWidth(15);foreach(range('C','P') as $c)$sheet->getColumnDimension($c)->setWidth(17);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);$sheet->getRowDimension(1)->setRowHeight(36);
    $sheet->getStyle('A5:P5')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');$sheet->getStyle('A5:P5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$sheet->getRowDimension(5)->setRowHeight(30);
    $sheet->getStyle('C6:N7')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF3C4');
    foreach(['O6:P7','A8:P8'] as $range)$sheet->getStyle($range)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');
    $sheet->getStyle('A8:P8')->getFont()->setBold(true);$sheet->getStyle('C6:P8')->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');
    $sheet->getStyle('A5:P8')->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFDDE4EE');
    $rule=(new \PhpOffice\PhpSpreadsheet\Cell\DataValidation())->setType('decimal')->setOperator('greaterThanOrEqual')->setFormula1('0')->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle('stop')->setError('Isi target unit nol atau positif.');$sheet->setDataValidation('C6:N7',$rule);
    $sheet->freezePane('C6');$sheet->setSelectedCell('C6');$sheet->getSheetView()->setZoomScale(90);
    $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(1)->setPrintArea('A1:P8');
}

require_once __DIR__.'/unit_sell_out_category_template.php';
