<?php
require_once __DIR__.'/upb_bj_template_history.php';
require_once __DIR__.'/../vendor/autoload.php';
const BOD_BI_MONTHS=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
/** Find source anchors by labels/formula roles, independent of product row counts. */
function bodBiRawLayout(DOMElement $table): array {
 $cells=upbBjCells($table);$header=null;$totals=[];$subtotalRows=[];$anchors=[];$columns=[];
 foreach($cells as $r=>$row){
  foreach($row as $c=>$cell){$label=strtoupper(trim(preg_replace('/\s+/',' ',$cell->textContent)));$formula=$cell->getAttributeNS(UPB_T,'formula');
   if($label==='MATA ANGGARAN')$header=$r;
   if($label==='REALISASI')$columns['actual']=$c;
   if(str_starts_with($label,'AGG S/D '))$columns['first']=$c;
   if($label==='REKAP REALISASI ANGGARAN BELANJA OPERASIONAL (PISAH BULAN)'||str_starts_with($label,'REKAP REALISASI ANGGARAN'))$anchors['title']=[$r,$c];
   if(str_contains($formula,'file:')){
    if(str_contains($formula,'TAHUN'))$anchors['period']=[$r,$c];
    elseif(str_contains($formula,'.C')){$anchors['budget']=[$r,$c];$columns['first']=$c;}
    elseif(str_contains($formula,'.G')){$anchors['actual']=[$r,$c];$columns['actual']=$c;}
   }
   if(preg_match('/^SUB\s*TOTAL\s+(I{1,3})$/',$label,$m))$totals['SUB TOTAL '.$m[1]]=$r;
   if($label==='TOTAL')$totals['TOTAL']=$r;
  }
  // The draft omits one subtotal label. Recognize its SUM row from the budget column.
  if(isset($row[$columns['first']??3])&&preg_match('/^of:=SUM\(/i',$row[$columns['first']??3]->getAttributeNS(UPB_T,'formula')))$subtotalRows[]=$r;
 }
 if(!isset($totals['SUB TOTAL II']))foreach($subtotalRows as $i=>$r)if($i<3&&!isset($totals['SUB TOTAL '.str_repeat('I',$i+1)]))$totals['SUB TOTAL '.str_repeat('I',$i+1)]=$r;
 if($header===null||!isset($totals['SUB TOTAL I'],$totals['SUB TOTAL II'],$totals['TOTAL'])||!isset($columns['actual']))throw new InvalidArgumentException('Pertahankan header dan baris subtotal/total pada template BOD.');
 return ['header'=>$header,'totals'=>$totals,'anchors'=>$anchors,'actual'=>$columns['actual'],'first'=>$columns['first']??3];
}
/** From 2026 onward the raw table ends at subtotal II, followed by TOTAL. */
function bodBiTrimThirdSection(DOMElement $table,int $year): void {
 if($year<2026)return;
 $layout=bodBiRawLayout($table);if(!isset($layout['totals']['SUB TOTAL III']))return;
 $start=$layout['totals']['SUB TOTAL II']+1;$end=$layout['totals']['TOTAL']-1;$r=1;
 foreach(iterator_to_array($table->childNodes) as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){
  $repeat=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));$last=$r+$repeat-1;
  if($last>=$start&&$r<=$end){$keep=max(0,$start-$r)+max(0,$last-$end);if($keep>0)$row->setAttributeNS(UPB_T,'table:number-rows-repeated',(string)$keep);else $table->removeChild($row);}
  $r+=$repeat;
 }
 $cells=upbBjCells($table);$total=$start;$first=$layout['totals']['SUB TOTAL I'];$second=$layout['totals']['SUB TOTAL II'];
 foreach($cells[$total]??[] as $c=>$cell)if($cell->hasAttributeNS(UPB_T,'formula')){
  $column=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
  $value=(float)($cells[$first][$c]??$cell)->getAttributeNS(UPB_O,'value')+(float)($cells[$second][$c]??$cell)->getAttributeNS(UPB_O,'value');
  upbBjSetFormula($cell,'SUM([.'.$column.$first.'];[.'.$column.$second.'])',$value);
 }
}
function buildBodBiInputTemplateOds(string $section,string $period): string {
 if(!in_array($section,['BOD BI.Opr & BOD BI.SDM'],true)||!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$m))throw new InvalidArgumentException('Bagian atau periode BOD tidak valid.');
 $year=(int)$m[1];$last=(int)$m[2];$path=tempnam(sys_get_temp_dir(),'bod-bi-raw-');$contentPath=null;$zip=new ZipArchive();$opened=false;
 try{
  copy(__DIR__.'/../dashboard/assets/input-templates/bod-bi-raw.ods',$path);if($zip->open($path)!==true)throw new RuntimeException('Template BOD tidak dapat dibaca.');$opened=true;
  $doc=upbBjXml($zip->getFromName('content.xml'));$tables=upbBjTables($doc);$original=reset($tables);$parent=$original->parentNode;foreach($tables as $table)if($table!==$original)$parent->removeChild($table);
  $layout=bodBiRawLayout($original);$monthly=[];
  $setText=static function(DOMElement $cell,string $value){foreach(['value','string-value','value-type'] as $a)$cell->removeAttributeNS(UPB_O,$a);$cell->removeAttributeNS(UPB_T,'formula');while($cell->firstChild)$cell->removeChild($cell->firstChild);$cell->setAttributeNS(UPB_O,'office:value-type','string');$p=$cell->ownerDocument->createElementNS(UPB_X,'text:p');$p->appendChild($cell->ownerDocument->createTextNode($value));$cell->appendChild($p);};
  // Seed the next three entries after the existing salary item without adding rows.
  $salaryRow=null;foreach(upbBjCells($original) as $at=>$cells)foreach($cells as $cell)if(preg_replace('/[^A-Z]/','',strtoupper($cell->textContent))==='GAJIUPAHDLL')$salaryRow=$at;
  if($salaryRow===null)throw new InvalidArgumentException('Baris GAJI, UPAH, DLL tidak ditemukan.');
  $labels=[2=>'RUMAH TANGGA & KEAMANAN',3=>'PENDIDIKAN & PEMBINAAN',4=>'RECRUITMENT'];
  $position=1;foreach(iterator_to_array($original->childNodes) as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){
   $repeat=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));$end=$position+$repeat-1;
   if($repeat>1&&$end>=$salaryRow+1&&$position<=$salaryRow+3){
    $from=max($position,$salaryRow+1);$to=min($end,$salaryRow+3);
    if($from>$position){$copy=$row->cloneNode(true);$copy->setAttributeNS(UPB_T,'table:number-rows-repeated',(string)($from-$position));$original->insertBefore($copy,$row);}
    for($at=$from;$at<=$to;$at++){$copy=$row->cloneNode(true);$copy->removeAttributeNS(UPB_T,'number-rows-repeated');$original->insertBefore($copy,$row);}
    if($to<$end){$copy=$row->cloneNode(true);$copy->setAttributeNS(UPB_T,'table:number-rows-repeated',(string)($end-$to));$original->insertBefore($copy,$row);}
    $original->removeChild($row);
   }$position+=$repeat;
  }
  $position=1;foreach($original->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){
   $number=$position-$salaryRow+1;if(isset($labels[$number])){$setText(upbBjColumnCell($row,1),(string)$number);$setText(upbBjColumnCell($row,2),$labels[$number]);}
   $position+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));
  }
  for($month=1;$month<=$last;$month++){
   $table=$original->cloneNode(true);$table->setAttributeNS(UPB_T,'table:name',BOD_BI_MONTHS[$month-1]);$parent->appendChild($table);$r=1;
   foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){
    $c=1;foreach($row->childNodes as $cell)if($cell instanceof DOMElement&&$cell->localName==='table-cell'){
     $formula=$cell->getAttributeNS(UPB_T,'formula');
     if($formula!==''&&str_contains($formula,'file:'))$setText($cell,'');
     elseif($formula!=='')upbBjSetFormula($cell,substr($formula,4),0);
     elseif($r>$layout['header']&&$c>=$layout['first'])$setText($cell,'');
     $c+=max(1,(int)$cell->getAttributeNS(UPB_T,'number-columns-repeated'));
    }
    foreach($layout['anchors'] as $role=>[$at,$column])if($r===$at){
     $value=$role==='title'?'REKAP REALISASI ANGGARAN BELANJA OPERASIONAL (KUMULATIF)':($role==='period'?$section.' — PERIODE : S/D '.BOD_BI_MONTHS[$month-1].' '.$year:($role==='budget'?'AGG S/D ':'S/D ').BOD_BI_MONTHS[$month-1].' '.$year);
     $setText(upbBjColumnCell($row,$column),$value);
    }
    foreach($layout['totals'] as $label=>$at)if($r===$at&&$label!=='TOTAL')$setText(upbBjColumnCell($row,2),$label);
    $r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));
   }
   bodBiTrimThirdSection($table,$year);$monthly[$month]=bodBiRawLayout($table);
  }
  $parent->removeChild($original);
  require_once __DIR__.'/bod_bi_raw_report_sheets.php';
  appendBodBiRawReportSheets($doc,$zip,$period);
  $contentPath=tempnam(sys_get_temp_dir(),'bod-bi-xml-');$doc->save($contentPath);$zip->addFile($contentPath,'content.xml');$zip->close();$opened=false;return file_get_contents($path);
 }finally{if($opened)$zip->close();if($contentPath)@unlink($contentPath);@unlink($path);}
}
