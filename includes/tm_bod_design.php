<?php
function ensureTmBodDesign(mysqli $conn): void {
 $conn->query("CREATE TABLE IF NOT EXISTS tm_bod_design_uploads (id CHAR(32) PRIMARY KEY,report_year SMALLINT UNSIGNED NOT NULL,original_name VARCHAR(255) NOT NULL,source_sha256 CHAR(64) NOT NULL,pages_json LONGTEXT NOT NULL,uploaded_by VARCHAR(150) NOT NULL,uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_tm_design_year(report_year)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function tmBodDesignPages(mysqli $conn,string $id,int $year): array {
 $q=$conn->prepare('SELECT pages_json FROM tm_bod_design_uploads WHERE id=? AND report_year=?');$q->bind_param('si',$id,$year);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();
 if(!$row)throw new InvalidArgumentException('Desain PowerPoint tidak ditemukan untuk tahun ini. Unggah desain untuk tahun tujuan.');
 return json_decode($row['pages_json'],true,512,JSON_THROW_ON_ERROR);
}
function tmBodBackgrounds(mysqli $conn,?array $template,int $year): ?array {
 $design=$template['design']??null;if(!$design)return null;
 $pages=tmBodDesignPages($conn,$design['uploadId'],(int)($design['sourceYear']??$year));
 $result=[];foreach(['cover','analysis'] as $key){$index=$design[$key];if(!isset($pages[$index]))throw new InvalidArgumentException('Nomor slide desain tidak tersedia.');$result[$key]=$pages[$index];}
 $result['uploadId']=$design['uploadId'];return $result;
}
