<?php
require_once __DIR__.'/../includes/bod_bi_input_template.php';
function oprRawCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
$bytes=buildBodBiInputTemplateOds('BOD BI.Opr & BOD BI.SDM','2025-03');$p=tempnam(sys_get_temp_dir(),'bod-opr-links-');
try{
 file_put_contents($p,$bytes);$zip=new ZipArchive();$zip->open($p);$doc=upbBjXml($zip->getFromName('content.xml'));$tables=upbBjTables($doc);$opr=upbBjCells($tables['BOD BI.Opr']);
 foreach([1,2,3] as $month){$r=$month+2;$ref=bodBiOprRawActualRef($tables[BOD_BI_MONTHS[$month-1]]);oprRawCheck($opr[$r][3]->getAttributeNS(UPB_T,'formula')==='of:='.bodBiRawLookupFormula(BOD_BI_MONTHS[$month-1],$tables[BOD_BI_MONTHS[$month-1]],'SUB TOTAL I').'+'.bodBiRawLookupFormula(BOD_BI_MONTHS[$month-1],$tables[BOD_BI_MONTHS[$month-1]],'SUB TOTAL III'),'Cumulative takes named SUB TOTAL I row in matching month');}
 oprRawCheck($opr[3][2]->getAttributeNS(UPB_T,'formula')==='of:=[.C3]','January RP equals cumulative');oprRawCheck($opr[4][2]->getAttributeNS(UPB_T,'formula')==='of:=[.C4]-[.C3]','February RP is cumulative difference');
 foreach(range(3,16) as $r)foreach([4,5] as $c){$cell=$opr[$r][$c]??null;oprRawCheck(!$cell||(!$cell->hasAttributeNS(UPB_T,'formula')&&!$cell->hasAttributeNS(UPB_O,'value')&&trim($cell->textContent)===''),'2024 is blank');}
 $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($p);foreach([100,180,150] as $i=>$value){$name=BOD_BI_MONTHS[$i];$book->getSheetByName($name)->setCellValue(bodBiRawActualRef($tables[$name],'GAJI, UPAH, DLL'),$value);}
 $book->getSheetByName('JANUARI')->setCellValue('G56',10);$book->getSheetByName('FEBRUARI')->setCellValue('G56',30);$book->getSheetByName('MARET')->setCellValue('G56',50);
 $sheet=$book->getSheetByName('BOD BI.Opr');foreach(['B3'=>110,'C3'=>110,'B4'=>100,'C4'=>210,'B5'=>-10,'C5'=>200,'B16'=>200] as $address=>$value)oprRawCheck((float)$sheet->getCell($address)->getCalculatedValue()===(float)$value,'Live cumulative/RP arithmetic: '.$address.' got '.var_export($sheet->getCell($address)->getCalculatedValue(),true).' formula '.var_export($sheet->getCell($address)->getValue(),true));$book->disconnectWorksheets();
 $salaryTable=$tables['FEBRUARI'];$rows=[];foreach($salaryTable->childNodes as $node)if($node instanceof DOMElement&&$node->localName==='table-row')$rows[]=$node;
 for($i=0;$i<4;$i++){$row=$doc->createElementNS(UPB_T,'table:table-row');$row->appendChild($doc->createElementNS(UPB_T,'table:table-cell'));$salaryTable->insertBefore($row,$rows[0]);}
 $ref=bodBiOprRawActualRef($salaryTable);oprRawCheck($ref==='G44','Subtotal row follows inserted rows');$sdmBefore=$tables['BOD BI.SDM']->C14N();linkBodBiOprRawMonths($doc,$tables['BOD BI.Opr'],'2025-03');$opr=upbBjCells($tables['BOD BI.Opr']);oprRawCheck($opr[4][3]->getAttributeNS(UPB_T,'formula')==='of:='.bodBiRawLookupFormula('FEBRUARI',$salaryTable,'SUB TOTAL I').'+'.bodBiRawLookupFormula('FEBRUARI',$salaryTable,'SUB TOTAL III'),'Generated links follow shifted subtotal row');oprRawCheck($tables['BOD BI.SDM']->C14N()===$sdmBefore,'SDM unaffected');$zip->close();
 echo "PASS: January RP/cumulative, SUB TOTAL I + III links and RP differences, subtotal lookup after row insertion, live calculations, blank 2024, period limits and untouched SDM.\n";
}finally{@unlink($p);}
