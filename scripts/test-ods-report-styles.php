<?php
require_once __DIR__.'/../includes/upb_bj_template_history.php';
require_once __DIR__.'/../includes/ods_report_styles.php';
function styleDefinitions($docs){$out=[];$ns='urn:oasis:names:tc:opendocument:xmlns:style:1.0';foreach($docs as $d)foreach((new DOMXPath($d))->query('//*') as $node){$name=$node->getAttributeNS($ns,'name');if($name!=='')$out[$name][]=$d->saveXML($node);}return $out;}
foreach(['upb-non-bj.ods','upb-bj-kx-report.ods'] as $file){
 $z=new ZipArchive();$z->open(__DIR__.'/../dashboard/assets/input-templates/'.$file);$content=upbBjXml($z->getFromName('content.xml'));$styles=upbBjXml($z->getFromName('styles.xml'));$z->close();
 $body=$content->getElementsByTagNameNS(UPB_O,'body')->item(0)->C14N();$before=styleDefinitions([$content,$styles]);$size=strlen($styles->saveXML());trimUnusedReportOdsStyles($content,$styles);$after=styleDefinitions([$content,$styles]);
 if($content->getElementsByTagNameNS(UPB_O,'body')->item(0)->C14N()!==$body)throw new RuntimeException('Sheet content changed');
 foreach($after as $name=>$definitions)if($definitions!==$before[$name])throw new RuntimeException('Used style definition changed');
 foreach([$content,$styles] as $d)foreach((new DOMXPath($d))->query('//*') as $node)foreach($node->attributes as $a)if(str_ends_with($a->localName,'-name')||in_array($a->localName,['font-name-asian','font-name-complex'],true))if(isset($before[$a->value])&&!isset($after[$a->value]))throw new RuntimeException('Referenced dependency removed');
 if(strlen($styles->saveXML())>=$size/10)throw new RuntimeException('Unused styles not reduced');
}
echo "PASS: report cells/formulas/merges unchanged, retained styles identical, all named dependencies retained and unused style size reduced >90%.\n";
