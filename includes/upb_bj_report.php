<?php
require_once __DIR__.'/upb_bj_input_template.php';
/** Move local and monthly-sheet references when raw monthly blocks share one report sheet. */
function upbBjReportFormula(string $formula,int $offset,array $offsets): string {
 return preg_replace_callback('/\[([^\]]+)\]/',static function($bracket)use($offset,$offsets){
  $refs=preg_replace_callback("/(?:(?:'([^']+)'|([A-Za-z_][A-Za-z0-9_ ]*))?\\.)(\\$?[A-Z]{1,3}\\$?)([0-9]+)/",static function($m)use($offset,$offsets){$name=$m[1]!==''?$m[1]:($m[2]??'');if($name!==''&&!isset($offsets[$name]))return $m[0];$delta=$name!==''?$offsets[$name]:$offset;return '.'.$m[3].((int)$m[4]+$delta);},$bracket[1]);return '['.$refs.']';
 },$formula);
}
function buildUpbBjReportOds(string $period,?string $current=null,?string $prior=null): string {
 $bytes=buildUpbBjInputTemplateOds($period,$current,$prior);$temp=tempnam('/tmp','upb-report-');$zip=new ZipArchive();$native=new ZipArchive();
 try{
  file_put_contents($temp,$bytes);if($zip->open($temp)!==true||$native->open(__DIR__.'/../dashboard/assets/input-templates/upb-bj-kx-report.ods')!==true)throw new RuntimeException('Tabel laporan UPB BJ tidak dapat dibaca.');
  $doc=upbBjXml($zip->getFromName('content.xml'));$tables=upbBjTables($doc);$summary=upbBjCells($tables['KESIMPULAN']);
  $prefix=static fn($xml)=>preg_replace('/((?:style:name|style:parent-style-name|style:next-style-name|style:apply-style-name|style:data-style-name|style:master-page-name|style:page-layout-name|style:font-name(?:-asian|-complex)?|table:style-name|table:default-cell-style-name|text:style-name)=")/','$1UPBREPORT_',$xml);
  $layout=upbBjXml($prefix($native->getFromName('content.xml')));
  foreach(['automatic-styles','font-face-decls'] as $section){$to=$doc->getElementsByTagNameNS(UPB_O,$section)->item(0);$from=$layout->getElementsByTagNameNS(UPB_O,$section)->item(0);if($to&&$from)upbBjMergeStyles($to,$from,$doc);}
  $styles=upbBjXml($zip->getFromName('styles.xml'));$nativeStyles=upbBjXml($prefix($native->getFromName('styles.xml')));
  foreach(['styles','automatic-styles','master-styles','font-face-decls'] as $section){$to=$styles->getElementsByTagNameNS(UPB_O,$section)->item(0);$from=$nativeStyles->getElementsByTagNameNS(UPB_O,$section)->item(0);if($to&&$from)upbBjMergeStyles($to,$from,$styles);}
  // ODF automatic styles must define borders directly, rather than inherit another automatic style.
  $auto=$doc->getElementsByTagNameNS(UPB_O,'automatic-styles')->item(0);
  $format='<style:style style:name="UPBReportNumber" style:family="table-cell" style:data-style-name="UPBSummaryInteger"><style:table-cell-properties fo:border="0.5pt solid #000000" fo:padding="0.04cm"/><style:paragraph-properties fo:text-align="right"/><style:text-properties fo:font-size="10pt" fo:language="en" fo:country="US"/></style:style>';
  $auto->appendChild(upbBjFragment($doc,$format));
  $report=$doc->importNode(array_values(upbBjTables($layout))[0],true);$report->setAttributeNS(UPB_T,'table:name','UPB BJ kx');$rows=[];$r=1;
  foreach(iterator_to_array($report->childNodes) as $node)if($node instanceof DOMElement&&$node->localName==='table-row'){if($r>21)$report->removeChild($node);else $rows[$r]=$node;$r+=max(1,(int)$node->getAttributeNS(UPB_T,'number-rows-repeated'));}
  $year=(int)substr($period,0,4);$last=(int)substr($period,5,2);
  // Keep the supplied Pareto area unchanged. Only populate the annual summary blocks.
  foreach([9=>$year-1,16=>$year] as $r=>$y){foreach([9=>'UPB BJ KONIMEX TH. '.$y.' ( 000 )',12=>'PER BULAN TH. '.$y.' (000)'] as $c=>$title){$cell=upbBjColumnCell($rows[$r],$c);while($cell->firstChild)$cell->removeChild($cell->firstChild);$cell->setAttributeNS(UPB_O,'office:value-type','string');$cell->appendChild($doc->createElementNS(UPB_X,'text:p',$title));}}
  foreach([[3,11],[10,18]] as [$sourceStart,$destStart]){
   for($i=0;$i<4;$i++){$row=$destStart+$i;
    foreach(range(4,15) as $col){$from=$summary[$sourceStart+$i][$col]??null;$to=upbBjColumnCell($rows[$row],$col+8);while($to->firstChild)$to->removeChild($to->firstChild);$to->removeAttributeNS(UPB_T,'formula');$to->removeAttributeNS(UPB_O,'value');$to->setAttributeNS(UPB_O,'office:value-type','string');
     $to->setAttributeNS(UPB_T,'table:style-name','UPBReportNumber');
     if($from&&$from->hasAttributeNS(UPB_O,'value')){$value=(float)$from->getAttributeNS(UPB_O,'value');$to->setAttributeNS(UPB_O,'office:value-type','float');$to->setAttributeNS(UPB_O,'office:value',(string)$value);$to->setAttributeNS(UPB_T,'table:style-name','UPBReportNumber');$to->appendChild($doc->createElementNS(UPB_X,'text:p',number_format($value,0,'.',',')));}
    }
    foreach([2=>10,16=>24] as $sourceCol=>$destCol){$from=$summary[$sourceStart+$i][$sourceCol]??null;$value=$from?(float)$from->getAttributeNS(UPB_O,'value'):0;$cell=upbBjColumnCell($rows[$row],$destCol);$end=$destCol===10?chr(75+$last):'W';upbBjSetFormula($cell,'SUM([.L'.$row.':.'.$end.$row.'])',$value);$cell->setAttributeNS(UPB_T,'table:style-name','UPBReportNumber');$cell->getElementsByTagNameNS(UPB_X,'p')->item(0)->nodeValue=number_format($value,0,'.',',');}
   }
  }
  $names=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];$offsets=[];$sizes=[];$position=22;
  // Compute all offsets first, allowing formulas to refer to another month's block.
  for($m=1;$m<=$last;$m++){$name=$names[$m-1];$position+=3;$offsets[$name]=$position-1;$used=upbBjCells($tables[$name]);$sizes[$name]=$used?max(array_keys($used)):1;$position+=$sizes[$name];}
  for($m=1;$m<=$last;$m++){
   for($gap=0;$gap<3;$gap++){$row=$doc->createElementNS(UPB_T,'table:table-row');if($m<$last)$row->setAttributeNS(UPB_T,'table:visibility','collapse');$report->appendChild($row);}
   $copied=0;foreach($tables[$names[$m-1]]->childNodes as $sourceRow){if(!$sourceRow instanceof DOMElement||$sourceRow->localName!=='table-row')continue;if($copied>=$sizes[$names[$m-1]])break;$row=$sourceRow->cloneNode(true);$repeat=min(max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated')),$sizes[$names[$m-1]]-$copied);if($repeat>1)$row->setAttributeNS(UPB_T,'table:number-rows-repeated',(string)$repeat);else $row->removeAttributeNS(UPB_T,'number-rows-repeated');$copied+=$repeat;
    if($m<$last)$row->setAttributeNS(UPB_T,'table:visibility','collapse');else $row->removeAttributeNS(UPB_T,'visibility');
    foreach($row->getElementsByTagNameNS(UPB_T,'table-cell') as $cell)if($cell->hasAttributeNS(UPB_T,'formula'))$cell->setAttributeNS(UPB_T,'table:formula',upbBjReportFormula($cell->getAttributeNS(UPB_T,'formula'),$offsets[$names[$m-1]],$offsets));
    $report->appendChild($row);
   }
  }
  $parent=$doc->getElementsByTagNameNS(UPB_O,'spreadsheet')->item(0);foreach(array_values($tables) as $table)$parent->removeChild($table);$parent->appendChild($report);
  $zip->addFromString('content.xml',$doc->saveXML());$zip->addFromString('styles.xml',$styles->saveXML());$zip->close();$native->close();return file_get_contents($temp);
 }finally{if(is_file($temp))unlink($temp);}
}
