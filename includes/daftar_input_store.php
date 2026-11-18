<?php
require_once __DIR__.'/daftar_input_template.php';
require_once __DIR__.'/ods_upload_xml.php';

function daftarInputPeriod(string $period): array {
    if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/D', $period, $m)) throw new InvalidArgumentException('Periode DAFTAR tidak valid.');
    return [(int)$m[1], (int)$m[2]];
}
function ensureDaftarInputStore(mysqli $conn): void {
    static $ready = null;
    if ($ready === null) $ready = new SplObjectStorage();
    if ($ready->contains($conn)) return;
    $conn->query('CREATE TABLE IF NOT EXISTS `daftar` (report_period CHAR(7) NOT NULL PRIMARY KEY, filename VARCHAR(255) NOT NULL, base64 LONGTEXT NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $ready->attach($conn);
}
function findDaftarInput(mysqli $conn, string $period): ?array {
    [$year] = daftarInputPeriod($period); ensureDaftarInputStore($conn);
    $first = $year.'-01';
    $q = $conn->prepare('SELECT report_period,base64 FROM `daftar` WHERE report_period BETWEEN ? AND ? ORDER BY report_period DESC LIMIT 1');
    $q->bind_param('ss', $first, $period); $q->execute(); $record = $q->get_result()->fetch_assoc(); $q->close();
    return $record ?: null;
}
function validateDaftarInputBytes(string $bytes, string $period): void {
    [$year,$month] = daftarInputPeriod($period);
    if (strlen($bytes) > 50*1024*1024) throw new InvalidArgumentException('File maksimal 50 MB.');
    $path = tempnam(sys_get_temp_dir(),'daftar-upload-'); $zip = new ZipArchive(); $opened = false;
    try {
        file_put_contents($path,$bytes);
        if ($zip->open($path) !== true) throw new InvalidArgumentException('Gunakan template DAFTAR berformat ODS.');
        $opened = true;
        if (trim((string)$zip->getFromName('mimetype')) !== 'application/vnd.oasis.opendocument.spreadsheet') throw new InvalidArgumentException('Gunakan template DAFTAR berformat ODS.');
        for ($i=0;$i<$zip->numFiles;$i++) if (str_starts_with($zip->getNameIndex($i),'Basic/')) throw new InvalidArgumentException('Gunakan template DAFTAR terbaru dengan isian V/X tanpa makro.');
        $zip->close(); $opened = false;
        $doc = loadOdsUploadDocument($path); $tables = upbBjTables($doc);
        if (array_keys($tables) !== ['DAFTAR']) throw new InvalidArgumentException('Upload template raw data dengan sheet DAFTAR saja.');
        $labels = [];
        foreach ($tables['DAFTAR']->getElementsByTagNameNS(UPB_X,'p') as $p) $labels[] = preg_replace('/\s+/u',' ',trim($p->textContent));
        if (!in_array('JT',$labels,true)) throw new InvalidArgumentException('Header JT tidak ditemukan pada sheet DAFTAR.');
        $titles = []; $ytds = [];
        foreach ($labels as $label) {
            if (preg_match('/^Periode\s*:\s*(20\d{2})$/iu',$label,$m)) $titles[] = (int)$m[1];
            if (preg_match('/^YTD\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(20\d{2})$/iu',$label,$m)) $ytds[] = [strtoupper($m[1]),(int)$m[2]];
        }
        $months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
        if (!$titles || array_diff($titles,[$year]) || !$ytds) throw new InvalidArgumentException('Tahun pada sheet DAFTAR tidak sesuai periode upload.');
        foreach ($ytds as [$short,$y]) if ($y !== $year || $short !== $months[$month-1]) throw new InvalidArgumentException('YTD pada sheet DAFTAR tidak sesuai periode upload.');
    } finally { if ($opened) $zip->close(); @unlink($path); }
}
function saveDaftarInput(mysqli $conn, string $period, string $bytes, string $filename): void {
    validateDaftarInputBytes($bytes,$period); ensureDaftarInputStore($conn);
    $filename = mb_substr(basename($filename),0,255); $base64 = base64_encode($bytes);
    $q = $conn->prepare('INSERT INTO `daftar` (report_period,filename,base64) VALUES (?,?,?) ON DUPLICATE KEY UPDATE filename=VALUES(filename),base64=VALUES(base64)');
    $q->bind_param('sss',$period,$filename,$base64); $q->execute(); $q->close();
}
/** Keep user rows, formulas and formatting; update only the requested YTD heading. */
function buildDaftarStoredInputTemplateOds(string $period, ?string $saved): string {
    [$year,$month] = daftarInputPeriod($period);
    if ($saved === null) return buildDaftarInputTemplateOds($period);
    $months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    $path = tempnam(sys_get_temp_dir(),'daftar-history-'); $zip = new ZipArchive(); $opened = false;
    try {
        file_put_contents($path,$saved);
        if ($zip->open($path) !== true) throw new RuntimeException('File DAFTAR tersimpan tidak valid.');
        $opened = true; $doc = loadOdsUploadDocument($path);
        foreach ($doc->getElementsByTagNameNS(UPB_X,'p') as $p) {
            $value = trim($p->textContent); $new = null;
            if (preg_match('/^Periode\s*:\s*20\d{2}$/iu',$value)) $new = 'Periode : '.$year;
            elseif (preg_match('/^YTD\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+20\d{2}$/iu',$value)) $new = 'YTD '.$months[$month-1].' '.$year;
            if ($new !== null && $new !== $value) { while ($p->firstChild) $p->removeChild($p->firstChild); $p->appendChild($doc->createTextNode($new)); }
        }
        $zip->addFromString('content.xml',$doc->saveXML()); $zip->close(); $opened = false;
        return file_get_contents($path);
    } finally { if ($opened) $zip->close(); @unlink($path); }
}
function loadDaftarInputTemplateOds(mysqli $conn, string $period): string {
    $record = findDaftarInput($conn,$period);
    $saved = $record ? base64_decode($record['base64'],true) : null;
    if ($saved === false) throw new RuntimeException('Data DAFTAR tersimpan tidak valid.');
    if ($record && $record['report_period'] === $period) return $saved;
    return buildDaftarStoredInputTemplateOds($period,$saved);
}
