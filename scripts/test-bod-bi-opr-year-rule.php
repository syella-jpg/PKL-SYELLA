<?php
require_once __DIR__.'/../includes/bod_bi_input_template.php';
require_once __DIR__.'/../includes/ods_upload_xml.php';
foreach([2025,2026,2028] as $year){
 $path=tempnam(sys_get_temp_dir(),'bod-year-rule-');
 try{
  file_put_contents($path,buildBodBiInputTemplateOds('BOD BI.Opr & BOD BI.SDM',$year.'-02'));$doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);
  foreach(['JANUARI'=>[100,40],'FEBRUARI'=>[180,70]] as $name=>$values){$cells=upbBjCells($tables[$name]);$layout=bodBiRawLayout($tables[$name]);foreach(['SUB TOTAL I','SUB TOTAL III'] as $i=>$label)if(isset($layout['totals'][$label]))upbBjSetFormula($cells[$layout['totals'][$label]][7],'0',$values[$i]);if($year>=2026&&($layout['totals']['TOTAL']!==$layout['totals']['SUB TOTAL II']+1||isset($layout['totals']['SUB TOTAL III'])))throw new RuntimeException('Third section not removed');}
  linkBodBiOprRawMonths($doc,$tables['BOD BI.Opr'],$year.'-02');$cells=upbBjCells($tables['BOD BI.Opr']);$include=$year===2025;
  foreach([3,4] as $r)if(str_contains($cells[$r][3]->getAttributeNS(UPB_T,'formula'),'SUB TOTAL III')!==$include)throw new RuntimeException('Wrong year formula');
  foreach([[3,3,$include?140:100],[4,3,$include?250:180],[4,2,$include?110:80],[16,2,$include?250:180]] as [$r,$c,$expected])if((float)$cells[$r][$c]->getAttributeNS(UPB_O,'value')!==(float)$expected)throw new RuntimeException('Wrong cached monthly/YTD result');
 }finally{@unlink($path);}
}
echo "PASS: 2025 includes subtotal III; 2026 and future years use only subtotal I, including cached cumulative, monthly RP and YTD.\n";
