<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/raw_template_protection.php';
require __DIR__.'/../includes/unit_sell_out_targets.php';
require __DIR__.'/../includes/biaya_input_template.php';
require __DIR__.'/../includes/ethical_source_template.php';
require __DIR__.'/../includes/upb_bj_template_history.php';
function checkLock($ok,$why){if(!$ok)throw new RuntimeException($why);}
foreach(['01','02','12'] as $month){
 $book=buildBiayaInputTemplate('2026-'.$month);
 protectRawTemplate($book,'2026-'.$month,'BIAYA');
 checkLock((bool)$book->getSheetByName('TARGET')->getProtection()->getSheet()===($month!=='01'),'Target period lock');
  $actual=$book->getSheetByName('REALISASI');$layout=locateBiayaInputTables($actual,true)[0];$r=$layout['first'];
 checkLock($actual->getStyle([$layout['months'][(int)$month-1],$r])->getProtection()->getLocked()==='unprotected','Current realization editable');
 if($month!=='01')checkLock($actual->getStyle([$layout['months'][0],$r])->getProtection()->getLocked()==='protected','Previous realization locked');
 $book->disconnectWorksheets();
 $book=buildEthicalSourceTemplate('2026-'.$month,array_fill_keys(['existing','streamline','np_previous','np_current'],2));$sheet=$book->getSheetByName('PRODUK NAS');
 $layout=locateEthicalProductTables($sheet,2026,true,true)['existing'];$row=$layout['first'];
 checkLock((bool)$sheet->getProtection()->getSheet()===($month!=='01'),'Mixed Ethical protection');
 if($month!=='01'){
  checkLock($sheet->getStyle([$layout['columns']['months'][0]['unit'],$row])->getProtection()->getLocked()==='protected','Target unit locked');
  checkLock($sheet->getStyle([$layout['columns']['months'][(int)$month-1]['actual_unit'],$row])->getProtection()->getLocked()==='unprotected','Real unit editable');
 }
 $path=tempnam('/tmp','dummy-protection-');try{$writer=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);$writer->setPreCalculateFormulas(false);$writer->save($path);$loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);checkLock($loaded->getSheetByName('REKAP')->getProtection()->getSheet(),'Protection survives export');$loaded->disconnectWorksheets();}finally{unlink($path);}$book->disconnectWorksheets();
}
$d=upbBjXml('<table:table xmlns:table="'.UPB_T.'" table:name="REKAP"/>');protectRawRekapOds($d->documentElement);checkLock($d->documentElement->getAttributeNS(UPB_T,'protected')==='true','ODS protected');checkLock($d->documentElement->getAttributeNS(UPB_T,'protection-key')!=='','ODS password key');
echo "PASS dummy January/February/December locks, mixed Ethical inputs, XLSX roundtrip, native ODS lock. No user files or DB accessed.\n";
