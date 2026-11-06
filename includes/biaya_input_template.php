<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Border;

function isBiayaInputTemplate(Spreadsheet $book): bool
{
    return in_array($book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue(),['KONIMEX_BIAYA_V1','KONIMEX_BIAYA_V2'],true);
}

/** New empty tables only; no product names or financial amounts from source files. */
function buildBiayaLegacyInputTemplate(string $period, array $slotsPerGb=[]): Spreadsheet
{
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$m))throw new InvalidArgumentException('Periode BIAYA tidak valid.');
    $year=(int)$m[1];$book=new Spreadsheet();$book->removeSheetByIndex(0);
    $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
    $months=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
    foreach([$year===2025?'TARGET ROLLING':'TARGET','REALISASI'] as $name){
        $sheet=new Worksheet($book,$name);$book->addSheet($sheet);
        $sheet->setCellValue('A1','BIAYA '.$name.' TAHUN '.$year.' — Rp (000)');$sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A2','PERIODE LAPORAN: '.$months[(int)$m[2]-1].' '.$year);$sheet->mergeCells('A2:P2');
        $sheet->setCellValue('A3','Isi kategori, nomor produk, nama produk, dan nilai bulanan pada sel kuning. EXIS = Existing; D = New Product. Baris yang belum dipakai boleh kosong.');$sheet->mergeCells('A3:P3');
        $sheet->setCellValue('A4','Satuan Rp (000), bukan kumulatif. Tambahkan baris produk jika diperlukan; nomor produk harus unik per GB.');$sheet->mergeCells('A4:P4');
        $sheet->fromArray([['GB','KATEGORI','NO. PRODUK','NAMA PRODUK',...$months]],null,'A5');
        $r=6;
        foreach(range(1,6) as $gb)foreach(range(1,max(30,(int)($slotsPerGb[$gb]??30))) as $number){
            $sheet->setCellValue('A'.$r,$gb);
            $sheet->getStyle('A'.$r.':C'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB($gb%2?'FFF1F5F9':'FFE8EDF5');
            $sheet->getStyle('B'.$r.':P'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF8DD');
            $sheet->getRowDimension($r)->setRowHeight(24);
            $sheet->getCell('B'.$r)->getDataValidation()->setType(DataValidation::TYPE_LIST)->setFormula1('"EXIS,D"')->setAllowBlank(false)->setShowDropDown(true)->setShowErrorMessage(true);
            for($c=5;$c<=16;$c++)$sheet->getCell([$c,$r])->getDataValidation()->setType(DataValidation::TYPE_DECIMAL)->setOperator(DataValidation::OPERATOR_BETWEEN)
                ->setFormula1('-999999999999999')->setFormula2('999999999999999')->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)->setError('Isi angka atau biarkan kosong.');
            $r++;
        }
        if($name==='REALISASI') {
            $targetName=$year===2025?'TARGET ROLLING':'TARGET';
            foreach(range(6,$r-1) as $row)foreach(['B','C','D'] as $column) {
                $sheet->setCellValue($column.$row, "='".$targetName."'!".$column.$row);
            }
            $sheet->getStyle('B6:D'.($r-1))->getFill()->getStartColor()->setARGB('FFEAF2FF');
            $sheet->getStyle('B6:D'.($r-1))->getNumberFormat()->setFormatCode('0;-0;;@');
            $sheet->setCellValue('A3','Kategori, nomor, dan nama produk mengikuti sheet '.$targetName.'. Isi nilai realisasi bulanan pada sel kuning.');
        }
        $sheet->getStyle('E6:P'.($r-1))->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');
        $sheet->getStyle('A5:P'.($r-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE1E5EB');
        $sheet->getStyle('A5:P5')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');
        $sheet->getStyle('A5:P5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:P1')->getFont()->setBold(true)->setSize(18);
        $sheet->getStyle('A2:P4')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF64748B'));
        $sheet->getStyle('A1:P'.($r-1))->getAlignment()->setVertical('center');
        $sheet->getStyle('A3:P5')->getAlignment()->setWrapText(true);
        foreach([1=>36,2=>24,3=>32,4=>28,5=>32] as $row=>$height)$sheet->getRowDimension($row)->setRowHeight($height);
        $sheet->getColumnDimension('A')->setWidth(8);$sheet->getColumnDimension('B')->setWidth(13);$sheet->getColumnDimension('C')->setWidth(14);$sheet->getColumnDimension('D')->setWidth(36);
        for($c=5;$c<=16;$c++)$sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(16);
        $sheet->freezePane('E6');$sheet->setShowGridlines(false);$sheet->setAutoFilter('A5:P'.($r-1));
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:P'.($r-1));
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(5,5);
    }
    $meta=new Worksheet($book,'_TEMPLATE');$book->addSheet($meta);$meta->fromArray([['KONIMEX_BIAYA_V1'],['BIAYA'],[$year],[$period]]);$meta->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $book->setActiveSheetIndex(0);return $book;
}

function parseBiayaInputTemplate(Spreadsheet $book,int $year): array
{
    if((int)$book->getSheetByName('_TEMPLATE')?->getCell('A3')->getValue()!==$year)throw new InvalidArgumentException('Tahun template BIAYA tidak sesuai dengan periode upload.');
    $months=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
    $rows=[];$hasValue=false;
    foreach(['target'=>$year===2025?'TARGET ROLLING':'TARGET','actual'=>'REALISASI'] as $type=>$name){
        $sheet=$book->getSheetByName($name);if(!$sheet)throw new InvalidArgumentException('Sheet '.$name.' tidak ditemukan pada template BIAYA.');
        $grouped=$book->getSheetByName('_TEMPLATE')->getCell('A1')->getValue()==='KONIMEX_BIAYA_V2';
        foreach(locateBiayaInputTables($sheet,$grouped) as $layout){
        // Read existing rows only, including products below any former fixed limit.
        $candidateRows=[];foreach($sheet->getCellCollection()->getCoordinates() as $address){[$col,$row]=Coordinate::coordinateFromString($address);if($row<=$layout['header']||$row>($layout['last']??PHP_INT_MAX))continue;$column=Coordinate::columnIndexFromString($col);if(in_array($column,array_values($layout['identity']),true)||in_array($column,$layout['months'],true))$candidateRows[$row]=true;}ksort($candidateRows);
        foreach(array_keys($candidateRows) as $r){
            $identity=static function(string $address)use($sheet,$type){
                $cell=$sheet->getCell($address);$v=$cell->getCalculatedValue();
                // A deleted unused Target row can leave an empty Actual identity link.
                // Rows with amounts still require a valid product identity below.
                if($type==='actual'&&$cell->isFormula()&&$v==='#REF!')return null;
                return $cell->isFormula() && ($v===0 || $v===0.0) ? null : $v;
            };
            $product=trim((string)$identity(Coordinate::stringFromColumnIndex($layout['identity']['name']).$r));$values=[];$rowHasValue=false;
            foreach($layout['months'] as $c){
                $cell=$sheet->getCell([$c,$r]);$v=$cell->getCalculatedValue();
                if(is_string($v))$v=trim($v);
                if($v===null||$v===''||in_array($v,['-','–','—'],true))$v=null;
                elseif(!is_numeric($v)||!is_finite((float)$v))throw new InvalidArgumentException('Nilai BIAYA tidak valid di '.$name.' sel '.$cell->getCoordinate().'.');
                else{$v=(float)$v;$rowHasValue=true;}
                $values[]=$v;
            }
            if($product===''&&!$rowHasValue)continue;
            if($product==='')throw new InvalidArgumentException('Isi nama produk pada sheet '.$name.' baris '.$r.'.');
            $gb=$sheet->getCell([$layout['identity']['gb'],$r])->getCalculatedValue();$category=strtoupper(trim((string)$identity(Coordinate::stringFromColumnIndex($layout['identity']['category']).$r)));$number=$identity(Coordinate::stringFromColumnIndex($layout['identity']['number']).$r);
            if(isset($layout['gb'])){if($gb!==null&&$gb!==''&&(string)$gb!==(string)$layout['gb'])throw new InvalidArgumentException('GB produk tidak sesuai tabel GB '.$layout['gb'].' pada sheet '.$name.' baris '.$r.'.');$gb=$layout['gb'];}
            if(!preg_match('/^[1-6]$/',(string)$gb)||!in_array($category,['EXIS','D'],true)||!is_numeric($number)||(float)$number<1||(float)$number!==(float)(int)$number)throw new InvalidArgumentException('GB, kategori, atau nomor produk tidak valid pada '.$name.' baris '.$r.'.');
            $key=$type.':'.$gb.':'.(int)$number;if(isset($rows[$key]))throw new InvalidArgumentException('Nomor produk berulang pada GB '.$gb.' sheet '.$name.'.');
            // Legacy reports divide GB5's 2026 target by 1000; keep storage compatible
            // while all new template inputs consistently use Rp (000).
            if($year===2026&&$type==='target'&&(int)$gb===5)$values=array_map(static fn($v)=>$v===null?null:$v*1000,$values);
            $rows[$key]=['type'=>$type,'gb'=>(int)$gb,'category'=>$category,'number'=>(int)$number,'name'=>$product,'values'=>$values];
            $hasValue=$hasValue||$rowHasValue;
        }
    }
        }
    if(!$hasValue)throw new InvalidArgumentException('Template BIAYA masih kosong. Isi nama produk dan minimal satu nilai sebelum upload.');
    return array_values($rows);
}

/** The template's headers identify its columns; product row positions are unrestricted. */
function locateBiayaInputTables(Worksheet $sheet,bool $grouped=false): array
{
    $norm=static fn($value)=>strtoupper(trim(preg_replace('/\s+/u',' ',str_replace("\xc2\xa0",' ',(string)$value))));
    $candidates=[];foreach($sheet->getCellCollection()->getCoordinates() as $address){$value=$sheet->getCell($address)->getValue();if(is_string($value)&&$norm($value)==='NAMA PRODUK'){[, $row]=Coordinate::coordinateFromString($address);$candidates[$row]=true;}}
    $aliases=[['JAN','JANUARI'],['FEB','FEBRUARI'],['MAR','MARET'],['APR','APRIL'],['MAY','MEI'],['JUN','JUNI'],['JUL','JULI'],['AUG','AGS','AGT','AGU','AGUSTUS'],['SEP','SEPT','SEPTEMBER'],['OCT','OKT','OKTOBER'],['NOV','NOVEMBER'],['DEC','DES','DESEMBER']];$layouts=[];
    foreach(array_keys($candidates) as $header){$identity=[];$months=[];foreach($sheet->getRowIterator($header,$header) as $row){$cells=$row->getCellIterator();$cells->setIterateOnlyExistingCells(true);foreach($cells as $cell){$label=$norm($cell->getValue());$column=Coordinate::columnIndexFromString($cell->getColumn());$key=['GB'=>'gb','KATEGORI'=>'category','NO. PRODUK'=>'number','NO PRODUK'=>'number','NOMOR PRODUK'=>'number','NAMA PRODUK'=>'name'][$label]??null;if($key!==null){if(isset($identity[$key]))throw new InvalidArgumentException('Header identitas BIAYA berulang pada sheet '.$sheet->getTitle().'.');$identity[$key]=$column;}foreach($aliases as $month=>$names)if(in_array($label,$names,true)){if(isset($months[$month]))throw new InvalidArgumentException('Header bulan BIAYA berulang pada sheet '.$sheet->getTitle().'.');$months[$month]=$column;}}}
        if(count($identity)===4&&count($months)===12){ksort($months);$layouts[]=['header'=>$header,'identity'=>$identity,'months'=>$months];}
    }
    if(!$grouped){if(count($layouts)!==1)throw new InvalidArgumentException('Header GB, KATEGORI, NO. PRODUK, NAMA PRODUK, dan Januari–Desember tidak lengkap atau berulang pada sheet '.$sheet->getTitle().'.');return $layouts;}
    return biayaGroupLayouts($sheet,$layouts);
}
function locateBiayaInputTable(Worksheet $sheet): array {
    return locateBiayaInputTables($sheet)[0];
}

require_once __DIR__.'/biaya_group_template.php';
