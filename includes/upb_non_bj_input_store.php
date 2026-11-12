<?php
require_once __DIR__.'/upb_bj_template_history.php';
function ensureUpbNonBjInputStore($conn): void {
    $conn->query('CREATE TABLE IF NOT EXISTS `upb non bj` (report_period CHAR(7) PRIMARY KEY, base64 LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function findUpbNonBjTemplate($conn,string $period): ?array {
    ensureUpbNonBjInputStore($conn);
    $first=substr($period,0,4).'-01';
    $q=$conn->prepare('SELECT report_period,base64 FROM `upb non bj` WHERE report_period BETWEEN ? AND ? ORDER BY report_period DESC LIMIT 1');
    $q->bind_param('ss',$first,$period);$q->execute();$record=$q->get_result()->fetch_assoc();$q->close();return $record?:null;
}
function validateUpbNonBjOds(string $bytes,string $period): void {
    if(!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D',$period,$parts))throw new InvalidArgumentException('Periode UPB NON BJ tidak valid.');
    if(strlen($bytes)>50*1024*1024)throw new InvalidArgumentException('File maksimal 50 MB.');
    $p=tempnam(sys_get_temp_dir(),'nonbj-validate-');$z=new ZipArchive();$opened=false;
    try {
        file_put_contents($p,$bytes);if($z->open($p)!==true)throw new InvalidArgumentException('Gunakan template ODS UPB NON BJ.');$opened=true;
        $info=$z->statName('content.xml');if(!$info||$info['size']>64*1024*1024)throw new InvalidArgumentException('Isi ODS tidak valid atau terlalu besar.');
        $d=upbBjXml($z->getFromName('content.xml'));$labels=[];
        foreach(upbBjTables($d) as $table)foreach(upbBjCells($table) as $row)foreach($row as $cell)$labels[]=strtoupper(trim(preg_replace('/\s+/',' ',$cell->textContent)));
        $text=implode("\n",$labels);$years=[];$months=[];
        $aliases=['JANUARI|JANUARY|JAN','FEBRUARI|FEBRUARY|FEB','MARET|MARCH|MAR','APRIL|APR','MEI|MAY','JUNI|JUNE|JUN','JULI|JULY|JUL','AGUSTUS|AUGUST|AGT|AGS|AUG','SEPTEMBER|SEP','OKTOBER|OCTOBER|OKT|OCT','NOVEMBER|NOPEMBER|NOV|NOP','DESEMBER|DECEMBER|DES|DEC'];
        foreach($labels as $label){
            if(preg_match('/UPB\s+NON\s+BJ\s+KONIMEX\s+TH\.?\s*(20\d{2})/',$label,$m))$years[]=$m[1];
            foreach($aliases as $i=>$alias)if(preg_match('/\bYTD\s+(?:'.$alias.')\b/',$label))$months[]=$i+1;
        }
        if(array_values(array_unique($years))!==[$parts[1]]||array_values(array_unique($months))!==[(int)$parts[2]])throw new InvalidArgumentException('Tahun atau YTD dalam file tidak sesuai Periode Laporan.');
        foreach(['/UPB\s+BAHAN\s+BAKU/','/UPB\s+BAHAN\s+KEMAS/','/UPB\s+WIP/','/TOTAL UPB NON BJ/'] as $pattern)if(!preg_match($pattern,$text))throw new InvalidArgumentException('Tabel ringkasan, Bahan Baku, Bahan Kemas, dan WIP harus lengkap.');
    } finally {if($opened)$z->close();@unlink($p);}
}
