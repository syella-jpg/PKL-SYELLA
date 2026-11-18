<?php
require_once __DIR__.'/../includes/bod_bi_input_template.php';
function bodRawCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach(['BOD BI.Opr & BOD BI.SDM'] as $section)foreach(['2026-01','2026-03','2027-12'] as $period){
 $bytes=buildBodBiInputTemplateOds($section,$period);$p=tempnam(sys_get_temp_dir(),'bod-raw-test-');
 try{file_put_contents($p,$bytes);$z=new ZipArchive();$z->open($p);$xml=$z->getFromName('content.xml');$tables=upbBjTables(upbBjXml($xml));$last=(int)substr($period,5,2);
 bodRawCheck(array_keys($tables)===array_merge(array_slice(BOD_BI_MONTHS,0,$last),['BOD BI.Opr','BOD BI.SDM']),'Monthly sheet names');
 bodRawCheck(!str_contains($xml,'file:///'),'No external workbook links');
 bodRawCheck(!isset($tables['KESIMPULAN']),'Conclusion sheet removed');
 foreach(array_slice($tables,0,$last) as $name=>$table){$cells=upbBjCells($table);bodRawCheck(str_contains($cells[3][1]->textContent,$section),'Section metadata');foreach([11=>[2,'RUMAH TANGGA & KEAMANAN'],12=>[3,'PENDIDIKAN & PEMBINAAN'],13=>[4,'RECRUITMENT']] as $at=>[$number,$label]){bodRawCheck($cells[$at][1]->textContent===(string)$number,'Budget item number');bodRawCheck($cells[$at][2]->textContent===$label,'Budget item label');}bodRawCheck(str_contains($cells[3][1]->textContent,substr($period,0,4)),'Dynamic year');foreach($cells as $r=>$row)foreach($row as $c=>$cell)if($r>=10&&$c>=3&&$c<=9&&!$cell->hasAttributeNS(UPB_T,'formula'))bodRawCheck(!$cell->hasAttributeNS(UPB_O,'value'),'Inputs genuinely empty');}
  $opr=upbBjCells($tables['BOD BI.Opr']);$sdm=upbBjCells($tables['BOD BI.SDM']);bodRawCheck($opr[16][2]->getAttributeNS(UPB_T,'formula')==='of:=SUM([.B3:.B'.($last+2).'])','OPR dynamic YTD');bodRawCheck($sdm[7][2]->getAttributeNS(UPB_T,'formula')==='of:=SUM([.D7:.'.chr(67+$last).'7])','SDM dynamic YTD');
 $z->close();
 }finally{@unlink($p);}
}
echo "PASS: both raw BOD sections, month/year selection, blank inputs, native subtotal formulas, independent source, no KESIMPULAN sheet and period-aware BOD layouts.\n";

$zip=new ZipArchive();$zip->open(__DIR__.'/../dashboard/assets/input-templates/bod-bi-raw.ods');$doc=upbBjXml($zip->getFromName('content.xml'));$zip->close();$table=array_values(upbBjTables($doc))[0];$before=bodBiRawLayout($table);
$firstRow=null;foreach($table->childNodes as $node)if($node instanceof DOMElement&&$node->localName==='table-row'){$firstRow=$node;break;}
for($i=0;$i<5;$i++){$row=$doc->createElementNS(UPB_T,'table:table-row');$row->appendChild($doc->createElementNS(UPB_T,'table:table-cell'));$table->insertBefore($row,$firstRow);}
$after=bodBiRawLayout($table);foreach($before['totals'] as $label=>$row)bodRawCheck($after['totals'][$label]===$row+5,'Subtotal discovery follows inserted rows');bodRawCheck($after['header']===$before['header']+5,'Header discovery follows inserted rows');
echo "PASS: label/formula-based anchors follow shifted header and subtotal rows.\n";
