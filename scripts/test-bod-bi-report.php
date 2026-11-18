<?php
require_once __DIR__.'/../includes/bod_bi_report.php';
function syntheticBodSnapshot(string $period,array $values): string {
 $path=tempnam(sys_get_temp_dir(),'bod-report-test-');
 try{
  file_put_contents($path,buildBodBiInputTemplateOds(BOD_BI_INPUT_SECTION,$period));$doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);
  foreach($values as $name=>$items){$rows=[];$at=1;foreach($tables[$name]->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$rows[$at]=$row;$at+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
   foreach($items as $label=>$value){$ref=bodBiRawActualRef($tables[$name],$label);preg_match('/([A-Z]+)(\d+)/',$ref,$m);$cell=upbBjColumnCell($rows[(int)$m[2]],7);upbBjSetFormula($cell,'0',$value);}
  }
  $z=new ZipArchive();$z->open($path);$z->addFromString('content.xml',$doc->saveXML());$z->close();return file_get_contents($path);
 }finally{@unlink($path);}
}
$prior=syntheticBodSnapshot('2025-02',['JANUARI'=>['SUB TOTAL I'=>100,'SUB TOTAL III'=>10,'GAJI, UPAH, DLL'=>60,'RECRUITMENT'=>5],'FEBRUARI'=>['SUB TOTAL I'=>180,'SUB TOTAL III'=>30,'GAJI, UPAH, DLL'=>110,'RECRUITMENT'=>15]]);
$current=syntheticBodSnapshot('2026-02',['JANUARI'=>['SUB TOTAL I'=>200,'GAJI, UPAH, DLL'=>120,'RECRUITMENT'=>10],'FEBRUARI'=>['SUB TOTAL I'=>350,'GAJI, UPAH, DLL'=>210,'RECRUITMENT'=>30]]);
$bytes=buildBodBiReportOds('2026-02',$current,$prior);$path=tempnam(sys_get_temp_dir(),'bod-report-check-');
try{
 file_put_contents($path,$bytes);$doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);if(array_keys($tables)!==['BOD BI.Opr','BOD BI.SDM'])throw new RuntimeException('Unexpected report sheets');
 $o=upbBjCells($tables['BOD BI.Opr']);$s=upbBjCells($tables['BOD BI.SDM']);
 foreach([[$o[3][2],200],[$o[4][2],150],[$o[16][2],350],[$o[16][4],210],[$s[7][2],210],[$s[8][2],30],[$s[13][2],110],[$s[14][2],15]] as [$cell,$expected])if((float)$cell->getAttributeNS(UPB_O,'value')!==(float)$expected)throw new RuntimeException('Report value mismatch');
 foreach($tables as $table)foreach(upbBjCells($table) as $row)foreach($row as $cell)if(str_contains($cell->getAttributeNS(UPB_T,'formula'),"['"))throw new RuntimeException('Missing raw dependency');
 $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);if($book->getSheetByName('BOD BI.Opr')->getCell('B16')->getCalculatedValue()!=350)throw new RuntimeException('PHP report recalculation');$book->disconnectWorksheets();
 file_put_contents('/tmp/bod-bi-report-synthetic.ods',$bytes);
 echo "PASS: full report uses current/prior raw inputs, year-specific OPR, SDM, monthly/YTD values and standalone formulas.\n";
}finally{@unlink($path);}
