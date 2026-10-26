<?php
require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../includes/biaya_workbook.php';
$book=\PhpOffice\PhpSpreadsheet\IOFactory::load('/home/user/Downloads/RAW DATA!/BIAYA/biaya 26 edited.ods');
$check=static function($ok,$message) { if (!$ok) throw new RuntimeException($message); };
$rows=parseBiayaProducts($book,2026);
$check(count($rows)===112,'Expected 56 products in target and actual');
foreach (['target','actual'] as $type) {
    $gb6=array_values(array_filter($rows,static fn($row)=>$row['type']===$type && $row['gb']===6));
    $check(count($gb6)===10,'GB6 products missing: '.$type);
    $check(array_column($gb6,'number')===range(47,56),'Formula product numbers must be evaluated');
}
$check($rows[0]['values']===array_fill(0,12,null),'Empty cells must stay NULL');
$sheet=$book->getSheet(0);
$sheet->setCellValue('D4','Produk uji');
$sheet->setCellValue('F4',1250.25);
$sheet->setCellValue('G4',0);
$sheet->setCellValue('W4',500.5);
$rows=parseBiayaProducts($book,2026);
$check($rows[0]['name']==='Produk uji' && $rows[0]['values'][0]===1250.25,'Target product data lost');
$check($rows[0]['values'][1]===0.0 && $rows[0]['values'][2]===null,'Zero and empty confused');
$check($rows[1]['type']==='actual' && $rows[1]['values'][0]===500.5,'Actual data lost');
foreach (['-', ' – ', '—', "\xc2\xa0"] as $empty) {
    $sheet->setCellValue('AB9',$empty);
    $rows=parseBiayaProducts($book,2026);
    $matches=array_values(array_filter($rows,static fn($row)=>$row['type']==='actual' && $row['number']===6));
    $check($matches[0]['values'][5]===null,'AB9 placeholder must stay NULL');
}
$sheet->setCellValue('AB9',null);
$sheet->setCellValue('F4','invalid');
try { parseBiayaProducts($book,2026); throw new RuntimeException('Invalid amount accepted'); }
catch (InvalidArgumentException $e) {}
echo "PASS: product identity, target/actual, decimal amounts, zero, NULL, invalid amounts.\n";

class BiayaStorageProbe {
    public array $sql=[];
    public array $inserted=[];
    public bool $committed=false;
    public function query($sql) { $this->sql[]=$sql; return true; }
    public function begin_transaction() {}
    public function commit() { $this->committed=true; }
    public function rollback() { throw new RuntimeException('Unexpected rollback'); }
    public function prepare($sql) {
        $this->sql[]=$sql;
        return new class($this,str_starts_with($sql,'INSERT INTO biaya (')) {
            private array $args=[];
            public function __construct(private $probe,private bool $product) {}
            public function bind_param($types,&...$args) {
                if (strlen($types)!==count($args)) throw new RuntimeException('Parameter mismatch');
                $this->args=$args;
            }
            public function execute() { if($this->product) $this->probe->inserted[]=$this->args; }
            public function close() {}
        };
    }
}
$probe=new BiayaStorageProbe();
saveBiayaProducts($probe,'2026-09',array_slice($rows,0,2));
$check(str_contains($probe->sql[0],'CREATE TABLE IF NOT EXISTS biaya ('),'Schema must be available without SQL file');
$check(str_contains($probe->sql[0],'`dec` DECIMAL'),'Reserved month must be quoted');
$check(count($probe->inserted)===2 && $probe->committed,'Product upload must commit');
$check(count($probe->inserted[0])===18,'All product columns must be saved');
echo "PASS: standalone schema creation, insert parameters and transaction commit.\n";
