<?php
require_once __DIR__.'/unit_revenue_input_template.php';

/** Pick an upload from the requested year, preferring the latest earlier snapshot. */
function selectUnitRevenueHistoryPeriod(string $period,array $available): ?string
{
    [$year,$month]=unitRevenueTemplatePeriod($period);
    if($month===1)return null;
    $eligible=array_values(array_unique(array_filter($available,static fn($p)=>is_string($p)&&preg_match('/^'.$year.'-(0[1-9]|1[0-2])$/D',$p))));
    if(!$eligible)return null;
    $past=array_values(array_filter($eligible,static fn($p)=>$p<=$period));
    return $past?max($past):min($eligible);
}

/** Pick the period across both formats; use the lossless snapshot at that exact period when available. */
function selectUnitRevenueHistorySource(string $period,array $snapshotPeriods,array $legacyPeriods): array
{
    $source=selectUnitRevenueHistoryPeriod($period,array_merge($snapshotPeriods,$legacyPeriods));
    return ['source_period'=>$source,'snapshot'=>$source!==null&&in_array($source,$snapshotPeriods,true)];
}

function loadUnitRevenueTemplateHistory(mysqli $conn,string $period): array
{
    [$year,$month]=unitRevenueTemplatePeriod($period);
    $empty=['source_period'=>null,'records'=>[]];
    if($month===1)return $empty;
    $first=$year.'-01';$last=$year.'-12';$snapshotPeriods=[];
    try {
        $q=$conn->prepare('SELECT report_period FROM unit_revenue_input_snapshots WHERE report_period BETWEEN ? AND ?');
        $q->bind_param('ss',$first,$last);$q->execute();$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
        $snapshotPeriods=array_column($rows,'report_period');
    }catch(mysqli_sql_exception $error){if($error->getCode()!==1146)throw $error;}

    // Read the two tables separately: an identity JOIN can multiply duplicate
    // legacy detail rows and inflate quantities or revenue.
    $tables=[];$periods=[];
    foreach(['revenue_edp_mnj','UNIT'] as $table){
        try{
            $q=$conn->prepare('SELECT DISTINCT report_period FROM `'.$table.'` WHERE report_period BETWEEN ? AND ?');
            $q->bind_param('ss',$first,$last);$q->execute();$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
            $tables[]=$table;foreach($rows as $row)$periods[]=$row['report_period'];
        }catch(mysqli_sql_exception $error){if($error->getCode()!==1146)throw $error;}
    }
    $selection=selectUnitRevenueHistorySource($period,$snapshotPeriods,$periods);$source=$selection['source_period'];if($source===null)return $empty;
    if($selection['snapshot']){
        $q=$conn->prepare('SELECT payload FROM unit_revenue_input_snapshots WHERE report_period=?');
        $q->bind_param('s',$source);$q->execute();$saved=$q->get_result()->fetch_assoc();$q->close();
        if($saved){
            $json=@gzdecode($saved['payload']);
            if($json===false)throw new RuntimeException('Riwayat template UNIT & Revenue tidak dapat dibaca.');
            try{$records=json_decode($json,true,512,JSON_THROW_ON_ERROR);}catch(JsonException $error){throw new RuntimeException('Riwayat template UNIT & Revenue tidak valid.',0,$error);}
            if(!is_array($records)||!array_is_list($records))throw new RuntimeException('Susunan riwayat template UNIT & Revenue tidak valid.');
            return ['source_period'=>$source,'records'=>$records];
        }
    }
    $data=['revenue_edp_mnj'=>[],'UNIT'=>[]];
    foreach($tables as $table){
        $q=$conn->prepare('SELECT * FROM `'.$table.'` WHERE report_period=? ORDER BY id ASC');
        $q->bind_param('s',$source);$q->execute();$data[$table]=$q->get_result()->fetch_all(MYSQLI_ASSOC);$q->close();
    }
    $records=unitRevenueLegacyTemplateRecords($source,$data['revenue_edp_mnj'],$data['UNIT']);
    $manual=[];$fields=['jan','feb','mar','apr','mei','jun','jul','agt','sep','okt','nov','des'];
    foreach($data['revenue_edp_mnj'] as $row){
        $label=strtoupper(trim((string)($row['product_name']??'')));
        $kind=match($label){'EXIST ETH','TOTAL EXISTING GB ET'=>'existing','NP ETH','TOTAL NEW PRODUCT GB ET'=>'new','TOTAL ETH','TOTAL ETHICAL','TOTAL GB ET'=>'total',default=>null};
        if($kind===null)continue;
        $values=[];foreach($fields as $i=>$field)$values[]=$i+1>(int)substr($source,5,2)?null:unitRevenueHistoryNumber($row['rev_'.$field]??null);
        $manual[$kind]=['__kind'=>'ethical_revenue','section'=>$kind,'rev'=>$values];
    }
    return ['source_period'=>$source,'records'=>array_merge($records,array_values($manual))];
}

function unitRevenueHistoryNumber($value): ?float
{
    if($value===null||$value==='')return null;
    if(!is_numeric($value)||!is_finite((float)$value))throw new RuntimeException('Nilai riwayat UNIT & Revenue tidak valid.');
    return (float)$value;
}

/** Preserve combined Existing grouping rather than inventing category A/B/C. */
function unitRevenueLegacyCategory($value): ?string
{
    $value=strtoupper(trim((string)$value));
    $value=preg_replace('/^KAT(?:EGORI)?\.?\s+/','',$value);
    if(in_array($value,['A','B','C','ABC','D','E'],true))return $value;
    if(preg_match('/^(?:A[\s,\/+-]*B[\s,\/+-]*C|EXIS|EXIST|EXISTING(?:\s+PRODUCT)?)$/D',$value))return 'ABC';
    if(preg_match('/^(?:NP|NEW(?:\s+PRODUCT)?)$/D',$value))return 'D';
    return null;
}

function unitRevenueLegacyRowSummary(array $row): array
{
    $labels=array_map(static fn($key)=>strtoupper(trim((string)($row[$key]??''))),['product_code','principal_product_code','product_name','kategori']);
    foreach($labels as $label){
        if(preg_match('/^(?:SUB\s*)?TOTAL\s+(?:PRODUK\s+)?KATEGORI\s*([ABCD])\b/',$label,$m))return [true,$m[1]];
        if(preg_match('/^(?:(?:SUB\s*)?TOTAL\s+)?(?:EXIS(?:T(?:ING)?)?(?:\s+PRODUCT)?|EXISTING)(?:\s+(?:GB\s*)?(?:[1-6]|ET|ETH|ETHICAL))?$/D',$label))return [true,'ABC'];
        if(preg_match('/^(?:(?:SUB\s*)?TOTAL\s+)?(?:NEW\s+PRODUCT|NP)(?:\s+(?:GB\s*)?(?:[1-6]|ET|ETH|ETHICAL))?$/D',$label))return [true,'D'];
        if(preg_match('/^(?:(?:SUB\s*)?TOTAL|GRAND\s+TOTAL)\b/',$label))return [true,null];
    }
    return [false,null];
}

/** Recover detail identities only, retaining source order to interpret subtotal separators. */
function unitRevenueLegacyTemplateRows(array $rows,string $kind,int $sourceMonth): array
{
    $months=['jan','feb','mar','apr','mei','jun','jul','agt','sep','okt','nov','des'];
    $out=[];$pending=[];$currentGb=null;
    foreach($rows as $row){
        $gb=normalizeUnitRevenueGroup($row['gb']??'');
        if($gb!==null)$currentGb=$gb;
        [$summary,$boundary]=unitRevenueLegacyRowSummary($row);
        if($summary){
            if($boundary!==null&&$currentGb!==null){
                foreach($pending[$currentGb]??[] as $index)$out[$index]['kategori']=$boundary;
                $pending[$currentGb]=[];
            }
            continue;
        }
        $product=trim((string)($row['product_name']??''));
        $code=trim((string)($row['product_code']??''));$principal=trim((string)($row['principal_product_code']??''));
        if($product===''&&$code===''&&$principal==='')continue;
        if($gb===null)$gb=$currentGb;
        if($gb===null||$product==='')throw new RuntimeException('Identitas produk lama UNIT & Revenue belum lengkap. Isi GB dan nama produk pada template baru.');
        $category=unitRevenueLegacyCategory($row['kategori']??'');
        if($category===null&&trim((string)($row['kategori']??''))!=='')throw new RuntimeException('Kategori produk lama UNIT & Revenue belum dikenali. Lengkapi kategori A/B/C/D pada template baru.');
        $values=[];
        foreach($months as $m=>$field){
            // Legacy schemas replace unavailable values with zero; do not
            // present those generated zeros as future-month actual data.
            $key=($kind==='UNIT'?'unit_':'rev_').$field;
            $values[]=$m+1>$sourceMonth?null:unitRevenueHistoryNumber($row[$key]??null);
        }
        $qty=$kind==='UNIT'?$values:array_map(static fn($field,$m)=>$m+1>$sourceMonth?null:unitRevenueHistoryNumber($row['qty_'.$field]??null),$months,array_keys($months));
        $out[]=['gb'=>$gb,'product_code'=>$code,'principal_product_code'=>$principal,'product_name'=>$product,'kategori'=>$category,'qty'=>$qty,'rev'=>$kind==='UNIT'?array_fill(0,12,null):$values];
        if($category===null)$pending[$gb][]=count($out)-1;
    }
    foreach($out as $record)if($record['kategori']===null)throw new RuntimeException('Kategori produk lama UNIT & Revenue kosong dan batas TOTAL EXISTING / TOTAL NEW PRODUCT tidak lengkap. Lengkapi kategori A/B/C/D pada template baru.');
    return $out;
}

/** Combine canonical UNIT quantities with revenue; never sum repeated detail rows. */
function unitRevenueLegacyTemplateRecords(string $sourcePeriod,array $revenueRows,array $unitRows): array
{
    [, $month]=unitRevenueTemplatePeriod($sourcePeriod);$combined=[];
    foreach(['revenue_edp_mnj'=>$revenueRows,'UNIT'=>$unitRows] as $kind=>$rows){
        $seen=[];
        foreach(unitRevenueLegacyTemplateRows($rows,$kind,$month) as $record){
            $key=json_encode(array_intersect_key($record,array_flip(['gb','product_code','principal_product_code','product_name','kategori'])),JSON_THROW_ON_ERROR);
            if(isset($seen[$key])){
                if($seen[$key]!==$record)throw new RuntimeException('Rincian produk lama UNIT & Revenue berulang dengan nilai berbeda. Periksa data sebelum mengunduh template.');
                continue;
            }
            $seen[$key]=$record;
            if(!isset($combined[$key]))$combined[$key]=$record;
            elseif($kind==='UNIT')$combined[$key]['qty']=$record['qty'];
        }
    }
    return array_values($combined);
}

function buildUnitRevenueTemplateWithHistory(string $period,array $history): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    [$year,$month]=unitRevenueTemplatePeriod($period);$source=$history['source_period']??null;$records=$history['records']??[];
    if($month===1||!is_string($source)||!preg_match('/^'.$year.'-(0[1-9]|1[0-2])$/D',$source))return buildUnitRevenueInputTemplate($period);
    if(!is_array($records)||!array_is_list($records))throw new RuntimeException('Susunan riwayat UNIT & Revenue tidak valid.');
    if(count($records)>9995)throw new RuntimeException('Riwayat UNIT & Revenue melebihi kapasitas template.');
    $manual=array_values(array_filter($records,static fn($r)=>($r['__kind']??null)==='ethical_revenue'));
    $records=array_values(array_filter($records,static fn($r)=>($r['__kind']??null)!=='ethical_revenue'));
    $book=buildUnitRevenueInputTemplate($period,max(210,count($records)));$unit=$book->getSheetByName('UNIT');$revenue=$book->getSheetByName('REVENUE');$seen=[];
    foreach($records as $i=>$record){
        $gb=normalizeUnitRevenueGroup($record['gb']??'');$category=strtoupper(trim((string)($record['kategori']??'')));
        if($gb===null||trim((string)($record['product_name']??''))===''||!in_array($category,['A','B','C','ABC','D','E'],true))throw new RuntimeException('Identitas riwayat UNIT & Revenue belum lengkap.');
        $identity=[$gb,(string)($record['product_code']??''),(string)($record['principal_product_code']??''),(string)$record['product_name'],$category];
        $key=json_encode($identity,JSON_THROW_ON_ERROR);if(isset($seen[$key]))throw new RuntimeException('Produk berulang pada riwayat UNIT & Revenue.');$seen[$key]=true;
        $r=$i+6;
        foreach($identity as $c=>$value)$unit->getCell([$c+1,$r])->setValueExplicit($value,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        foreach(['qty'=>$unit,'rev'=>$revenue] as $kind=>$sheet){
            $values=$record[$kind]??null;if(!is_array($values)||!array_is_list($values)||count($values)!==12)throw new RuntimeException('Riwayat UNIT & Revenue harus berisi dua belas bulan.');
            foreach($values as $m=>$value){
                if($source>$period&&$m+1>$month)continue;
                $number=unitRevenueHistoryNumber($value);if($number!==null)$sheet->setCellValue([$m+6,$r],$number);
            }
        }
    }
    // Old compact snapshots have product-level Ethical revenue only. Carry
    // those sums into the new editable summary once; later snapshots preserve
    // the independently entered Existing/New/Total values exactly.
    if(!$manual){
        $sums=['existing'=>array_fill(0,12,null),'new'=>array_fill(0,12,null),'total'=>array_fill(0,12,null)];
        foreach($records as $record){
            if(normalizeUnitRevenueGroup($record['gb'])!=='ET')continue;
            $kind=$record['kategori']==='D'?'new':($record['kategori']==='E'?null:'existing');
            foreach($record['rev'] as $m=>$v)if($v!==null){foreach(array_filter([$kind,'total']) as $k)$sums[$k][$m]=($sums[$k][$m]??0)+(float)$v;}
        }
        foreach($sums as $kind=>$values)$manual[]=['__kind'=>'ethical_revenue','section'=>$kind,'rev'=>$values];
    }
    $destinations=unitRevenueEthicalManualRows($book);$seenManual=[];
    foreach($manual as $record){
        $kind=$record['section']??'';$values=$record['rev']??null;
        if(!isset($destinations[$kind])||isset($seenManual[$kind])||!is_array($values)||count($values)!==12)throw new RuntimeException('Riwayat rupiah Ethical tidak valid.');
        $seenManual[$kind]=true;
        foreach($values as $m=>$value){
            if($source>$period&&$m+1>$month)continue;
            $number=unitRevenueHistoryNumber($value);if($number!==null)$revenue->setCellValue([$m+6,$destinations[$kind]],$number);
        }
    }
    $book->getSheetByName('_TEMPLATE')->setCellValue('A6',$source);
    return $book;
}
