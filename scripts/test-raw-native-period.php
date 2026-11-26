<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/raw_native_period.php';require __DIR__.'/../includes/raw_actual_period.php';
function verifyNative($ok,$message){if(!$ok)throw new RuntimeException($message);}
function dummyOds(array $sheets): string {
 $b=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$b->removeSheetByIndex(0);foreach($sheets as $name=>$rows){$s=$b->createSheet();$s->setTitle($name);$s->fromArray($rows);}
 $path=tempnam('/tmp','dummy-native-');try{$w=new \PhpOffice\PhpSpreadsheet\Writer\Ods($b);$w->setPreCalculateFormulas(false);$w->save($path);return file_get_contents($path);}finally{$b->disconnectWorksheets();unlink($path);}
}
$rows=[['PARAMETER','JAN','FEB','MAR','APR','MEI','JUN'],['Dummy GB1',1,2,3,4,5,6],['Dummy GB4',10,20,30,40,50,60]];
$before=dummyOds(['SERVICE LEVEL'=>$rows]);$edit=$rows;$edit[1][6]=99;$after=dummyOds(['SERVICE LEVEL'=>$edit]);assertRawNativePeriod('2026-06',$before,$after);
$edit[1][5]=999;try{assertRawNativePeriod('2026-06',$before,dummyOds(['SERVICE LEVEL'=>$edit]));throw new RuntimeException('May edit accepted');}catch(InvalidArgumentException $e){verifyNative(str_contains($e->getMessage(),'2026-05'),'May correction message');}
$monthly=['JANUARI'=>[['Data'],['Dummy',10]],'FEBRUARI'=>[['Data'],['Dummy',20]],'REKAP'=>[['Automatic']]];
$before=dummyOds($monthly);$monthly['FEBRUARI'][1][1]=30;$after=dummyOds($monthly);assertRawNativePeriod('2026-02',$before,$after);
$locked=rawPeriodDocument(protectRawNativePeriod($after,'2026-02'));$tables=upbBjTables($locked);verifyNative($tables['JANUARI']->getAttributeNS(UPB_T,'protected')==='true','Previous monthly sheet protected');verifyNative($tables['FEBRUARI']->getAttributeNS(UPB_T,'protected')!=='true','Current monthly sheet editable');verifyNative($tables['REKAP']->getAttributeNS(UPB_T,'protected')==='true','REKAP remains locked');
$monthly['JANUARI'][1][1]=11;try{assertRawNativePeriod('2026-02',$before,dummyOds($monthly));throw new RuntimeException('January edit accepted');}catch(InvalidArgumentException $e){}
$wide=rawPeriodDocument(protectRawNativePeriod(dummyOds(['MNJ'=>$rows]),'2026-06'));verifyNative(upbBjTables($wide)['MNJ']->getAttributeNS(UPB_T,'protected')==='true','Wide native table protected');
$blank=$rows;$blank[1][6]=null;$protected=protectRawNativePeriod(dummyOds(['MNJ'=>$blank]),'2026-06');$path=tempnam('/tmp','dummy-blank-');
try{file_put_contents($path,$protected);$loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$s=$loaded->getSheetByName('MNJ');verifyNative($s->getStyle('F2')->getProtection()->getLocked()==='protected','Previous month stays locked on ODS reopen');verifyNative($s->getStyle('G2')->getProtection()->getLocked()==='unprotected','Empty current month editable on ODS reopen');$loaded->disconnectWorksheets();}finally{unlink($path);}
$b=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$b->getActiveSheet();$s->setTitle('PENJUALAN');$s->fromArray([['Barang','JANUARI','','FEBRUARI',''],['','Unit','Rupiah','Unit','Rupiah'],['Dummy',10,20,30,40]]);$s->mergeCells('B1:C1');$s->mergeCells('D1:E1');protectRawTemplate($b,'2026-02','BOD KX Sell In');verifyNative($s->getStyle('C3')->getProtection()->getLocked()==='protected','Paired previous Rupiah locked');verifyNative($s->getStyle('E3')->getProtection()->getLocked()==='unprotected','Paired current Rupiah editable');$b->disconnectWorksheets();
echo "PASS dummy native monthly/wide sheets, blocked history edits, current-month updates, REKAP preservation and paired Unit/Rupiah protection. No real files or DB.\n";
