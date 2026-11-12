<?php
require_once __DIR__.'/../includes/upb_non_bj_input_template.php';
function checkNonBj($ok,$message){if(!$ok)throw new RuntimeException($message);}
function readNonBj($bytes){$p=tempnam(sys_get_temp_dir(),'nonbj-check-');file_put_contents($p,$bytes);$z=new ZipArchive();$z->open($p);$d=upbBjXml($z->getFromName('content.xml'));$z->close();unlink($p);return $d;}
foreach(['2026-01','2026-09','2027-02','2028-12'] as $period){
 $doc=readNonBj(buildUpbNonBjInputTemplateOds($period));$table=array_values(upbBjTables($doc))[0];$cells=upbBjCells($table);
 checkNonBj(str_contains($table->textContent,substr($period,0,4).'RP.'),'Dynamic year');
 $end=chr(67+(int)substr($period,5,2));
 checkNonBj($cells[4][2]->getAttributeNS(UPB_T,'formula')==='of:=SUM([.D4:.'.$end.'4])','Summary YTD');
 checkNonBj($cells[16][2]->getAttributeNS(UPB_T,'formula')==='of:=SUM([.D16:.'.$end.'16])','Detail YTD');
 checkNonBj($cells[4][4]->getAttributeNS(UPB_T,'formula')==='of:=[.D28]/1000','Monthly conversion');
 checkNonBj(!str_contains($table->textContent,'STREAMLINE'),'No historical note');
}
// Change row counts before generation; totals must follow the moved labels.
$base=file_get_contents(__DIR__.'/../dashboard/assets/input-templates/upb-non-bj.ods');
foreach([1,-1] as $change){
 $doc=readNonBj($base);$table=array_values(upbBjTables($doc))[0];$rows=iterator_to_array($table->getElementsByTagNameNS(UPB_T,'table-row'));
 if($change===1)$table->insertBefore($rows[15]->cloneNode(true),$rows[15]);else $table->removeChild($rows[15]);
 $p=tempnam(sys_get_temp_dir(),'nonbj-move-');file_put_contents($p,$base);$z=new ZipArchive();$z->open($p);$z->addFromString('content.xml',$doc->saveXML());$z->close();$bytes=file_get_contents($p);unlink($p);
 $out=readNonBj(buildUpbNonBjInputTemplateOds('2027-02',$bytes));$cells=upbBjCells(array_values(upbBjTables($out))[0]);
 checkNonBj($cells[4][4]->getAttributeNS(UPB_T,'formula')==='of:=[.D'.(28+$change).']/1000','Moved detail total');
 checkNonBj($cells[28+$change][4]->getAttributeNS(UPB_T,'formula')==='of:=SUM([.D15:.D'.(27+$change).'])','Added/deleted detail row included');
}
try{buildUpbNonBjInputTemplateOds('2027-13');throw new RuntimeException('Accepted invalid period');}catch(InvalidArgumentException $e){}
echo "PASS: UPB NON BJ native template, dynamic year/YTD, monthly conversion, added/deleted rows, invalid period.\n";
