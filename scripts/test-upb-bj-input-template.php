<?php
require __DIR__.'/../includes/upb_bj_input_template.php';
function upbCheck($value,$message){if(!$value)throw new RuntimeException($message);}
$original=new ZipArchive();$original->open(__DIR__.'/../dashboard/assets/input-templates/upb-bj-kx.ods');
$names=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
foreach(['2025-12','2026-02','2027-01'] as $period){
 $temp=tempnam(sys_get_temp_dir(),'test-upb-');try{
 file_put_contents($temp,buildUpbBjInputTemplateOds($period));$zip=new ZipArchive();$zip->open($temp);$xml=$zip->getFromName('content.xml');[$year,$month]=explode('-',$period);
 $doc=new DOMDocument();upbCheck($doc->loadXML($xml),'Valid ODS content');$xp=new DOMXPath($doc);$xp->registerNamespace('t','urn:oasis:names:tc:opendocument:xmlns:table:1.0');$xp->registerNamespace('o','urn:oasis:names:tc:opendocument:xmlns:office:1.0');$tables=$xp->query('//t:table');upbCheck($tables->length===(int)$month+1,'Monthly sheets plus conclusion');
 for($m=1;$m<=(int)$month;$m++){$table=$tables->item($m-1);upbCheck($table->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0','name')===$names[$m-1],'Sheet name');upbCheck(str_contains($table->textContent,'Mulai 01/'.$year.' Hingga '.sprintf('%02d',$m).'/'.$year),'Cumulative monthly period');}
 $summary=$tables->item((int)$month);upbCheck($summary->getAttributeNS('urn:oasis:names:tc:opendocument:xmlns:table:1.0','name')==='KESIMPULAN','Conclusion sheet');upbCheck(str_contains($summary->textContent,'UPB BJ KONIMEX TH. '.((int)$year-1))&&str_contains($summary->textContent,'UPB BJ KONIMEX TH. '.$year),'Both years');upbCheck($xp->query('.//*[@t:formula]',$summary)->length>0,'Monthly formulas are provided');
 for($i=0;$i<$original->numFiles;$i++){$name=$original->getNameIndex($i);if($name!=='content.xml')upbCheck($zip->getFromName($name)===$original->getFromName($name),'Source styles/assets preserved');}$zip->close();
 }finally{unlink($temp);}
}
$original->close();echo "PASS: monthly cumulative sheets through selected period, two-year conclusion with monthly formulas, original styles/assets and valid ODS XML.\n";
