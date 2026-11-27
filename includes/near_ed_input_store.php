<?php
require_once __DIR__.'/near_ed_input_template.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

function nearEdPeriod(string $period): void {
    if(!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$period))throw new InvalidArgumentException('Periode NEAR ED tidak valid.');
}
function readNearEdInput(string $bytes): array {
    if(strlen($bytes)>50*1024*1024)throw new InvalidArgumentException('File maksimal 50 MB.');
    $path=tempnam(sys_get_temp_dir(),'near-ed-');
    try {
        file_put_contents($path,$bytes);$book=IOFactory::load($path);$groups=[];
        foreach(nearEdGroups() as $group){
            $s=$book->getSheetByName($group);if(!$s)throw new InvalidArgumentException('Sheet '.$group.' tidak ditemukan. Gunakan template NEAR ED.');
            $header=null;
            foreach($s->getRowIterator() as $row){$r=$row->getRowIndex();if(strtoupper(trim((string)$s->getCell('B'.$r)->getValue()))==='NAMA PRODUK'){$header=$r;break;}}
            if($header===null)throw new InvalidArgumentException('Header Nama Produk pada '.$group.' tidak ditemukan.');
            $groups[$group]=[];
            for($r=$header+1;$r<=$s->getHighestDataRow();$r++){
                if(strtoupper(trim((string)$s->getCell('A'.$r)->getValue()))==='TOTAL')break;
                $name=trim((string)$s->getCell('B'.$r)->getValue());if($name==='')continue;
                if($s->getCell('B'.$r)->getDataType()===DataType::TYPE_FORMULA)throw new InvalidArgumentException('Nama Produk harus berupa teks pada '.$group.'.');
                $values=[];
                foreach(['F','I','J','K','M','N','P','Q','R','S'] as $col){
                    $cell=$s->getCell($col.$r);$value=$cell->getCalculatedValue();
                    if($value===null||$value==='')$value=0;
                    if(!is_numeric($value)||!is_finite((float)$value))throw new InvalidArgumentException('Nilai stok tidak valid pada '.$group.' '.$col.$r.'.');
                    $values[$col]=(float)$value;
                }
                $groups[$group][]=['name'=>$name,'values'=>$values];
            }
        }
        return $groups;
    } catch(InvalidArgumentException $e){throw $e;}
    catch(Throwable $e){throw new InvalidArgumentException('File NEAR ED tidak dapat dibaca. Gunakan template XLSX atau ODS yang benar.');}
    finally{if(isset($book))$book->disconnectWorksheets();@unlink($path);}
}
function ensureNearEdStore(mysqli $db): void {
    $db->query(file_get_contents(__DIR__.'/../database/migrations/2026-11-27-near-ed-input.sql'));
}
function saveNearEdInput(mysqli $db,string $period,string $bytes,string $filename): void {
    nearEdPeriod($period);readNearEdInput($bytes);ensureNearEdStore($db);
    $filename=mb_substr(basename($filename),0,255);$base64=base64_encode($bytes);
    $q=$db->prepare('INSERT INTO `near ED` (report_period,filename,base64) VALUES (?,?,?) ON DUPLICATE KEY UPDATE filename=VALUES(filename),base64=VALUES(base64)');
    $q->bind_param('sss',$period,$filename,$base64);$q->execute();$q->close();
}
function findNearEdInput(mysqli $db,string $period): ?array {
    nearEdPeriod($period);
    try{$q=$db->prepare('SELECT report_period,filename,base64 FROM `near ED` WHERE report_period=?');$q->bind_param('s',$period);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();return $row?:null;}
    catch(mysqli_sql_exception $e){if((int)$e->getCode()===1146)return null;throw $e;}
}
/** Export calculated values only: the report needs no hidden input sheets or cached Excel calculations. */
function buildNearEdReport(string $period,array $groups): \PhpOffice\PhpSpreadsheet\Spreadsheet {
    $book=buildNearEdInputTemplate($period,1);$summary=$book->getSheetByName('NEAR ED');$top=$book->getSheetByName('TOP 10 NEAR ED');
    foreach([$summary,$top] as $s)foreach($s->getCoordinates() as $coordinate){$cell=$s->getCell($coordinate);if($cell->getDataType()===DataType::TYPE_FORMULA)$cell->setValue(null);}
    $row=5;$block=4;$grandUnits=0;$grandRp=0;
    foreach(nearEdGroups() as $group){
        $products=$groups[$group]??[];$totalUnits=0;$totalRp=0;
        foreach($products as $p){$totalUnits+=$p['values']['F'];$totalRp+=$p['values']['I'];}
        foreach(nearEdBuckets() as $bucket=>[$label,$units,$rp]){
            $u=0;$v=0;$ranked=[];
            foreach($products as $index=>$p){$u+=$p['values'][$units];$v+=$p['values'][$rp];if($p['values'][$rp]>0)$ranked[]=['name'=>$p['name'],'value'=>$p['values'][$rp],'index'=>$index];}
            $summary->setCellValue('C'.$row,$u);$summary->setCellValue('E'.$row,$v);
            $summary->setCellValue('D'.$row,$totalUnits!=0&&$u!=0?$u/$totalUnits:' - ');$summary->setCellValue('F'.$row,$totalRp!=0&&$v!=0?$v/$totalRp:' - ');
            usort($ranked,static function($a,$b){return ($b['value']<=>$a['value'])?:($a['index']<=>$b['index']);});
            $col=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($bucket+2);
            for($i=0;$i<10;$i++)$top->setCellValueExplicit($col.($block+2+$i),$ranked[$i]['name']??'',DataType::TYPE_STRING);
            $row++;
        }
        $summary->setCellValue('C'.$row,$totalUnits);$summary->setCellValue('E'.$row,$totalRp);$summary->setCellValue('D'.$row,$totalUnits!=0?1:' - ');$summary->setCellValue('F'.$row,$totalRp!=0?1:' - ');
        $grandUnits+=$totalUnits;$grandRp+=$totalRp;$row+=2;$block+=15;
    }
    $summary->setCellValue('C'.$row,$grandUnits);$summary->setCellValue('E'.$row,$grandRp);
    foreach(nearEdGroups() as $group)$book->removeSheetByIndex($book->getIndex($book->getSheetByName($group)));
    $book->removeSheetByIndex($book->getIndex($book->getSheetByName('_TEMPLATE')));styleNearEdReport($book,$period);$book->setActiveSheetIndex(0);return $book;
}

/** Consistent report tables; kept separate from the editable raw template. */
function styleNearEdReport(\PhpOffice\PhpSpreadsheet\Spreadsheet $book,string $period): void {
    $summary=$book->getSheetByName('NEAR ED');$top=$book->getSheetByName('TOP 10 NEAR ED');
    foreach([$summary,$top] as $s){
        $end=$s===$summary?'F':'E';
        $s->getStyle('A1:'.$end.'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF405B6A');
        $s->getStyle('A1:'.$end.'1')->getFont()->setSize(15)->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $s->getStyle('A1:'.$end.'1')->getAlignment()->setVertical('center');$s->getRowDimension(1)->setRowHeight(38);
        $s->getStyle('A2:'.$end.'2')->getFont()->setSize(10)->getColor()->setARGB('FF64748B');
        $s->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1,4);
    }
    $summary->setCellValue('A2','Periode '.$period.' | Ringkasan stok per kelompok dan rentang umur stok.')->mergeCells('A2:F2');
    $summary->getRowDimension(2)->setRowHeight(28);$summary->getRowDimension(4)->setRowHeight(30);
    foreach(nearEdGroups() as $index=>$group){
        $start=5+$index*6;$total=$start+4;
        for($r=$start;$r<$total;$r++){
            $summary->getRowDimension($r)->setRowHeight(26);
            $summary->getStyle('A'.$r.':F'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB(($r-$start)%2===0?'FFF0F5F8':'FFFFFFFF');
        }
        $summary->getStyle('A'.$start.':A'.$total)->getFont()->setBold(true);
        $summary->getRowDimension($total)->setRowHeight(28);
        $summary->getStyle('A'.$total.':F'.$total)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFDCE7EB');
        $summary->getStyle('A'.$start.':F'.$total)->getAlignment()->setVertical('center');
        $summary->getRowDimension($total+1)->setRowHeight(10);
        $block=4+$index*15;
        $top->getStyle('A'.$block.':E'.$block)->getFill()->setFillType('solid')->getStartColor()->setARGB('FF405B6A');
        $top->getStyle('A'.$block.':E'.$block)->getFont()->setSize(12)->getColor()->setARGB('FFFFFFFF');
        $top->getRowDimension($block)->setRowHeight(30);$top->getRowDimension($block+1)->setRowHeight(30);
        $top->getStyle('A'.($block+1).':E'.($block+11))->getAlignment()->setVertical('center');
        $top->getStyle('A'.($block+2).':A'.($block+11))->getAlignment()->setHorizontal('center');
        $top->getStyle('A'.($block+2).':A'.($block+11))->getFont()->setBold(true)->getColor()->setARGB('FF405B6A');
        for($r=$block+2;$r<=$block+11;$r++)$top->getStyle('A'.$r.':E'.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB(($r-$block)%2===0?'FFF0F5F8':'FFFFFFFF');
    }
    $summary->getStyle('A47:F47')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF405B6A');
    $summary->getStyle('A47:F47')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');$summary->getRowDimension(47)->setRowHeight(34);
    $summary->getColumnDimension('B')->setWidth(34);$summary->getColumnDimension('E')->setWidth(26);
    $summary->getStyle('C5:F47')->getAlignment()->setHorizontal('right');
    foreach(['D','F'] as $column)$summary->getStyle($column.'5:'.$column.'47')->getNumberFormat()->setFormatCode('0.00%');
    $summary->getPageSetup()->setPrintArea('A1:F47');$top->getPageSetup()->setPrintArea('A1:E105');
}
