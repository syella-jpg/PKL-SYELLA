<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

function buildMnjInputTemplate(string $period): Spreadsheet {
    if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$p)) throw new InvalidArgumentException('Periode MNJ tidak valid.');
    $book=new Spreadsheet();$s=$book->getActiveSheet();$s->setTitle('MNJ');
    $months=['JAN','FEB','MAR','APR','MEI','JUN','JUL','AGS','SEP','OKT','NOV','DES'];$month=(int)$p[2];
    foreach ([(int)$p[1]] as $index=>$year) {
        $top=1+$index*19;
        $s->setCellValue('A'.$top,'DISTRIBUTOR CONTRIBUTION MARGIN '.$year)->mergeCells('A'.$top.':P'.$top);
        $s->setCellValue('A'.($top+1),'(000.000)');
        $s->fromArray([['PARAMETER','JENIS','YTD '.$months[$month-1],...$months,'FULL YEAR']],null,'A'.($top+3));
        foreach(['DCM','REVENUE','BIAYA'] as $i=>$label){$r=$top+4+$i*4;
            $s->setCellValue('A'.$r,$label)->mergeCells('A'.$r.':A'.($r+3));
            foreach(['TARGET AWAL','TARGET ROLLING','AKTUAL','%'] as $j=>$kind){$row=$r+$j;$s->setCellValue('B'.$row,$kind);
                if($j<3){$end=Coordinate::stringFromColumnIndex(3+$month);$s->setCellValue('C'.$row,'=SUM(D'.$row.':'.$end.$row.')');$s->setCellValue('P'.$row,'=SUM(D'.$row.':O'.$row.')');}
                else foreach(range(3,16) as $c){$col=Coordinate::stringFromColumnIndex($c);$s->setCellValue($col.$row,'='.$col.($r+2).'/'.$col.($r+1));$s->getStyle($col.$row)->getNumberFormat()->setFormatCode('0.0%');}
            }
            $s->getStyle('D'.$r.':O'.($r+2))->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFF2CC');
        }
        $s->getStyle('A'.$top.':P'.$top)->getFont()->setBold(true)->setSize(14);
        $s->getStyle('A'.($top+3).':P'.($top+3))->getFill()->setFillType('solid')->getStartColor()->setARGB('FF263449');
        $s->getStyle('A'.($top+3).':P'.($top+3))->getFont()->getColor()->setARGB('FFFFFFFF');
        $s->getStyle('A'.($top+3).':P'.($top+15))->getBorders()->getAllBorders()->setBorderStyle('thin');
    }
    $s->getStyle('C5:P16')->getNumberFormat()->setFormatCode('#,##0');
    foreach([8,12,16] as $r)$s->getStyle('C'.$r.':P'.$r)->getNumberFormat()->setFormatCode('0.0%');
    $s->getColumnDimension('A')->setWidth(24);$s->getColumnDimension('B')->setWidth(22);foreach(range(3,16) as $c)$s->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(17);
    $s->freezePane('D5');
    $meta=$book->createSheet();$meta->setTitle('_TEMPLATE');$meta->fromArray([['KONIMEX_MNJ_V1'],[$period]]);$meta->setSheetState('veryHidden');$book->setActiveSheetIndex(0);return $book;
}
function readMnjInput(string $base64,string $period): Spreadsheet {
    if(strlen($base64)>70*1024*1024 || !($bytes=base64_decode($base64,true)))throw new InvalidArgumentException('File MNJ tidak valid.');
    $temp=tempnam(sys_get_temp_dir(),'mnj-');try {file_put_contents($temp,$bytes);$type=IOFactory::identify($temp);if(!in_array($type,['Xlsx','Ods'],true))throw new InvalidArgumentException('Gunakan XLSX atau ODS.');$book=IOFactory::load($temp);}finally{unlink($temp);}
    $meta=$book->getSheetByName('_TEMPLATE');$s=$book->getSheetByName('MNJ');
    if(!$s || !$meta || $meta->getCell('A1')->getValue()!=='KONIMEX_MNJ_V1' || $meta->getCell('A2')->getValue()!==$period)throw new InvalidArgumentException('Gunakan template MNJ dengan periode yang sesuai.');
    foreach([5,6,7,9,10,11,13,14,15] as $r)foreach(range(4,15) as $c){$cell=$s->getCell([$c,$r]);$v=$cell->getValue();if($v===null||$v==='')continue;if($cell->isFormula())$v=$cell->getCalculatedValue();if(!is_numeric($v)||!is_finite((float)$v))throw new InvalidArgumentException('Isi angka yang valid pada '.$cell->getCoordinate().'.');}
    return $book;
}
function findMnjInput($conn,string $period): ?array {
    try{$q=$conn->prepare('SELECT report_period,base64 FROM mnj_input_files WHERE report_period BETWEEN ? AND ? ORDER BY CASE WHEN report_period <= ? THEN 0 ELSE 1 END, CASE WHEN report_period <= ? THEN report_period END DESC, report_period ASC LIMIT 1');$first=substr($period,0,4).'-01';$last=substr($period,0,4).'-12';$q->bind_param('ssss',$first,$last,$period,$period);$q->execute();$r=$q->get_result()->fetch_assoc();$q->close();return $r?:null;}catch(mysqli_sql_exception $e){if($e->getCode()===1146)return null;throw $e;}
}
