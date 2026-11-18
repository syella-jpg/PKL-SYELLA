<?php
require_once __DIR__.'/../includes/bod_bi_input_template.php';
function sdmRawCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
$bytes=buildBodBiInputTemplateOds('BOD BI.Opr & BOD BI.SDM','2025-03');$p=tempnam(sys_get_temp_dir(),'bod-sdm-test-');
try{
 file_put_contents($p,$bytes);$zip=new ZipArchive();$zip->open($p);$doc=upbBjXml($zip->getFromName('content.xml'));$tables=upbBjTables($doc);$sdm=upbBjCells($tables['BOD BI.SDM']);
 foreach([7=>'GAJI, UPAH, DLL',8=>'RECRUITMENT'] as $r=>$item){$jan=bodBiRawActualRef($tables['JANUARI'],$item);$feb=bodBiRawActualRef($tables['FEBRUARI'],$item);
  sdmRawCheck($sdm[$r][4]->getAttributeNS(UPB_T,'formula')==='of:='.bodBiRawLookupFormula('JANUARI',$tables['JANUARI'],$item),'January source item');
  sdmRawCheck($sdm[$r][5]->getAttributeNS(UPB_T,'formula')==='of:='.bodBiRawLookupFormula('FEBRUARI',$tables['FEBRUARI'],$item).'-'.bodBiRawLookupFormula('JANUARI',$tables['JANUARI'],$item),'February item cumulative difference');
  sdmRawCheck(!isset($sdm[$r][7])||!$sdm[$r][7]->hasAttributeNS(UPB_T,'formula'),'Future months stay blank');
 }
 $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($p);
 foreach([[100,180,150],[20,50,90]] as $i=>$values){$item=$i===0?'GAJI, UPAH, DLL':'RECRUITMENT';foreach($values as $m=>$value){$name=BOD_BI_MONTHS[$m];$book->getSheetByName($name)->setCellValue(bodBiRawActualRef($tables[$name],$item),$value);}}
 $sheet=$book->getSheetByName('BOD BI.SDM');foreach(['D7'=>100,'E7'=>80,'F7'=>-30,'B7'=>150,'D8'=>20,'E8'=>30,'F8'=>40,'B8'=>90,'B9'=>240] as $address=>$expected)sdmRawCheck((float)$sheet->getCell($address)->getCalculatedValue()===(float)$expected,'Correct live monthly/YTD values');$book->disconnectWorksheets();
 $source=$tables['FEBRUARI'];$first=null;foreach($source->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$first=$row;break;}
 for($i=0;$i<3;$i++){$row=$doc->createElementNS(UPB_T,'table:table-row');$row->appendChild($doc->createElementNS(UPB_T,'table:table-cell'));$source->insertBefore($row,$first);}
 $opr=$tables['BOD BI.Opr']->C14N();linkBodBiSdmRawMonths($doc,$tables['BOD BI.SDM'],'2025-03');$sdm=upbBjCells($tables['BOD BI.SDM']);sdmRawCheck($sdm[8][5]->getAttributeNS(UPB_T,'formula')==='of:='.bodBiRawLookupFormula('FEBRUARI',$source,'RECRUITMENT').'-'.bodBiRawLookupFormula('JANUARI',$tables['JANUARI'],'RECRUITMENT'),'Recruitment follows shifted source row');sdmRawCheck($opr===$tables['BOD BI.Opr']->C14N(),'OPR unchanged');$zip->close();
 echo "PASS: SDM salary/recruitment source labels, cumulative-to-monthly differences, live YTD/total, shifted rows, future blanks and unchanged OPR.\n";
}finally{@unlink($p);}
