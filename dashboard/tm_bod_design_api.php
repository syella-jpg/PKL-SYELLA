<?php
require_once __DIR__.'/../includes/database.php';require_once __DIR__.'/../includes/tm_bod_annual.php';require_once __DIR__.'/../includes/tm_bod_design.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';if(in_array($origin,['null','http://127.0.0.1:8000','http://localhost:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Headers: Authorization, Content-Type');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');}
$method=$_SERVER['REQUEST_METHOD']??'GET';if($method==='OPTIONS'){http_response_code(204);exit;}$conn=null;$dir=null;
try{
 if(!in_array($method,['GET','POST'],true)){http_response_code(405);throw new InvalidArgumentException('Metode tidak didukung.');}
 if($method==='POST'&&empty($_POST)&&empty($_FILES)&&(int)($_SERVER['CONTENT_LENGTH']??0)>0)throw new InvalidArgumentException('Ukuran upload melewati batas server PHP. Sesuaikan post_max_size dan upload_max_filesize.');
 $year=filter_var($_POST['year']??$_GET['year']??null,FILTER_VALIDATE_INT);if(!$year||$year<2026||$year>2100)throw new InvalidArgumentException('Tahun desain tidak valid.');
 $conn=openReportConnection();$conn->query("SET time_zone='+07:00'");requireTmBodAdmin($conn);ensureTmBodDesign($conn);
 if($method==='GET'){$pages=tmBodDesignPages($conn,(string)($_GET['id']??''),$year);echo json_encode(['success'=>true,'pages'=>$pages]);exit;}
 $file=$_FILES['file']??[];if(in_array($file['error']??null,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))throw new InvalidArgumentException('File melewati batas upload PHP. Sesuaikan upload_max_filesize dan post_max_size.');$extension=strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION));
 if(($file['error']??1)!==UPLOAD_ERR_OK||($file['size']??0)>30*1024*1024||!in_array($extension,['ppt','pptx'],true))throw new InvalidArgumentException('Unggah file PPT/PPTX maksimal 30 MB.');
 if(!function_exists('proc_open'))throw new InvalidArgumentException('Server belum mengizinkan konversi PowerPoint (proc_open). Desain aktif tetap dipakai.');
 foreach(['/usr/bin/python3','/usr/bin/libreoffice','/usr/bin/pdfinfo','/usr/bin/pdftoppm'] as $binary)if(!is_executable($binary))throw new InvalidArgumentException('Konversi PowerPoint belum tersedia di server. Pasang Python 3, LibreOffice dan Poppler; desain aktif tetap dipakai.');
 $dir=sys_get_temp_dir().'/tm-bod-design-'.bin2hex(random_bytes(12));if(!mkdir($dir,0700))throw new RuntimeException('Folder sementara gagal dibuat.');
 $path=$dir.'/design.'.$extension;if(!move_uploaded_file($file['tmp_name'],$path))throw new RuntimeException('File upload gagal dibaca.');
 $process=proc_open(['/usr/bin/python3',__DIR__.'/../scripts/render-tm-bod-powerpoint.py',$path,$dir.'/render'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($process))throw new RuntimeException('Konverter gagal dijalankan.');
 stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$start=microtime(true);$errors='';$exit=null;
 while(true){$errors=substr($errors.stream_get_contents($pipes[2]),-10000);stream_get_contents($pipes[1]);$state=proc_get_status($process);if(!$state['running']){$exit=$state['exitcode'];break;}if(microtime(true)-$start>165){proc_terminate($process);throw new RuntimeException('Konversi PowerPoint melewati batas waktu.');}usleep(100000);}
 fclose($pipes[1]);fclose($pipes[2]);proc_close($process);
 if($exit!==0){error_log('PowerPoint converter: '.$errors);throw new InvalidArgumentException('PowerPoint gagal diproses. Gunakan file PPT/PPTX yang dapat dibuka dan berisi maksimal 12 slide desain.');}
 $files=glob($dir.'/render/page-*.png');natsort($files);$pages=[];
 foreach($files as $image){$bytes=file_get_contents($image);$info=getimagesizefromstring($bytes);if(!$info||$info[2]!==IMAGETYPE_PNG)throw new RuntimeException('Hasil konversi tidak valid.');$pages[]='data:image/png;base64,'.base64_encode($bytes);}
 if(!$pages||count($pages)>12)throw new InvalidArgumentException('Tidak ada slide desain yang dapat dibaca.');
 $json=json_encode($pages,JSON_THROW_ON_ERROR);if(strlen($json)>4*1024*1024)throw new InvalidArgumentException('Hasil desain terlalu besar. Kurangi jumlah slide atau ukuran gambar pada PowerPoint.');
 $id=bin2hex(random_bytes(16));$name=mb_substr(basename($file['name']),0,255);$hash=hash_file('sha256',$path);
 $q=$conn->prepare("INSERT INTO tm_bod_design_uploads (id,report_year,original_name,source_sha256,pages_json,uploaded_by) VALUES (?,?,?,?,?,'admin 1')");$q->bind_param('sisss',$id,$year,$name,$hash,$json);$q->execute();$q->close();echo json_encode(['success'=>true,'id'=>$id,'pages'=>$pages]);
}catch(Throwable $e){error_log('TM BOD design: '.$e->getMessage());if(http_response_code()<400)http_response_code($e instanceof InvalidArgumentException?400:500);echo json_encode(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Upload desain gagal. Periksa log server; template aktif tidak berubah.']);}
finally{if($conn)$conn->close();if($dir){$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($iterator as $item){if($item->isDir())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($dir);}}
