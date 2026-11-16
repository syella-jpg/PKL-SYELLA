<?php
require_once __DIR__.'/../includes/ods_upload_detection.php';
require_once __DIR__.'/../includes/upb_distr_input.php';
require_once __DIR__.'/../includes/upb_non_bj_input_template.php';
require_once __DIR__.'/../includes/upb_bj_input_template.php';
function previewCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
$fixtures=['distr'=>buildUpbDistrInputTemplateOds('2026-03'),'pareto'=>buildDataParetoUpbTemplateOds('2026-03'),'nonbj'=>buildUpbNonBjInputTemplateOds('2026-03'),'bj'=>buildUpbBjInputTemplateOds('2026-03')];
$previews=[];foreach($fixtures as $key=>$bytes){$path=tempnam(sys_get_temp_dir(),'ods-preview-');try{file_put_contents($path,$bytes);$book=readOdsUploadDetection($path);previewCheck(array_sum(array_map('count',$book['Sheets']))<=5000,'Cell budget');previewCheck(strlen(json_encode($book))<300000,'Bounded JSON');$previews[$key]=$book;}finally{unlink($path);}}
// A formatted ODS row can cover the entire sheet; recognition must not expand it.
$path=tempnam(sys_get_temp_dir(),'ods-repeat-');try{$zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('content.xml','<?xml version="1.0"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"><office:body><office:spreadsheet><table:table table:name="JANUARI"><table:table-row><table:table-cell><text:p>DATABASE JAN SD JAN 2026</text:p></table:table-cell></table:table-row><table:table-row table:number-rows-repeated="1048575"><table:table-cell table:number-columns-repeated="16384"/></table:table-row></table:table></office:spreadsheet></office:body></office:document-content>');$zip->close();$start=microtime(true);$book=readOdsUploadDetection($path);previewCheck(count($book['Sheets']['JANUARI'])===1,'Repeated blank rows/columns remain unexpanded');previewCheck(microtime(true)-$start<2,'Recognition does not iterate logical cell count');}finally{unlink($path);}
$path='/tmp/ods-upload-detection-fixtures.json';file_put_contents($path,json_encode($previews,JSON_THROW_ON_ERROR));chmod($path,0600);
echo "PASS: bounded ODS previews, month headers, native UPB templates and unexpanded full-sheet formatting.\n";
