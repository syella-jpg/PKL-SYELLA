<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/raw_native_period.php';
function passwordCheck($ok,$why){if(!$ok)throw new RuntimeException($why);}
class PasswordDb {
 public ?string $saved=null;public array $queries=[];
 function query($sql){$this->queries[]=$sql;return true;}
 function prepare($sql){$this->queries[]=$sql;return new PasswordQuery($this,$sql);}
}
class PasswordQuery {
 private array $args=[];
 function __construct(private $db,private $sql){}
 function bind_param($type,&...$args){$this->args=&$args;}
 function execute(){if(str_starts_with($this->sql,'INSERT'))$this->db->saved=$this->args[0];}
 function close(){}
 function get_result(){return new class($this->db->saved){function __construct(private $saved){}function fetch_assoc(){return $this->saved===null?null:['setting_value'=>$this->saved];}};}
}
$db=new PasswordDb();passwordCheck(readTemplatePassword($db)==='testing','Default password');
try{saveTemplatePassword($db,'dummy-changed','pharma');throw new LogicException('Unauthorized change accepted');}catch(RuntimeException $e){passwordCheck($e->getCode()===403,'Admin2 authorization');}
passwordCheck($db->saved===null,'Unauthorized change wrote data');
saveTemplatePassword($db,'dummy-changed','admin 2');passwordCheck(readTemplatePassword($db)==='dummy-changed','Shared setting persisted');
foreach(['testing','dummy-changed'] as $password){
 $GLOBALS['rawTemplateSheetPassword']=$password;
 $b=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$b->getActiveSheet();$s->setTitle('REKAP');$s->setCellValue('A1','Dummy');protectRawTemplate($b,'2026-06');addRawTemplateInstructions($b);
 passwordCheck($s->getProtection()->getPassword()===\PhpOffice\PhpSpreadsheet\Shared\PasswordHasher::hashPassword($password),'XLSX protection password');
 $p=tempnam('/tmp','dummy-sheet-password-');
 try{
  $w=new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($b);$w->setPreCalculateFormulas(false);$w->save($p);$loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($p);passwordCheck($loaded->getSheetByName('REKAP')->getProtection()->getPassword()===$s->getProtection()->getPassword(),'XLSX export');$loaded->disconnectWorksheets();
  $w=new \PhpOffice\PhpSpreadsheet\Writer\Ods($b);$w->setPreCalculateFormulas(false);$w->save($p);$out=protectRawNativePeriod(file_get_contents($p),'2026-06');$tables=upbBjTables(rawPeriodDocument($out));
  foreach(['REKAP','PETUNJUK'] as $name)passwordCheck($tables[$name]->getAttributeNS(UPB_T,'protection-key')===base64_encode(sha1($password,true)),'ODS SHA1 sheet password '.$name);
 }finally{$b->disconnectWorksheets();unlink($p);}
}
unset($GLOBALS['rawTemplateSheetPassword']);
echo "PASS default/configured sheet password, admin2-only updates, shared storage double, XLSX export and native ODS protection keys. No live DB or user files.\n";
