<?php
require __DIR__.'/../includes/data_pareto_upb_formulas.php';
function paretoSynthetic(int $count): DOMDocument {
 $d=new DOMDocument();$table=$d->createElementNS(UPB_T,'table:table');$d->appendChild($table);
 $add=static function(array $values)use($d,$table):void{
  $row=$d->createElementNS(UPB_T,'table:table-row');$table->appendChild($row);
  foreach($values as $value){
   $cell=$d->createElementNS(UPB_T,'table:table-cell');$row->appendChild($cell);
   if(is_string($value)&&str_starts_with($value,'='))upbBjSetFormula($cell,substr($value,1),999);
   elseif(is_numeric($value)){$cell->setAttributeNS(UPB_O,'office:value',(string)$value);}
   elseif($value!==null)$cell->appendChild($d->createElementNS(UPB_X,'text:p',$value));
  }
 };
 $add(['BERDASARKAN UNIT PRODUK',null,'BERDASARKAN RUPIAH PRODUK']);
 $add(['Nama Produk','Unit','Nama Produk','Rupiah']);
 for($i=1;$i<=$count;$i++)$add(['Produk buatan '.$i,$i,'Produk buatan '.$i,$i*10]);
 $add(['TOTAL','=SUM([.B3:.B2500])','TOTAL','=SUM([.$D$3:.$D$2500])']);
 // A following block must not get included in the preceding total.
 $add(['Nama Produk','Unit','Nama Produk','Rupiah']);
 $add(['Produk buatan lain',7,'Produk buatan lain',70]);
 $add(['TOTAL','=SUM([.B1:.B2])','TOTAL','=SUM([.D1:.D2])']);
 return $d;
}
foreach([1,5,2600] as $count){
 $doc=paretoSynthetic($count);refreshDataParetoUpbSums($doc->documentElement);$cells=upbBjCells($doc->documentElement);$r=$count+3;
 if($cells[$r][2]->getAttributeNS(UPB_T,'formula')!=='of:=SUM([.B3:.B'.($r-1).'])')throw new RuntimeException('Unit range incorrect');
 if((float)$cells[$r][2]->getAttributeNS(UPB_O,'value')!==(float)($count*($count+1)/2))throw new RuntimeException('Unit cached sum incorrect');
 if($cells[$r][4]->getAttributeNS(UPB_T,'formula')!=='of:=SUM([.$D$3:.$D$'.($r-1).'])')throw new RuntimeException('Rupiah range/locks incorrect');
 if((float)$cells[$r+3][2]->getAttributeNS(UPB_O,'value')!==7.0)throw new RuntimeException('Next table mixed into sum');
}
echo "PASS synthetic Pareto: shrinking/growing to 2600 rows, side-by-side unit/rupiah tables, locked references, cached totals, separate following blocks.\n";
require_once __DIR__.'/../includes/upb_distr_input.php';
$sample=paretoSynthetic(5);
$doc=upbBjXml('<office:document-content xmlns:office="'.UPB_O.'" xmlns:table="'.UPB_T.'" xmlns:text="'.UPB_X.'"><office:body><office:spreadsheet/></office:body></office:document-content>');
$table=$doc->importNode($sample->documentElement,true);$table->setAttributeNS(UPB_T,'table:name','DATA PARETO UPB');$doc->getElementsByTagNameNS(UPB_O,'spreadsheet')->item(0)->appendChild($table);
$path=tempnam('/tmp','pareto-synthetic-');$zip=new ZipArchive();
try{
 $zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('mimetype','application/vnd.oasis.opendocument.spreadsheet');$zip->addFromString('content.xml',$doc->saveXML());$zip->close();
 $output=buildDataParetoUpbTemplateOds('2026-10',file_get_contents($path));file_put_contents($path,$output);
 $zip->open($path);$result=upbBjXml($zip->getFromName('content.xml'));$zip->close();
 $tables=upbBjTables($result);$cells=upbBjCells($tables['DATA PARETO UPB']);
 if($cells[8][2]->getAttributeNS(UPB_T,'formula')!=='of:=SUM([.B3:.B7])')throw new RuntimeException('Download did not update SUM');
}finally{@unlink($path);}
echo "PASS synthetic ODS download: repaired SUM retained in exported native XML.\n";
