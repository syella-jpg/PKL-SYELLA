<?php
require_once __DIR__.'/../includes/bod_bi_input_store.php';
require_once __DIR__.'/../includes/bod_bi_prior_year.php';
$p=tempnam(sys_get_temp_dir(),'bod-prior-test-');
try{
 file_put_contents($p,buildBodBiInputTemplateOds(BOD_BI_INPUT_SECTION,'2025-02'));$doc=loadOdsUploadDocument($p);$tables=upbBjTables($doc);
 foreach(['JANUARI'=>[100,20,10],'FEBRUARI'=>[180,50,30]] as $name=>$values){$cells=upbBjCells($tables[$name]);$rows=[];$at=1;foreach($tables[$name]->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$rows[$at]=$row;$at+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}foreach(['GAJI, UPAH, DLL','RECRUITMENT','SUB TOTAL III'] as $i=>$label){$ref=bodBiRawActualRef($tables[$name],$label);preg_match('/([A-Z]+)(\d+)/',$ref,$m);upbBjSetFormula(upbBjColumnCell($rows[(int)$m[2]],7),'0',$values[$i]);}$layout=bodBiRawLayout($tables[$name]);upbBjSetFormula($cells[$layout['totals']['SUB TOTAL I']][7],'0',$values[0]);}
 $z=new ZipArchive();$z->open($p);$z->addFromString('content.xml',$doc->saveXML());$z->close();$prior=file_get_contents($p);
 $bytes=bodBiAddPriorYear(buildBodBiInputTemplateOds(BOD_BI_INPUT_SECTION,'2026-02'),'2026-02',$prior);file_put_contents($p,$bytes);validateBodBiInputBytes($bytes,'2026-02');$tables=upbBjTables(loadOdsUploadDocument($p));$opr=upbBjCells($tables['BOD BI.Opr']);$sdm=upbBjCells($tables['BOD BI.SDM']);
 foreach([[$opr[3][5],110],[$opr[4][4],100],[$opr[16][4],210],[$sdm[13][2],180],[$sdm[14][2],50],[$sdm[15][2],230]] as [$cell,$expected])if((float)$cell->getAttributeNS(UPB_O,'value')!== (float)$expected)throw new RuntimeException('Synthetic mismatch '.$cell->getAttributeNS(UPB_O,'value').' expected '.$expected);
 if($tables['2025 JANUARI']->getAttributeNS(UPB_T,'style-name')!=='BODPriorHidden')throw new RuntimeException('Prior raw sheet must be hidden');
 echo "PASS: prior database snapshot supplies OPR and SDM, monthly differences, YTD, totals and hidden source sheets; upload validation accepts workbook.\n";
}finally{@unlink($p);}
