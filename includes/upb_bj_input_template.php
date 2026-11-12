<?php
require_once __DIR__.'/upb_bj_template_history.php';
/** Monthly raw inputs remain cumulative; the conclusion shows monthly differences. */
function buildUpbBjInputTemplateOds(string $period, ?string $historyBytes=null, ?string $priorBytes=null): string
{
 if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$parts))throw new InvalidArgumentException('Periode UPB BJ tidak valid.');
 $asset=__DIR__.'/../dashboard/assets/input-templates/upb-bj-kx.ods';$temp=tempnam(sys_get_temp_dir(),'upb-template-');$raw=new ZipArchive();$zip=new ZipArchive();
 try{
  if($historyBytes!==null)file_put_contents($temp,$historyBytes);else copy($asset,$temp);
  if($zip->open($temp)!==true||$raw->open($asset)!==true)throw new RuntimeException('Template UPB BJ tidak dapat dibaca.');
  $doc=upbBjXml($zip->getFromName('content.xml'));
  // A new year starts empty; an existing January upload in this year must survive.
  if($historyBytes!==null&&!str_contains($doc->textContent,'Mulai 01/'.$parts[1].' Hingga ')){
   $zip->close();copy($asset,$temp);if($zip->open($temp)!==true)throw new RuntimeException('Template tahun baru gagal dibaca.');$historyBytes=null;$doc=upbBjXml($zip->getFromName('content.xml'));
  }
  $saved=upbBjTables($doc);$nativeXml=$raw->getFromName('content.xml');
  $prefix=static fn($text)=>preg_replace('/((?:style:name|style:parent-style-name|style:next-style-name|style:apply-style-name|style:data-style-name|style:master-page-name|style:page-layout-name|style:font-name(?:-asian|-complex)?|table:style-name|table:default-cell-style-name|text:style-name)=")/','$1UPBRAW_',$text);
  if($historyBytes!==null){
   $nativeXml=$prefix($nativeXml);$rawDoc=upbBjXml($nativeXml);
   foreach(['automatic-styles','font-face-decls'] as $section){$to=$doc->getElementsByTagNameNS(UPB_O,$section)->item(0);$from=$rawDoc->getElementsByTagNameNS(UPB_O,$section)->item(0);if($to&&$from)upbBjMergeStyles($to,$from,$doc);}
   $styles=upbBjXml($zip->getFromName('styles.xml'));$originalStyles=upbBjXml($prefix($raw->getFromName('styles.xml')));
   foreach(['styles','automatic-styles','master-styles','font-face-decls'] as $section){$to=$styles->getElementsByTagNameNS(UPB_O,$section)->item(0);$from=$originalStyles->getElementsByTagNameNS(UPB_O,$section)->item(0);if($to&&$from)upbBjMergeStyles($to,$from,$styles);}
   $zip->addFromString('styles.xml',$styles->saveXML());
  }
  $nativeDoc=upbBjXml($nativeXml);$native=array_values(upbBjTables($nativeDoc))[0];
  $parent=$doc->getElementsByTagNameNS(UPB_O,'spreadsheet')->item(0);foreach(array_values($saved) as $table)$parent->removeChild($table);
  $names=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];$short=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGT','SEP','OKT','NOV','DES'];$monthly=[];
  for($m=1;$m<=(int)$parts[2];$m++){
   $name=$names[$m-1];
   if(isset($saved[$name])&&$historyBytes!==null)$table=$saved[$name];
   elseif(isset($previousTable))$table=upbBjCloneMonth($previousTable,$name,$parts[1].'-'.sprintf('%02d',$m),$short[$m-1]);
   else{$xml=$nativeDoc->saveXML($native);$xml=str_replace(['Mulai 01/2026 Hingga 01/2026','JAN 2026'],['Mulai 01/'.$parts[1].' Hingga '.sprintf('%02d',$m).'/'.$parts[1],$short[$m-1].' '.$parts[1]],$xml);$fragment=upbBjFragment($doc,$xml);$table=$fragment->firstChild;$table->setAttributeNS(UPB_T,'table:name',$name);}
   $parent->appendChild($table);upbBjRestoreBottomLabels($table);upbBjApplySubtotals($table,true);$monthly[$m]=upbBjBottom($table);$previousTable=$table;
  }
  $fragment=upbBjFragment($doc,upbBjConclusionTable((int)$parts[1]));$conclusion=$fragment->firstChild;$parent->appendChild($conclusion);
  $auto=$doc->getElementsByTagNameNS(UPB_O,'automatic-styles')->item(0);
  $defs='<style:style style:name="UPBSummaryCell" style:family="table-cell"><style:table-cell-properties fo:border="0.5pt solid #000000"/><style:paragraph-properties fo:text-align="center"/><style:text-properties fo:font-size="10pt"/></style:style><style:style style:name="UPBSummaryLabel" style:family="table-cell" style:parent-style-name="UPBSummaryCell"><style:paragraph-properties fo:text-align="left"/><style:text-properties fo:font-weight="bold"/></style:style><style:style style:name="UPBSummaryWide" style:family="table-column"><style:table-column-properties style:column-width="5.5cm"/></style:style><style:style style:name="UPBSummaryMonth" style:family="table-column"><style:table-column-properties style:column-width="2.3cm"/></style:style><style:style style:name="UPBSummaryGap" style:family="table-column"><style:table-column-properties style:column-width="0.3cm"/></style:style>';
  $defs.='<number:number-style style:name="UPBSummaryInteger" number:language="en" number:country="US"><number:number number:decimal-places="0" number:min-integer-digits="1" number:grouping="true"/></number:number-style><style:style style:name="UPBSummaryNumber" style:family="table-cell" style:parent-style-name="UPBSummaryCell" style:data-style-name="UPBSummaryInteger"><style:text-properties fo:language="en" fo:country="US"/></style:style>';
  $defs.='<style:style style:name="UPBSummaryPriorHidden" style:family="table"><style:table-properties table:display="false"/></style:style>';
  foreach(iterator_to_array($auto->childNodes) as $node)if($node instanceof DOMElement&&str_starts_with($node->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0','name'),'UPBSummary'))$auto->removeChild($node);
  $fragment=upbBjFragment($doc,$defs);$auto->appendChild($fragment);
  upbBjFillConclusion($conclusion,$monthly,(int)$parts[2],$names);
  if($priorBytes!==null){
   $prior=upbBjPriorSource($priorBytes,(int)$parts[1]-1,$doc,$names);
   if($prior){$parent->appendChild($prior['table']);upbBjFillConclusion($conclusion,$prior['monthly'],12,array_fill(0,12,$prior['name']),-7,(int)$parts[2]);}
  }
  $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$raw->close();return file_get_contents($temp);
 }finally{if(is_file($temp))unlink($temp);}
}
function upbBjFillConclusion(DOMElement $table,array $monthly,int $last,array $names,int $offset=0,?int $ytdMonth=null): void
{
 $ytdMonth=$ytdMonth??$last;
 $cells=upbBjCells($table);$doc=$table->ownerDocument;
 // Include the initially blank cells as addressable nodes (the summary is small).
 $rows=[];$r=1;foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$c=1;foreach($row->childNodes as $cell)if($cell instanceof DOMElement){$rows[$r][$c++]=$cell;}$r++;}
 $put=static function(int $r,int $c,string $formula,float $value)use($rows,$doc){$cell=$rows[$r][$c];$cell->setAttributeNS(UPB_T,'table:formula','of:='.$formula);$cell->setAttributeNS(UPB_O,'office:value-type','float');$cell->setAttributeNS(UPB_O,'office:value',(string)$value);while($cell->firstChild)$cell->removeChild($cell->firstChild);$cell->appendChild($doc->createElementNS(UPB_X,'text:p',number_format($value,0,'.',',')));$cell->setAttributeNS(UPB_T,'table:style-name','UPBSummaryNumber');};
 foreach(['FARMASI','SOBISCO','CANDY'] as $i=>$label){$row=10+$i+$offset;$sum=0;
  for($m=1;$m<=$last;$m++){$current=$monthly[$m][$label]??null;$previous=$m>1?($monthly[$m-1][$label]??null):null;if(!$current||($m>1&&!$previous))continue;
   $ref="['".$names[$m-1]."'.".$current['ref']."]";$formula=$ref.($previous?"-['".$names[$m-2]."'.".$previous['ref']."]":'');$formula=($previous?'('.$formula.')':$formula).'/1000';$value=($current['value']-($previous['value']??0))/1000;$put($row,$m+3,$formula,$value);$sum+=$value;
  }
  $ytd=0;foreach(range(4,3+$ytdMonth) as $c)$ytd+=(float)$rows[$row][$c]->getAttributeNS(UPB_O,'value');$put($row,2,'SUM([.D'.$row.':.'.chr(67+$ytdMonth).$row.'])',$ytd);$put($row,16,'SUM([.D'.$row.':.O'.$row.'])',$sum);
 }
 foreach(array_merge([2],range(4,$last+3),[16]) as $c){$letter=chr(64+$c);$sum=0;foreach([10+$offset,11+$offset,12+$offset] as $r)$sum+=(float)$rows[$r][$c]->getAttributeNS(UPB_O,'value');$formula=$c===2?'SUM([.D'.(13+$offset).':.'.chr(67+$ytdMonth).(13+$offset).'])':'[.'.$letter.(10+$offset).']+[.'.$letter.(11+$offset).']+[.'.$letter.(12+$offset).']';$put(13+$offset,$c,$formula,$sum);}
}

/** Empty conclusion tables: cumulative-to-month calculations will be defined later. */
function upbBjConclusionTable(int $year): string
{
    $cell=static function(string $text='',string $extra='',string $style='UPBSummaryCell'): string {
        return '<table:table-cell table:style-name="'.$style.'" office:value-type="string"'.$extra.'><text:p>'.htmlspecialchars($text,ENT_XML1|ENT_QUOTES,'UTF-8').'</text:p></table:table-cell>';
    };
    $covered='<table:covered-table-cell/>';
    $xml='<table:table table:name="KESIMPULAN"><table:table-column table:style-name="UPBSummaryWide"/><table:table-column table:style-name="UPBSummaryMonth"/><table:table-column table:style-name="UPBSummaryGap"/><table:table-column table:style-name="UPBSummaryMonth" table:number-columns-repeated="13"/>';
    $names=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    foreach([$year-1,$year] as $index=>$tableYear){
        if($index)$xml.='<table:table-row><table:table-cell table:number-columns-repeated="16"/></table:table-row>';
        $xml.='<table:table-row>'.$cell('UPB BJ KONIMEX TH. '.$tableYear.' (000)',' table:number-columns-spanned="2" table:number-rows-spanned="2"').$covered.'<table:table-cell/>'
            .$cell('PER BULAN TH. '.$tableYear.' (000)',' table:number-columns-spanned="12"').str_repeat($covered,11)
            .$cell('TOTAL (000)',' table:number-rows-spanned="2"').'</table:table-row>';
        $xml.='<table:table-row>'.$covered.$covered.'<table:table-cell/>';
        foreach($names as $name)$xml.=$cell($name);
        $xml.=$covered.'</table:table-row>';
        foreach(['TOTAL FARMASI','TOTAL SOBISCO','TOTAL CANDY','TOTAL'] as $label){
            $xml.='<table:table-row>'.$cell($label,'','UPBSummaryLabel').$cell('','','UPBSummaryNumber').'<table:table-cell/>'.str_repeat($cell('','','UPBSummaryNumber'),13).'</table:table-row>';
        }
    }
    return $xml.'</table:table>';
}

/** Compact hidden cumulative values keep last-year formulas valid without mixing visible monthly tabs. */
function upbBjPriorSource(string $bytes,int $year,DOMDocument $target,array $names): ?array
{
 $temp=tempnam(sys_get_temp_dir(),'upb-prior-');try{
  file_put_contents($temp,$bytes);$zip=new ZipArchive();if($zip->open($temp)!==true)throw new RuntimeException('Data UPB BJ tahun sebelumnya tidak dapat dibaca.');try{$doc=upbBjXml($zip->getFromName('content.xml'));}finally{$zip->close();}
 }finally{unlink($temp);}
 $tables=upbBjTables($doc);$name='_UPB_'.$year;$table=$target->createElementNS(UPB_T,'table:table');$table->setAttributeNS(UPB_T,'table:name',$name);$table->setAttributeNS(UPB_T,'table:style-name','UPBSummaryPriorHidden');$monthly=[];
 $row=$target->createElementNS(UPB_T,'table:table-row');$table->appendChild($row);foreach(['BULAN','FARMASI','SOBISCO','CANDY'] as $label){$cell=$target->createElementNS(UPB_T,'table:table-cell');$cell->setAttributeNS(UPB_O,'office:value-type','string');$cell->appendChild($target->createElementNS(UPB_X,'text:p',$label));$row->appendChild($cell);}
 foreach($names as $i=>$month){$row=$target->createElementNS(UPB_T,'table:table-row');$table->appendChild($row);$bottom=[];if(isset($tables[$month])&&str_contains($tables[$month]->textContent,'Mulai 01/'.$year.' Hingga ')){upbBjRestoreBottomLabels($tables[$month]);upbBjApplySubtotals($tables[$month],true);$bottom=upbBjBottom($tables[$month]);}
  foreach(array_merge([$month],['FARMASI','SOBISCO','CANDY']) as $c=>$label){$cell=$target->createElementNS(UPB_T,'table:table-cell');$row->appendChild($cell);if($c===0){$cell->setAttributeNS(UPB_O,'office:value-type','string');$cell->appendChild($target->createElementNS(UPB_X,'text:p',$month));}elseif(isset($bottom[$label])){$value=$bottom[$label]['value'];$cell->setAttributeNS(UPB_O,'office:value-type','float');$cell->setAttributeNS(UPB_O,'office:value',(string)$value);$cell->appendChild($target->createElementNS(UPB_X,'text:p',(string)$value));$monthly[$i+1][$label]=['ref'=>chr(65+$c).($i+2),'value'=>$value];}}
 }
 return $monthly?['table'=>$table,'monthly'=>$monthly,'name'=>$name]:null;
}
