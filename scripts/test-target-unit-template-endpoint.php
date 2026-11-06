<?php
namespace TargetUnitTemplateEndpointTest;
// Exercise the actual download handler in a fresh process with no production DB access.
class Connection extends \mysqli {
    public function __construct(){parent::__construct();}
    public function set_charset(string $charset):bool{return true;}
    #[\ReturnTypeWillChange] public function query($query,$mode=MYSQLI_STORE_RESULT){return true;}
    #[\ReturnTypeWillChange] public function prepare($query){return new Statement();}
    public function close():bool{return true;}
}
class Statement {
    public function bind_param($types,&...$values){}
    public function execute(){}
    public function get_result(){return new class{public function fetch_all($mode){return [];}public function fetch_assoc(){return null;}};}
    public function close(){}
}
$connections=0;
function openReportConnection():Connection{global $connections;$connections++;return new Connection();}
$source=file_get_contents(__DIR__.'/../dashboard/report_input_template.php');
$source=preg_replace('/^<\?php\s*/','',$source);
// Keep all real template includes, replacing only the database connection dependency.
$source=str_replace("require_once __DIR__.'/../includes/database.php';",'',$source);
foreach([['TARGET UNIT SELL OUT','2026-09'],['TARGET UNIT SELL OUT','2027-01'],['ETHICAL','2026-09'],['ETHICAL','2027-01'],['UNIT & Revenue','2026-10'],['REVENUE VERSI EDP MNJ','2026-10']] as [$section,$period]){
    $_GET=['section'=>$section,'period'=>$period];
    ob_start();try{eval('namespace '.__NAMESPACE__.';'.$source);$xlsx=ob_get_contents();}finally{ob_end_clean();}
    if(!str_starts_with($xlsx,'PK'))throw new \RuntimeException('Download handler returned an error instead of Excel for '.$period);
    $path=tempnam('/tmp','endpoint-target-');try{
        file_put_contents($path,$xlsx);$loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        if($loaded->getSheetNames()!==($section==='ETHICAL'?['PRODUK NAS','_TEMPLATE']:(in_array($section,['UNIT & Revenue','REVENUE VERSI EDP MNJ'],true)?['UNIT','REVENUE','_TEMPLATE']:['GB 1','GB 2','GB 3','GB 4','GB 5','GB 6','ETHICAL','IB','_TEMPLATE'])))throw new \RuntimeException('Wrong template sheets');
        if(!str_contains($loaded->getSheetByName($section==='ETHICAL'?'PRODUK NAS':(in_array($section,['UNIT & Revenue','REVENUE VERSI EDP MNJ'],true)?'UNIT':'GB 1'))->getCell('A1')->getValue(),substr($period,0,4)))throw new \RuntimeException('Wrong template year');
        $loaded->disconnectWorksheets();
    }finally{unlink($path);}
}
if($connections!==5)throw new \RuntimeException('Target template must read same-year Ethical master in January too');
echo "PASS actual template handler: September as first request, history dependencies loaded, valid separate GB sheets; January keeps GB inputs empty while checking current-year Ethical source\n";
