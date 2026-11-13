<?php
require_once __DIR__.'/upb_distr_input.php';
/** Native distributor report layout supplied by the user; no financial data is invented. */
function buildUpbDistrReportOds(string $period,?string $current=null,?string $prior=null): string
{
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$parts))throw new InvalidArgumentException('Periode UPB DISTR tidak valid.');
    $year=(int)$parts[1];$months=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];$month=$months[(int)$parts[2]-1];
    $temp=tempnam(sys_get_temp_dir(),'upb-distr-report-');$zip=new ZipArchive();$opened=false;
    try{
        if(!copy(__DIR__.'/../dashboard/assets/input-templates/upb-distr-report.ods',$temp)||$zip->open($temp)!==true)throw new RuntimeException('Template UPB DISTR tidak dapat dibaca.');$opened=true;
        $doc=upbBjXml($zip->getFromName('content.xml'));$table=array_values(upbBjTables($doc))[0];$oldName=$table->getAttributeNS(UPB_T,'name');$table->setAttributeNS(UPB_T,'table:name','UPB DISTR');
        $rows=iterator_to_array($table->getElementsByTagNameNS(UPB_T,'table-row'));
        $monthCell=upbBjColumnCell($rows[0],1);while($monthCell->firstChild)$monthCell->removeChild($monthCell->firstChild);$monthCell->appendChild($doc->createElementNS(UPB_X,'text:p',$month));$monthCell->setAttributeNS(UPB_O,'office:string-value',$month);
        foreach($table->getElementsByTagNameNS(UPB_T,'table-cell') as $cell){
            $formula=$cell->getAttributeNS(UPB_T,'formula');
            if(str_contains($formula,'UPB DISTRIBUTOR TH.')){
                $priorTitle=str_contains($formula,'2025');$y=$year-($priorTitle?1:0);
                $formula=preg_replace('/UPB DISTRIBUTOR TH\. 20\d{2}/','UPB DISTRIBUTOR TH. '.$y,$formula);
                $cell->setAttributeNS(UPB_O,'office:value-type','string');$cell->setAttributeNS(UPB_O,'office:string-value','UPB DISTRIBUTOR TH. '.$y.' - YTD '.$month);while($cell->firstChild)$cell->removeChild($cell->firstChild);
                $cell->appendChild($doc->createElementNS(UPB_X,'text:p','UPB DISTRIBUTOR TH. '.$y.' - YTD '.$month));
            }
            if($formula!=='')$cell->setAttributeNS(UPB_T,'table:formula',str_replace(["['".$oldName."'.",'['.$oldName.'.'],["['UPB DISTR'.","['UPB DISTR'."],$formula));
        }
        // Keep the report self-contained: detail snapshots stay in the raw template.
        // Report sums reference the visible monthly cells; no missing external sheets.
        $rowNodes=[];$r=1;foreach($table->childNodes as $row)if($row instanceof DOMElement&&$row->localName==='table-row'){$rowNodes[$r]=$row;$r+=max(1,(int)$row->getAttributeNS(UPB_T,'number-rows-repeated'));}
        foreach([false,true] as $previous){
            $values=distrMonthlyValues($previous?$prior:$current,$year-($previous?1:0));
            $mapping=$previous?[17=>['_GB_1_6'],18=>['ETHICAL'],19=>['NON KONIMEX'],20=>['_TOTAL']]:[4=>['_GB_1_6'],5=>['GB 1'],6=>['GB 2'],7=>['GB 3'],8=>['GB 4'],9=>['GB 5'],10=>['GB 6'],11=>['ETHICAL'],12=>['NON KONIMEX'],13=>['_TOTAL']];
            $cache=[];
            foreach($mapping as $row=>$groups){$sumYtd=0;$sumFull=0;
                foreach(range(1,12) as $m){$has=false;$value=0;foreach($groups as $group)if(isset($values[$group][$m])){$has=true;$value+=$values[$group][$m];}
                    // A current-year snapshot cannot display actuals after the selected month.
                    if(!$previous&&$m>(int)$parts[2])$has=false;
                    $cell=upbBjColumnCell($rowNodes[$row],$m+4);distrText($cell,'');
                    if($has){distrNumber($cell,(string)$value,$value);$cell->removeAttributeNS(UPB_T,'formula');$sumFull+=$value;if($m<=(int)$parts[2])$sumYtd+=$value;}
                }
                $aggregateParts=$previous?($row===20?[17,18,19]:[]):($row===4?[5,6,7,8,9,10]:($row===13?[4,11,12]:[]));
                if($aggregateParts)foreach(range(1,12) as $m){$letter=chr(68+$m);$sum=0;foreach($groups as $group)$sum+=$values[$group][$m]??0;if(!$previous&&$m>(int)$parts[2])$sum=0;
                    $childGroups=$previous?['_GB_1_6','ETHICAL','NON KONIMEX']:($row===4?array_slice(DISTR_GROUPS,0,6):['_GB_1_6','ETHICAL','NON KONIMEX']);$childSum=0;foreach($childGroups as $child)$childSum+=$values[$child][$m]??0;if(!$previous&&$m>(int)$parts[2])$childSum=0;
                    // Custom summary totals can differ from an ordinary sum of children.
                    // Keep their stored result instead of replacing the user's calculation.
                    if(abs($childSum-$sum)<0.000000001)distrNumber(upbBjColumnCell($rowNodes[$row],$m+4),implode('+',array_map(static fn($part)=>'[.'.$letter.$part.']',$aggregateParts)),$sum);
                }
                distrNumber(upbBjColumnCell($rowNodes[$row],2),'SUM([.E'.$row.':.'.chr(68+(int)$parts[2]).$row.'])',$sumYtd);
                distrNumber(upbBjColumnCell($rowNodes[$row],17),'SUM([.E'.$row.':.P'.$row.'])',$sumFull);$cache[$row]=$sumYtd;
            }
            $total=$cache[$previous?20:13]??0;
            foreach($mapping as $row=>$groups)distrNumber(upbBjColumnCell($rowNodes[$row],3),'IF([.$B$'.($previous?20:13).']=0;0;[.B'.$row.']/[.$B$'.($previous?20:13).'])',$total!=0?$cache[$row]/$total:0);
        }
        $zip->addFromString('content.xml',$doc->saveXML());$zip->close();$opened=false;return file_get_contents($temp);
    }finally{if($opened)$zip->close();@unlink($temp);}
}
