<?php
require_once __DIR__.'/../includes/bod_mnj_input_template.php';
foreach(['2026-10','2027-03'] as $period){
 $bytes=buildBodMnjInputTemplateOds($period);$path=tempnam(sys_get_temp_dir(),'mnj-test-');
 try{
  file_put_contents($path,$bytes);$doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);
  if(!isset($tables['BOD MNJ']))throw new RuntimeException('Missing sheet');
  $xp=new DOMXPath($doc);$xp->registerNamespace('t',UPB_T);
  if(str_contains($doc->textContent,'TH '.((int)substr($period,0,4)-1)))throw new RuntimeException('Prior year leaked into raw template');
  if(!str_contains($doc->textContent,'SALES MNJ  TH '.substr($period,0,4)))throw new RuntimeException('Incorrect year');
  $zip=new ZipArchive();$zip->open($path);$source=new ZipArchive();$source->open(__DIR__.'/../dashboard/assets/input-templates/bod-mnj.ods');
  if($zip->getFromName('styles.xml')!==$source->getFromName('styles.xml'))throw new RuntimeException('Styles changed');
  if(!preg_match('/style:data-style-name="BodMnj[a-f0-9]+Percent2"/',$doc->saveXML()))throw new RuntimeException('Percent row format missing');
  $sourceDoc=upbBjXml($source->getFromName('content.xml'));
  $sourceTables=upbBjTables($sourceDoc);$sourceTable=reset($sourceTables);$remove=false;
  foreach(iterator_to_array($sourceTable->childNodes) as $row){
   $parts=[];if($row instanceof DOMElement)foreach($row->getElementsByTagNameNS(UPB_X,'p') as $p)$parts[]=trim($p->textContent);
   if(preg_match('/SALES\s+MNJ\s+TH\.?\s*2025/i',implode(' ',$parts)))$remove=true;
   if($remove)$sourceTable->removeChild($row);
  }
  $sourceXp=new DOMXPath($sourceDoc);$sourceXp->registerNamespace('t',UPB_T);
  $formulas=static fn($nodes)=>array_map(static fn($node)=>$node->nodeValue,iterator_to_array($nodes));
  if($formulas($xp->query('//@t:formula'))!==$formulas($sourceXp->query('//@t:formula')))throw new RuntimeException('Source formulas changed');
  $label=$period==='2026-10'?'S/D OKTOBER':'S/D MARET';
  if(!str_contains($doc->textContent,$label))throw new RuntimeException('Incorrect month');
  $zip->close();$source->close();
 }finally{unlink($path);}
}
echo "PASS: native structure/styles, selected-year formulas unchanged; prior-year block excluded, dynamic year and month labels.\n";

// Uploaded values must survive same-period and subsequent-period downloads.
$uploaded=buildBodMnjInputTemplateOds('2026-10');$path=tempnam(sys_get_temp_dir(),'mnj-history-');
try{
 file_put_contents($path,$uploaded);$zip=new ZipArchive();$zip->open($path);$doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);$table=reset($tables);
 $row=$table->getElementsByTagNameNS(UPB_T,'table-row')->item(4);$cell=upbBjColumnCell($row,4);
 $cell->setAttributeNS(UPB_O,'office:value-type','float');$cell->setAttributeNS(UPB_O,'office:value','987654');
 $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$saved=file_get_contents($path);
 foreach(['2026-10','2026-11'] as $period){
  $result=buildBodMnjInputTemplateOds($period,$saved);file_put_contents($path,$result);$doc=loadOdsUploadDocument($path);
  if(!str_contains($doc->saveXML(),'office:value="987654"'))throw new RuntimeException('Uploaded value lost');
  if(!str_contains($doc->textContent,$period==='2026-10'?'S/D OKTOBER':'S/D NOVEMBER'))throw new RuntimeException('Incorrect continued period');
 }
}finally{@unlink($path);}
echo "PASS: uploaded values retained in October and November downloads.\n";
