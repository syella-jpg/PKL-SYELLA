<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Border;

function isBiayaInputTemplate(Spreadsheet $book): bool
{
    return $book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue()==='KONIMEX_BIAYA_V1';
}

/** New empty tables only; no product names or financial amounts from source files. */
function buildBiayaInputTemplate(string $period, array $slotsPerGb=[]): Spreadsheet
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
        foreach(['GB','KATEGORI','NO. PRODUK','NAMA PRODUK',...$months] as $i=>$header){
            if(strtoupper(trim((string)$sheet->getCell([$i+1,5])->getValue()))!==$header)throw new InvalidArgumentException('Header template BIAYA berubah. Gunakan susunan template yang diunduh.');
        }
        for($r=6;$r<=min(10000,$sheet->getHighestDataRow());$r++){
            $identity=static function(string $address)use($sheet){
                $cell=$sheet->getCell($address);$v=$cell->getCalculatedValue();
                return $cell->isFormula() && ($v===0 || $v===0.0) ? null : $v;
            };
            $product=trim((string)$identity('D'.$r));$values=[];$rowHasValue=false;
            for($c=5;$c<=16;$c++){
                $cell=$sheet->getCell([$c,$r]);$v=$cell->getCalculatedValue();
                if(is_string($v))$v=trim($v);
                if($v===null||$v===''||in_array($v,['-','–','—'],true))$v=null;
                elseif(!is_numeric($v)||!is_finite((float)$v))throw new InvalidArgumentException('Nilai BIAYA tidak valid di '.$name.' sel '.$cell->getCoordinate().'.');
                else{$v=(float)$v;$rowHasValue=true;}
                $values[]=$v;
            }
            if($product===''&&!$rowHasValue)continue;
            if($product==='')throw new InvalidArgumentException('Isi nama produk pada sheet '.$name.' baris '.$r.'.');
            $gb=$sheet->getCell('A'.$r)->getCalculatedValue();$category=strtoupper(trim((string)$identity('B'.$r)));$number=$identity('C'.$r);
            if(!preg_match('/^[1-6]$/',(string)$gb)||!in_array($category,['EXIS','D'],true)||!is_numeric($number)||(float)$number<1||(float)$number!==(float)(int)$number)throw new InvalidArgumentException('GB, kategori, atau nomor produk tidak valid pada '.$name.' baris '.$r.'.');
            $key=$type.':'.$gb.':'.(int)$number;if(isset($rows[$key]))throw new InvalidArgumentException('Nomor produk berulang pada GB '.$gb.' sheet '.$name.'.');
            // Legacy reports divide GB5's 2026 target by 1000; keep storage compatible
            // while all new template inputs consistently use Rp (000).
            if($year===2026&&$type==='target'&&(int)$gb===5)$values=array_map(static fn($v)=>$v===null?null:$v*1000,$values);
            $rows[$key]=['type'=>$type,'gb'=>(int)$gb,'category'=>$category,'number'=>(int)$number,'name'=>$product,'values'=>$values];
            $hasValue=$hasValue||$rowHasValue;
        }
    }
    if(!$hasValue)throw new InvalidArgumentException('Template BIAYA masih kosong. Isi nama produk dan minimal satu nilai sebelum upload.');
    return array_values($rows);
}
