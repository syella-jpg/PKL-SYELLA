<?php
function ensureAnalysisLockTable(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS report_analysis_locks (
        scope_id CHAR(64) NOT NULL PRIMARY KEY,
        owner_token VARCHAR(64) NOT NULL,
        owner_name VARCHAR(150) NOT NULL,
        expires_at DATETIME NOT NULL,
        INDEX idx_analysis_lock_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function analysisLockScope(string $report, string $tab): string { return hash('sha256',$report."\0".$tab); }
function readAnalysisLock(mysqli $conn,string $scope,bool $forUpdate=false): ?array
{
    $q=$conn->prepare('SELECT owner_token,owner_name,(expires_at>NOW()) AS active FROM report_analysis_locks WHERE scope_id=?'.($forUpdate?' FOR UPDATE':''));
    $q->bind_param('s',$scope);$q->execute();$row=$q->get_result()->fetch_assoc();$q->close();return $row;
}
/** Caller holds this row lock through its analysis write transaction. */
function requireAnalysisLock(mysqli $conn,string $report,string $tab,string $token): void
{
    $row=readAnalysisLock($conn,analysisLockScope($report,$tab),true);
    if(!$row || !$row['active'] || !hash_equals($row['owner_token'],$token)) {
        http_response_code(409);
        throw new InvalidArgumentException($row && $row['active']?$row['owner_name'].' sedang menganalisis bagian ini.':'Kunci analisis telah berakhir. Klik kotak analisis untuk mengedit kembali.');
    }
}
