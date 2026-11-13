<?php
/** Private derived files only; every caller still reads its current database sources. */
function reportArtifactKey(array $sources,array $dependencies): string
{
    $hash=hash_init('sha256');
    foreach($sources as $value)hash_update($hash,hash('sha256',(string)$value));
    foreach($dependencies as $path)hash_update($hash,$path.':'.(is_file($path)?hash_file('sha256',$path):'missing'));
    return hash_final($hash);
}
function cachedReportArtifact(string $kind,string $key,callable $build): string
{
    $uid=function_exists('posix_geteuid')?posix_geteuid():getmyuid();
    $directory=sys_get_temp_dir().'/corporate-report-artifacts-'.$uid;
    if(!is_dir($directory))@mkdir($directory,0700,true);
    if(!is_dir($directory)||is_link($directory)||!is_writable($directory))return $build();
    $path=$directory.'/'.hash('sha256',$kind.':'.$key).'.cache';
    if(is_file($path)&&!is_link($path)&&filemtime($path)>time()-86400){$bytes=@file_get_contents($path);if($bytes!==false&&$bytes!=='')return $bytes;}
    $bytes=$build();
    if(!is_string($bytes))throw new RuntimeException('Hasil pembuatan laporan tidak valid.');
    $temporary=@tempnam($directory,'pending-');
    if($temporary!==false){@chmod($temporary,0600);if(@file_put_contents($temporary,$bytes,LOCK_EX)!==false)@rename($temporary,$path);if(is_file($temporary))@unlink($temporary);}
    return $bytes;
}
