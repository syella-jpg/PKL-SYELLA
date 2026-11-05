<?php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../includes/report_input_templates.php';
require_once __DIR__.'/../includes/mtm_template_history.php';
require_once __DIR__.'/../includes/ib_template_history.php';
require_once __DIR__.'/../includes/biaya_template_history.php';
require_once __DIR__.'/../includes/unit_revenue_template_history.php';
require_once __DIR__.'/../includes/unit_sell_out_input_template.php';

// Match the upload page's file:// and local-server access.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ['null', 'http://127.0.0.1:8000', 'http://localhost:8000'], true)) {
    header('Access-Control-Allow-Origin: '.$origin);
    header('Vary: Origin');
}

try {
    $section = is_string($_GET['section'] ?? null) ? $_GET['section'] : '';
    $period = is_string($_GET['period'] ?? null) ? $_GET['period'] : '';
    reportInputTemplateSpec($section, $period);
    $rebuildFromHistory = in_array($section,['BIAYA','UNIT & Revenue','REVENUE VERSI EDP MNJ','TARGET UNIT SELL OUT'],true) && substr($period,5,2) !== '01';
    $book = $rebuildFromHistory ? null : buildReportInputTemplate($section, $period);
    if ($section === 'MTM' || (in_array($section,['IB','BIAYA','UNIT & Revenue','REVENUE VERSI EDP MNJ','TARGET UNIT SELL OUT'],true) && substr($period,5,2) !== '01')) {
        require_once __DIR__.'/../includes/database.php';
        $conn = openReportConnection();
        try {
            if ($conn->connect_error) throw new RuntimeException('Database laporan tidak dapat dihubungkan.');
            $conn->set_charset('utf8mb4');
            if ($section === 'TARGET UNIT SELL OUT') {
                ensureUnitSellOutInputStore($conn);
                $year=(int)substr($period,0,4);
                $query=$conn->prepare('SELECT report_year,product,payload FROM unit_sell_out_target_inputs WHERE report_year=?');
                $query->bind_param('i',$year);$query->execute();
                $history=$query->get_result()->fetch_all(MYSQLI_ASSOC);$query->close();
                $book=buildUnitSellOutProductWithHistory($period,$history);
            } elseif ($section === 'MTM') fillMtmTemplateHistory($book,$period,loadMtmTemplateHistory($conn,$period));
            elseif (in_array($section,['UNIT & Revenue','REVENUE VERSI EDP MNJ'],true)) {
                $history=loadUnitRevenueTemplateHistory($conn,$period);
                $book?->disconnectWorksheets();
                $book=buildUnitRevenueTemplateWithHistory($period,$history);
            } elseif ($section === 'BIAYA') {
                $records=loadBiayaTemplateHistory($conn,$period);
                $book?->disconnectWorksheets();
                $book=buildBiayaTemplateWithHistory($period,$records);
            } else {
                $records=loadIbPeriodHistory($conn,$period);
                $source=$records[0]['report_period']??null;
                fillIbTemplateHistory($book,$period,$records,$source?loadIbCumulativeHistory($conn,$source):[]);
            }
        } finally { $conn->close(); }
    }
    $stream = fopen('php://temp', 'w+b');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);
    if ($section === 'TARGET UNIT SELL OUT') {
        // Excel/Calc recalculates the visible summaries on open and on edits.
        $writer->setPreCalculateFormulas(false)->setForceFullCalc(true);
    }
    $writer->save($stream);
    rewind($stream);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Template_'.$section.'_'.$period.'.xlsx"');
    header('Cache-Control: no-store');
    fpassthru($stream);
    fclose($stream);
    $book->disconnectWorksheets();
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>$error->getMessage()]);
} catch (Throwable $error) {
    error_log('Input template: '.$error->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>'Template belum dapat dibuat. Periksa koneksi database agar data tersimpan dapat dimuat.']);
}
