<?php
require_once __DIR__.'/upb_distr_input.php';

function distrStoreTable(string $section): string {
    $tables=['UPB DISTR'=>'upb distr','DATA PARETO UPB'=>'data pareto upb'];
    if(!isset($tables[$section]))throw new InvalidArgumentException('Bagian UPB distributor tidak valid.');
    return $tables[$section];
}
function ensureUpbDistrInputStore($conn): void {
    static $ready=null;
    if($ready===null)$ready=new SplObjectStorage();
    if($ready->contains($conn))return;
    foreach(['UPB DISTR','DATA PARETO UPB'] as $section){$table=distrStoreTable($section);$conn->query("CREATE TABLE IF NOT EXISTS `$table` (report_period CHAR(7) PRIMARY KEY, base64 LONGTEXT NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}
    $ready->attach($conn);
}
function findUpbDistrInput($conn,string $period,string $section='UPB DISTR'): ?array {
    distrPeriod($period);$table=distrStoreTable($section);ensureUpbDistrInputStore($conn);
    // Monthly database snapshots carry January through the chosen period.
    // Pareto is a direct, exact-period sheet and is never borrowed from another month.
    $first=$section==='DATA PARETO UPB'?$period:substr($period,0,4).'-01';
    $q=$conn->prepare("SELECT report_period,base64 FROM `$table` WHERE report_period BETWEEN ? AND ? ORDER BY report_period DESC LIMIT 1");
    $q->bind_param('ss',$first,$period);$q->execute();$record=$q->get_result()->fetch_assoc();$q->close();return $record?:null;
}
function distrReadOds(string $bytes): DOMDocument {
    if(strlen($bytes)>50*1024*1024)throw new InvalidArgumentException('File maksimal 50 MB.');
    $path=tempnam(sys_get_temp_dir(),'distr-read-');$zip=new ZipArchive();$opened=false;
    try{file_put_contents($path,$bytes);if($zip->open($path)!==true)throw new InvalidArgumentException('Gunakan template ODS UPB DISTR atau DATA PARETO UPB.');$opened=true;
        $info=$zip->statName('content.xml');if(!$info||$info['size']>64*1024*1024)throw new InvalidArgumentException('Isi ODS tidak valid atau terlalu besar.');
        return upbBjXml($zip->getFromName('content.xml'));
    }finally{if($opened)$zip->close();@unlink($path);}
}
function distrHeadingPeriod(string $label,string $prefix): ?array {
    if(!preg_match('/^'.preg_quote($prefix,'/').' JAN SD ([A-Z]+) (20\d{2})$/D',strtoupper(trim($label)),$m))return null;
    $month=array_search($m[1],DISTR_SHORT,true);return $month===false?null:[(int)$m[2],$month+1];
}
function validateUpbDistrInput(string $bytes,string $period,string $section): void {
    [$year,$last]=distrPeriod($period);distrStoreTable($section);$tables=upbBjTables(distrReadOds($bytes));
    if($section==='DATA PARETO UPB'){
        $table=$tables['DATA PARETO UPB']??null;if(!$table)throw new InvalidArgumentException('Sheet DATA PARETO UPB tidak ditemukan.');
        $labels=[];$periods=[];foreach(upbBjCells($table) as $row)foreach($row as $cell){$label=strtoupper(trim(preg_replace('/\s+/',' ',$cell->textContent)));$labels[]=$label;$p=distrHeadingPeriod($label,'PERIODE');if($p)$periods[implode('-',$p)]=$p;}
        if(array_values($periods)!==[[$year,$last]])throw new InvalidArgumentException('Periode dalam DATA PARETO UPB tidak sesuai Periode Laporan.');
        foreach(['DATA PARETO UPB','BERDASARKAN UNIT PRODUK','BERDASARKAN RUPIAH PRODUK'] as $label)if(!in_array($label,$labels,true))throw new InvalidArgumentException('Pertahankan kedua tabel pada DATA PARETO UPB.');
        return;
    }
    for($month=1;$month<=12;$month++){
        $table=$tables[DISTR_MONTHS[$month-1]]??null;
        if($month>$last){if($table)throw new InvalidArgumentException('Sheet bulanan melebihi Periode Laporan.');continue;}
        if(!$table)throw new InvalidArgumentException('Sheet Januari sampai bulan pilihan harus lengkap.');
        $periods=[];foreach(upbBjCells($table) as $row)foreach($row as $cell){$p=distrHeadingPeriod($cell->textContent,'DATABASE');if($p)$periods[implode('-',$p)]=$p;}
        if(array_values($periods)!==[[$year,$month]])throw new InvalidArgumentException('Tahun atau bulan judul DATABASE tidak sesuai dengan sheet dan Periode Laporan.');
        $info=distrMonthInfo($table);if(count($info)!==count(DISTR_GROUPS)||array_diff(DISTR_GROUPS,array_keys($info)))throw new InvalidArgumentException('Pertahankan judul, header Nama Produk, dan SUB TOTAL untuk GB 1–6, ETHICAL, serta NON KONIMEX.');
    }
}
