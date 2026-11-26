<?php
// Metadata only. Never reads workbook contents.
function uploadFileCatalog(): array {
    return ['IB','ETHICAL','MTM','MNJ','UNIT & Revenue','TARGET UNIT SELL OUT','REVENUE VERSI EDP MNJ Target','BIAYA','TARGET REVENUE','DAFTAR','LAUNCH','SERVICE LEVEL','BOD KX Sell In','BOD MNJ','BOD JML SDM','BOD BI.Opr & BOD BI.SDM','RATIO UPB','UPB BJ kx','UPB non BJ','UPB DISTR','DATA PARETO UPB','complaint - Accident','NEAR ED TW 3','TOP 10 NEAR ED TW 3','Revenue Konimex Selling Out'];
}
function uploadCanonicalSection(string $section): string {
    return $section==='REVENUE VERSI EDP MNJ'?'UNIT & Revenue':$section;
}
function uploadLockSchema($db): void {
    $sql=file_get_contents(__DIR__.'/../database/migrations/2026-11-24-upload-file-locks.sql');
    foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$db->query($statement);
    $db->query("SET time_zone='+07:00'");
}
function uploadLockQuery($db,string $sql,string $types='',array $values=[]) {
    $q=$db->prepare($sql);if($types!=='')$q->bind_param($types,...$values);$q->execute();return $q;
}
function uploadSessionUser($db,string $token): string {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('Sesi upload tidak valid. Silakan login ulang.',401);
    $q=uploadLockQuery($db,'SELECT username FROM report_upload_sessions WHERE token_hash=? AND expires_at>NOW()','s',[hash('sha256',$token)]);
    $row=$q->get_result()->fetch_assoc();$q->close();
    if(!$row)throw new RuntimeException('Sesi upload berakhir. Silakan login ulang.',401);
    return $row['username'];
}
function uploadCanManage(string $user): bool { return $user==='admin 2'; }
function uploadCanUnlock(string $user): bool { return $user==='admin 2'; }
function uploadValidateScope(string $section,string $period): void {
    if(!in_array($section,uploadFileCatalog(),true)||!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$period))throw new InvalidArgumentException('Bagian file atau periode tidak valid.');
}
function uploadEndpointScope(string $endpoint,array $data): ?array {
    $map=['daftar_input_api'=>'DAFTAR','launch_input_api'=>'LAUNCH','service_level_input_api'=>'SERVICE LEVEL','bod_jml_sdm_input_api'=>'BOD JML SDM','bod_bi_input_api'=>'BOD BI.Opr & BOD BI.SDM','bod_kx_sell_in_api'=>'BOD KX Sell In','bod_mnj_api'=>'BOD MNJ','biaya_api'=>'BIAYA','mnj_api'=>'MNJ','mtm_appendix_api'=>'MTM','upb_non_bj_input_api'=>'UPB non BJ','upb_bj_input_api'=>'UPB BJ kx','unit_sell_out_targets_api'=>'TARGET UNIT SELL OUT','revenue_so_targets_api'=>'TARGET REVENUE','revenue_edp_mnj_target_api'=>'REVENUE VERSI EDP MNJ Target'];
    $section=$endpoint==='upload'?uploadCanonicalSection((string)($data['divisi']??'')):($endpoint==='upb_distr_input_api'?(string)($data['section']??''):($map[$endpoint]??null));
    if($section===null)return null;
    $period=$data['period']??'';
    if(in_array($endpoint,['revenue_so_targets_api','revenue_edp_mnj_target_api'],true)&&isset($data['year'],$data['month'])){
        $native=sprintf('%04d-%02d',(int)$data['year'],(int)$data['month']);
        if($period!==''&&$period!==$native)throw new InvalidArgumentException('Periode target berbeda dari tahun/bulan payload.');
        $period=$native;
    }
    uploadValidateScope($section,(string)$period);return [$section,(string)$period];
}
function uploadIsAnnual(string $section): bool {
    return in_array($section,['ETHICAL','TARGET UNIT SELL OUT','TARGET REVENUE','REVENUE VERSI EDP MNJ Target'],true);
}
function uploadMetadataPredicate(string $section,string $table,array $columns,string $period): ?array {
    $year=substr($period,0,4);
    if(uploadIsAnnual($section)){
        foreach(['report_year','periode_tahun'] as $column)if(in_array($column,$columns,true))return ['`'.$column.'`=?','s',[$year]];
        if($table==='target_revenue'&&in_array('product',$columns,true))return ['product LIKE ?','s',[$year.' | %']];
        if(in_array('report_period',$columns,true))return ['report_period BETWEEN ? AND ?','ss',[$year.'-01',$year.'-12']];
    }
    return in_array('report_period',$columns,true)?['report_period=?','s',[$period]]:null;
}
function uploadLegacyMetadata($db,string $section,string $period): ?array {
    if(uploadIsAnnual($section)){
        $year=substr($period,0,4);
        $q=uploadLockQuery($db,'SELECT filename,uploaded_at,uploaded_by FROM report_upload_files WHERE section=? AND report_period BETWEEN ? AND ? AND has_upload=1 ORDER BY uploaded_at DESC,revision DESC,report_period DESC LIMIT 1','sss',[$section,$year.'-01',$year.'-12']);
        $saved=$q->get_result()->fetch_assoc();$q->close();if($saved)return $saved;
    }
    $tables=['IB'=>['revenue_ibfm'],'ETHICAL'=>['template ethical','ethical HNA dan UNIT','ETHICAL'],'TARGET UNIT SELL OUT'=>['target unit sell out'],'TARGET REVENUE'=>['target_revenue'],'REVENUE VERSI EDP MNJ Target'=>['revenue_edp_mnj_target'],'MTM'=>['mtm_monthly_values'],'MNJ'=>['mnj_input_files'],'UNIT & Revenue'=>['UNIT','revenue_edp_mnj'],'BIAYA'=>['biaya_uploads'],'DAFTAR'=>['daftar'],'LAUNCH'=>['launch_input'],'SERVICE LEVEL'=>['service_level_input'],'BOD KX Sell In'=>['bod_kx_sell_in_monthly'],'BOD MNJ'=>['BOD MNJ','bod_mnj_files'],'BOD JML SDM'=>['bod_jml_sdm_input'],'BOD BI.Opr & BOD BI.SDM'=>['bod bi opr sdm'],'UPB BJ kx'=>['upb bj kx','upb_bj_input_files'],'UPB non BJ'=>['upb non bj'],'UPB DISTR'=>['upb distr'],'DATA PARETO UPB'=>['data pareto upb']];
    foreach($tables[$section]??[] as $table){
        $q=uploadLockQuery($db,'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?','s',[$table]);$columnRows=$q->get_result()->fetch_all(MYSQLI_ASSOC);$columns=[];foreach($columnRows as $columnRow){$value=array_values($columnRow)[0]??'';$columns[]=strtolower((string)$value);}$q->close();
        $predicate=uploadMetadataPredicate($section,$table,$columns,$period);if(!$predicate)continue;
        $date=in_array('uploaded_at',$columns,true)?'uploaded_at':(in_array('updated_at',$columns,true)?'updated_at':null);
        $select=($date?'MAX(`'.$date.'`)':'NULL').' AS uploaded_at,'.(in_array('filename',$columns,true)?'MAX(filename)':'NULL').' AS filename';
        $q=uploadLockQuery($db,'SELECT COUNT(*) AS total,'.$select.' FROM `'.$table.'` WHERE '.$predicate[0],$predicate[1],$predicate[2]);$row=$q->get_result()->fetch_assoc();$q->close();
        if(!empty($row['total']))return $row;
    }return null;
}
function uploadSeedLegacy($db,string $section,string $period): void {
    $q=uploadLockQuery($db,'SELECT section,has_upload,unlocked_by FROM report_upload_files WHERE section=? AND report_period=?','ss',[$section,$period]);$exists=$q->get_result()->fetch_assoc();$q->close();
    if($exists&&(!empty($exists['has_upload'])||!empty($exists['unlocked_by'])))return;
    $legacy=uploadLegacyMetadata($db,$section,$period);
    if(!$legacy){
        $effective=uploadEffectiveMetadata($db,$section,$period);if(!$effective)return;
        // Coverage is not a new upload. Store only its access lock; metadata is
        // still read from the actual source month when the list is displayed.
        $q=uploadLockQuery($db,'INSERT IGNORE INTO report_upload_files(section,report_period,locked) VALUES (?,?,1)','ss',[$section,$period]);$q->close();
        $q=uploadLockQuery($db,'UPDATE report_upload_files SET locked=1 WHERE section=? AND report_period=? AND has_upload=0 AND unlocked_by IS NULL','ss',[$section,$period]);$q->close();
        return;
    }
    $actor=$legacy['uploaded_by']??null;
    $q=uploadLockQuery($db,'INSERT IGNORE INTO report_upload_files(section,report_period,has_upload,locked,filename,uploaded_at,uploaded_by) VALUES (?,?,1,1,?,?,?)','sssss',[$section,$period,$legacy['filename'],$legacy['uploaded_at'],$actor]);$q->close();
    {
        $q=uploadLockQuery($db,'UPDATE report_upload_files SET has_upload=1,locked=1,filename=?,uploaded_at=?,uploaded_by=? WHERE section=? AND report_period=? AND has_upload=0 AND unlocked_by IS NULL','sssss',[$legacy['filename'],$legacy['uploaded_at'],$actor,$section,$period]);$q->close();
    }
}
function uploadEffectivePeriodSql(string $section,string $period): array {
    $year=substr($period,0,4);$first=$year.'-01';$last=$year.'-12';
    if(in_array($section,['SERVICE LEVEL','IB','MTM','MNJ','UNIT & Revenue','BIAYA','BOD MNJ','BOD BI.Opr & BOD BI.SDM','UPB non BJ','UPB DISTR'],true))return ['report_period BETWEEN ? AND ?','(report_period >= ?) DESC, CASE WHEN report_period >= ? THEN report_period END ASC, report_period DESC','ssss',[$first,$last,$period,$period]];
    if($section==='UPB BJ kx')return ['report_period BETWEEN ? AND ?','report_period DESC','ss',[$first,$last]];
    if(in_array($section,['DAFTAR','LAUNCH','BOD MNJ','BOD BI.Opr & BOD BI.SDM','UPB non BJ','UPB DISTR'],true))return ['report_period BETWEEN ? AND ?','report_period DESC','ss',[$first,$period]];
    return ['report_period=?','report_period DESC','s',[$period]];
}
function uploadEffectiveMetadata($db,string $section,string $period): ?array {
    // Checklist coverage includes later cumulative uploads in the same year; never open stored workbooks.
    $tables=['IB'=>['revenue_ibfm'],'MTM'=>['mtm_monthly_values'],'MNJ'=>['mnj_input_files'],'UNIT & Revenue'=>['UNIT','revenue_edp_mnj'],'BIAYA'=>['biaya_uploads'],'DAFTAR'=>['daftar'],'LAUNCH'=>['launch_input'],'SERVICE LEVEL'=>['service_level_input'],'BOD MNJ'=>['BOD MNJ','bod_mnj_files'],'BOD JML SDM'=>['bod_jml_sdm_input'],'BOD BI.Opr & BOD BI.SDM'=>['bod bi opr sdm'],'BOD KX Sell In'=>['bod_kx_sell_in_monthly'],'UPB BJ kx'=>['upb bj kx','upb_bj_input_files'],'UPB non BJ'=>['upb non bj'],'UPB DISTR'=>['upb distr']];
    if(!isset($tables[$section]))return null;
    [$where,$order,$types,$parameters]=uploadEffectivePeriodSql($section,$period);
    foreach($tables[$section] as $table){
        $q=uploadLockQuery($db,'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?','s',[$table]);$columns=[];foreach($q->get_result()->fetch_all(MYSQLI_ASSOC) as $row)$columns[]=strtolower((string)(array_values($row)[0]??''));$q->close();
        if(!in_array('report_period',$columns,true))continue;
        $date=in_array('uploaded_at',$columns,true)?'uploaded_at':(in_array('updated_at',$columns,true)?'updated_at':null);
        $fields='report_period,'.($date?'`'.$date.'`':'NULL').' AS uploaded_at,'.(in_array('filename',$columns,true)?'filename':'NULL').' AS filename';
        $q=uploadLockQuery($db,'SELECT '.$fields.' FROM `'.$table.'` WHERE '.$where.' ORDER BY '.$order.' LIMIT 1',$types,$parameters);$row=$q->get_result()->fetch_assoc();$q->close();if(!$row)continue;
        $source=$row['report_period'];$q=uploadLockQuery($db,'SELECT uploaded_by,uploaded_at,filename FROM report_upload_files WHERE section=? AND report_period=? AND has_upload=1','ss',[$section,$source]);$tracked=$q->get_result()->fetch_assoc();$q->close();
        return array_merge($row,$tracked?:[],['source_period'=>$source,'has_upload'=>1]);
    }return null;
}
function uploadLockBegin($db): void {
    $version=$db->server_info??'';
    if(stripos($version,'TiDB')!==false)$db->query('BEGIN PESSIMISTIC');else $db->begin_transaction();
}
function uploadLockReserve($db,string $section,string $period): void {
    // Create the durable row before beginning the transaction (also required by TiDB).
    $q=uploadLockQuery($db,'INSERT IGNORE INTO report_upload_files(section,report_period) VALUES (?,?)','ss',[$section,$period]);$q->close();
    uploadLockBegin($db);
    try {
        $q=uploadLockQuery($db,'SELECT locked FROM report_upload_files WHERE section=? AND report_period=? FOR UPDATE','ss',[$section,$period]);$row=$q->get_result()->fetch_assoc();$q->close();
        if(!$row||$row['locked'])throw new RuntimeException('Upload '.$section.' periode '.$period.' terkunci. Minta admin 2 membuka kunci',423);
    } catch(Throwable $error){$db->rollback();throw $error;}
}
function uploadLockFinish($db,string $section,string $period,string $user,string $filename,bool $success): void {
    if(!$success){$db->rollback();return;}
    $q=uploadLockQuery($db,'UPDATE report_upload_files SET has_upload=1,locked=1,filename=?,uploaded_by=?,uploaded_at=NOW(),revision=revision+1 WHERE section=? AND report_period=?','ssss',[$filename,$user,$section,$period]);$q->close();
    $q=uploadLockQuery($db,"INSERT INTO report_upload_events(section,report_period,action,actor,filename,happened_at) VALUES (?,?,'upload',?,?,NOW())",'ssss',[$section,$period,$user,$filename]);$q->close();$db->commit();
}
function uploadLockUnlock($db,string $section,string $period,string $user): void {
    if(!uploadCanUnlock($user))throw new RuntimeException('Hanya admin 2 yang dapat membuka kunci.',403);
    uploadValidateScope($section,$period);uploadLockBegin($db);
    try {
        $q=uploadLockQuery($db,'SELECT locked FROM report_upload_files WHERE section=? AND report_period=? FOR UPDATE','ss',[$section,$period]);$row=$q->get_result()->fetch_assoc();$q->close();
        if(!$row)throw new InvalidArgumentException('Bagian ini belum mempunyai upload tercatat.');
        if($row['locked']){
            $q=uploadLockQuery($db,'UPDATE report_upload_files SET locked=0,unlocked_by=?,unlocked_at=NOW() WHERE section=? AND report_period=?','sss',[$user,$section,$period]);$q->close();
            $q=uploadLockQuery($db,"INSERT INTO report_upload_events(section,report_period,action,actor,happened_at) VALUES (?,?,'unlock',?,NOW())",'sss',[$section,$period,$user]);$q->close();
        }$db->commit();
    }catch(Throwable $e){$db->rollback();throw $e;}
}
function uploadLockRelock($db,string $section,string $period,string $user): void {
    if(!uploadCanUnlock($user))throw new RuntimeException('Hanya admin 2 yang dapat mengunci kembali.',403);
    uploadValidateScope($section,$period);uploadLockBegin($db);
    try {
        $q=uploadLockQuery($db,'SELECT locked FROM report_upload_files WHERE section=? AND report_period=? FOR UPDATE','ss',[$section,$period]);$row=$q->get_result()->fetch_assoc();$q->close();
        if(!$row)throw new InvalidArgumentException('Bagian ini belum mempunyai upload tercatat.');
        if(!$row['locked']){
            $q=uploadLockQuery($db,'UPDATE report_upload_files SET locked=1 WHERE section=? AND report_period=?','ss',[$section,$period]);$q->close();
            $q=uploadLockQuery($db,"INSERT INTO report_upload_events(section,report_period,action,actor,happened_at) VALUES (?,?,'lock',?,NOW())",'sss',[$section,$period,$user]);$q->close();
        }$db->commit();
    }catch(Throwable $e){$db->rollback();throw $e;}
}
function uploadGuardCors(): void {
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if(in_array($origin,['null','http://localhost:8000','http://127.0.0.1:8000'],true)){header('Access-Control-Allow-Origin: '.$origin);header('Vary: Origin');header('Access-Control-Allow-Headers: Content-Type, Authorization');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');}
}
function guardReportFileUpload(string $endpoint): void {
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;
    uploadGuardCors();$db=null;
    try {
        $json=str_contains(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json')?json_decode(file_get_contents('php://input'),true):[];
        $data=array_merge($_GET,is_array($json)?$json:[],$_POST);
        $scope=uploadEndpointScope($endpoint,$data);if(!$scope)return;
        require_once __DIR__.'/database.php';$db=openReportConnection();uploadLockSchema($db);
        $user=uploadSessionUser($db,(string)($data['upload_token']??''));
        if(!in_array($user,['admin 1','admin 2','test 1','pharma'],true))throw new RuntimeException('Akun ini tidak diizinkan mengunggah file.',403);
        [$section,$period]=$scope;
        if($user==='pharma'&&!in_array($section,reportPharmaUploads(),true))throw new RuntimeException('Upload akun pharma hanya untuk data GB 1 dan GB 4.',403);
        $filename=basename(str_replace('\\','/',(string)($_FILES['file']['name']??$data['upload_filename']??$data['source']??'Upload '.$section)));
        $filename=mb_substr($filename,0,255);
        uploadSeedLegacy($db,$section,$period);
        uploadLockReserve($db,$section,$period);
        $GLOBALS['reportUploadAuthorizedScope']=[$section,$period];
        // Endpoints commit their data before returning success. Hold the metadata row
        // lock until then so a second request cannot replace the same section/period.
        ob_start(static function(string $body)use($db,$section,$period,$user,$filename):string{
            $result=json_decode($body,true);$success=http_response_code()<400&&is_array($result)&&($result['success']??false)===true;
            try{uploadLockFinish($db,$section,$period,$user,$filename,$success);}
            catch(Throwable $error){$db->rollback();error_log('Upload lock finalization failed');http_response_code(500);$body=json_encode(['success'=>false,'message'=>'Status kunci upload gagal disimpan. Hubungi admin 2 sebelum mencoba ulang.']);}
            finally{$db->close();}return $body;
        });
    }catch(Throwable $error){if($db)$db->close();$code=(int)$error->getCode();http_response_code(in_array($code,[401,403,423],true)?$code:($error instanceof InvalidArgumentException?400:503));header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'message'=>$error instanceof InvalidArgumentException||in_array($code,[401,403,423],true)?$error->getMessage():'Penguncian upload belum tersedia. Periksa migrasi database.']);exit;}
}
