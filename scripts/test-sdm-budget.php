<?php
require_once __DIR__.'/../includes/bod_bi_report.php';
$path=tempnam(sys_get_temp_dir(),'budget-test-');$zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);
$zip->addFromString('content.xml','<?xml version="1.0"?><office:document-content xmlns:office="'.UPB_O.'" xmlns:table="'.UPB_T.'" xmlns:text="'.UPB_X.'"><office:body><office:spreadsheet><table:table table:name="OKTOBER"><table:table-row><table:table-cell/><table:table-cell><text:p>TOTAL ANGGARAN</text:p></table:table-cell></table:table-row><table:table-row><table:table-cell><text:p>GAJI, UPAH, DLL</text:p></table:table-cell><table:table-cell office:value-type="float" office:value="150000"/></table:table-row><table:table-row/><table:table-row><table:table-cell><text:p>RECRUITMENT</text:p></table:table-cell><table:table-cell office:value-type="float" office:value="25000"/></table:table-row></table:table></office:spreadsheet></office:body></office:document-content>');
$zip->close();
try{
 $result=bodBiSdmBudgetsFromBytes(file_get_contents($path),'2026-10');
 if($result!==['salary'=>150000.0,'recruitment'=>25000.0])throw new RuntimeException('Incorrect budgets');
 echo "PASS: selected-month raw budgets by labels, blank rows supported, values retained.\n";
}finally{unlink($path);}
