<?php
function ensureTmBodAnnual(mysqli $conn): void {
 $conn->query("CREATE TABLE IF NOT EXISTS tm_bod_annual_templates (report_year SMALLINT UNSIGNED PRIMARY KEY,draft_json LONGTEXT NULL,active_json LONGTEXT NULL,version INT NOT NULL DEFAULT 0,updated_by VARCHAR(150) NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $conn->query("CREATE TABLE IF NOT EXISTS tm_bod_template_versions (report_year SMALLINT UNSIGNED NOT NULL,version INT NOT NULL,template_json LONGTEXT NOT NULL,activated_by VARCHAR(150) NOT NULL,activated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(report_year,version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function tmBodActiveTemplate(mysqli $conn,int $year): ?array {
 $q=$conn->prepare('SELECT active_json FROM tm_bod_annual_templates WHERE report_year=?');$q->bind_param('i',$year);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
 if(!empty($row['active_json']))return json_decode($row['active_json'],true,512,JSON_THROW_ON_ERROR);
 return $year===2026?json_decode(file_get_contents(__DIR__.'/../dashboard/assets/tm-bod/template-2026.json'),true,512,JSON_THROW_ON_ERROR):null;
}
function validateTmBodTemplate($config,bool $activate=false): array {
 if(!is_array($config)||!is_array($config['slides']??null)||!count($config['slides'])||count($config['slides'])>190||!is_array($config['routes']??null))throw new InvalidArgumentException('Template harus berisi 1–190 slide dan pemetaan tujuan.');
 $ids=[];$enabled=[];
 foreach($config['slides'] as &$slide){
  if(!is_array($slide)||!is_string($slide['id']??null)||!preg_match('/^[a-zA-Z0-9_-]{1,80}$/',$slide['id'])||isset($ids[$slide['id']]))throw new InvalidArgumentException('ID slide tidak valid atau berulang.');
  foreach(['title','subtitle'] as $key)if(!is_string($slide[$key]??null)||mb_strlen($slide[$key])>300)throw new InvalidArgumentException('Judul slide terlalu panjang atau tidak valid.');
  if(trim($slide['title'])===''&&trim($slide['subtitle'])==='')throw new InvalidArgumentException('Isi judul slide.');
  $slide=['id'=>$slide['id'],'title'=>trim($slide['title']),'subtitle'=>trim($slide['subtitle']),'enabled'=>($slide['enabled']??false)===true];$ids[$slide['id']]=true;if($slide['enabled'])$enabled[$slide['id']]=true;
 }
 unset($slide);
 if($activate&&!$enabled)throw new InvalidArgumentException('Aktifkan minimal satu slide.');
 foreach($config['routes'] as $key=>$targets){
  if(!is_string($key)||strlen($key)>350||!str_contains($key,'|')||!is_array($targets))throw new InvalidArgumentException('Pemetaan tujuan tidak valid.');
  foreach($targets as $id)if(!is_string($id)||!isset($ids[$id])||($activate&&!isset($enabled[$id])))throw new InvalidArgumentException('Tujuan '.$key.' menunjuk slide yang dihapus/nonaktif. Pilih penggantinya pada pemetaan tujuan.');
 }
 $result=['slides'=>array_values($config['slides']),'routes'=>$config['routes']];
 if(isset($config['design'])){
  $d=$config['design'];if(!is_array($d)||!is_string($d['uploadId']??null)||!preg_match('/^[a-f0-9]{32}$/',$d['uploadId']))throw new InvalidArgumentException('Desain upload tidak valid.');
  foreach(['cover','analysis'] as $key)if(!is_int($d[$key]??null)||$d[$key]<0||$d[$key]>11)throw new InvalidArgumentException('Nomor slide desain tidak valid.');
  foreach(['coverColor','analysisColor'] as $key)if(!is_string($d[$key]??null)||!preg_match('/^#[a-fA-F0-9]{6}$/',$d[$key]))throw new InvalidArgumentException('Warna teks desain tidak valid.');
  if(isset($d['sourceYear'])&&(!is_int($d['sourceYear'])||$d['sourceYear']<2026||$d['sourceYear']>2100))throw new InvalidArgumentException('Tahun sumber desain tidak valid.');
  $result['design']=['uploadId'=>$d['uploadId'],'cover'=>$d['cover'],'analysis'=>$d['analysis'],'coverColor'=>$d['coverColor'],'analysisColor'=>$d['analysisColor'],'sourceYear'=>(int)($d['sourceYear']??2026)];
 }
 return $result;
}
function requireTmBodAdmin(mysqli $conn): void {
 $auth=$_SERVER['HTTP_AUTHORIZATION']??'';$token=preg_match('/^Bearer ([a-f0-9]{64})$/',$auth,$m)?$m[1]:'';$hash=hash('sha256',$token);
 $q=$conn->prepare("SELECT username FROM tm_bod_transfer_sessions WHERE token_hash=? AND expires_at>NOW() AND username='admin 1'");$q->bind_param('s',$hash);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
 if(!$row){http_response_code(403);throw new InvalidArgumentException('Hanya admin 1 yang dapat mengelola template. Login ulang.');}
}
