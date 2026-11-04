<?php
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/ib_exchange_rates.php';
require_once __DIR__ . '/../includes/ib_template_history.php';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'null') {
    header('Access-Control-Allow-Origin: null'); header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS'); header('Access-Control-Allow-Headers: Content-Type');
    if (($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK'] ?? '') === 'true') header('Access-Control-Allow-Private-Network: true');
} elseif ($origin === 'http://127.0.0.1:8000' || $origin === 'http://localhost:8000') {
    header('Access-Control-Allow-Origin: '.$origin); header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS'); header('Access-Control-Allow-Headers: Content-Type');
}
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { http_response_code(204); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); header('Allow: GET, OPTIONS'); echo json_encode(['success'=>false,'message'=>'GET only']); exit; }
$year = trim((string)($_GET['year'] ?? ''));
$requestedMonth = (int)($_GET['month'] ?? 0);
if (!preg_match('/^20\d{2}$/', $year)) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Tahun tidak valid.']); exit; }
if ($requestedMonth < 1 || $requestedMonth > 12) { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Bulan YTD harus bernilai 1 sampai 12.']); exit; }
$requestedPeriod = $_GET['period'] ?? ($year.'-'.str_pad((string)$requestedMonth,2,'0',STR_PAD_LEFT));
if (!is_string($requestedPeriod) || !preg_match('/^'.preg_quote($year,'/').'-(0[1-9]|1[0-2])$/D',$requestedPeriod)) {
    http_response_code(400);echo json_encode(['success'=>false,'message'=>'Periode IB tidak sesuai dengan tahun yang dipilih.']);exit;
}
$conn = openReportConnection();
if ($conn->connect_error) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Database tidak dapat dihubungkan.']); exit; }
$conn->set_charset('utf8mb4');
try {
    // Pastikan kolom untuk IB FD, REVENUE NEW, dan MCM tersedia saat ekspor
    // membaca data lama yang diunggah sebelum pemetaan ini ditambahkan.
    $extraColumns = [
        'target_ib_fd' => 'DECIMAL(20,2) NULL', 'actual_ib_fd' => 'DECIMAL(20,2) NULL',
        'target_new_ib_fm' => 'DECIMAL(20,2) NULL', 'actual_new_ib_fm' => 'DECIMAL(20,2) NULL',
        'target_new_ib_fd' => 'DECIMAL(20,2) NULL', 'actual_new_ib_fd' => 'DECIMAL(20,2) NULL',
        'target_mcm' => 'DECIMAL(20,2) NULL', 'actual_mcm' => 'DECIMAL(20,2) NULL',
    ];
    foreach ($extraColumns as $column => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM `revenue_ibfm` LIKE '{$column}'");
        if ($check && $check->num_rows === 0 && !$conn->query("ALTER TABLE `revenue_ibfm` ADD COLUMN `{$column}` {$definition}")) {
            throw new RuntimeException('Gagal menyiapkan kolom data IB.');
        }
    }
    $records=loadIbPeriodHistory($conn,$requestedPeriod);
    $data=['period'=>null,'target'=>array_fill(0,12,null),'actual'=>array_fill(0,12,null),'targetFd'=>array_fill(0,12,null),'actualFd'=>array_fill(0,12,null),'newTarget'=>array_fill(0,12,null),'newActual'=>array_fill(0,12,null),'newTargetFd'=>array_fill(0,12,null),'newActualFd'=>array_fill(0,12,null),'mcmTarget'=>array_fill(0,12,null),'mcmActual'=>array_fill(0,12,null)];
    foreach($records as $row){ $i=(int)$row['month_number']-1; if($i<0||$i>11)continue; $data['period']=$row['report_period']; $data['target'][$i]=$row['target_ib_fm']===null?null:(float)$row['target_ib_fm']; $data['actual'][$i]=$row['actual_ib_fm']===null?null:(float)$row['actual_ib_fm']; $data['targetFd'][$i]=$row['target_ib_fd']===null?null:(float)$row['target_ib_fd']; $data['actualFd'][$i]=$row['actual_ib_fd']===null?null:(float)$row['actual_ib_fd']; $data['newTarget'][$i]=$row['target_new_ib_fm']===null?null:(float)$row['target_new_ib_fm']; $data['newActual'][$i]=$row['actual_new_ib_fm']===null?null:(float)$row['actual_new_ib_fm']; $data['newTargetFd'][$i]=$row['target_new_ib_fd']===null?null:(float)$row['target_new_ib_fd']; $data['newActualFd'][$i]=$row['actual_new_ib_fd']===null?null:(float)$row['actual_new_ib_fd']; $data['mcmTarget'][$i]=$row['target_mcm']===null?null:(float)$row['target_mcm']; $data['mcmActual'][$i]=$row['actual_mcm']===null?null:(float)$row['actual_mcm']; }
    $sum=static function(array $values):?float { return in_array(null,$values,true)?null:array_sum($values); };
    $sumYtd=static function(array $values,int $month):?float {
        $selected=array_slice($values,0,$month);
        if (!array_filter($selected,static fn($value)=>$value!==null)) return null;
        return array_sum(array_map(static fn($value)=>(float)($value ?? 0),$selected));
    };
    $data['ytd']=['target'=>$sumYtd($data['target'],$requestedMonth),'actual'=>$sumYtd($data['actual'],$requestedMonth),'targetFd'=>$sumYtd($data['targetFd'],$requestedMonth),'actualFd'=>$sumYtd($data['actualFd'],$requestedMonth),'newTarget'=>$sumYtd($data['newTarget'],$requestedMonth),'newActual'=>$sumYtd($data['newActual'],$requestedMonth),'newTargetFd'=>$sumYtd($data['newTargetFd'],$requestedMonth),'newActualFd'=>$sumYtd($data['newActualFd'],$requestedMonth),'mcmTarget'=>$sumYtd($data['mcmTarget'],$requestedMonth),'mcmActual'=>$sumYtd($data['mcmActual'],$requestedMonth)];
    $data['fullYear']=['target'=>$sum($data['target']),'actual'=>$sum($data['actual'])];
    $data['fullYear']['targetFd']=$sum($data['targetFd']); $data['fullYear']['actualFd']=$sum($data['actualFd']);
    $data['fullYear']['newTarget']=$sum($data['newTarget']); $data['fullYear']['newActual']=$sum($data['newActual']);
    $data['fullYear']['newTargetFd']=$sum($data['newTargetFd']); $data['fullYear']['newActualFd']=$sum($data['newActualFd']);
    $data['fullYear']['mcmTarget']=$sum($data['mcmTarget']); $data['fullYear']['mcmActual']=$sum($data['mcmActual']);
    ensureIbExchangeRates($conn);
    $rateStatement = $conn->prepare('SELECT usd_rate FROM ib_exchange_rates WHERE report_year = ?');
    if (!$rateStatement) throw new RuntimeException('Kurs IB gagal dibaca.');
    $rateYear = (int)$year;
    $rateStatement->bind_param('i', $rateYear);
    $rateStatement->execute();
    $rateRow = $rateStatement->get_result()->fetch_assoc();
    $data['usdRate'] = $rateRow ? (float)$rateRow['usd_rate'] : null;
    $rateStatement->close();
    echo json_encode(['success'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch(Throwable $e){ http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); }
$conn->close();
