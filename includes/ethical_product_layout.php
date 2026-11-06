<?php
/** Locate product tables using their titles, month headers, metric labels and subtotals. */
function locateEthicalProductTables(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,int $year,bool $withReal=true,bool $priced=true): array {
    $norm=static fn($value)=>strtoupper(trim(preg_replace('/\s+/u',' ',str_replace("\xc2\xa0",' ',(string)$value))));
    $labels=ethicalProductLabels($year);$titles=[];$rows=[];
    foreach($sheet->getCellCollection()->getCoordinates() as $address){$value=$sheet->getCell($address)->getValue();if(!is_string($value)||str_starts_with($value,'='))continue;$label=$norm($value);if($label==='')continue;[$column,$row]=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::coordinateFromString($address);$column=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($column);$rows[$row][$column]=$label;
        foreach($labels as $kind=>$name)if($label==='PRODUK '.$name){if(isset($titles[$kind]))throw new InvalidArgumentException('Judul produk '.$name.' berulang pada ETHICAL.');$titles[$kind]=$row;}
    }
    ksort($rows);$aliases=[['JAN','JANUARI'],['FEB','FEBRUARI'],['MAR','MARET'],['APR','APRIL'],['MEI','MAY'],['JUN','JUNI'],['JUL','JULI'],['AGS','AGT','AGU','AUG','AGUSTUS'],['SEP','SEPT','SEPTEMBER'],['OKT','OCT','OKTOBER'],['NOV','NOVEMBER'],['DES','DEC','DESEMBER']];$result=[];
    foreach($labels as $kind=>$name){$title=$titles[$kind]??null;if($title===null)throw new InvalidArgumentException('Judul PRODUK '.$name.' tidak ditemukan pada ETHICAL.');$next=PHP_INT_MAX;foreach($titles as $other)if($other>$title)$next=min($next,$other);$total=null;
        foreach($rows as $row=>$values)if($row>$title&&$row<$next&&in_array('TOTAL '.$name,$values,true)){$total=$row;break;}
        if($total===null)throw new InvalidArgumentException('Subtotal TOTAL '.$name.' tidak ditemukan sebelum kelompok produk berikutnya.');
        $candidates=[];foreach($rows as $header=>$values)if($header>$title&&$header<$total&&in_array('NAMA PRODUK',$values,true))$candidates[$header]=$values;
        if(count($candidates)!==1)throw new InvalidArgumentException('Header NAMA PRODUK kelompok '.$name.' tidak lengkap atau berulang.');$header=array_key_first($candidates);$values=$candidates[$header];$columns=['name'=>array_search('NAMA PRODUK',$values,true)];foreach(['KODE PRODUK'=>'code','KATEGORI'=>'category'] as $label=>$key){$column=array_search($label,$values,true);if($column!==false)$columns[$key]=$column;}
        $months=[];$endColumn=null;foreach($values as $column=>$label){foreach($aliases as $month=>$names)if(in_array($label,$names,true)){if(isset($months[$month]))throw new InvalidArgumentException('Bulan berulang pada kelompok '.$name.'.');$months[$month]=$column;}if(preg_match('/^YTD\b/',$label))$endColumn=$column;}
        if(count($months)!==12)throw new InvalidArgumentException('Header Januari–Desember kelompok '.$name.' belum lengkap.');ksort($months);$starts=array_values($months);$firstMonth=min($starts);if($priced){$prices=array_filter($values,static fn($label,$column)=>$column<$firstMonth&&$label==='HNA',ARRAY_FILTER_USE_BOTH);if(count($prices)!==1)throw new InvalidArgumentException('Kolom HNA produk kelompok '.$name.' tidak ditemukan.');$columns['price']=array_key_first($prices);}
        $metricEnd=$header;$required=$withReal?['unit','hna','actual_unit','actual_hna']:['unit','hna'];
        foreach($months as $month=>$start){$end=$month<11?$months[$month+1]-1:($endColumn!==null?$endColumn-1:max($values));if($end<$start)throw new InvalidArgumentException('Urutan kolom bulan ETHICAL tidak sesuai.');$metrics=[];
            foreach($rows as $metricRow=>$metricLabels){if($metricRow<=$header||$metricRow>=$total)continue;
                foreach($metricLabels as $column=>$label){if($column<$start||$column>$end||!in_array($label,['UNIT','HNA'],true))continue;$type='TARGET';
                    if($withReal){$type=null;for($typeRow=$metricRow-1;$typeRow>$header;$typeRow--){$bestColumn=-1;foreach(($rows[$typeRow]??[]) as $typeColumn=>$typeLabel)if($typeColumn>=$start&&$typeColumn<=$column&&$typeColumn>$bestColumn&&in_array($typeLabel,['TARGET','REAL','REALISASI'],true)){$type=$typeLabel;$bestColumn=$typeColumn;}if($type!==null)break;}if($type===null)continue;}
                    $key=($type==='TARGET'?'':'actual_').strtolower($label);if(isset($metrics[$key]))throw new InvalidArgumentException('Header UNIT/HNA berulang di '.$name.'.');$metrics[$key]=$column;$metricEnd=max($metricEnd,$metricRow);
                }
                if(count($metrics)===count($required))break;
            }
            foreach($required as $key)if(!isset($metrics[$key]))throw new InvalidArgumentException('Header TARGET/REAL UNIT/HNA kelompok '.$name.' belum lengkap.');$columns['months'][$month]=$metrics;
        }
        $result[$kind]=['title'=>$title,'header'=>$header,'first'=>$metricEnd+1,'last'=>$total-1,'total'=>$total,'columns'=>$columns];
    }return $result;
}
