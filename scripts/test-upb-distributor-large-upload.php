<?php
require_once __DIR__.'/../includes/upb_distr_input_store.php';
function largeDistrCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
// Saved Calc files can contain thousands of physical formatted rows instead of
// ODS repeated rows. That shape previously made formula scans exceed 120 s.
$bytes=buildUpbDistrInputTemplateOds('2025-01');$path=tempnam(sys_get_temp_dir(),'distr-large-');$zip=new ZipArchive();
try{
 file_put_contents($path,$bytes);$zip->open($path);$doc=upbBjXml($zip->getFromName('content.xml'));$table=upbBjTables($doc)['JANUARI'];
 foreach(iterator_to_array($table->childNodes) as $row)if($row instanceof DOMElement&&$row->localName==='table-row'&&$row->hasAttributeNS(UPB_T,'number-rows-repeated')){
  $row->removeAttributeNS(UPB_T,'number-rows-repeated');for($i=1;$i<1100;$i++)$table->insertBefore($row->cloneNode(true),$row);
 }
 $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$source=file_get_contents($path);unset($doc,$table);
 $start=microtime(true);$compacted=prepareUpbDistrInputOds($source,'2025-01');$result=buildUpbDistrInputTemplateOds('2025-02',$compacted);validateUpbDistrInput($result,'2025-02','UPB DISTR');$elapsed=microtime(true)-$start;
 $tables=upbBjTables(distrReadOds($result));largeDistrCheck(array_keys($tables)===['JANUARI','FEBRUARI','KESIMPULAN'],'Both months and conclusion retained');
 foreach(['JANUARI','FEBRUARI'] as $name){$info=distrMonthInfo($tables[$name]);largeDistrCheck(array_keys($info)===DISTR_GROUPS,'All GB/group labels and matching subtotals retained');foreach($info as $part)largeDistrCheck($part['last']-$part['start']+1===2500,'Unfilled months retain 2500 product rows per group');}
 largeDistrCheck($elapsed<8,'Large-upload generation exceeded regression time limit');
 echo 'PASS: expanded formatted rows compact and download with all groups in '.round($elapsed,3)."s.\n";
}finally{if($zip->status===ZipArchive::ER_OK){try{$zip->close();}catch(Throwable $e){}}@unlink($path);}
