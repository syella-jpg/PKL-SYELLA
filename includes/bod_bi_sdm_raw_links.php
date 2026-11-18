<?php
require_once __DIR__.'/bod_bi_opr_raw_links.php';
/** Source sheets are cumulative; the SDM monthly grid displays each month alone. */
function linkBodBiSdmRawMonths(DOMDocument $doc,DOMElement $table,string $period): void {
 $year=(int)substr($period,0,4);$last=(int)substr($period,5,2);$sources=upbBjTables($doc);$cells=upbBjCells($table);$rows=[];$r=1;
 foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$rows[$r]=$row;$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
 $norm=static fn($v)=>preg_replace('/[^A-Z0-9]/','',strtoupper((string)$v));$top=null;$bottom=PHP_INT_MAX;
 foreach($cells as $r=>$row)foreach($row as $cell)if(preg_match('/^BIAYA\s+SDM\s+TH\.?\s*(20\d{2})$/i',trim($cell->textContent),$m)){
  if((int)$m[1]===$year)$top=$r;elseif($top!==null&&$r>$top)$bottom=min($bottom,$r);
 }
 if($top===null)throw new InvalidArgumentException('Tabel tahun berjalan BOD BI.SDM tidak ditemukan.');
 $targets=[];$firstColumn=null;
 foreach($cells as $r=>$row){if($r<=$top||$r>=$bottom)continue;foreach($row as $c=>$cell){$label=$norm($cell->textContent);
  if($label==='JAN')$firstColumn=$c;
  if($label==='GAJIUPAHDLL')$targets['GAJI, UPAH, DLL']=$r;
  if($label==='RECRUITMENT')$targets['RECRUITMENT']=$r;
 }}
 if(count($targets)!==2||$firstColumn===null)throw new InvalidArgumentException('Pertahankan header bulan dan nama GAJI, UPAH, DLL serta RECRUITMENT pada BOD BI.SDM.');
 $values=[];
 foreach($targets as $item=>$r)foreach(range(1,12) as $month){$cell=upbBjColumnCell($rows[$r],$firstColumn+$month-1);
  if($month>$last){$cell->removeAttributeNS(UPB_T,'formula');foreach(['value','value-type','string-value'] as $a)$cell->removeAttributeNS(UPB_O,$a);while($cell->firstChild)$cell->removeChild($cell->firstChild);continue;}
  $ref=static function($m)use($sources,$item){$name=BOD_BI_MONTHS[$m-1];if(!isset($sources[$name]))throw new InvalidArgumentException('Sheet bulan BOD belum lengkap.');return bodBiRawLookupFormula($name,$sources[$name],$item);};
  $current=bodBiRawLookupValue($sources[BOD_BI_MONTHS[$month-1]],$item);$previous=$month===1?0:bodBiRawLookupValue($sources[BOD_BI_MONTHS[$month-2]],$item);$value=$current-$previous;$values[$r][$month]=$value;
  upbBjSetFormula($cell,$month===1?$ref($month):$ref($month).'-'.$ref($month-1),$value);
 }
 // Refresh cached YTD/FY and monthly totals as well as the source-linked cells.
 foreach($targets as $r){$sum=array_sum($values[$r]??[]);foreach([2,$firstColumn+12] as $c){$cell=upbBjColumnCell($rows[$r],$c);if($cell->hasAttributeNS(UPB_T,'formula'))upbBjSetFormula($cell,substr($cell->getAttributeNS(UPB_T,'formula'),4),$sum);}}
 foreach($cells as $r=>$row)if($r>$top&&$r<$bottom&&isset($row[1])&&$norm($row[1]->textContent)==='TOTAL'){
  foreach(range(1,12) as $m){$sum=0;foreach($targets as $sourceRow)$sum+=$values[$sourceRow][$m]??0;$cell=upbBjColumnCell($rows[$r],$firstColumn+$m-1);if($cell->hasAttributeNS(UPB_T,'formula'))upbBjSetFormula($cell,substr($cell->getAttributeNS(UPB_T,'formula'),4),$sum);}
  $sum=0;foreach($values as $monthly)$sum+=array_sum($monthly);foreach([2,$firstColumn+12] as $c){$cell=upbBjColumnCell($rows[$r],$c);if($cell->hasAttributeNS(UPB_T,'formula'))upbBjSetFormula($cell,substr($cell->getAttributeNS(UPB_T,'formula'),4),$sum);}
 }
}
