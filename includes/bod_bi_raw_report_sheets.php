<?php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/bod_bi_period.php';
require_once __DIR__.'/ods_report_styles.php';
require_once __DIR__.'/bod_bi_opr_raw_links.php';
require_once __DIR__.'/bod_bi_sdm_raw_links.php';
/** Attach the two native BOD layouts to the combined raw workbook. */
function appendBodBiRawReportSheets(DOMDocument $doc,ZipArchive $zip,string $period): void {
 $styleDoc=upbBjXml($zip->getFromName('styles.xml'));$parent=$doc->getElementsByTagNameNS(UPB_O,'spreadsheet')->item(0);
 foreach(['opr'=>'BOD BI.Opr','sdm'=>'BOD BI.SDM'] as $kind=>$name){
  $path=__DIR__.'/../dashboard/assets/input-templates/bod-bi-'.$kind.'.ods';$source=new ZipArchive();if($source->open($path)!==true)throw new RuntimeException('Template '.$name.' tidak dapat dibaca.');
  try{$content=upbBjXml($source->getFromName('content.xml'));$styles=upbBjXml($source->getFromName('styles.xml'));}finally{$source->close();}
  trimUnusedReportOdsStyles($content,$styles);
  $prefix=static fn($xml)=>preg_replace('/((?:style:name|style:parent-style-name|style:next-style-name|style:apply-style-name|style:data-style-name|style:master-page-name|style:page-layout-name|style:font-name(?:-asian|-complex)?|table:style-name|table:default-cell-style-name|text:style-name)=")/','$1RAWBOD'.strtoupper($kind).'_',$xml);
  $content=upbBjXml($prefix($content->saveXML()));$styles=upbBjXml($prefix($styles->saveXML()));
  $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$sheet=$book->getActiveSheet();applyBodBiPeriod($sheet,$kind,$period);
  $table=array_values(upbBjTables($content))[0];$cells=upbBjCells($table);
  foreach($cells as $row)foreach($row as $cell)if($cell->hasAttributeNS(UPB_T,'formula'))upbBjSetFormula($cell,substr($cell->getAttributeNS(UPB_T,'formula'),4),0);
  $text=static function($cell,$value){$cell->removeAttributeNS(UPB_T,'formula');foreach(['value','string-value','value-type'] as $a)$cell->removeAttributeNS(UPB_O,$a);$cell->setAttributeNS(UPB_O,'office:value-type','string');while($cell->firstChild)$cell->removeChild($cell->firstChild);$p=$cell->ownerDocument->createElementNS(UPB_X,'text:p');$p->appendChild($cell->ownerDocument->createTextNode($value));$cell->appendChild($p);};
  foreach($cells as $r=>$row)foreach($row as $c=>$cell){$address=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c).$r;$value=$sheet->getCell($address)->getValue();if(is_string($value)&&!str_starts_with($value,'=')&&$value!==$cell->textContent)$text($cell,$value);}
  // Include originally empty RP cells too: the prior-year monthly formulas fill these.
  $rows=[];$r=1;foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$rows[$r]=$row;$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
  $formula=static function($address,$expression)use($rows){[$col,$row]=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($address);$cell=upbBjColumnCell($rows[$row],\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($col));upbBjSetFormula($cell,$expression,0);};
  $month=(int)substr($period,5,2);
  if($kind==='sdm'){foreach([7,8,13,14] as $r)$formula('B'.$r,'SUM([.D'.$r.':.'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($month+3).$r.'])');}
  else{$formula('D3','[.E3]');for($r=4;$r<=14;$r++)$formula('D'.$r,'[.E'.$r.']-[.E'.($r-1).']');$formula('D15','SUM([.D3:.D14])');foreach(['B','D'] as $c)$formula($c.'16','SUM([.'.$c.'3:.'.$c.($month+2).'])');}
  if($kind==='opr')linkBodBiOprRawMonths($doc,$table,$period);
  if($kind==='sdm')linkBodBiSdmRawMonths($doc,$table,$period);
  $book->disconnectWorksheets();
  foreach(['automatic-styles','font-face-decls'] as $section){$to=$doc->getElementsByTagNameNS(UPB_O,$section)->item(0);$from=$content->getElementsByTagNameNS(UPB_O,$section)->item(0);if($to&&$from)upbBjMergeStyles($to,$from,$doc);}
  foreach(['styles','automatic-styles','master-styles','font-face-decls'] as $section){$to=$styleDoc->getElementsByTagNameNS(UPB_O,$section)->item(0);$from=$styles->getElementsByTagNameNS(UPB_O,$section)->item(0);if($to&&$from)upbBjMergeStyles($to,$from,$styleDoc);}
  $parent->appendChild($doc->importNode($table,true));
 }
 $zip->addFromString('styles.xml',$styleDoc->saveXML());
}
