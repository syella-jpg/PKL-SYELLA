<?php
function parseUnitSellOutIbTargets(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,int $year):array {
    $norm=fn($v)=>strtoupper(trim(preg_replace('/\s+/u',' ',(string)$v)));
    $months=['JAN'=>1,'JANUARI'=>1,'FEB'=>2,'FEBRUARI'=>2,'MAR'=>3,'MARET'=>3,'APR'=>4,'APRIL'=>4,'MEI'=>5,'MAY'=>5,'JUN'=>6,'JUNI'=>6,'JUL'=>7,'JULI'=>7,'AGS'=>8,'AGU'=>8,'AGT'=>8,'AUG'=>8,'AGUSTUS'=>8,'SEP'=>9,'SEPT'=>9,'SEPTEMBER'=>9,'OKT'=>10,'OCT'=>10,'OKTOBER'=>10,'NOV'=>11,'NOVEMBER'=>11,'DES'=>12,'DEC'=>12,'DESEMBER'=>12];
    $rows=$sheet->rangeToArray('A1:Z'.min(1000,$sheet->getHighestDataRow()),null,false,false);
    $financial=false;$candidates=[];$section=-1;$objective='';
    $subKpis=['SALES REVENUE (UNIT)'=>'A','SALES REVENUE NEW BUSINESS PRODUCT (UNIT)'=>'B'];
    foreach($rows as $r=>$row){
        if($norm($row[0]??'')==='FINANCIAL'){$financial=true;$section=$r;$objective='';}
        elseif(in_array($norm($row[0]??''),['CUSTOMER','INTERNAL BUSINESS PROCESS','INTERNAL PROCESS','LEARNING AND GROWTH','LEARNING & GROWTH'],true))$financial=false;
        $first=$norm($row[0]??'');if($first!==''&&$first!=='FINANCIAL')$objective=$first;
        $key=$subKpis[$norm($row[2]??'')]??null;
        if($financial&&$objective==='REVENUE GROWTH'&&$norm($row[1]??'')==='VALUE SELLING IN'&&$key!==null){
            if(isset($candidates[$key]))throw new InvalidArgumentException('Baris TARGET HRU sub KPI '.$key.' berulang.');
            $candidates[$key]=[$r,$section];
        }
    }
    if(count($candidates)!==2)throw new InvalidArgumentException('HRU: kedua TARGET SALES REVENUE (UNIT) dan SALES REVENUE NEW BUSINESS PRODUCT (UNIT) harus tersedia.');
    if($candidates['A'][1]!==$candidates['B'][1])throw new InvalidArgumentException('Kedua sub KPI UNIT HRU harus berada pada bagian FINANCIAL yang sama.');
    [$row,$section]=$candidates['A'];$columns=[];$header=null;
    for($r=$row-1;$r>$section;$r--){
        $candidate=[];
        foreach($rows[$r] as $c=>$v){
            $label=preg_replace('/^S\s*\/\s*D\s+/','',$norm($v));
            if(isset($months[$label])){
                $m=$months[$label];if(isset($candidate[$m]))throw new InvalidArgumentException('Kolom bulan pada HRU berulang.');
                $candidate[$m]=['column'=>$c,'cumulative'=>$m===1||preg_match('/^S\s*\/\s*D\s+/',$norm($v))===1];
            }
        }
        if(count($candidate)===12){$columns=$candidate;$header=$r;break;}
    }
    if($header===null)throw new InvalidArgumentException('Kolom Januari–Desember HRU tidak lengkap.');
    $years=[];
    for($r=$section;$r<$header;$r++)foreach($rows[$r] as $v){
        if(is_numeric($v)&&(int)$v>=2000&&(int)$v<=2099)$years[(int)$v]=true;
        elseif(is_string($v)&&preg_match_all('/\b20\d{2}\b/',$v,$found))foreach($found[0] as $y)$years[(int)$y]=true;
    }
    if(!isset($years[$year])||count($years)!==1)throw new InvalidArgumentException('Tahun HRU tidak sesuai periode upload '.$year.'.');
    foreach($candidates as $key=>[$targetRow]){
        $labelColumns=[];foreach($rows[$targetRow] as $c=>$v)if($norm($v)==='TARGET')$labelColumns[]=$c;
        if(count($labelColumns)!==1)throw new InvalidArgumentException('Baris TARGET sub KPI '.$key.' HRU tidak ditemukan.');
    }
    $cumulative=[];
    for($m=1;$m<=12;$m++){
        $spec=$columns[$m];if(!$spec['cumulative'])throw new InvalidArgumentException('Header HRU harus kumulatif S/D bulan agar nilai unit per bulan tidak terhitung ganda.');
        $parts=[];
        foreach($candidates as [$targetRow]){
            $address=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($spec['column']+1).($targetRow+1);$cell=$sheet->getCell($address);
            try{$v=$cell->isFormula()?$cell->getCalculatedValue():$cell->getValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus TARGET HRU tidak dapat dihitung pada '.$address.'.');}
            if($v===null||$v===''||in_array(trim((string)$v),['-','–','—'],true))$parts[]=null;
            elseif(!is_numeric($v))throw new InvalidArgumentException('Nilai TARGET UNIT HRU bukan angka pada '.$address.'.');
            else $parts[]=(float)$v;
        }
        $cumulative[$m]=in_array(null,$parts,true)?null:array_sum($parts);
    }
    $result=[];
    for($m=1;$m<=12;$m++){
        $prior=$m===1?0:$cumulative[$m-1];$value=$cumulative[$m]!==null&&$prior!==null?$cumulative[$m]-$prior:null;
        $result[]=['report_year'=>$year,'product'=>'ib','kind'=>'total','month'=>$m,'units'=>$value===null?null:number_format($value,6,'.','')];
    }
    return $result;
}
