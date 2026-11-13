<?php
require_once __DIR__.'/../includes/upb_bj_template_history.php';
require_once __DIR__.'/../includes/ods_report_styles.php';
$path=__DIR__.'/../dashboard/report-templates.js';$source=file_get_contents($path);$count=0;$before=0;$after=0;
$updated=preg_replace_callback('/(const [A-Z0-9_]+\s*=\s*")([A-Za-z0-9+\/=]{1000,})(";)/',static function($m)use(&$count,&$before,&$after){
    if(str_contains($m[1],'BOD_MNJ_'))return $m[0];
    $bytes=base64_decode($m[2],true);$temp=tempnam(sys_get_temp_dir(),'report-layout-');file_put_contents($temp,$bytes);$zip=new ZipArchive();$opened=false;
    try{
        if($zip->open($temp)!==true)return $m[0];$opened=true;
        if($zip->locateName('content.xml')===false||$zip->locateName('styles.xml')===false)return $m[0];
        $content=upbBjXml($zip->getFromName('content.xml'));$styles=upbBjXml($zip->getFromName('styles.xml'));
        $original=$content->getElementsByTagNameNS(UPB_O,'body')->item(0)->C14N();
        $before+=strlen($styles->saveXML());trimUnusedReportOdsStyles($content,$styles);$after+=strlen($styles->saveXML());
        if($content->getElementsByTagNameNS(UPB_O,'body')->item(0)->C14N()!==$original)throw new RuntimeException('Report body changed during style compaction.');
        $zip->addFromString('content.xml',$content->saveXML());$zip->addFromString('styles.xml',$styles->saveXML());$zip->close();$opened=false;$count++;
        return $m[1].base64_encode(file_get_contents($temp)).$m[3];
    }finally{if($opened)$zip->close();unlink($temp);}
},$source);
if($updated===null||$count!==6)throw new RuntimeException('Expected six static report layouts.');
file_put_contents($path,$updated);
echo json_encode(['layouts'=>$count,'style_bytes_before'=>$before,'style_bytes_after'=>$after,'all_report_bodies_unchanged'=>true]),"\n";
