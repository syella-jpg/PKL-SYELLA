<?php
require_once __DIR__.'/bod_bi_input_store.php';
require_once __DIR__.'/bod_bi_prior_year.php';
/** Rebuild report sheets from saved raw inputs, retaining local report formulas. */
function buildBodBiReportOds(string $period,?string $current,?string $prior): string {
 $bytes=bodBiAddPriorYear(buildBodBiStoredInputTemplateOds($period,$current),$period,$prior);
 $path=tempnam(sys_get_temp_dir(),'bod-report-');$xmlPath=null;$zip=new ZipArchive();$opened=false;
 try{
  file_put_contents($path,$bytes);$doc=loadOdsUploadDocument($path);
  foreach(upbBjTables($doc) as $name=>$table){
   if(!in_array($name,['BOD BI.Opr','BOD BI.SDM'],true)){$table->parentNode->removeChild($table);continue;}
   foreach(upbBjCells($table) as $row)foreach($row as $cell){$f=$cell->getAttributeNS(UPB_T,'formula');
    // Source monthly sheets are not part of the full report: use their computed values.
    if($f!==''&&str_contains($f,"['"))$cell->removeAttributeNS(UPB_T,'formula');
   }
  }
  if($zip->open($path)!==true)throw new RuntimeException('Laporan BOD tidak dapat dibaca.');$opened=true;
  $xmlPath=tempnam(sys_get_temp_dir(),'bod-report-xml-');$doc->save($xmlPath);$zip->addFile($xmlPath,'content.xml');$zip->close();$opened=false;return file_get_contents($path);
 }finally{if($opened)$zip->close();@unlink($path);if($xmlPath)@unlink($xmlPath);}
}
function loadBodBiReportOds(mysqli $conn,string $period): string {
 require_once __DIR__.'/report_artifact_cache.php';
 bodBiInputPeriod($period);$current=findBodBiInput($conn,$period);$prior=findBodBiInput($conn,((int)substr($period,0,4)-1).'-12');
 $files=['bod_bi_report.php','bod_bi_input_store.php','bod_bi_input_template.php','bod_bi_prior_year.php','bod_bi_raw_report_sheets.php','bod_bi_opr_raw_links.php','bod_bi_sdm_raw_links.php','bod_bi_period.php'];
 $key=reportArtifactKey([$period,$current['base64']??'',$prior['base64']??''],array_merge(array_map(static fn($f)=>__DIR__.'/'.$f,$files),glob(__DIR__.'/../dashboard/assets/input-templates/bod-bi-*.ods')));
 return cachedReportArtifact('bod-bi-report',$key,static fn()=>buildBodBiReportOds($period,$current?base64_decode($current['base64'],true):null,$prior?base64_decode($prior['base64'],true):null));
}

/** Read annual budgets from the selected month's saved raw sheet by labels. */
function bodBiSdmBudgetsFromBytes(string $bytes,string $period): array {
 [$year,$month]=bodBiInputPeriod($period);$path=tempnam(sys_get_temp_dir(),'bod-budget-');
 try{
  file_put_contents($path,$bytes);$tables=upbBjTables(loadOdsUploadDocument($path));$name=BOD_BI_MONTHS[$month-1];
  if(!isset($tables[$name]))return [];
  $rows=upbBjCells($tables[$name]);$column=null;$targets=[];
  $norm=static fn($s)=>preg_replace('/[^A-Z0-9]/','',strtoupper($s));
  foreach($rows as $r=>$row)foreach($row as $c=>$cell){
   $label=$norm($cell->textContent);
   if($label==='TOTALANGGARAN')$column=$c;
   if($label==='GAJIUPAHDLL')$targets['salary']=$r;
   if($label==='RECRUITMENT')$targets['recruitment']=$r;
  }
  if($column===null)throw new InvalidArgumentException('Kolom TOTAL ANGGARAN pada sheet '.$name.' tidak ditemukan.');
  $result=[];foreach($targets as $key=>$r){
   $cell=$rows[$r][$column]??null;$value=$cell?->getAttributeNS(UPB_O,'value');
   if($value!==null&&$value!==''&&is_numeric($value))$result[$key]=(float)$value;
  }
  return $result;
 }finally{@unlink($path);}
}
function loadBodBiSdmBudgets(mysqli $conn,string $period): array {
 $record=findBodBiInput($conn,$period);if(!$record)return [];
 $bytes=base64_decode($record['base64'],true);if($bytes===false)throw new RuntimeException('Raw data BOD tidak valid.');
 return bodBiSdmBudgetsFromBytes($bytes,$period);
}
