<?php
/** Compare product-month values, independently of row order and workbook protection. */
function assertRawActualPeriod(string $period, array $before, array $after): void
{
    if (!$before) return; // First annual upload may establish the existing history.
    $selected = (int)substr($period,5,2)-1;
    foreach (array_unique(array_merge(array_keys($before),array_keys($after))) as $key) {
        for ($month=0;$month<12;$month++) {
            if ($month===$selected) continue;
            $old=$before[$key][$month]??null;$new=$after[$key][$month]??null;
            // Empty spreadsheet formulas commonly arrive as zero.
            if (abs((float)($old??0)-(float)($new??0)) <= 0.000001) continue;
            $lockedPeriod=substr($period,0,4).'-'.str_pad((string)($month+1),2,'0',STR_PAD_LEFT);
            throw new InvalidArgumentException('UPLOAD DITOLAK! Terdapat perubahan pada realisasi periode '.$lockedPeriod.' yang masih terkunci. Hubungi pihak CSPPM untuk membuka periode tersebut, lalu gunakan template periode yang dikoreksi.');
        }
    }
}
function biayaActualPeriodValues(array $rows,bool $stored=false): array
{
    $months=['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];$out=[];
    foreach($rows as $row){
        if(($row[$stored?'jenis':'type']??'')!=='actual')continue;
        $key=($row['gb']??'').':'.($row[$stored?'nomor_produk':'number']??'').':'.($row[$stored?'kategori':'category']??'');
        $out[$key]=$stored?array_map(static fn($m)=>$row[$m]??null,$months):$row['values'];
    }
    return $out;
}
function ethicalActualPeriodValues(array $inputs): array
{
    $out=[];
    if(isset($inputs['_products'])){
        foreach($inputs['_products'] as $kind=>$products)foreach($products as $row){
            $identity=trim((string)($row['code']??''));if($identity==='')$identity=trim((string)($row['name']??''));
            foreach(['actual_unit','actual_hna'] as $metric){
                $values=$row[$metric]??array_fill(0,12,null);
                if($metric==='actual_hna'&&isset($row['price']))$values=array_map(static fn($v)=>$v===null?null:$v*(float)$row['price'],$row['actual_unit']??array_fill(0,12,null));
                $out[$kind.':'.strtolower($identity).':'.$metric]=$values;
            }
        }
    }else foreach(['actual_unit','actual_hna'] as $metric)foreach($inputs[$metric]??[] as $kind=>$values)$out[$kind.':'.$metric]=$values;
    return $out;
}

function unitRevenueActualPeriodValues(array $records): array {
    $out=[];
    foreach($records as $row){
        $key=($row['__kind']??'product').':'.($row['gb']??'').':'.($row['section']??'').':'.strtolower(trim((string)(($row['product_code']??'')?:($row['product_name']??'')))).':'.($row['kategori']??'');
        foreach(['qty','rev'] as $field)if(isset($row[$field]))$out[$key.':'.$field]=$row[$field];
    }
    return $out;
}
function bodKxActualPeriodValues(array $state): array {
    $out=[];foreach($state['sales']??[] as $row)foreach(['units','rupiah'] as $field)$out[$row['product'].':'.strtoupper($row['barang']).':'.$field]=$row[$field];return $out;
}

/** Annual targets are immutable outside January, regardless of sheet protection. */
function assertRawTargetPeriod(string $period,array $before,array $after): void {
    if(substr($period,5,2)==='01')return;
    foreach(array_unique(array_merge(array_keys($before),array_keys($after))) as $key)for($m=0;$m<12;$m++){
        $old=$before[$key][$m]??null;$new=$after[$key][$m]??null;
        $textChange=(!is_numeric($old)&&$old!==null)||(!is_numeric($new)&&$new!==null);
        if(($textChange&&$old!==$new)||(!$textChange&&abs((float)($old??0)-(float)($new??0))>0.000001))
            throw new InvalidArgumentException('UPLOAD DITOLAK! Terdapat perubahan pada target yang masih terkunci. Target hanya dapat diubah pada periode Januari. Hubungi pihak CSPPM untuk melakukan koreksi.');
    }
}
function biayaTargetPeriodValues(array $rows,bool $stored=false): array {
    foreach($rows as &$r){$field=$stored?'jenis':'type';$r[$field]=($r[$field]??'')==='target'?'actual':'skip';}unset($r);
    return biayaActualPeriodValues($rows,$stored);
}
function ethicalTargetPeriodValues(array $inputs): array {
    if(isset($inputs['_products']))foreach($inputs['_products'] as &$products)foreach($products as &$p){$p['actual_unit']=$p['unit']??[];$p['actual_hna']=$p['hna']??[];}unset($products,$p);
    $inputs['actual_unit']=$inputs['unit']??[];$inputs['actual_hna']=$inputs['hna']??[];
    return ethicalActualPeriodValues($inputs);
}
function bodKxTargetPeriodValues(array $state): array {
    $out=[];foreach($state['rod']??[] as $r){$key=$r['product'].':'.strtoupper($r['code']);$out[$key.':units']=$r['units'];$out[$key.':value']=array_map(static fn($v)=>$v===null?null:$v*(float)$r['price'],$r['units']);}return $out;
}
