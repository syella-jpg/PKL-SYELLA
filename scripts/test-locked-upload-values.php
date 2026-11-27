<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/raw_actual_period.php';require __DIR__.'/../includes/raw_native_period.php';
function lockedCheck($ok,$text){if(!$ok)throw new RuntimeException($text);}
function rejected(callable $f){try{$f();}catch(InvalidArgumentException $e){lockedCheck(str_starts_with($e->getMessage(),'UPLOAD DITOLAK!')&&str_contains($e->getMessage(),'CSPPM'),'Rejection wording');return;}throw new RuntimeException('Tampered data accepted');}
$target=['GB1'=>array_fill(0,12,100)];$changed=$target;$changed['GB1'][0]=900;
assertRawTargetPeriod('2026-06',$target,$target);assertRawTargetPeriod('2026-01',$target,$changed);rejected(fn()=>assertRawTargetPeriod('2026-06',$target,$changed));rejected(fn()=>assertRawTargetPeriod('2026-06',$target,[]));
$actual=['product'=>array_fill(0,12,10)];$current=$actual;$current['product'][5]=20;assertRawActualPeriod('2026-06',$actual,$current);$current['product'][4]=999;rejected(fn()=>assertRawActualPeriod('2026-06',$actual,$current));
function dummyLockedBytes($target,$past,$current,$lock):string {
 $b=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$b->getActiveSheet();$s->setTitle('DUMMY');$s->fromArray([['Jenis','JAN','FEB'],['TARGET',$target,$target],['REALISASI',$past,$current]]);$s->getProtection()->setSheet($lock)->setPassword('dummy');$p=tempnam('/tmp','dummy-relocked-');
 try{$w=new \PhpOffice\PhpSpreadsheet\Writer\Ods($b);$w->setPreCalculateFormulas(false);$w->save($p);return file_get_contents($p);}finally{$b->disconnectWorksheets();unlink($p);}
}
$before=dummyLockedBytes(100,10,20,true);
assertRawNativePeriod('2026-02',$before,dummyLockedBytes(100,10,30,true));
foreach([true,false] as $locked){rejected(fn()=>assertRawNativePeriod('2026-02',$before,dummyLockedBytes(999,10,30,$locked)));rejected(fn()=>assertRawNativePeriod('2026-02',$before,dummyLockedBytes(100,999,30,$locked)));}
$first=dummyLockedBytes(100,0,20,true);rejected(fn()=>assertRawNativePeriod('2026-02',$first,dummyLockedBytes(100,55,20,true)));
echo "PASS locked/relocked dummy uploads: annual target and prior actual tampering/deletion rejected, newly populated past values rejected, January targets/current actual accepted. No live DB or user files.\n";
