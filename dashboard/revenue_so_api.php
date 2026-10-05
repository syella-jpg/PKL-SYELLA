<?php

header('Content-Type: application/json');

$host = '127.0.0.1';
$user = 'root';
$password = '';
$database = 'LAPORAN KORPORAT';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database gagal terhubung: ' . $conn->connect_error
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

$sql = "
    SELECT
        id,
        gb,
        product_code,
        principal_product_code,
        product_name,
        kategori,

        qty_jan,
        qty_feb,
        qty_mar,
        qty_apr,
        qty_mei,
        qty_jun,
        qty_jul,
        qty_agt,
        qty_sep,
        qty_okt,
        qty_nov,
        qty_des,
        qty_total,

        rev_jan,
        rev_feb,
        rev_mar,
        rev_apr,
        rev_mei,
        rev_jun,
        rev_jul,
        rev_agt,
        rev_sep,
        rev_okt,
        rev_nov,
        rev_des,
        rev_total

    FROM revenue_selling_out
    ORDER BY id ASC
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Query gagal: ' . $conn->error
    ]);
    $conn->close();
    exit;
}

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'total' => count($data),
    'data' => $data
]);

$conn->close();

