<?php
require_once __DIR__.'/template_password.php';
/** Formula ranges include the blank-category subtotal boundary so inserted rows extend them. */
function refreshUnitRevenueRekap(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $period): void
{
    [$year,$month]=unitRevenueTemplatePeriod($period);
    if($old=$book->getSheetByName('REKAP'))$book->removeSheetByIndex($book->getIndex($old));
    $rekap=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,'REKAP');$book->addSheet($rekap);
    $rekap->setCellValue('A1','REKAP UNIT & REVENUE '.$year)->mergeCells('A1:P1');
    $rekap->setCellValue('A2','PERIODE '.$period.' · UNIT dalam unit; REVENUE dalam Rupiah penuh. Existing dan NP mengikuti kategori produk.')->mergeCells('A2:P2');
    $months=array_slice(unitRevenueInputHeaders(),5,12);$r=4;
    foreach(['UNIT','REVENUE'] as $name){
        $source=$book->getSheetByName($name);$layouts=locateUnitRevenueGroupTables($source);
        $rekap->setCellValue('A'.$r,$name)->mergeCells('A'.$r.':P'.$r);$rekap->getStyle('A'.$r.':P'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF345C50');$rekap->getStyle('A'.$r)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$r++;
        $rekap->fromArray([['KELOMPOK','KATEGORI',...$months,'YTD '.$months[$month-1],'FULL YEAR']],null,'A'.$r);$rekap->getStyle('A'.$r.':P'.$r)->getFont()->setBold(true);$rekap->getStyle('A'.$r.':P'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF8FC9F4');$r++;
        $groupTotals=[];
        foreach($layouts as $gb=>$layout){
            $first=$layout['first'];$boundary=$layout['existing']??$layout['total'];$category=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($layout['columns'][4]);
            $prefix=$name."!";$criteria=$prefix.'$'.$category.'$'.$first.':$'.$category.'$'.$boundary;$start=$r;
            $manual=$name==='REVENUE'&&$gb==='ET'?locateUnitRevenueManualTable($source):null;
            foreach(['existing'=>'Existing','new'=>'NP','total'=>'TOTAL'] as $kind=>$label){
                $rekap->setCellValue('A'.$r,$gb==='ET'?'ETHICAL':'GB '.$gb);$rekap->setCellValue('B'.$r,$label);
                foreach($months as $m=>$_){
                    $destination=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($m+3);
                    if($manual){$col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($manual['months'][$m]);$formula=$prefix.$col.$manual['rows'][$kind];}
                    elseif($kind==='total')$formula='SUM('.$destination.$start.':'.$destination.($start+1).')';
                    else{
                        $col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($layout['columns'][$m+5]);$range=$prefix.'$'.$col.'$'.$first.':$'.$col.'$'.$boundary;$terms=[];
                        foreach($kind==='new'?['D','NP']:($gb==='ET'?['A','B','C','ABC','E','KAT E']:['A','B','C','ABC']) as $code)$terms[]='SUMIF('.$criteria.',"'.$code.'",'.$range.')';
                        $formula=implode('+',$terms);
                    }
                    $rekap->setCellValue([$m+3,$r],'='.$formula);
                }
                $end=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($month+2);
                $rekap->setCellValue('O'.$r,'=SUM(C'.$r.':'.$end.$r.')');$rekap->setCellValue('P'.$r,'=SUM(C'.$r.':N'.$r.')');
                if($kind==='total'){$groupTotals[]=$r;$rekap->getStyle('A'.$r.':P'.$r)->getFont()->setBold(true);}
                $r++;
            }
        }
        $rekap->setCellValue('A'.$r,'TOTAL KESELURUHAN');$rekap->mergeCells('A'.$r.':B'.$r);
        foreach(range('C','P') as $col)$rekap->setCellValue($col.$r,'=SUM('.implode(',',array_map(static fn($row)=>$col.$row,$groupTotals)).')');
        $rekap->getStyle('A'.$r.':P'.$r)->getFont()->setBold(true);$r+=3;
    }
    $rekap->getStyle('A1')->getFont()->setBold(true)->setSize(18);$rekap->getStyle('A2')->getAlignment()->setWrapText(true);$rekap->getRowDimension(2)->setRowHeight(32);
    $rekap->getColumnDimension('A')->setWidth(25);$rekap->getColumnDimension('B')->setWidth(15);foreach(range('C','P') as $col)$rekap->getColumnDimension($col)->setWidth(18);
    $rekap->getStyle('C4:P'.$r)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);0');$rekap->getStyle('A4:P'.$r)->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFE1E5EB');
    $rekap->freezePane('C6');$rekap->setShowGridlines(false);$rekap->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
    $rekap->getProtection()->setPassword(rawTemplatePassword())->setSheet(true);
}
