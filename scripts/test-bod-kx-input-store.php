<?php
namespace BodKxInputStoreTest;
use RuntimeException;
use Throwable;
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/bod_kx_sell_in_input_store.php';
// A transaction-aware mysqli double: never connects to a production database.
class Connection extends \mysqli {
    public array $inputs=[],$sales=[],$targets=[],$events=[];public ?array $before=null;public bool $failTarget=false,$failSnapshot=false;
    public function __construct(){parent::__construct();}
    public function set_charset(string $charset):bool{return true;}
    public function close():bool{return true;}
    #[\ReturnTypeWillChange] public function query($query,$mode=MYSQLI_STORE_RESULT){$this->events[]=$query;if(str_starts_with($query,'SHOW COLUMNS'))return new Result([['Field'=>'units']]);return true;}
    #[\ReturnTypeWillChange] public function prepare($query){return new Statement($this,$query);}
    public function begin_transaction(int $flags=0,?string $name=null):bool {if($this->before!==null)throw new RuntimeException('Nested transaction');$this->before=[$this->inputs,$this->sales,$this->targets];$this->events[]='BEGIN';return true;}
    public function commit(int $flags=0,?string $name=null):bool {$this->before=null;$this->events[]='COMMIT';return true;}
    public function rollback(int $flags=0,?string $name=null):bool {if($this->before!==null)[$this->inputs,$this->sales,$this->targets]=$this->before;$this->before=null;$this->events[]='ROLLBACK';return true;}
}
class Result {
    public int $num_rows;public function __construct(public array $rows){$this->num_rows=count($rows);}
    public function fetch_assoc(){return array_shift($this->rows);}
    public function fetch_all($mode=MYSQLI_ASSOC){return $this->rows;}
    public function free(){}
}
class Statement {
    private array $values=[],$rows=[];
    public function __construct(private Connection $conn,private string $sql){}
    public function bind_param($types,&...$values){$this->values=$values;}
    public function close(){}
    public function get_result(){return new Result($this->rows);}
    public function execute(){
        $v=$this->values;$sql=$this->sql;$c=$this->conn;$c->events[]=$sql;
        if(str_contains($sql,'information_schema.TABLES'))$this->rows=[['TABLE_NAME'=>'BOD KX SELL IN target']];
        elseif(str_starts_with($sql,'INSERT IGNORE INTO `BOD KX SELL IN TEMPLATE`'))$c->inputs[$v[0]]??=['report_period'=>$v[1],'payload'=>$v[2]];
        elseif(str_starts_with($sql,'SELECT payload FROM `BOD KX SELL IN TEMPLATE`'))$this->rows=isset($c->inputs[$v[0]])?[$c->inputs[$v[0]]]:[];
        elseif(str_starts_with($sql,'UPDATE `BOD KX SELL IN TEMPLATE`')){if($c->failSnapshot)throw new RuntimeException('Synthetic snapshot failure');$c->inputs[$v[2]]=['report_period'=>$v[0],'payload'=>$v[1]];}
        elseif(str_starts_with($sql,'DELETE FROM `BOD KX SELL IN target`'))$c->targets=array_filter($c->targets,fn($r)=>$r['report_year']!==$v[0]||(isset($v[1])&&$r['product']!==$v[1]));
        elseif(str_starts_with($sql,'INSERT INTO bod_kx_sell_in_monthly')){$key=$v[0].'|'.$v[1];$record=$c->sales[$key]??['report_period'=>$v[0],'product'=>$v[1],'units'=>null,'rupiah'=>null];foreach(['units'=>2,'rupiah'=>3] as $field=>$i)if(!isset($c->sales[$key])||str_contains($sql,$field.'=VALUES('.$field.')'))$record[$field]=$v[$i];$c->sales[$key]=$record;}
        elseif(str_starts_with($sql,'INSERT INTO `BOD KX SELL IN target`')){if($c->failTarget)throw new RuntimeException('Synthetic target failure');$c->targets[$v[0].'|'.$v[1].'|'.$v[2]]=['report_year'=>$v[0],'month'=>$v[1],'product'=>$v[2],'rupiah'=>$v[3],'units'=>$v[4]];}
        elseif(str_starts_with($sql,'SELECT report_period,product,units,rupiah FROM bod_kx_sell_in_monthly'))$this->rows=array_values(array_filter($c->sales,fn($r)=>$r['report_period']>=$v[0]&&$r['report_period']<=$v[1]));
        elseif(str_starts_with($sql,'SELECT report_year,month,product,rupiah,units FROM `BOD KX SELL IN target`'))$this->rows=array_values(array_filter($c->targets,fn($r)=>$r['report_year']>=$v[0]&&$r['report_year']<=$v[1]));
        else throw new RuntimeException('Unexpected SQL in transaction test');
        return true;
    }
}
function check($ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$conn=new Connection();$qty=array_fill(0,12,null);$qty[0]=2;$qty[9]=3;$rp=array_fill(0,12,null);$rp[0]=20;$rp[9]=30;
$input=['year'=>2026,'sales'=>[['product'=>'gb1','barang'=>'Synthetic sales','units'=>$qty,'rupiah'=>$rp]],'rod'=>[['product'=>'gb1','code'=>'0001','name'=>'Synthetic target','price'=>5.0,'units'=>$qty]]];
$saved=\saveBodKxCombinedInput($conn,'2026-10',$input);check($saved['kind']==='combined'&&$saved['sales_count']===2&&$saved['target_count']===2,'Both report sources saved from one input');
check($conn->sales['2026-10|gb1']['units']==='3.000000'&&$conn->targets['2026|10|gb1']['rupiah']==='15.000000','Actual and target subtotals persisted with proper period/year');
check(\loadBodKxInputHistory($conn,'2026-11')===$input,'Compressed source detail can be loaded for next download');
$prior=[$conn->sales,$conn->targets];\saveBodKxCombinedInput($conn,'2026-10',$input);check([$conn->sales,$conn->targets]===$prior,'Reupload does not duplicate or inflate values');
$changed=$input;$changed['sales'][0]['units'][9]=0;$changed['rod'][0]['units'][9]=0;$conn->failTarget=true;$before=[$conn->inputs,$conn->sales,$conn->targets];
try{\saveBodKxCombinedInput($conn,'2026-10',$changed);throw new RuntimeException('Failure accepted');}catch(RuntimeException $e){check($e->getMessage()==='Synthetic target failure','Expected simulated database failure');}
check([$conn->inputs,$conn->sales,$conn->targets]===$before,'Target failure rolls back actual values and input detail together');$conn->failTarget=false;
\saveBodKxCombinedInput($conn,'2026-10',$changed);check($conn->sales['2026-10|gb1']['units']==='0.000000'&&$conn->targets['2026|10|gb1']['units']==='0.000000','Explicit zero replaces saved values');
check(in_array('SELECT payload FROM `BOD KX SELL IN TEMPLATE` WHERE report_year=? FOR UPDATE',$conn->events,true),'Year source is locked for atomic concurrent changes');
$conn->begin_transaction();\invalidateBodKxInputState($conn,'2026-10',false);$conn->commit();$state=\loadBodKxInputHistory($conn,'2026-11');check($state['sales'][0]['units'][9]===null&&$state['sales'][0]['units'][0]===2&&$state['rod'][0]['units'][0]===2,'Legacy monthly replacement invalidates only its month detail');
$conn->begin_transaction();\invalidateBodKxInputState($conn,'2026-10',true);$conn->commit();$state=\loadBodKxInputHistory($conn,'2026-11');check($state['rod']===[]&&$state['sales'][0]['units'][0]===2,'Legacy ROD replacement invalidates only target detail');

// Exercise the actual upload/read handler using a real generated XLSX and this connection double.
$apiConn=new Connection();$connections=0;
function openReportConnection():Connection {global $apiConn,$connections;$connections++;return $apiConn;}
$source=preg_replace('/^<\?php\s*/','',file_get_contents(__DIR__.'/../dashboard/bod_kx_sell_in_api.php'));
$source=str_replace("require_once __DIR__.'/../includes/database.php';",'',$source);
function runApi(string $source):array {http_response_code(200);ob_start();try{eval('namespace '.__NAMESPACE__.'; use \\Throwable; use \\InvalidArgumentException;'.$source);$response=ob_get_contents();}finally{ob_end_clean();}return json_decode($response,true,512,JSON_THROW_ON_ERROR);}
$book=\buildBodKxInputTemplate('2026-10');$sales=$book->getSheetByName('PENJUALAN');$rod=$book->getSheetByName('ROD');$st=\locateBodKxInputTables($sales,2026,false)['gb1'];$rt=\locateBodKxInputTables($rod,2026,true)['gb1'];
$sales->setCellValue('A'.$st['first'],'Synthetic API sales');$sales->setCellValue([$st['months'][9]['units'],$st['first']],2);$sales->setCellValue([$st['months'][9]['rupiah'],$st['first']],20);$rod->setCellValue('C'.$rt['first'],'0001');$rod->setCellValue('D'.$rt['first'],'Synthetic API target');$rod->setCellValue('E'.$rt['first'],5);$rod->setCellValue([$rt['months'][9]['units'],$rt['first']],2);
$path=tempnam('/tmp','kx-upload-');try{$writer=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);$writer->setPreCalculateFormulas(false);$writer->save($path);$book->disconnectWorksheets();$_SERVER['REQUEST_METHOD']='POST';$_POST=['period'=>'2026-10'];$_FILES=['file'=>['name'=>'Synthetic.xlsx','tmp_name'=>$path,'error'=>UPLOAD_ERR_OK,'size'=>filesize($path)]];
    $reply=runApi($source);check($reply['success']&&$reply['kind']==='combined'&&count($apiConn->sales)===1&&count($apiConn->targets)===1,'Real upload route detects and saves both sheets');
    runApi($source);check(count($apiConn->sales)===1&&count($apiConn->targets)===1,'Real route reupload retains unique keys');
    $raw=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$sheet=$raw->getActiveSheet();$sheet->setTitle('MONTHLY_SALES_REPORT');
    foreach(['M35'=>'Bulan Oktober 2026','M36'=>'Jumlah Unit','N36'=>'Rupiah','P35'=>'Bulan Januari 2026 s.d Oktober 2026','P36'=>'Jumlah Unit','Q36'=>'Rupiah','B60'=>'Sub Total','D60'=>'01 - GB1','M60'=>7,'N60'=>70,'P60'=>999,'Q60'=>999] as $cell=>$value)$sheet->setCellValue($cell,$value);
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($raw))->save($path);$raw->disconnectWorksheets();$_FILES['file']['size']=filesize($path);$_POST['source_type']='sales';
    $reply=runApi($source);check($reply['success']&&$apiConn->sales['2026-10|gb1']['units']==='7.000000'&&$apiConn->targets['2026|10|gb1']['units']==='2.000000','Monthly upload updates actual summary while preserving ROD');
    $history=\loadBodKxInputHistory($apiConn,'2026-10');$template=\buildBodKxInputTemplate('2026-10',$history);$parsed=\parseBodKxInputTemplate($template,'2026-10');$actual=array_values(array_filter($parsed['sales'],fn($r)=>$r['units'][9]!==null));check(count($actual)===1&&$actual[0]['units'][9]===7.0,'Raw subtotal appears in downloaded template without old detail double counting');$template->disconnectWorksheets();
    runApi($source);check($apiConn->sales['2026-10|gb1']['units']==='7.000000','Repeated monthly import replaces rather than adds');
    $_POST['source_type']='rod';$beforeConnections=$connections;$reply=runApi($source);check(!$reply['success']&&$connections===$beforeConnections,'Sales file in ROD mode is rejected before database access');$_POST['source_type']='sales';
    $_POST['period']='2026-09';$beforeState=$apiConn->inputs;$reply=runApi($source);check(!$reply['success']&&http_response_code()===400&&$apiConn->inputs===$beforeState,'Wrong monthly slot does not alter stored data');$_POST['period']='2026-10';
    $jan=\bodKxMonthlyInput([['product'=>'gb1','units'=>'1','rupiah'=>'10']],'2027-01');\saveBodKxCombinedInput($apiConn,'2027-01',$jan);$january=\buildBodKxInputTemplate('2027-01',\loadBodKxInputHistory($apiConn,'2027-01'));check(\parseBodKxInputTemplate($january,'2027-01')['sales'][0]['units'][0]===1.0,'Imported January results are visible in January template');$january->disconnectWorksheets();
    $_POST['period']='2025-10';$beforeConnections=$connections;$reply=runApi($source);check(!$reply['success']&&http_response_code()===400&&$connections===$beforeConnections,'Year validation happens before connecting or writing');
    $_SERVER['REQUEST_METHOD']='GET';$_GET=['period'=>'2026-10'];$reply=runApi($source);check($reply['success']&&count($reply['data'])===1&&count($reply['targets'])===1,'Existing report reader receives both new canonical sources');
    $raw=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$r=$raw->getActiveSheet();$r->setTitle('ROD ORIGINAL');foreach(['A1'=>'Tahun','B1'=>'Group Brand','C2'=>'Kode','D2'=>'Nama','H1'=>'Harga Beli','A3'=>2026,'B3'=>'GB 1','C3'=>'0004','D3'=>'Synthetic ROD','H3'=>3] as $cell=>$value)$r->setCellValue($cell,$value);
    foreach(['JAN','FEB','MAR','APR','MEI','JUN','JUL','AUG','SEPT','OKT','NOV','DES'] as $m=>$label){$col=10+$m*2;$r->setCellValue([$col,1],$label);$r->setCellValue([$col,2],'ROD Unit Terkecil Dari Unit Pesan');$r->setCellValue([$col+1,2],'Nilai ROD Unit Terkecil Dari Unit Pesan');}$r->setCellValue('AB3',2);$r->setCellValue('AC3',999999);
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($raw))->save($path);$raw->disconnectWorksheets();$_SERVER['REQUEST_METHOD']='POST';$_POST=['period'=>'2026-10','source_type'=>'rod'];$_FILES['file']['size']=filesize($path);
    $_POST['source_type']='sales';$beforeConnections=$connections;$reply=runApi($source);check(!$reply['success']&&$connections===$beforeConnections,'ROD file in Sales mode is rejected before database access');$_POST['source_type']='rod';
    $reply=runApi($source);check($reply['success']&&$reply['source']==='rod'&&$apiConn->targets['2026|10|gb1']['rupiah']==='6.000000'&&$apiConn->sales['2026-10|gb1']['units']==='7.000000','Raw ROD fills canonical targets without changing monthly sales');
    $history=\loadBodKxInputHistory($apiConn,'2026-10');$template=\buildBodKxInputTemplate('2026-10',$history);$parsed=\parseBodKxInputTemplate($template,'2026-10');check(count($parsed['rod'])===1&&$parsed['rod'][0]['code']==='0004'&&$parsed['rod'][0]['price']===3.0&&$parsed['rod'][0]['units'][9]===2.0,'Imported ROD detail appears in downloaded template');$template->disconnectWorksheets();
    runApi($source);check(count($apiConn->targets)===12&&$apiConn->targets['2026|10|gb1']['rupiah']==='6.000000','Annual ROD reupload does not duplicate targets');$before=[$apiConn->inputs,$apiConn->sales,$apiConn->targets];$apiConn->failTarget=true;$reply=runApi($source);check(!$reply['success']&&[$apiConn->inputs,$apiConn->sales,$apiConn->targets]===$before,'Failed annual ROD replacement rolls back deletion and snapshot');$apiConn->failTarget=false;
    $janRod=\buildBodKxInputTemplate('2026-01',\loadBodKxInputHistory($apiConn,'2026-01'));check(\parseBodKxInputTemplate($janRod,'2026-01')['rod'][0]['units'][9]===2.0,'Explicit annual ROD import remains available when downloading January');$janRod->disconnectWorksheets();
}finally{unlink($path);}

// Regression: an edited combined template declares the ROD products that still exist.
// Use compact history templates so the test covers deleted rows in both file formats.
foreach(['Xlsx','Ods'] as $format){
    $apiConn=new Connection();$units=array_fill(0,12,null);$units[0]=2.0;$units[11]=3.0;
    $salesUnits=array_fill(0,12,null);$salesUnits[8]=4.0;$salesUnits[10]=5.0;
    $seed=['year'=>2025,'sales'=>[['product'=>'gb1','barang'=>'Synthetic sales retained','units'=>$salesUnits,'rupiah'=>$salesUnits]],'rod'=>[
        ['product'=>'gb1','code'=>'DELETE','name'=>'Synthetic removed','price'=>5.0,'units'=>$units],
        ['product'=>'gb2','code'=>'KEEP','name'=>'Synthetic retained','price'=>7.0,'units'=>$units],
    ]];
    \saveBodKxCombinedInput($apiConn,'2025-12',$seed);
    $otherYear=$seed;$otherYear['year']=2026;\saveBodKxCombinedInput($apiConn,'2026-12',$otherYear);
    $unchangedSales=$apiConn->sales;$unchangedYear=$apiConn->inputs[2026];
    $book=\buildBodKxInputTemplate('2025-09',\loadBodKxInputHistory($apiConn,'2025-09'));
    $rod=$book->getSheetByName('ROD');$table=\locateBodKxInputTables($rod,2025,true)['gb1'];
    $rod->removeRow($table['first'],$table['last']-$table['first']+1);
    $path=tempnam('/tmp','kx-delete-');
    try{
        $writer=\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book,$format);$writer->setPreCalculateFormulas(false);$writer->save($path);
        $_SERVER['REQUEST_METHOD']='POST';$_POST=['period'=>'2025-09'];$_FILES=['file'=>['name'=>'Synthetic.'.$format,'tmp_name'=>$path,'error'=>UPLOAD_ERR_OK,'size'=>filesize($path)]];
        $before=[$apiConn->inputs,$apiConn->sales,$apiConn->targets];$apiConn->failSnapshot=true;
        $reply=runApi($source);check(!$reply['success']&&[$apiConn->inputs,$apiConn->sales,$apiConn->targets]===$before,'Snapshot failure rolls back deleted targets and all other writes');$apiConn->failSnapshot=false;
        $reply=runApi($source);check($reply['success'],'Edited combined template accepts deleted last ROD product');
        check(!isset($apiConn->targets['2025|1|gb1'])&&!isset($apiConn->targets['2025|12|gb1']),'Deleted group loses all January-December canonical target rows');
        check($apiConn->targets['2025|12|gb2']['rupiah']==='21.000000','Another ROD group retains its annual target');
        check($apiConn->sales===$unchangedSales&&$apiConn->inputs[2026]===$unchangedYear&&isset($apiConn->targets['2026|1|gb1']),'Deletion preserves sales including later months and other years');
        $history=\loadBodKxInputHistory($apiConn,'2025-10');check(count($history['rod'])===1&&$history['rod'][0]['product']==='gb2','Deleted product is absent from stored detail');
        $next=\buildBodKxInputTemplate('2025-10',$history);$empty=\locateBodKxInputTables($next->getSheetByName('ROD'),2025,true)['gb1'];check($empty['last']<$empty['first'],'Next downloaded template does not recreate deleted product');$next->disconnectWorksheets();
        $before=[$apiConn->inputs,$apiConn->sales,$apiConn->targets];$reply=runApi($source);check($reply['success']&&[$apiConn->inputs,$apiConn->sales,$apiConn->targets]===$before,'Reuploading deletion does not change or duplicate other data');
        $_SERVER['REQUEST_METHOD']='GET';$_GET=['period'=>'2026-09'];$reply=runApi($source);
        check(!array_filter($reply['targets'],fn($r)=>$r['report_year']===2025&&$r['product']==='gb1'),'Actual report API no longer returns deleted prior-year target');
    }finally{unlink($path);$book->disconnectWorksheets();}
}

// Removing one sparse product must clear its old months even when another product remains.
$conn=new Connection();$seed['rod'][1]['product']='gb1';$seed['rod'][1]['units'][0]=null;
\saveBodKxCombinedInput($conn,'2025-12',$seed);$edit=$seed;$edit['_rod_template']=true;array_shift($edit['rod']);
\saveBodKxCombinedInput($conn,'2025-12',$edit);
check($conn->targets['2025|1|gb1']['units']===null&&$conn->targets['2025|12|gb1']['rupiah']==='21.000000','Removed product clears stale sparse months while retaining remaining product totals');

// A workbook that becomes entirely empty is valid only when it deletes existing ROD.
$apiConn=new Connection();$onlyRod=$seed;$onlyRod['sales']=[];\saveBodKxCombinedInput($apiConn,'2025-12',$onlyRod);
$empty=\buildBodKxInputTemplate('2025-12',\loadBodKxInputHistory($apiConn,'2025-12'));$rod=$empty->getSheetByName('ROD');$table=\locateBodKxInputTables($rod,2025,true)['gb1'];$rod->removeRow($table['first'],$table['last']-$table['first']+1);
$path=tempnam('/tmp','kx-delete-all-');try{
    $writer=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($empty);$writer->setPreCalculateFormulas(false);$writer->save($path);
    $_SERVER['REQUEST_METHOD']='POST';$_POST=['period'=>'2025-12'];$_FILES=['file'=>['name'=>'Synthetic.xlsx','tmp_name'=>$path,'error'=>UPLOAD_ERR_OK,'size'=>filesize($path)]];
    $reply=runApi($source);check($reply['success']&&$apiConn->targets===[]&&\loadBodKxInputHistory($apiConn,'2025-12')['rod']===[],'Deletion-only workbook clears last ROD without dummy sales input');
    $before=$apiConn->inputs;$reply=runApi($source);check(!$reply['success']&&$apiConn->inputs===$before,'Empty workbook with no existing ROD deletion remains rejected');
}finally{unlink($path);$empty->disconnectWorksheets();}
echo "PASS deleted ROD products: XLSX/ODS actual API, all-month removal, other groups/years/sales retained, sparse months, deletion-only input, reupload and rollback\n";
echo "PASS BOD KX input database transaction double and actual upload/read handler: two sources, compressed detail/history, reupload uniqueness, zero, rollback, year lock, legacy invalidation and pre-database validation\n";
