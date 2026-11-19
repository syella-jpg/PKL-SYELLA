<?php
require_once __DIR__.'/../includes/service_level_input_store.php';
function serviceCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$source=new ZipArchive();$source->open(__DIR__.'/../dashboard/assets/input-templates/service-level.ods');$styles=$source->getFromName('styles.xml');$source->close();
foreach([2025,2026,2027,2028] as $year){
    $period=$year.'-03';$bytes=buildServiceLevelInputOds($period);validateServiceLevelInput($bytes,$period);
    $raw=buildServiceLevelInputOds($period,null,true);validateServiceLevelInput($raw,$period);
    $rawPath=tempnam(sys_get_temp_dir(),'service-raw-test-');
    try{
        file_put_contents($rawPath,$raw);$rawDoc=loadOdsUploadDocument($rawPath);
        $rawTables=upbBjTables($rawDoc);$rawTable=reset($rawTables);$headings=[];$sections=0;
        foreach($rawTable->getElementsByTagNameNS(UPB_X,'p') as $p){
            $label=trim($p->textContent);
            if(preg_match('/^TH\.\s*(\d{4})$/',$label,$m))$headings[]=(int)$m[1];
            if(preg_match('/^(Eksternal|Internal)\s*\(/i',$label))$sections++;
        }
        serviceCheck($headings===[$year] && $sections===2,'Raw template must contain only two current-year tables');
    }finally{@unlink($rawPath);}
    $path=tempnam(sys_get_temp_dir(),'service-test-');
    try{
        file_put_contents($path,$bytes);$zip=new ZipArchive();$zip->open($path);$xml=$zip->getFromName('content.xml');
        serviceCheck($zip->getFromName('styles.xml')===$styles,'Original styles changed');
        $doc=upbBjXml($xml);$table=upbBjTables($doc)['SERVICE LEVEL']??null;serviceCheck($table!==null,'Sheet name');
        $titles=[];foreach($table->getElementsByTagNameNS(UPB_X,'p') as $p)if(preg_match('/^TH\.\s*(\d{4})$/',trim($p->textContent),$m))$titles[]=(int)$m[1];
        serviceCheck($titles===[$year,$year-1],'Incorrect year headings');
        // Simulate a row added in Calc and a user formula: uploaded native XML survives.
        $row=$doc->createElementNS(UPB_T,'table:table-row');$cell=$doc->createElementNS(UPB_T,'table:table-cell');upbBjSetFormula($cell,'SUM([.C4:.C10])',12);$row->appendChild($cell);$table->appendChild($row);
        $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$saved=file_get_contents($path);
        $continued=buildServiceLevelInputOds($year.'-04',$saved);file_put_contents($path,$continued);$zip->open($path);
        serviceCheck(str_contains($zip->getFromName('content.xml'),'SUM([.C4:.C10])'),'User formula lost');serviceCheck($zip->getFromName('styles.xml')===$styles,'Saved styles changed');$zip->close();
        if($year===2027)file_put_contents('/tmp/service-level-2027-test.ods',$bytes);
    }finally{@unlink($path);}
}
$invalid=false;try{validateServiceLevelInput(buildServiceLevelInputOds('2026-01'),'2027-01');}catch(InvalidArgumentException $e){$invalid=true;}
serviceCheck($invalid,'Wrong-year upload accepted');
echo "PASS: years 2025–2028, prior-year headings, native styles, added rows/formulas retained, wrong-year validation.\n";

// Use distinct uploaded values to prove that both years survive report assembly.
function serviceFixture(string $period,int $value): string {
    $bytes=buildServiceLevelInputOds($period,null,true);$path=tempnam(sys_get_temp_dir(),'sl-fixture-');
    try{
        file_put_contents($path,$bytes);$zip=new ZipArchive();$zip->open($path);$doc=upbBjXml($zip->getFromName('content.xml'));
        $tables=upbBjTables($doc);$table=reset($tables);$rows=$table->getElementsByTagNameNS(UPB_T,'table-row');
        $cell=upbBjColumnCell($rows->item(3),3);
        $cell->setAttributeNS(UPB_O,'office:value-type','float');$cell->setAttributeNS(UPB_O,'office:value',(string)$value);
        $zip->addFromString('content.xml',$doc->saveXML());$zip->close();return file_get_contents($path);
    }finally{@unlink($path);}
}
$merged=buildServiceLevelReportOds('2026-10',serviceFixture('2026-10',26),serviceFixture('2025-12',25));
$path=tempnam(sys_get_temp_dir(),'sl-merged-');
try{
    file_put_contents($path,$merged);$doc=loadOdsUploadDocument($path);$tables=upbBjTables($doc);$table=reset($tables);
    $years=[];$values=[];$sections=0;
    foreach($table->getElementsByTagNameNS(UPB_X,'p') as $p){
        if(preg_match('/^TH\.\s*(\d{4})$/',trim($p->textContent),$m))$years[]=(int)$m[1];
        if(preg_match('/^(Eksternal|Internal)\s*\(/i',trim($p->textContent)))$sections++;
    }
    foreach($table->getElementsByTagNameNS(UPB_T,'table-cell') as $cell)$values[]=$cell->getAttributeNS(UPB_O,'value');
    serviceCheck($years===[2026,2025] && $sections===4,'Both years and four report tables required');
    serviceCheck(in_array('26',$values,true) && in_array('25',$values,true),'Uploaded values from both years required');
}finally{@unlink($path);}
echo "PASS: report merges distinct current/prior uploads; raw template remains two tables.\n";
