<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/upb_distr_input.php';
function flexibleCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
$b=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$b->getActiveSheet();$s->fromArray([['GB 1'],['NAMA PRODUK'],['Dummy A',null,10,null,100],['Dummy B',null,20,null,200],['SUB TOTAL GB 1']]);
foreach([3=>'C',5=>'E'] as $c=>$letter){$f=distrFlexibleSubtotal('GB 1',$letter);$excel=\PhpOffice\PhpSpreadsheet\Reader\Ods\FormulaTranslator::convertToExcelFormulaValue('='.$f);$s->setCellValue([$c,5],$excel);}
flexibleCheck((float)$s->getCell('E5')->getCalculatedValue()===300.0,'Initial subtotal');
$s->insertNewRowBefore(5,1);$s->setCellValue('A5','Inserted product');$s->setCellValue('C5',30);$s->setCellValue('E5',300);
flexibleCheck((float)$s->getCell('E6')->getCalculatedValue()===600.0,'Insert immediately before subtotal automatically included');
$s->removeRow(3,1);\PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($b)->clearCalculationCache();flexibleCheck((float)$s->getCell('E5')->getCalculatedValue()===500.0,'Delete first product, no broken start reference');
$s->removeRow(3,2);\PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($b)->clearCalculationCache();flexibleCheck((float)$s->getCell('E3')->getCalculatedValue()===0.0,'Delete all products yields zero');
flexibleCheck(distrIsStandardSubtotal('of:=SUM([.E3:.E4])','GB 1','E'),'Old generated SUM upgraded');
flexibleCheck(!distrIsStandardSubtotal('of:=SUM([.E3:.E4])*2','GB 1','E'),'Custom calculation preserved');
$b->disconnectWorksheets();echo "PASS dummy UPB DISTR subtotal: insertion at bottom, first/all-product deletion, old SUM upgrade and custom formula preservation. No source files or DB.\n";
