<?php
require_once __DIR__.'/../includes/upb_non_bj_input_template.php';
require_once __DIR__.'/../includes/upb_non_bj_input_store.php';
$bytes=buildUpbNonBjInputTemplateOds('2026-09');validateUpbNonBjOds($bytes,'2026-09');
foreach(['2026-08','2025-09','2026-13'] as $period){try{validateUpbNonBjOds($bytes,$period);throw new RuntimeException('Mismatch accepted');}catch(InvalidArgumentException $e){}}
try{validateUpbNonBjOds('not an ODS','2026-09');throw new RuntimeException('Bad file accepted');}catch(InvalidArgumentException $e){}
$p=tempnam(sys_get_temp_dir(),'nonbj-history-test-');file_put_contents($p,$bytes);$z=new ZipArchive();$z->open($p);$doc=upbBjXml($z->getFromName('content.xml'));$table=array_values(upbBjTables($doc))[0];$rows=iterator_to_array($table->getElementsByTagNameNS(UPB_T,'table-row'));$input=upbBjColumnCell($rows[15],4);$input->setAttributeNS(UPB_O,'office:value-type','float');$input->setAttributeNS(UPB_O,'office:value','12345');upbBjSetFormula(upbBjColumnCell($rows[15],16),'SUM([.D16:.O16])+100',100);upbBjSetFormula(upbBjColumnCell($rows[16],2),'[.D17]*2',0);$z->addFromString('content.xml',$doc->saveXML());$z->close();$saved=file_get_contents($p);
file_put_contents($p,buildUpbNonBjInputTemplateOds('2026-10',$saved,true));$z->open($p);$doc=upbBjXml($z->getFromName('content.xml'));$z->close();unlink($p);$cells=upbBjCells(array_values(upbBjTables($doc))[0]);if($cells[16][4]->getAttributeNS(UPB_O,'value')!=='12345')throw new RuntimeException('Saved input lost');
if($cells[16][16]->getAttributeNS(UPB_T,'formula')!=='of:=SUM([.D16:.O16])+100')throw new RuntimeException('Custom total overwritten');
if($cells[17][2]->getAttributeNS(UPB_T,'formula')!=='of:=[.D17]*2')throw new RuntimeException('Custom YTD overwritten');
if($cells[16][2]->getAttributeNS(UPB_T,'formula')!=='of:=SUM([.D16:.M16])')throw new RuntimeException('Standard YTD failed to advance');
if((float)$cells[16][2]->getAttributeNS(UPB_O,'value')!==12345.0)throw new RuntimeException('YTD cache lost');
validateUpbNonBjOds(buildUpbNonBjInputTemplateOds('2026-10',$saved,true),'2026-10');
echo "PASS: NON BJ upload validation, mismatched period rejection, saved inputs retained on later template.\n";
