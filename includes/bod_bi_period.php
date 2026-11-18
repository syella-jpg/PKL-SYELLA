<?php
/** Apply the report period to BOD labels and YTD only; Full Year stays Jan–Dec. */
function applyBodBiPeriod(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,string $kind,string $period): void {
 if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$m))throw new InvalidArgumentException('Periode BOD tidak valid.');
 $year=(int)$m[1];$month=(int)$m[2];$months=['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];$label=$months[$month-1];
 foreach($sheet->getCellCollection()->getCoordinates() as $address){$cell=$sheet->getCell($address);$v=$cell->getValue();if(!is_string($v)||$cell->isFormula())continue;
  $v=preg_replace_callback('/\b202[56]\b/',static fn($match)=>(string)((int)$match[0]===2026?$year:$year-1),$v);
  $v=preg_replace('/S\/D\s+\S+/i','S/D '.$label,$v);$cell->setValueExplicit($v,\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
 }
 if($kind==='sdm'){
  $sheet->setCellValue('B6','s/d '.$label);$sheet->setCellValue('B12','s/d '.$label);
  $last=\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($month+3);
  foreach([7,8,13,14] as $r)$sheet->setCellValue('B'.$r,'=SUM(D'.$r.':'.$last.$r.')');
 }else{
  $sheet->setCellValue('D3','=E3');
  for($r=4;$r<=14;$r++)$sheet->setCellValue('D'.$r,'=E'.$r.'-E'.($r-1));
  $sheet->setCellValue('D15','=SUM(D3:D14)');
  $sheet->setCellValue('A16','KUMULATIF S/D '.$label.' (YTD)');
  foreach(['B','D'] as $c)$sheet->setCellValue($c.'16','=SUM('.$c.'3:'.$c.($month+2).')');
 }
}
