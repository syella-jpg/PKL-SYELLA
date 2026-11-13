<?php
require_once __DIR__.'/../includes/upb_distr_input_store.php';
require_once __DIR__.'/../includes/upb_distr_report.php';
function distrCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
function distrEdit($bytes,$edit){$path=tempnam(sys_get_temp_dir(),'distr-test-');file_put_contents($path,$bytes);$zip=new ZipArchive();$zip->open($path);$doc=upbBjXml($zip->getFromName('content.xml'));$edit($doc);$zip->addFromString('content.xml',$doc->saveXML());$zip->close();$bytes=file_get_contents($path);unlink($path);return $bytes;}
// Address actual spreadsheet rows, including ODS repeated blank rows.
function distrTestRow($table,$position){$r=1;
 foreach(iterator_to_array($table->childNodes) as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$repeat=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));
  if($position>=$r&&$position<$r+$repeat){if($repeat===1)return $row;$before=$position-$r;$after=$repeat-$before-1;
   if($before){$copy=$row->cloneNode(true);$copy->setAttributeNS(UPB_T,'table:number-rows-repeated',(string)$before);$table->insertBefore($copy,$row);}
   $single=$row->cloneNode(true);$single->removeAttributeNS(UPB_T,'number-rows-repeated');$table->insertBefore($single,$row);
   if($after)$row->setAttributeNS(UPB_T,'table:number-rows-repeated',(string)$after);else $table->removeChild($row);return $single;
  }$r+=$repeat;
 }throw new RuntimeException('Test row not found');
}
function distrFixture($year){
 $bytes=buildUpbDistrInputTemplateOds($year.'-03');
 return distrEdit($bytes,function($doc){$tables=upbBjTables($doc);
  foreach([1=>1000,2=>2500] as $month=>$amount){$table=$tables[DISTR_MONTHS[$month-1]];$parts=distrMonthInfo($table);
   foreach(DISTR_GROUPS as $i=>$group){$part=$parts[$group];distrText(upbBjColumnCell(distrTestRow($table,$part['start']),1),'TEST GROUP '.($i+1));$cell=upbBjColumnCell(distrTestRow($table,$part['start']),5);distrNumber($cell,'1',$amount*($i+1));$cell->removeAttributeNS(UPB_T,'formula');
    distrNumber(upbBjColumnCell(distrTestRow($table,$part['row']),5),'SUM([.E'.$part['start'].':.E'.$part['last'].'])',$amount*($i+1));
   }
  }
 });
}
$blank=buildUpbDistrInputTemplateOds('2026-03');validateUpbDistrInput($blank,'2026-03','UPB DISTR');
$tables=upbBjTables(distrReadOds($blank));distrCheck(array_keys($tables)===['JANUARI','FEBRUARI','MARET','KESIMPULAN'],'Monthly tabs through selected period');
foreach(DISTR_MONTHS as $i=>$name)if($i<3){$parts=distrMonthInfo($tables[$name]);distrCheck(array_keys($parts)===DISTR_GROUPS,'Eight separate group tables');foreach($parts as $part)distrCheck($part['last']-$part['start']+1===2500,'2500 product rows per group');}

$fixture=distrFixture('2026');$rebuilt=buildUpbDistrInputTemplateOds('2026-03',$fixture);$tables=upbBjTables(distrReadOds($rebuilt));$summary=upbBjCells($tables['KESIMPULAN']);
$value=static fn($row,$col)=>(float)$summary[$row][$col]->getAttributeNS(UPB_O,'value');
distrCheck($value(5,5)===1.0&&$value(5,6)===1.5,'Cumulative converted to individual months and scaled /1000');
distrCheck(!$summary[5][7]->hasAttributeNS(UPB_O,'value'),'New empty month does not negate cumulative history');
distrCheck($value(13,2)===90.0&&$value(4,2)===52.5,'YTD totals do not count GB children twice');
distrCheck(abs($value(4,3)-21/36)<0.00000001,'YTD contribution');
foreach($summary as $row)foreach($row as $cell)if($cell->hasAttributeNS(UPB_T,'formula'))distrCheck(!preg_match('/COUNTA|COUNT\(|AND\(/',$cell->getAttributeNS(UPB_T,'formula')),'Conclusion uses short subtotal references');
distrCheck(str_contains($summary[5][2]->getAttributeNS(UPB_T,'formula'),'E5:.G5'),'Selected-month SUM YTD');
$extended=buildUpbDistrInputTemplateOds('2026-04',$fixture);validateUpbDistrInput($extended,'2026-04','UPB DISTR');$tables=upbBjTables(distrReadOds($extended));
distrCheck(distrMonthInfo($tables['JANUARI'])['GB 1']['value']===1000.0,'Existing monthly input preserved');
foreach(upbBjCells($tables['APRIL']) as $row)foreach($row as $cell)distrCheck(!str_contains($cell->textContent,'TEST GROUP'),'New month input blank');
// Move a complete table without hard-coded row assumptions; preserve a custom subtotal.
$moved=distrEdit($fixture,function($doc){$all=upbBjTables($doc);$all['MARET']->parentNode->removeChild($all['MARET']);$table=$all['FEBRUARI'];$rows=iterator_to_array($table->getElementsByTagNameNS(UPB_T,'table-row'));$part=distrMonthInfo($table)['NON KONIMEX'];$productRow=distrTestRow($table,$part['start']);$table->insertBefore($productRow->cloneNode(true),$productRow);$part=distrMonthInfo($table)['NON KONIMEX'];$rows=iterator_to_array($table->getElementsByTagNameNS(UPB_T,'table-row'));distrNumber(upbBjColumnCell(distrTestRow($table,$part['row']),5),'SUM([.E'.$part['start'].':.E'.$part['last'].'])*2',40000);});
$moved=buildUpbDistrInputTemplateOds('2026-04',$moved);$tables=upbBjTables(distrReadOds($moved));$part=distrMonthInfo($tables['FEBRUARI'])['NON KONIMEX'];$summary=upbBjCells($tables['KESIMPULAN']);
distrCheck(str_contains($summary[12][6]->getAttributeNS(UPB_T,'formula'),'.E'.$part['row'].']'),'Moved group subtotal found by label');
$april=upbBjCells($tables['APRIL']);$lastPart=distrMonthInfo($tables['APRIL'])['NON KONIMEX'];distrCheck(str_ends_with($april[$lastPart['row']][5]->getAttributeNS(UPB_T,'formula'),'*2'),'Custom formula carries to new month');
foreach([1,-1] as $change){
 $resized=distrEdit($fixture,function($doc)use($change){$table=upbBjTables($doc)['FEBRUARI'];$part=distrMonthInfo($table)['GB 1'];
  $row=distrTestRow($table,$part['last']);if($change>0)$table->insertBefore($row->cloneNode(true),distrTestRow($table,$part['row']));else $table->removeChild($row);
  // Calc updates an ordinary SUM when inserting/deleting spreadsheet rows.
  $part=distrMonthInfo($table)['GB 1'];foreach([3,5] as $column){$cell=upbBjColumnCell(distrTestRow($table,$part['row']),$column);$cache=(float)$cell->getAttributeNS(UPB_O,'value');distrNumber($cell,'SUM([.'.chr(64+$column).$part['start'].':.'.chr(64+$column).$part['last'].'])',$cache);}
 });
 $resized=buildUpbDistrInputTemplateOds('2026-03',$resized);$tabs=upbBjTables(distrReadOds($resized));$part=distrMonthInfo($tabs['FEBRUARI'])['GB 1'];$cells=upbBjCells($tabs['FEBRUARI']);
 distrCheck($part['last']-$part['start']+1===2500+$change,'Rows can be added/deleted');
 distrCheck($cells[$part['row']][5]->getAttributeNS(UPB_T,'formula')==='of:=SUM([.E'.$part['start'].':.E'.$part['last'].'])','Ordinary subtotal range follows edited row count');
 $summary=upbBjCells($tabs['KESIMPULAN']);distrCheck(str_contains($summary[5][6]->getAttributeNS(UPB_T,'formula'),"'FEBRUARI'.E".$part['row'].']'),'Conclusion follows moved subtotal');
}
$fresh=upbBjTables(distrReadOds(buildUpbDistrInputTemplateOds('2027-01')));distrCheck(array_keys($fresh)===['JANUARI','KESIMPULAN'],'Fresh-year template');
$pareto=buildDataParetoUpbTemplateOds('2026-03');validateUpbDistrInput($pareto,'2026-03','DATA PARETO UPB');
$pareto=distrEdit($pareto,function($doc){$rows=iterator_to_array(upbBjTables($doc)['DATA PARETO UPB']->getElementsByTagNameNS(UPB_T,'table-row'));distrText(upbBjColumnCell($rows[7],2),'TEST PARETO');});
$savedTable=upbBjTables(distrReadOds($pareto))['DATA PARETO UPB'];$outTable=upbBjTables(distrReadOds(buildDataParetoUpbTemplateOds('2026-03',$pareto)))['DATA PARETO UPB'];distrCheck($savedTable->C14N()===$outTable->C14N(),'Direct Pareto sheet copy');
foreach([[$blank,'2026-02','UPB DISTR'],[$blank,'2025-03','UPB DISTR'],[$blank,'2026-03','DATA PARETO UPB'],[$pareto,'2026-02','DATA PARETO UPB']] as $args){$rejected=false;try{validateUpbDistrInput(...$args);}catch(InvalidArgumentException $e){$rejected=true;}distrCheck($rejected,'Wrong period/section rejected');}
$report=buildUpbDistrReportOds('2026-03',$fixture,distrFixture('2025'));$cells=upbBjCells(upbBjTables(distrReadOds($report))['UPB DISTR']);distrCheck((float)$cells[5][5]->getAttributeNS(UPB_O,'value')===1.0&&(float)$cells[5][6]->getAttributeNS(UPB_O,'value')===1.5,'Report monthly data');distrCheck((float)$cells[20][2]->getAttributeNS(UPB_O,'value')===90.0,'Prior-year report YTD');
file_put_contents('/tmp/distr-input-test.ods',$rebuilt);file_put_contents('/tmp/distr-input-extended-test.ods',$extended);file_put_contents('/tmp/pareto-input-test.ods',$pareto);file_put_contents('/tmp/distr-data-report-test.ods',$report);
echo "PASS: distributor monthly tables, cumulative differences, scaling, YTD/contribution, empty new month, history/custom formulas, shifted rows, period validation, native Pareto copy and report data.\n";
