<?php
use PhpOffice\PhpSpreadsheet\IOFactory;
require_once __DIR__.'/upb_bj_template_history.php';

/** Apply the revised difference to native ODS, including previously uploaded files. */
function reviseBodJmlSdmDifference(string $bytes): string {
    $path=tempnam(sys_get_temp_dir(),'sdm-difference-');$zip=new ZipArchive();$opened=false;
    try{
        file_put_contents($path,$bytes);
        if($zip->open($path)!==true)throw new InvalidArgumentException('File ODS BOD JML SDM tidak valid.');
        $opened=true;$doc=upbBjXml($zip->getFromName('content.xml'));
        $tables=upbBjTables($doc);$table=$tables['BOD JML SDM']??reset($tables);
        if(!$table)throw new InvalidArgumentException('Tabel BOD JML SDM tidak ditemukan.');
        $rows=upbBjCells($table);$columns=[];
        foreach($rows as $row)foreach($row as $c=>$cell){
            $label=strtoupper(trim(preg_replace('/\s+/u',' ',$cell->textContent)));
            foreach(['PLAN S/D BULAN INI'=>'plan','REAL S/D BULAN INI'=>'real','SELISIH'=>'difference'] as $text=>$key)if($label===$text)$columns[$key]=$c;
        }
        if(count($columns)!==3)throw new InvalidArgumentException('Header PLAN, REAL, atau SELISIH tidak ditemukan.');
        $plan=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns['plan']);
        $real=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columns['real']);
        $changed=false;
        foreach($rows as $r=>$row){
            $cell=$row[$columns['difference']]??null;if(!$cell)continue;
            if(preg_match('/^a\s*[-–−]\s*b$/iu',trim($cell->textContent))){
                foreach($cell->getElementsByTagNameNS(UPB_X,'p') as $p){while($p->firstChild)$p->removeChild($p->firstChild);$p->appendChild($doc->createTextNode('b – a'));}
            }
            $formula=$cell->getAttributeNS(UPB_T,'formula');
            $old='of:=[.'.$plan.$r.']-[.'.$real.$r.']';
            if($formula===$old){
                $value=(float)($row[$columns['real']]??$cell)->getAttributeNS(UPB_O,'value')-(float)($row[$columns['plan']]??$cell)->getAttributeNS(UPB_O,'value');
                upbBjSetFormula($cell,'[.'.$real.$r.']-[.'.$plan.$r.']',$value);$changed=true;
            }
        }
        if($changed)foreach($rows as $row){
            $cell=$row[$columns['difference']]??null;
            if($cell&&str_starts_with(strtoupper($cell->getAttributeNS(UPB_T,'formula')),'OF:=SUM('))upbBjSetFormula($cell,substr($cell->getAttributeNS(UPB_T,'formula'),4),-(float)$cell->getAttributeNS(UPB_O,'value'));
        }
        $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$opened=false;return file_get_contents($path);
    }finally{if($opened)$zip->close();@unlink($path);}
}

function bodJmlSdmInputPeriod(string $period): void {
    if (!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $period)) throw new InvalidArgumentException('Periode BOD JML SDM tidak valid.');
}
function ensureBodJmlSdmInputStore(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS `bod_jml_sdm_input` (report_period CHAR(7) NOT NULL PRIMARY KEY, filename VARCHAR(255) NOT NULL, base64 LONGTEXT NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function findBodJmlSdmInput(mysqli $conn, string $period): ?array {
    bodJmlSdmInputPeriod($period); ensureBodJmlSdmInputStore($conn);
    $q=$conn->prepare('SELECT report_period,filename,base64 FROM `bod_jml_sdm_input` WHERE report_period=?');
    $q->bind_param('s',$period);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();return $row?:null;
}
function validateBodJmlSdmInputBytes(string $bytes): void {
    if(strlen($bytes)>50*1024*1024)throw new InvalidArgumentException('File maksimal 50 MB.');
    $path=tempnam(sys_get_temp_dir(),'bod-jml-sdm-');$zip=new ZipArchive();$opened=false;
    try{
        file_put_contents($path,$bytes);if($zip->open($path)!==true)throw new InvalidArgumentException('Gunakan template BOD JML SDM berformat ODS.');$opened=true;
        if(trim((string)$zip->getFromName('mimetype'))!=='application/vnd.oasis.opendocument.spreadsheet')throw new InvalidArgumentException('Gunakan template BOD JML SDM berformat ODS.');
        $zip->close();$opened=false;$book=IOFactory::load($path);$sheet=$book->getSheetByName('BOD JML SDM')??$book->getSheet(0);$labels=[];
        foreach($sheet->getRowIterator() as $row)foreach($row->getCellIterator() as $cell){$v=$cell->getValue();if(is_string($v)&&!$cell->isFormula())$labels[]=strtoupper(preg_replace('/\s+/u',' ',trim($v)));}
        $required=['KETERANGAN','REAL 2025','PLAN 2026','PLAN S/D BULAN INI','REAL S/D BULAN INI','SELISIH'];
        foreach($required as $label)if(!in_array($label,$labels,true))throw new InvalidArgumentException('Header '.$label.' tidak ditemukan. Gunakan template BOD JML SDM.');
        $book->disconnectWorksheets();
    }catch(InvalidArgumentException $e){throw $e;}catch(Throwable $e){throw new InvalidArgumentException('Isi file ODS BOD JML SDM tidak dapat dibaca.');}
    finally{if($opened)$zip->close();@unlink($path);}
}
function saveBodJmlSdmInput(mysqli $conn,string $period,string $bytes,string $filename): void {
    bodJmlSdmInputPeriod($period);validateBodJmlSdmInputBytes($bytes);$bytes=reviseBodJmlSdmDifference($bytes);ensureBodJmlSdmInputStore($conn);
    require_once __DIR__.'/raw_native_period.php';guardStoredNativePeriod($conn,'bod_jml_sdm_input',$period,$bytes);
    $filename=mb_substr(basename($filename),0,255);$base64=base64_encode($bytes);
    $q=$conn->prepare('INSERT INTO `bod_jml_sdm_input` (report_period,filename,base64) VALUES (?,?,?) ON DUPLICATE KEY UPDATE filename=VALUES(filename),base64=VALUES(base64)');
    $q->bind_param('sss',$period,$filename,$base64);$q->execute();$q->close();
}
/** Match prior-year values by KETERANGAN, retaining the current native layout. */
function bodJmlSdmFillPriorReal(string $bytes,string $priorBytes): string {
    $path=tempnam(sys_get_temp_dir(),'sdm-prior-');$zip=new ZipArchive();$opened=false;
    $read=static function(string $raw)use($path):DOMDocument{
        file_put_contents($path,$raw);$archive=new ZipArchive();
        if($archive->open($path)!==true)throw new RuntimeException('File BOD JML SDM tidak valid.');
        try{return upbBjXml($archive->getFromName('content.xml'));}finally{$archive->close();}
    };
    $norm=static fn($v)=>strtoupper(trim(preg_replace('/\s+/u',' ',(string)$v)));
    $layout=static function(DOMDocument $doc)use($norm):array{
        $tables=upbBjTables($doc);$table=$tables['BOD JML SDM']??reset($tables);$rows=upbBjCells($table);$cols=[];$header=0;$total=PHP_INT_MAX;
        foreach($rows as $r=>$row)foreach($row as $c=>$cell){
            $text=$norm($cell->textContent);
            if($text==='KETERANGAN'){$cols['label']=$c;$header=$r;}
            if($text==='REAL 2025')$cols['prior']=$c;
            if($text==='REAL S/D BULAN INI')$cols['real']=$c;
        }
        if(count($cols)!==3)throw new RuntimeException('Header BOD JML SDM tidak lengkap.');
        foreach($rows as $r=>$row)if($r>$header&&$norm(($row[$cols['label']]??null)?->textContent)==='TOTAL'){$total=$r;break;}
        return compact('table','rows','cols','header','total');
    };
    try{
        $previous=$layout($read($priorBytes));$doc=$read($bytes);$current=$layout($doc);$byLabel=[];$details=[];
        foreach($previous['rows'] as $r=>$row)if($r>$previous['header']+1&&$r<$previous['total']){
            $label=$norm(($row[$previous['cols']['label']]??null)?->textContent);
            $value=($row[$previous['cols']['real']]??null)?->getAttributeNS(UPB_O,'value');
            $entry=['label'=>$label,'name'=>($row[$previous['cols']['label']]??null)?->textContent??'','value'=>$value];
            $details[]=$entry;if($label!=='')$byLabel[$label][]=$entry;
        }
        $r=1;$index=0;$sum=0;
        foreach($current['table']->childNodes as $node){
            if(!$node instanceof DOMElement||$node->localName!=='table-row')continue;
            $repeat=max(1,(int)$node->getAttributeNS(UPB_T,'number-rows-repeated'));
            if($r>$current['header']+1&&$r<$current['total']&&$repeat===1){
                $label=$norm(($current['rows'][$r][$current['cols']['label']]??null)?->textContent);
                $entry=$label!==''?(!empty($byLabel[$label])?array_shift($byLabel[$label]):null):($details[$index]??null);$index++;
                $cell=upbBjColumnCell($node,$current['cols']['prior']);
                $cell->removeAttributeNS(UPB_T,'formula');foreach(['value','value-type','string-value'] as $attr)$cell->removeAttributeNS(UPB_O,$attr);while($cell->firstChild)$cell->removeChild($cell->firstChild);
                if($entry&&$entry['value']!==null&&$entry['value']!==''&&is_numeric($entry['value'])){
                    $value=(float)$entry['value'];$sum+=$value;$cell->setAttributeNS(UPB_O,'office:value-type','float');$cell->setAttributeNS(UPB_O,'office:value',(string)$value);$cell->appendChild($doc->createElementNS(UPB_X,'text:p',(string)$value));
                }
                if($label===''&&$entry&&$entry['name']!==''){
                    $name=upbBjColumnCell($node,$current['cols']['label']);while($name->firstChild)$name->removeChild($name->firstChild);$name->setAttributeNS(UPB_O,'office:value-type','string');$p=$doc->createElementNS(UPB_X,'text:p');$p->appendChild($doc->createTextNode($entry['name']));$name->appendChild($p);
                }
            }
            if($r===$current['total']){
                $cell=upbBjColumnCell($node,$current['cols']['prior']);$f=$cell->getAttributeNS(UPB_T,'formula');if(str_starts_with(strtoupper($f),'OF:=SUM('))upbBjSetFormula($cell,substr($f,4),$sum);
            }
            $r+=$repeat;
        }
        file_put_contents($path,$bytes);if($zip->open($path)!==true)throw new RuntimeException('File BOD JML SDM tidak valid.');$opened=true;
        $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$opened=false;return file_get_contents($path);
    }finally{if($opened)$zip->close();@unlink($path);}
}
function loadBodJmlSdmInputOds(mysqli $conn,string $period): string {
    bodJmlSdmInputPeriod($period);$record=findBodJmlSdmInput($conn,$period);
    if($record){
        $bytes=base64_decode($record['base64'],true);if($bytes===false)throw new RuntimeException('File BOD JML SDM tersimpan tidak valid.');
    }else{
        $path=__DIR__.'/../dashboard/assets/input-templates/bod-jml-sdm.ods';$bytes=file_get_contents($path);
        if($bytes===false)throw new RuntimeException('Template BOD JML SDM tidak ditemukan.');
    }
    $bytes=reviseBodJmlSdmDifference($bytes);
    // REAL 2025 is the full prior year, fixed to December for every 2026 period.
    if(substr($period,0,4)==='2026'){
        $prior=findBodJmlSdmInput($conn,'2025-12');
        if($prior){
            $priorBytes=base64_decode($prior['base64'],true);
            if($priorBytes===false)throw new RuntimeException('File BOD JML SDM Desember 2025 tersimpan tidak valid.');
            $bytes=bodJmlSdmFillPriorReal($bytes,$priorBytes);
        }
    }
    return $bytes;
}
