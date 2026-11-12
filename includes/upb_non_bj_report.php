<?php
require_once __DIR__.'/upb_non_bj_input_template.php';
/** Two annual summaries, then current-year and prior-year detail blocks. */
function buildUpbNonBjReportOds(string $period,?string $current=null,?string $prior=null): string {
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$m))throw new InvalidArgumentException('Periode UPB NON BJ tidak valid.');
    $inputs=[buildUpbNonBjInputTemplateOds($period,$current,$current!==null),buildUpbNonBjInputTemplateOds(((int)$m[1]-1).'-'.$m[2],$prior,$prior!==null)];
    $paths=[];$zips=[];
    try{
        foreach($inputs as $i=>$bytes){$paths[$i]=tempnam(sys_get_temp_dir(),'nonbj-report-');file_put_contents($paths[$i],$bytes);$zips[$i]=new ZipArchive();if($zips[$i]->open($paths[$i])!==true)throw new RuntimeException('File UPB NON BJ tidak dapat dibaca.');}
        $doc=upbBjXml($zips[0]->getFromName('content.xml'));$priorXml=$zips[1]->getFromName('content.xml');
        $prefix=static fn($xml)=>preg_replace('/((?:style:name|style:parent-style-name|style:next-style-name|style:apply-style-name|style:data-style-name|style:master-page-name|style:page-layout-name|style:font-name(?:-asian|-complex)?|table:style-name|table:default-cell-style-name|text:style-name)=")/','$1NONBJPRIOR_',$xml);
        $other=upbBjXml($prefix($priorXml));
        foreach(['automatic-styles','font-face-decls'] as $section)upbBjMergeStyles($doc->getElementsByTagNameNS(UPB_O,$section)->item(0),$other->getElementsByTagNameNS(UPB_O,$section)->item(0),$doc);
        $styles=upbBjXml($zips[0]->getFromName('styles.xml'));$oldStyles=upbBjXml($prefix($zips[1]->getFromName('styles.xml')));
        foreach(['styles','automatic-styles','master-styles','font-face-decls'] as $section){$to=$styles->getElementsByTagNameNS(UPB_O,$section)->item(0);$from=$oldStyles->getElementsByTagNameNS(UPB_O,$section)->item(0);if($to&&$from)upbBjMergeStyles($to,$from,$styles);}
        $sources=[array_values(upbBjTables($doc))[0],array_values(upbBjTables($other))[0]];$groups=[];
        foreach($sources as $i=>$table){
            $cells=upbBjCells($table);$summaryEnd=null;$detailStart=null;$detailEnd=null;
            foreach($cells as $r=>$row){$label=strtoupper(trim(preg_replace('/\s+/',' ',$row[1]->textContent??'')));
                if(str_starts_with($label,'TOTAL UPB NON BJ'))$summaryEnd=$r;
                if(str_starts_with($label,'UPB BAHAN BAKU'))$detailStart=$r;
                if($label==='TOTAL')$detailEnd=$r;
            }
            if(!$summaryEnd||!$detailStart||!$detailEnd)throw new InvalidArgumentException('Kerangka tabel UPB NON BJ tidak lengkap.');
            $r=1;$groups[$i]=['summary'=>[],'detail'=>[]];
            foreach($table->childNodes as $node)if($node instanceof DOMElement&&$node->localName==='table-row'){
                if($r<=$summaryEnd)$groups[$i]['summary'][$r]=$node->cloneNode(true);
                if($r>=$detailStart&&$r<=$detailEnd)$groups[$i]['detail'][$r]=$node->cloneNode(true);
                $r+=max(1,(int)$node->getAttributeNS(UPB_T,'number-rows-repeated'));
            }
        }
        $report=$sources[0];foreach(iterator_to_array($report->childNodes) as $node)if($node instanceof DOMElement&&$node->localName==='table-row')$report->removeChild($node);
        $report->setAttributeNS(UPB_T,'table:name','UPB non BJ');$maps=[[],[]];$copies=[];$position=1;
        foreach([[0,'summary'],[1,'summary'],[0,'detail'],[1,'detail']] as [$i,$kind]){
            if($position>1){$gap=$doc->createElementNS(UPB_T,'table:table-row');$gap->appendChild($doc->createElementNS(UPB_T,'table:table-cell'));$report->appendChild($gap);$position++;}
            foreach($groups[$i][$kind] as $old=>$node){$row=$doc->importNode($node,true);$report->appendChild($row);$maps[$i][$old]=$position;$copies[]=[$i,$row];$position+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
        }
        foreach($copies as [$i,$row])foreach($row->getElementsByTagNameNS(UPB_T,'table-cell') as $cell)if($cell->hasAttributeNS(UPB_T,'formula')){
            $formula=$cell->getAttributeNS(UPB_T,'formula');
            $formula=preg_replace_callback('/\[([^\]]+)\]/',static function($match)use($maps,$i){return '['.preg_replace_callback('/(?:(?:\$?\x27[^\x27]*\x27|\$?[A-Za-z_][A-Za-z0-9_ ]*)?\.)(\$?[A-Z]+\$?)(\d+)/',static fn($ref)=>'.'.$ref[1].($maps[$i][(int)$ref[2]]??$ref[2]),$match[1]).']';},$formula);
            $cell->setAttributeNS(UPB_T,'table:formula',$formula);
        }
        $zips[0]->addFromString('content.xml',$doc->saveXML());$zips[0]->addFromString('styles.xml',$styles->saveXML());
        foreach($zips as $zip)$zip->close();$zips=[];return file_get_contents($paths[0]);
    }finally{foreach($zips as $zip)$zip->close();foreach($paths as $path)@unlink($path);}
}
