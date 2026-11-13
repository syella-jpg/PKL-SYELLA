<?php
require_once __DIR__ . '/../includes/database.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'null') {
    header('Access-Control-Allow-Origin: null');
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
    if (($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_PRIVATE_NETWORK'] ?? '') === 'true') {
        header('Access-Control-Allow-Private-Network: true');
    }
} elseif ($origin === 'http://127.0.0.1:8000' || $origin === 'http://localhost:8000') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__.'/../includes/revenue_so_source.php';
$conn=null;
try {
    $period=trim((string)($_GET['period']??''));
    if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$period)){
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'Periode harus berformat YYYY-MM.']);
    }else{
        $conn=openReportConnection();
        echo json_encode(readRevenueSellingOutSource($conn,$period));
    }
}catch(Throwable $error){
    error_log('Revenue Selling Out API: '.$error->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Data Revenue Selling Out gagal dibaca. Periksa koneksi dan struktur database.']);
}finally{if($conn)$conn->close();}
