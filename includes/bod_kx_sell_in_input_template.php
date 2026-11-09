<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

function bodKxInputPeriod(string $period): array {
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$m))throw new InvalidArgumentException('Periode BOD KX SELL IN tidak valid.');
    return [(int)$m[1],(int)$m[2]];
}
function bodKxInputMonths(): array {return ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];}
function bodKxInputGroups(bool $rod=false): array {
    $groups=['gb1'=>'GB 1','gb2'=>'GB 2','gb3'=>'GB 3','gb4'=>'GB 4','gb5'=>'GB 5','gb6'=>'GB 6','ethical'=>'ETHICAL'];
    return $rod?$groups:$groups+['ib_pharma'=>'IB FARMASI','ib_food'=>'IB FOOD','solonat'=>'EKSTRAKSI SOLONAT','kodc'=>'KONIMEX DIAGNOSTIC CENTER','toll_in'=>'TOLL IN'];
}
function bodKxInputNorm($value): string {return strtoupper(trim(preg_replace('/\s+/u',' ',str_replace("\xc2\xa0",' ',(string)$value))));}
function bodKxInputGroupCode($value,bool $rod=false): ?string {
    $label=bodKxInputNorm($value);$compact=str_replace(' ','',$label);
    if(preg_match('/^GB([1-6])$/D',$compact,$m))$group='gb'.$m[1];
    elseif(in_array($compact,['ET','ETH','ETHICAL','GBET','GBETH','GBETHICAL'],true))$group='ethical';
    else $group=match($label){'IB FARMA','IB FARMASI','IB PHARMA','INTERNATIONAL BUSINESS PHARMA'=>'ib_pharma','IB FOOD','INTERNATIONAL BUSINESS FOOD'=>'ib_food','SOLONAT','EKSTRAKSI SOLONAT','EXTRAKSI SOLONAT'=>'solonat','KODC','KONIMEX DIAGNOSTIC CENTER'=>'kodc','TOLL IN','TOLLIN'=>'toll_in',default=>null};
    return $group!==null&&isset(bodKxInputGroups($rod)[$group])?$group:null;
}
function isBodKxInputTemplate(Spreadsheet $book): bool {return $book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue()==='KONIMEX_BOD_KX_SELL_IN_V1';}

/** Only product detail is entered; displayed subtotals never become extra records. */
function buildBodKxInputTemplate(string $period,array $history=[]): Spreadsheet {
    [$year,$month]=bodKxInputPeriod($period);$months=bodKxInputMonths();
    if(($history['year']??null)!==$year)$history=[];
    if($month===1){
        if(!empty($history['monthly_imports'][1])||!empty($history['rod_imported'])){if(empty($history['rod_imported']))$history['rod']=[];if(empty($history['monthly_imports'][1]))$history['sales']=[];foreach($history['sales'] as &$record)foreach(['units','rupiah'] as $field)foreach(range(1,11) as $m)$record[$field][$m]=null;unset($record);}
        else $history=[];
    }
    $fresh=empty($history['sales'])&&empty($history['rod']);
    $book=new Spreadsheet();$book->removeSheetByIndex(0);$book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
    foreach(['PENJUALAN'=>'sales','ROD'=>'rod'] as $name=>$kind){
        $rod=$kind==='rod';$sheet=new Worksheet($book,$name);$book->addSheet($sheet);$sheet->setShowGridlines(false);$firstMonth=$rod?6:2;$end=Coordinate::stringFromColumnIndex($firstMonth+25);
        $instructions=$rod?'Isi identitas, Harga Beli, dan ROD Unit bulanan. Nilai ROD = Unit × Harga Beli; subtotal otomatis.':'Isi Barang, Jumlah Unit, dan Rupiah per bulan, bukan Januari s/d bulan. Sub Total dihitung otomatis.';
        foreach([1=>'BOD KX SELL IN — '.$name.' — TAHUN '.$year,2=>'PERIODE LAPORAN: '.$months[$month-1].' '.$year,3=>$instructions,4=>'Baris produk boleh ditambah/dikurangi. Kelompok/bulan yang seluruhnya kosong tidak menimpa data tersimpan; isi 0 untuk menolkan.'] as $r=>$value){$sheet->setCellValue('A'.$r,$value);$sheet->mergeCells('A'.$r.':'.$end.$r);}
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);$sheet->getRowDimension(1)->setRowHeight(36);$sheet->getRowDimension(3)->setRowHeight(32);$sheet->getRowDimension(4)->setRowHeight(32);$sheet->getStyle('A2:'.$end.'4')->getFont()->getColor()->setARGB('FF64748B');$sheet->getStyle('A3:'.$end.'4')->getAlignment()->setWrapText(true);
        $title=5;
        foreach(bodKxInputGroups($rod) as $group=>$label){
            $records=array_values(array_filter($history[$kind]??[],static fn($r)=>($r['product']??'')===$group));$count=$fresh?200:count($records);$header=$title+1;$first=$title+3;$last=$first+$count-1;$total=$last+1;
            $sheet->setCellValue('A'.$title,$label);$sheet->mergeCells('A'.$title.':'.$end.$title);
            $identity=$rod?['TAHUN','GROUP BRAND','KODE','NAMA PRODUK','HARGA BELI']:['BARANG'];
            foreach($identity as $i=>$value){$col=Coordinate::stringFromColumnIndex($i+1);$sheet->setCellValue($col.$header,$value);$sheet->mergeCells($col.$header.':'.$col.($header+1));}
            foreach([...$months,'TOTAL FY'] as $m=>$labelMonth){$col=$firstMonth+$m*2;$a=Coordinate::stringFromColumnIndex($col);$b=Coordinate::stringFromColumnIndex($col+1);$sheet->setCellValue($a.$header,$rod||$m===12?$labelMonth:'BULAN '.$labelMonth.' '.$year);$sheet->mergeCells($a.$header.':'.$b.$header);$sheet->setCellValue($a.($header+1),$rod?'ROD Unit Terkecil Dari Unit Pesan':'Jumlah Unit');$sheet->setCellValue($b.($header+1),$rod?'Nilai ROD Unit Terkecil Dari Unit Pesan':'Rupiah');}
            for($r=$first;$r<=$last;$r++){
                if($rod){$sheet->setCellValue('A'.$r,$year);$sheet->setCellValueExplicit('B'.$r,$label,DataType::TYPE_STRING);foreach(range(0,11) as $m){$col=Coordinate::stringFromColumnIndex($firstMonth+$m*2);$sheet->setCellValue([$firstMonth+$m*2+1,$r],'='.$col.$r.'*E'.$r);}}
                foreach(range(0,1) as $offset){$cells=[];foreach(range(0,11) as $m)$cells[]=Coordinate::stringFromColumnIndex($firstMonth+$m*2+$offset).$r;$sheet->setCellValue([$firstMonth+24+$offset,$r],'=SUM('.implode(',',$cells).')');}
            }
            $sheet->setCellValue('A'.$total,'Sub Total');if($rod)$sheet->mergeCells('A'.$total.':E'.$total);
            for($c=$firstMonth;$c<$firstMonth+26;$c++){$col=Coordinate::stringFromColumnIndex($c);$sheet->setCellValue($col.$total,$count>0?'=SUM('.$col.$first.':'.$col.$last.')':'=0');}
            $sheet->getStyle('A'.$title.':'.$end.$title)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF345C50');$sheet->getStyle('A'.$title)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$sheet->getRowDimension($title)->setRowHeight(28);
            $sheet->getStyle('A'.$header.':'.$end.($header+1))->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');$sheet->getStyle('A'.$header.':'.$end.($header+1))->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$sheet->getStyle('A'.$header.':'.$end.($header+1))->getAlignment()->setWrapText(true)->setHorizontal('center');$sheet->getRowDimension($header)->setRowHeight(30);$sheet->getRowDimension($header+1)->setRowHeight($rod?72:28);
            if($count>0)$sheet->getStyle('A'.$first.':'.$end.$last)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF8DD');$sheet->getStyle('A'.$total.':'.$end.$total)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF2FF');$sheet->getStyle('A'.$total.':'.$end.$total)->getFont()->setBold(true);
            $sheet->getStyle(Coordinate::stringFromColumnIndex($firstMonth).$first.':'.$end.$total)->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');$sheet->getStyle('A'.$header.':'.$end.$total)->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFE1E5EB');
            if($rod&&$count>0){$sheet->getStyle('C'.$first.':D'.$last)->getNumberFormat()->setFormatCode('@');$sheet->getStyle('E'.$first.':E'.$last)->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');foreach(range(0,11) as $m){$col=Coordinate::stringFromColumnIndex($firstMonth+$m*2+1);$sheet->getStyle($col.$first.':'.$col.$last)->getFill()->getStartColor()->setARGB('FFEAF2FF');}}
            $numeric=(new DataValidation())->setType('decimal')->setOperator('between')->setFormula1('-999999999999999')->setFormula2('999999999999999')->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle('stop')->setError('Isi angka atau biarkan kosong.');
            if($rod&&$count>0){$sheet->setDataValidation('E'.$first.':E'.$last,(clone $numeric)->setOperator('greaterThanOrEqual')->setFormula1('0'));foreach(range(0,11) as $m){$col=Coordinate::stringFromColumnIndex($firstMonth+$m*2);$sheet->setDataValidation($col.$first.':'.$col.$last,clone $numeric);}}
            elseif(!$rod&&$count>0)$sheet->setDataValidation('B'.$first.':Y'.$last,$numeric);
            foreach($records as $i=>$record){$r=$first+$i;if($rod){$sheet->setCellValueExplicit('C'.$r,$record['code'],DataType::TYPE_STRING);$sheet->setCellValueExplicit('D'.$r,$record['name'],DataType::TYPE_STRING);$sheet->setCellValue('E'.$r,$record['price']);}else $sheet->setCellValueExplicit('A'.$r,$record['barang'],DataType::TYPE_STRING);
                foreach(range(0,11) as $m){if(!$rod&&$m+1>$month)continue;foreach($rod?['units']:['units','rupiah'] as $offset=>$field){$value=$record[$field][$m]??null;if($value!==null)$sheet->setCellValue([$firstMonth+$m*2+$offset,$r],$value);}}
            }
            $title=$total+3;
        }
        foreach($rod?['A'=>12,'B'=>18,'C'=>20,'D'=>40,'E'=>18]:['A'=>45] as $col=>$width)$sheet->getColumnDimension($col)->setWidth($width);
        for($c=$firstMonth;$c<$firstMonth+26;$c++)$sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(($c-$firstMonth)%2?24:18);
        $sheet->getDefaultRowDimension()->setRowHeight(24);$sheet->getStyle('A1:'.$end.($title-3))->getAlignment()->setVertical('center');$sheet->freezePane(Coordinate::stringFromColumnIndex($firstMonth).'8');$sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:'.$end.($title-3));
    }
    $meta=new Worksheet($book,'_TEMPLATE');$book->addSheet($meta);$meta->fromArray([['KONIMEX_BOD_KX_SELL_IN_V1'],['BOD KX Sell In'],[$year],[$period]]);$meta->setSheetState('hidden');$book->setActiveSheetIndex(0);return $book;
}

/** Locate labels and paired month headers instead of fixed row/column offsets. */
function locateBodKxInputTables(Worksheet $sheet,int $year,bool $rod): array {
    $groups=bodKxInputGroups($rod);$titles=[];$subtotals=[];$text=[];
    foreach($sheet->getCellCollection()->getCoordinates() as $address){$cell=$sheet->getCell($address);$value=$cell->getValue();if(!is_string($value)||$cell->isFormula())continue;[$col,$row]=Coordinate::coordinateFromString($address);$column=Coordinate::columnIndexFromString($col);$label=bodKxInputNorm($value);$text[$row][$column]=$label;
        if($label==='SUB TOTAL')$subtotals[]=$row;
        $group=bodKxInputGroupCode($label,$rod);if($group!==null&&$cell->isInMergeRange()){if(isset($titles[$group]))throw new InvalidArgumentException('Judul kelompok berulang pada '.$sheet->getTitle().'.');$titles[$group]=$row;}
    }
    $tables=[];
    foreach($groups as $group=>$label){$title=$titles[$group]??null;if($title===null)throw new InvalidArgumentException('Tabel '.$label.' tidak ditemukan pada '.$sheet->getTitle().'.');$end=$sheet->getHighestRow()+1;foreach($titles as $row)if($row>$title)$end=min($end,$row);$totals=array_values(array_unique(array_filter($subtotals,static fn($r)=>$r>$title&&$r<$end)));if(count($totals)!==1)throw new InvalidArgumentException('Baris Sub Total '.$label.' tidak lengkap atau berulang.');$total=$totals[0];
        $header=null;foreach($text as $row=>$values)if($row>$title&&$row<$total&&in_array($rod?'NAMA PRODUK':'BARANG',$values,true)){if($header!==null)throw new InvalidArgumentException('Header produk berulang pada tabel '.$label.'.');$header=$row;}
        if($header===null)throw new InvalidArgumentException('Header produk tidak ditemukan pada tabel '.$label.'.');$identity=[];
        foreach($rod?['year'=>'TAHUN','group'=>'GROUP BRAND','code'=>'KODE','name'=>'NAMA PRODUK','price'=>'HARGA BELI']:['barang'=>'BARANG'] as $key=>$heading){$matches=array_keys($text[$header]??[],$heading,true);if(count($matches)!==1)throw new InvalidArgumentException('Kolom '.$heading.' tidak lengkap atau berulang pada tabel '.$label.'.');$identity[$key]=$matches[0];}
        $months=[];$active=null;$width=Coordinate::columnIndexFromString($sheet->getHighestColumn());
        for($col=1;$col<=$width;$col++){$heading=$text[$header][$col]??'';if($heading!==''){$active=null;foreach(bodKxInputMonths() as $m=>$month)if($heading===($rod?$month:'BULAN '.$month.' '.$year)){$active=$m;break;}}
            if($active===null)continue;$sub=$text[$header+1][$col]??'';$field=match($sub){'JUMLAH UNIT','ROD UNIT TERKECIL DARI UNIT PESAN'=>'units','RUPIAH','NILAI ROD UNIT TERKECIL DARI UNIT PESAN'=>'rupiah',default=>null};if($field!==null){if(isset($months[$active][$field]))throw new InvalidArgumentException('Kolom bulan berulang pada tabel '.$label.'.');$months[$active][$field]=$col;}
        }
        if(count($months)!==12)throw new InvalidArgumentException('Header Januari–Desember tahun '.$year.' tidak lengkap pada tabel '.$label.'.');foreach($months as $pair)if(count($pair)!==2)throw new InvalidArgumentException('Setiap bulan harus berisi kolom unit dan rupiah pada tabel '.$label.'.');ksort($months);
        $tables[$group]=['title'=>$title,'header'=>$header,'first'=>$header+2,'last'=>$total-1,'total'=>$total,'identity'=>$identity,'months'=>$months];
    }
    return $tables;
}
function bodKxInputNumber($value,string $where): ?float {
    if($value===null||$value===''||in_array(trim((string)$value),['-','–','—'],true))return null;
    if(!is_numeric($value)||!is_finite((float)$value)||abs((float)$value)>=1e18)throw new InvalidArgumentException('Isi angka yang valid pada '.$where.'.');
    return (float)$value;
}
function bodKxInputCellNumber(Worksheet $sheet,int $column,int $row): ?float {
    $cell=$sheet->getCell([$column,$row]);try{$value=$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus tidak dapat dihitung pada '.$sheet->getTitle().' '.$cell->getCoordinate().'.');}
    return bodKxInputNumber($value,$sheet->getTitle().' '.$cell->getCoordinate());
}
function parseBodKxInputTemplate(Spreadsheet $book,string $period): array {
    [$year]=bodKxInputPeriod($period);if(!isBodKxInputTemplate($book)||(int)$book->getSheetByName('_TEMPLATE')->getCell('A3')->getValue()!==$year)throw new InvalidArgumentException('Tahun template BOD KX SELL IN tidak sesuai periode upload.');
    $result=['year'=>$year,'sales'=>[],'rod'=>[]];$hasInput=false;
    foreach(['PENJUALAN'=>'sales','ROD'=>'rod'] as $name=>$kind){$sheet=$book->getSheetByName($name);if(!$sheet)throw new InvalidArgumentException('Sheet '.$name.' tidak ditemukan.');$rod=$kind==='rod';$seen=[];
        foreach(locateBodKxInputTables($sheet,$year,$rod) as $group=>$table)for($row=$table['first'];$row<=$table['last'];$row++){
            $identity=[];foreach($table['identity'] as $key=>$column)$identity[$key]=trim((string)$sheet->getCell([$column,$row])->getCalculatedValue());$record=['product'=>$group,'units'=>array_fill(0,12,null)];if(!$rod)$record['rupiah']=array_fill(0,12,null);$has=false;
            foreach($table['months'] as $m=>$pair)foreach($rod?['units']:['units','rupiah'] as $field){$value=bodKxInputCellNumber($sheet,$pair[$field],$row);$record[$field][$m]=$value;$has=$has||$value!==null;}
            $nameValue=$identity[$rod?'name':'barang'];$code=$identity['code']??'';$price=$rod?bodKxInputCellNumber($sheet,$table['identity']['price'],$row):null;
            if($nameValue===''&&$code===''&&$price===null&&!$has)continue;
            if($nameValue===''||mb_strlen($nameValue)>255)throw new InvalidArgumentException('Isi '.($rod?'Nama Produk':'Barang').' pada '.$name.' baris '.$row.'.');
            if(preg_match('/^(?:SUB\s*TOTAL|GRAND\s+TOTAL|TOTAL)$/i',$nameValue))throw new InvalidArgumentException('Gunakan baris produk; Sub Total dihitung otomatis ('.$name.' baris '.$row.').');
            if($rod){if($code===''||mb_strlen($code)>100)throw new InvalidArgumentException('Isi Kode produk pada ROD baris '.$row.'.');if($identity['year']!==''&&(string)$year!==$identity['year'])throw new InvalidArgumentException('Tahun produk ROD berbeda dari periode upload (baris '.$row.').');if($identity['group']!==''&&bodKxInputGroupCode($identity['group'],true)!==$group)throw new InvalidArgumentException('Group Brand tidak sesuai tabel pada ROD baris '.$row.'.');if(($price===null&&$has)||($price!==null&&$price<0))throw new InvalidArgumentException('Isi Harga Beli nol atau positif pada ROD baris '.$row.'.');$record+=['code'=>$code,'name'=>$nameValue,'price'=>$price];foreach($record['units'] as $value)if($value!==null)bodKxInputNumber($value*$price,'hasil Nilai ROD baris '.$row);$key=$group.'|'.mb_strtoupper($code);}
            else{$record['barang']=$nameValue;$key=$group.'|'.mb_strtoupper($nameValue);}
            if(isset($seen[$key]))throw new InvalidArgumentException('Produk berulang pada '.$name.' baris '.$row.'.');$seen[$key]=true;$result[$kind][]=$record;$hasInput=$hasInput||$has;
        }
    }
    if(!$hasInput)throw new InvalidArgumentException('Template BOD KX SELL IN masih kosong. Isi penjualan atau target ROD sebelum upload.');
    return $result;
}
