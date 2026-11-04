<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

function reportInputTemplateSpec(string $section, string $period): array
{
    if (!in_array($section, ['IB', 'MTM', 'BIAYA'], true) || !preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D', $period, $m)) {
        throw new InvalidArgumentException('Pilih bagian IB, MTM, atau BIAYA dan periode yang valid untuk mengunduh template.');
    }
    return [$section, (int)$m[1], (int)$m[2]];
}

/** Builds fresh labels and genuinely empty input cells; never copies uploaded data. */
function buildReportInputTemplate(string $section, string $period): Spreadsheet
{
    [, $year, $month] = reportInputTemplateSpec($section, $period);
    if ($section === 'BIAYA') {
        require_once __DIR__.'/biaya_input_template.php';
        return buildBiayaInputTemplate($period);
    }
    $months = ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
    $book = new Spreadsheet();
    $book->removeSheetByIndex(0);
    $specs = $section === 'MTM'
        ? [['MTM', 'MTM', ['Revenue (Rp)', 'Revenue Existing (Rp)', 'Revenue New Product (Rp)']]]
        : [['YFR - IBFM', 'IB FARMA — USD', ['SALES REVENUE (USD)', 'SALES REVENUE NEW BUSINESS PRODUCT (USD)']],
           ['IAP', 'IB FOOD — USD', ['SALES REVENUE (USD)', 'SALES REVENUE NEW BUSINESS PRODUCT (USD)']],
           ['HRU', 'HRU — USD', ['% REALIZATION OF COST VS BUDGET']]];
    $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
    foreach ($specs as [$name, $title, $metrics]) {
        $sheet = new Worksheet($book, $name);
        $book->addSheet($sheet);
        $first = $section === 'IB' ? 5 : 3;
        $last = Coordinate::stringFromColumnIndex($first + 11);
        $sheet->setCellValue('A1', $title . ' — TAHUN ' . $year);
        $sheet->mergeCells('A1:'.$last.'1');
        $sheet->setCellValue('A2', 'PERIODE LAPORAN: '.$months[$month-1].' '.$year);
        $sheet->mergeCells('A2:'.$last.'2');
        $sheet->setCellValue('A3', $section === 'IB'
            ? ''
            : 'Isi sel kuning dalam RUPIAH PENUH untuk setiap bulan (bukan kumulatif). Kosong = belum tersedia; 0 = benar-benar nol.');
        $sheet->mergeCells('A3:'.$last.'3');
        $sheet->setCellValue('A4', $section === 'MTM' ? 'INCREASE REVENUE' : 'FINANCIAL');
        $sheet->mergeCells('A4:'.$last.'4');
        $sheet->setCellValue('A5', $section === 'IB' ? 'No.' : 'Indikator');
        if ($section === 'IB') {
            $sheet->setCellValue('B5', 'OBJECTIVE');
            $sheet->setCellValue('C5', 'KPI');
        }
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($first-1).'5', 'Jenis');
        $headers = $section === 'IB'
            ? ['JAN','S/D FEB','S/D MAR','S/D APR','S/D MEI','S/D JUN','S/D JUL','S/D AGS','S/D SEP','S/D OKT','S/D NOV','S/D DES']
            : $months;
        foreach ($headers as $i => $header) $sheet->setCellValue([$first+$i,5], $header);
        foreach ($metrics as $i => $metric) {
            $height = $section === 'MTM' ? 3 : 2;
            $r = 6 + $i*($height+1);
            if ($section === 'IB') {
                $sheet->setCellValue('A'.$r, $i+1);
                $sheet->setCellValue('B'.$r, $name === 'HRU' ? 'COST EFFICIENCY & EFFECTIVENESS' : 'REVENUE GROWTH');
                $sheet->mergeCells('B'.$r.':B'.($r+1));
            }
            $labelCol = $section === 'IB' ? 'C' : 'A';
            $sheet->setCellValue($labelCol.$r, $metric);
            $sheet->mergeCells($labelCol.$r.':'.$labelCol.($r+$height-1));
            $typeCol = Coordinate::stringFromColumnIndex($first-1);
            if ($section === 'MTM') {
                $sheet->setCellValue($typeCol.$r, 'TARGET AWAL');
                $sheet->setCellValue($typeCol.($r+1), 'TARGET ROLLING');
                $sheet->setCellValue($typeCol.($r+2), 'AKTUAL');
            } else {
                $sheet->setCellValue($typeCol.$r, 'TARGET');
                $sheet->setCellValue($typeCol.($r+1), 'REALISASI');
            }
            $range = Coordinate::stringFromColumnIndex($first).$r.':'.$last.($r+$height-1);
            $sheet->getStyle($range)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF8DD');
            $sheet->getStyle($range)->getNumberFormat()->setFormatCode($section === 'MTM' ? '#,##0;[Red](#,##0);0' : '#,##0.00;[Red](#,##0.00);0');
            for ($row=$r;$row<=$r+$height-1;$row++) for ($col=$first;$col<$first+12;$col++) {
                // Styled empty cells carry no example numbers or default zeros.
                $cell = $sheet->getCell([$col,$row]);
                $validation = $cell->getDataValidation();
                $validation->setType(DataValidation::TYPE_DECIMAL)->setOperator(DataValidation::OPERATOR_BETWEEN)
                    ->setFormula1('-999999999999999')->setFormula2('999999999999999')->setAllowBlank(true)
                    ->setShowErrorMessage(true)->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setErrorTitle('Isi angka')->setError('Masukkan angka, atau biarkan kosong jika belum tersedia.');
            }
            $sheet->getRowDimension($r)->setRowHeight(27);
            $sheet->getRowDimension($r+1)->setRowHeight(27);
            if ($height===3) $sheet->getRowDimension($r+2)->setRowHeight(27);
        }
        if ($section === 'MTM') {
            for ($c=3;$c<=14;$c++) {
                $col = Coordinate::stringFromColumnIndex($c);
                foreach ([6=>[10,14],7=>[11,15],8=>[12,16]] as $totalRow=>[$existingRow,$newRow]) {
                    $sheet->setCellValue($col.$totalRow, '='.$col.$existingRow.'+'.$col.$newRow);
                }
            }
            $sheet->getStyle('C6:N8')->getFill()->getStartColor()->setARGB('FFEAF2FF');
            $sheet->getStyle('C6:N8')->getFont()->setBold(true);
            $sheet->setCellValue('A3', 'Isi sel kuning per bulan dalam Rupiah penuh. Revenue (Rp) dihitung otomatis dari Existing + New Product.');
        }
        $end = $section === 'MTM' ? 16 : 7+(count($metrics)-1)*3;
        $sheet->getStyle('A5:'.$last.$end)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE1E5EB');
        $sheet->getStyle('A5:'.$last.'5')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF29354A');
        $sheet->getStyle('A5:'.$last.'5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->setSize(18)->getColor()->setARGB('FF1F2937');
        $sheet->getStyle('A2:'.$last.'3')->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF64748B'));
        $sheet->getStyle('A3:'.$last.'3')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A4:'.$last.'4')->getFont()->setBold(true)->getColor()->setARGB('FFE31E24');
        $sheet->getStyle('A1:'.$last.$end)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A5:'.$last.$end)->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(36);
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->getRowDimension(3)->setRowHeight(36);
        $sheet->getRowDimension(4)->setRowHeight(28);
        $sheet->getRowDimension(5)->setRowHeight(32);
        $sheet->getColumnDimension('A')->setWidth($section === 'IB' ? 7 : 39);
        if ($section === 'IB') {
            $sheet->getColumnDimension('B')->setWidth(36);
            $sheet->getColumnDimension('C')->setWidth(52);
        }
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($first-1))->setWidth(15);
        for ($c=$first;$c<$first+12;$c++) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(16);
        $sheet->freezePane(Coordinate::stringFromColumnIndex($first).'6');
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(1);
        $sheet->getPageSetup()->setPrintArea('A1:'.$last.$end);
    }
    $meta = new Worksheet($book, '_TEMPLATE');
    $book->addSheet($meta);
    $meta->fromArray([['KONIMEX_INPUT_V1'],[$section],[$year],[$period]]);
    if ($section === 'MTM') $meta->setCellValue('A5','MTM_TARGETS_V2');
    $meta->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    $book->setActiveSheetIndex(0);
    return $book;
}

/** Only the new compact format is strict; legacy files retain their existing readers. */
function validateReportInputTemplate(Spreadsheet $book, string $section, string $period): void
{
    $meta = $book->getSheetByName('_TEMPLATE');
    if (!$meta || $meta->getCell('A1')->getValue() !== 'KONIMEX_INPUT_V1') return;
    if ($meta->getCell('A2')->getValue() !== $section) throw new InvalidArgumentException('Bagian template tidak sesuai. Pilih '.$meta->getCell('A2')->getValue().' pada halaman upload.');
    if ((int)$meta->getCell('A3')->getValue() !== (int)substr($period,0,4)) throw new InvalidArgumentException('Tahun template tidak sesuai dengan periode upload. Unduh template untuk tahun yang dipilih.');
    $specs = $section === 'MTM' ? ['MTM'=>[3,[6,7,9,10,12,13]]] : ['YFR - IBFM'=>[4,[6,7,9,10]],'IAP'=>[4,[6,7,9,10]],'HRU'=>[4,[6,7]]];
    foreach ($specs as $name => [$first,$rows]) {
        $sheet = $book->getSheetByName($name);
        if (!$sheet) throw new InvalidArgumentException('Sheet '.$name.' tidak ditemukan. Gunakan susunan template yang diunduh.');
        if ($section === 'MTM' && $meta->getCell('A5')->getValue()==='MTM_TARGETS_V2') $rows=[6,7,8,10,11,12,14,15,16];
        if ($section === 'IB' && $sheet->getCell('B5')->getValue() === 'OBJECTIVE') $first = 5;
        foreach ($rows as $r) for ($c=$first;$c<$first+12;$c++) {
            $cell = $sheet->getCell([$c,$r]);
            $value = $cell->getCalculatedValue();
            if ($value === null || $value === '') continue;
            if (!is_numeric($value) || !is_finite((float)$value)) throw new InvalidArgumentException('Isi angka yang valid di sheet '.$name.', sel '.$cell->getCoordinate().'.');
        }
    }
}
