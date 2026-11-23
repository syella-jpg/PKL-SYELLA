<?php
require __DIR__.'/../includes/data_pareto_upb_formulas.php';
foreach([250,450,2600] as $count){
 $doc=new DOMDocument();$table=$doc->createElementNS(UPB_T,'table:table');$doc->appendChild($table);
 $put=static function(array $values)use($doc,$table){
  $row=$doc->createElementNS(UPB_T,'table:table-row');$table->appendChild($row);
  foreach($values as $v){
   $cell=$doc->createElementNS(UPB_T,'table:table-cell');$row->appendChild($cell);
   if(is_string($v)&&str_starts_with($v,'='))upbBjSetFormula($cell,substr($v,1),999);
   elseif(is_numeric($v))$cell->setAttributeNS(UPB_O,'office:value',(string)$v);
   elseif($v!==null)$cell->appendChild($doc->createElementNS(UPB_X,'text:p',$v));
  }
 };
 // Summaries above the header, plus a different sum column from the summary itself.
 $put(['=SUM([.B4:.B400])',null,null,'=SUM([.$E$4:.$E$400])']);
 $put([]);
 $put(['Nama Produk','Unit','Persentase','Nama Produk','Rupiah','Persentase']);
 for($i=0;$i<$count;$i++){
  $r=$i+4;
  $put(['Buatan '.$i,1,'=[.B'.$r.']/SUM([.$B$4:.$B$400])','Buatan '.$i,10,'=[.E'.$r.']/SUM([.$E$4:.$E$400])']);
 }
 $put(['TOTAL','=SUM([.B4:.B400])',null,'TOTAL','=SUM([.E4:.E400])']);
 $put(['Nama Produk','Unit',null,'Nama Produk','Rupiah']);
 $put(['Tabel berikut',77,null,'Tabel berikut',770]);
 refreshDataParetoUpbSums($table);refreshDataParetoDetailRanges($table);
 $cells=upbBjCells($table);$last=$count+3;
 if($cells[1][1]->getAttributeNS(UPB_T,'formula')!=='of:=SUM([.B4:.B'.$last.'])')throw new RuntimeException('Top summary still stops at 400');
 if((float)$cells[1][1]->getAttributeNS(UPB_O,'value')!==(float)$count)throw new RuntimeException('Top summary value wrong');
 if($cells[4][3]->getAttributeNS(UPB_T,'formula')!=='of:=[.B4]/SUM([.$B$4:.$B$'.$last.'])')throw new RuntimeException('Percentage denominator still stops at 400');
 if(abs((float)$cells[4][3]->getAttributeNS(UPB_O,'value')-1/$count)>1e-12)throw new RuntimeException('Percentage cache not refreshed');
 if((float)$cells[1][4]->getAttributeNS(UPB_O,'value')!==(float)($count*10))throw new RuntimeException('Adjacent rupiah table mixed');
 if((float)$cells[$last+1][2]->getAttributeNS(UPB_O,'value')!==(float)$count)throw new RuntimeException('Bottom total wrong');
}
echo "PASS synthetic 250/450/2600 products: top summaries, bottom totals, percentage SUM denominators resize beyond/shrink below 400; adjacent and following tables stay separate.\n";
