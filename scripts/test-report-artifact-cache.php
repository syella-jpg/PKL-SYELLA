<?php
require_once __DIR__.'/../includes/report_artifact_cache.php';
$dependency=tempnam(sys_get_temp_dir(),'report-dependency-test-');file_put_contents($dependency,'v1');$nonce=bin2hex(random_bytes(8));$calls=0;
$build=static function()use(&$calls){return 'artifact-'.++$calls;};
try{
 $key=reportArtifactKey([$nonce,'2026-09','source-a'],[$dependency]);
 if(cachedReportArtifact('test',$key,$build)!=='artifact-1'||cachedReportArtifact('test',$key,$build)!=='artifact-1'||$calls!==1)throw new RuntimeException('Cache reuse');
 $key=reportArtifactKey([$nonce,'2026-09','source-b'],[$dependency]);if(cachedReportArtifact('test',$key,$build)!=='artifact-2')throw new RuntimeException('Source invalidation');
 $key=reportArtifactKey([$nonce,'2026-10','source-b'],[$dependency]);if(cachedReportArtifact('test',$key,$build)!=='artifact-3')throw new RuntimeException('Period isolation');
 file_put_contents($dependency,'v2');$key=reportArtifactKey([$nonce,'2026-10','source-b'],[$dependency]);if(cachedReportArtifact('test',$key,$build)!=='artifact-4')throw new RuntimeException('Code invalidation');
 $failed=reportArtifactKey([$nonce,'failure'],[]);try{cachedReportArtifact('test',$failed,static function(){throw new RuntimeException('synthetic failure');});}catch(RuntimeException $e){}
 if(cachedReportArtifact('test',$failed,$build)!=='artifact-5')throw new RuntimeException('Failed result cached');
 echo "PASS: derived artifacts reused, new source/period/code invalidate, failures retried.\n";
}finally{unlink($dependency);}
