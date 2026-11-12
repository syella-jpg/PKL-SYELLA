<?php
require __DIR__.'/../includes/upb_bj_pareto.php';
function paretoCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
function paretoFixture(array $data): DOMElement {
 $d=upbBjXml('<table:table xmlns:table="'.UPB_T.'" xmlns:office="'.UPB_O.'" xmlns:text="'.UPB_X.'" table:name="SEPTEMBER"/>');$table=$d->documentElement;
 foreach($data as $record){$row=$d->createElementNS(UPB_T,'table:table-row');$table->appendChild($row);foreach(range(1,9) as $column){$cell=$d->createElementNS(UPB_T,'table:table-cell');$row->appendChild($cell);if(!array_key_exists($column,$record))continue;$value=$record[$column];$cell->setAttributeNS(UPB_O,'office:value-type',is_numeric($value)?'float':'string');if(is_numeric($value))$cell->setAttributeNS(UPB_O,'office:value',(string)$value);$cell->appendChild($d->createElementNS(UPB_X,'text:p',(string)$value));}}
 return $table;
}
$table=paretoFixture([
 [1=>'No.'],[6=>'Nama'],[6=>'KONICARE VARIAN A',9=>60],[6=>'KONICARE VARIAN B',9=>40],[6=>'TALITA TELON A',9=>20],[1=>'Sub Total GB GB1, Sub Kel. Barang FARMA',9=>120],[1=>'Sub Total GB GB1',9=>120],
 [6=>'KONICARE VARIAN C',9=>50],[6=>'TALITA TELON B',9=>10],[6=>'BETA',9=>20],[1=>'Sub Total GB GB2, Sub Kel. Barang FARMA',9=>80],[1=>'Sub Total GB GB2',9=>80],
 [6=>'GET GIT A',9=>30],[6=>'GET GIT B',9=>20],[6=>'POP BALLS A',9=>30],[6=>'LAINNYA A',9=>20],[1=>'Sub Total GB GB5, Sub Kel. Barang SOBIS',9=>100],[1=>'Sub Total GB GB5',9=>100],
 [6=>'FROZZ MINT',9=>60],[6=>'FROZZ BERRY',9=>40],[6=>'ZERO',9=>0],[6=>'RETURN',9=>-5],[6=>'RETURN LAIN',9=>5],[1=>'Sub Total GB GB6, Sub Kel. Barang CANDY',9=>100],[1=>'Sub Total GB GB6',9=>100],[1=>'TOTAL',9=>400],
 [6=>'FARMASI',9=>200],[6=>'SOBISCO',9=>100],[6=>'CANDY',9=>100],[6=>'TOTAL',9=>400]
]);
paretoCheck(upbBjParetoFamily('Konicare® Minyak Telon')==='KONICARE','Trademark does not split same brand');
$before=$table->ownerDocument->saveXML();$p=upbBjParetoData($table);
paretoCheck(array_column($p['FARMASI']['selected'],'name')===['KONICARE','TALITA'],'Variants across GBs join one family');paretoCheck(array_column($p['FARMASI']['selected'],'value')===[150.0,30.0],'Sum variants before ranking');paretoCheck($p['FARMASI']['selected'][0]['share']===.75,'Share uses whole Farmasi, not one GB');paretoCheck(abs($p['FARMASI']['coverage']-.9)<1e-12,'Crossing 80 percent included');paretoCheck(array_column($p['SOBISCO']['selected'],'name')===['GET GIT','POP BALLS'],'Multi-word families retained');paretoCheck(abs($p['SOBISCO']['coverage']-.8)<1e-12,'Exact 80 percent stops selection');paretoCheck(count($p['CANDY']['selected'])===1&&$p['CANDY']['selected'][0]['value']===100.0,'Zero/negative net family excluded');paretoCheck($before===$table->ownerDocument->saveXML(),'Source not mutated');
$empty=upbBjParetoData(paretoFixture([[1=>'No.'],[6=>'Nama'],[6=>'FARMASI',9=>0],[6=>'SOBISCO',9=>0],[6=>'CANDY',9=>0],[6=>'TOTAL',9=>0]]));foreach($empty as $group)paretoCheck($group['selected']===[],'Zero denominator has no Pareto entries');
$shifted=$table->cloneNode(true);for($i=0;$i<7;$i++)$shifted->insertBefore($shifted->ownerDocument->createElementNS(UPB_T,'table:table-row'),$shifted->firstChild);$shift=upbBjParetoData($shifted);paretoCheck(array_column($shift['FARMASI']['selected'],'value')===[150.0,30.0],'Moved rows retain totals');paretoCheck($shift['FARMASI']['selected'][0]['refs'][0]==='I10','Moved row references preserved');
echo "PASS: family aggregation, cross-GB group denominator, multi-word brands, 80% exact/crossing, zero/negative values and moved product rows.\n";
