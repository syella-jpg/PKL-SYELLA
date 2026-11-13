<?php
require_once __DIR__.'/../includes/revenue_so_source.php';
class FakeRevenueSource {
 public $rows;public $code;public $stmt;
 function __construct($rows=[],$code=0){$this->rows=$rows;$this->code=$code;}
 function prepare($sql){if($this->code)throw new mysqli_sql_exception('synthetic failure',$this->code);if(!str_contains($sql,'WHERE report_period = ?'))throw new RuntimeException('Period filter lost');return $this->stmt=new FakeRevenueStmt($this->rows);}
}
class FakeRevenueStmt {
 public $rows;public $period;public $closed=false;
 function __construct($rows){$this->rows=$rows;}
 function bind_param($type,&$period){$this->period=$period;}
 function execute(){}
 function get_result(){return new FakeRevenueRows($this->rows);}
 function close(){$this->closed=true;}
}
class FakeRevenueRows {
 private $rows;
 function __construct($rows){$this->rows=$rows;}
 function fetch_assoc(){return array_shift($this->rows);}
}
$r=readRevenueSellingOutSource(new FakeRevenueSource([],1146),'2026-11');if(!$r['success']||$r['source_available']||$r['data']!==[])throw new RuntimeException('Missing optional table result');
foreach([[],[['id'=>1,'rev_total'=>'123']]] as $rows){$conn=new FakeRevenueSource($rows);$r=readRevenueSellingOutSource($conn,'2026-11');if(!$r['source_available']||$r['data']!==$rows||$r['total']!==count($rows)||!$conn->stmt->closed||$conn->stmt->period!=='2026-11')throw new RuntimeException('Existing source result changed');}
try{readRevenueSellingOutSource(new FakeRevenueSource([],1054),'2026-11');throw new RuntimeException('Real schema error hidden');}catch(mysqli_sql_exception $e){if($e->getCode()!==1054)throw $e;}
echo "PASS: optional legacy table absence, existing/empty data, period binding, statement cleanup and real errors preserved.\n";
