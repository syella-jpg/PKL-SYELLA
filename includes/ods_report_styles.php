<?php
/** Retain every referenced ODF style and its dependencies; omit unused definitions. */
function trimUnusedReportOdsStyles(DOMDocument $content, DOMDocument $styles): void
{
    $office='urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    $style='urn:oasis:names:tc:opendocument:xmlns:style:1.0';
    $definitions=[];$roots=[];
    foreach([$content,$styles] as $doc)foreach($doc->documentElement->childNodes as $section){
        if(!$section instanceof DOMElement)continue;
        if($section->namespaceURI!==$office||!in_array($section->localName,['styles','automatic-styles','master-styles','font-face-decls'],true)){$roots[]=$section;continue;}
        foreach($section->childNodes as $node)if($node instanceof DOMElement){
            $name=$node->getAttributeNS($style,'name');
            if($name==='')$roots[]=$node;else $definitions[$name][]=$node;
        }
    }
    $needed=[];$queue=[];
    $scan=static function(DOMElement $root)use(&$needed,&$queue){
        $nodes=[$root];
        while($nodes){$node=array_pop($nodes);
            foreach($node->attributes as $attribute){
                $key=$attribute->localName;
                if(str_ends_with($key,'-name')||in_array($key,['font-name-asian','font-name-complex'],true)){
                    $name=$attribute->value;if($name!==''&&!isset($needed[$name])){$needed[$name]=true;$queue[]=$name;}
                }
            }
            foreach($node->childNodes as $child)if($child instanceof DOMElement)$nodes[]=$child;
        }
    };
    foreach($roots as $root)$scan($root);
    for($i=0;$i<count($queue);$i++)foreach($definitions[$queue[$i]]??[] as $node)$scan($node);
    foreach($definitions as $name=>$nodes)if(!isset($needed[$name]))foreach($nodes as $node)$node->parentNode->removeChild($node);
}
