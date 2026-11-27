<?php
require_once __DIR__.'/raw_actual_period.php';
require_once __DIR__.'/raw_native_period.php';
require_once __DIR__.'/report_access.php';
/** SELECT only; missing historical tables mean no baseline, never create them here. */
function uploadPrecheckRows($db,string $sql,string $types='',array $args=[]): array {
    if(!preg_match('/^SELECT\s/i',$sql))throw new LogicException('Precheck hanya boleh membaca data.');
    try{$q=$db->prepare($sql);if($types!=='')$q->bind_param($types,...$args);$q->execute();try{return $q->get_result()->fetch_all(MYSQLI_ASSOC);}finally{$q->close();}}
    catch(mysqli_sql_exception $e){if($e->getCode()===1146)return [];throw $e;}
}
function uploadPrecheckInput($db,string $section,string $period,string $path): void {
    require_once __DIR__.'/upload_file_locks.php';$section=uploadCanonicalSection($section);uploadValidateScope($section,$period);
    $year=(int)substr($period,0,4);$first=$year.'-01';
    if(reportIsPharma()&&!in_array($section,reportPharmaUploads(),true))throw new InvalidArgumentException('Bagian ini tidak tersedia untuk akun pharma.');
    if(in_array($section,['TARGET REVENUE','TARGET UNIT SELL OUT','REVENUE VERSI EDP MNJ Target'],true)&&substr($period,5,2)!=='01')throw new InvalidArgumentException('UPLOAD DITOLAK! Sheet target terkunci di luar periode Januari. Hubungi pihak CSPPM untuk melakukan koreksi.');
    $locks=uploadPrecheckRows($db,'SELECT locked FROM report_upload_files WHERE section=? AND report_period=?','ss',[uploadCanonicalSection($section),$period]);
    if(!empty($locks[0]['locked']))throw new InvalidArgumentException('UPLOAD DITOLAK! Periode '.$period.' sudah terkunci. Hubungi pihak CSPPM untuk membuka periode tersebut.');
    $native=['SERVICE LEVEL'=>'service_level_input','BOD MNJ'=>'BOD MNJ','BOD JML SDM'=>'bod_jml_sdm_input','BOD BI.Opr & BOD BI.SDM'=>'bod bi opr sdm','UPB BJ kx'=>'upb bj kx','UPB non BJ'=>'upb non bj','UPB DISTR'=>'upb distr','MNJ'=>'mnj_input_files'];
    if(isset($native[$section])){
        $table=$native[$section];$saved=uploadPrecheckRows($db,'SELECT base64 FROM `'.$table.'` WHERE report_period BETWEEN ? AND ? ORDER BY report_period DESC LIMIT 1','ss',[$first,$period]);
        if($saved){$before=base64_decode($saved[0]['base64'],true);if($before===false)throw new RuntimeException('Riwayat file tidak valid.');assertRawNativePeriod($period,$before,file_get_contents($path));}return;
    }
    if(in_array($section,['DAFTAR','LAUNCH','DATA PARETO UPB','TARGET REVENUE','TARGET UNIT SELL OUT','REVENUE VERSI EDP MNJ Target'],true))return; // No historical actual edits in these inputs; final parsers still validate structure.
    require_once __DIR__.'/../vendor/autoload.php';
    $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
    if(!($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Ods)&&!($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx))throw new InvalidArgumentException('Gunakan file ODS atau XLSX.');
    $reader->setReadEmptyCells(false);$book=$reader->load($path);
    try{
        if($section==='BIAYA'){
            require_once __DIR__.'/biaya_workbook.php';$rows=parseBiayaProducts($book,$year);
            if(reportIsPharma())$rows=reportPharmaRows($rows,'gb',true);
            $old=uploadPrecheckRows($db,'SELECT * FROM biaya WHERE report_year=?'.(reportIsPharma()?' AND gb IN (1,4)':''),'i',[$year]);
            assertRawTargetPeriod($period,biayaTargetPeriodValues($old,true),biayaTargetPeriodValues($rows));assertRawActualPeriod($period,biayaActualPeriodValues($old,true),biayaActualPeriodValues($rows));return;
        }
        if($section==='ETHICAL'){
            require_once __DIR__.'/unit_sell_out_input_template.php';require_once __DIR__.'/ethical_source_template.php';
            $data=isEthicalSourceTemplate($book)?parseEthicalSourceTemplate($book,$period):parseEthicalNasSource($book,$period);if(!$data)throw new InvalidArgumentException('Struktur ETHICAL tidak dikenali.');
            $saved=uploadPrecheckRows($db,'SELECT payload FROM `template ethical` WHERE report_year=?','i',[$year]);$old=$saved?json_decode($saved[0]['payload'],true,512,JSON_THROW_ON_ERROR):[];$new=$data['inputs'];if(!isset($old['_products']))unset($new['_products']);
            assertRawTargetPeriod($period,ethicalTargetPeriodValues($old),ethicalTargetPeriodValues($new));if($saved)assertRawActualPeriod($period,ethicalActualPeriodValues($old),ethicalActualPeriodValues($new));return;
        }
        if(in_array($section,['UNIT & Revenue','REVENUE VERSI EDP MNJ'],true)){
            require_once __DIR__.'/unit_revenue_template_history.php';$history=loadUnitRevenueTemplateHistory($db,$period);$old=$history['records']??[];
            if(!isUnitRevenueInputTemplate($book)){if($old&&substr($period,5,2)!=='01')throw new InvalidArgumentException('UPLOAD DITOLAK! Gunakan template UNIT & Revenue terbaru untuk memeriksa periode terkunci.');return;}
            $new=parseUnitRevenueInputTemplate($book,$period);if(reportIsPharma()){$new=reportPharmaRows($new,'gb',true);$old=reportPharmaRows($old,'gb');}assertRawActualPeriod($period,unitRevenueActualPeriodValues($old),unitRevenueActualPeriodValues($new));return;
        }
        if($section==='BOD KX Sell In'){
            require_once __DIR__.'/bod_kx_sell_in_input_store.php';require_once __DIR__.'/bod_kx_sell_in_monthly.php';require_once __DIR__.'/bod_kx_sell_in_rod_input.php';require_once __DIR__.'/bod_kx_sell_in_targets.php';
            $saved=uploadPrecheckRows($db,'SELECT payload FROM `BOD KX SELL IN TEMPLATE` WHERE report_year=?','i',[$year]);$old=decodeBodKxInputState($saved[0]??null,$year);
            if(isBodKxInputTemplate($book)){$incoming=parseBodKxInputTemplate($book,$period,true);$incoming['_rod_template']=true;}
            else{foreach($book->getAllSheets() as $s)if(isBodKxRodSheet($s)){if(substr($period,5,2)!=='01')throw new InvalidArgumentException('UPLOAD DITOLAK! Target ROD terkunci di luar Januari. Hubungi pihak CSPPM.');return;}$incoming=bodKxMonthlyInput(parseBodKxSellInMonthly($book->getActiveSheet(),$period),$period);}
            if(reportIsPharma()){foreach(['sales','rod'] as $kind)reportPharmaRows($incoming[$kind]??[],'product',true);$incoming['_allowed_groups']=['gb1','gb4'];}
            $merged=mergeBodKxInputState($old,$incoming)['state'];assertRawTargetPeriod($period,bodKxTargetPeriodValues($old),bodKxTargetPeriodValues($merged));assertRawActualPeriod($period,bodKxActualPeriodValues($old),bodKxActualPeriodValues($merged));return;
        }
        if($section==='MTM'){
            require_once __DIR__.'/mtm_workbook.php';
            $headers=implode(' ',array_map(static fn($r)=>implode(' ',array_filter($r,'is_string')),$book->getActiveSheet()->rangeToArray('A1:AD4',null,false,false)));
            if(stripos($headers,'Budget Spending')!==false){$saved=uploadPrecheckRows($db,"SELECT appendix_data FROM mtm_monthly_values WHERE report_period=? AND metric_code='APPENDIX' AND value_type='FILE' AND month_number=0",'s',[$first]);if($saved)assertRawNativePeriod($period,$saved[0]['appendix_data'],file_get_contents($path));return;}
            $data=parseMtmWorkbook($book);$saved=uploadPrecheckRows($db,"SELECT metric_code,value_type,month_number,amount FROM mtm_monthly_values WHERE report_period=(SELECT MAX(report_period) FROM mtm_monthly_values WHERE report_period BETWEEN ? AND ?) AND value_type IN ('TARGET','ROLLING','ACTUAL')",'ss',[$first,$period]);$old=[];$new=[];
            foreach($saved as $r)$old[$r['value_type']][$r['metric_code']][(int)$r['month_number']-1]=$r['amount'];foreach($data as $metric=>$types)foreach($types as $type=>$values)foreach($values as $m=>$v)$new[$type][$metric][(int)$m-1]=$v;
            foreach(['TARGET','ROLLING'] as $type)assertRawTargetPeriod($period,$old[$type]??[],$new[$type]??[]);assertRawActualPeriod($period,$old['ACTUAL']??[],$new['ACTUAL']??[]);return;
        }
        if($section==='IB'){
            uploadPrecheckIb($db,$book,$path,$period);return;
        }
        return;
    }finally{$book->disconnectWorksheets();}
}
function uploadPrecheckIb($db,$book,string $path,string $period): void {
    require_once __DIR__.'/../dashboard/revenue_ibfm_processor.php';require_once __DIR__.'/ib_unit_template.php';
    $year=(int)substr($period,0,4);$first=$year.'-01';
    $saved=uploadPrecheckRows($db,"SELECT report_period,month_number,metric_code,business_unit,value_type,amount FROM ib_monthly_values WHERE report_period BETWEEN ? AND ? AND metric_code IN ('revenue_exist_cum','revenue_new_cum','mcm_cum') ORDER BY report_period DESC",'ss',[$first,$period]);$old=[];
    foreach($saved as $r){$key=$r['metric_code'].':'.$r['business_unit'];$m=(int)$r['month_number']-1;if(!array_key_exists($m,$old[$r['value_type']][$key]??[]))$old[$r['value_type']][$key][$m]=$r['amount'];}$new=$old;
    foreach([['YFR - IBFM','revenue_exist_cum','IB_FM','SALES REVENUE (USD)',6],['IAP','revenue_exist_cum','IB_FD','SALES REVENUE (USD)',6],['YFR - IBFM','revenue_new_cum','IB_FM','SALES REVENUE NEW BUSINESS PRODUCT (USD)',9],['IAP','revenue_new_cum','IB_FD','SALES REVENUE NEW BUSINESS PRODUCT (USD)',9],['HRU','mcm_cum','GROUP','MCM = REVENUE - HPP – EXPENSES',15]] as [$sheet,$metric,$unit,$label,$row]){
        $data=processRevenueExistIbFmSpreadsheet($path,$year,$sheet,true,$label,$row);$active=count(array_filter(array_merge($data['cumulative']['target'],$data['cumulative']['actual']),static fn($v)=>$v!==null))>0;
        foreach(['TARGET'=>'target','ACTUAL'=>'actual'] as $type=>$field)foreach($data['cumulative'][$field] as $m=>$v)if($v!==null||($active&&$type==='ACTUAL'&&(int)$m===(int)substr($period,5,2)))$new[$type][$metric.':'.$unit][(int)$m-1]=$v;
    }
    assertRawTargetPeriod($period,$old['TARGET']??[],$new['TARGET']??[]);assertRawActualPeriod($period,$old['ACTUAL']??[],$new['ACTUAL']??[]);
    if($book->getSheetByName('_TEMPLATE')?->getCell('A5')->getValue()==='IB_UNIT_KPI_V1'){
        $incoming=readIbUnitTable(getIbInputSheet($book,'GM IB'),true);$saved=uploadPrecheckRows($db,"SELECT payload FROM unit_sell_out_target_inputs WHERE report_year=? AND product='ib'",'i',[$year]);$before=$saved?json_decode($saved[0]['payload'],true,512,JSON_THROW_ON_ERROR):[];$after=mergeIbUnitParts($incoming,$before);
        foreach(['actual_A','actual_B'] as $key)$after[$key][(int)substr($period,5,2)-1]=$incoming[$key][(int)substr($period,5,2)-1];
        assertRawTargetPeriod($period,array_intersect_key($before,array_flip(['A','B'])),array_intersect_key($after,array_flip(['A','B'])));assertRawActualPeriod($period,array_intersect_key($before,array_flip(['actual_A','actual_B'])),array_intersect_key($after,array_flip(['actual_A','actual_B'])));
    }
}
