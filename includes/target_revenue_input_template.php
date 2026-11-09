<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function targetRevenueMonths(): array {
    return ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'];
}
function ensureTargetRevenueInputStore($conn): void {
    if (!$conn->query('CREATE TABLE IF NOT EXISTS target_revenue_inputs (report_year SMALLINT UNSIGNED PRIMARY KEY, payload LONGBLOB NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4')) throw new RuntimeException('Penyimpanan rincian Target Revenue gagal disiapkan.');
}
function loadTargetRevenueInputHistory($conn, string $period): array {
    if (substr($period,5,2)==='01') return [];
    ensureTargetRevenueInputStore($conn);
    $year=(int)substr($period,0,4);
    $stmt=$conn->prepare('SELECT payload FROM target_revenue_inputs WHERE report_year=?');
    $stmt->bind_param('i',$year);$stmt->execute();$record=$stmt->get_result()->fetch_assoc();$stmt->close();
    if (empty($record['payload'])) return [];
    $json=gzdecode($record['payload']);
    if ($json===false) throw new RuntimeException('Rincian Target Revenue tersimpan tidak valid.');
    return json_decode($json,true,512,JSON_THROW_ON_ERROR);
}
function buildTargetRevenueInputTemplate(string $period, array $history=[]): Spreadsheet {
    if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$match)) throw new InvalidArgumentException('Periode Target Revenue tidak valid.');
    $year=(int)$match[1];$month=(int)$match[2];
    if ($month===1) $history=[];
    $book=new Spreadsheet();$book->removeSheetByIndex(0);
    $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
    foreach (range(1,6) as $gb) {
        $sheet=new Worksheet($book,'GB '.$gb);$book->addSheet($sheet);
        $sheet->setCellValue('A1','REKAP REVENUE PER PRODUCT TAHUN '.$year)->mergeCells('A1:P1');
        $sheet->setCellValue('A2','TARGET AWAL — Rp (000)')->mergeCells('A2:P2');
        $sheet->setCellValue('A3','GB '.$gb);
        $sheet->setCellValue('A4','Isi target bulanan per produk. Baris produk boleh ditambah atau dihapus; pertahankan judul dan subtotal.')->mergeCells('A4:P4');
        $r=6;$totals=[];
        foreach (['A','B','C','D','NON TARGET'] as $category) {
            $label=$category==='NON TARGET'?$category:'KATEGORI '.$category;
            $sheet->setCellValue('A'.$r,'RINCIAN '.$label)->mergeCells('A'.$r.':P'.$r);
            $sheet->getStyle('A'.$r.':P'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF335C56');
            $sheet->getStyle('A'.$r.':P'.$r)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $r++;$header=$r;
            foreach (array_merge(['NO.','PRODUK'],targetRevenueMonths(),['YTD '.targetRevenueMonths()[$month-1],'TOTAL FY']) as $c=>$value) $sheet->setCellValue([$c+1,$r],$value);
            $sheet->getStyle('A'.$r.':P'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF283448');
            $sheet->getStyle('A'.$r.':P'.$r)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $products=$history[$gb][$category]??[];
            $count=$month===1?50:count($products);$start=++$r;
            for ($i=0;$i<$count;$i++,$r++) {
                $sheet->setCellValue('A'.$r,$i+1);
                if (isset($products[$i])) {
                    $sheet->setCellValueExplicit('B'.$r,$products[$i]['name'],\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    foreach($products[$i]['months'] as $m=>$v) if($v!==null) $sheet->setCellValue([$m+3,$r],$v);
                }
                $endYtd=Coordinate::stringFromColumnIndex($month+2);
                $sheet->setCellValue('O'.$r,'=SUM(C'.$r.':'.$endYtd.$r.')');
                $sheet->setCellValue('P'.$r,'=SUM(C'.$r.':N'.$r.')');
            }
            if ($count>0) $sheet->getStyle('B'.$start.':N'.($r-1))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF3C4');
            $sheet->setCellValue('B'.$r,$label);$totals[$category]=$r;
            for($c=3;$c<=16;$c++) {$col=Coordinate::stringFromColumnIndex($c);$sheet->setCellValue([$c,$r],$count>0?'=SUM('.$col.$start.':'.$col.($r-1).')':'=0');}
            $sheet->getStyle('A'.$r.':P'.$r)->getFont()->setBold(true);$r+=3;
        }
        foreach(['TOTAL EXISTING','TOTAL NEW PRODUCT','TOTAL'] as $index=>$label) {
            $sheet->setCellValue('B'.$r,$label);
            for($c=3;$c<=16;$c++) {$col=Coordinate::stringFromColumnIndex($c);$formula=$index===0?'='.$col.$totals['A'].'+'.$col.$totals['B'].'+'.$col.$totals['C']:($index===1?'='.$col.$totals['D']:'='.$col.($r-2).'+'.$col.($r-1));$sheet->setCellValue([$c,$r],$formula);}
            $r++;
        }
        $sheet->getStyle('C7:P'.$r)->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);0');
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(7);$sheet->getColumnDimension('B')->setWidth(38);
        foreach(range('C','P') as $col) $sheet->getColumnDimension($col)->setWidth(17);
        $sheet->freezePane('C8');$sheet->getSheetView()->setZoomScale(80);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
    }
    $meta=new Worksheet($book,'_TEMPLATE');$book->addSheet($meta);
    $meta->setCellValue('A1','KONIMEX_TARGET_REVENUE_V1')->setCellValue('A2','TARGET REVENUE')->setCellValue('A3',$year)->setCellValue('A4',$period);
    $meta->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);$book->setActiveSheetIndex(0);
    return $book;
}
/** Discover category boundaries and month columns from labels, never row offsets or cached subtotals. */
function parseTargetRevenueInputTemplate(Spreadsheet $book, string $period): array {
    if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$match)) throw new InvalidArgumentException('Periode tidak valid.');
    $meta=$book->getSheetByName('_TEMPLATE');
    if (!$meta || $meta->getCell('A1')->getValue()!=='KONIMEX_TARGET_REVENUE_V1' || (int)$meta->getCell('A3')->getValue()!==(int)$match[1]) throw new InvalidArgumentException('Template atau tahun Target Revenue tidak sesuai periode upload.');
    $details=[];$rows=[];$hasValue=false;
    foreach(range(1,6) as $gb) {
        $sheet=$book->getSheetByName('GB '.$gb);
        if (!$sheet) throw new InvalidArgumentException('Sheet GB '.$gb.' tidak ditemukan.');
        $active=null;$columns=null;$products=[];$found=[];
        foreach($sheet->getRowIterator() as $row) {
            $labels=[];
            foreach($row->getCellIterator() as $cell) {
                $value=$cell->getValue();
                if(is_string($value) && !str_starts_with($value,'=')) $labels[$cell->getColumn()]=strtoupper(trim($value));
            }
            $titles=array_filter($labels,fn($v)=>preg_match('/^RINCIAN (KATEGORI [ABCD]|NON TARGET)$/D',$v));
            if($titles) {
                if($active!==null) throw new InvalidArgumentException('Subtotal kategori GB '.$gb.' tidak ditemukan.');
                $title=reset($titles);$active=str_replace(['RINCIAN ','KATEGORI '],'',$title);
                if(isset($found[$active])) throw new InvalidArgumentException('Kategori GB '.$gb.' berulang.');
                $columns=null;$products=[];continue;
            }
            if($active===null) continue;
            if(in_array('PRODUK',$labels,true)) {
                $columns=['name'=>array_search('PRODUK',$labels,true),'months'=>[]];
                foreach(targetRevenueMonths() as $name) {$c=array_search($name,$labels,true);if($c===false) throw new InvalidArgumentException('Kolom bulan GB '.$gb.' tidak lengkap.');$columns['months'][]=$c;}
                continue;
            }
            $subtotal=$active==='NON TARGET'?$active:'KATEGORI '.$active;
            if(in_array($subtotal,$labels,true)) {
                if(!$columns) throw new InvalidArgumentException('Header produk GB '.$gb.' tidak ditemukan.');
                $sums=array_fill(0,12,0.0);foreach($products as $p)foreach($p['months'] as $m=>$v)$sums[$m]+=$v??0;
                $rows[]=['code'=>$active==='NON TARGET'?'nonTarget'.$gb:'category'.$gb.$active,'months'=>$sums];
                $details[$gb][$active]=$products;$found[$active]=true;$active=null;continue;
            }
            if(!$columns) continue;
            $r=$row->getRowIndex();$name=trim((string)$sheet->getCell($columns['name'].$r)->getValue());$months=[];$filled=false;
            foreach($columns['months'] as $col) {
                $cell=$sheet->getCell($col.$r);$raw=$cell->getValue();
                if($raw===null || $raw==='') {$months[]=null;continue;}
                try {$v=$cell->getCalculatedValue();}catch(Throwable $e){throw new InvalidArgumentException('Rumus Target Revenue tidak dapat dihitung pada '.$sheet->getTitle().'!'.$col.$r);}
                if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0) throw new InvalidArgumentException('Isi angka nol atau positif pada '.$sheet->getTitle().'!'.$col.$r.'.');
                $months[]=(float)$v;$filled=true;
            }
            if($name==='' && !$filled) continue;
            if($name==='') throw new InvalidArgumentException('Nama produk belum diisi pada GB '.$gb.' baris '.$r.'.');
            $products[]=['name'=>$name,'months'=>$months];$hasValue=$hasValue||$filled;
        }
        if($active!==null || count($found)!==5) throw new InvalidArgumentException('Judul atau subtotal kategori GB '.$gb.' tidak lengkap.');
    }
    if(!$hasValue) throw new InvalidArgumentException('Template masih kosong. Isi target terlebih dahulu.');
    return ['year'=>(int)$match[1],'month'=>(int)$match[2],'rows'=>$rows,'details'=>$details];
}
