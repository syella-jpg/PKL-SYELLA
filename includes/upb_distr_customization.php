<?php
/** Rewrite native ODS references without touching quoted text or cached results. */
function distrFormulaRefs(string $formula,string $sheet,callable $map): string {
 $segments=preg_split('/("(?:[^"]|"")*")/',$formula,-1,PREG_SPLIT_DELIM_CAPTURE);
 foreach($segments as $index=>&$segment){if($index%2)continue;
  $segment=preg_replace_callback('/\[([^\[\]]+)\]/',static function($match)use($sheet,$map){
   $points=explode(':',$match[1]);if(count($points)>2)return $match[0];$out=[];$inherited=$sheet;$firstMappedSheet=null;
   foreach($points as $i=>$point){
    if(!preg_match('~^(?:(\$?(?:\'(?:[^\']|\'\')+\'|[A-Z_][A-Z0-9_ ]*))?\.)?(\$?[A-Z]+)(\$?)([1-9]\d*)$~iD',$point,$m))return $match[0];
    $explicit=($m[1]??'')!=='';$locked=$explicit&&$m[1][0]==='$';$name=$explicit?str_replace("''","'",trim(ltrim($m[1],'$'),"'")):$inherited;
    $role=count($points)===1?'single':($i===0?'first':'last');$result=$map($name,(int)$m[4],$m[2],$role,$locked,$explicit);
    if($result===null)throw new InvalidArgumentException('Ada referensi rumus ke baris yang tidak dapat dirapikan dengan aman.');
    [$newName,$newRow,$newColumn]=$result;
    $prefix=$explicit||$newName!==$sheet?($locked?'$':'')."'".str_replace("'","''",$newName)."'.":'.';
    if($i>0&&$newName===$firstMappedSheet)$prefix='.';if($i===0)$firstMappedSheet=$newName;
    $out[]=$prefix.$newColumn.$m[3].$newRow;$inherited=$name;
   }
   return '['.implode(':',$out).']';
  },$segment);
 }unset($segment);return implode('',$segments);
}
function distrFormulaKey(string $formula): string {
 $formula=distrFormulaRefs($formula,'KESIMPULAN',static fn($s,$r,$c)=>[$s,$r,$c]);
 $parts=preg_split('/("(?:[^"]|"")*")/',$formula,-1,PREG_SPLIT_DELIM_CAPTURE);
 foreach($parts as $i=>&$part)if($i%2===0)$part=strtoupper(preg_replace('/\s+/','',$part));unset($part);return implode('',$parts);
}
function distrTotalRef(DOMElement $table): ?string {
 foreach(upbBjCells($table) as $r=>$row)if(strtoupper(trim($row[1]->textContent??''))==='TOTAL')return 'E'.$r;
 return null;
}
function distrSnapshotInfo(DOMDocument $doc): array {
 $tables=upbBjTables($doc);$monthly=[];
 foreach(DISTR_MONTHS as $i=>$name)if(isset($tables[$name])){$monthly[$i+1]=distrMonthInfo($tables[$name]);$total=distrTotalRef($tables[$name]);foreach($monthly[$i+1] as &$part)$part['month_total']=$total;unset($part);}
 return $monthly;
}
const DISTR_META_NS='urn:oasis:names:tc:opendocument:xmlns:meta:1.0';
function distrReadFormulaState(ZipArchive $zip): array {
 $xml=$zip->getFromName('meta.xml');if(!$xml)return [];$meta=upbBjXml($xml);
 foreach($meta->getElementsByTagNameNS(DISTR_META_NS,'user-defined') as $item)if($item->getAttributeNS(DISTR_META_NS,'name')==='UPB_DISTR_FORMULA_HISTORY')return json_decode($item->textContent,true)?:[];
 return [];
}
function distrWriteFormulaState(ZipArchive $zip,array $state): void {
 $xml=$zip->getFromName('meta.xml');if(!$xml)return;$doc=upbBjXml($xml);$parent=$doc->getElementsByTagNameNS(UPB_O,'meta')->item(0);if(!$parent)return;$target=null;
 foreach($parent->getElementsByTagNameNS(DISTR_META_NS,'user-defined') as $node)if($node->getAttributeNS(DISTR_META_NS,'name')==='UPB_DISTR_FORMULA_HISTORY'){$target=$node;break;}
 if(!$target){$target=$doc->createElementNS(DISTR_META_NS,'meta:user-defined');$target->setAttributeNS(DISTR_META_NS,'meta:name','UPB_DISTR_FORMULA_HISTORY');$target->setAttributeNS(DISTR_META_NS,'meta:value-type','string');$parent->appendChild($target);}
 $target->nodeValue='';$target->appendChild($doc->createTextNode(json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)));$zip->addFromString('meta.xml',$doc->saveXML());
}
function distrRawFormulaSnapshot(DOMElement $table): array {
 $out=[];$total=distrTotalRef($table);foreach(upbBjCells($table) as $r=>$row)foreach($row as $c=>$cell)if($cell->hasAttributeNS(UPB_T,'formula')){
  $address=$c.':'.$r;if($c===5&&'E'.$r===$total)continue;$out[$address]=$cell->getAttributeNS(UPB_T,'formula');
 }return $out;
}
function distrRawHasInputs(DOMElement $table): bool {
 $info=distrMonthInfo($table);$cells=upbBjCells($table);
 foreach($cells as $r=>$row){$product=false;foreach($info as $part)if($r>=$part['start']&&$r<=$part['last']){$product=true;break;}if(!$product)continue;
  foreach($row as $cell)if(!$cell->hasAttributeNS(UPB_T,'formula')&&($cell->hasAttributeNS(UPB_O,'value')||trim($cell->textContent)!==''||trim($cell->getAttributeNS(UPB_O,'string-value'))!==''))return true;
 }return false;
}
function distrCaptureRawState(DOMDocument $doc,array $state): array {
 $tables=upbBjTables($doc);$baseline=$state['raw_baseline']??[];$edits=$state['raw_edits']??[];
 foreach(DISTR_MONTHS as $i=>$name)if(isset($tables[$name])){$month=$i+1;$current=distrRawFormulaSnapshot($tables[$name]);
  if(isset($baseline[$month])){$old=$baseline[$month];$changed=array_keys($old)!==array_keys($current);
   foreach($current as $a=>$f)if(!isset($old[$a])||distrFormulaKey($old[$a])!==distrFormulaKey($f)){$changed=true;break;}
   if($changed)$edits[$month]=true;
  }else{
   $standard=[];foreach(distrMonthInfo($tables[$name]) as $part)foreach([3,5] as $c)$standard[$c.':'.$part['row']]='of:='.($part['last']>=$part['start']?'SUM([.'.chr(64+$c).$part['start'].':.'.chr(64+$c).$part['last'].'])':'0');
   foreach($current as $a=>$f)if(!isset($standard[$a])||distrFormulaKey($standard[$a])!==distrFormulaKey($f)){$edits[$month]=true;break;}
  }
  $baseline[$month]=$current;
 }$state['raw_baseline']=$baseline;$state['raw_edits']=$edits;return $state;
}
/** Record user changes against the last generated formulas, including explicit resets. */
function distrCaptureFormulaState(DOMDocument $doc,array $state,string $period): array {
 $state=distrCaptureRawState($doc,$state);$tables=upbBjTables($doc);$old=$tables['KESIMPULAN']??null;if(!$old)return $state+['rules'=>[],'baseline'=>[]];$monthly=distrSnapshotInfo($doc);if(!$monthly)return $state;
 $last=max(array_keys($monthly));$year=(int)substr($period,0,4);$oldPeriod=$year.'-'.sprintf('%02d',$last);
 $complete=true;foreach($monthly as $parts)if(empty($parts['GB 1']['month_total']))$complete=false;
 $expectedTable=$complete?distrConclusion($doc,$old,$monthly,$oldPeriod):null;$expected=$expectedTable?upbBjCells($expectedTable):[];$actual=upbBjCells($old);$rules=$state['rules']??[];$baseline=[];
 foreach(range(1,$last) as $month)foreach(range(4,13) as $row){$cell=$actual[$row][$month+4]??null;if(!$cell)continue;$f=$cell->getAttributeNS(UPB_T,'formula');if($f==='')continue;
  $standard=isset($expected[$row][$month+4])?$expected[$row][$month+4]->getAttributeNS(UPB_T,'formula'):'';
  $previous=$state['baseline'][$month][$row]??$standard;
  if($previous!==''&&distrFormulaKey($f)!==distrFormulaKey($previous)){
   $rules[$month][$row]=distrFormulaKey($f)===distrFormulaKey($standard)?['reset'=>true]:['formula'=>$f];
  }
  $baseline[$month][$row]=$f;
 }
 $state['rules']=$rules;$state['baseline']=$baseline;return $state;
}
function distrRowHasContent(DOMElement $row): bool {
 foreach($row->childNodes as $cell)if($cell instanceof DOMElement){
  if($cell->hasAttributeNS(UPB_T,'formula')||$cell->hasAttributeNS(UPB_O,'value')||$cell->hasAttributeNS(UPB_O,'date-value')||$cell->hasAttributeNS(UPB_O,'boolean-value')||trim($cell->getAttributeNS(UPB_O,'string-value'))!==''||trim($cell->textContent)!=='')return true;
 }return false;
}
/** A static, filtered selection avoids repeatedly traversing every formatted cell. */
function distrFormulaCells(DOMElement $table): DOMNodeList {
 $xpath=new DOMXPath($table->ownerDocument);$xpath->registerNamespace('distr',UPB_T);
 return $xpath->query('.//distr:table-cell[@distr:formula]',$table);
}
/** Compact product inputs only; retain all data/formulas and referenced empty cells. */
function distrCompactRows(DOMDocument $doc,array &$state): void {
 $tables=upbBjTables($doc);$info=distrSnapshotInfo($doc);$used=[];$protected=[];
 foreach($info as $month=>$parts){$name=DISTR_MONTHS[$month-1];$r=1;foreach($tables[$name]->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){
  $repeat=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));if(distrRowHasContent($row))$used[$name][]=[$r,$r+$repeat-1];$r+=$repeat;
 }}
 $protect=static function($name,$row)use(&$protected,$info){$month=array_search($name,DISTR_MONTHS,true);if($month===false)return;foreach($info[$month+1]??[] as $part)if($row>=$part['start']&&$row<=$part['last']){$protected[$name][$row]=true;break;}};
 foreach($tables as $sheet=>$table)foreach(distrFormulaCells($table) as $cell){
  $first=null;distrFormulaRefs($cell->getAttributeNS(UPB_T,'formula'),$sheet,static function($s,$r,$c,$role)use(&$first,$protect,$used){
   if($role==='single')$protect($s,$r);elseif($role==='first')$first=[$s,$r];elseif($first&&$first[0]===$s){$hasData=false;foreach($used[$s]??[] as [$a,$b])if($b>=$first[1]&&$a<=$r){$hasData=true;break;}if(!$hasData)$protect($s,$first[1]);}
   return [$s,$r,$c];
  });
 }
 $deleted=[];
 foreach($info as $month=>$parts){$name=DISTR_MONTHS[$month-1];$table=$tables[$name];$r=1;
  // An unfilled month is an input template, not an uploaded data table to trim.
  if(!distrRawHasInputs($table))continue;
  // Keep one editable row for an entirely empty group.
  foreach($parts as $part){$hasData=false;$hasProtected=false;foreach($used[$name]??[] as [$a,$b])if($b>=$part['start']&&$a<=$part['last']){$hasData=true;break;}
   foreach($protected[$name]??[] as $p=>$unused)if($p>=$part['start']&&$p<=$part['last']){$hasProtected=true;break;}
   if(!$hasData&&!$hasProtected&&$part['last']>=$part['start'])$protected[$name][$part['start']]=true;
  }
  foreach(iterator_to_array($table->childNodes) as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){
   $repeat=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));$end=$r+$repeat-1;$product=false;
   foreach($parts as $part)if($r>=$part['start']&&$end<=$part['last']){$product=true;break;}
   if($product&&!distrRowHasContent($row)){
    $keep=[];foreach($protected[$name]??[] as $p=>$unused)if($p>=$r&&$p<=$end)$keep[]=$p;sort($keep);$cursor=$r;
    foreach($keep as $p){if($cursor<$p)$deleted[$name][]=[$cursor,$p-1];$copy=$row->cloneNode(true);$copy->removeAttributeNS(UPB_T,'number-rows-repeated');$table->insertBefore($copy,$row);$cursor=$p+1;}
    if($cursor<=$end)$deleted[$name][]=[$cursor,$end];$table->removeChild($row);
   }$r+=$repeat;
  }
 }
 // Calc may save each blank row separately. Merge adjacent deletions before
 // translating references so lookup cost depends on gaps, not blank-row count.
 foreach($deleted as $name=>$ranges){$merged=[];foreach($ranges as [$a,$b]){$last=count($merged)-1;if($last>=0&&$a===$merged[$last][1]+1)$merged[$last][1]=$b;else $merged[]=[$a,$b];}$deleted[$name]=$merged;}
 $map=static function($s,$r,$c,$role)use($deleted){$offset=0;foreach($deleted[$s]??[] as [$a,$b]){
  if($r<$a)break;if($r<=$b){if($role==='single')return null;return [$s,$a-$offset-($role==='last'?1:0),$c];}$offset+=$b-$a+1;
 }return [$s,$r-$offset,$c];};
 foreach($tables as $sheet=>$table)foreach(distrFormulaCells($table) as $cell)$cell->setAttributeNS(UPB_T,'table:formula',distrFormulaRefs($cell->getAttributeNS(UPB_T,'formula'),$sheet,$map));
 foreach(DISTR_MONTHS as $i=>$name)if(isset($tables[$name]))$state['raw_baseline'][$i+1]=distrRawFormulaSnapshot($tables[$name]);
 foreach(['baseline','rules'] as $key)foreach($state[$key]??[] as $month=>$rows)foreach($rows as $row=>$value){
  if($key==='baseline')$state[$key][$month][$row]=distrFormulaRefs($value,'KESIMPULAN',$map);
  elseif(isset($value['formula']))$state[$key][$month][$row]['formula']=distrFormulaRefs($value['formula'],'KESIMPULAN',$map);
 }
}
/** Restore full input capacity for every unfilled month, including cloned ones. */
function distrExpandEmptyMonths(DOMDocument $doc,array &$state): void {
 $tables=upbBjTables($doc);$inserted=[];
 foreach(DISTR_MONTHS as $name)if(isset($tables[$name])&&!distrRawHasInputs($tables[$name])){
  $table=$tables[$name];$info=distrMonthInfo($table);$rowNodes=[];$r=1;
  foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$rowNodes[$r]=$row;$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
  foreach($info as $part){$extra=DISTR_PRODUCT_ROWS-max(0,$part['last']-$part['start']+1);if($extra<=0)continue;
   $row=$doc->createElementNS(UPB_T,'table:table-row');$row->setAttributeNS(UPB_T,'table:number-rows-repeated',(string)$extra);
   foreach(range(1,7) as $column)upbBjColumnCell($row,$column)->setAttributeNS(UPB_T,'table:style-name','DISTRRawCell');
   $table->insertBefore($row,$rowNodes[$part['row']]);$inserted[$name][]=[$part['row'],$extra,$part['last']];
  }
 }
 if(!$inserted)return;
 $map=static function($s,$r,$c,$role)use($inserted){$offset=0;foreach($inserted[$s]??[] as [$at,$count,$last])if($r>=$at||($role==='last'&&$r===$last))$offset+=$count;return [$s,$r+$offset,$c];};
 foreach($tables as $sheet=>$table)foreach(distrFormulaCells($table) as $cell)$cell->setAttributeNS(UPB_T,'table:formula',distrFormulaRefs($cell->getAttributeNS(UPB_T,'formula'),$sheet,$map));
 foreach(DISTR_MONTHS as $i=>$name)if(isset($tables[$name]))$state['raw_baseline'][$i+1]=distrRawFormulaSnapshot($tables[$name]);
 foreach(['baseline','rules'] as $key)foreach($state[$key]??[] as $month=>$rows)foreach($rows as $row=>$value){
  if($key==='baseline')$state[$key][$month][$row]=distrFormulaRefs($value,'KESIMPULAN',$map);
  elseif(isset($value['formula']))$state[$key][$month][$row]['formula']=distrFormulaRefs($value['formula'],'KESIMPULAN',$map);
 }
}
/** Shift monthly summary rules using group anchors, not hard-coded subtotal rows. */
function distrMoveSummaryFormula(string $formula,int $from,int $to,array $monthly): string {
 $delta=$to-$from;
 return distrFormulaRefs($formula,'KESIMPULAN',static function($sheet,$r,$c,$role,$locked,$explicit)use($from,$to,$delta,$monthly){
  $old=array_search(strtoupper($sheet),DISTR_MONTHS,true);
  if($old!==false&&!$locked){$next=$old+$delta;if($next>=0&&$next<12){
    $oldInfo=$monthly[$old+1]??[];$newInfo=$monthly[$next+1]??[];
    $oldTotal=$oldInfo['GB 1']['month_total']??null;$newTotal=$newInfo['GB 1']['month_total']??null;
    if($oldTotal&&$newTotal&&$r===(int)substr($oldTotal,1))$r=(int)substr($newTotal,1);
    else foreach($oldInfo as $group=>$part)if(isset($newInfo[$group])){
     if($r===$part['row']){$r=$newInfo[$group]['row'];break;}
     if($r>=$part['start']&&$r<=$part['last']){$r=min($newInfo[$group]['last'],max($newInfo[$group]['start'],$newInfo[$group]['start']+$r-$part['start']));break;}
    }$sheet=DISTR_MONTHS[$next];
   }
  }elseif(!$explicit&&$c[0]!=='$'){
   $column=0;foreach(str_split(strtoupper($c)) as $char)$column=$column*26+ord($char)-64;$column+=$delta;$c='';while($column>0){$column--;$c=chr(65+$column%26).$c;$column=intdiv($column,26);}
  }return [$sheet,$r,$c];
 });
}
function distrApplySummaryRules(DOMDocument $doc,DOMElement $table,array $monthly,array &$state,int $last): array {
 $cells=upbBjCells($table);$rows=[];$r=1;foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$rows[$r]=$row;$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
 $active=[];$custom=[];$baseline=[];
 foreach(range(1,$last) as $month)foreach(range(4,13) as $row){$rule=$state['rules'][$month][$row]??null;
  if($rule!==null){if(!empty($rule['reset']))unset($active[$row]);else $active[$row]=['month'=>$month,'formula'=>$rule['formula']];}
  $cell=upbBjColumnCell($rows[$row],$month+4);
  if(isset($active[$row])){$f=distrMoveSummaryFormula($active[$row]['formula'],$active[$row]['month'],$month,$monthly);distrNumber($cell,substr($f,4),null);$custom[]=[$row,$month+4];}
  $baseline[$month][$row]=$cell->getAttributeNS(UPB_T,'formula');
 }
 $state['baseline']=$baseline;return $custom;
}
/** Calculate customized summaries from sparse cached source inputs only. */
function distrCalculateSummary(DOMDocument $doc,array $custom): void {
 if(!$custom)return;require_once __DIR__.'/../vendor/autoload.php';$book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$book->removeSheetByIndex(0);$tables=upbBjTables($doc);
 try{foreach($tables as $name=>$table){$sheet=$book->createSheet();$sheet->setTitle($name);foreach(upbBjCells($table) as $r=>$row)foreach($row as $c=>$cell){
    $formula=$cell->getAttributeNS(UPB_T,'formula');$value=$cell->hasAttributeNS(UPB_O,'value')?(float)$cell->getAttributeNS(UPB_O,'value'):($cell->getAttributeNS(UPB_O,'string-value')?:$cell->textContent);
    if($name==='KESIMPULAN'&&$formula!=='')$sheet->setCellValue([$c,$r],\PhpOffice\PhpSpreadsheet\Reader\Ods\FormulaTranslator::convertToExcelFormulaValue(substr($formula,3)));
    elseif(is_float($value))$sheet->setCellValueExplicit([$c,$r],$value,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    else $sheet->setCellValueExplicit([$c,$r],$value,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
   }}
  $summary=$book->getSheetByName('KESIMPULAN');$native=upbBjCells($tables['KESIMPULAN']);
  foreach($native as $r=>$row)foreach($row as $c=>$cell)if($cell->hasAttributeNS(UPB_T,'formula')){
   try{$value=$summary->getCell([$c,$r])->getCalculatedValue();if(is_numeric($value))distrNumber($cell,substr($cell->getAttributeNS(UPB_T,'formula'),4),(float)$value);elseif($value==='')distrNumber($cell,substr($cell->getAttributeNS(UPB_T,'formula'),4),null);}catch(Throwable $e){/* Calc will recalculate unsupported custom functions on open. */}
  }
 }finally{$book->disconnectWorksheets();}
}
/** Normalize an upload before persistence, preserving native formatting and custom rules. */
function prepareUpbDistrInputOds(string $bytes,string $period): string {
 $path=tempnam(sys_get_temp_dir(),'distr-compact-');$zip=new ZipArchive();$opened=false;
 try{file_put_contents($path,$bytes);if($zip->open($path)!==true)throw new InvalidArgumentException('File ODS UPB DISTR tidak valid.');$opened=true;$doc=upbBjXml($zip->getFromName('content.xml'));
  $state=distrCaptureFormulaState($doc,distrReadFormulaState($zip),$period);distrCompactRows($doc,$state);distrWriteFormulaState($zip,$state);$zip->addFromString('content.xml',$doc->saveXML());$zip->close();$opened=false;
  return buildUpbDistrInputTemplateOds($period,file_get_contents($path));
 }finally{if($opened)$zip->close();@unlink($path);}
}
