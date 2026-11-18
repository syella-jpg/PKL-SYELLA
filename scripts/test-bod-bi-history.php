<?php
require_once __DIR__.'/../includes/bod_bi_input_store.php';
function bodHistoryCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
$bytes=buildBodBiInputTemplateOds(BOD_BI_INPUT_SECTION,'2098-01');$path=tempnam(sys_get_temp_dir(),'bod-history-test-');
try{
 file_put_contents($path,$bytes);$zip=new ZipArchive();$zip->open($path);$doc=loadOdsUploadDocument($path);$table=upbBjTables($doc)['JANUARI'];$rows=[];$r=1;foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$rows[$r]=$row;$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
 $cell=upbBjColumnCell($rows[10],7);upbBjSetFormula($cell,'0',120);$cell->removeAttributeNS(UPB_T,'formula');upbBjSetFormula(upbBjColumnCell($rows[40],7),'SUM([.G10:.G39])*2',240);
 $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$saved=file_get_contents($path);validateBodBiInputBytes($saved,'2098-01');
 foreach(['2098-01','2098-03'] as $period){$next=buildBodBiStoredInputTemplateOds($period,$saved);validateBodBiInputBytes($next,$period);file_put_contents($path,$next);$tables=upbBjTables(loadOdsUploadDocument($path));$jan=upbBjCells($tables['JANUARI']);bodHistoryCheck((float)$jan[10][7]->getAttributeNS(UPB_O,'value')===120.0,'Saved January data retained');$opr=upbBjCells($tables['BOD BI.Opr']);$sdm=upbBjCells($tables['BOD BI.SDM']);bodHistoryCheck((float)$opr[3][2]->getAttributeNS(UPB_O,'value')===240.0,'January OPR visible without recalculation');bodHistoryCheck((float)$opr[3][3]->getAttributeNS(UPB_O,'value')===240.0,'January cumulative cache retained');bodHistoryCheck((float)$sdm[7][4]->getAttributeNS(UPB_O,'value')===120.0,'January salary visible without recalculation');
 if($period==='2098-03')foreach(['FEBRUARI','MARET'] as $name){$cells=upbBjCells($tables[$name]);bodHistoryCheck(!isset($cells[10][7])||!$cells[10][7]->hasAttributeNS(UPB_O,'value'),'New month inputs blank');bodHistoryCheck(str_ends_with($cells[40][7]->getAttributeNS(UPB_T,'formula'),'*2'),'New month retains modified raw formulas');}
 }
 $rejected=false;try{validateBodBiInputBytes($saved,'2097-01');}catch(InvalidArgumentException $e){$rejected=true;}bodHistoryCheck($rejected,'Wrong year rejected');
 file_put_contents('/tmp/bod-bi-db-test-fixture.ods',$saved);chmod('/tmp/bod-bi-db-test-fixture.ods',0600);
 echo "PASS: validated upload, saved monthly data, later blank months, formula inheritance, no previous-year borrowing and period validation.\n";
}finally{@unlink($path);}
