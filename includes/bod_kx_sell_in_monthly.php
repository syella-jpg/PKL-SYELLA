<?php
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
function parseBodKxSellInMonthly(Worksheet $sheet, string $period): array {
    $normal = fn($v) => strtoupper(preg_replace('/\s+/u',' ',trim((string)$v)));
    $months=['JANUARI'=>1,'FEBRUARI'=>2,'MARET'=>3,'APRIL'=>4,'MEI'=>5,'JUNI'=>6,'JULI'=>7,'AGUSTUS'=>8,'SEPTEMBER'=>9,'OKTOBER'=>10,'NOVEMBER'=>11,'DESEMBER'=>12];
    $rows=$sheet->toArray(null,false,false,false);
    $unit=null;$rupiah=null;$header=null;
    foreach(array_slice($rows,0,20,true) as $r=>$row) foreach($row as $c=>$value){
        // A single-month heading only; cumulative headings never match this expression.
        if(!preg_match('/^BULAN ('.implode('|',array_keys($months)).') (20\d{2})$/',$normal($value),$m))continue;
        $sourcePeriod=$m[2].'-'.str_pad($months[$m[1]],2,'0',STR_PAD_LEFT);
        if($sourcePeriod!==$period)throw new InvalidArgumentException('Bulan/tahun file ('.$sourcePeriod.') tidak sesuai periode upload ('.$period.').');
        $next=$rows[$r+1]??[];
        for($i=$c;$i<$c+3;$i++){
            if($normal($next[$i]??'')==='JUMLAH UNIT')$unit=$i;
            if($normal($next[$i]??'')==='RUPIAH')$rupiah=$i;
        }
        $header=$r;
    }
    if($header===null||$unit===null||$rupiah===null)throw new InvalidArgumentException('Header Bulan [nama bulan] [tahun] dengan Jumlah Unit dan Rupiah tidak ditemukan. Kolom kumulatif tidak digunakan.');
    $number=function($value) {
        if($value===null||$value===''||in_array(trim((string)$value),['-','–','—'],true))return null;
        if(is_int($value)||is_float($value))return (string)$value;
        // Workbook numeric cells are read as numbers; reject ambiguous formatted strings.
        $text=trim((string)$value);
        if(!preg_match('/^-?\d+(?:\.\d+)?$/',$text))throw new InvalidArgumentException('Nilai Sub Total bukan angka yang valid. Gunakan sel numerik pada file.');
        return $text;
    };
    $result=[];
    foreach($rows as $r=>$row){
        if($r<=$header||!in_array('SUB TOTAL',array_map($normal,array_slice($row,0,$unit)),true))continue;
        $label=$normal(implode(' ',array_map(fn($v)=>is_scalar($v)?$v:'',array_slice($row,0,$unit))));
        $code=null;
        if(preg_match('/\bGB\s*([1-6])\b/',$label,$m))$code='gb'.$m[1];
        elseif(str_contains($label,'INTERNATIONAL BUSINESS PHARMA'))$code='ib_pharma';
        elseif(str_contains($label,'INTERNATIONAL BUSINESS FOOD'))$code='ib_food';
        elseif(str_contains($label,'ETHICAL'))$code='ethical';
        elseif(str_contains($label,'TOLL IN'))$code='toll_in';
        elseif(str_contains($label,'SOLONAT'))$code='solonat';
        elseif(str_contains($label,'KODC')||str_contains($label,'KONIMEX DIAGNOSTIC CENTER'))$code='kodc';
        if(!$code)continue; // The grand Sub Total and item detail rows are excluded.
        if(isset($result[$code]))throw new InvalidArgumentException('Baris Sub Total '.$code.' berulang; periksa file.');
        $result[$code]=['product'=>$code,'units'=>$number($row[$unit]??null),'rupiah'=>$number($row[$rupiah]??null)];
    }
    if(!$result)throw new InvalidArgumentException('Baris Sub Total per Group Brand tidak ditemukan.');
    return array_values($result);
}
