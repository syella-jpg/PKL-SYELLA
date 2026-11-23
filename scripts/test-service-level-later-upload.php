<?php
require __DIR__.'/../includes/service_level_input_store.php';
function serviceSyntheticUpload(int $year): string {
 $doc=upbBjXml('<office:document-content xmlns:office="'.UPB_O.'" xmlns:table="'.UPB_T.'" xmlns:text="'.UPB_X.'" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"><office:automatic-styles/><office:body><office:spreadsheet><table:table table:name="SERVICE LEVEL"/></office:spreadsheet></office:body></office:document-content>');
 $tables=upbBjTables($doc);$table=reset($tables);
 $put=static function(array $values)use($doc,$table){
  $row=$doc->createElementNS(UPB_T,'table:table-row');$table->appendChild($row);
  foreach($values as $v){
   $cell=$doc->createElementNS(UPB_T,'table:table-cell');$row->appendChild($cell);
   if(is_numeric($v)){$cell->setAttributeNS(UPB_O,'office:value',(string)$v);$cell->setAttributeNS(UPB_O,'office:value-type','float');}
   elseif($v!==null)$cell->appendChild($doc->createElementNS(UPB_X,'text:p',$v));
  }
 };
 $put(['TH. '.$year]);
 foreach(['Eksternal (MNJ to Outlet) – UNIT','Internal (KX to MNJ) – UNIT'] as $heading){
  $put(['SERVICE LEVEL']);$put([$heading,null,'JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC']);
  $put(['GB 1',null,...range(1,12)]);
  $put(['TOTAL',null,...range(1,12)]);
 }
 $path=tempnam('/tmp','service-synthetic-');$zip=new ZipArchive();
 try{
  $zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('mimetype','application/vnd.oasis.opendocument.spreadsheet');
  $zip->addFromString('content.xml',$doc->saveXML());
  $zip->addFromString('styles.xml','<office:document-styles xmlns:office="'.UPB_O.'"><office:styles/><office:automatic-styles/></office:document-styles>');
  $zip->close();return file_get_contents($path);
 }finally{unlink($path);}
}
$uploaded=serviceSyntheticUpload(2026);$prior=serviceSyntheticUpload(2025);
foreach(['2026-09'=>9,'2026-10'=>10] as $period=>$last){
 $output=buildServiceLevelReportOds($period,$uploaded,$prior);$path=tempnam('/tmp','service-export-synthetic-');
 try{
  file_put_contents($path,$output);$doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);$rows=upbBjCells(reset($tables));$year=null;$groups=0;
  foreach($rows as $cells){
   if(preg_match('/^TH\. (20\d{2})$/',$cells[1]->textContent??'',$m))$year=(int)$m[1];
   if(($cells[1]->textContent??'')!=='GB 1')continue;$groups++;
   foreach(range(1,12) as $month){
    $cell=$cells[$month+2]??null;$value=$cell?->getAttributeNS(UPB_O,'value');
    if($year===2026&&$month>$last){if($value!==null&&$value!=='')throw new RuntimeException('Future month leaked');}
    elseif((float)$value!==(float)$month)throw new RuntimeException('Available month lost');
   }
  }
  if($groups!==4)throw new RuntimeException('Current/prior external/internal tables missing');
 }finally{unlink($path);}
}
echo "PASS synthetic ODS: September includes Jan-Sep from later upload, hides Oct-Dec, October retains its values, prior-year tables preserved.\n";
