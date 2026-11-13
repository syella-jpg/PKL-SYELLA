<?php
require __DIR__.'/test-upb-distributor-input.php';
function customValue($table,$row,$column){$cells=upbBjCells($table);return $cells[$row][$column]->getAttributeNS(UPB_O,'value');}
$source=distrFixture('2026');
$source=distrEdit($source,function($doc){$tables=upbBjTables($doc);$feb=$tables['FEBRUARI'];$part=distrMonthInfo($feb)['GB 1'];
 distrNumber(upbBjColumnCell(distrTestRow($feb,$part['row']),5),'SUM([.E'.$part['start'].':.E'.($part['start']+1).'])*2',5000);
 $summary=upbBjCells($tables['KESIMPULAN']);$cell=$summary[5][6];distrNumber($cell,str_replace('/1000','/100',substr($cell->getAttributeNS(UPB_T,'formula'),4)),40);
});
$compact=prepareUpbDistrInputOds($source,'2026-03');validateUpbDistrInput($compact,'2026-03','UPB DISTR');$doc=distrReadOds($compact);$tabs=upbBjTables($doc);
foreach(['JANUARI','FEBRUARI','MARET'] as $name)foreach(distrMonthInfo($tabs[$name]) as $group=>$part)distrCheck($part['last']-$part['start']+1===($name==='MARET'?2500:1),'Uploaded months compact; unfilled months keep 2500 input rows');
$jan=distrMonthInfo($tabs['JANUARI'])['GB 1'];$feb=distrMonthInfo($tabs['FEBRUARI'])['GB 1'];$j=upbBjCells($tabs['JANUARI']);$f=upbBjCells($tabs['FEBRUARI']);
distrCheck(!str_contains($j[$jan['row']][5]->getAttributeNS(UPB_T,'formula'),'*2'),'January retains original calculation');
distrCheck(str_ends_with($f[$feb['row']][5]->getAttributeNS(UPB_T,'formula'),'*2'),'February retains custom subtotal');
distrCheck((float)customValue($tabs['KESIMPULAN'],5,5)===1.0,'January summary unchanged');
distrCheck((float)customValue($tabs['KESIMPULAN'],5,6)===40.0,'February custom summary calculated');
$next=buildUpbDistrInputTemplateOds('2026-04',$compact);$doc=distrReadOds($next);$tabs=upbBjTables($doc);$summary=upbBjCells($tabs['KESIMPULAN']);
distrCheck(str_contains($summary[5][7]->getAttributeNS(UPB_T,'formula'),'/100)'),'March inherits February summary rule');
distrCheck(str_contains($summary[5][8]->getAttributeNS(UPB_T,'formula'),'/100)'),'April inherits February summary rule');
foreach(['MARET','APRIL'] as $name){$part=distrMonthInfo($tabs[$name])['GB 1'];$cells=upbBjCells($tabs[$name]);distrCheck(str_ends_with($cells[$part['row']][5]->getAttributeNS(UPB_T,'formula'),'*2'),'Previously empty future raw sheet follows February');}
// A later edit to February refreshes untouched blank future sheets again.
$again=distrEdit($next,function($doc){$table=upbBjTables($doc)['FEBRUARI'];$part=distrMonthInfo($table)['GB 1'];$cell=upbBjColumnCell(distrTestRow($table,$part['row']),5);distrNumber($cell,str_replace('*2','*3',substr($cell->getAttributeNS(UPB_T,'formula'),4)),7500);});
$again=prepareUpbDistrInputOds($again,'2026-04');$againTabs=upbBjTables(distrReadOds($again));foreach(['MARET','APRIL'] as $name){$part=distrMonthInfo($againTabs[$name])['GB 1'];$cells=upbBjCells($againTabs[$name]);distrCheck(str_ends_with($cells[$part['row']][5]->getAttributeNS(UPB_T,'formula'),'*3'),'Newer February rule refreshes untouched future raw sheets');}

// A new month inherits the latest monthly structure and subtotal formulas.
$toFeb=distrEdit($source,function($doc){$tables=upbBjTables($doc);$tables['MARET']->parentNode->removeChild($tables['MARET']);});
$toFeb=prepareUpbDistrInputOds($toFeb,'2026-02');$march=buildUpbDistrInputTemplateOds('2026-03',$toFeb);$tabs=upbBjTables(distrReadOds($march));$m=distrMonthInfo($tabs['MARET'])['GB 1'];$mc=upbBjCells($tabs['MARET']);
distrCheck(str_ends_with($mc[$m['row']][5]->getAttributeNS(UPB_T,'formula'),'*2'),'New March raw table inherits February custom formula');
$march=distrEdit($march,function($doc){$table=upbBjTables($doc)['MARET'];$part=distrMonthInfo($table)['GB 1'];$row=distrTestRow($table,$part['start']);distrText(upbBjColumnCell($row,1),'TEST NEW MONTH');$cell=upbBjColumnCell($row,5);distrNumber($cell,'1',4000);$cell->removeAttributeNS(UPB_T,'formula');distrNumber(upbBjColumnCell(distrTestRow($table,$part['row']),5),'SUM([.E'.$part['start'].':.E'.$part['last'].'])*2',8000);});
$march=prepareUpbDistrInputOds($march,'2026-03');$tabs=upbBjTables(distrReadOds($march));distrCheck((float)customValue($tabs['KESIMPULAN'],5,7)===30.0,'Inherited summary uses March/February anchors and calculates accurately');
// Reverting a later month to its default formula starts a new rule, without changing February.
$reset=distrEdit($march,function($doc){$tables=upbBjTables($doc);$expected=distrConclusion($doc,$tables['KESIMPULAN'],distrSnapshotInfo($doc),'2026-03');$e=upbBjCells($expected);$a=upbBjCells($tables['KESIMPULAN']);distrNumber($a[5][7],substr($e[5][7]->getAttributeNS(UPB_T,'formula'),4),3);});
$reset=prepareUpbDistrInputOds($reset,'2026-03');$reset=buildUpbDistrInputTemplateOds('2026-04',$reset);$tabs=upbBjTables(distrReadOds($reset));$s=upbBjCells($tabs['KESIMPULAN']);distrCheck(str_contains($s[5][6]->getAttributeNS(UPB_T,'formula'),'/100)'),'February rule retained after March reset');distrCheck(str_contains($s[5][8]->getAttributeNS(UPB_T,'formula'),'/1000)'),'March reset carries into April');
// Meaningful zero values, direct references to blank cells, and formula rows must survive cleanup.
$protected=distrEdit(distrFixture('2026'),function($doc){$tabs=upbBjTables($doc);$table=$tabs['FEBRUARI'];$part=distrMonthInfo($table)['GB 1'];$row=distrTestRow($table,$part['start']+10);distrNumber(upbBjColumnCell($row,5),'1',0);upbBjColumnCell($row,5)->removeAttributeNS(UPB_T,'formula');$row=distrTestRow($table,$part['start']+20);distrNumber(upbBjColumnCell($row,5),'[.E'.($part['start']+30).']+7',7);});
$protected=prepareUpbDistrInputOds($protected,'2026-03');$tabs=upbBjTables(distrReadOds($protected));$part=distrMonthInfo($tabs['FEBRUARI'])['GB 1'];distrCheck($part['last']-$part['start']+1===4,'Data, explicit zeros, formula rows and directly referenced blank cells retained');

$report=buildUpbDistrReportOds('2026-03',$march);$rt=upbBjTables(distrReadOds($report))['UPB DISTR'];distrCheck((float)customValue($rt,5,6)===40.0&&(float)customValue($rt,5,7)===30.0,'Final distributor report follows customized monthly conclusion');
file_put_contents('/tmp/distr-customization-april-test.ods',$next);
file_put_contents('/tmp/distr-customization-test.ods',$march);
echo "PASS: compact upload, reference remapping, January isolation, custom raw/summary inheritance, later reset, accurate custom caches and preservation of zeros/dependencies.\n";
