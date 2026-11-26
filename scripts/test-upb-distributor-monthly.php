<?php
require __DIR__.'/test-upb-distributor-input.php';
$source=distrFixture('2026');$path=tempnam(sys_get_temp_dir(),'distr-old-basis-');
try{
 file_put_contents($path,$source);$zip=new ZipArchive();$zip->open($path);$state=distrReadFormulaState($zip);unset($state['input_basis']);
 $doc=upbBjXml($zip->getFromName('content.xml'));$tables=upbBjTables($doc);$jan=distrMonthInfo($tables['JANUARI'])['GB 1'];$feb=distrMonthInfo($tables['FEBRUARI'])['GB 1'];
 $formula="(['FEBRUARI'.".$feb['ref']."]-['JANUARI'.".$jan['ref']."])/1000";
 $summary=upbBjCells($tables['REKAP']);distrNumber($summary[5][6],$formula,1.5);$state['rules'][2][5]=['formula'=>'of:='.$formula];
 $zip->addFromString('content.xml',$doc->saveXML());distrWriteFormulaState($zip,$state);$zip->close();$old=file_get_contents($path);
 $new=buildUpbDistrInputTemplateOds('2026-04',$old);$tables=upbBjTables(distrReadOds($new));$summary=upbBjCells($tables['REKAP']);
 distrCheck((float)$summary[5][6]->getAttributeNS(UPB_O,'value')===2.5,'Old cumulative rule replaced by monthly total');
 distrCheck(!str_contains($summary[5][6]->getAttributeNS(UPB_T,'formula'),'JANUARI'),'February formula independent of January');
 distrCheck((float)$summary[5][2]->getAttributeNS(UPB_O,'value')===3.5,'Monthly YTD sums January through selected month');
 $missingJanuary=distrEdit($source,function($doc){$table=upbBjTables($doc)['JANUARI'];foreach(distrMonthInfo($table) as $part){$row=distrTestRow($table,$part['start']);foreach([1,5] as $c)distrText(upbBjColumnCell($row,$c),'');}});
 $new=buildUpbDistrInputTemplateOds('2026-03',$missingJanuary);$summary=upbBjCells(upbBjTables(distrReadOds($new))['REKAP']);
 distrCheck((float)$summary[5][6]->getAttributeNS(UPB_O,'value')===2.5,'February remains available when January is blank');
 $values=distrMonthlyValues($old,2026);distrCheck($values['GB 1'][2]===2.5,'Report ignores legacy cumulative custom caches');
 echo "PASS: old cumulative rules migrate to direct monthly totals, independent months, YTD SUM and matching report values.\n";
}finally{@unlink($path);}
