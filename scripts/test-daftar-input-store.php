<?php
require_once __DIR__.'/../includes/daftar_input_store.php';
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../vendor/autoload.php';
function checkDaftar($condition,$message){if(!$condition)throw new RuntimeException($message);}
$period='2098-02';$path=tempnam(sys_get_temp_dir(),'daftar-fixture-');$conn=null;
try{
 file_put_contents($path,buildDaftarInputTemplateOds($period));$book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$sheet=$book->getSheetByName('DAFTAR');$sheet->setCellValue('M7','V');$sheet->setCellValue('M8','X');$sheet->insertNewRowBefore(10,1);$sheet->setCellValue('D10','DAFTAR_SYNTHETIC_EXTRA_ROW');$sheet->setCellValue('J56','=COUNTIF(M7:M17,"V")');(new \PhpOffice\PhpSpreadsheet\Writer\Ods($book))->save($path);$book->disconnectWorksheets();$bytes=file_get_contents($path);
 validateDaftarInputBytes($bytes,$period);
 try{validateDaftarInputBytes($bytes,'2098-03');throw new RuntimeException('Wrong period accepted');}catch(InvalidArgumentException $expected){}
 $next=buildDaftarStoredInputTemplateOds('2098-03',$bytes);validateDaftarInputBytes($next,'2098-03');$z=new ZipArchive();$p=tempnam(sys_get_temp_dir(),'daftar-next-');try{file_put_contents($p,$next);$z->open($p);$xml=$z->getFromName('content.xml');$z->close();checkDaftar(str_contains($xml,'DAFTAR_SYNTHETIC_EXTRA_ROW'),'Added row lost');checkDaftar(str_contains($xml,'COUNTIF'),'Formula lost');}finally{@unlink($p);}
 $conn=openReportConnection();ensureDaftarInputStore($conn);
 checkDaftar(findDaftarInput($conn,'2098-12')===null,'Reserved test year already contains data; refusing to overwrite');
 saveDaftarInput($conn,$period,$bytes,'synthetic.ods');
 try{
  checkDaftar(loadDaftarInputTemplateOds($conn,$period)===$bytes,'Database exact-period round-trip changed bytes');
  checkDaftar(findDaftarInput($conn,'2098-01')===null,'Future upload leaked into earlier period');
  checkDaftar(findDaftarInput($conn,'2099-01')===null,'Prior-year data leaked into next year');
  $march=loadDaftarInputTemplateOds($conn,'2098-03');validateDaftarInputBytes($march,'2098-03');
  checkDaftar(base64_decode(findDaftarInput($conn,$period)['base64'],true)===$bytes,'Later download changed historical upload');
 }finally{$q=$conn->prepare('DELETE FROM `daftar` WHERE report_period=?');$q->bind_param('s',$period);$q->execute();$q->close();}
 file_put_contents('/tmp/daftar-upload-synthetic.ods',$bytes);
 echo "PASS: DAFTAR period validation, added rows/formulas, database round-trip, same-year continuation, earlier-period isolation; synthetic DB rows removed.\n";
}finally{if($conn)$conn->close();@unlink($path);}
