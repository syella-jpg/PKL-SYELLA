<?php
require_once __DIR__.'/upb_bj_template_history.php';
function upbBjParetoFamily(string $name): string {
 $name=strtoupper(trim(preg_replace('/\s+/u',' ',str_replace(['®','™'],'',$name))));
 // Recognizable multi-word brands stay together; other families use the leading brand word.
 foreach(['GET GIT','POP BALLS','KONICARE','TALITA'] as $brand)if(preg_match('/^'.preg_quote($brand,'/').'(?:\s|$)/u',$name))return $brand;
 $first=preg_split('/\s+/u',$name)[0]??'';return trim($first," \t\n\r\0\x0B.,:;()");
}
function upbBjParetoCategory(string $name): ?string {
 $name=strtoupper(trim($name));if(preg_match('/^(?:FARMA|FARMASI|PHARMA)(?:\b|$)/',$name))return 'FARMASI';if(preg_match('/^(?:SOBIS|SOBISCO|BISKUIT)(?:\b|$)/',$name))return 'SOBISCO';if(preg_match('/^CANDY(?:\b|$)/',$name))return 'CANDY';return null;
}
/** Group variants within their actual subcategory; terminating subtotal labels tolerate moved product rows. */
function upbBjParetoData(DOMElement $table,float $limit=0.8): array {
 $cells=upbBjCells($table);$footer=upbBjBottom($table);$footerStart=$footer?min(array_map(static fn($item)=>(int)substr($item['ref'],1),$footer)):PHP_INT_MAX;$all=['FARMASI'=>[],'SOBISCO'=>[],'CANDY'=>[]];$pending=[];$started=false;$skipHeader=false;$category=null;
 $flush=static function(?string $category)use(&$pending,&$all):void{foreach($pending as $record){$group=$category??$record['category'];if($group===null)continue;$family=upbBjParetoFamily($record['name']);if($family==='')continue;if(!isset($all[$group][$family]))$all[$group][$family]=['name'=>$family,'value'=>0.0,'refs'=>[]];$all[$group][$family]['value']+=$record['value'];$all[$group][$family]['refs'][]=$record['ref'];}$pending=[];};
 foreach($cells as $r=>$row){$label=strtoupper(trim(preg_replace('/\s+/',' ',$row[1]->textContent??'')));
  if($label==='NO.'){$started=true;$skipHeader=true;continue;}if(!$started)continue;
  if($r>=$footerStart){$flush(null);break;}
  if($skipHeader){$skipHeader=false;if(strtoupper(trim($row[6]->textContent??''))==='NAMA')continue;}
  if(preg_match('/^SUB TOTAL GB .+, SUB KEL\. BARANG (.+)$/',$label,$match)){$flush(upbBjParetoCategory($match[1]));$category=null;continue;}
  if(preg_match('/^SUB TOTAL GB /',$label)){$flush(null);$category=null;continue;}
  if($label==='TOTAL'){$flush(null);break;}
  $declared=upbBjParetoCategory($row[3]->textContent??'');if($declared!==null)$category=$declared;
  $name=trim($row[6]->textContent??'');$cell=$row[9]??null;$raw=$cell?$cell->getAttributeNS(UPB_O,'value'):'';
  if($name===''||$raw===''||!is_numeric($raw)||!is_finite((float)$raw))continue;
  $pending[]=['name'=>$name,'value'=>(float)$raw,'ref'=>'I'.$r,'category'=>$category];
 }
 $bottom=upbBjBottom($table);$result=[];
 foreach($all as $group=>$families){$denominator=isset($bottom[$group])?(float)$bottom[$group]['value']:0;$totalRefs=[];$sum=0.0;foreach($families as $family){$sum+=$family['value'];$totalRefs=array_merge($totalRefs,$family['refs']);}
  $useBottom=$denominator>0;if(!$useBottom)$denominator=$sum;
  $sorted=array_values(array_filter($families,static fn($family)=>$family['value']>0));usort($sorted,static fn($a,$b)=>($b['value']<=>$a['value'])?:strcmp($a['name'],$b['name']));$selected=[];$cumulative=0.0;
  if($denominator>0)foreach($sorted as $family){if($cumulative>=$limit-1e-12)break;$family['share']=$family['value']/$denominator;$cumulative+=$family['share'];$family['cumulative']=$cumulative;$selected[]=$family;}
  $result[$group]=['selected'=>$selected,'total'=>$denominator,'denominator_ref'=>$useBottom?$bottom[$group]['ref']:null,'total_refs'=>$totalRefs,'coverage'=>$cumulative];
 }
 return $result;
}
/** Fill only D:F; extra Pareto rows extend the upper block without moving its annual summaries. */
function upbBjRenderPareto(DOMElement $report,array &$rows,array $data,string $period,DOMDocument $doc): array {
 $labels=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGS','SEP','OKT','NOV','DES'];$date=$labels[(int)substr($period,5,2)-1].' '.substr($period,0,4);
 $content=[['kind'=>'title','text'=>'PARETO']];
 foreach(['SOBISCO','CANDY','FARMASI'] as $group){$content[]=['kind'=>'header','text'=>$group.' '.$date];$items=$data[$group]['selected'];if(!$items)$content[]=['kind'=>'blank'];foreach($items as $item)$content[]=['kind'=>'item','group'=>$group,'item'=>$item];$content[]=['kind'=>'gap'];}
 $height=max(21,count($content));for($r=count($rows)+1;$r<=$height;$r++){$row=$doc->createElementNS(UPB_T,'table:table-row');$report->appendChild($row);$rows[$r]=$row;}
 $pending=[];
 for($r=1;$r<=$height;$r++){
  $entry=$content[$r-1]??['kind'=>'gap'];$heading=in_array($entry['kind'],['title','header'],true);
  foreach([4,5,6] as $column){$old=upbBjColumnCell($rows[$r],$column);$cell=$doc->createElementNS(UPB_T,$heading&&$column>4?'table:covered-table-cell':'table:table-cell');$rows[$r]->replaceChild($cell,$old);
   if($heading&&$column>4)continue;
   $style=$heading?'UPBParetoHeading':($entry['kind']==='gap'?'UPBParetoGap':($column===4?'UPBParetoLabel':($column===6?'UPBParetoPercent':'UPBParetoNumber')));$cell->setAttributeNS(UPB_T,'table:style-name',$style);
   if($heading){$cell->setAttributeNS(UPB_T,'table:number-columns-spanned','3');$cell->setAttributeNS(UPB_O,'office:value-type','string');$cell->appendChild($doc->createElementNS(UPB_X,'text:p',$entry['text']));}
   elseif($entry['kind']==='item'){
    $item=$entry['item'];if($column===4){$cell->setAttributeNS(UPB_O,'office:value-type','string');$cell->appendChild($doc->createElementNS(UPB_X,'text:p',$item['name']));}
    elseif($column===5){$cell->setAttributeNS(UPB_O,'office:value-type','float');$cell->setAttributeNS(UPB_O,'office:value',(string)$item['value']);$cell->appendChild($doc->createElementNS(UPB_X,'text:p',number_format($item['value'],0,'.',',')));}
    else{$cell->setAttributeNS(UPB_O,'office:value-type','float');$cell->setAttributeNS(UPB_O,'office:value',(string)$item['share']);$cell->appendChild($doc->createElementNS(UPB_X,'text:p',number_format($item['share']*100,2,'.',',').'%'));}
   }
  }
  if($entry['kind']==='item')$pending[$r]=$entry;
 }
 return ['height'=>$height,'pending'=>$pending];
}
function upbBjParetoStyles(): string {
 $xml='<number:percentage-style style:name="UPBParetoPercentFormat" number:language="en" number:country="US"><number:number number:decimal-places="2" number:min-integer-digits="1"/><number:text>%</number:text></number:percentage-style>';
 foreach(['Label','Number','Percent','Heading','Gap'] as $kind){$number=$kind==='Number'?' style:data-style-name="UPBSummaryInteger"':($kind==='Percent'?' style:data-style-name="UPBParetoPercentFormat"':'');$border=$kind==='Gap'?'none':'0.5pt solid #000000';$align=in_array($kind,['Number','Percent'],true)?'right':($kind==='Heading'?'center':'left');
  $xml.='<style:style style:name="UPBPareto'.$kind.'" style:family="table-cell"'.$number.'><style:table-cell-properties fo:border="'.$border.'" fo:padding="0.03cm" fo:wrap-option="wrap" style:shrink-to-fit="true"/><style:paragraph-properties fo:text-align="'.$align.'"/><style:text-properties fo:font-size="9pt"'.($kind==='Heading'?' fo:font-weight="bold"':'').'/></style:style>';
 }
 return $xml;
}
function upbBjLinkPareto(array $rendered,array $data,array $rows,int $offset): void {
 foreach($rendered['pending'] as $r=>$entry){$item=$entry['item'];$group=$data[$entry['group']];$move=static fn($ref)=>'[.I'.((int)substr($ref,1)+$offset).']';$refs=array_map($move,$item['refs']);$amount=upbBjColumnCell($rows[$r],5);$amount->setAttributeNS(UPB_T,'table:formula','of:=SUM('.implode(';',$refs).')');$denominator=$group['denominator_ref']?$move($group['denominator_ref']):'SUM('.implode(';',array_map($move,$group['total_refs'])).')';upbBjColumnCell($rows[$r],6)->setAttributeNS(UPB_T,'table:formula','of:=[.E'.$r.']/'.$denominator);}
}
