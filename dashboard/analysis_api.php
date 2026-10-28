<?php
require_once __DIR__.'/../includes/database.php';
require_once __DIR__.'/../includes/analysis_locks.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$origin=$_SERVER['HTTP_ORIGIN']??'';
if(in_array($origin,['null','http://localhost:8000','http://127.0.0.1:8000'],true)) {
    header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');header('Access-Control-Allow-Headers: Content-Type');
}
if(($_SERVER['REQUEST_METHOD']??'GET')==='OPTIONS'){http_response_code(204);exit;}
$conn=null;
$stage='connection';
try {
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if(!in_array($method,['GET','POST'],true)){http_response_code(405);throw new InvalidArgumentException('Metode tidak didukung.');}
    $conn=openReportConnection();
    $stage='schema';
    $conn->query("SET time_zone = '+07:00'");
    // Keep the runtime schema self-contained; SQL editor files may be removed locally.
    $conn->query("CREATE TABLE IF NOT EXISTS report_analysis (
        id VARCHAR(64) NOT NULL PRIMARY KEY,
        report_name VARCHAR(200) NOT NULL,
        tab_name VARCHAR(120) NOT NULL,
        analysis_text MEDIUMTEXT NOT NULL,
        analysis_html MEDIUMTEXT NULL,
        original_created_label VARCHAR(150) NULL,
        created_by VARCHAR(150) NULL,
        created_role VARCHAR(80) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_by VARCHAR(150) NULL,
        updated_role VARCHAR(80) NULL,
        updated_at DATETIME NULL,
        INDEX idx_analysis_report_tab (report_name, tab_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Existing installations may predate the optional legacy creation label.
    $legacyColumn=$conn->query("SHOW COLUMNS FROM report_analysis LIKE 'original_created_label'");
    $needsLegacyColumn=$legacyColumn->num_rows===0;
    $legacyColumn->free();
    if($needsLegacyColumn) {
        try {
            $conn->query('ALTER TABLE report_analysis ADD COLUMN original_created_label VARCHAR(150) NULL');
        } catch(mysqli_sql_exception $error) {
            // Another simultaneous request may have added the same column.
            if((int)$error->getCode()!==1060)throw $error;
        }
    }
    $stage='query';
    if($method==='GET') {
        $result=$conn->query('SELECT * FROM report_analysis ORDER BY created_at DESC,id DESC');$notes=[];
        while($row=$result->fetch_assoc()) {
            $key=$row['report_name'].'|'.$row['tab_name'];
            $notes[$key][]=['id'=>$row['id'],'text'=>$row['analysis_text'],'html'=>$row['analysis_html'],
                'date'=>$row['created_at'],'originalDate'=>$row['original_created_label'],'createdBy'=>$row['created_by']===null?null:['username'=>$row['created_by'],'role'=>$row['created_role']],
                'updatedAt'=>$row['updated_at'],'updatedBy'=>$row['updated_by']===null?null:['username'=>$row['updated_by'],'role'=>$row['updated_role']]];
        }
        echo json_encode(['success'=>true,'data'=>$notes],JSON_THROW_ON_ERROR);exit;
    }
    $data=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    $id=$data['id']??'';$report=$data['report']??'';$tab=$data['tab']??'';$action=$data['action']??'';
    if(!is_string($id)||!preg_match('/^[a-zA-Z0-9_-]{1,64}$/',$id)||!is_string($report)||$report===''||strlen($report)>200||!is_string($tab)||$tab===''||strlen($tab)>120)throw new InvalidArgumentException('Identitas analisis tidak valid.');
    ensureAnalysisLockTable($conn);
    $conn->begin_transaction();
    requireAnalysisLock($conn,$report,$tab,is_string($data['lockToken']??null)?$data['lockToken']:'');
    if($action==='delete') {
        $q=$conn->prepare('DELETE FROM report_analysis WHERE id=? AND report_name=? AND tab_name=?');$q->bind_param('sss',$id,$report,$tab);$q->execute();$q->close();
    } elseif(in_array($action,['create','update'],true)) {
        $text=$data['text']??'';$html=$data['html']??null;$user=$data['user']??null;
        if(!is_string($text)||trim($text)===''||strlen($text)>1000000||($html!==null&&(!is_string($html)||strlen($html)>2000000)))throw new InvalidArgumentException('Isi analisis tidak valid.');
        $originalDate=isset($data['originalDate'])&&is_string($data['originalDate'])?substr($data['originalDate'],0,150):null;
        $name=is_array($user)&&is_string($user['username']??null)?substr($user['username'],0,150):null;
        $role=is_array($user)&&is_string($user['role']??null)?substr($user['role'],0,80):null;
        if($action==='create') {
            $q=$conn->prepare('INSERT INTO report_analysis (id,report_name,tab_name,analysis_text,analysis_html,created_by,created_role,original_created_label) VALUES (?,?,?,?,?,?,?,?)');$q->bind_param('ssssssss',$id,$report,$tab,$text,$html,$name,$role,$originalDate);
        } else {
            $q=$conn->prepare('UPDATE report_analysis SET analysis_text=?,analysis_html=?,updated_by=?,updated_role=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND report_name=? AND tab_name=?');$q->bind_param('sssssss',$text,$html,$name,$role,$id,$report,$tab);
        }
        $q->execute();$q->close();
    } else throw new InvalidArgumentException('Aksi analisis tidak valid.');
    $conn->commit();
    echo json_encode(['success'=>true]);
} catch(Throwable $error) {
    if($conn){try{$conn->rollback();}catch(Throwable $ignored){}}
    error_log('Analysis API: '.$error->getMessage());
    if(http_response_code()<400)http_response_code($error instanceof InvalidArgumentException?400:500);
    $messages=[
        'connection'=>'Koneksi database analisis gagal. Pastikan server PHP memakai konfigurasi TiDB (DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME), atau MySQL lokal sudah aktif.',
        'schema'=>'Persiapan tabel report_analysis gagal. Periksa database yang dipilih dan izin database pada server PHP.',
        'query'=>'Query report_analysis gagal. Pastikan struktur tabel sesuai dan akun database memiliki izin baca/tulis.'
    ];
    $message=$error instanceof InvalidArgumentException?$error->getMessage():$messages[$stage];
    $code=$error instanceof mysqli_sql_exception?(int)$error->getCode():0;
    if($code) {
        $details=[
            1054=>'Struktur report_analysis belum sesuai: ada kolom yang belum tersedia.',
            1062=>'ID analisis sudah ada. Muat ulang daftar sebelum menyimpan kembali.',
            1142=>'Akun database belum memiliki izin menjalankan operasi ini.',
            1264=>'Nilai melebihi kapasitas kolom. Periksa id: harus VARCHAR(64), bukan INT.',
            1364=>'Ada kolom wajib pada tabel yang tidak memiliki nilai default.',
            1406=>'Isi data melebihi panjang kolom pada tabel report_analysis.'
        ];
        $message=($details[$code]??$message).' (kode '.$code.')';
        if($code===1054 && preg_match("/Unknown column '([a-zA-Z0-9_]+)'/",$error->getMessage(),$match))$message.=' Kolom: '.$match[1].'.';
    }
    echo json_encode(['success'=>false,'message'=>$message,'stage'=>$stage,'code'=>$code]);
} finally {if($conn)$conn->close();}
