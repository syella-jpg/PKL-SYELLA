<?php
require_once __DIR__.'/bod_kx_sell_in_input_template.php';

function ensureBodKxInputStore(mysqli $conn): void {
    $conn->query('CREATE TABLE IF NOT EXISTS `BOD KX SELL IN TEMPLATE` (report_year SMALLINT UNSIGNED NOT NULL,report_period CHAR(7) NOT NULL,payload LONGBLOB NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(report_year)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function bodKxInputEmptyState(int $year): array {return ['year'=>$year,'sales'=>[],'rod'=>[]];}
function bodKxInputKey(array $record,bool $rod): string {return $record['product'].'|'.mb_strtoupper($record[$rod?'code':'barang']);}
function decodeBodKxInputState(?array $saved,int $year): array {
    if(!$saved)return bodKxInputEmptyState($year);
    $json=@gzdecode($saved['payload']);if($json===false)throw new RuntimeException('Rincian BOD KX SELL IN tersimpan tidak dapat dibaca.');
    $state=json_decode($json,true,512,JSON_THROW_ON_ERROR);
    if(!is_array($state)||($state['year']??null)!==$year)throw new RuntimeException('Tahun rincian BOD KX SELL IN tersimpan tidak valid.');
    foreach(['sales','rod'] as $kind){$rod=$kind==='rod';$records=$state[$kind]??null;if(!is_array($records)||!array_is_list($records))throw new RuntimeException('Susunan rincian BOD KX SELL IN tersimpan tidak valid.');$seen=[];
        foreach($records as $record){if(!isset(bodKxInputGroups($rod)[$record['product']??''])||!is_string($record[$rod?'name':'barang']??null)||trim($record[$rod?'name':'barang'])==='')throw new RuntimeException('Identitas rincian BOD KX SELL IN tersimpan tidak lengkap.');
            if($rod&&(!is_string($record['code']??null)||$record['code']===''||!array_key_exists('price',$record)))throw new RuntimeException('Kode atau Harga Beli ROD tersimpan tidak lengkap.');
            $key=bodKxInputKey($record,$rod);if(isset($seen[$key]))throw new RuntimeException('Rincian BOD KX SELL IN tersimpan berulang.');$seen[$key]=true;
            foreach($rod?['units']:['units','rupiah'] as $field){$values=$record[$field]??null;if(!is_array($values)||!array_is_list($values)||count($values)!==12)throw new RuntimeException('Rincian BOD KX SELL IN harus berisi dua belas bulan.');foreach($values as $value)bodKxInputNumber($value,'rincian tersimpan');}
            if($rod){$price=bodKxInputNumber($record['price'],'Harga Beli tersimpan');if(($price!==null&&$price<0)||($price===null&&count(array_filter($record['units'],static fn($v)=>$v!==null))))throw new RuntimeException('Harga Beli ROD tersimpan tidak valid.');}
        }
    }
    return $state;
}
function loadBodKxInputHistory(mysqli $conn,string $period): array {
    [$year,$month]=bodKxInputPeriod($period);
    try{$q=$conn->prepare('SELECT payload FROM `BOD KX SELL IN TEMPLATE` WHERE report_year=?');$q->bind_param('i',$year);$q->execute();$saved=$q->get_result()->fetch_assoc();$q->close();return decodeBodKxInputState($saved?:null,$year);}
    catch(mysqli_sql_exception $e){if($e->getCode()===1146)return bodKxInputEmptyState($year);throw $e;}
}
/** The initial row serializes concurrent imports for the same year inside the transaction. */
function lockBodKxInputState(mysqli $conn,string $period): array {
    [$year]=bodKxInputPeriod($period);$empty=gzencode(json_encode(bodKxInputEmptyState($year),JSON_THROW_ON_ERROR),6);
    $q=$conn->prepare('INSERT IGNORE INTO `BOD KX SELL IN TEMPLATE` (report_year,report_period,payload) VALUES (?,?,?)');$q->bind_param('iss',$year,$period,$empty);$q->execute();$q->close();
    $q=$conn->prepare('SELECT payload FROM `BOD KX SELL IN TEMPLATE` WHERE report_year=? FOR UPDATE');$q->bind_param('i',$year);$q->execute();$saved=$q->get_result()->fetch_assoc();$q->close();
    if(!$saved)throw new RuntimeException('Rincian BOD KX SELL IN belum dapat dikunci.');return decodeBodKxInputState($saved,$year);
}
function writeBodKxInputState(mysqli $conn,string $period,array $state): void {
    $year=$state['year'];$payload=gzencode(json_encode($state,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION),6);
    $q=$conn->prepare('UPDATE `BOD KX SELL IN TEMPLATE` SET report_period=?,payload=?,updated_at=NOW() WHERE report_year=?');$q->bind_param('ssi',$period,$payload,$year);$q->execute();$q->close();
}
/** Empty months/groups preserve saved inputs; an explicit zero replaces their values. */
function mergeBodKxInputState(array $saved,array $incoming): array {
    if($saved['year']!==$incoming['year'])throw new InvalidArgumentException('Tahun rincian BOD KX SELL IN berbeda.');
    $state=bodKxInputEmptyState($incoming['year']);if(isset($saved['monthly_imports']))$state['monthly_imports']=$saved['monthly_imports'];if(!empty($saved['rod_imported']))$state['rod_imported']=true;$touches=['sales'=>[],'rod'=>[]];
    foreach(['sales','rod'] as $kind){$rod=$kind==='rod';$fields=$rod?['units']:['units','rupiah'];$map=[];$newKeys=[];
        foreach($saved[$kind] as $record)$map[bodKxInputKey($record,$rod)]=$record;
        $replace=[];
        foreach($incoming[$kind] as $record){$group=$record['product'];foreach($fields as $field)foreach($record[$field] as $m=>$value)if($value!==null)$replace[$group][$m][$field]=true;}
        $touches[$kind]=$replace;
        // A corrected price changes every existing monthly value for that product.
        if($rod)foreach($incoming[$kind] as $record){$old=$map[bodKxInputKey($record,true)]??null;if($old&&$record['price']!==null&&$record['price']!==$old['price'])foreach($old['units'] as $m=>$value)if($value!==null)$touches[$kind][$record['product']][$m]['units']=true;}
        foreach($map as &$record)foreach($replace[$record['product']]??[] as $m=>$flags)foreach($flags as $field=>$yes)$record[$field][$m]=null;unset($record);
        foreach($incoming[$kind] as $record){$key=bodKxInputKey($record,$rod);$newKeys[$key]=true;$previous=$map[$key]??null;
            if(!$previous)$previous=$rod?['product'=>$record['product'],'code'=>$record['code'],'name'=>$record['name'],'price'=>$record['price'],'units'=>array_fill(0,12,null)]:['product'=>$record['product'],'barang'=>$record['barang'],'units'=>array_fill(0,12,null),'rupiah'=>array_fill(0,12,null)];
            if($rod){$previous['code']=$record['code'];$previous['name']=$record['name'];if($record['price']!==null)$previous['price']=$record['price'];}else $previous['barang']=$record['barang'];
            foreach($fields as $field)foreach($record[$field] as $m=>$value)if($value!==null)$previous[$field][$m]=$value;$map[$key]=$previous;
        }
        foreach($map as $key=>$record){$has=false;foreach($fields as $field)foreach($record[$field] as $value)$has=$has||$value!==null;if($has||isset($newKeys[$key]))$state[$kind][]=$record;}
    }
    return ['state'=>$state,'touches'=>$touches];
}
function bodKxInputDecimal(?float $value): ?string {return $value===null?null:number_format(bodKxInputNumber($value,'jumlah Sub Total'),6,'.','');}
function bodKxInputAggregates(array $state,array $touches): array {
    $out=['sales'=>[],'rod'=>[]];
    foreach(['sales','rod'] as $kind)foreach($touches[$kind] as $group=>$months)foreach($months as $m=>$flags){$units=null;$rupiah=null;
        foreach($state[$kind] as $record){if($record['product']!==$group)continue;$qty=$record['units'][$m];if($qty!==null)$units=($units??0)+$qty;$rp=$kind==='rod'?($qty===null?null:$qty*$record['price']):$record['rupiah'][$m];if($rp!==null)$rupiah=($rupiah??0)+$rp;}
        $out[$kind][]=['product'=>$group,'month'=>$m+1,'units'=>bodKxInputDecimal($units),'rupiah'=>bodKxInputDecimal($rupiah),'fields'=>$flags];
    }
    return $out;
}
/** Save product detail and both canonical report sources atomically, without duplicate imports. */
function saveBodKxCombinedInput(mysqli $conn,string $period,array $incoming): array {
    [$year]=bodKxInputPeriod($period);if($incoming['year']!==$year)throw new InvalidArgumentException('Tahun template berbeda dari periode upload.');ensureBodKxInputStore($conn);
    $conn->begin_transaction();
    try{
        $saved=lockBodKxInputState($conn,$period);
        if(!empty($incoming['_rod_import']))$saved['rod']=[];
        $merged=mergeBodKxInputState($saved,$incoming);
        if(!empty($incoming['_rod_import'])){
            $merged['state']['rod_imported']=true;
            foreach($incoming['rod'] as $record)foreach(range(0,11) as $m)$merged['touches']['rod'][$record['product']][$m]['units']=true;
            $q=$conn->prepare('DELETE FROM `BOD KX SELL IN target` WHERE report_year=?');$q->bind_param('i',$year);$q->execute();$q->close();
        }
        $totals=bodKxInputAggregates($merged['state'],$merged['touches']);
        if(isset($incoming['_monthly_import']))$merged['state']['monthly_imports'][(int)$incoming['_monthly_import']]=true;
        foreach($totals['sales'] as $record){$p=$year.'-'.str_pad((string)$record['month'],2,'0',STR_PAD_LEFT);$updates=[];foreach(['units','rupiah'] as $field)if(isset($record['fields'][$field]))$updates[]=$field.'=VALUES('.$field.')';
            $q=$conn->prepare('INSERT INTO bod_kx_sell_in_monthly (report_period,product,units,rupiah) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE '.implode(',',$updates).',updated_at=NOW()');$q->bind_param('ssss',$p,$record['product'],$record['units'],$record['rupiah']);$q->execute();$q->close();
        }
        foreach($totals['rod'] as $record){$q=$conn->prepare('INSERT INTO `BOD KX SELL IN target` (report_year,month,product,rupiah,units) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE rupiah=VALUES(rupiah),units=VALUES(units),updated_at=NOW()');$q->bind_param('iisss',$year,$record['month'],$record['product'],$record['rupiah'],$record['units']);$q->execute();$q->close();}
        writeBodKxInputState($conn,$period,$merged['state']);$conn->commit();return ['success'=>true,'kind'=>'combined','input_template'=>true,'year'=>$year,'period'=>$period,'sales_count'=>count($totals['sales']),'target_count'=>count($totals['rod']),'source'=>!empty($incoming['_rod_import'])?'rod':(!empty($incoming['_monthly_import'])?'monthly':'template')];
    }catch(Throwable $e){$conn->rollback();throw $e;}
}
/** Legacy replacements invalidate only their detail, keeping future downloads truthful. */
function invalidateBodKxInputState(mysqli $conn,string $period,bool $target): void {
    $state=lockBodKxInputState($conn,$period);
    if($target){$state['rod']=[];unset($state['rod_imported']);}else{$m=(int)substr($period,5,2)-1;unset($state['monthly_imports'][$m+1]);foreach($state['sales'] as &$record){$record['units'][$m]=null;$record['rupiah'][$m]=null;}unset($record);}
    writeBodKxInputState($conn,$period,$state);
}

/** Keep only the monthly report's authoritative group subtotals in the combined template. */
function bodKxMonthlyInput(array $records,string $period): array {
    [$year,$month]=bodKxInputPeriod($period);$input=bodKxInputEmptyState($year);$input['_monthly_import']=$month;$has=false;
    foreach($records as $record){$units=array_fill(0,12,null);$rupiah=$units;$units[$month-1]=bodKxInputNumber($record['units'],'Sub Total unit');$rupiah[$month-1]=bodKxInputNumber($record['rupiah'],'Sub Total Rupiah');$has=$has||$units[$month-1]!==null||$rupiah[$month-1]!==null;$input['sales'][]=['product'=>$record['product'],'barang'=>'RINGKASAN LAPORAN BULANAN','units'=>$units,'rupiah'=>$rupiah];}
    if(!$has)throw new InvalidArgumentException('Sub Total laporan bulanan masih kosong. Belum ada angka yang dapat dimasukkan ke template.');
    return $input;
}
