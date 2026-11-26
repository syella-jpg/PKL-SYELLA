<?php
require_once __DIR__.'/bod_bi_input_template.php';
require_once __DIR__.'/ods_upload_xml.php';
require_once __DIR__.'/bod_bi_raw_report_sheets.php';
const BOD_BI_INPUT_SECTION='BOD BI.Opr & BOD BI.SDM';
function bodBiInputPeriod(string $period): array {
 if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$m))throw new InvalidArgumentException('Periode BOD tidak valid.');return [(int)$m[1],(int)$m[2]];
}
function ensureBodBiInputStore(mysqli $conn): void {
 static $ready=null;if($ready===null)$ready=new SplObjectStorage();if($ready->contains($conn))return;
 $conn->query('CREATE TABLE IF NOT EXISTS `bod bi opr sdm` (report_period CHAR(7) NOT NULL PRIMARY KEY, base64 LONGTEXT NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');$ready->attach($conn);
}
function findBodBiInput(mysqli $conn,string $period): ?array {
 [$year]=bodBiInputPeriod($period);ensureBodBiInputStore($conn);$first=$year.'-01';$q=$conn->prepare('SELECT report_period,base64 FROM `bod bi opr sdm` WHERE report_period BETWEEN ? AND ? ORDER BY report_period DESC LIMIT 1');$q->bind_param('ss',$first,$period);$q->execute();$record=$q->get_result()->fetch_assoc();$q->close();return $record?:null;
}
function validateBodBiInputDocument(DOMDocument $doc,string $period): void {
 [$year,$last]=bodBiInputPeriod($period);$tables=upbBjTables($doc);
 foreach(BOD_BI_MONTHS as $i=>$name){$month=$i+1;if($month>$last){if(isset($tables[$name]))throw new InvalidArgumentException('Sheet bulanan melebihi periode upload.');continue;}
  if(!isset($tables[$name]))throw new InvalidArgumentException('Sheet Januari sampai bulan pilihan harus lengkap.');
  $valid=false;foreach(upbBjCells($tables[$name]) as $row)foreach($row as $cell)if(preg_match('/BOD BI\.Opr\s*&\s*BOD BI\.SDM.*PERIODE\s*:\s*S\/D\s+'.preg_quote($name,'/').'\s+'.$year.'$/iu',trim($cell->textContent)))$valid=true;
  if(!$valid)throw new InvalidArgumentException('Tahun/bulan pada judul sheet '.$name.' tidak sesuai periode upload.');
  bodBiRawLayout($tables[$name]);foreach(array_merge(['SUB TOTAL I','SUB TOTAL II','GAJI, UPAH, DLL','RECRUITMENT'],$year<2026?['SUB TOTAL III']:[]) as $label)bodBiRawActualRef($tables[$name],$label);
 }
}
function validateBodBiInputBytes(string $bytes,string $period): void {
 if(strlen($bytes)>50*1024*1024)throw new InvalidArgumentException('File maksimal 50 MB.');$path=tempnam(sys_get_temp_dir(),'bod-validate-');
 try{file_put_contents($path,$bytes);validateBodBiInputDocument(loadOdsUploadDocument($path),$period);}finally{@unlink($path);}
}
function saveBodBiInput(mysqli $conn,string $period,string $bytes): void {
 validateBodBiInputBytes($bytes,$period);ensureBodBiInputStore($conn);require_once __DIR__.'/raw_native_period.php';guardStoredNativePeriod($conn,'bod bi opr sdm',$period,$bytes);$base64=base64_encode($bytes);$q=$conn->prepare('INSERT INTO `bod bi opr sdm` (report_period,base64) VALUES (?,?) ON DUPLICATE KEY UPDATE base64=VALUES(base64)');$q->bind_param('ss',$period,$base64);$q->execute();$q->close();
}
/** Continue the saved native monthly structure; clear inputs only in new months. */
function buildBodBiStoredInputTemplateOds(string $period,?string $saved): string {
 if($saved===null)return buildBodBiInputTemplateOds(BOD_BI_INPUT_SECTION,$period);[$year,$last]=bodBiInputPeriod($period);
 $path=tempnam(sys_get_temp_dir(),'bod-history-');$xmlPath=null;$zip=new ZipArchive();$opened=false;
 try{
  file_put_contents($path,$saved);if($zip->open($path)!==true)throw new InvalidArgumentException('Data ODS BOD tersimpan tidak valid.');$opened=true;$doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);$parent=$doc->getElementsByTagNameNS(UPB_O,'spreadsheet')->item(0);
  foreach($tables as $name=>$table)if(!in_array($name,array_slice(BOD_BI_MONTHS,0,$last),true))$parent->removeChild($table);
  $blank=static function($cell){$cell->removeAttributeNS(UPB_T,'formula');foreach(['value','value-type','string-value','date-value','boolean-value'] as $a)$cell->removeAttributeNS(UPB_O,$a);while($cell->firstChild)$cell->removeChild($cell->firstChild);};
  foreach(array_slice(BOD_BI_MONTHS,0,$last) as $name)if(isset($tables[$name]))bodBiTrimThirdSection($tables[$name],$year);
  $text=static function($cell,$value)use($blank){$blank($cell);$cell->setAttributeNS(UPB_O,'office:value-type','string');$p=$cell->ownerDocument->createElementNS(UPB_X,'text:p');$p->appendChild($cell->ownerDocument->createTextNode($value));$cell->appendChild($p);};$previous=null;
  foreach(array_slice(BOD_BI_MONTHS,0,$last) as $i=>$name){
   if(isset($tables[$name])){$previous=$tables[$name];continue;}
   if(!$previous)throw new InvalidArgumentException('Sheet Januari tidak ditemukan dalam data tersimpan.');
   $old=$previous->getAttributeNS(UPB_T,'name');$table=$previous->cloneNode(true);$table->setAttributeNS(UPB_T,'table:name',$name);$parent->appendChild($table);$layout=bodBiRawLayout($table);$r=1;
   foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$c=1;foreach($row->childNodes as $cell)if($cell instanceof DOMElement&&in_array($cell->localName,['table-cell','covered-table-cell'],true)){
     $f=$cell->getAttributeNS(UPB_T,'formula');if($f!==''){upbBjSetFormula($cell,str_replace(["['".$old."'.",'['.$old.'.'],["['".$name."'.","['".$name."'."],substr($f,4)),0);}
     elseif($r>$layout['header']+1&&$c>=$layout['first']&&$cell->localName==='table-cell')$blank($cell);
     elseif($cell->localName==='table-cell'){$v=$cell->textContent;if(str_contains($v,'PERIODE : S/D '))$text($cell,BOD_BI_INPUT_SECTION.' — PERIODE : S/D '.$name.' '.$year);elseif(preg_match('/^(?:AGG )?S\/D /i',trim($v)))$text($cell,(str_starts_with(trim($v),'AGG ')?'AGG ':'').'S/D '.$name.' '.$year);}
     $c+=max(1,(int)$cell->getAttributeNS(UPB_T,'number-columns-repeated'));
   }$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}$previous=$table;
  }
  appendBodBiRawReportSheets($doc,$zip,$period);$xmlPath=tempnam(sys_get_temp_dir(),'bod-history-xml-');$doc->save($xmlPath);$zip->addFile($xmlPath,'content.xml');$zip->close();$opened=false;return file_get_contents($path);
 }finally{if($opened)$zip->close();if($xmlPath)@unlink($xmlPath);@unlink($path);}
}
