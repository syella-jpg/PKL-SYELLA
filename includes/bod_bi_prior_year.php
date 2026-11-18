<?php
/** Link prior-year summaries to archived raw monthly values kept in hidden sheets. */
function bodBiAddPriorYear(string $bytes,string $period,?string $prior): string {
 if($prior===null)return $bytes;
 $path=tempnam(sys_get_temp_dir(),'bod-prior-');$sourcePath=tempnam(sys_get_temp_dir(),'bod-prior-source-');$zip=new ZipArchive();
 try{
  file_put_contents($path,$bytes);file_put_contents($sourcePath,$prior);$source=loadOdsUploadDocument($sourcePath);$sources=upbBjTables($source);$zip->open($path);$doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);$parent=$tables['BOD BI.Opr']->parentNode;$year=(int)substr($period,0,4)-1;$last=(int)substr($period,5,2);$dest=[];foreach(['BOD BI.Opr','BOD BI.SDM'] as $name){$r=1;foreach($tables[$name]->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){if($name==='BOD BI.Opr'&&$r>=3&&$r<=16)foreach([4,5] as $c)$dest[$name][$r][$c]=upbBjColumnCell($row,$c);if($name==='BOD BI.SDM'&&$r>=13&&$r<=15)foreach(array_merge([2],range(4,16)) as $c)$dest[$name][$r][$c]=upbBjColumnCell($row,$c);$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}}$opr=$dest['BOD BI.Opr'];$sdm=$dest['BOD BI.SDM'];$auto=$doc->getElementsByTagNameNS(UPB_O,'automatic-styles')->item(0);$style=$doc->createElementNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0','style:style');$style->setAttribute('style:name','BODPriorHidden');$style->setAttribute('style:family','table');$props=$doc->createElementNS('urn:oasis:names:tc:opendocument:xmlns:style:1.0','style:table-properties');$props->setAttributeNS(UPB_T,'table:display','false');$style->appendChild($props);$auto->appendChild($style);$monthly=[[],[],[]];$previous=[0,0,0];
  foreach(BOD_BI_MONTHS as $i=>$name){
   $values=$previous;if(isset($sources[$name])){
    $values[0]=bodBiRawLookupValue($sources[$name],'SUB TOTAL I');if($year<2026)$values[0]+=bodBiRawLookupValue($sources[$name],'SUB TOTAL III');
    $values[1]=bodBiRawLookupValue($sources[$name],'GAJI, UPAH, DLL');$values[2]=bodBiRawLookupValue($sources[$name],'RECRUITMENT');
   }
   $hidden=$doc->createElementNS(UPB_T,'table:table');$hiddenName=$year.' '.$name;$hidden->setAttributeNS(UPB_T,'table:name',$hiddenName);$hidden->setAttributeNS(UPB_T,'table:style-name','BODPriorHidden');$row=$doc->createElementNS(UPB_T,'table:table-row');$hidden->appendChild($row);$parent->appendChild($hidden);
   foreach($values as $j=>$value){$cell=$doc->createElementNS(UPB_T,'table:table-cell');$cell->setAttributeNS(UPB_O,'office:value-type','float');$cell->setAttributeNS(UPB_O,'office:value',(string)$value);$row->appendChild($cell);$monthly[$j][$i]=isset($sources[$name])?$value-$previous[$j]:0;$previous[$j]=$value;}
   $r=$i+3;$ref="['".$hiddenName."'.A1]";upbBjSetFormula($opr[$r][5],$ref,$values[0]);$expr=$i===0?'[.E'.$r.']':'[.E'.$r.']-[.E'.($r-1).']';upbBjSetFormula($opr[$r][4],$expr,$monthly[0][$i]);
   foreach([13=>1,14=>2] as $dest=>$j){$col=$i+4;$sourceCol=$j===1?'B':'C';$expr="['".$hiddenName."'.".$sourceCol.'1]';if($i>0)$expr.="-['".$year.' '.BOD_BI_MONTHS[$i-1]."'.".$sourceCol.'1]';upbBjSetFormula($sdm[$dest][$col],$expr,$monthly[$j][$i]);}
  }
  foreach([15=>12,16=>$last] as $r=>$count)upbBjSetFormula($opr[$r][4],'SUM([.D3:.D'.($count+2).'])',array_sum(array_slice($monthly[0],0,$count)));
  $end=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($last+3);
  foreach([13=>1,14=>2] as $r=>$j){upbBjSetFormula($sdm[$r][2],'SUM([.D'.$r.':.'.$end.$r.'])',array_sum(array_slice($monthly[$j],0,$last)));upbBjSetFormula($sdm[$r][16],'SUM([.D'.$r.':.O'.$r.'])',array_sum($monthly[$j]));}
  foreach(array_merge([2],range(4,16)) as $c){$col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);$v=(float)$sdm[13][$c]->getAttributeNS(UPB_O,'value')+(float)$sdm[14][$c]->getAttributeNS(UPB_O,'value');upbBjSetFormula($sdm[15][$c],'SUM([.'.$col.'13:.'.$col.'14])',$v);}
  $zip->addFromString('content.xml',$doc->saveXML());$zip->close();return file_get_contents($path);
 }finally{@unlink($path);@unlink($sourcePath);}
}
