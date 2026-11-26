<?php
/** Rebuild a derived summary; source product records remain authoritative. */
function refreshBiayaConclusion(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $period): void {
    $year=(int)substr($period,0,4);$month=(int)substr($period,5,2);
    foreach (['REKAP', 'KESIMPULAN'] as $name) { $old=$book->getSheetByName($name);if($old)$book->removeSheetByIndex($book->getIndex($old)); }
    $sheet=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,'REKAP');$book->addSheet($sheet);
    $sheet->setCellValue('A1','KESIMPULAN BIAYA '.$year.' — Rp (000)');$sheet->mergeCells('A1:Q1');
    $sheet->setCellValue('A2','PERIODE '.$period.' · Existing = EXIS · NP = D · Total mengikuti tabel produk per GB.');$sheet->mergeCells('A2:Q2');
    $months=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGS','SEP','OKT','NOV','DES'];$r=4;
    foreach([$year===2025?'TARGET ROLLING':'TARGET','REALISASI'] as $name){
        $source=$book->getSheetByName($name);if(!$source)throw new InvalidArgumentException('Sheet '.$name.' tidak ditemukan.');
        $sheet->setCellValue('A'.$r,$name);$sheet->mergeCells('A'.$r.':Q'.$r);$sheet->getStyle('A'.$r.':Q'.$r)->getFont()->setBold(true);$r++;
        $sheet->fromArray([['GB','KATEGORI',...$months,'YTD','FULL YEAR','']],null,'A'.$r);$sheet->getStyle('A'.$r.':P'.$r)->getFont()->setBold(true);$sheet->getStyle('A'.$r.':P'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF8FC9F4');$r++;
        foreach(locateBiayaInputTables($source,true) as $layout){
            $gb=$layout['gb'];$first=$layout['first'];$last=$layout['total'];
            $category=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($layout['identity']['category']);
            $prefix="'".str_replace("'","''",$name)."'!";
            // Include the TOTAL row as an empty category boundary. Inserting
            // immediately before it extends SUMIF ranges automatically.
            $criteria=$prefix.'$'.$category.'$'.$first.':$'.$category.'$'.$last;
            $start=$r;
            foreach(['Existing'=>'EXIS','NP'=>'D'] as $label=>$code){
                $sheet->setCellValue('A'.$r,$gb);$sheet->setCellValue('B'.$r,$label);
                foreach($layout['months'] as $m=>$col){
                    $letter=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);$range=$prefix.'$'.$letter.'$'.$first.':$'.$letter.'$'.$last;
                    $sheet->setCellValue([$m+3,$r],'=IF(COUNTIFS('.$criteria.',"'.$code.'",'.$range.',"<>")=0,"",SUMIF('.$criteria.',"'.$code.'",'.$range.'))');
                }
                $r++;
            }
            $sheet->setCellValue('A'.$r,$gb);$sheet->setCellValue('B'.$r,'TOTAL');
            foreach(range('C','N') as $col)$sheet->setCellValue($col.$r,'=IF(COUNT('.$col.$start.':'.$col.($r-1).')=0,"",SUM('.$col.$start.':'.$col.($r-1).'))');
            $sheet->getStyle('A'.$r.':P'.$r)->getFont()->setBold(true);
            for($row=$start;$row<=$r;$row++)foreach(['O'=>\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($month+2),'P'=>'N'] as $col=>$end)$sheet->setCellValue($col.$row,'=IF(COUNT(C'.$row.':'.$end.$row.')=0,"",SUM(C'.$row.':'.$end.$row.'))');
            $r++;
        }
        $r+=2;
    }
    $sheet->getStyle('C4:P'.$r)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);0');
    $sheet->getStyle('A4:P'.$r)->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFE1E5EB');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(17);$sheet->getColumnDimension('A')->setWidth(9);$sheet->getColumnDimension('B')->setWidth(16);
    foreach(range('C','P') as $col)$sheet->getColumnDimension($col)->setWidth(17);
    $sheet->setShowGridlines(false);$sheet->freezePane('C6');$sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
    require_once __DIR__.'/raw_template_protection.php';
    protectRawTemplate($book, $period, 'BIAYA');
}
