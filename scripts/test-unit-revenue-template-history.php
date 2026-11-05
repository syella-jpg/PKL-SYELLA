<?php
// Synthetic records only; this test never connects to a database or reads raw files.
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/includes/unit_revenue_template_history.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

function checkUnitRevenueHistory($condition,string $message): void
{
    if(!$condition)throw new RuntimeException($message);
}
function rejectUnitRevenueHistory(callable $call,string $message): void
{
    try{$call();}catch(RuntimeException|InvalidArgumentException $error){return;}
    throw new RuntimeException($message);
}
$records=[];
for($i=0;$i<212;$i++)$records[]=[
    'gb'=>$i===211?'ET':'1','product_code'=>str_pad((string)($i+1),6,'0',STR_PAD_LEFT),
    'principal_product_code'=>'000'.$i,'product_name'=>'Synthetic product '.($i+1),'kategori'=>$i%2?'D':'ABC',
    'qty'=>[10,0,null,4,5,6,7,8,9,10,11,null],
    'rev'=>[100,0,null,40,50,60,70,80,90,100,110,null],
];
$history=['source_period'=>'2026-11','records'=>$records];
$book=buildUnitRevenueTemplateWithHistory('2026-12',$history);
$unit=$book->getSheetByName('UNIT');$revenue=$book->getSheetByName('REVENUE');
checkUnitRevenueHistory($unit->getCell('B6')->getValue()==='000001','Product code retains leading zeroes');
checkUnitRevenueHistory($unit->getCell('C6')->getValue()==='0000','Principal code retains leading zeroes');
checkUnitRevenueHistory($unit->getCell('E6')->getValue()==='ABC','Combined Existing category remains ABC');
checkUnitRevenueHistory($unit->getCell('F6')->getValue()===10.0,'Prior UNIT values restored');
checkUnitRevenueHistory($revenue->getCell('F6')->getValue()===100.0,'Prior REVENUE values restored');
checkUnitRevenueHistory($unit->getCell('G6')->getValue()===0.0&&$unit->getCell('H6')->getValue()===null,'Zeros remain distinct from missing data');
checkUnitRevenueHistory($unit->getCell('P6')->getValue()===11.0&&$unit->getCell('Q6')->getValue()===null,'November-to-December carry retains prior month and empty December');
checkUnitRevenueHistory($revenue->getCell('B6')->getCalculatedValue()==='000001','REVENUE identity references preserve leading zeroes');
checkUnitRevenueHistory($revenue->getCell('A217')->isFormula()&&$unit->getCell('D217')->getValue()==='Synthetic product 212','History beyond initial capacity expands both sheets');
checkUnitRevenueHistory($book->getSheetByName('_TEMPLATE')->getCell('A6')->getValue()==='2026-11','Source upload recorded in metadata');
$parsed=parseUnitRevenueInputTemplate($book,'2026-12');
$numbers=static fn($values)=>array_map(static fn($v)=>$v===null?null:(float)$v,$values);
checkUnitRevenueHistory(count($parsed)===215&&$parsed[0]['qty']===$numbers($records[0]['qty'])&&$parsed[0]['rev']===$numbers($records[0]['rev']),'Prefilled template can be uploaded without losing either measure');
$path=tempnam(sys_get_temp_dir(),'unit-revenue-history-');
try{
    IOFactory::createWriter($book,'Xlsx')->save($path);$loaded=IOFactory::load($path);
    $restored=parseUnitRevenueInputTemplate($loaded,'2026-12');
    checkUnitRevenueHistory($restored[0]['product_code']==='000001'&&$restored[0]['principal_product_code']==='0000','Download-upload cycle retains text identities');
    checkUnitRevenueHistory($restored[0]['qty'][1]===0.0&&$restored[0]['qty'][2]===null,'Download-upload cycle retains zero and blank');
    $loaded->disconnectWorksheets();
}finally{unlink($path);}
$book->disconnectWorksheets();
$oct=buildUnitRevenueTemplateWithHistory('2026-10',$history);
checkUnitRevenueHistory($oct->getSheetByName('UNIT')->getCell('O6')->getValue()===10.0,'October uses October content from earliest later upload');
foreach(['UNIT','REVENUE'] as $name)checkUnitRevenueHistory($oct->getSheetByName($name)->getCell('P6')->getValue()===null&&$oct->getSheetByName($name)->getCell('Q6')->getValue()===null,'Later snapshot does not expose November or December in October');
$oct->disconnectWorksheets();
foreach(['2026-01','2027-01','2027-02'] as $period){
    $blank=buildUnitRevenueTemplateWithHistory($period,$history);
    checkUnitRevenueHistory($blank->getSheetByName('UNIT')->getCell('A6')->getValue()===null&&$blank->getSheetByName('UNIT')->getCell('F6')->getValue()===null&&$blank->getSheetByName('REVENUE')->getCell('F6')->getValue()===null,'January reset and other-year isolation');
    $blank->disconnectWorksheets();
}
checkUnitRevenueHistory(selectUnitRevenueHistoryPeriod('2026-12',['2025-12','2026-09','2026-11','2027-01'])==='2026-11','Latest earlier snapshot selected within requested year');
checkUnitRevenueHistory(selectUnitRevenueHistoryPeriod('2026-10',['2026-11','2026-12'])==='2026-11','Earliest later snapshot selected if no prior upload');
checkUnitRevenueHistory(selectUnitRevenueHistoryPeriod('2026-10',['2026-09','2026-11'])==='2026-09','Earlier snapshot preferred over later upload');
checkUnitRevenueHistory(selectUnitRevenueHistoryPeriod('2027-02',['2026-11'])===null&&selectUnitRevenueHistoryPeriod('2026-01',['2026-01','2026-11'])===null,'No cross-year or January history');
checkUnitRevenueHistory(selectUnitRevenueHistorySource('2026-12',['2026-09'],['2026-11'])===['source_period'=>'2026-11','snapshot'=>false],'Later legacy upload supersedes earlier compact snapshot');
checkUnitRevenueHistory(selectUnitRevenueHistorySource('2026-10',['2026-09'],['2026-11'])===['source_period'=>'2026-09','snapshot'=>true],'Earlier selected month still prefers preceding compact snapshot');
checkUnitRevenueHistory(selectUnitRevenueHistorySource('2026-10',['2026-12'],['2026-11'])===['source_period'=>'2026-11','snapshot'=>false],'Earliest later period is selected across both formats');
checkUnitRevenueHistory(selectUnitRevenueHistorySource('2026-12',['2026-11'],['2026-11'])===['source_period'=>'2026-11','snapshot'=>true],'Exact matching compact snapshot preserves original blanks');
checkUnitRevenueHistory(selectUnitRevenueHistorySource('2027-02',['2026-11'],['2026-12'])===['source_period'=>null,'snapshot'=>false],'Mixed-format history remains isolated by year');

$oldExisting=['gb'=>'GB 1','product_code'=>'00007','principal_product_code'=>'00008','product_name'=>'Synthetic existing','kategori'=>'','qty_jan'=>0,'rev_jan'=>120,'rev_nov'=>150,'rev_des'=>0];
$oldNew=['gb'=>'GB 1','product_code'=>'00009','principal_product_code'=>'00010','product_name'=>'Synthetic new','kategori'=>'','rev_jan'=>20,'rev_nov'=>30,'rev_des'=>0];
$existingSubtotal=['gb'=>'GB 1','product_name'=>'TOTAL EXISTING GB 1'];
$newSubtotal=['gb'=>'GB 1','product_name'=>'TOTAL NEW PRODUCT GB 1'];
$total=['gb'=>'GB 1','product_name'=>'TOTAL GB 1'];
$legacyRevenue=[$oldExisting,$oldExisting,$existingSubtotal,$oldNew,$newSubtotal,$total];
$legacyUnit=[$oldExisting+['unit_jan'=>12,'unit_nov'=>15,'unit_des'=>0],$existingSubtotal,$oldNew+['unit_jan'=>2,'unit_nov'=>3,'unit_des'=>0],$newSubtotal,$total];
$legacy=unitRevenueLegacyTemplateRecords('2026-11',$legacyRevenue,$legacyUnit);
checkUnitRevenueHistory(count($legacy)===2,'Legacy totals excluded and identical detail duplicates do not multiply results');
checkUnitRevenueHistory($legacy[0]['kategori']==='ABC'&&$legacy[1]['kategori']==='D','Blank legacy categories recovered only from matching subtotal boundaries');
checkUnitRevenueHistory($legacy[0]['qty'][0]===12.0&&$legacy[0]['rev'][0]===120.0,'UNIT quantities override cleared legacy revenue qty columns');
checkUnitRevenueHistory($legacy[0]['qty'][11]===null&&$legacy[0]['rev'][11]===null,'Legacy future-month generated zeros remain blank');
checkUnitRevenueHistory($legacy[0]['product_code']==='00007','Legacy leading zeroes preserved');
rejectUnitRevenueHistory(static fn()=>unitRevenueLegacyTemplateRecords('2026-11',[$oldExisting],[]),'Missing category boundaries must not be guessed');
$conflicting=$oldExisting;$conflicting['rev_jan']=121;
rejectUnitRevenueHistory(static fn()=>unitRevenueLegacyTemplateRecords('2026-11',[$oldExisting,$conflicting,$existingSubtotal],[]),'Conflicting duplicate rows must be reported without summing');
$invalid=$history;$invalid['records'][0]['qty'][0]='not numeric';
rejectUnitRevenueHistory(static fn()=>buildUnitRevenueTemplateWithHistory('2026-12',$invalid),'Invalid saved numeric data rejected');
echo "PASS UNIT & Revenue history: mixed-format precedence, carry forward, earlier-month limit, yearly reset, linked identities, zero/blank, expanded capacity, upload roundtrip and safe legacy reconstruction\n";

assert(unitRevenueLegacyCategory('KAT A')==='A');
assert(unitRevenueLegacyCategory('KATEGORI D')==='D');
