<?php
require_once __DIR__.'/../includes/daftar_input_template.php';require_once __DIR__.'/../vendor/autoload.php';
foreach(['2026-02','2027-11'] as $period){$p=tempnam(sys_get_temp_dir(),'daftar-test-');try{
 file_put_contents($p,buildDaftarInputTemplateOds($period));$zip=new ZipArchive();$zip->open($p);$doc=upbBjXml($zip->getFromName('content.xml'));$zip->close();$tables=upbBjTables($doc);if(array_keys($tables)!==['DAFTAR'])throw new RuntimeException('DAFTAR sheet name');
 $v=$doc->getElementsByTagNameNS(UPB_T,'content-validation')->item(0);if(!$v||$v->getAttributeNS(UPB_T,'allow-empty-cell')!=='true'||!str_contains($v->getAttributeNS(UPB_T,'condition'),'"✓";"✗"'))throw new RuntimeException('Optional dropdown missing');
 $cells=upbBjCells($tables['DAFTAR']);foreach([10,11,14] as $col)if(!str_contains($cells[55][$col]->getAttributeNS(UPB_T,'formula'),'"✓"')||str_contains($cells[55][$col]->getAttributeNS(UPB_T,'formula'),'"V"'))throw new RuntimeException('COUNTIF criteria');
 $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($p);$s=$book->getSheetByName('DAFTAR');applyDaftarJtValidation($s);if($s->getCell('M7')->getDataValidation()->getAllowBlank()!==true)throw new RuntimeException('Blank not allowed');
 $s->setCellValue('M7','✓');$s->setCellValue('M8','✗');$s->setCellValue('M9',null);$s->setCellValue('M24','✓');
 $book->getCalculationEngine()->clearCalculationCache();if($s->getCell('J55')->getCalculatedValue()!=2||$s->getCell('J53')->getCalculatedValue()!=3)throw new RuntimeException('JT check/cross/blank calculation');
 if(!str_contains($s->getCell('D3')->getValue(),substr($period,0,4)))throw new RuntimeException('Year label');$book->disconnectWorksheets();
 }finally{@unlink($p);}}
echo "PASS: DAFTAR optional check/cross dropdown, COUNTIF, blank rows, COUNTA, native layout and period labels.\n";
