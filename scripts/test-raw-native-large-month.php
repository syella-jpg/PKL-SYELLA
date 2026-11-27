<?php
require __DIR__.'/../includes/raw_native_period.php';
$path=tempnam('/tmp','dummy-large-month-');$z=new ZipArchive();
try{
 $row='<table:table-row>'.str_repeat('<table:table-cell><text:p>Dummy</text:p></table:table-cell>',7).'</table:table-row>';
 $xml='<?xml version="1.0"?><office:document-content xmlns:office="'.UPB_O.'" xmlns:table="'.UPB_T.'" xmlns:text="'.UPB_X.'" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"><office:automatic-styles/><office:body><office:spreadsheet><table:table table:name="JANUARI">'.str_repeat($row,10000).'</table:table><table:table table:name="FEBRUARI">'.$row.'</table:table></office:spreadsheet></office:body></office:document-content>';
 $z->open($path,ZipArchive::OVERWRITE);$z->addFromString('mimetype','application/vnd.oasis.opendocument.spreadsheet');$z->addFromString('content.xml',$xml);$z->close();unset($xml);
 $start=microtime(true);$result=protectRawNativePeriod(file_get_contents($path),'2026-02');$seconds=microtime(true)-$start;
 $tables=upbBjTables(rawPeriodDocument($result));
 if($tables['JANUARI']->getAttributeNS(UPB_T,'protected')!=='true')throw new RuntimeException('Earlier month not protected');
 if($tables['FEBRUARI']->getAttributeNS(UPB_T,'protected')==='true')throw new RuntimeException('Current month protected');
 if($tables['JANUARI']->getElementsByTagNameNS(UPB_T,'table-cell')->length!==70000)throw new RuntimeException('Lost cells');
 echo 'PASS 70,000 dummy cells protected in '.round($seconds,3)." seconds; data and current-month editing preserved. No DB/source templates.\n";
}finally{unlink($path);}
