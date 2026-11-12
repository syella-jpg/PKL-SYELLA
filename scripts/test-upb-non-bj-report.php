<?php
require_once __DIR__.'/../includes/upb_non_bj_report.php';
function nonBjFixture($year,$value){
 $p=tempnam(sys_get_temp_dir(),'nonbj-fixture-');file_put_contents($p,buildUpbNonBjInputTemplateOds($year.'-09'));$z=new ZipArchive();$z->open($p);$doc=upbBjXml($z->getFromName('content.xml'));$table=array_values(upbBjTables($doc))[0];$rows=iterator_to_array($table->getElementsByTagNameNS(UPB_T,'table-row'));
 foreach([[16,4,$value],[28,4,$value],[4,4,$value/1000]] as [$r,$c,$v]){$cell=upbBjColumnCell($rows[$r-1],$c);$cell->setAttributeNS(UPB_O,'office:value-type','float');$cell->setAttributeNS(UPB_O,'office:value',(string)$v);}
 upbBjSetFormula(upbBjColumnCell($rows[15],16),'SUM([.D16:.O16])+10',$value+10);
 $z->addFromString('content.xml',$doc->saveXML());$z->close();$bytes=file_get_contents($p);unlink($p);return $bytes;
}
foreach(['2026-09','2027-01'] as $period){
 $year=(int)substr($period,0,4);$bytes=buildUpbNonBjReportOds($period,nonBjFixture($year,12000),nonBjFixture($year-1,8000));$p=tempnam(sys_get_temp_dir(),'nonbj-report-check-');file_put_contents($p,$bytes);$z=new ZipArchive();$z->open($p);$doc=upbBjXml($z->getFromName('content.xml'));$z->close();unlink($p);$tables=upbBjTables($doc);if(array_keys($tables)!==['UPB non BJ'])throw new RuntimeException('Sheet name');$cells=upbBjCells($tables['UPB non BJ']);$titles=[];$details=[];
 foreach($cells as $r=>$row){$label=$row[1]->textContent??'';if(str_contains($label,'UPB non BJ KONIMEX TH.'))$titles[]=$label;if(str_starts_with($label,'UPB BAHAN BAKU'))$details[]=$r;}
 if(count($titles)!==2||!str_contains($titles[0],(string)$year)||!str_contains($titles[1],(string)($year-1))||count($details)!==2)throw new RuntimeException('Annual table order');
 if((float)$cells[4][4]->getAttributeNS(UPB_O,'value')!==12.0||(float)$cells[12][4]->getAttributeNS(UPB_O,'value')!==8.0)throw new RuntimeException('Saved summary values');
 foreach([[4,$details[0]],[12,$details[1]]] as [$summary,$start]){if($cells[$summary][4]->getAttributeNS(UPB_T,'formula')!=='of:=[.D'.($start+15).']/1000')throw new RuntimeException('Summary/detail reference moved incorrectly');$r=$start+3;if($cells[$r][16]->getAttributeNS(UPB_T,'formula')!=='of:=SUM([.D'.$r.':.O'.$r.'])+10')throw new RuntimeException('Custom formula lost');}
 if($period==='2026-09')file_put_contents('/tmp/upb-non-report-fixture.ods',$bytes);
}
echo "PASS: two annual summaries, current/prior details, uploaded values, custom formulas and remapped references.\n";
