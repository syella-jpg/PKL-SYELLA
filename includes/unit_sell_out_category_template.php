<?php
require_once __DIR__.'/ib_unit_template.php';
/** Each GB has four independent product blocks with simple SUM subtotals. */
function unitSellOutCategoryBlocks(array $capacities=[]): array {
    $blocks=[];$title=6;foreach(['A','B','C','D'] as $category){$count=max(1,(int)($capacities[$category]??50));$blocks[$category]=['title'=>$title,'header'=>$title+1,'first'=>$title+2,'last'=>$title+$count+1,'total'=>$title+$count+2];$title+=$count+5;}return $blocks;
}
/** Find table boundaries and month columns by labels, not template row numbers. */
function locateUnitSellOutCategoryTables(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array {
    $norm=static fn($v)=>strtoupper(trim(preg_replace('/\s+/u',' ',str_replace("\xc2\xa0",' ',(string)$v))));
    $titles=[];$totals=[];$headers=[];
    foreach($sheet->getCellCollection()->getCoordinates() as $address){$value=$sheet->getCell($address)->getValue();if(!is_string($value)||str_starts_with($value,'='))continue;$label=$norm($value);[$column,$row]=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($address);
        if(preg_match('/^(TOTAL\s+)?KATEGORI\s*:?\s*([A-D])$/D',$label,$m)){$category=$m[2];if(!empty($m[1])){if(isset($totals[$category]))throw new InvalidArgumentException('Subtotal kategori '.$category.' berulang di '.$sheet->getTitle().'.');$totals[$category]=$row;}else{if(isset($titles[$category]))throw new InvalidArgumentException('Judul kategori '.$category.' berulang di '.$sheet->getTitle().'.');$titles[$category]=$row;}}
        if($label==='NAMA PRODUK')$headers[$row]=true;
    }
    $aliases=[['JAN','JANUARI'],['FEB','FEBRUARI'],['MAR','MARET'],['APR','APRIL'],['MEI','MAY'],['JUN','JUNI'],['JUL','JULI'],['AGS','AGT','AGU','AUG','AGUSTUS'],['SEP','SEPT','SEPTEMBER'],['OKT','OCT','OKTOBER'],['NOV','NOVEMBER'],['DES','DEC','DESEMBER']];$result=[];
    foreach(['A','B','C','D'] as $category){$title=$titles[$category]??null;$total=$totals[$category]??null;if($title===null||$total===null||$total<=$title)throw new InvalidArgumentException('Judul dan TOTAL KATEGORI '.$category.' tidak lengkap di '.$sheet->getTitle().'.');foreach($titles as $other=>$r)if($other!==$category&&$r>$title&&$r<$total)throw new InvalidArgumentException('Subtotal kategori '.$category.' harus berada sebelum kategori berikutnya di '.$sheet->getTitle().'.');
        $found=[];foreach(array_keys($headers) as $header){if($header<=$title||$header>=$total)continue;$columns=[];foreach($sheet->getRowIterator($header,$header) as $row){$cells=$row->getCellIterator();$cells->setIterateOnlyExistingCells(true);foreach($cells as $cell){$label=$norm($cell->getValue());$col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cell->getColumn());if($label==='NAMA PRODUK')$columns['name']=$col;if($label==='SATUAN')$columns['unit']=$col;foreach($aliases as $month=>$names)if(in_array($label,$names,true)){if(isset($columns['months'][$month]))throw new InvalidArgumentException('Kolom bulan berulang di '.$sheet->getTitle().' kategori '.$category.'.');$columns['months'][$month]=$col;}}}
            if(isset($columns['name'])&&count($columns['months']??[])===12)$found[]=['title'=>$title,'header'=>$header,'first'=>$header+1,'last'=>$total-1,'total'=>$total,'columns'=>$columns];
        }
        if(count($found)!==1)throw new InvalidArgumentException('Header NAMA PRODUK dan Januari–Desember kategori '.$category.' tidak lengkap atau berulang di '.$sheet->getTitle().'.');$result[$category]=$found[0];
    }return $result;
}

function buildUnitSellOutProductTemplate(string $period,int $gbCapacity=1000,int $ethicalCapacity=200,bool $ethicalSummary=true,bool $splitGb=true,array $groupCapacities=[]): \PhpOffice\PhpSpreadsheet\Spreadsheet {
    if(!$ethicalSummary||!$splitGb)return buildUnitSellOutLegacyProductTemplate($period,$gbCapacity,$ethicalCapacity,$ethicalSummary,$splitGb);
    $book=buildUnitSellOutLegacyProductTemplate($period,1,2,true,false);$book->removeSheetByIndex(0);
    $months=unitSellOutInputMonths();$month=(int)substr($period,5,2);$year=(int)substr($period,0,4);$blocks=unitSellOutCategoryBlocks();
    foreach(range(1,6) as $gb){
        $blocks=unitSellOutCategoryBlocks($groupCapacities['gb'.$gb]??[]);
        $s=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,'GB '.$gb);$book->addSheet($s,$gb-1);$s->setShowGridlines(false);
        foreach([1=>'TARGET UNIT SELL OUT — GB '.$gb.' — TAHUN '.$year,2=>'PERIODE LAPORAN: '.$months[$month-1].' '.$year,3=>'Isi produk pada tabel kategorinya. A+B+C = Existing; D = New Product. Target UNIT per bulan.',4=>'Isi sel kuning. Baris produk boleh ditambah atau dikurangi di dalam tabel kategori; subtotal dan ringkasan otomatis.'] as $r=>$label){$s->setCellValue('A'.$r,$label);$s->mergeCells('A'.$r.':Q'.$r);}
        $s->getStyle('A1')->getFont()->setBold(true)->setSize(19);$s->getRowDimension(1)->setRowHeight(36);
        $s->getColumnDimension('A')->setWidth(9);$s->getColumnDimension('B')->setWidth(42);$s->getColumnDimension('C')->setWidth(14);foreach(range('D','Q') as $c)$s->getColumnDimension($c)->setWidth(17);
        $dark=function($row,$color)use($s){$s->getStyle('A'.$row.':Q'.$row)->getFill()->setFillType('solid')->getStartColor()->setARGB($color);$s->getStyle('A'.$row.':Q'.$row)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$s->getRowDimension($row)->setRowHeight(28);};
        foreach($blocks as $category=>$b){
            $s->setCellValue('A'.$b['title'],'KATEGORI '.$category);$s->mergeCells('A'.$b['title'].':Q'.$b['title']);$dark($b['title'],'FF345C50');
            foreach(['NO.','NAMA PRODUK','SATUAN',...$months,'YTD '.$months[$month-1],'TOTAL FY'] as $c=>$v)$s->setCellValue([$c+1,$b['header']],$v);$dark($b['header'],'FF29354A');
            $s->getStyle('B'.$b['first'].':O'.$b['last'])->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF3C4');
            $s->getStyle('P'.$b['first'].':Q'.$b['last'])->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');
            for($r=$b['first'];$r<=$b['last'];$r++){$s->setCellValue('A'.$r,$r-$b['first']+1);$end=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($month+3);$s->setCellValue('P'.$r,'=SUM(D'.$r.':'.$end.$r.')');$s->setCellValue('Q'.$r,'=SUM(D'.$r.':O'.$r.')');}
            $s->setCellValue('A'.$b['total'],'TOTAL KATEGORI '.$category);$s->mergeCells('A'.$b['total'].':C'.$b['total']);foreach(range('D','Q') as $c)$s->setCellValue($c.$b['total'],'=SUM('.$c.$b['first'].':'.$c.$b['last'].')');
            $s->getStyle('A'.$b['total'].':Q'.$b['total'])->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');$s->getStyle('A'.$b['total'].':Q'.$b['total'])->getFont()->setBold(true);
            $s->getStyle('D'.$b['first'].':Q'.$b['total'])->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');$s->getStyle('A'.$b['header'].':Q'.$b['total'])->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFDDE4EE');
            $rule=(new \PhpOffice\PhpSpreadsheet\Cell\DataValidation())->setType('decimal')->setOperator('greaterThanOrEqual')->setFormula1('0')->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle('stop')->setError('Isi target unit nol atau positif.');$s->setDataValidation('D'.$b['first'].':O'.$b['last'],$rule);
        }
        $summary=$blocks['D']['total']+3;$s->setCellValue('A'.$summary,'RINGKASAN GB '.$gb);$s->mergeCells('A'.$summary.':Q'.$summary);$dark($summary,'FF345C50');foreach(['PARAMETER','','',...$months,'YTD '.$months[$month-1],'TOTAL FY'] as $c=>$label)if($label!=='')$s->setCellValue([$c+1,$summary+1],$label);$s->mergeCells('A'.($summary+1).':C'.($summary+1));$dark($summary+1,'FF29354A');
        foreach(['EXIST','NP','TOTAL'] as $i=>$label){$r=$summary+2+$i;$s->setCellValue('A'.$r,$label.' GB '.$gb);$s->mergeCells('A'.$r.':C'.$r);foreach(range('D','Q') as $c){$formula=$i===0?'='.$c.$blocks['A']['total'].'+'.$c.$blocks['B']['total'].'+'.$c.$blocks['C']['total']:($i===1?'='.$c.$blocks['D']['total']:'='.$c.($r-2).'+'.$c.($r-1));$s->setCellValue($c.$r,$formula);}}
        $s->getStyle('A'.($summary+2).':Q'.($summary+4))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');$s->getStyle('D'.($summary+2).':Q'.($summary+4))->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');$s->freezePane('D8');$s->setSelectedCell('B8');$s->getSheetView()->setZoomScale(85);$s->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:Q'.($summary+4));
    }
    // The same UNIT KPI table is shared with the IB upload template.
    $oldIb=$book->getSheetByName('IB');$ibIndex=$book->getIndex($oldIb);$book->removeSheetByIndex($ibIndex);$newIb=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,'IB');$book->addSheet($newIb,$ibIndex);$newIb->setCellValue('A1','TARGET UNIT SELL OUT — IB — TAHUN '.$year);$newIb->mergeCells('A1:Q1');$newIb->setCellValue('A2','PERIODE LAPORAN: '.$months[$month-1].' '.$year);$newIb->mergeCells('A2:Q2');$newIb->setShowGridlines(false);foreach(range('F','Q') as $c)$newIb->getColumnDimension($c)->setWidth(17);appendIbUnitTable($newIb,$period,false);
    $book->getSheetByName('_TEMPLATE')->setCellValue('A1','KONIMEX_UNIT_SELL_OUT_V6');$book->getSheetByName('_TEMPLATE')->setCellValue('A5',50);$book->setActiveSheetIndex(0);return $book;
}
/** Normalize editable product rows only; ignore displayed subtotal caches. */
function parseUnitSellOutCategoryTemplate(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,int $year): array {
    if((int)$book->getSheetByName('_TEMPLATE')->getCell('A3')->getValue()!==$year)throw new InvalidArgumentException('Tahun template berbeda dari periode upload.');
    $normalized=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$normalized->getActiveSheet();$s->setTitle('GB 1-6');foreach(unitSellOutProductHeaders() as $c=>$label)$s->setCellValue([$c+1,5],$label);$next=6;
    foreach(range(1,6) as $gb){$sheet=$book->getSheetByName('GB '.$gb);if(!$sheet)throw new InvalidArgumentException('Sheet GB '.$gb.' tidak ditemukan.');
        foreach(locateUnitSellOutCategoryTables($sheet) as $cat=>$b){
            for($r=$b['first'];$r<=$b['last'];$r++){$name=trim((string)$sheet->getCell([$b['columns']['name'],$r])->getValue());$unit=isset($b['columns']['unit'])?trim((string)$sheet->getCell([$b['columns']['unit'],$r])->getValue()):'';$values=[];$hasValue=false;
                for($m=0;$m<12;$m++){$cell=$sheet->getCell([$b['columns']['months'][$m],$r]);try{$v=$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus GB '.$gb.' tidak valid pada '.$cell->getCoordinate().'.');}if($v!==null&&$v!==''&&!in_array(trim((string)$v),['-','–','—'],true))$hasValue=true;$values[$m]=$v;}
                if($name===''&&$unit===''&&!$hasValue)continue;
                $s->setCellValue('A'.$next,$gb);$s->setCellValueExplicit('C'.$next,$name,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);$s->setCellValue('D'.$next,$cat);$s->setCellValueExplicit('E'.$next,$unit,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);foreach($values as $m=>$v)if($v!==null)$s->setCellValueExplicit([$m+6,$next],$v,is_numeric($v)?\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC:\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);$next++;
            }
        }
    }
    $sharedIb=$book->getSheetByName('_TEMPLATE')->getCell('A1')->getValue()==='KONIMEX_UNIT_SELL_OUT_V6';$ibParts=$sharedIb?readIbUnitTable($book->getSheetByName('IB'),false):null;
    foreach(['ETHICAL','IB'] as $name){$source=$book->getSheetByName($name);if(!$source)throw new InvalidArgumentException('Sheet '.$name.' tidak ditemukan.');$normalized->addSheet(clone $source);if($name==='IB'&&$sharedIb){$old=buildUnitSellOutLegacyProductTemplate($year.'-01',1,2,true,false);$normalized->removeSheetByIndex($normalized->getIndex($normalized->getSheetByName('IB')));$normalized->addSheet(clone $old->getSheetByName('IB'));$old->disconnectWorksheets();foreach(['A'=>6,'B'=>7] as $key=>$row)foreach($ibParts[$key] as $m=>$v)$normalized->getSheetByName('IB')->setCellValue([$m+3,$row],$v);}}
    $meta=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($normalized,'_TEMPLATE');$normalized->addSheet($meta);$meta->fromArray([['KONIMEX_UNIT_SELL_OUT_V3'],['TARGET UNIT SELL OUT'],[$year],[$year.'-01'],[max(1,$next-6)],[2]]);
    try{$extraInput=$sharedIb&&count(array_filter(array_merge($ibParts['actual_A'],$ibParts['actual_B']),static fn($v)=>$v!==null))>0;$result=parseUnitSellOutProductTemplate($normalized,$year,$extraInput,max(1,$next-6));if($sharedIb){$result['inputs']['ib']=$ibParts;$result['ib_shared']=true;}return $result;}finally{$normalized->disconnectWorksheets();}
}
function fillUnitSellOutCategoryHistory(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $period,array $history): void {
    if(substr($period,5,2)==='01')return;$year=(int)substr($period,0,4);$other=[];
    foreach($history as $record){if((int)$record['report_year']!==$year)continue;$product=$record['product'];if(!preg_match('/^gb([1-6])$/D',$product,$match)){$other[]=$record;continue;}$parts=json_decode($record['payload'],true,512,JSON_THROW_ON_ERROR);$sheet=$book->getSheetByName('GB '.$match[1]);$blocks=locateUnitSellOutCategoryTables($sheet);$next=array_map(fn($b)=>$b['first'],$blocks);
        if(($parts['__format']??'')!=='products_v2'){ $rows=[];foreach(['A','B','C','D'] as $cat)if(isset($parts[$cat]))$rows[]=['name'=>'Subtotal kategori tersimpan','category'=>$cat,'unit'=>'','months'=>$parts[$cat]];}else $rows=$parts['rows'];
        foreach($rows as $row){$cat=$row['category'];if(!isset($blocks[$cat]))throw new RuntimeException('Kategori riwayat GB tidak valid.');$r=$next[$cat]++;if($r>$blocks[$cat]['last'])throw new RuntimeException('Riwayat GB '.$match[1].' kategori '.$cat.' melebihi ruang tabel yang tersedia. Data tersimpan tetap utuh.');$sheet->setCellValueExplicit([$blocks[$cat]['columns']['name'],$r],$row['name'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);$sheet->setCellValueExplicit([$blocks[$cat]['columns']['unit'],$r],$row['unit']??'',\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);if(count($row['months'])!==12)throw new RuntimeException('Riwayat bulanan produk tidak lengkap.');foreach($row['months'] as $m=>$v)if($v!==null)$sheet->setCellValue([$blocks[$cat]['columns']['months'][$m],$r],(float)$v);}
    }
    $version=$book->getSheetByName('_TEMPLATE')->getCell('A1')->getValue();if($version==='KONIMEX_UNIT_SELL_OUT_V6'){$remaining=[];foreach($other as $record){if($record['product']==='ib')fillIbUnitTable($book->getSheetByName('IB'),false,json_decode($record['payload'],true,512,JSON_THROW_ON_ERROR));else $remaining[]=$record;}$other=$remaining;}
    // Reuse the existing Ethical/IB history path without changing those sheets.
    $meta=$book->getSheetByName('_TEMPLATE');$meta->setCellValue('A1','KONIMEX_UNIT_SELL_OUT_V4');try{fillUnitSellOutProductHistory($book,$period,$other);}finally{$meta->setCellValue('A1',$version);}
}
