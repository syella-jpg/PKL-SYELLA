<?php
const UPB_T='urn:oasis:names:tc:opendocument:xmlns:table:1.0';
const UPB_O='urn:oasis:names:tc:opendocument:xmlns:office:1.0';
const UPB_X='urn:oasis:names:tc:opendocument:xmlns:text:1.0';
function upbBjXml(string $xml): DOMDocument {$d=new DOMDocument();if(!@$d->loadXML($xml,LIBXML_NONET))throw new InvalidArgumentException('File ODS UPB BJ tidak valid.');return $d;}
function upbBjTables(DOMDocument $doc): array {$out=[];foreach($doc->getElementsByTagNameNS(UPB_T,'table') as $t)$out[$t->getAttributeNS(UPB_T,'name')]=$t;return $out;}
/** Sparse cell positions: added/deleted product rows do not change label-based lookup. */
function upbBjCells(DOMElement $table): array {
 $out=[];$r=1;foreach($table->childNodes as $row){if(!$row instanceof DOMElement||$row->localName!=='table-row')continue;$c=1;
 foreach($row->childNodes as $cell){if(!$cell instanceof DOMElement||!in_array($cell->localName,['table-cell','covered-table-cell'],true))continue;
 $count=max(1,(int)$cell->getAttributeNS(UPB_T,'number-columns-repeated'));if(trim($cell->textContent)!==''||$cell->hasAttributeNS(UPB_O,'value')||$cell->hasAttributeNS(UPB_T,'formula'))$out[$r][$c]=$cell;$c+=$count;}
 $r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}return $out;
}
function upbBjBottom(DOMElement $table): array {
 $found=[];foreach(upbBjCells($table) as $r=>$cells)foreach($cells as $c=>$cell){$label=strtoupper(trim($cell->textContent));if(!in_array($label,['FARMASI','SOBISCO','CANDY','TOTAL'],true)||$c<4)continue;
 $value=$cells[9]??null;$raw=$value?$value->getAttributeNS(UPB_O,'value'):'';$found[$label]=['ref'=>'I'.$r,'value'=>$raw!==''?(float)$raw:0];}
 return $found;
}
/** Canonical database store; retain any uploads saved under the earlier table name. */
function ensureUpbBjInputStore($conn): void {
 $conn->query('CREATE TABLE IF NOT EXISTS `upb bj kx` (report_period CHAR(7) PRIMARY KEY, base64 LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
 try{$conn->query('INSERT IGNORE INTO `upb bj kx` (report_period,base64) SELECT report_period,base64 FROM upb_bj_input_files');}
 catch(mysqli_sql_exception $e){if($e->getCode()!==1146)throw $e;}
}
function findUpbBjTemplate($conn,string $period): ?array {
 ensureUpbBjInputStore($conn);
 try{$q=$conn->prepare('SELECT report_period,base64 FROM `upb bj kx` WHERE report_period BETWEEN ? AND ? ORDER BY report_period DESC LIMIT 1');$first=substr($period,0,4).'-01';$last=substr($period,0,4).'-12';$q->bind_param('ss',$first,$last);$q->execute();$r=$q->get_result()->fetch_assoc();$q->close();return $r?:null;}catch(mysqli_sql_exception $e){if($e->getCode()===1146)return null;throw $e;}
}
function validateUpbBjOds(string $bytes,string $period): void {
 if(strlen($bytes)>50*1024*1024)throw new InvalidArgumentException('File maksimal 50 MB.');$p=tempnam(sys_get_temp_dir(),'upb-validate-');try{file_put_contents($p,$bytes);$z=new ZipArchive();if($z->open($p)!==true)throw new InvalidArgumentException('Gunakan template ODS UPB BJ.');try{$info=$z->statName('content.xml');if(!$info||$info['size']>64*1024*1024)throw new InvalidArgumentException('Isi ODS terlalu besar.');$xml=$z->getFromName('content.xml');}finally{$z->close();}
 $tables=upbBjTables(upbBjXml($xml));$names=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
 for($m=1;$m<=(int)substr($period,5,2);$m++){$name=$names[$m-1];if(!isset($tables[$name]))throw new InvalidArgumentException('Sheet '.$name.' tidak ditemukan.');$text=$tables[$name]->textContent;$year=substr($period,0,4);if(!str_contains($text,'Mulai 01/'.$year.' Hingga '.sprintf('%02d',$m).'/'.$year))throw new InvalidArgumentException('Periode sheet '.$name.' tidak sesuai.');$bottom=upbBjBottom($tables[$name]);foreach(['FARMASI','SOBISCO','CANDY','TOTAL'] as $label)if(!isset($bottom[$label]))throw new InvalidArgumentException('Ringkasan '.$label.' pada '.$name.' tidak ditemukan.');}
 }finally{unlink($p);}
}
function upbBjFragment(DOMDocument $target,string $xml): DOMDocumentFragment {
 $xp=new DOMXPath($target);$decl='';foreach($xp->query('namespace::*',$target->documentElement) as $ns){if($ns->nodeName==='xmlns:xml')continue;$decl.=' '.$ns->nodeName.'="'.htmlspecialchars($ns->nodeValue,ENT_XML1|ENT_QUOTES,'UTF-8').'"';}
 $d=upbBjXml('<upb-wrapper'.$decl.'>'.$xml.'</upb-wrapper>');$fragment=$target->createDocumentFragment();foreach(iterator_to_array($d->documentElement->childNodes) as $child)$fragment->appendChild($target->importNode($child,true));return $fragment;
}

function upbBjMergeStyles(DOMElement $to,DOMElement $from,DOMDocument $doc): void {
 $ns='urn:oasis:names:tc:opendocument:xmlns:style:1.0';$known=[];foreach($to->childNodes as $node)if($node instanceof DOMElement)$known[$node->getAttributeNS($ns,'name')]=true;
 foreach(iterator_to_array($from->childNodes) as $node){if(!$node instanceof DOMElement||$node->localName==='default-style')continue;$name=$node->getAttributeNS($ns,'name');if($name!==''&&isset($known[$name]))continue;$to->appendChild($doc->importNode($node,true));$known[$name]=true;}
}
/** Insert a cell at its actual column, splitting repeated empty cells when needed. */
function upbBjColumnCell(DOMElement $row,int $column): DOMElement {
 $index=1;$doc=$row->ownerDocument;
 foreach(iterator_to_array($row->childNodes) as $cell){if(!$cell instanceof DOMElement||!in_array($cell->localName,['table-cell','covered-table-cell'],true))continue;$repeat=max(1,(int)$cell->getAttributeNS(UPB_T,'number-columns-repeated'));
 if($column>=$index&&$column<$index+$repeat){if($repeat===1)return $cell;$before=$column-$index;$after=$repeat-$before-1;if($before){$copy=$cell->cloneNode(true);$copy->setAttributeNS(UPB_T,'table:number-columns-repeated',(string)$before);$row->insertBefore($copy,$cell);}$single=$cell->cloneNode(true);$single->removeAttributeNS(UPB_T,'number-columns-repeated');$row->insertBefore($single,$cell);if($after)$cell->setAttributeNS(UPB_T,'table:number-columns-repeated',(string)$after);else $row->removeChild($cell);return $single;}$index+=$repeat;
 }
 while($index<=$column){$cell=$doc->createElementNS(UPB_T,'table:table-cell');$row->appendChild($cell);$index++;}return $cell;
}
function upbBjSetFormula(DOMElement $cell,string $formula,float $value): void {
 $cell->setAttributeNS(UPB_T,'table:formula','of:='.$formula);$cell->setAttributeNS(UPB_O,'office:value-type','float');$cell->setAttributeNS(UPB_O,'office:value',(string)$value);while($cell->firstChild)$cell->removeChild($cell->firstChild);$cell->appendChild($cell->ownerDocument->createElementNS(UPB_X,'text:p',(string)$value));
}
function upbBjApplySubtotals(DOMElement $table,bool $preserveExisting=false): void {
 $put=static function(DOMElement $cell,string $formula,float $value)use($preserveExisting):void {if($preserveExisting&&$cell->hasAttributeNS(UPB_T,'formula'))return;upbBjSetFormula($cell,$formula,$value);};
 $cells=upbBjCells($table);$rowNodes=[];$r=1;foreach($table->childNodes as $node)if($node instanceof DOMElement&&$node->localName==='table-row'){$rowNodes[$r]=$node;$r+=max(1,(int)$node->getAttributeNS(UPB_T,'number-rows-repeated'));}
 $start=null;$subgroups=[];$groups=[];$totals=[];$norm=static fn($s)=>strtoupper(trim(preg_replace('/\s+/',' ',$s)));
 foreach($cells as $r=>$row){
  $label=$norm($row[1]->textContent??'');
  if($label==='NO.'){$start=$r+2;continue;}
  if($start===null)continue;
  if(preg_match('/^SUB TOTAL GB (.+), SUB KEL\. BARANG (.+)$/',$label,$m)){
   foreach([7=>'G',9=>'I'] as $c=>$letter){$sum=0;for($p=$start;$p<$r;$p++)$sum+=isset($cells[$p][$c])?(float)$cells[$p][$c]->getAttributeNS(UPB_O,'value'):0;$cell=upbBjColumnCell($rowNodes[$r],$c);$put($cell,$r>$start?'SUM([.'.$letter.$start.':.'.$letter.($r-1).'])':'0',$sum);$cells[$r][$c]=$cell;}
   $subgroups[$m[1]][]=$r;$start=$r+1;
  }elseif(preg_match('/^SUB TOTAL GB (.+)$/',$label,$m)){
   $parts=$subgroups[$m[1]]??[];
   foreach([7=>'G',9=>'I'] as $c=>$letter){$sum=0;foreach($parts as $p)$sum+=(float)$cells[$p][$c]->getAttributeNS(UPB_O,'value');$cell=upbBjColumnCell($rowNodes[$r],$c);$put($cell,$parts?implode('+',array_map(static fn($p)=>'[.'.$letter.$p.']',$parts)):'0',$sum);$cells[$r][$c]=$cell;}
   $groups[$m[1]]=$r;$totals[]=$r;$start=$r+1;
  }elseif($label==='TOTAL'){
   foreach([7=>'G',9=>'I'] as $c=>$letter){$sum=0;foreach($totals as $p)$sum+=(float)$cells[$p][$c]->getAttributeNS(UPB_O,'value');$cell=upbBjColumnCell($rowNodes[$r],$c);$put($cell,$totals?implode('+',array_map(static fn($p)=>'[.'.$letter.$p.']',$totals)):'0',$sum);$cells[$r][$c]=$cell;}
   break;
  }
 }
 // Follow the group labels when product insertion/deletion moves the final summary.
 $bottom=upbBjBottom($table);$values=[];
 foreach(['FARMASI'=>['ETHICAL','GB1','GB2','GB3','GB4'],'SOBISCO'=>['GB5'],'CANDY'=>['GB6']] as $label=>$keys){if(!isset($bottom[$label]))continue;$refs=[];$sum=0;foreach($groups as $key=>$p)if(in_array(str_replace(' ','',$key),$keys,true)){$refs[]='[.I'.$p.']';$sum+=(float)$cells[$p][9]->getAttributeNS(UPB_O,'value');}$row=(int)substr($bottom[$label]['ref'],1);$put(upbBjColumnCell($rowNodes[$row],9),$refs?implode('+',$refs):'0',$sum);$values[$label]=['row'=>$row,'value'=>$sum];}
 if(isset($bottom['TOTAL'])&&$values){$row=(int)substr($bottom['TOTAL']['ref'],1);$put(upbBjColumnCell($rowNodes[$row],9),implode('+',array_map(static fn($v)=>'[.I'.$v['row'].']',$values)),array_sum(array_column($values,'value')));}
}
/** Copy the previous structure and formulas while leaving new product input cells empty. */
function upbBjCloneMonth(DOMElement $previous,string $name,string $period,string $short): DOMElement {
 $table=$previous->cloneNode(true);$oldName=$previous->getAttributeNS(UPB_T,'name');$table->setAttributeNS(UPB_T,'table:name',$name);
 $xpath=new DOMXPath($table->ownerDocument);
 foreach($xpath->query('.//text()',$table) as $text){$text->nodeValue=preg_replace('/Mulai 01\/20\d{2} Hingga \d{2}\/20\d{2}/','Mulai 01/'.substr($period,0,4).' Hingga '.substr($period,5,2).'/'.substr($period,0,4),$text->nodeValue);if(preg_match('/^(?:JAN|FEB|MAR|APR|MEI|MAY|JUN|JUL|AGT|AGS|AUG|SEP|OKT|OCT|NOV|DES|DEC)\s+20\d{2}$/',trim($text->nodeValue)))$text->nodeValue=$short.' '.substr($period,0,4);}
 $rows=[];foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row')$rows[]=$row;
 foreach($rows as $row){
  // Make G/I independently addressable, including templates with repeated empty cells.
  foreach([7,9] as $column){$cell=upbBjColumnCell($row,$column);if($cell->hasAttributeNS(UPB_T,'formula'))continue;$type=$cell->getAttributeNS(UPB_O,'value-type');if(!in_array($type,['float','currency','percentage'],true)&&!is_numeric(trim($cell->textContent)))continue;
   foreach(['value','string-value','date-value','boolean-value','value-type'] as $attr)$cell->removeAttributeNS(UPB_O,$attr);while($cell->firstChild)$cell->removeChild($cell->firstChild);
  }
 }
 upbBjRestoreBottomLabels($table);
 $bottom=upbBjBottom($table);$summaryStart=$bottom?min(array_map(static fn($v)=>(int)substr($v['ref'],1),$bottom)):PHP_INT_MAX;
 $sparse=upbBjCells($table);$position=1;$start=null;
 foreach($rows as $row){
  $label=strtoupper(trim($sparse[$position][1]->textContent??''));
  if($label==='NO.')$start=$position+2;
  elseif($start!==null&&$position>=$start){
   if($label==='TOTAL'||$position>=$summaryStart)break;
   if(!str_starts_with($label,'SUB TOTAL GB'))foreach(range(2,8) as $column){
    $cell=upbBjColumnCell($row,$column);if($cell->hasAttributeNS(UPB_T,'formula'))continue;
    foreach(['value','string-value','date-value','boolean-value','value-type'] as $attr)$cell->removeAttributeNS(UPB_O,$attr);
    while($cell->firstChild)$cell->removeChild($cell->firstChild);
   }
  }
  $position+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));
 }
 foreach($table->getElementsByTagNameNS(UPB_T,'table-cell') as $cell){if(!$cell->hasAttributeNS(UPB_T,'formula'))continue;$formula=$cell->getAttributeNS(UPB_T,'formula');$formula=str_replace(["['".$oldName."'.",'['.$oldName.'.'],["['".$name."'.",'['.$name.'.'],$formula);upbBjSetFormula($cell,preg_replace('/^of:=/','',$formula),0);}
 return $table;
}
/** Recover only missing fixed summary labels, using its month/year marker rather than row numbers. */
function upbBjRestoreBottomLabels(DOMElement $table): void {
 $cells=upbBjCells($table);$nodes=[];$r=1;foreach($table->childNodes as $node)if($node instanceof DOMElement&&$node->localName==='table-row'){$nodes[$r]=$node;$r+=max(1,(int)$node->getAttributeNS(UPB_T,'number-rows-repeated'));}
 $marker=null;foreach($cells as $r=>$row)foreach($row as $c=>$cell)if($c<=5&&preg_match('/^(?:JAN|FEB|MAR|APR|MEI|MAY|JUN|JUL|AGT|AGS|AUG|SEP|OKT|OCT|NOV|DES|DEC)\s+20\d{2}$/',strtoupper(trim($cell->textContent))))$marker=$r;
 if($marker===null)return;
 foreach(['FARMASI','SOBISCO','CANDY','TOTAL'] as $i=>$label){if(!isset($nodes[$marker+$i]))continue;$cell=upbBjColumnCell($nodes[$marker+$i],6);if(trim($cell->textContent)!==''||$cell->hasAttributeNS(UPB_T,'formula'))continue;$cell->setAttributeNS(UPB_O,'office:value-type','string');$cell->appendChild($table->ownerDocument->createElementNS(UPB_X,'text:p',$label));}
}
