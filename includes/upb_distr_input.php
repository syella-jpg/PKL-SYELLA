<?php
require_once __DIR__.'/upb_bj_template_history.php';
require_once __DIR__.'/ods_upload_xml.php';
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
 $out=[];$group=null;$start=null;$r=1;$sum=0;$qty=0;$count=0;
 foreach($table->childNodes as $node)if($node instanceof DOMElement&&$node->localName==='table-row'){
  $repeat=max(1,(int)$node->getAttributeNS(UPB_T,'number-rows-repeated'));
  $columns=[];$c=1;foreach($node->childNodes as $cell)if($cell instanceof DOMElement&&in_array($cell->localName,['table-cell','covered-table-cell'],true)){
   $n=max(1,(int)$cell->getAttributeNS(UPB_T,'number-columns-repeated'));foreach([1,3,5] as $wanted)if($wanted>=$c&&$wanted<$c+$n)$columns[$wanted]=$cell;$c+=$n;if($c>5)break;
  }
  $label=strtoupper(trim(preg_replace('/\s+/',' ',$columns[1]->textContent??'')));
  if(in_array($label,DISTR_GROUPS,true)){$group=$label;$start=null;}
  elseif($group&&$label==='NAMA PRODUK'){$start=$r+1;$sum=0;$qty=0;$count=0;}
  elseif($group&&$start!==null&&$label==='SUB TOTAL '.$group){
   $total=$columns[5]??upbBjColumnCell($node,5);
   if($repair)foreach([3=>$qty,5=>$sum] as $column=>$value){$cell=$columns[$column]??upbBjColumnCell($node,$column);$letter=chr(64+$column);
    if($cell->getAttributeNS(UPB_T,'formula')==='')distrNumber($cell,$r>$start?'SUM([.'.$letter.$start.':.'.$letter.($r-1).'])':'0',$value);
   }
   $out[$group]=['row'=>$r,'ref'=>'E'.$r,'value'=>$total->hasAttributeNS(UPB_O,'value')?(float)$total->getAttributeNS(UPB_O,'value'):$sum,'start'=>$start,'last'=>$r-1,'count'=>$count];$group=null;$start=null;
  }elseif($group&&$start!==null){
   $value=$columns[5]??null;if($value&&$value->hasAttributeNS(UPB_O,'value')){$sum+=(float)$value->getAttributeNS(UPB_O,'value')*$repeat;if($label!=='')$count+=$repeat;}
   if(isset($columns[3]))$qty+=(float)$columns[3]->getAttributeNS(UPB_O,'value')*$repeat;
  }$r+=$repeat;
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
 [$year,$last]=distrPeriod($period);$table=$layout->ownerDocument===$doc?$layout->cloneNode(true):$doc->importNode($layout,true);$table->setAttributeNS(UPB_T,'table:name','REKAP');$rows=[];$r=1;
 foreach(iterator_to_array($table->childNodes) as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){if($r>13)$table->removeChild($row);else $rows[$r]=$row;$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
 distrText(upbBjColumnCell($rows[1],1),DISTR_SHORT[$last-1]);distrText(upbBjColumnCell($rows[2],1),'UPB DISTRIBUTOR TH. '.$year.' - YTD '.DISTR_SHORT[$last-1]);
 $values=[];$map=array_combine(DISTR_GROUPS,[5,6,7,8,9,10,11,12]);
 $monthTotalRef=static fn($month)=>"['".DISTR_MONTHS[$month-1]."'.".$monthly[$month]['GB 1']['month_total']."]";
 for($month=1;$month<=12;$month++){
  $col=$month+4;$letter=chr(64+$col);$current=$monthly[$month]??[];
  $hasData=$month<=$last&&array_sum(array_column($current,'count'))>0;
  foreach($map as $group=>$row){$cell=upbBjColumnCell($rows[$row],$col);$part=$current[$group]??null;
   if($month<=$last&&$part){$ref="['".DISTR_MONTHS[$month-1]."'.".$part['ref']."]";$formula='IF('.$monthTotalRef($month).'="";"";'.$ref.'/1000)';$value=$hasData?$part['value']/1000:null;distrNumber($cell,$formula,$value);$values[$row][$month]=$value;
   }else{distrText($cell,'');$values[$row][$month]=null;}
  }
  foreach([4=>[5,6,7,8,9,10],13=>[4,11,12]] as $row=>$parts){$sum=0;foreach($parts as $p)$sum+=$values[$p][$month]??0;$formula=$row===4?'SUM([.'.$letter.'5:.'.$letter.'10])':implode('+',array_map(static fn($p)=>'[.'.$letter.$p.']',$parts));distrNumber(upbBjColumnCell($rows[$row],$col),$formula,$sum);$values[$row][$month]=$sum;}
 }
 foreach(range(4,13) as $row){$sum=array_sum(array_slice($values[$row]??[],0,$last,true));distrNumber(upbBjColumnCell($rows[$row],2),'SUM([.E'.$row.':.'.chr(68+$last).$row.'])',$sum);distrNumber(upbBjColumnCell($rows[$row],17),'SUM([.E'.$row.':.P'.$row.'])',array_sum($values[$row]??[]));}
 $total=array_sum($values[13]??[]);foreach(range(4,13) as $row){$value=array_sum($values[$row]??[]);distrNumber(upbBjColumnCell($rows[$row],3),'IF([.$B$13]=0;0;[.B'.$row.']/[.$B$13])',$total!=0?$value/$total:0);}
 return $table;
}
function buildUpbDistrInputTemplateOds(string $period,?string $history=null,bool $validateUpload=false): string {
 [$year,$last]=distrPeriod($period);$path=tempnam(sys_get_temp_dir(),'distr-input-');$zip=new ZipArchive();$layout=new ZipArchive();$opened=false;$layoutOpened=false;$contentPath=null;
 try{
  if($history!==null)file_put_contents($path,$history);else copy(__DIR__.'/../dashboard/assets/input-templates/upb-distr-raw.ods',$path);
  if($zip->open($path)!==true)throw new InvalidArgumentException('Gunakan file ODS UPB DISTR.');$opened=true;$doc=loadOdsUploadDocument($path);
  if($validateUpload)validateUpbDistrDocument($doc,$period,'UPB DISTR');
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
  distrCompactRows($doc,$formulaState);
  distrExpandEmptyMonths($doc,$formulaState,$last);$monthly=distrSnapshotInfo($doc);
  if($layout->open(__DIR__.'/../dashboard/assets/input-templates/upb-distr-report.ods')!==true)throw new RuntimeException('Kerangka ringkasan UPB DISTR tidak tersedia.');$layoutOpened=true;$native=distrMergeLayout($doc,$zip,$layout);
  $conclusion=distrConclusion($doc,array_values(upbBjTables($native))[0],$monthly,$period);$parent->appendChild($conclusion);
  $custom=distrApplySummaryRules($doc,$conclusion,$monthly,$formulaState,$last);distrCalculateSummary($doc,$custom);distrWriteFormulaState($zip,$formulaState);
  require_once __DIR__.'/raw_template_protection.php';protectRawRekapOds($conclusion);
  // Serialize to disk and let ZIP stream it; saveXML/addFromString duplicates hundreds of MB.
  $contentPath=tempnam(sys_get_temp_dir(),'distr-content-');
  if($contentPath===false||$doc->save($contentPath)===false||!$zip->addFile($contentPath,'content.xml'))throw new RuntimeException('File ODS UPB DISTR gagal ditulis.');
  if(!$zip->close())throw new RuntimeException('File ODS UPB DISTR gagal dikemas.');$opened=false;$layout->close();$layoutOpened=false;return file_get_contents($path);
 }finally{if($opened)$zip->close();if($layoutOpened)$layout->close();if($contentPath!==null)@unlink($contentPath);@unlink($path);}
}
require_once __DIR__.'/upb_distr_customization.php';
function buildDataParetoUpbTemplateOds(string $period,?string $saved=null): string {
 require_once __DIR__.'/data_pareto_upb_formulas.php';
 [$year,$month]=distrPeriod($period);$path=tempnam(sys_get_temp_dir(),'pareto-input-');$zip=new ZipArchive();$opened=false;
 try{
  if($saved!==null)file_put_contents($path,$saved);else copy(__DIR__.'/../dashboard/assets/input-templates/upb-distr-raw.ods',$path);
  if($zip->open($path)!==true)throw new InvalidArgumentException('Gunakan file ODS DATA PARETO UPB.');$opened=true;$doc=upbBjXml(readOdsUploadContent($path));$tables=upbBjTables($doc);$table=$tables['DATA PARETO UPB']??null;if(!$table)throw new InvalidArgumentException('Sheet DATA PARETO UPB tidak ditemukan.');
  $parent=$doc->getElementsByTagNameNS(UPB_O,'spreadsheet')->item(0);foreach($tables as $name=>$other)if($name!=='DATA PARETO UPB')$parent->removeChild($other);
  if($saved===null)foreach($table->getElementsByTagNameNS(UPB_X,'p') as $text)if(preg_match('/^PERIODE JAN SD /i',$text->textContent)){$text->nodeValue='PERIODE JAN SD '.DISTR_SHORT[$month-1].' '.$year;}
  refreshDataParetoUpbSums($table);
  refreshDataParetoDetailRanges($table);
  $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$opened=false;return file_get_contents($path);
 }finally{if($opened)$zip->close();@unlink($path);}
}

/** Read the saved conclusion without loading hundreds of thousands of product cells. */
function distrSavedMonthlySummary(string $path,int $year): ?array {
 $reader=new XMLReader();if(!@$reader->open('zip://'.$path.'#content.xml',null,LIBXML_NONET))return null;
 try{
  while($reader->read()){
   if($reader->nodeType===XMLReader::DOC_TYPE)throw new InvalidArgumentException('DTD tidak diizinkan dalam ODS.');
   if($reader->nodeType!==XMLReader::ELEMENT||$reader->namespaceURI!==UPB_T||$reader->localName!=='table')continue;
   if(!in_array($reader->getAttributeNs('name',UPB_T),['REKAP','KESIMPULAN'],true)){
    // next() skips raw detail nodes in native XML code rather than creating PHP objects.
    while($reader->next()){
     if($reader->nodeType!==XMLReader::ELEMENT||$reader->namespaceURI!==UPB_T||$reader->localName!=='table')continue;
     if(in_array($reader->getAttributeNs('name',UPB_T),['REKAP','KESIMPULAN'],true))break;
    }
   }
   if($reader->nodeType!==XMLReader::ELEMENT||$reader->localName!=='table'||!in_array($reader->getAttributeNs('name',UPB_T),['REKAP','KESIMPULAN'],true))continue;
   $doc=upbBjXml($reader->readOuterXml());$cells=upbBjCells($doc->documentElement);
   if(!preg_match('/UPB DISTRIBUTOR TH\.?\s*'.$year.'(?: |$)/i',$cells[2][1]->textContent??''))return null;
   $result=[];
   foreach(range(1,12) as $month){$available=false;foreach(range(5,12) as $row)if(isset($cells[$row][$month+4])&&$cells[$row][$month+4]->hasAttributeNS(UPB_O,'value')){$available=true;break;}
    foreach(DISTR_GROUPS as $i=>$group){$cell=$cells[$i+5][$month+4]??null;$result[$group][$month]=$available&&$cell&&$cell->hasAttributeNS(UPB_O,'value')?(float)$cell->getAttributeNS(UPB_O,'value'):null;}
    foreach(['_GB_1_6'=>4,'_TOTAL'=>13] as $key=>$row){$cell=$cells[$row][$month+4]??null;$result[$key][$month]=$available&&$cell&&$cell->hasAttributeNS(UPB_O,'value')?(float)$cell->getAttributeNS(UPB_O,'value'):null;}
   }return $result;
  }return null;
 }finally{$reader->close();}
}

/** Monthly amounts in (000); each input sheet contains that month alone. */
function distrMonthlyValues(?string $bytes,int $year): array {
    if($bytes===null)return [];
    $path=tempnam(sys_get_temp_dir(),'distr-values-');$zip=new ZipArchive();$opened=false;
    try{file_put_contents($path,$bytes);if($zip->open($path)!==true)throw new RuntimeException('Data UPB DISTR tersimpan tidak dapat dibaca.');$opened=true;
        $state=distrReadFormulaState($zip);
        if(($state['input_basis']??null)==='monthly'&&!empty($state['baseline'])){
            $saved=distrSavedMonthlySummary($path,$year);if($saved!==null)return $saved;
        }
        $tables=upbBjTables(loadOdsUploadDocument($path));$monthly=[];
$customSummary=($state['input_basis']??null)==='monthly'&&!empty($state['rules']);$summary=$customSummary&&isset($tables['REKAP'])?upbBjCells($tables['REKAP']):[];
        foreach(DISTR_MONTHS as $i=>$name){if(!isset($tables[$name]))continue;
            $valid=false;foreach(upbBjCells($tables[$name]) as $row)foreach($row as $cell)if(trim($cell->textContent)==='DATABASE JAN SD '.DISTR_SHORT[$i].' '.$year)$valid=true;
            if($valid)$monthly[$i+1]=distrMonthInfo($tables[$name]);
        }
        $result=[];foreach(range(1,12) as $month){$current=$monthly[$month]??[];
            $available=array_sum(array_column($current,'count'))>0;
            foreach(DISTR_GROUPS as $i=>$group){$value=$available?($current[$group]['value']??0)/1000:null;
                $cell=$summary[$i+5][$month+4]??null;if($available&&$cell&&$cell->hasAttributeNS(UPB_O,'value'))$value=(float)$cell->getAttributeNS(UPB_O,'value');$result[$group][$month]=$value;
            }
            foreach(['_GB_1_6'=>4,'_TOTAL'=>13] as $key=>$row){$groups=$row===4?array_slice(DISTR_GROUPS,0,6):DISTR_GROUPS;$value=0;foreach($groups as $group)$value+=$result[$group][$month]??0;
                $cell=$summary[$row][$month+4]??null;if($available&&$cell&&$cell->hasAttributeNS(UPB_O,'value'))$value=(float)$cell->getAttributeNS(UPB_O,'value');$result[$key][$month]=$available?$value:null;
            }
        }return $result;
    }finally{if($opened)$zip->close();@unlink($path);}
}
