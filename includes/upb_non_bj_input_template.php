<?php
require_once __DIR__.'/upb_bj_template_history.php';

/** Keep the supplied native layout; discover sections by labels, not fixed row numbers. */
function buildUpbNonBjInputTemplateOds(string $period, ?string $templateBytes=null, bool $preserveInputs=false): string
{
    if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D', $period, $parts)) {
        throw new InvalidArgumentException('Periode UPB NON BJ tidak valid.');
    }
    $year=$parts[1];$month=(int)$parts[2];
    $short=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    $temp=tempnam(sys_get_temp_dir(),'upb-non-bj-');$zip=new ZipArchive();$opened=false;
    try {
        if($templateBytes===null)copy(__DIR__.'/../dashboard/assets/input-templates/upb-non-bj.ods',$temp);
        else file_put_contents($temp,$templateBytes);
        if($zip->open($temp)!==true)throw new RuntimeException('Template UPB NON BJ tidak dapat dibaca.');
        $opened=true;
        $doc=upbBjXml($zip->getFromName('content.xml'));
        foreach(upbBjTables($doc) as $table){
            $rows=[];$r=1;
            foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){
                $rows[$r]=$row;$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));
            }
            $cells=upbBjCells($table);$sections=[];$summary=[];$heading=null;
            foreach($cells as $r=>$row){
                $label=strtoupper(trim(preg_replace('/\s+/',' ',$row[1]->textContent??'')));
                if($label==='BAGIAN')$heading=$r;
                elseif($heading!==null&&$label==='TOTAL'){$sections[]=[$heading,$r];$heading=null;}
                if(preg_match('/^UPB (BB|BK|WIP)\s*[–—-]/u',$label,$m))$summary[$m[1]]=$r;
            }
            if(count($sections)!==3||count($summary)!==3)throw new InvalidArgumentException('Tabel Bahan Baku, Bahan Kemas, WIP, atau ringkasan tidak lengkap.');
            // Reset numeric inputs, keeping department names, formatting and formula cells.
            if(!$preserveInputs)foreach($cells as $row)foreach($row as $cell){
                if($cell->hasAttributeNS(UPB_T,'formula'))continue;
                if(in_array($cell->getAttributeNS(UPB_O,'value-type'),['float','currency','percentage'],true)){
                    foreach(['value-type','value','currency'] as $attr)$cell->removeAttributeNS(UPB_O,$attr);
                    while($cell->firstChild)$cell->removeChild($cell->firstChild);
                }
            }
            $xp=new DOMXPath($doc);
            foreach($xp->query('.//text()',$table) as $text){
                $text->nodeValue=preg_replace('/\b20\d{2}\b/',$year,$text->nodeValue);
                $text->nodeValue=preg_replace('/\bYTD\s+(?:JAN|FEB|MAR|APR|MAY|MEI|JUN|JUL|AUG|AGT|SEP|OCT|OKT|NOV|DEC|DES)\b/i','YTD '.$short[$month-1],$text->nodeValue);
            }
            // Historical explanation text is not input data for a fresh period.
            $notes=false;
            foreach($cells as $r=>$row){
                $label=strtoupper(trim($row[1]->textContent??''));
                if(str_starts_with($label,'UPB BAHAN BAKU'))$notes=false;
                if($notes&&!$preserveInputs)foreach($row as $cell)while($cell->firstChild)$cell->removeChild($cell->firstChild);
                if($label==='PENJELASAN:')$notes=true;
            }
            $put=static function($r,$c,$formula,$ytd=false)use($rows,$preserveInputs,$cells,$month){
                $cell=upbBjColumnCell($rows[$r],$c);$old=$cell->getAttributeNS(UPB_T,'formula');
                // Keep uploaded formulas verbatim. Only a standard same-row YTD SUM
                // advances to the selected month; custom YTD expressions stay intact.
                $standardYtd=$ytd&&preg_match('/^of:=SUM\(\[\.\$?D\$?'.$r.':\.\$?[D-O]\$?'.$r.'\]\)$/i',$old);
                if($preserveInputs&&$old!==''&&!$standardYtd)return;
                $value=0;
                if($preserveInputs&&$standardYtd)for($col=4;$col<4+$month;$col++)$value+=(float)(($cells[$r][$col]??null)?->getAttributeNS(UPB_O,'value')??0);
                upbBjSetFormula($cell,$formula,$value);
            };
            $end=chr(ord('D')+$month-1);
            foreach($sections as $i=>[$header,$total]){
                $first=$header+1;$last=$total-1;
                foreach(range($first,$last) as $r){
                    $put($r,2,'SUM([.D'.$r.':.'.$end.$r.'])',true);
                    $put($r,16,'SUM([.D'.$r.':.O'.$r.'])');
                }
                foreach([2,...range(4,15)] as $c){$col=chr(64+$c);$put($total,$c,'SUM([.'.$col.$first.':.'.$col.$last.'])');}
                $put($total,16,'SUM([.D'.$total.':.O'.$total.'])');
                $s=$summary[['BB','BK','WIP'][$i]];
                foreach(range(4,15) as $c){$col=chr(64+$c);$put($s,$c,'[.'.$col.$total.']/1000');}
                $put($s,2,'SUM([.D'.$s.':.'.$end.$s.'])',true);
                $put($s,16,'SUM([.D'.$s.':.O'.$s.'])');
            }
            foreach($cells as $r=>$row)if(preg_match('/^TOTAL UPB NON BJ\b/i',trim($row[1]->textContent??''))){
                foreach([2,...range(4,16)] as $c){$col=chr(64+$c);$put($r,$c,implode('+',array_map(static fn($s)=>'[.'.$col.$s.']',array_values($summary))));}
            }
        }
        $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$opened=false;
        return file_get_contents($temp);
    } finally {if($opened)$zip->close();@unlink($temp);}
}
