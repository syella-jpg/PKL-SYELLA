<?php
function parseUnitSellOutEthicalTargets(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,int $year):array {
    $norm=fn($v)=>strtoupper(trim(preg_replace('/\s+/u',' ',(string)$v)));
    $rows=$sheet->rangeToArray('A1:AA'.min(1000,$sheet->getHighestDataRow()),null,false,false);
    $start=null;$end=count($rows);
    foreach($rows as $r=>$row){
        $label=$norm($row[0]??'');
        if(preg_match('/^1[.\s]+PERSPEKTIF\s+FINANCIAL$/',$label)){$start=$r;continue;}
        if($start!==null&&$r>$start&&preg_match('/^\d+[.\s]+PERSPEKTIF\b/',$label)){$end=$r;break;}
    }
    if($start===null)throw new InvalidArgumentException('GMEB: bagian 1. PERSPEKTIF FINANCIAL tidak ditemukan.');
    $months=['JAN'=>1,'JANUARI'=>1,'FEB'=>2,'FEBRUARI'=>2,'MAR'=>3,'MARET'=>3,'APR'=>4,'APRIL'=>4,'MEI'=>5,'MAY'=>5,'JUN'=>6,'JUNI'=>6,'JUL'=>7,'JULI'=>7,'AGS'=>8,'AGU'=>8,'AGT'=>8,'AUG'=>8,'AGUSTUS'=>8,'SEP'=>9,'SEPT'=>9,'SEPTEMBER'=>9,'OKT'=>10,'OCT'=>10,'OKTOBER'=>10,'NOV'=>11,'NOVEMBER'=>11,'DES'=>12,'DEC'=>12,'DESEMBER'=>12];
    $objective='';$unit=false;$targets=[];$unitStart=null;
    for($r=$start+1;$r<$end;$r++){
        if($norm($rows[$r][1]??'')!=='')$objective=$norm($rows[$r][1]);
        $kpi=$norm($rows[$r][2]??'');if($kpi!==''){$unit=$objective==='REVENUE GROWTH'&&$kpi==='REVENUE SELLING OUT (UNIT)';if($unit)$unitStart=$r;}
        if(!$unit)continue;
        $sub=$norm($rows[$r][3]??'');$kind=$sub==='EXISTING PRODUCT (UNIT)'?'existing':($sub==='NEW PRODUCT (UNIT)'?'new':null);if(!$kind)continue;
        if(isset($targets[$kind]))throw new InvalidArgumentException('GMEB: baris '.$sub.' berulang pada Revenue Selling Out (Unit).');
        if(!in_array('TARGET',array_map($norm,array_slice($rows[$r],0,15)),true))throw new InvalidArgumentException('GMEB: baris TARGET '.$sub.' tidak ditemukan.');
        $targets[$kind]=$r;
    }
    if(count($targets)!==2)throw new InvalidArgumentException('GMEB: TARGET Existing Product (Unit) dan New Product (Unit) belum lengkap.');
    $columns=[];$header=null;
    for($r=$unitStart-1;$r>$start;$r--){
        foreach($rows[$r] as $c=>$v){
            if(($months[$norm($v)]??0)!==1)continue;$candidate=[];
            for($m=1;$m<=12;$m++)if(($months[$norm($rows[$r][$c+$m-1]??'')]??0)===$m)$candidate[$m]=$c+$m-1;
            if(count($candidate)===12){$columns=$candidate;$header=$r;break 2;}
        }
    }
    if($header===null)throw new InvalidArgumentException('GMEB: kolom bulanan Januari–Desember tidak lengkap.');
    $years=[];
    foreach(array_slice($rows,0,$header) as $row)foreach($row as $v){
        $label=$norm($v);if(preg_match('/^(?:TAHUN|TARGET)\b/',$label)&&preg_match_all('/\b20\d{2}\b/',$label,$found))foreach($found[0] as $y)$years[(int)$y]=true;
    }
    if(!isset($years[$year])||count($years)!==1)throw new InvalidArgumentException('Tahun GMEB tidak sesuai periode upload '.$year.'.');
    $result=[];
    foreach($targets as $kind=>$r)foreach($columns as $month=>$c){
        $address=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c+1).($r+1);$cell=$sheet->getCell($address);
        try{
            $value=$cell->getValue();
            if($cell->isFormula()){
                $cached=$cell->getOldCalculatedValue();
                // GMEB targets reference PM/other sheets. Import the workbook's saved result
                // without recalculating against the intentionally filtered-out worksheets.
                $external=strpos($value,'!')!==false&&strpos($value,'#REF!')===false;
                $value=$external&&is_numeric($cached)?$cached:$cell->getCalculatedValue();
            }
        }catch(Throwable $e){throw new InvalidArgumentException('Rumus TARGET UNIT GMEB tidak dapat dihitung pada '.$address.'.');}
        if($value===null||$value===''||in_array(trim((string)$value),['-','–','—'],true))$value=null;
        elseif(!is_numeric($value))throw new InvalidArgumentException('TARGET UNIT GMEB bukan angka pada '.$address.'.');
        $result[]=['report_year'=>$year,'product'=>'ethical','kind'=>$kind,'month'=>$month,'units'=>$value===null?null:number_format((float)$value,6,'.','')];
    }
    return $result;
}
