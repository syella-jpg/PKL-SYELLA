<?php
require_once __DIR__.'/upb_bj_template_history.php';
require_once __DIR__.'/ods_upload_xml.php';

function serviceLevelPeriod(string $period): int {
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$m))throw new InvalidArgumentException('Periode SERVICE LEVEL tidak valid.');
    return (int)$m[1];
}
function ensureServiceLevelInputStore(mysqli $conn): void {
    $conn->query('CREATE TABLE IF NOT EXISTS service_level_input (report_period CHAR(7) NOT NULL PRIMARY KEY,filename VARCHAR(255) NOT NULL,base64 LONGTEXT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function serviceLevelHistorySelectionSql(): string {
    return 'SELECT report_period,filename,base64 FROM service_level_input WHERE report_period BETWEEN ? AND ? ORDER BY (report_period <= ?) DESC, CASE WHEN report_period <= ? THEN report_period END DESC, report_period ASC LIMIT 1';
}
/** Hide future monthly values in the export copy; the uploaded snapshot stays intact. */
function serviceLevelLimitMonths(DOMElement $table,int $lastMonth): void {
    $aliases=[['JAN','JANUARI'],['FEB','FEBRUARI'],['MAR','MARET'],['APR','APRIL'],['MAY','MEI'],['JUN','JUNI'],['JUL','JULI'],['AUG','AGS','AGU','AGUSTUS'],['SEP','SEPTEMBER'],['OCT','OKT','OKTOBER'],['NOV','NOP','NOVEMBER'],['DEC','DES','DESEMBER']];
    $cells=upbBjCells($table);$active=[];$rowNumber=1;
    foreach($table->childNodes as $row){
        if(!$row instanceof DOMElement||$row->localName!=='table-row')continue;
        $current=$cells[$rowNumber]??[];$header=[];
        foreach($current as $c=>$cell){
            $label=strtoupper(trim($cell->textContent));
            foreach($aliases as $i=>$names)if(in_array($label,$names,true))$header[$c]=$i+1;
        }
        if(count($header)>=2)$active=$header;
        else foreach($active as $c=>$month){
            if($month<=$lastMonth||!isset($current[$c]))continue;
            $cell=$current[$c];
            $cell->removeAttributeNS(UPB_T,'formula');
            foreach(['value','value-type','string-value','date-value','boolean-value'] as $attribute)$cell->removeAttributeNS(UPB_O,$attribute);
            $cell->removeAttributeNS('urn:org:documentfoundation:names:experimental:calc:xmlns:calcext:1.0','value-type');
            while($cell->firstChild)$cell->removeChild($cell->firstChild);
        }
        $rowNumber+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));
    }
}
function findServiceLevelInput(mysqli $conn,string $period): ?array {
    $year=serviceLevelPeriod($period);ensureServiceLevelInputStore($conn);$first=$year.'-01';$last=$year.'-12';
    // Prefer the latest snapshot at/before the report, then the earliest later upload in that year.
    $q=$conn->prepare(serviceLevelHistorySelectionSql());
    $q->bind_param('ssss',$first,$last,$period,$period);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();return $row?:null;
}
/** Change only sheet name and year headings; retain native styles and blank cells. */
function buildServiceLevelInputOds(string $period,?string $saved=null,bool $rawTemplate=false): string {
    $year=serviceLevelPeriod($period);$path=tempnam(sys_get_temp_dir(),'service-level-');$zip=new ZipArchive();$opened=false;
    try{
        if($saved===null)copy(__DIR__.'/../dashboard/assets/input-templates/service-level.ods',$path);else file_put_contents($path,$saved);
        $doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);
        if(count($tables)!==1)throw new InvalidArgumentException('Gunakan satu sheet SERVICE LEVEL.');
        $table=reset($tables);$table->setAttributeNS(UPB_T,'table:name','SERVICE LEVEL');
        if($saved===null)foreach($table->getElementsByTagNameNS(UPB_X,'p') as $p){
            $text=trim($p->textContent);$new=null;
            if(preg_match('/^TH\.\s*2026$/i',$text))$new='TH. '.$year;
            elseif(preg_match('/^TH\.\s*2025$/i',$text))$new='TH. '.($year-1);
            if($new!==null){while($p->firstChild)$p->removeChild($p->firstChild);$p->appendChild($doc->createTextNode($new));}
        }
        if($rawTemplate){
            // Locate the previous-year block by its heading, so inserted rows remain supported.
            $remove=false;
            foreach(iterator_to_array($table->childNodes) as $node){
                if($node instanceof DOMElement && $node->localName==='table-row'){
                    foreach($node->getElementsByTagNameNS(UPB_X,'p') as $p){
                        if(preg_match('/^TH\.\s*(20\d{2})$/i',trim($p->textContent),$m) && (int)$m[1]!==$year)$remove=true;
                    }
                }
                if($remove)$table->removeChild($node);
            }
        }
        if($rawTemplate)serviceLevelLimitMonths($table,(int)substr($period,5,2));
        if($zip->open($path)!==true)throw new RuntimeException('Template SERVICE LEVEL tidak dapat dibaca.');$opened=true;
        $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$opened=false;return file_get_contents($path);
    }finally{if($opened)$zip->close();@unlink($path);}
}
function validateServiceLevelInput(string $bytes,string $period): void {
    $year=serviceLevelPeriod($period);
    if(strlen($bytes)>50*1024*1024)throw new InvalidArgumentException('File maksimal 50 MB.');
    $path=tempnam(sys_get_temp_dir(),'service-level-check-');$zip=new ZipArchive();$opened=false;
    try{
        file_put_contents($path,$bytes);if($zip->open($path)!==true)throw new InvalidArgumentException('Gunakan file SERVICE LEVEL berformat ODS.');$opened=true;
        if(trim((string)$zip->getFromName('mimetype'))!=='application/vnd.oasis.opendocument.spreadsheet')throw new InvalidArgumentException('Gunakan file SERVICE LEVEL berformat ODS.');
        $zip->close();$opened=false;$doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);
        if(count($tables)!==1)throw new InvalidArgumentException('Gunakan satu sheet SERVICE LEVEL.');
        $labels=[];foreach(reset($tables)->getElementsByTagNameNS(UPB_X,'p') as $p)$labels[]=strtoupper(trim(preg_replace('/\s+/u',' ',$p->textContent)));
        if(!in_array('SERVICE LEVEL',$labels,true)||!preg_grep('/^EKSTERNAL\s*\(MNJ TO OUTLET\)/',$labels)||!preg_grep('/^INTERNAL\s*\(KX TO MNJ\)/',$labels))throw new InvalidArgumentException('Header SERVICE LEVEL eksternal dan internal tidak ditemukan.');
        foreach([$year] as $y)if(!preg_grep('/^TH\.\s*'.$y.'$/',$labels))throw new InvalidArgumentException('Tahun SERVICE LEVEL tidak sesuai periode. Unduh template untuk '.$year.'.');
    }finally{if($opened)$zip->close();@unlink($path);}
}
function saveServiceLevelInput(mysqli $conn,string $period,string $bytes,string $filename): void {
    validateServiceLevelInput($bytes,$period);$bytes=buildServiceLevelInputOds($period,$bytes);ensureServiceLevelInputStore($conn);
    $filename=mb_substr(basename($filename),0,255);$base64=base64_encode($bytes);
    $q=$conn->prepare('INSERT INTO service_level_input (report_period,filename,base64) VALUES (?,?,?) ON DUPLICATE KEY UPDATE filename=VALUES(filename),base64=VALUES(base64)');
    $q->bind_param('sss',$period,$filename,$base64);$q->execute();$q->close();
}
function loadServiceLevelInputOds(mysqli $conn,string $period,bool $rawTemplate=false): string {
    $record=findServiceLevelInput($conn,$period);$saved=$record?base64_decode($record['base64'],true):null;
    if($saved===false)throw new RuntimeException('Data SERVICE LEVEL tersimpan tidak valid.');
    if($rawTemplate)return buildServiceLevelInputOds($period,$saved,true);
    $priorRecord=findServiceLevelInput($conn,(serviceLevelPeriod($period)-1).'-12');
    $prior=$priorRecord?base64_decode($priorRecord['base64'],true):null;
    if($prior===false)throw new RuntimeException('Data SERVICE LEVEL tahun sebelumnya tidak valid.');
    return buildServiceLevelReportOds($period,$saved,$prior);
}

/** Append the prior year's uploaded tables without flattening formatting or blank cells. */
function buildServiceLevelReportOds(string $period,?string $current=null,?string $previous=null): string {
    $year=serviceLevelPeriod($period);
    $bytes=buildServiceLevelInputOds($period,$current,true);
    $prior=buildServiceLevelInputOds(($year-1).'-12',$previous,true);
    $path=tempnam(sys_get_temp_dir(),'service-report-');$priorPath=tempnam(sys_get_temp_dir(),'service-prior-');
    $zip=new ZipArchive();$priorZip=new ZipArchive();
    try{
        file_put_contents($path,$bytes);file_put_contents($priorPath,$prior);
        $zip->open($path);$priorZip->open($priorPath);
        $doc=upbBjXml($zip->getFromName('content.xml'));$other=upbBjXml($priorZip->getFromName('content.xml'));
        $styles=upbBjXml($zip->getFromName('styles.xml'));$otherStyles=upbBjXml($priorZip->getFromName('styles.xml'));
        // Prefix imported style names so user formatting from either upload stays independent.
        $styleNs='urn:oasis:names:tc:opendocument:xmlns:style:1.0';$map=[];
        foreach([$other,$otherStyles] as $part)foreach((new DOMXPath($part))->query('//*') as $element){
            if($element->hasAttributeNS($styleNs,'name')){
                $name=$element->getAttributeNS($styleNs,'name');$map[$name]='SLPRIOR_'.$name;
            }
        }
        foreach([$other,$otherStyles] as $part)foreach((new DOMXPath($part))->query('//*') as $element){
            foreach(iterator_to_array($element->attributes) as $attr){
                if(($attr->localName==='name' && $attr->namespaceURI===$styleNs) || str_contains($attr->localName,'style-name')){
                    if(isset($map[$attr->value]))$attr->value=$map[$attr->value];
                }
            }
        }
        foreach([[$other,$doc,'automatic-styles'],[$otherStyles,$styles,'styles'],[$otherStyles,$styles,'automatic-styles']] as [$from,$to,$tag]){
            $source=$from->getElementsByTagNameNS(UPB_O,$tag)->item(0);$target=$to->getElementsByTagNameNS(UPB_O,$tag)->item(0);
            if($source && $target)foreach(iterator_to_array($source->childNodes) as $child)$target->appendChild($to->importNode($child,true));
        }
        $tables=upbBjTables($doc);$table=reset($tables);$priorTables=upbBjTables($other);$priorTable=reset($priorTables);
        foreach([$table,$priorTable] as $trimTable){
            for($tail=$trimTable->lastChild;$tail;){
                $before=$tail->previousSibling;
                if($tail instanceof DOMElement && $tail->localName==='table-row'){
                    $hasData=trim($tail->textContent)!=='';
                    foreach($tail->getElementsByTagNameNS(UPB_T,'table-cell') as $cell){
                        if($cell->hasAttributeNS(UPB_T,'formula') || $cell->hasAttributeNS(UPB_O,'value'))$hasData=true;
                    }
                    if($hasData)break;
                    $trimTable->removeChild($tail);
                }
                $tail=$before;
            }
        }
        $offset=0;foreach($table->childNodes as $node)if($node instanceof DOMElement && $node->localName==='table-row')$offset+=max(1,(int)$node->getAttributeNS(UPB_T,'number-rows-repeated'));
        $gap=$doc->createElementNS(UPB_T,'table:table-row');$gap->setAttributeNS(UPB_T,'table:number-rows-repeated','2');$table->appendChild($gap);$offset+=2;
        foreach($priorTable->childNodes as $node){
            if(!$node instanceof DOMElement || $node->localName!=='table-row')continue;
            $row=$doc->importNode($node,true);
            foreach($row->getElementsByTagNameNS(UPB_T,'table-cell') as $cell){
                if($cell->hasAttributeNS(UPB_T,'formula')){
                    $formula=$cell->getAttributeNS(UPB_T,'formula');
                    $formula=preg_replace_callback('/(\\.\\$?[A-Z]+\\$?)(\\d+)/',fn($m)=>$m[1].((int)$m[2]+$offset),$formula);
                    $cell->setAttributeNS(UPB_T,'table:formula',$formula);
                }
            }
            $table->appendChild($row);
        }
        $zip->addFromString('content.xml',$doc->saveXML());$zip->addFromString('styles.xml',$styles->saveXML());$zip->close();$priorZip->close();
        return file_get_contents($path);
    }finally{@unlink($path);@unlink($priorPath);}
}
