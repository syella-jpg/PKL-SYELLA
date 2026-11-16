<?php
// Run the upload pipeline under 128 MB with XML large enough that two PHP
// string copies would exceed that limit. Native XML and styles must survive.
require_once __DIR__.'/../includes/upb_distr_input_store.php';
$path=tempnam(sys_get_temp_dir(),'distr-memory-');$xmlPath=tempnam(sys_get_temp_dir(),'distr-memory-xml-');$child=tempnam(sys_get_temp_dir(),'distr-memory-child-');
try{
 file_put_contents($path,buildUpbDistrInputTemplateOds('2026-01'));$zip=new ZipArchive();$zip->open($path);$xml=$zip->getFromName('content.xml');
 $position=strrpos($xml,'</office:document-content>');if($position===false)throw new RuntimeException('Missing XML document end');
 $stream=fopen($xmlPath,'wb');fwrite($stream,substr($xml,0,$position));$block='<!--'.str_repeat('memory-regression ',1024).'-->';
 for($i=0;$i<4200;$i++)fwrite($stream,$block);fwrite($stream,substr($xml,$position));fclose($stream);unset($xml,$block);
 $zip->addFile($xmlPath,'content.xml');$zip->close();
 $code='<?php require '.var_export(realpath(__DIR__.'/../includes/upb_distr_input_store.php'),true).'; $bytes=prepareUpbDistrInputOds(file_get_contents($argv[1]),"2026-01"); $doc=distrReadOds($bytes); $parts=distrMonthInfo(upbBjTables($doc)["JANUARI"]); if(count($parts)!==8)exit(2); foreach($parts as $p)if($p["last"]-$p["start"]+1!==2500)exit(3); echo json_encode(["peak"=>memory_get_peak_usage(true)]);';
 file_put_contents($child,$code);$output=[];$status=0;exec(escapeshellarg(PHP_BINARY).' -d memory_limit=128M '.escapeshellarg($child).' '.escapeshellarg($path).' 2>&1',$output,$status);
 $result=json_decode(implode("\n",$output),true);if($status!==0||!isset($result['peak']))throw new RuntimeException('Bounded-memory upload failed: '.implode("\n",$output));
 echo 'PASS: '.round(filesize($xmlPath)/1048576,1).' MB XML upload and reread under 128 MB limit; PHP peak '.round($result['peak']/1048576,1)." MB; all groups and 2500 rows retained.\n";
}finally{@unlink($path);@unlink($xmlPath);@unlink($child);}
