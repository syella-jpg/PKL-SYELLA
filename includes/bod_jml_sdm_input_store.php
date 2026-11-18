<?php
use PhpOffice\PhpSpreadsheet\IOFactory;

function bodJmlSdmInputPeriod(string $period): void {
    if (!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $period)) throw new InvalidArgumentException('Periode BOD JML SDM tidak valid.');
}
function ensureBodJmlSdmInputStore(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS `bod_jml_sdm_input` (report_period CHAR(7) NOT NULL PRIMARY KEY, filename VARCHAR(255) NOT NULL, base64 LONGTEXT NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function findBodJmlSdmInput(mysqli $conn, string $period): ?array {
    bodJmlSdmInputPeriod($period); ensureBodJmlSdmInputStore($conn);
    $q=$conn->prepare('SELECT report_period,filename,base64 FROM `bod_jml_sdm_input` WHERE report_period=?');
    $q->bind_param('s',$period);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();return $row?:null;
}
function validateBodJmlSdmInputBytes(string $bytes): void {
    if(strlen($bytes)>50*1024*1024)throw new InvalidArgumentException('File maksimal 50 MB.');
    $path=tempnam(sys_get_temp_dir(),'bod-jml-sdm-');$zip=new ZipArchive();$opened=false;
    try{
        file_put_contents($path,$bytes);if($zip->open($path)!==true)throw new InvalidArgumentException('Gunakan template BOD JML SDM berformat ODS.');$opened=true;
        if(trim((string)$zip->getFromName('mimetype'))!=='application/vnd.oasis.opendocument.spreadsheet')throw new InvalidArgumentException('Gunakan template BOD JML SDM berformat ODS.');
        $zip->close();$opened=false;$book=IOFactory::load($path);$sheet=$book->getSheetByName('BOD JML SDM')??$book->getSheet(0);$labels=[];
        foreach($sheet->getRowIterator() as $row)foreach($row->getCellIterator() as $cell){$v=$cell->getValue();if(is_string($v)&&!$cell->isFormula())$labels[]=strtoupper(preg_replace('/\s+/u',' ',trim($v)));}
        $required=['KETERANGAN','REAL 2025','PLAN 2026','PLAN S/D BULAN INI','REAL S/D BULAN INI','SELISIH'];
        foreach($required as $label)if(!in_array($label,$labels,true))throw new InvalidArgumentException('Header '.$label.' tidak ditemukan. Gunakan template BOD JML SDM.');
        $book->disconnectWorksheets();
    }catch(InvalidArgumentException $e){throw $e;}catch(Throwable $e){throw new InvalidArgumentException('Isi file ODS BOD JML SDM tidak dapat dibaca.');}
    finally{if($opened)$zip->close();@unlink($path);}
}
function saveBodJmlSdmInput(mysqli $conn,string $period,string $bytes,string $filename): void {
    bodJmlSdmInputPeriod($period);validateBodJmlSdmInputBytes($bytes);ensureBodJmlSdmInputStore($conn);
    $filename=mb_substr(basename($filename),0,255);$base64=base64_encode($bytes);
    $q=$conn->prepare('INSERT INTO `bod_jml_sdm_input` (report_period,filename,base64) VALUES (?,?,?) ON DUPLICATE KEY UPDATE filename=VALUES(filename),base64=VALUES(base64)');
    $q->bind_param('sss',$period,$filename,$base64);$q->execute();$q->close();
}
function loadBodJmlSdmInputOds(mysqli $conn,string $period): string {
    bodJmlSdmInputPeriod($period);$record=findBodJmlSdmInput($conn,$period);
    if($record){$bytes=base64_decode($record['base64'],true);if($bytes===false)throw new RuntimeException('File BOD JML SDM tersimpan tidak valid.');return $bytes;}
    $path=__DIR__.'/../dashboard/assets/input-templates/bod-jml-sdm.ods';$bytes=file_get_contents($path);
    if($bytes===false)throw new RuntimeException('Template BOD JML SDM tidak ditemukan.');return $bytes;
}
