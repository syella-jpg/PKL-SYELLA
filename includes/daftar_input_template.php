<?php
require_once __DIR__.'/upb_bj_template_history.php';
function buildDaftarInputTemplateOds(string $period): string {
 if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$m))throw new InvalidArgumentException('Periode DAFTAR tidak valid.');
 $months=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];$path=tempnam(sys_get_temp_dir(),'daftar-');$zip=new ZipArchive();$opened=false;
 try{
  copy(__DIR__.'/../dashboard/assets/input-templates/daftar.ods',$path);if($zip->open($path)!==true)throw new RuntimeException('Template DAFTAR tidak dapat dibaca.');$opened=true;$doc=upbBjXml($zip->getFromName('content.xml'));
  foreach(upbBjTables($doc) as $table)foreach(upbBjCells($table) as $row)foreach($row as $cell)if(!$cell->hasAttributeNS(UPB_T,'formula'))foreach($cell->getElementsByTagNameNS(UPB_X,'p') as $p){$value=$p->textContent;$new=preg_replace('/\b2026\b/',$m[1],$value);$new=preg_replace('/\bYTD OCT\b/','YTD '.$months[(int)$m[2]-1],$new);if($new!==$value){while($p->firstChild)$p->removeChild($p->firstChild);$p->appendChild($doc->createTextNode($new));}}
  $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$opened=false;return file_get_contents($path);
 }finally{if($opened)$zip->close();@unlink($path);}
}
/** The ODS reader omits validation rules; restore them for the PHP/XLSX export. */
function applyDaftarJtValidation(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void {
 foreach([[7,16],[18,19],[21,22],[24,36],[40,43]] as [$first,$last])foreach(range($first,$last) as $r){
  $v=$sheet->getCell('M'.$r)->getDataValidation();$v->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST)->setAllowBlank(true)->setShowDropDown(true)->setShowInputMessage(true)->setPromptTitle('JT')->setPrompt('✓ = tepat waktu; ✗ = terlambat; boleh kosong.')->setFormula1('"✓,✗"');
 }
}
