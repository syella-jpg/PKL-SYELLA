<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function ibHistoryPeriod(string $period): array
{
    if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$parts)) throw new InvalidArgumentException('Periode IB tidak valid.');
    return [$parts[1],(int)$parts[2]];
}

/** Prefer an existing historical snapshot; otherwise use the earliest later upload, limited to the requested months. */
function loadIbPeriodHistory(mysqli $conn,string $period): array
{
    [$year] = ibHistoryPeriod($period);
    try {
        $stmt=$conn->prepare('SELECT month_number,target_ib_fm,actual_ib_fm,target_ib_fd,actual_ib_fd,target_new_ib_fm,actual_new_ib_fm,target_new_ib_fd,actual_new_ib_fd,target_mcm,actual_mcm,report_period
            FROM revenue_ibfm WHERE report_period=(SELECT COALESCE(MAX(CASE WHEN report_period<=? THEN report_period END),MIN(report_period)) FROM revenue_ibfm
                WHERE report_period LIKE CONCAT(?,"-%")) ORDER BY month_number');
        $stmt->bind_param('ss',$period,$year);$stmt->execute();
        $records=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();
        return array_values(array_filter($records,static fn($r)=>$r['report_period']<=$period || (int)$r['month_number']<=(int)substr($period,5,2)));
    } catch(mysqli_sql_exception $error) {
        if($error->getCode()===1146)return [];
        throw $error;
    }
}

/** Original cumulative cells preserve gaps that cannot be reconstructed from differences. */
function loadIbCumulativeHistory(mysqli $conn,string $sourcePeriod): array
{
    try {
        $stmt=$conn->prepare('SELECT report_period,month_number,metric_code,business_unit,value_type,amount FROM ib_monthly_values
            WHERE report_period=? AND metric_code IN ("revenue_exist_cum","revenue_new_cum","mcm_cum")');
        $stmt->bind_param('s',$sourcePeriod);$stmt->execute();
        $records=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $records;
    } catch(mysqli_sql_exception $error) {
        if($error->getCode()===1146)return [];
        throw $error;
    }
}

function fillIbTemplateHistory(Spreadsheet $book,string $period,array $records,array $cumulative=[]): ?string
{
    [$year,$month]=ibHistoryPeriod($period);
    if($month===1)return null;
    $eligible=array_filter($records,static fn($row)=>preg_match('/^'.preg_quote($year,'/').'-(0[1-9]|1[0-2])$/D',(string)($row['report_period']??'')));
    if(!$eligible)return null;
    $past=array_filter($eligible,static fn($r)=>$r['report_period']<=$period);
    $source=$past?max(array_column($past,'report_period')):min(array_column($eligible,'report_period'));$byMonth=[];
    foreach($eligible as $row)if($row['report_period']===$source)$byMonth[(int)$row['month_number']]=$row;
    $raw=[];
    foreach($cumulative as $row)if(($row['report_period']??null)===$source){
        $raw[$row['metric_code']][$row['business_unit']][$row['value_type']][(int)$row['month_number']]=$row['amount'];
    }
    $mapping=[
        ['YFR - IBFM',6,'target_ib_fm','revenue_exist_cum','IB_FM','TARGET'],['YFR - IBFM',7,'actual_ib_fm','revenue_exist_cum','IB_FM','ACTUAL'],
        ['YFR - IBFM',9,'target_new_ib_fm','revenue_new_cum','IB_FM','TARGET'],['YFR - IBFM',10,'actual_new_ib_fm','revenue_new_cum','IB_FM','ACTUAL'],
        ['IAP',6,'target_ib_fd','revenue_exist_cum','IB_FD','TARGET'],['IAP',7,'actual_ib_fd','revenue_exist_cum','IB_FD','ACTUAL'],
        ['IAP',9,'target_new_ib_fd','revenue_new_cum','IB_FD','TARGET'],['IAP',10,'actual_new_ib_fd','revenue_new_cum','IB_FD','ACTUAL'],
        ['HRU',6,'target_mcm','mcm_cum','GROUP','TARGET'],['HRU',7,'actual_mcm','mcm_cum','GROUP','ACTUAL']
    ];
    foreach($mapping as [$name,$row,$key,$metric,$unit,$type]){
        $sheet=$book->getSheetByName($name);if(!$sheet)continue;
        $exact=$raw[$metric][$unit][$type]??null;
        $sum=0.0;$complete=true;
        for($m=1;$m<=12;$m++){
            if($source>$period && $m>$month)continue;
            $value=$byMonth[$m][$key]??null;
            if($value===null||$value==='')$complete=false;
            elseif(!is_numeric($value)||!is_finite((float)$value))throw new RuntimeException('Nilai IB tersimpan tidak valid.');
            else $sum+=(float)$value;
            $total=$exact!==null?($exact[$m]??null):($complete?$sum:null);
            if($total!==null&&$total!==''&&is_numeric($total))$sheet->setCellValue(Coordinate::stringFromColumnIndex($m+4).$row,(float)$total);
        }
    }
    $book->getSheetByName('_TEMPLATE')?->setCellValue('A6',$source);
    return $source;
}
