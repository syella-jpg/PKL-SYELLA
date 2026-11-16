<?php
require_once __DIR__.'/../includes/ods_upload_xml.php';
require_once __DIR__.'/../includes/ods_upload_detection.php';
$xmlPath=tempnam(sys_get_temp_dir(),'ods-large-xml-');$odsPath=tempnam(sys_get_temp_dir(),'ods-large-file-');
try{
 $stream=fopen($xmlPath,'wb');$head='<?xml version="1.0"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"><office:body><office:spreadsheet><table:table table:name="JANUARI"><table:table-row><table:table-cell><text:p>DATABASE JAN SD JAN 2026</text:p></table:table-cell></table:table-row>';
 fwrite($stream,$head);$block='<!--'.str_repeat('large-valid-xml ',70).'-->';
 for($i=0;$i<66000;$i++)fwrite($stream,$block);
 fwrite($stream,'</table:table></office:spreadsheet></office:body></office:document-content>');fclose($stream);
 if(filesize($xmlPath)<=64*1024*1024)throw new RuntimeException('Regression source must exceed former XML limit');
 $zip=new ZipArchive();$zip->open($odsPath,ZipArchive::OVERWRITE);$zip->addFile($xmlPath,'content.xml');$zip->close();
 $book=readOdsUploadDetection($odsPath);if(($book['Sheets']['JANUARI']['A1']['v']??'')!=='DATABASE JAN SD JAN 2026')throw new RuntimeException('Large valid ODS rejected by recognition');
 $xml=readOdsUploadContent($odsPath);if(hash('sha256',$xml)!==hash_file('sha256',$xmlPath))throw new RuntimeException('Native XML changed');
 echo "PASS: valid compressed ODS with XML above 64 MB is recognized and read without changing native content.\n";
}finally{@unlink($xmlPath);@unlink($odsPath);}
