<?php
require_once __DIR__.'/upb_bj_template_history.php';
require_once __DIR__.'/ods_upload_xml.php';
require_once __DIR__.'/../vendor/autoload.php';
/** Preserve the supplied native table, formulas and styles; update period labels only. */
function buildBodMnjInputTemplateOds(string $period,?string $saved=null): string {
 if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$m))throw new InvalidArgumentException('Periode BOD MNJ tidak valid.');
 $year=(int)$m[1];$month=(int)$m[2];$short=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGS','SEP','OKT','NOV','DES'];
 $long=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
 $path=tempnam(sys_get_temp_dir(),'bod-mnj-template-');$zip=new ZipArchive();$opened=false;
 try{
  if($saved===null)copy(__DIR__.'/../dashboard/assets/input-templates/bod-mnj.ods',$path);else file_put_contents($path,$saved);
  $doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);
  $table=reset($tables);$table->setAttributeNS(UPB_T,'table:name','BOD MNJ');
  // The supplied workbook contains a second annual block; raw input is one year only.
  $remove=false;
  foreach(iterator_to_array($table->childNodes) as $row){
   if($row instanceof DOMElement && $row->localName==='table-row'){
    $parts=[];foreach($row->getElementsByTagNameNS(UPB_X,'p') as $p)$parts[]=trim($p->textContent);
    if(preg_match('/SALES\s+MNJ\s+TH\.?\s*'.($saved===null?2025:$year-1).'/i',implode(' ',$parts)))$remove=true;
   }
   if($remove)$table->removeChild($row);
  }
  $xpath=new DOMXPath($doc);$xpath->registerNamespace('text',UPB_X);
  foreach($xpath->query('//text:p//text()') as $node){
   $text=$saved===null?preg_replace_callback('/\b202[56]\b/',fn($match)=>(string)((int)$match[0]===2026?$year:$year-1),$node->nodeValue):$node->nodeValue;
   $text=preg_replace('/S\/D\s+(?:JANUARI|FEBRUARI|MARET|APRIL|MEI|JUNI|JULI|AGUSTUS|SEPTEMBER|OKTOBER|NOVEMBER|DESEMBER)\b/i','S/D '.$long[$month-1],$text);
   $text=preg_replace('/S\/D\s+(?:JAN|FEB|MAR|APR|MAY|JUN|JUL|AGS|AUG|SEP|OKT|OCT|NOV|NOP|DES|DEC)\b/i','S/D '.$short[$month-1],$text);
   $node->nodeValue=$text;
  }
  $styleNs='urn:oasis:names:tc:opendocument:xmlns:style:1.0';
  $numberNs='urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0';
  $auto=$doc->getElementsByTagNameNS(UPB_O,'automatic-styles')->item(0);
  $stylePrefix='BodMnj'.bin2hex(random_bytes(4));
  $percent=$doc->createElementNS($numberNs,'number:percentage-style');
  $percent->setAttributeNS($styleNs,'style:name',$stylePrefix.'Percent2');
  $number=$doc->createElementNS($numberNs,'number:number');
  $number->setAttributeNS($numberNs,'number:decimal-places','2');
  $number->setAttributeNS($numberNs,'number:min-integer-digits','1');
  $percent->appendChild($number);$percent->appendChild($doc->createElementNS($numberNs,'number:text','%'));$auto->appendChild($percent);
  $styles=[];
  foreach($table->getElementsByTagNameNS(UPB_T,'table-row') as $row){
   $isPercent=false;foreach($row->getElementsByTagNameNS(UPB_X,'p') as $p)if(trim($p->textContent)==='%')$isPercent=true;
   if(!$isPercent)continue;
   foreach($row->getElementsByTagNameNS(UPB_T,'table-cell') as $cell){
    if(!$cell->hasAttributeNS(UPB_T,'formula')&&!in_array($cell->getAttributeNS(UPB_O,'value-type'),['float','percentage'],true))continue;
    $parent=$cell->getAttributeNS(UPB_T,'style-name');
    if(!isset($styles[$parent])){
     $name=$stylePrefix.'PercentCell'.count($styles);$styles[$parent]=$name;
     $style=$doc->createElementNS($styleNs,'style:style');$style->setAttributeNS($styleNs,'style:name',$name);
     $style->setAttributeNS($styleNs,'style:family','table-cell');
     if($parent!=='')$style->setAttributeNS($styleNs,'style:parent-style-name',$parent);
     $style->setAttributeNS($styleNs,'style:data-style-name',$stylePrefix.'Percent2');$auto->appendChild($style);
    }
    $cell->setAttributeNS(UPB_T,'table:style-name',$styles[$parent]);
    $value=$cell->getAttributeNS(UPB_O,'value');
    if($value!==''&&is_numeric($value)){
     $cell->setAttributeNS(UPB_O,'office:value-type','percentage');
     foreach($cell->getElementsByTagNameNS(UPB_X,'p') as $p)$p->nodeValue=number_format((float)$value*100,2,'.',',').'%';
    }
   }
  }
  if($zip->open($path)!==true)throw new RuntimeException('Template BOD MNJ tidak dapat dibaca.');$opened=true;
  $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$opened=false;return file_get_contents($path);
 }finally{if($opened)$zip->close();@unlink($path);}
}


function loadBodMnjInputTemplateOds($conn,string $period): string {
 require_once __DIR__.'/bod_mnj_workbook.php';
 $record=findBodMnjRecord($conn,$period);
 if(!$record)return buildBodMnjInputTemplateOds($period);
 $bytes=base64_decode($record['base64'],true);
 if($bytes===false)throw new RuntimeException('Data BOD MNJ tersimpan tidak valid.');
 // Preserve ODS uploads natively; convert older XLSX uploads when needed.
 $path=tempnam(sys_get_temp_dir(),'mnj-format-');
 try{
  file_put_contents($path,$bytes);$zip=new ZipArchive();$ods=false;
  if($zip->open($path)===true){$ods=$zip->getFromName('mimetype')==='application/vnd.oasis.opendocument.spreadsheet';$zip->close();}
  if(!$ods){
   $book=decodeBodMnjWorkbook($record['base64'],(int)substr($period,0,4));
   try{(new \PhpOffice\PhpSpreadsheet\Writer\Ods($book))->save($path);$bytes=file_get_contents($path);}
   finally{$book->disconnectWorksheets();}
  }
 }finally{@unlink($path);}
 return buildBodMnjInputTemplateOds($period,$bytes);
}
