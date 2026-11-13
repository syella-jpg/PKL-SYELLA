<?php
/** The legacy upload table is optional after migration to UNIT & Revenue. */
function readRevenueSellingOutSource($conn, string $period): array
{
$sql = "
    SELECT
        id,
        gb,
        product_code,
        principal_product_code,
        product_name,
        kategori,

        qty_jan,
        qty_feb,
        qty_mar,
        qty_apr,
        qty_mei,
        qty_jun,
        qty_jul,
        qty_agt,
        qty_sep,
        qty_okt,
        qty_nov,
        qty_des,
        qty_total,

        rev_jan,
        rev_feb,
        rev_mar,
        rev_apr,
        rev_mei,
        rev_jun,
        rev_jul,
        rev_agt,
        rev_sep,
        rev_okt,
        rev_nov,
        rev_des,
        rev_total

    FROM revenue_selling_out
    WHERE report_period = ?
    ORDER BY id ASC
";

    try {$stmt=$conn->prepare($sql);}
    catch(mysqli_sql_exception $error){
        if((int)$error->getCode()!==1146)throw $error;
        return ['success'=>true,'source_available'=>false,'total'=>0,'data'=>[],
            'message'=>'Sumber lama Revenue Selling Out belum tersedia pada database ini.'];
    }
    if(!$stmt)throw new RuntimeException('Query Revenue Selling Out gagal disiapkan.');
    try {
        $stmt->bind_param('s',$period);$stmt->execute();$result=$stmt->get_result();
        if(!$result)throw new RuntimeException('Data Revenue Selling Out gagal dibaca.');
        $data=[];while($row=$result->fetch_assoc())$data[]=$row;
        return ['success'=>true,'source_available'=>true,'total'=>count($data),'data'=>$data];
    }finally{$stmt->close();}
}
