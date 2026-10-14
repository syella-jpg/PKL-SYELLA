<?php
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
$conn = new mysqli('127.0.0.1', 'root', '', 'LAPORAN KORPORAT');
if ($conn->connect_error) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Database tidak dapat dihubungkan.']); exit; }
$conn->set_charset('utf8mb4');
try {
    $stmt = $conn->prepare("SELECT month_number,target_ib_fm,actual_ib_fm,target_ib_fd,actual_ib_fd,report_period FROM revenue_ibfm WHERE report_period LIKE CONCAT(?,'-%') AND report_period=(SELECT MAX(report_period) FROM revenue_ibfm WHERE report_period LIKE CONCAT(?,'-%')) ORDER BY month_number");
    if (!$stmt) throw new RuntimeException('Data sheet IB belum tersedia. Unggah file melalui bagian IB terlebih dahulu.');
    $stmt->bind_param('ss',$year,$year); if (!$stmt->execute()) throw new RuntimeException('Data sheet IB gagal dibaca.');
    $rows=$stmt->get_result(); $data=['period'=>null,'target'=>array_fill(0,12,null),'actual'=>array_fill(0,12,null),'targetFd'=>array_fill(0,12,null),'actualFd'=>array_fill(0,12,null)];
    while($row=$rows->fetch_assoc()){ $i=(int)$row['month_number']-1; if($i<0||$i>11)continue; $data['period']=$row['report_period']; $data['target'][$i]=$row['target_ib_fm']===null?null:(float)$row['target_ib_fm']; $data['actual'][$i]=$row['actual_ib_fm']===null?null:(float)$row['actual_ib_fm']; $data['targetFd'][$i]=$row['target_ib_fd']===null?null:(float)$row['target_ib_fd']; $data['actualFd'][$i]=$row['actual_ib_fd']===null?null:(float)$row['actual_ib_fd']; }
    $sum=static function(array $values):?float { return in_array(null,$values,true)?null:array_sum($values); };
    $sumYtd=static function(array $values,int $month):?float {
        $selected=array_slice($values,0,$month);
        if (!array_filter($selected,static fn($value)=>$value!==null)) return null;
        return array_sum(array_map(static fn($value)=>(float)($value ?? 0),$selected));
    };
    $data['ytd']=['target'=>$sumYtd($data['target'],$requestedMonth),'actual'=>$sumYtd($data['actual'],$requestedMonth),'targetFd'=>$sumYtd($data['targetFd'],$requestedMonth),'actualFd'=>$sumYtd($data['actualFd'],$requestedMonth)];
    $data['fullYear']=['target'=>$sum($data['target']),'actual'=>$sum($data['actual'])];
    $data['fullYear']['targetFd']=$sum($data['targetFd']); $data['fullYear']['actualFd']=$sum($data['actualFd']);
    echo json_encode(['success'=>true,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); $stmt->close();
} catch(Throwable $e){ http_response_code(500); echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); }
$conn->close();
