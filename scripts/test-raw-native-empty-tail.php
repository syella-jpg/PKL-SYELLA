<?php
require __DIR__.'/../vendor/autoload.php';require __DIR__.'/../includes/raw_native_period.php';
function emptyTailCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach(['DAFTAR','UPB non BJ'] as $name){
 $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$s=$book->getActiveSheet();$s->setTitle($name);
 $s->fromArray($name==='DAFTAR'?[['JT','Dummy product'],['V','X']]:[['BAGIAN','JAN','FEB','MAR'],['Dummy BB',10,20,30]]);
 $path=tempnam('/tmp','dummy-empty-tail-');
 try{
  $writer=new \PhpOffice\PhpSpreadsheet\Writer\Ods($book);$writer->setPreCalculateFormulas(false);$writer->save($path);
  $z=new ZipArchive();$z->open($path);$doc=upbBjXml($z->getFromName('content.xml'));$table=upbBjTables($doc)[$name];
  $tail=$doc->createElementNS(UPB_T,'table:table-row');$tail->setAttributeNS(UPB_T,'table:number-rows-repeated','1048574');
  $blank=$doc->createElementNS(UPB_T,'table:table-cell');$blank->setAttributeNS(UPB_T,'table:number-columns-repeated','16384');$tail->appendChild($blank);$table->appendChild($tail);
  $z->addFromString('content.xml',$doc->saveXML());$z->close();
  $out=protectRawNativePeriod(file_get_contents($path),'2026-03');$tables=upbBjTables(rawPeriodDocument($out));
  $last=$tables[$name]->lastChild;while($last&&!($last instanceof DOMElement))$last=$last->previousSibling;
  emptyTailCheck($last->getAttributeNS(UPB_T,'number-rows-repeated')==='1048574','Repeated tail preserved');
  emptyTailCheck(!$last->firstChild->hasAttributeNS(UPB_O,'value-type'),'Empty tail must not become populated string cells');
  emptyTailCheck($last->childNodes->length===1,'Empty tail not expanded');
  emptyTailCheck(isset($tables['PETUNJUK']),'Instructions preserved');
  emptyTailCheck(strlen($out)<100000,'Compact output');
 }finally{$book->disconnectWorksheets();unlink($path);}
}
echo "PASS synthetic DAFTAR/non-BJ layouts: million-row empty ODS tail remains empty and compact, instructions preserved. No source templates or DB accessed.\n";
