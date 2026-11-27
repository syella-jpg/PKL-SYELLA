<?php
/** Sheet editing password, separate from account authentication and upload authorization. */
function rawTemplatePassword(): string {return $GLOBALS['rawTemplateSheetPassword']??'testing';}
function validateTemplatePassword(string $password): string {
    if(trim($password)===''||strlen($password)>128)throw new InvalidArgumentException('Isi password sheet antara 1–128 karakter (maksimal 128 byte).');
    return $password;
}
function readTemplatePassword($db): string {
    try{$q=$db->prepare("SELECT setting_value FROM report_template_settings WHERE setting_key='sheet_password'");$q->execute();try{$row=$q->get_result()->fetch_assoc();}finally{$q->close();}return $row?validateTemplatePassword($row['setting_value']):'testing';}
    catch(mysqli_sql_exception $e){if($e->getCode()===1146)return 'testing';throw $e;}
}
function saveTemplatePassword($db,string $password,string $user): void {
    if($user!=='admin 2')throw new RuntimeException('Pengaturan password sheet hanya tersedia untuk admin 2.',403);
    $password=validateTemplatePassword($password);
    $db->query('CREATE TABLE IF NOT EXISTS report_template_settings (setting_key VARCHAR(64) NOT NULL PRIMARY KEY,setting_value VARCHAR(255) NOT NULL,updated_by VARCHAR(80) NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $q=$db->prepare("INSERT INTO report_template_settings (setting_key,setting_value,updated_by) VALUES ('sheet_password',?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)");
    $q->bind_param('ss',$password,$user);try{$q->execute();}finally{$q->close();}
}
