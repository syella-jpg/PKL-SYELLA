<?php
use PhpOffice\PhpSpreadsheet\IOFactory;

function launchInputPeriod(string $period): void {
    if (!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $period)) throw new InvalidArgumentException('Periode LAUNCH tidak valid.');
}
function ensureLaunchInputStore(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS `launch_input` (report_period CHAR(7) NOT NULL PRIMARY KEY, filename VARCHAR(255) NOT NULL, base64 LONGTEXT NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function findLaunchInput(mysqli $conn, string $period): ?array {
    launchInputPeriod($period); ensureLaunchInputStore($conn);
    $first=substr($period,0,4).'-01';
    $q=$conn->prepare('SELECT report_period,filename,base64 FROM `launch_input` WHERE report_period BETWEEN ? AND ? ORDER BY report_period DESC LIMIT 1');
    $q->bind_param('ss',$first,$period);$q->execute();$record=$q->get_result()->fetch_assoc();$q->close();
    return $record?:null;
}
function validateLaunchInputBytes(string $bytes): void {
    if(strlen($bytes)>50*1024*1024)throw new InvalidArgumentException('File maksimal 50 MB.');
    $path=tempnam(sys_get_temp_dir(),'launch-upload-');$zip=new ZipArchive();$opened=false;
    try{
        file_put_contents($path,$bytes);
        if($zip->open($path)!==true)throw new InvalidArgumentException('Gunakan file LAUNCH berformat ODS.');
        $opened=true;
        if(trim((string)$zip->getFromName('mimetype'))!=='application/vnd.oasis.opendocument.spreadsheet')throw new InvalidArgumentException('Gunakan file LAUNCH berformat ODS.');
        $zip->close();$opened=false;
        $book=IOFactory::load($path);$sheet=$book->getSheetByName('LAUNCH')??$book->getSheet(0);$found=false;
        foreach($sheet->getRowIterator() as $row)foreach($row->getCellIterator() as $cell){
            if(!is_string($cell->getValue()))continue;
            $value=strtoupper(preg_replace('/\s+/u',' ',trim($cell->getValue())));
            if(in_array($value,['NAMA PRODUK','RENCANA LAUNCH','REALISASI LAUNCH','WAKTU PERSETUJUAN LAUNCH','GM MKT'],true)){$found=true;break 2;}
        }
        $book->disconnectWorksheets();
        if(!$found)throw new InvalidArgumentException('Header tabel LAUNCH tidak ditemukan. Baris dan kolom boleh kosong atau berubah; pertahankan minimal satu header utama.');
    }catch(InvalidArgumentException $e){throw $e;}catch(Throwable $e){throw new InvalidArgumentException('Isi file ODS LAUNCH tidak dapat dibaca.');}
    finally{if($opened)$zip->close();@unlink($path);}
}
function saveLaunchInput(mysqli $conn,string $period,string $bytes,string $filename): void {
    launchInputPeriod($period);validateLaunchInputBytes($bytes);ensureLaunchInputStore($conn);
    $filename=mb_substr(basename($filename),0,255);$base64=base64_encode($bytes);
    $q=$conn->prepare('INSERT INTO `launch_input` (report_period,filename,base64) VALUES (?,?,?) ON DUPLICATE KEY UPDATE filename=VALUES(filename),base64=VALUES(base64)');
    $q->bind_param('sss',$period,$filename,$base64);$q->execute();$q->close();
}
function loadLaunchInputOds(mysqli $conn,string $period): string {
    launchInputPeriod($period);$record=findLaunchInput($conn,$period);
    if($record){$bytes=base64_decode($record['base64'],true);if($bytes===false)throw new RuntimeException('File LAUNCH tersimpan tidak valid.');return $bytes;}
    $path=__DIR__.'/../dashboard/assets/input-templates/launch.ods';$bytes=file_get_contents($path);
    if($bytes===false)throw new RuntimeException('Template LAUNCH tidak ditemukan.');return $bytes;
}
