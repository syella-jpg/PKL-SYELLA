<?php
/** Resolve the named raw input row separately in each month; never pin its row number. */
function bodBiRawActualRef(DOMElement $table,string $item): string {
 $normalize=static fn($v)=>strtoupper(trim(preg_replace('/\s+/',' ',(string)$v)));
 $cells=upbBjCells($table);$actual=null;$salary=null;
 foreach($cells as $r=>$row)foreach($row as $c=>$cell){$label=$normalize($cell->textContent);
  if($label==='REALISASI')$actual=$c;
  if(preg_replace('/[^A-Z0-9]/','',$label)===preg_replace('/[^A-Z0-9]/','',$normalize($item)))$salary=$r;
 }
 if($actual===null||$salary===null)throw new InvalidArgumentException('Pertahankan label REALISASI dan '.$item.' pada sheet bulanan BOD.');
 return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($actual).$salary;
}
/** Cached SUMIF result for initial ODS display, before Calc recalculates formulas. */
function bodBiRawLookupValue(DOMElement $table,string $item): float {
 $ref=bodBiRawActualRef($table,$item);[$column,$row]=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($ref);$cells=upbBjCells($table);$cell=$cells[$row][\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column)]??null;
 return $cell&&$cell->hasAttributeNS(UPB_O,'value')?(float)$cell->getAttributeNS(UPB_O,'value'):0.0;
}
function bodBiOprRawActualRef(DOMElement $table): string {return bodBiRawActualRef($table,'SUB TOTAL I');}
/** Whole-column label lookup survives later row insertions/deletions in the downloaded file. */
function bodBiRawLookupFormula(string $name,DOMElement $table,string $item): string {
 $actualRef=bodBiRawActualRef($table,$item);$actual=preg_replace('/[0-9]/','',$actualRef);$labelColumn=null;
 foreach(upbBjCells($table) as $row)foreach($row as $c=>$cell)if(strtoupper(trim($cell->textContent))==='MATA ANGGARAN')$labelColumn=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
 if($labelColumn===null)throw new InvalidArgumentException('Header MATA ANGGARAN tidak ditemukan.');
 $sheet="'".str_replace("'","''",$name)."'";
 return 'SUMIF(['.$sheet.'.'.$labelColumn.':.'.$labelColumn.'];"'.str_replace('"','""',$item).'";['.$sheet.'.'.$actual.':.'.$actual.'])';
}
function linkBodBiOprRawMonths(DOMDocument $doc,DOMElement $table,string $period): void {
 $sources=upbBjTables($doc);$last=(int)substr($period,5,2);$rows=[];$r=1;
 foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$rows[$r]=$row;$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
 $blank=static function(DOMElement $cell){$cell->removeAttributeNS(UPB_T,'formula');foreach(['value','value-type','string-value','date-value','boolean-value'] as $a)$cell->removeAttributeNS(UPB_O,$a);$cell->removeAttributeNS('urn:org:documentfoundation:names:experimental:calc:xmlns:calcext:1.0','value-type');while($cell->firstChild)$cell->removeChild($cell->firstChild);};
 $includeThird=(int)substr($period,0,4)<2026;
 $previous=0.0;$sum=0.0;
 foreach(range(1,12) as $month){$r=$month+2;$rp=upbBjColumnCell($rows[$r],2);$cumulative=upbBjColumnCell($rows[$r],3);
  if($month>$last){$blank($rp);$blank($cumulative);continue;}
  $name=BOD_BI_MONTHS[$month-1];if(!isset($sources[$name]))throw new InvalidArgumentException('Sheet bulan BOD belum lengkap.');
  $value=bodBiRawLookupValue($sources[$name],'SUB TOTAL I');$expression=bodBiRawLookupFormula($name,$sources[$name],'SUB TOTAL I');
  if($includeThird){$value+=bodBiRawLookupValue($sources[$name],'SUB TOTAL III');$expression.='+'.bodBiRawLookupFormula($name,$sources[$name],'SUB TOTAL III');}
  $monthly=$value-$previous;$previous=$value;$sum+=$monthly;
  upbBjSetFormula($cumulative,$expression,$value);
  upbBjSetFormula($rp,$month===1?'[.C'.$r.']':'[.C'.$r.']-[.C'.($r-1).']',$monthly);
 }
 upbBjSetFormula(upbBjColumnCell($rows[15],2),'SUM([.B3:.B14])',$sum);
 upbBjSetFormula(upbBjColumnCell($rows[16],2),'SUM([.B3:.B'.($last+2).'])',$sum);
 // The 2025 template has no 2024 input. Do not invent prior-year figures/formulas.
 if(substr($period,0,4)==='2025')foreach(range(3,16) as $r)foreach([4,5] as $c)$blank(upbBjColumnCell($rows[$r],$c));
}
