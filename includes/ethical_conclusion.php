<?php
/** Summary is derived from detected product blocks, never from fixed row numbers. */
function refreshEthicalConclusion(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $period): void {
    $year=(int)substr($period,0,4);$month=(int)substr($period,5,2);$source=$book->getSheetByName('PRODUK NAS');
    if(!$source)throw new InvalidArgumentException('Sheet PRODUK NAS tidak ditemukan.');
    $layouts=locateEthicalProductTables($source,$year,true,true);
    foreach (['REKAP', 'KESIMPULAN'] as $name) { $old=$book->getSheetByName($name);if($old)$book->removeSheetByIndex($book->getIndex($old)); }
    $s=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,'REKAP');$book->addSheet($s);
    $s->setCellValue('A1','KESIMPULAN ETHICAL '.$year);$s->mergeCells('A1:O1');$s->setCellValue('A2','PERIODE '.$period.' · UNIT dan HNA (Rupiah penuh) · Nilai bulanan, bukan kumulatif.');$s->mergeCells('A2:O2');
    $labels=ethicalProductLabels($year);$months=unitSellOutInputMonths();$r=4;
    foreach(['unit'=>'TARGET UNIT','hna'=>'TARGET HNA','actual_unit'=>'REAL UNIT','actual_hna'=>'REAL HNA'] as $metric=>$title){
        $s->setCellValue('A'.$r,'RINGKASAN '.$title);$s->mergeCells('A'.$r.':O'.$r);$s->getStyle('A'.$r.':O'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF345C50');$s->getStyle('A'.$r.':O'.$r)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$r++;
        $s->fromArray([['SUBTOTAL PRODUK NAS',...$months,'YTD '.$months[$month-1],'TOTAL FY']],null,'A'.$r);$s->getStyle('A'.$r.':O'.$r)->getFont()->setBold(true);$s->getStyle('A'.$r.':O'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF8FC9F4');$r++;$first=$r;
        foreach($layouts as $kind=>$layout){
            $s->setCellValue('A'.$r,'TOTAL '.$labels[$kind]);
            $range=static function(int $column)use($layout){
                $col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
                // Include the subtotal boundary; blank product-name cells on
                // that row exclude it, and inserted rows extend the range.
                return "'PRODUK NAS'!".$col.$layout['first'].':'.$col.$layout['total'];
            };
            foreach($layout['columns']['months'] as $m=>$columns){
                $values=$range($columns[$metric]);$names=$range($layout['columns']['name']);$formula='SUMIF('.$names.',"<>",'.$values.')';
                if(str_contains($metric,'hna')){
                    $units=$range($columns[$metric==='hna'?'unit':'actual_unit']);$prices=$range($layout['columns']['price']);
                    // Recompute priced rows even when an inserted row has no
                    // HNA formula; retain legacy manually entered HNA otherwise.
                    $formula='SUMPRODUCT('.$prices.','.$units.')+SUMIFS('.$values.','.$prices.',"",'.$names.',"<>")';
                    $count='COUNTIFS('.$names.',"<>",'.$units.',"<>")+COUNTIFS('.$names.',"<>",'.$values.',"<>")';
                }else $count='COUNTIFS('.$names.',"<>",'.$values.',"<>")';
                $s->setCellValue([$m+2,$r],'=IF('.$count.'=0,"",'.$formula.')');
            }
            $r++;
        }
        foreach(['EXISTING','NP','TOTAL ETHICAL'] as $i=>$label){
            $s->setCellValue('A'.$r,$label);$a=$i===0?$first:($i===1?$first+2:$first+4);
            foreach(range('B','M') as $col)$s->setCellValue($col.$r,'=IF(COUNT('.$col.$a.':'.$col.($a+1).')=0,"",SUM('.$col.$a.':'.$col.($a+1).'))');
            $s->getStyle('A'.$r.':O'.$r)->getFont()->setBold(true);$r++;
        }
        for($row=$first;$row<$r;$row++)foreach(['N'=>\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($month+1),'O'=>'M'] as $col=>$end)$s->setCellValue($col.$row,'=IF(COUNT(B'.$row.':'.$end.$row.')=0,"",SUM(B'.$row.':'.$end.$row.'))');
        $r+=2;
    }
    $s->getColumnDimension('A')->setWidth(32);foreach(range('B','O') as $col)$s->getColumnDimension($col)->setWidth(18);
    $s->getStyle('A1')->getFont()->setBold(true)->setSize(18);$s->getStyle('B4:O'.$r)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);0');
    $s->getStyle('A4:O'.$r)->getBorders()->getAllBorders()->setBorderStyle('thin')->getColor()->setARGB('FFDDE4EE');$s->setShowGridlines(false);$s->freezePane('B6');$s->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
    require_once __DIR__.'/raw_template_protection.php';
    protectRawTemplate($book, $period, 'ETHICAL');
}
