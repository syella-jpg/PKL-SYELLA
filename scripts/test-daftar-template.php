<?php
require_once __DIR__.'/../includes/daftar_input_template.php';require_once __DIR__.'/../vendor/autoload.php';
foreach(['2026-02','2027-11'] as $period){$p=tempnam(sys_get_temp_dir(),'daftar-test-');try{
 file_put_contents($p,buildDaftarInputTemplateOds($period));$zip=new ZipArchive();$zip->open($p);$doc=upbBjXml($zip->getFromName('content.xml'));if($zip->locateName('Basic/DaftarJT/Controls.xml')!==false)throw new RuntimeException('Unexpected JT macro');$zip->close();$tables=upbBjTables($doc);if(array_keys($tables)!==['DAFTAR'])throw new RuntimeException('DAFTAR sheet name');
 $controls=$doc->getElementsByTagNameNS('urn:oasis:names:tc:opendocument:xmlns:form:1.0','button');if($controls->length!==0||$doc->getElementsByTagNameNS(UPB_T,'content-validation')->length!==0)throw new RuntimeException('Unexpected JT buttons or dropdown');
 $cells=upbBjCells($tables['DAFTAR']);foreach([10,11,14] as $col)if(!str_contains($cells[55][$col]->getAttributeNS(UPB_T,'formula'),'"V"')||str_contains($cells[55][$col]->getAttributeNS(UPB_T,'formula'),'"✓"'))throw new RuntimeException('COUNTIF criteria');
 $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($p);$s=$book->getSheetByName('DAFTAR');
 $s->setCellValue('M7','V');$s->setCellValue('M8','X');$s->setCellValue('M9',null);$s->setCellValue('M24','V');
 $book->getCalculationEngine()->clearCalculationCache();if($s->getCell('J55')->getCalculatedValue()!=2||$s->getCell('J53')->getCalculatedValue()!=3)throw new RuntimeException('JT check/cross/blank calculation');
 if(!str_contains($s->getCell('D3')->getValue(),substr($period,0,4)))throw new RuntimeException('Year label');$book->disconnectWorksheets();
 }finally{@unlink($p);}}
echo "PASS: DAFTAR manual V/X without controls, COUNTIF, blank rows, COUNTA, native layout and period labels.\n";
