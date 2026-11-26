<?php
/** Editing rules use the selected report period, never the current date. */
function protectRawTemplate(\PhpOffice\PhpSpreadsheet\Spreadsheet $book, string $period, string $section = ''): void
{
    if (!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $period)) throw new InvalidArgumentException('Periode template tidak valid.');
    $january = substr($period, 5, 2) === '01';
    $selectedMonth = (int)substr($period,5,2);
    $targetOnly = in_array($section, ['TARGET REVENUE','TARGET UNIT SELL OUT','REVENUE VERSI EDP MNJ Target'], true);
    foreach ($book->getAllSheets() as $sheet) {
        $name = strtoupper(trim($sheet->getTitle()));
        $summary = in_array($name, ['REKAP','KESIMPULAN'], true);
        $target = ($targetOnly || str_starts_with($name, 'TARGET') || ($section==='BOD KX Sell In' && $name==='ROD')) && $name !== '_TEMPLATE';
        if ($summary || $target) {
            $sheet->getStyle($sheet->calculateWorksheetDimension())->getProtection()->setLocked('protected');
            $sheet->getProtection()->setPassword(bin2hex(random_bytes(16)))->setSheet($summary || !$january);
        }
    }
    if (in_array($section, ['UNIT & Revenue','REVENUE VERSI EDP MNJ','BOD KX Sell In','MNJ','MTM','IB'], true)) {
        $aliases=[['JAN','JANUARI'],['FEB','FEBRUARI'],['MAR','MARET'],['APR','APRIL'],['MEI','MAY'],['JUN','JUNI'],['JUL','JULI'],['AGS','AGT','AGU','AUG','AGUSTUS'],['SEP','SEPT','SEPTEMBER'],['OKT','OCT','OKTOBER'],['NOV','NOP','NOVEMBER'],['DES','DEC','DESEMBER']];
        foreach($book->getAllSheets() as $sheet){
            if(in_array(strtoupper($sheet->getTitle()),['_TEMPLATE','REKAP','KESIMPULAN'],true)||str_starts_with(strtoupper($sheet->getTitle()),'TARGET')||($section==='BOD KX Sell In'&&$sheet->getTitle()==='ROD'))continue;
            $months=[];
            foreach($sheet->getCellCollection()->getCoordinates() as $address){$cell=$sheet->getCell($address);if($cell->isFormula()||!is_string($cell->getValue()))continue;
                $label=strtoupper(trim($cell->getValue()));$label=preg_replace('/^(?:BULAN |S\/D )| 20\d{2}$/','',$label);
                foreach($aliases as $month=>$names)if(in_array($label,$names,true))$months[$cell->getColumn()]=$month;
            }
            foreach($sheet->getMergeCells() as $range){[$first,$last]=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($range);$column=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($first[0]);if(isset($months[$column]))for($c=$first[0]+1;$c<=$last[0];$c++)$months[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c)]=$months[$column];}
            if(count(array_unique($months))<2)continue;
            $sheet->getStyle($sheet->calculateWorksheetDimension())->getProtection()->setLocked('unprotected');
            foreach($months as $column=>$month)if($month<$selectedMonth-1)$sheet->getStyle($column.'1:'.$column.$sheet->getHighestRow())->getProtection()->setLocked('protected');
            if(!$january)foreach($sheet->getCellCollection()->getCoordinates() as $address){$cell=$sheet->getCell($address);if(!$cell->isFormula()&&is_string($cell->getValue())&&preg_match('/^(?:TARGET(?: |$)|T\.\s*(?:AWAL|ROLLING))/i',trim($cell->getValue()))){$r=$cell->getRow();$sheet->getStyle('A'.$r.':'.$sheet->getHighestColumn().$r)->getProtection()->setLocked('protected');}}
            $sheet->getProtection()->setPassword(bin2hex(random_bytes(16)))->setSheet(!$january);
        }
    }
    if ($section === 'BIAYA' && ($actual = $book->getSheetByName('REALISASI'))) {
        $actual->getStyle($actual->calculateWorksheetDimension())->getProtection()->setLocked('unprotected');
        foreach (locateBiayaInputTables($actual,true) as $layout) {
            foreach ($layout['months'] ?? [] as $month=>$column) {
                if ($month >= $selectedMonth-1) continue;
                $letter=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
                $actual->getStyle($letter.$layout['first'].':'.$letter.$layout['last'])->getProtection()->setLocked('protected');
            }
        }
        $actual->setCellValue('A4', rawActualLockNote());
        $actual->getProtection()->setPassword(bin2hex(random_bytes(16)))->setSheet(!$january);
    }
    if ($section === 'ETHICAL' && !$january && ($sheet = $book->getSheetByName('PRODUK NAS'))) {
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getProtection()->setLocked('unprotected');
        foreach (locateEthicalProductTables($sheet, (int)substr($period,0,4), true, true) as $layout) {
            // Price is shared by target and realization; changes would also change target HNA.
            $columns = [$layout['columns']['price']];
            foreach ($layout['columns']['months'] as $metrics) foreach (['unit','hna'] as $metric) $columns[] = $metrics[$metric];
            foreach ($layout['columns']['months'] as $month=>$metrics) if($month<$selectedMonth-1) foreach(['actual_unit','actual_hna'] as $metric)$columns[]=$metrics[$metric];
            foreach (array_unique($columns) as $column) {
                $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
                $sheet->getStyle($letter.'1:'.$letter.$sheet->getHighestRow())->getProtection()->setLocked('protected');
            }
        }
        $sheet->getProtection()->setPassword(bin2hex(random_bytes(16)))->setSheet(true);
        $sheet->setCellValue('A4', rawActualLockNote());
        $sheet->getProtection()->setInsertRows(false)->setDeleteRows(false);
    }
}
function rawActualLockNote(): string {
    return 'Realisasi bulan sebelumnya dikunci. Koreksi: hubungi pihak CSPPM untuk membuka periode, lalu unduh dan upload template periode yang dikoreksi. Perubahan bulan lain ditolak sistem.';
}
function protectRawRekapOds(DOMElement $sheet): void
{
    $sheet->setAttributeNS(UPB_T, 'table:protected', 'true');
    $sheet->setAttributeNS(UPB_T, 'table:protection-key', base64_encode(sha1(random_bytes(32), true)));
    $sheet->setAttributeNS(UPB_T, 'table:protection-key-digest-algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
}

/** Separate first sheet keeps product row addresses and existing formulas intact. */
function rawTemplateInstructions(): array {
    return [
        'PERHATIAN',
        'Penambahan/pengurangan baris: Lakukan pada baris data produk, sebelum baris subtotal/total. Jangan menghapus atau mengubah judul kolom, nama sheet, dan baris subtotal/total agar data tetap terbaca dengan benar.',
        'Sheet TARGET: Hanya dapat diedit pada template periode Januari.',
        'Sheet REKAP: Terisi otomatis dan dikunci agar rumusnya tetap terjaga.',
        'Realisasi bulan sebelumnya: Dikunci. Untuk melakukan koreksi, hubungi pihak CSPPM agar periode tersebut dibuka, lalu unduh dan unggah kembali template untuk periode yang ingin diperbaiki.',
    ];
}
function addRawTemplateInstructions(\PhpOffice\PhpSpreadsheet\Spreadsheet $book): void {
    $sheet=$book->getSheetByName('PETUNJUK');
    if($sheet)$book->removeSheetByIndex($book->getIndex($sheet));
    $sheet=new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($book,'PETUNJUK');
    $book->addSheet($sheet,0);
    foreach(rawTemplateInstructions() as $i=>$text){$row=$i+1;$sheet->setCellValue('A'.$row,$text);$sheet->getRowDimension($row)->setRowHeight($i===0?30:64);}
    $sheet->getColumnDimension('A')->setWidth(110);
    $sheet->getStyle('A1:A5')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFC00000'))->setSize(12);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1:A5')->getAlignment()->setWrapText(true)->setVertical('center');
    $sheet->getProtection()->setPassword(bin2hex(random_bytes(16)))->setSheet(true);
    $book->setActiveSheetIndex(0);
}
function addRawNativeInstructions(DOMDocument $doc,DOMElement $auto): void {
    $styleNs='urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    $fo='urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0';
    foreach(['RawInstructionsCell','RawInstructionsColumn','RawInstructionsRow'] as $name){
        $style=$doc->createElementNS($styleNs,'style:style');$style->setAttributeNS($styleNs,'style:name',$name);
        $family=$name==='RawInstructionsCell'?'table-cell':($name==='RawInstructionsColumn'?'table-column':'table-row');
        $style->setAttributeNS($styleNs,'style:family',$family);
        if($family==='table-cell'){
            $font=$doc->createElementNS($styleNs,'style:text-properties');$font->setAttributeNS($fo,'fo:color','#C00000');$font->setAttributeNS($fo,'fo:font-size','12pt');$style->appendChild($font);
            $props=$doc->createElementNS($styleNs,'style:table-cell-properties');$props->setAttributeNS($fo,'fo:wrap-option','wrap');$props->setAttributeNS($styleNs,'style:cell-protect','protected');
        }elseif($family==='table-column'){$props=$doc->createElementNS($styleNs,'style:table-column-properties');$props->setAttributeNS($styleNs,'style:column-width','24cm');}
        else{$props=$doc->createElementNS($styleNs,'style:table-row-properties');$props->setAttributeNS($styleNs,'style:min-row-height','1.8cm');}
        $style->appendChild($props);$auto->appendChild($style);
    }
    $tables=upbBjTables($doc);$first=reset($tables);if(!$first)return;
    if(isset($tables['PETUNJUK']))$tables['PETUNJUK']->parentNode->removeChild($tables['PETUNJUK']);
    $table=$doc->createElementNS(UPB_T,'table:table');$table->setAttributeNS(UPB_T,'table:name','PETUNJUK');
    $col=$doc->createElementNS(UPB_T,'table:table-column');$col->setAttributeNS(UPB_T,'table:style-name','RawInstructionsColumn');$table->appendChild($col);
    foreach(rawTemplateInstructions() as $text){
        $row=$doc->createElementNS(UPB_T,'table:table-row');$row->setAttributeNS(UPB_T,'table:style-name','RawInstructionsRow');
        $cell=$doc->createElementNS(UPB_T,'table:table-cell');$cell->setAttributeNS(UPB_T,'table:style-name','RawInstructionsCell');$cell->setAttributeNS(UPB_O,'office:value-type','string');
        $p=$doc->createElementNS(UPB_X,'text:p');$p->appendChild($doc->createTextNode($text));$cell->appendChild($p);$row->appendChild($cell);$table->appendChild($row);
    }
    protectRawRekapOds($table);
    $parent=$first->parentNode;if(!$parent){$remaining=upbBjTables($doc);$first=reset($remaining);$parent=$first->parentNode;}
    $parent->insertBefore($table,$first);
}
