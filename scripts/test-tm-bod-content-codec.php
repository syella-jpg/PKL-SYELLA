<?php
require __DIR__.'/../includes/tm_bod_content_codec.php';
function check($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$small=['Q2'=>['Analisis biasa']];
check(decodeTmBodContent(encodeTmBodContent($small)) === $small,'Small round trip failed');
check(decodeTmBodContent(json_encode($small)) === $small,'Legacy JSON failed');
$image='data:image/png;base64,'.base64_encode(random_bytes(420000));
$value=['text'=>'Laporan besar','html'=>'<b>Laporan besar</b>','images'=>[$image]];
$large=['Q2'=>[$value],'Q2-section-ids'=>['base-1'],'Q2-section-values'=>['base-1'=>$value]];
$raw=json_encode($large);
$packed=encodeTmBodContent($large);
check(strlen($raw)>1048576,'Fixture must exceed 1 MB');
check(strlen($packed)<1048576-16384,'Compressed fixture exceeds packet limit');
check(decodeTmBodContent($packed)===$large,'Rich text or image changed during compression');
echo 'PASS legacy JSON and lossless rich-text/images: '.strlen($raw).' bytes -> '.strlen($packed)." bytes\n";

$bigImage='data:image/png;base64,'.base64_encode(random_bytes(1800000));
$bigValue=['text'=>'Analisis lebih dari 4 MB','html'=>'<b>Analisis</b>','images'=>[$bigImage]];
$big=['Q3'=>[$bigValue],'Q3-section-ids'=>['base-2'],'Q3-section-values'=>['base-2'=>$bigValue]];
check(strlen(json_encode($big))>4*1024*1024,'Fixture must exceed old 4 MB limit');
check(decodeTmBodContent(encodeTmBodContent($big))===$big,'Large content round trip failed');
try { encodeTmBodContent(['Q1'=>[str_repeat('x',TM_BOD_CONTENT_MAX_BYTES)]]); throw new RuntimeException('New 32 MB limit not enforced'); }
catch (InvalidArgumentException $e) { check(strpos($e->getMessage(),'32 MB')!==false,'Wrong limit message'); }
echo "PASS >4 MB rich-text/image round trip and 32 MB upper limit\n";
