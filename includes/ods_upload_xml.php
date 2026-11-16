<?php
// The 50 MB upload limit applies to compressed ODS files. Valid workbooks with
// many formatted records can have more than 64 MB of uncompressed XML.
const ODS_UPLOAD_XML_LIMIT=256*1024*1024;
function readOdsUploadContent(string $path): string {
 $zip=new ZipArchive();if($zip->open($path)!==true)throw new InvalidArgumentException('File ODS tidak valid.');
 try{$info=$zip->statName('content.xml');if(!$info)throw new InvalidArgumentException('Isi XML ODS tidak ditemukan.');
  if($info['size']>ODS_UPLOAD_XML_LIMIT)throw new InvalidArgumentException('Isi XML ODS melebihi batas pemrosesan 256 MB.');
  return $zip->getFromName('content.xml');
 }finally{$zip->close();}
}

/** Load ZIP XML directly so PHP never holds a second full XML string. */
function loadOdsUploadDocument(string $path): DOMDocument {
 $zip=new ZipArchive();if($zip->open($path)!==true)throw new InvalidArgumentException('File ODS tidak valid.');
 try{$info=$zip->statName('content.xml');if(!$info)throw new InvalidArgumentException('Isi XML ODS tidak ditemukan.');
  if($info['size']>ODS_UPLOAD_XML_LIMIT)throw new InvalidArgumentException('Isi XML ODS melebihi batas pemrosesan 256 MB.');
 }finally{$zip->close();}
 $doc=new DOMDocument();if(!@$doc->load('zip://'.$path.'#content.xml',LIBXML_NONET))throw new InvalidArgumentException('Isi XML ODS tidak valid.');
 return $doc;
}
