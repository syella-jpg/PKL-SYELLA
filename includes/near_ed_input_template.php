<?php
require_once __DIR__.'/template_password.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
function nearEdGroups(): array {return ['GB 1','GB 2','GB 3','GB 4','GB 5','GB 6','ETHICAL'];}
function nearEdBuckets(): array {return [['Stok <= 4 bln','J','K'],['Stok 5-6 bln','M','N'],['Stok 7-8 bln','P','Q'],['Stok >= 9 bln','R','S']];}
/** Include the blank total-row boundary; sheet edits before TOTAL extend all references. */
function nearEdRange(string $name,string $column,int $total): string {return "'".$name."'!\$".$column.'$6:$'.$column.'$'.$total;}
function nearEdStockRatio(string $numerator,string $denominator): string {
    $ratio=$numerator.'/'.$denominator;
    return '=IFERROR(IF('.$ratio.'=0," - ",'.$ratio.')," - ")';
}
function nearEdBorders(Worksheet $s,string $range): void {
    $s->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFB8C4CC');
}
function nearEdHeader(Worksheet $s,string $range): void {
    $s->getStyle($range)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFDCE5EB');
    $s->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FF253746');
    $s->getStyle($range)->getAlignment()->setWrapText(true)->setVertical('center');
    nearEdBorders($s,$range);
}
function buildNearEdInputTemplate(string $period,int $capacity=200): Spreadsheet {
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$parts))throw new InvalidArgumentException('Periode NEAR ED tidak valid.');
    $capacity=max(1,$capacity);$total=6+$capacity;
    $b=new Spreadsheet();$b->removeSheetByIndex(0);$b->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
    foreach(nearEdGroups() as $group){
        $s=new Worksheet($b,$group);$b->addSheet($s);$s->setShowGridlines(false);
        $s->setCellValue('A1','RAW DATA NEAR ED — '.$group.' — '.$period)->mergeCells('A1:S1');
        $s->setCellValue('A2','Isi sel biru muda untuk kelompok ini. Unit dan Rupiah setiap rentang stok diisi sesuai data sumber; Rupiah penuh.')->mergeCells('A2:S2');
        $s->setCellValue('A3','Tambah/hapus baris produk sebelum TOTAL. Pertahankan judul kolom dan baris TOTAL. TOP 10 diurutkan otomatis per rentang berdasarkan Rupiah terbesar.')->mergeCells('A3:S3');
        foreach(['A'=>'Kode Produk','B'=>'Nama Produk','C'=>'Kode Produk mnrt Principal','D'=>'GB','E'=>'Rata-rata Sales (unit)','F'=>'Total Stok di KX & MNJ (unit)','G'=>'Perkiraan Lama Stok Habis (bulan)','H'=>'Ketr','I'=>'Total Stok (Rupiah)'] as $c=>$text){$s->setCellValue($c.'4',$text)->mergeCells($c.'4:'.$c.'5');}
        foreach(nearEdBuckets() as [$label,$units,$rupiah]){$end=$units==='J'?'L':($units==='M'?'O':$rupiah);$s->setCellValue($units.'4',$label)->mergeCells($units.'4:'.$end.'4');$s->setCellValue($units.'5','Unit');$s->setCellValue($rupiah.'5','Rupiah');}
        $s->setCellValue('L5','% Stok <= 4 bln vs Total Stok');$s->setCellValue('O5','% Stok 5-6 bln vs Total Stok');
        $s->getStyle('A6:S'.($total-1))->getProtection()->setLocked('unprotected');
        foreach(range(6,$total-1) as $r){
            $s->setCellValue('D'.$r,$group);
            $s->setCellValue('G'.$r,'=IFERROR(IF(ISNUMBER(E'.$r.'),F'.$r.'/E'.$r.'," - ")," - ")');
            $s->setCellValue('H'.$r,'=IF(ISNUMBER(G'.$r.'),IF(G'.$r.'>12,"lebih dari setahun",IF(G'.$r.'>6,"lebih dari 6 bulan","")),"")');
            foreach(['L'=>'J','O'=>'M'] as $ratio=>$units)$s->setCellValue($ratio.$r,nearEdStockRatio($units.$r,'F'.$r));
        }
        foreach(['A:C','E:F','I:K','M:N','P:S'] as $columns){[$first,$last]=explode(':',$columns);$s->getStyle($first.'6:'.$last.($total-1))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFF0F5F8');}
        foreach(['D','G','H','L','O'] as $col)$s->getStyle($col.'6:'.$col.($total-1))->getProtection()->setLocked('protected');
        $s->setCellValue('A'.$total,'TOTAL');
        foreach(['F','I','J','K','M','N','P','Q','R','S'] as $col)$s->setCellValue($col.$total,'=IF(ROW()<=6,0,SUM(INDIRECT("'.$col.'6:'.$col.'"&(ROW()-1))))');
        foreach(['L'=>'J','O'=>'M'] as $col=>$units)$s->setCellValue($col.$total,nearEdStockRatio($units.$total,'F'.$total));
        nearEdBorders($s,'A4:S'.$total);
        foreach(['D','G','H','L','O'] as $col)$s->getStyle($col.'6:'.$col.($total-1))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFF2F3F4');
        $s->getStyle('A6:S'.$total)->getAlignment()->setVertical('center');
        $s->getDefaultRowDimension()->setRowHeight(22);
        nearEdHeader($s,'A4:S5');nearEdHeader($s,'A'.$total.':S'.$total);
        $s->getStyle('E6:S'.$total)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);0');foreach(['L','O'] as $c)$s->getStyle($c.'6:'.$c.$total)->getNumberFormat()->setFormatCode('0%');
        $s->getStyle('G6:G'.$total)->getNumberFormat()->setFormatCode('0');
        $s->getColumnDimension('A')->setWidth(18);$s->getColumnDimension('B')->setWidth(38);$s->getColumnDimension('C')->setWidth(24);$s->getColumnDimension('D')->setWidth(12);foreach(range('E','S') as $c)$s->getColumnDimension($c)->setWidth($c==='H'?26:19);
        $s->getRowDimension(1)->setRowHeight(30);$s->getRowDimension(2)->setRowHeight(32);$s->getRowDimension(3)->setRowHeight(32);$s->getRowDimension(4)->setRowHeight(40);$s->getRowDimension(5)->setRowHeight(35);$s->getStyle('A1:S3')->getAlignment()->setWrapText(true);
        $s->freezePane('E6');$s->setAutoFilter('A5:S'.($total-1));$s->getProtection()->setPassword(rawTemplatePassword())->setSheet(true)->setInsertRows(false)->setDeleteRows(false)->setAutoFilter(false);
        $s->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
    }
    $summary=new Worksheet($b,'NEAR ED');$b->addSheet($summary,0);$summary->setShowGridlines(false);
    $summary->setCellValue('A1','TOTAL STOK FG KX & MNJ — NEAR ED — '.$period)->mergeCells('A1:F1');
    $summary->fromArray([['GB','KATEGORI','UNIT','% UNIT','Rupiah','% RUPIAH']],null,'A4');nearEdHeader($summary,'A4:F4');$r=5;$grandUnits=[];$grandRp=[];
    foreach(nearEdGroups() as $group){nearEdBorders($summary,'A'.$r.':F'.($r+4));$fullUnits="'".$group."'!F".$total;$fullRp="'".$group."'!I".$total;
        foreach(nearEdBuckets() as [$label,$unit,$rp]){$summary->setCellValue('A'.$r,$group);$summary->setCellValue('B'.$r,$label);
            // The TOTAL boundary is included for flexible inserts; subtract its calculated total once.
            $summary->setCellValue('C'.$r,'=SUM('.nearEdRange($group,$unit,$total).')-\''.$group.'\'!'.$unit.$total);
            $summary->setCellValue('E'.$r,'=SUM('.nearEdRange($group,$rp,$total).')-\''.$group.'\'!'.$rp.$total);
            $summary->setCellValue('D'.$r,nearEdStockRatio('C'.$r,$fullUnits));$summary->setCellValue('F'.$r,nearEdStockRatio('E'.$r,$fullRp));$r++;}
        $summary->setCellValue('A'.$r,$group);$summary->setCellValue('B'.$r,'TOTAL');$summary->setCellValue('C'.$r,'='.$fullUnits);$summary->setCellValue('E'.$r,'='.$fullRp);$summary->setCellValue('D'.$r,nearEdStockRatio('C'.$r,'C'.$r));$summary->setCellValue('F'.$r,nearEdStockRatio('E'.$r,'E'.$r));nearEdHeader($summary,'A'.$r.':F'.$r);$grandUnits[]='C'.$r;$grandRp[]='E'.$r;$r+=2;
    }
    $summary->setCellValue('B'.$r,'TOTAL SELURUH KELOMPOK');$summary->setCellValue('C'.$r,'=SUM('.implode(',',$grandUnits).')');$summary->setCellValue('E'.$r,'=SUM('.implode(',',$grandRp).')');nearEdHeader($summary,'A'.$r.':F'.$r);
    foreach(['A'=>15,'B'=>30,'C'=>22,'D'=>16,'E'=>25,'F'=>16] as $c=>$width)$summary->getColumnDimension($c)->setWidth($width);$summary->getStyle('C5:E'.$r)->getNumberFormat()->setFormatCode('#,##0.00');foreach(['D','F'] as $c)$summary->getStyle($c.'5:'.$c.$r)->getNumberFormat()->setFormatCode('0%');$summary->freezePane('C5');
    $top=new Worksheet($b,'TOP 10 NEAR ED');$b->addSheet($top,1);$top->setShowGridlines(false);$top->setCellValue('A1','TOP 10 NEAR ED — RUPIAH TERBESAR — '.$period)->mergeCells('A1:E1');$top->setCellValue('A2','Masing-masing kelompok dan rentang stok diurutkan terpisah. Nilai sama mengikuti urutan baris input; produk tanpa nama/nilai Rupiah positif tidak ditampilkan.')->mergeCells('A2:E2');$top->getStyle('A2:E2')->getAlignment()->setWrapText(true);$top->getRowDimension(2)->setRowHeight(38);$r=4;
    foreach(nearEdGroups() as $group){$top->setCellValue('A'.$r,$group)->mergeCells('A'.$r.':E'.$r);nearEdHeader($top,'A'.$r.':E'.$r);$top->setCellValue('A'.($r+1),'No.');$names=nearEdRange($group,'B',$total);$first=$r+2;
        foreach(nearEdBuckets() as $bucket=>[$label,$unit,$rp]){$visible=Coordinate::stringFromColumnIndex($bucket+2);$valueCol=Coordinate::stringFromColumnIndex($bucket+6);$rowCol=Coordinate::stringFromColumnIndex($bucket+10);$top->setCellValue($visible.($r+1),$label);$values=nearEdRange($group,$rp,$total);
            for($rank=1;$rank<=10;$rank++){$row=$first+$rank-1;$value=$valueCol.$row;$position=$rowCol.$row;$top->setCellValue('A'.$row,$rank);
                $top->setCellValue($value,'=IFERROR(_xlfn.AGGREGATE(14,6,'.$values.'/(('.$names.'<>"")*('.$values.'>0)),'.$rank.'),"")');
                $top->setCellValue($position,'=IFERROR(IF('.$value.'="","",_xlfn.AGGREGATE(15,6,(ROW('.$names.')-5)/(('.$values.'='.$value.')*('.$names.'<>"")),COUNTIF('.$valueCol.'$'.$first.':'.$value.','.$value.'))),"")');
                $top->setCellValue($visible.$row,'=IFERROR(IF('.$position.'="","",INDEX('.$names.','.$position.')) ,"")');
            }
        }nearEdHeader($top,'A'.($r+1).':E'.($r+1));$top->getStyle('B'.$first.':E'.($first+9))->getAlignment()->setWrapText(true);nearEdBorders($top,'A'.$first.':E'.($first+9));for($body=$first;$body<$first+10;$body++){$top->getRowDimension($body)->setRowHeight(32);if(($body-$first)%2===0)$top->getStyle('A'.$body.':E'.$body)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFF4F7F9');}$r+=15;
    }
    $top->getColumnDimension('A')->setWidth(8);foreach(range('B','E') as $c)$top->getColumnDimension($c)->setWidth(34);foreach(range('F','M') as $c)$top->getColumnDimension($c)->setVisible(false);$top->freezePane('B6');
    foreach([$summary,$top] as $s){$s->getDefaultRowDimension()->setRowHeight(24);$s->getRowDimension(1)->setRowHeight(32);$s->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF253746');$s->getProtection()->setPassword(rawTemplatePassword())->setSheet(true);$s->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);}
    $meta=new Worksheet($b,'_TEMPLATE');$b->addSheet($meta);$meta->fromArray([['KONIMEX_NEAR_ED_V1'],['NEAR ED'],[(int)$parts[1]],[$period]]);$meta->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);$b->setActiveSheetIndex(0);return $b;
}
