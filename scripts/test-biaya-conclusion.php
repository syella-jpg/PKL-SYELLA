<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/biaya_template_history.php';
require __DIR__.'/../includes/biaya_workbook.php';
function checkConclusion($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach([2025,2026,2027] as $year){
 $records=[];
 foreach(range(1,6) as $gb)foreach(['target','actual'] as $type)foreach(['EXIS','D'] as $i=>$category){
  $records[]=['report_year'=>$year,'report_period'=>$year.'-10','jenis'=>$type,'gb'=>$gb,'kategori'=>$category,'nomor_produk'=>$i+1,'nama_produk'=>'Dummy '.$gb.' '.$category,'jan'=>($year===2026&&$gb===5&&$type==='target'?1000:1)*($i+1)*10,'feb'=>0,'mar'=>null];
 }
 $b=buildBiayaTemplateWithHistory($year.'-11',$records);$s=$b->getSheetByName('KESIMPULAN');checkConclusion($s!==null,'Summary exists');
 checkConclusion((float)$s->getCell('C6')->getCalculatedValue()===10.0,'Existing target');
 checkConclusion((float)$s->getCell('C7')->getCalculatedValue()===20.0,'NP target');
 checkConclusion((float)$s->getCell('C8')->getCalculatedValue()===30.0,'GB total');
 checkConclusion((float)$s->getCell('D6')->getCalculatedValue()===0.0,'Explicit zero retained');
 checkConclusion($s->getCell('E6')->getCalculatedValue()==='','Missing month blank');
 checkConclusion((float)$s->getCell('O6')->getCalculatedValue()===10.0,'YTD');
 $target=$b->getSheetByName($year===2025?'TARGET ROLLING':'TARGET');$l=locateBiayaInputTables($target,true)[0];
 $target->insertNewRowBefore($l['total'],45);
 for($i=0;$i<45;$i++)foreach(['A'=>1,'B'=>'D','C'=>$i+3,'D'=>'Dummy extra '.$i,'E'=>2] as $col=>$v)$target->setCellValue($col.($l['total']+$i),$v);
 // Rebuild using moved table boundaries, rather than any original row numbers.
 refreshBiayaConclusion($b,$year.'-11');$s=$b->getSheetByName('KESIMPULAN');
 \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($b)->clearCalculationCache();
 checkConclusion((float)$s->getCell('C7')->getCalculatedValue()===110.0,'Added 45 NP rows included');
 $target->removeRow($l['total'],10);refreshBiayaConclusion($b,$year.'-11');$s=$b->getSheetByName('KESIMPULAN');
 \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($b)->clearCalculationCache();
 checkConclusion((float)$s->getCell('C7')->getCalculatedValue()===90.0,'Deleted rows excluded');
 $rows=parseBiayaProducts($b,$year);checkConclusion(count(array_filter($rows,fn($r)=>$r['type']==='target'&&$r['gb']===1))===37,'Summary never imported as products');
 foreach(['Xlsx','Ods'] as $format){$path=tempnam('/tmp','dummy-biaya-summary-');try{$writer=\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($b,$format);$writer->setPreCalculateFormulas(false);$writer->save($path);$read=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);checkConclusion($read->getSheetByName('KESIMPULAN')!==null,'Summary export');checkConclusion(parseBiayaProducts($read,$year)===$rows,'Export/upload preserves product rows');$read->disconnectWorksheets();}finally{unlink($path);}}
 $b->disconnectWorksheets();
}
echo "PASS dummy BIAYA summary: stored-record hydration, per-GB Existing/NP/total, zeros/blanks, add/remove >30 rows, 2025/2026/2027, GB5 units and XLSX/ODS roundtrip. No database or user files accessed.\n";
