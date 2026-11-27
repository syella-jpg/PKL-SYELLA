<?php
require_once __DIR__.'/upb_bj_template_history.php';
require_once __DIR__.'/raw_template_protection.php';
function rawPeriodMonth(string $text): ?int {
    $text=strtoupper(trim(preg_replace('/\s+/u',' ',$text)));
    $aliases=[['JAN','JANUARI'],['FEB','FEBRUARI'],['MAR','MARET'],['APR','APRIL'],['MEI','MAY'],['JUN','JUNI'],['JUL','JULI'],['AGS','AGT','AGU','AUG','AGUSTUS'],['SEP','SEPT','SEPTEMBER'],['OKT','OCT','OKTOBER'],['NOV','NOP','NOVEMBER'],['DES','DEC','DESEMBER']];
    $text=preg_replace('/^(?:BULAN |S\/D )/','',$text);$text=preg_replace('/ 20\d{2}$/','',$text);
    foreach($aliases as $m=>$names)if(in_array($text,$names,true))return $m;
    return null;
}
function rawPeriodDocument(string $bytes): DOMDocument {
    $path=tempnam(sys_get_temp_dir(),'raw-period-');$zip=new ZipArchive();
    try{file_put_contents($path,$bytes);if($zip->open($path)!==true)throw new InvalidArgumentException('File ODS tidak valid.');try{$xml=$zip->getFromName('content.xml');if(!is_string($xml)){
                if(!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory'))require_once __DIR__.'/../vendor/autoload.php';
                $book=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);$out=tempnam(sys_get_temp_dir(),'raw-convert-');
                try{$writer=new \PhpOffice\PhpSpreadsheet\Writer\Ods($book);$writer->setPreCalculateFormulas(false);$writer->save($out);$converted=new ZipArchive();if($converted->open($out)!==true)throw new RuntimeException('Konversi template gagal.');try{$xml=$converted->getFromName('content.xml');}finally{$converted->close();}}finally{$book->disconnectWorksheets();unlink($out);}
                if(!is_string($xml))throw new InvalidArgumentException('Isi template tidak ditemukan.');
            }return upbBjXml($xml);}finally{$zip->close();}}finally{unlink($path);}
}
/** Monthly sheet contents are immutable; wide-sheet values are keyed by labels. */
function rawNativeMonthValues(DOMDocument $doc,string $period): array {
    $out=[];$year=substr($period,0,4);
    foreach(upbBjTables($doc) as $name=>$table){
        if(in_array(strtoupper($name),['REKAP','KESIMPULAN'],true)||str_starts_with(strtoupper($name),'TARGET'))continue;
        $sheetMonth=rawPeriodMonth($name);$cells=upbBjCells($table);$active=[];$section='';$duplicates=[];$blockYear=$year;
        foreach($cells as $r=>$row){
            foreach($row as $cell){$label=strtoupper(trim($cell->textContent));if(preg_match('/^(?:TH\.?|TAHUN)\s*(20\d{2})$/',$label,$match))$blockYear=$match[1];}
            if($sheetMonth===null&&$blockYear!==$year)continue;
            $headers=[];foreach($row as $c=>$cell){$m=rawPeriodMonth($cell->textContent);if($m!==null)for($span=0;$span<max(1,(int)$cell->getAttributeNS(UPB_T,'number-columns-spanned'));$span++)$headers[$c+$span]=$m;}
            if(count(array_unique($headers))>=2){$active=$headers;continue;}
            $labels=[];foreach($row as $c=>$cell){if($cell->hasAttributeNS(UPB_O,'value')||$cell->hasAttributeNS(UPB_T,'formula'))continue;$v=trim(preg_replace('/\s+/u',' ',$cell->textContent));if($v!=='')$labels[$c]=$v;}
            if($sheetMonth!==null){
                foreach($row as $c=>$cell){
                    $f=$cell->getAttributeNS(UPB_T,'formula');$v=$cell->hasAttributeNS(UPB_O,'value')?$cell->getAttributeNS(UPB_O,'value'):trim($cell->textContent);
                    if($f!==''||$v!=='')$out[$name][$sheetMonth][$r.':'.$c]=$f!==''?'formula:'.$f:$v;
                }
                continue;
            }
            if(!$active)continue;
            if(preg_match('/\b(?:TARGET|BUDGET|ANGGARAN|ROD)\b/i',implode('|',$labels)))continue;
            $first=min(array_keys($active));$identity=implode('|',array_filter($labels,static fn($c)=>$c<$first,ARRAY_FILTER_USE_KEY));
            if($identity===''){if($labels)$section=implode('|',$labels);continue;}
            if(preg_match('/20\d{2}/',$identity,$match)&&$match[0]!==$year)continue;
            $key=$section.'|'.$identity;$occurrence=$duplicates[$key]??0;$duplicates[$key]=$occurrence+1;
            foreach($active as $c=>$m){$cell=$row[$c]??null;if(!$cell||$cell->hasAttributeNS(UPB_T,'formula'))continue;
                $v=$cell->getAttributeNS(UPB_O,'value');if($v!=='')$out[$name][$m][$key.'|'.$occurrence]=(float)$v;
            }
        }
    }
    return $out;
}
function assertRawNativePeriod(string $period,string $before,string $after): void {
    $old=rawNativeMonthValues(rawPeriodDocument($before),$period);$new=rawNativeMonthValues(rawPeriodDocument($after),$period);$selected=(int)substr($period,5,2)-1;
    foreach($old as $sheet=>$months)foreach($months as $month=>$values){
        if($month>=$selected)continue;
        $incoming=$new[$sheet][$month]??[];ksort($values);ksort($incoming);
        if($values==$incoming)continue;
        $locked=substr($period,0,4).'-'.str_pad((string)($month+1),2,'0',STR_PAD_LEFT);
        throw new InvalidArgumentException('Realisasi periode '.$locked.' tidak boleh diubah melalui upload '.$period.'. Hubungi admin 2 untuk membuka periode '.$locked.' di Kelola File, lalu gunakan template periode tersebut.');
    }
}
function guardStoredNativePeriod($db,string $table,string $period,string $bytes): void {
    $allowed=['upb bj kx','upb distr','upb non bj','bod bi opr sdm','bod_jml_sdm_input','service_level_input','BOD MNJ','mnj_input_files','mtm_appendix_uploads'];
    if(!in_array($table,$allowed,true))throw new LogicException('Sumber penguncian tidak dikenal.');
    $first=substr($period,0,4).'-01';
    $q=$db->prepare('SELECT base64 FROM `'.$table.'` WHERE report_period BETWEEN ? AND ? ORDER BY report_period DESC LIMIT 1');$q->bind_param('ss',$first,$period);$q->execute();$saved=$q->get_result()->fetch_assoc();$q->close();
    if($saved){$old=base64_decode($saved['base64'],true);if($old===false)throw new RuntimeException('Data periode tersimpan tidak valid.');assertRawNativePeriod($period,$old,$bytes);}
}
/** Preserve native cell styles while applying protection to earlier monthly inputs. */
function protectRawNativePeriod(string $bytes,string $period): string {
    $doc=rawPeriodDocument($bytes);$selected=(int)substr($period,5,2)-1;
    $styleNs='urn:oasis:names:tc:opendocument:xmlns:style:1.0';$office='urn:oasis:names:tc:opendocument:xmlns:office:1.0';
    $auto=$doc->getElementsByTagNameNS($office,'automatic-styles')->item(0);
    if(!$auto){$auto=$doc->createElementNS($office,'office:automatic-styles');$doc->documentElement->insertBefore($auto,$doc->documentElement->firstChild);}
    $styles=[];
    $apply=static function(DOMElement $cell,bool $locked)use($doc,$auto,$styleNs,&$styles){
        $base=$cell->getAttributeNS(UPB_T,'style-name');$key=$base.'|'.($locked?'1':'0');
        if(!isset($styles[$key])){
            $name='RawPeriod'.substr(sha1($key),0,16);$node=$doc->createElementNS($styleNs,'style:style');$node->setAttributeNS($styleNs,'style:name',$name);$node->setAttributeNS($styleNs,'style:family','table-cell');if($base!=='')$node->setAttributeNS($styleNs,'style:parent-style-name',$base);
            $properties=$doc->createElementNS($styleNs,'style:table-cell-properties');$properties->setAttributeNS($styleNs,'style:cell-protect',$locked?'protected':'none');$node->appendChild($properties);$auto->appendChild($node);$styles[$key]=$name;
        }
        $cell->setAttributeNS(UPB_T,'table:style-name',$styles[$key]);
    };
    foreach(upbBjTables($doc) as $name=>$table){
        if(in_array(strtoupper($name),['REKAP','KESIMPULAN'],true)){protectRawRekapOds($table);continue;}
        $sheetMonth=rawPeriodMonth($name);
        if($sheetMonth!==null){
            if($sheetMonth<$selected){protectRawRekapOds($table);
                $xpath=new DOMXPath($doc);$xpath->registerNamespace('raw',UPB_T);
                foreach($xpath->query('.//raw:table-cell',$table) as $cell)$apply($cell,true);}
            else{$table->removeAttributeNS(UPB_T,'protected');$table->removeAttributeNS(UPB_T,'protection-key');}
            continue;
        }
        $active=[];$hasMonthly=false;$sparse=upbBjCells($table);$rowNumber=1;
        // Leave nonmonthly sheets and the huge repeated empty ODS tail untouched.
        $hasHeaders=false;
        foreach($sparse as $row){$found=[];foreach($row as $cell){$m=rawPeriodMonth($cell->textContent);if($m!==null)$found[$m]=true;}if(count($found)>=2){$hasHeaders=true;break;}}
        if(!$hasHeaders)continue;
        foreach(iterator_to_array($table->childNodes) as $node){
            if(!$node instanceof DOMElement||$node->localName!=='table-row')continue;
            if(!isset($sparse[$rowNumber])){$rowNumber+=max(1,(int)$node->getAttributeNS(UPB_T,'number-rows-repeated'));continue;}
            $headers=[];foreach($sparse[$rowNumber]??[] as $c=>$cell){$month=rawPeriodMonth($cell->textContent);if($month!==null)for($span=0;$span<max(1,(int)$cell->getAttributeNS(UPB_T,'number-columns-spanned'));$span++)$headers[$c+$span]=$month;}
            if(count(array_unique($headers))>=2){$active=$headers;$hasMonthly=true;}
            $column=1;
            foreach(iterator_to_array($node->childNodes) as $cell){
                if(!$cell instanceof DOMElement||!in_array($cell->localName,['table-cell','covered-table-cell'],true))continue;
                $count=max(1,(int)$cell->getAttributeNS(UPB_T,'number-columns-repeated'));
                if($cell->localName==='table-cell'){
                    $points=[$column,$column+$count];foreach($active as $c=>$m)if($c>=$column&&$c<$column+$count){$points[]=$c;$points[]=$c+1;}
                    $points=array_values(array_unique($points));sort($points);
                    for($i=0;$i<count($points)-1;$i++){
                        $copy=$cell->cloneNode(true);$repeat=$points[$i+1]-$points[$i];if($repeat>1)$copy->setAttributeNS(UPB_T,'table:number-columns-repeated',(string)$repeat);else $copy->removeAttributeNS(UPB_T,'number-columns-repeated');
                        if(isset($active[$points[$i]])&&!$copy->hasAttributeNS(UPB_O,'value-type')){$copy->setAttributeNS(UPB_O,'office:value-type','string');if(!$copy->firstChild)$copy->appendChild($doc->createElementNS(UPB_X,'text:p'));}
                        $apply($copy,isset($active[$points[$i]])&&$active[$points[$i]]<$selected);$node->insertBefore($copy,$cell);
                    }
                    $node->removeChild($cell);
                }
                $column+=$count;
            }
            if($active)for($c=$column;$c<=max(array_keys($active));$c++){
                $blank=$doc->createElementNS(UPB_T,'table:table-cell');$blank->setAttributeNS(UPB_O,'office:value-type','string');$blank->appendChild($doc->createElementNS(UPB_X,'text:p'));$apply($blank,isset($active[$c])&&$active[$c]<$selected);$node->appendChild($blank);
            }
            $rowNumber+=max(1,(int)$node->getAttributeNS(UPB_T,'number-rows-repeated'));
        }
        if($hasMonthly&&$selected>0)protectRawRekapOds($table);
        elseif($hasMonthly){$table->removeAttributeNS(UPB_T,'protected');$table->removeAttributeNS(UPB_T,'protection-key');}
    }
    addRawNativeInstructions($doc,$auto);
    $path=tempnam(sys_get_temp_dir(),'raw-lock-');$zip=new ZipArchive();try{file_put_contents($path,$bytes);if($zip->open($path)!==true)throw new RuntimeException('Template tidak dapat dikunci.');try{$zip->addFromString('content.xml',$doc->saveXML());}finally{$zip->close();}return file_get_contents($path);}finally{unlink($path);}
}
