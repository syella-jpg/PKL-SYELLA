<?php
require_once __DIR__.'/ods_upload_xml.php';
/** Small, read-only workbook preview for recognition; never expand repeated ODS cells. */
function readOdsUploadDetection(string $path): array {
 if(!is_file($path)||filesize($path)>50*1024*1024)throw new InvalidArgumentException('File maksimal 50 MB.');
 $zip=new ZipArchive();if($zip->open($path)!==true)throw new InvalidArgumentException('File ODS tidak valid.');
 try{$info=$zip->statName('content.xml');if(!$info||$info['size']>ODS_UPLOAD_XML_LIMIT)throw new InvalidArgumentException('Isi XML ODS melebihi batas pemrosesan 256 MB.');}finally{$zip->close();}
 $t='urn:oasis:names:tc:opendocument:xmlns:table:1.0';$o='urn:oasis:names:tc:opendocument:xmlns:office:1.0';
 $reader=new XMLReader();$previous=libxml_use_internal_errors(true);$book=['SheetNames'=>[],'Sheets'=>[]];$total=0;$characters=0;
 try{
  if(!$reader->open('zip://'.$path.'#content.xml',null,LIBXML_NONET))throw new InvalidArgumentException('Isi ODS tidak dapat dibaca.');
  $sheet=null;$tableDepth=null;$row=1;$column=1;$rowRepeat=1;$cell=null;$sheetCells=0;
  $store=static function()use(&$cell,&$book,&$total,&$characters,&$sheetCells){if($cell===null)return;
   $value=$cell['value'];if($value===null)$value=trim($cell['text']);
   if($value!==''&&strlen((string)$value)<=2048&&$characters+strlen((string)$value)<=131072&&$total<5000&&$sheetCells<256){
    $n=$cell['column'];$address='';while($n>0){$n--;$address=chr(65+$n%26).$address;$n=intdiv($n,26);}$address.=$cell['row'];
    $book['Sheets'][$cell['sheet']][$address]=['t'=>is_float($value)?'n':'s','v'=>$value];$total++;$sheetCells++;$characters+=strlen((string)$value);
   }$cell=null;
  };
  while($reader->read()){
   if($reader->nodeType===XMLReader::DOC_TYPE)throw new InvalidArgumentException('Deklarasi DTD tidak didukung pada file ODS.');
   if($reader->nodeType===XMLReader::ELEMENT&&$reader->namespaceURI===$t){
    $name=$reader->localName;
    if($name==='table'&&$sheet===null){$sheet=$reader->getAttributeNs('name',$t);if(!$sheet||count($book['SheetNames'])>=128)throw new InvalidArgumentException('Jumlah sheet atau nama sheet tidak valid.');$book['SheetNames'][]=$sheet;$book['Sheets'][$sheet]=[];$tableDepth=$reader->depth;$row=1;$column=1;$sheetCells=0;if($reader->isEmptyElement)$sheet=null;}
    elseif($sheet!==null&&$name==='table-row'){$column=1;$rowRepeat=max(1,(int)$reader->getAttributeNs('number-rows-repeated',$t));if($reader->isEmptyElement)$row+=$rowRepeat;}
    elseif($sheet!==null&&in_array($name,['table-cell','covered-table-cell'],true)){
     $repeat=max(1,(int)$reader->getAttributeNs('number-columns-repeated',$t));
     if($row<=1000&&$column<=64&&$sheetCells<256&&$total<5000){$value=$reader->getAttributeNs('string-value',$o);$number=$reader->getAttributeNs('value',$o);if($number!==null)$value=(float)$number;
      $cell=['sheet'=>$sheet,'row'=>$row,'column'=>$column,'depth'=>$reader->depth,'value'=>$value,'text'=>''];
     }$column+=$repeat;if($reader->isEmptyElement)$store();
    }
   }elseif($reader->nodeType===XMLReader::END_ELEMENT&&$reader->namespaceURI===$t){
    if($cell!==null&&$reader->depth===$cell['depth'])$store();
    if($reader->localName==='table-row')$row+=$rowRepeat;
    elseif($reader->localName==='table'&&$reader->depth===$tableDepth){$sheet=null;$tableDepth=null;}
   }elseif($cell!==null&&$reader->nodeType===XMLReader::END_ELEMENT&&$reader->localName==='p'&&$reader->namespaceURI==='urn:oasis:names:tc:opendocument:xmlns:text:1.0'&&strlen($cell['text'])<2048)$cell['text'].="\n";
   elseif($cell!==null&&in_array($reader->nodeType,[XMLReader::TEXT,XMLReader::CDATA,XMLReader::SIGNIFICANT_WHITESPACE],true)&&strlen($cell['text'])<2048)$cell['text'].=substr($reader->value,0,2048-strlen($cell['text']));
  }
  foreach(libxml_get_errors() as $error)if($error->level>=LIBXML_ERR_ERROR)throw new InvalidArgumentException('XML pada file ODS tidak valid.');
  if(!$book['SheetNames'])throw new InvalidArgumentException('Sheet ODS tidak ditemukan.');return $book;
 }finally{$reader->close();libxml_clear_errors();libxml_use_internal_errors($previous);}
}
