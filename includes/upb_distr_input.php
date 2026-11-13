<?php
require_once __DIR__.'/upb_bj_template_history.php';
const DISTR_MONTHS=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
const DISTR_SHORT=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
const DISTR_GROUPS=['GB 1','GB 2','GB 3','GB 4','GB 5','GB 6','ETHICAL','NON KONIMEX'];
const DISTR_PRODUCT_ROWS=2500;
function distrPeriod(string $period): array {if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$m))throw new InvalidArgumentException('Periode UPB DISTR tidak valid.');return [(int)$m[1],(int)$m[2]];}
function distrText(DOMElement $cell,string $text): void {while($cell->firstChild)$cell->removeChild($cell->firstChild);$cell->removeAttributeNS(UPB_T,'formula');$cell->removeAttributeNS(UPB_O,'value');$cell->removeAttributeNS('urn:org:documentfoundation:names:experimental:calc:xmlns:calcext:1.0','value-type');$cell->setAttributeNS(UPB_O,'office:value-type','string');$cell->setAttributeNS(UPB_O,'office:string-value',$text);$cell->appendChild($cell->ownerDocument->createElementNS(UPB_X,'text:p',$text));}
function distrNumber(DOMElement $cell,string $formula,?float $value): void {
 $cell->removeAttributeNS(UPB_O,'string-value');$cell->removeAttributeNS('urn:org:documentfoundation:names:experimental:calc:xmlns:calcext:1.0','value-type');
 upbBjSetFormula($cell,$formula,$value??0);
 if($value===null){$cell->setAttributeNS(UPB_O,'office:value-type','string');$cell->removeAttributeNS(UPB_O,'value');$cell->setAttributeNS(UPB_O,'office:string-value','');while($cell->firstChild)$cell->removeChild($cell->firstChild);$cell->appendChild($cell->ownerDocument->createElementNS(UPB_X,'text:p',''));}
}
/** Detect groups and totals by their labels; product rows are never tied to fixed addresses. */
function distrMonthInfo(DOMElement $table,bool $repair=false): array {
 $cells=upbBjCells($table);$rowNodes=[];$r=1;
 foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$rowNodes[$r]=$row;$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
 $out=[];$group=null;$start=null;
 foreach($cells as $r=>$row){$label=strtoupper(trim(preg_replace('/\s+/',' ',$row[1]->textContent??'')));
  if(in_array($label,DISTR_GROUPS,true)){$group=$label;$start=null;continue;}
  if($group&&$label==='NAMA PRODUK'){$start=$r+1;continue;}
  if($group&&$start!==null&&$label==='SUB TOTAL '.$group){
   $sum=0;$qty=0;$count=0;
   foreach($cells as $p=>$product)if($p>=$start&&$p<$r){
    $node=$rowNodes[$p];$repeat=min($r-$p,max(1,(int)$node->getAttributeNS(UPB_T,'number-rows-repeated')));$value=upbBjColumnCell($node,5);
    if($value->hasAttributeNS(UPB_O,'value')){$sum+=(float)$value->getAttributeNS(UPB_O,'value')*$repeat;if(trim(($product[1]??null)?->textContent??'')!=='')$count+=$repeat;}
    $qty+=(float)upbBjColumnCell($node,3)->getAttributeNS(UPB_O,'value')*$repeat;
   }
   $total=upbBjColumnCell($rowNodes[$r],5);
   if($repair){foreach([3=>$qty,5=>$sum] as $c=>$value){$cell=upbBjColumnCell($rowNodes[$r],$c);$formula=$cell->getAttributeNS(UPB_T,'formula');$letter=chr(64+$c);
    // Existing formulas belong to their month, including deliberately narrowed SUMs.
    if($formula==='')distrNumber($cell,$r>$start?'SUM([.'.$letter.$start.':.'.$letter.($r-1).'])':'0',$value);
   }}
   $value=$total->hasAttributeNS(UPB_O,'value')?(float)$total->getAttributeNS(UPB_O,'value'):$sum;
   $out[$group]=['row'=>$r,'ref'=>'E'.$r,'value'=>$value,'start'=>$start,'last'=>$r-1,'count'=>$count];$group=null;$start=null;
  }
 }
 return $out;
}
function distrBlankClone(DOMElement $previous,string $name,int $year,int $month): DOMElement {
 $table=$previous->cloneNode(true);$old=$table->getAttributeNS(UPB_T,'name');$table->setAttributeNS(UPB_T,'table:name',$name);
 $info=distrMonthInfo($table);$r=1;
 foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){
  $product=false;foreach($info as $part)if($r>=$part['start']&&$r<=$part['last']){$product=true;break;}
  if($product)foreach($row->childNodes as $cell)if($cell instanceof DOMElement&&$cell->localName==='table-cell'&&!$cell->hasAttributeNS(UPB_T,'formula')){
   foreach(['value','value-type','string-value','date-value','boolean-value'] as $a)$cell->removeAttributeNS(UPB_O,$a);while($cell->firstChild)$cell->removeChild($cell->firstChild);
  }
  foreach($row->getElementsByTagNameNS(UPB_T,'table-cell') as $cell)if($cell->hasAttributeNS(UPB_T,'formula')){distrNumber($cell,str_replace(["['".$old."'.",'['.$old.'.'],["['".$name."'.","['".$name."'."],substr($cell->getAttributeNS(UPB_T,'formula'),4)),0);}
  $r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));
 }
 $titleRow=0;foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'&&++$titleRow===2){distrText(upbBjColumnCell($row,1),'DATABASE JAN SD '.DISTR_SHORT[$month-1].' '.$year);break;}
 return $table;
}
function distrNewMonth(DOMDocument $doc,string $name,int $year,int $month): DOMElement {
 $escape=static fn($s)=>htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8');
 $cell=static fn($text='', $style='DISTRRawCell')=>'<table:table-cell table:style-name="'.$style.'">'.($text!==''?'<text:p>'.$escape($text).'</text:p>':'').'</table:table-cell>';
 $title=static fn($text)=>'<table:table-row><table:table-cell table:style-name="DISTRRawHead" table:number-columns-spanned="7"><text:p>'.$escape($text).'</text:p></table:table-cell><table:covered-table-cell table:number-columns-repeated="6"/></table:table-row>';
 $xml='<table:table table:name="'.$name.'"><table:table-column table:style-name="DISTRRawProduct"/><table:table-column table:style-name="DISTRRawColumn" table:number-columns-repeated="6"/>'.$title('PT. MNJ').$title('DATABASE JAN SD '.DISTR_SHORT[$month-1].' '.$year).'<table:table-row><table:table-cell/></table:table-row>';
 foreach(DISTR_GROUPS as $group){$xml.=$title($group).'<table:table-row>';foreach(['Nama Produk','GB','QTY','Sat','Nilai','BULAN','TAHUN'] as $label)$xml.=$cell($label,'DISTRRawHead');$xml.='</table:table-row>';
  // ODS repeated rows provide the full capacity without expanding the archive.
  $xml.='<table:table-row table:number-rows-repeated="'.DISTR_PRODUCT_ROWS.'">'.str_repeat($cell(),7).'</table:table-row>';
  $xml.='<table:table-row>'.$cell('SUB TOTAL '.$group,'DISTRRawHead').str_repeat($cell(),6).'</table:table-row><table:table-row><table:table-cell/></table:table-row>';
 }
 $xml.='</table:table>';$fragment=upbBjFragment($doc,$xml);return $fragment->removeChild($fragment->firstChild);
}
/** One monthly total keeps the conclusion's formulas short and empty months blank. */
function distrMonthTotal(DOMElement $table,array $info): string {
 $position=1;$totalRow=null;$totalPosition=null;
 foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){
  $cell=upbBjColumnCell($row,1);
  if(strtoupper(trim($cell->textContent))==='TOTAL'){$totalRow=$row;$totalPosition=$position;}
  $position+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));
 }
 if($totalRow===null){$doc=$table->ownerDocument;$totalRow=$doc->createElementNS(UPB_T,'table:table-row');$table->appendChild($totalRow);$totalPosition=$position;
  foreach(range(1,7) as $column)upbBjColumnCell($totalRow,$column)->setAttributeNS(UPB_T,'table:style-name','DISTRRawHead');
  distrText(upbBjColumnCell($totalRow,1),'TOTAL');
 }
 $ranges=[];$totals=[];
 foreach($info as $part){if($part['last']>=$part['start'])$ranges[]='[.A'.$part['start'].':.A'.$part['last'].']';$totals[]='[.'.$part['ref'].']';}
 $count=$ranges?'COUNTA('.implode(';',$ranges).')':'0';$sum=$totals?'SUM('.implode(';',$totals).')':'0';
 $value=array_sum(array_column($info,'count'))>0?array_sum(array_column($info,'value')):null;
 distrNumber(upbBjColumnCell($totalRow,5),'IF('.$count.'=0;"";'.$sum.')',$value);
 return 'E'.$totalPosition;
}
function distrMergeLayout(DOMDocument $doc,ZipArchive $zip,ZipArchive $layout): DOMDocument {
 $prefix=static fn($s)=>preg_replace('/((?:style:name|style:parent-style-name|style:next-style-name|style:apply-style-name|style:data-style-name|style:master-page-name|style:page-layout-name|style:font-name(?:-asian|-complex)?|table:style-name|table:default-cell-style-name|text:style-name)=")/','$1DISTRSUM_',$s);
 $native=upbBjXml($prefix($layout->getFromName('content.xml')));
 foreach(['automatic-styles','font-face-decls'] as $section){$to=$doc->getElementsByTagNameNS(UPB_O,$section)->item(0);$from=$native->getElementsByTagNameNS(UPB_O,$section)->item(0);if($to&&$from)upbBjMergeStyles($to,$from,$doc);}
 $styles=upbBjXml($zip->getFromName('styles.xml'));$other=upbBjXml($prefix($layout->getFromName('styles.xml')));
 foreach(['styles','automatic-styles','master-styles','font-face-decls'] as $section){$to=$styles->getElementsByTagNameNS(UPB_O,$section)->item(0);$from=$other->getElementsByTagNameNS(UPB_O,$section)->item(0);if($to&&$from)upbBjMergeStyles($to,$from,$styles);}
 $zip->addFromString('styles.xml',$styles->saveXML());return $native;
}
function distrConclusion(DOMDocument $doc,DOMElement $layout,array $monthly,string $period): DOMElement {
 [$year,$last]=distrPeriod($period);$table=$layout->ownerDocument===$doc?$layout->cloneNode(true):$doc->importNode($layout,true);$table->setAttributeNS(UPB_T,'table:name','KESIMPULAN');$rows=[];$r=1;
 foreach(iterator_to_array($table->childNodes) as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){if($r>13)$table->removeChild($row);else $rows[$r]=$row;$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
 distrText(upbBjColumnCell($rows[1],1),DISTR_SHORT[$last-1]);distrText(upbBjColumnCell($rows[2],1),'UPB DISTRIBUTOR TH. '.$year.' - YTD '.DISTR_SHORT[$last-1]);
 $values=[];$map=array_combine(DISTR_GROUPS,[5,6,7,8,9,10,11,12]);
 $monthTotalRef=static fn($month)=>"['".DISTR_MONTHS[$month-1]."'.".$monthly[$month]['GB 1']['month_total']."]";
 for($month=1;$month<=12;$month++){
  $col=$month+4;$letter=chr(64+$col);$current=$monthly[$month]??[];$previous=$monthly[$month-1]??[];
  $hasData=$month<=$last&&array_sum(array_column($current,'count'))>0&&($month===1||array_sum(array_column($previous,'count'))>0);
  foreach($map as $group=>$row){$cell=upbBjColumnCell($rows[$row],$col);$part=$current[$group]??null;$prior=$previous[$group]??null;
   if($month<=$last&&$part){$ref="['".DISTR_MONTHS[$month-1]."'.".$part['ref']."]";$formula=$month===1?$ref:'('.$ref.'-'.($prior?"['".DISTR_MONTHS[$month-2]."'.".$prior['ref']."]":'0').')';$empty=$monthTotalRef($month).'=""';if($month>1)$empty='OR('.$empty.';'.$monthTotalRef($month-1).'="")';$formula='IF('.$empty.';"";'.$formula.'/1000)';$value=$hasData?($part['value']-($month===1?0:($prior['value']??0)))/1000:null;distrNumber($cell,$formula,$value);$values[$row][$month]=$value;
   }else{distrText($cell,'');$values[$row][$month]=null;}
  }
  foreach([4=>[5,6,7,8,9,10],13=>[4,11,12]] as $row=>$parts){$sum=0;foreach($parts as $p)$sum+=$values[$p][$month]??0;$formula=$row===4?'SUM([.'.$letter.'5:.'.$letter.'10])':implode('+',array_map(static fn($p)=>'[.'.$letter.$p.']',$parts));distrNumber(upbBjColumnCell($rows[$row],$col),$formula,$sum);$values[$row][$month]=$sum;}
 }
 foreach(range(4,13) as $row){$sum=array_sum(array_slice($values[$row]??[],0,$last,true));distrNumber(upbBjColumnCell($rows[$row],2),'SUM([.E'.$row.':.'.chr(68+$last).$row.'])',$sum);distrNumber(upbBjColumnCell($rows[$row],17),'SUM([.E'.$row.':.P'.$row.'])',array_sum($values[$row]??[]));}
 $total=array_sum($values[13]??[]);foreach(range(4,13) as $row){$value=array_sum($values[$row]??[]);distrNumber(upbBjColumnCell($rows[$row],3),'IF([.$B$13]=0;0;[.B'.$row.']/[.$B$13])',$total!=0?$value/$total:0);}
 return $table;
}
function buildUpbDistrInputTemplateOds(string $period,?string $history=null): string {
 [$year,$last]=distrPeriod($period);$path=tempnam(sys_get_temp_dir(),'distr-input-');$zip=new ZipArchive();$layout=new ZipArchive();$opened=false;$layoutOpened=false;
 try{
  if($history!==null)file_put_contents($path,$history);else copy(__DIR__.'/../dashboard/assets/input-templates/upb-distr-raw.ods',$path);
  if($zip->open($path)!==true)throw new InvalidArgumentException('Gunakan file ODS UPB DISTR.');$opened=true;$doc=upbBjXml($zip->getFromName('content.xml'));
  $formulaState=distrCaptureFormulaState($doc,distrReadFormulaState($zip),$period);
  $saved=upbBjTables($doc);$parent=$doc->getElementsByTagNameNS(UPB_O,'spreadsheet')->item(0);foreach(array_values($saved) as $table)$parent->removeChild($table);
  $auto=$doc->getElementsByTagNameNS(UPB_O,'automatic-styles')->item(0);$definitions='<style:style style:name="DISTRRawCell" style:family="table-cell"><style:table-cell-properties fo:border="0.5pt solid #000000" fo:padding="0.04cm"/><style:text-properties fo:font-size="10pt"/></style:style><style:style style:name="DISTRRawHead" style:family="table-cell" style:parent-style-name="DISTRRawCell"><style:table-cell-properties fo:border="0.5pt solid #000000" fo:background-color="#dcefff"/><style:text-properties fo:font-size="10pt" fo:font-weight="bold"/></style:style><style:style style:name="DISTRRawProduct" style:family="table-column"><style:table-column-properties style:column-width="7cm"/></style:style><style:style style:name="DISTRRawColumn" style:family="table-column"><style:table-column-properties style:column-width="2.8cm"/></style:style>';
  $defs=upbBjFragment($doc,$definitions);$known=[];foreach($auto->childNodes as $node)if($node instanceof DOMElement)$known[$node->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0','name')]=true;
  foreach(iterator_to_array($defs->childNodes) as $node)if(!isset($known[$node->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0','name')]))$auto->appendChild($node);
  $monthly=[];$previous=null;
  for($month=1;$month<=$last;$month++){$name=DISTR_MONTHS[$month-1];$existing=$saved[$name]??null;
   $inherit=$previous&&(!$existing||(!distrRawHasInputs($existing)&&empty($formulaState['raw_edits'][$month])));
   $table=$inherit?distrBlankClone($previous,$name,$year,$month):($existing??distrNewMonth($doc,$name,$year,$month));
   if($inherit)$formulaState['raw_edits'][$month]=false;
   $parent->appendChild($table);$monthly[$month]=distrMonthInfo($table,true);
   $totalRef=distrMonthTotal($table,$monthly[$month]);$formulaState['raw_baseline'][$month]=distrRawFormulaSnapshot($table);foreach($monthly[$month] as &$part)$part['month_total']=$totalRef;unset($part);$previous=$table;
  }
  distrExpandEmptyMonths($doc,$formulaState);$monthly=distrSnapshotInfo($doc);
  if($layout->open(__DIR__.'/../dashboard/assets/input-templates/upb-distr-report.ods')!==true)throw new RuntimeException('Kerangka ringkasan UPB DISTR tidak tersedia.');$layoutOpened=true;$native=distrMergeLayout($doc,$zip,$layout);
  $conclusion=distrConclusion($doc,array_values(upbBjTables($native))[0],$monthly,$period);$parent->appendChild($conclusion);
  $custom=distrApplySummaryRules($doc,$conclusion,$monthly,$formulaState,$last);distrCalculateSummary($doc,$custom);distrWriteFormulaState($zip,$formulaState);
  $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$opened=false;$layout->close();$layoutOpened=false;return file_get_contents($path);
 }finally{if($opened)$zip->close();if($layoutOpened)$layout->close();@unlink($path);}
}
require_once __DIR__.'/upb_distr_customization.php';
function buildDataParetoUpbTemplateOds(string $period,?string $saved=null): string {
 [$year,$month]=distrPeriod($period);$path=tempnam(sys_get_temp_dir(),'pareto-input-');$zip=new ZipArchive();$opened=false;
 try{
  if($saved!==null)file_put_contents($path,$saved);else copy(__DIR__.'/../dashboard/assets/input-templates/upb-distr-raw.ods',$path);
  if($zip->open($path)!==true)throw new InvalidArgumentException('Gunakan file ODS DATA PARETO UPB.');$opened=true;$doc=upbBjXml($zip->getFromName('content.xml'));$tables=upbBjTables($doc);$table=$tables['DATA PARETO UPB']??null;if(!$table)throw new InvalidArgumentException('Sheet DATA PARETO UPB tidak ditemukan.');
  $parent=$doc->getElementsByTagNameNS(UPB_O,'spreadsheet')->item(0);foreach($tables as $name=>$other)if($name!=='DATA PARETO UPB')$parent->removeChild($other);
  if($saved===null)foreach($table->getElementsByTagNameNS(UPB_X,'p') as $text)if(preg_match('/^PERIODE JAN SD /i',$text->textContent)){$text->nodeValue='PERIODE JAN SD '.DISTR_SHORT[$month-1].' '.$year;}
  $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$opened=false;return file_get_contents($path);
 }finally{if($opened)$zip->close();@unlink($path);}
}

/** Monthly amounts in (000); an empty new month must not negate prior cumulative data. */
function distrMonthlyValues(?string $bytes,int $year): array {
    if($bytes===null)return [];
    $path=tempnam(sys_get_temp_dir(),'distr-values-');$zip=new ZipArchive();$opened=false;
    try{file_put_contents($path,$bytes);if($zip->open($path)!==true)throw new RuntimeException('Data UPB DISTR tersimpan tidak dapat dibaca.');$opened=true;
        $tables=upbBjTables(upbBjXml($zip->getFromName('content.xml')));$monthly=[];
        $customSummary=!empty(distrReadFormulaState($zip)['rules']);$summary=$customSummary&&isset($tables['KESIMPULAN'])?upbBjCells($tables['KESIMPULAN']):[];
        foreach(DISTR_MONTHS as $i=>$name){if(!isset($tables[$name]))continue;
            $valid=false;foreach(upbBjCells($tables[$name]) as $row)foreach($row as $cell)if(trim($cell->textContent)==='DATABASE JAN SD '.DISTR_SHORT[$i].' '.$year)$valid=true;
            if($valid)$monthly[$i+1]=distrMonthInfo($tables[$name]);
        }
        $result=[];foreach(range(1,12) as $month){$current=$monthly[$month]??[];$prior=$monthly[$month-1]??[];
            $available=array_sum(array_column($current,'count'))>0&&($month===1||array_sum(array_column($prior,'count'))>0);
            foreach(DISTR_GROUPS as $i=>$group){$value=$available?(($current[$group]['value']??0)-($month===1?0:($prior[$group]['value']??0)))/1000:null;
                $cell=$summary[$i+5][$month+4]??null;if($available&&$cell&&$cell->hasAttributeNS(UPB_O,'value'))$value=(float)$cell->getAttributeNS(UPB_O,'value');$result[$group][$month]=$value;
            }
            foreach(['_GB_1_6'=>4,'_TOTAL'=>13] as $key=>$row){$groups=$row===4?array_slice(DISTR_GROUPS,0,6):DISTR_GROUPS;$value=0;foreach($groups as $group)$value+=$result[$group][$month]??0;
                $cell=$summary[$row][$month+4]??null;if($available&&$cell&&$cell->hasAttributeNS(UPB_O,'value'))$value=(float)$cell->getAttributeNS(UPB_O,'value');$result[$key][$month]=$available?$value:null;
            }
        }return $result;
    }finally{if($opened)$zip->close();@unlink($path);}
}
