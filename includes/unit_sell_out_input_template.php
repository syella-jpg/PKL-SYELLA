<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

/** Fixed input coordinates, shared by the generator and upload validator. */
function unitSellOutInputRows(): array {
    $groups=[];
    for($gb=1;$gb<=6;$gb++){
        $start=7+($gb-1)*8;
        $groups['gb'.$gb]=['A'=>$start,'B'=>$start+1,'C'=>$start+2,'D'=>$start+3];
    }
    $groups['ethical']=['existing'=>59,'new'=>60];
    $groups['ib']=['A'=>68,'B'=>69];
    return $groups;
}
function isUnitSellOutInputTemplate(Spreadsheet $book): bool {
    return in_array($book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue(),['KONIMEX_UNIT_SELL_OUT_V1','KONIMEX_UNIT_SELL_OUT_V2','KONIMEX_UNIT_SELL_OUT_V3'],true);
}
function unitSellOutInputMonths(): array {
    return ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
}
function buildUnitSellOutSummaryTemplate(string $period): Spreadsheet {
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$match))throw new InvalidArgumentException('Periode TARGET UNIT SELL OUT tidak valid.');
    $year=(int)$match[1];$month=(int)$match[2];$months=unitSellOutInputMonths();
    $book=new Spreadsheet();$sheet=$book->getActiveSheet();$sheet->setTitle('TARGET UNIT SELL OUT');
    $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11)->getColor()->setARGB('FF263449');
    $sheet->setShowGridlines(false);
    foreach(['A'=>25,'B'=>53] as $column=>$width)$sheet->getColumnDimension($column)->setWidth($width);
    foreach(range('C','P') as $column)$sheet->getColumnDimension($column)->setWidth(17);
    $sheet->setCellValue('A1','TARGET UNIT SELL OUT — TAHUN '.$year);$sheet->mergeCells('A1:P1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(20);
    $sheet->getRowDimension(1)->setRowHeight(38);
    $sheet->setCellValue('A2','PERIODE LAPORAN: '.$months[$month-1].' '.$year);$sheet->mergeCells('A2:P2');
    $sheet->setCellValue('A3','Isi sel kuning dalam UNIT penuh. Sel biru dihitung otomatis. Satu file untuk GB 1–6, Ethical, dan IB.');$sheet->mergeCells('A3:P3');
    $sheet->setCellValue('A4','GB dan Ethical: target per bulan. IB: target kumulatif S/D bulan. Kategori yang tidak ada diisi 0.');$sheet->mergeCells('A4:P4');
    $header=function(int $row,bool $cumulative=false)use($sheet,$months,$month){
        $sheet->setCellValue('A'.$row,'PARAMETER');$sheet->setCellValue('B'.$row,'SUMBER / PERHITUNGAN');
        foreach($months as $m=>$label)$sheet->setCellValue([$m+3,$row],$cumulative&&$m>0?'S/D '.$label:$label);
        $sheet->setCellValue('O'.$row,'YTD '.$months[$month-1]);$sheet->setCellValue('P'.$row,'TOTAL FY');
        $sheet->getStyle('A'.$row.':P'.$row)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');
        $sheet->getStyle('A'.$row.':P'.$row)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getRowDimension($row)->setRowHeight(30);
    };
    $inputRule=(new DataValidation())->setType(DataValidation::TYPE_DECIMAL)->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)->setFormula1('0')->setAllowBlank(true)->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)->setErrorTitle('Isi angka unit')->setError('Gunakan angka nol atau positif tanpa teks.');
    $input=function(int $row,string $label,string $source,bool $cumulative=false)use($sheet,$inputRule,$month){
        $sheet->setCellValue('A'.$row,$label);$sheet->setCellValue('B'.$row,$source);
        $sheet->getStyle('C'.$row.':N'.$row)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF3C4');
        $sheet->setDataValidation('C'.$row.':N'.$row,clone $inputRule);
        $last=Coordinate::stringFromColumnIndex(2+$month);
        $sheet->setCellValue('O'.$row,$cumulative?'='.$last.$row:'=SUM(C'.$row.':'.$last.$row.')');
        $sheet->setCellValue('P'.$row,$cumulative?'=N'.$row:'=SUM(C'.$row.':N'.$row.')');
    };
    $summary=function(int $row,string $label,array $children)use($sheet,$month){
        $sheet->setCellValue('A'.$row,$label);$sheet->setCellValue('B'.$row,'Dihitung otomatis');
        foreach(range('C','P') as $column)$sheet->setCellValue($column.$row,'='.implode('+',array_map(fn($r)=>$column.$r,$children)));
        $sheet->getStyle('A'.$row.':P'.$row)->getFont()->setBold(true);
    };
    $sheet->getStyle('C6:P71')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEAF1FF');
    $header(6);
    foreach(unitSellOutInputRows() as $product=>$rows){
        if(!preg_match('/^gb([1-6])$/',$product,$m))continue;$gb=$m[1];$start=$rows['A'];
        foreach($rows as $category=>$row)$input($row,'GB '.$gb.' — KATEGORI '.$category,'TARGET GB '.$gb.' → ringkasan UNIT kategori '.$category);
        $summary($start+4,'EXIST GB '.$gb,[$start,$start+1,$start+2]);
        $summary($start+5,'NP GB '.$gb,[$start+3]);
        $summary($start+6,'TOTAL GB '.$gb,[$start+4,$start+5]);
    }
    $sheet->setCellValue('A56','ETHICAL — TARGET BULANAN');$sheet->mergeCells('A56:P56');
    $sheet->setCellValue('A57','GMEB → Revenue Growth → Revenue Selling Out (Unit) → TARGET.');$sheet->mergeCells('A57:P57');
    $header(58);$input(59,'EXIST ETHICAL','GMEB → Existing Product (Unit) → Target');$input(60,'NP ETHICAL','GMEB → New Product (Unit) → Target');$summary(61,'TOTAL ETHICAL',[59,60]);
    $sheet->setCellValue('A64','IB — TARGET KUMULATIF');$sheet->mergeCells('A64:P64');
    $sheet->setCellValue('A65','HRU → Financial → Revenue Growth → Value Selling In → TARGET. Isi A dan B; IB per bulan dihitung otomatis.');$sheet->mergeCells('A65:P65');
    $header(67,true);$input(68,'A — SALES REVENUE','HRU → Sales Revenue (Unit) → Target',true);$input(69,'B — NEW BUSINESS','HRU → Sales Revenue New Business Product (Unit) → Target',true);
    $summary(70,'TOTAL IB KUMULATIF',[68,69]);
    $sheet->setCellValue('A71','IB PER BULAN');$sheet->setCellValue('B71','JAN = A+B; FEB dst. = kumulatif kini − sebelumnya');
    foreach(range('C','N') as $i=>$column){$previous=Coordinate::stringFromColumnIndex($i+2);$sheet->setCellValue($column.'71','='.$column.'70'.($i===0?'':'-'.$previous.'70'));}
    $sheet->setCellValue('O71','=SUM(C71:'.Coordinate::stringFromColumnIndex($month+2).'71)');$sheet->setCellValue('P71','=SUM(C71:N71)');
    foreach([56,64] as $row){$sheet->getStyle('A'.$row.':P'.$row)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF345C50');$sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');$sheet->getRowDimension($row)->setRowHeight(32);}
    foreach([[6,53],[58,61],[67,71]] as [$start,$end]){
        $sheet->getStyle('A'.$start.':P'.$end)->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFDDE4EE');
        for($r=$start+1;$r<=$end;$r++)$sheet->getRowDimension($r)->setRowHeight(25);
    }
    $sheet->getStyle('B6:B71')->getAlignment()->setWrapText(true);
    $sheet->getStyle('C7:P71')->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');
    $sheet->freezePane('C7');$sheet->setSelectedCell('C7');$sheet->getSheetView()->setZoomScale(85);
    $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0)->setPrintArea('A1:P71');
    $meta=new Worksheet($book,'_TEMPLATE');$book->addSheet($meta);$meta->fromArray([['KONIMEX_UNIT_SELL_OUT_V1'],['TARGET UNIT SELL OUT'],[$year],[$period]]);$meta->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    return $book;
}

/** Read only editable inputs. Recompute summaries so stale/tampered formula caches cannot alter imports. */
function parseUnitSellOutInputTemplate(Spreadsheet $book,int $year): array {
    if(in_array($book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue(),['KONIMEX_UNIT_SELL_OUT_V2','KONIMEX_UNIT_SELL_OUT_V3'],true))return parseUnitSellOutProductTemplate($book,$year);
    if(!isUnitSellOutInputTemplate($book)||(int)$book->getSheetByName('_TEMPLATE')->getCell('A3')->getValue()!==$year)throw new InvalidArgumentException('Tahun template TARGET UNIT SELL OUT berbeda dari periode upload.');
    $sheet=$book->getSheetByName('TARGET UNIT SELL OUT');if(!$sheet)throw new InvalidArgumentException('Sheet TARGET UNIT SELL OUT tidak ditemukan.');
    foreach([6,58,67] as $header)foreach(unitSellOutInputMonths() as $m=>$label){
        $expected=$header===67&&$m>0?'S/D '.$label:$label;
        if($sheet->getCell([$m+3,$header])->getValue()!==$expected)throw new InvalidArgumentException('Kolom bulan template berubah. Unduh kembali template TARGET UNIT SELL OUT.');
    }
    $reference=buildUnitSellOutSummaryTemplate($year.'-01');$expected=$reference->getSheetByName('TARGET UNIT SELL OUT');$inputs=[];$hasNumber=false;
    foreach(unitSellOutInputRows() as $product=>$rows)foreach($rows as $kind=>$row){
        if($sheet->getCell('A'.$row)->getValue()!==$expected->getCell('A'.$row)->getValue())throw new InvalidArgumentException('Label baris template berubah pada baris '.$row.'.');
        for($m=0;$m<12;$m++){
            $cell=$sheet->getCell([$m+3,$row]);
            try{$v=$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus tidak dapat dihitung pada '.$cell->getCoordinate().'.');}
            if($v===null||$v===''||in_array(trim((string)$v),['-','–','—'],true))$v=null;
            elseif(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||abs((float)$v)>=1e18)throw new InvalidArgumentException('Isi angka unit nol atau positif pada '.$cell->getCoordinate().'.');
            else{$v=(float)$v;$hasNumber=true;}
            $inputs[$product][$kind][$m]=$v;
        }
    }
    $reference->disconnectWorksheets();
    if(!$hasNumber)throw new InvalidArgumentException('Template TARGET UNIT SELL OUT masih kosong. Isi minimal satu target; kategori yang tidak ada dapat diisi 0.');
    $records=[];
    foreach($inputs as $product=>$parts){
        if($product==='ib'){
            $cumulative=[];for($m=0;$m<12;$m++)$cumulative[$m]=$parts['A'][$m]===null||$parts['B'][$m]===null?null:$parts['A'][$m]+$parts['B'][$m];
            for($m=0;$m<12;$m++){
                $prior=$m===0?0:$cumulative[$m-1];$value=$cumulative[$m]===null||$prior===null?null:$cumulative[$m]-$prior;
                $records[]=['report_year'=>$year,'product'=>$product,'kind'=>'total','month'=>$m+1,'units'=>$value===null?null:number_format($value,6,'.','')];
            }
            continue;
        }
        foreach(['existing','new'] as $kind)for($m=0;$m<12;$m++){
            if($product==='ethical')$value=$parts[$kind][$m];
            else{$categories=$kind==='existing'?['A','B','C']:['D'];$value=null;foreach($categories as $category)if($parts[$category][$m]!==null)$value=($value??0)+$parts[$category][$m];}
            $records[]=['report_year'=>$year,'product'=>$product,'kind'=>$kind,'month'=>$m+1,'units'=>$value===null?null:number_format($value,6,'.','')];
        }
    }
    return ['records'=>$records,'inputs'=>$inputs];
}

function ensureUnitSellOutInputStore(mysqli $conn): void {
    $conn->query('CREATE TABLE IF NOT EXISTS unit_sell_out_target_inputs (report_year SMALLINT UNSIGNED NOT NULL,product VARCHAR(30) NOT NULL,payload MEDIUMBLOB NOT NULL,source_period CHAR(7) NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(report_year,product)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function saveUnitSellOutInputs(mysqli $conn,int $year,string $period,array $products,?array $inputs): void {
    $delete=$conn->prepare('DELETE FROM unit_sell_out_target_inputs WHERE report_year=? AND product=?');
    foreach($products as $product){$delete->bind_param('is',$year,$product);$delete->execute();}$delete->close();
    if($inputs===null)return;
    $insert=$conn->prepare('INSERT INTO unit_sell_out_target_inputs (report_year,product,payload,source_period) VALUES (?,?,?,?)');
    foreach($inputs as $product=>$parts){$payload=json_encode($parts,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION);$insert->bind_param('isss',$year,$product,$payload,$period);$insert->execute();}$insert->close();
}
function fillUnitSellOutInputHistory(Spreadsheet $book,string $period,array $history): void {
    if(in_array($book->getSheetByName('_TEMPLATE')?->getCell('A1')->getValue(),['KONIMEX_UNIT_SELL_OUT_V2','KONIMEX_UNIT_SELL_OUT_V3'],true)){fillUnitSellOutProductHistory($book,$period,$history);return;}
    if(substr($period,5,2)==='01')return;
    $year=(int)substr($period,0,4);$rows=unitSellOutInputRows();$sheet=$book->getSheetByName('TARGET UNIT SELL OUT');
    foreach($history as $record){
        if((int)$record['report_year']!==$year)continue;
        $product=$record['product'];if(!isset($rows[$product]))throw new RuntimeException('Riwayat kelompok target unit tidak valid.');
        $parts=json_decode($record['payload'],true,512,JSON_THROW_ON_ERROR);
        foreach($rows[$product] as $kind=>$row){
            if(!isset($parts[$kind])||!is_array($parts[$kind])||count($parts[$kind])!==12)throw new RuntimeException('Riwayat target unit tidak lengkap.');
            foreach($parts[$kind] as $m=>$v){if($v!==null){if(!is_numeric($v)||!is_finite((float)$v))throw new RuntimeException('Riwayat target unit bukan angka.');$sheet->setCellValue([$m+3,$row],(float)$v);}}
        }
    }
}

require_once __DIR__.'/unit_sell_out_product_template.php';
function buildUnitSellOutInputTemplate(string $period): Spreadsheet {return buildUnitSellOutProductTemplate($period);}
