<?php
function tmBodAnalysisDestination(string $report,string $tab): ?string {
    if(trim($report)==='UPB Konimex (RP)')return 'UPB KX';
    if(trim($report)==='UPB MNJ (RP)')return 'UPB MNJ';
    if(trim($report)==='Service Level')return 'Service Level Kx to MNJ';
    $maps=[
      'Revenue Konimex Selling Out (RP)'=>['Corporate'=>'KX Revenue Selling Out (Rp)','Pharma'=>'Revenue Divisi Farma (Rp)','HCE'=>'HEC Revenue (Rp)','Food'=>'GB 5 Revenue (Rp)|GB 6 Revenue (Rp)','MTM'=>'KX Revenue - MTM','Ethical'=>'Ethical Revenue Selling Out (Rp)','International Business'=>'IB Revenue (Rp) IB','IB'=>'IB Revenue (Rp) IB'],
      'Revenue Konimex Selling In (RP)'=>['Korporat'=>'KX Revenue Selling In (Rp)','Ekspor'=>'IB Revenue (Rp) IB'],
      'MCM Konimex Sell In (RP)'=>['Pharma'=>'Rasio Biaya & Rasio MCM Farma','HCE'=>'Rasio Biaya & Rasio MCM HEC','Food'=>'Rasio Biaya & Rasio MCM GB 5|Rasio Biaya & Rasio MCM GB 6','Ethical'=>'Rasio Biaya & Rasio MCM ETHICAL','EBITDA IB'=>'Rasio Biaya & EBITDA IB'],
      'Ratio Biaya (Biaya vs Selling in)'=>['Pharma'=>'Rasio Biaya & Rasio MCM Farma','HCE'=>'Rasio Biaya & Rasio MCM HEC','Food'=>'Rasio Biaya & Rasio MCM GB 5|Rasio Biaya & Rasio MCM GB 6','Ethical'=>'Rasio Biaya & Rasio MCM ETHICAL'],
      'Revenue Konimex Sell Out (Unit)'=>['Corporate'=>'KX Revenue Selling Out','Pharma'=>'Selling Out Farma','Food'=>'Rasio Sell Out vs Sell in GB 5 (unit)|Rasio Sell Out vs Sell in GB 6 (unit)','Ethical'=>'Rasio Sell Out vs Sell in ETHICAL']
    ];
    return $maps[$report][$tab]??null;
}

/** Resolve fixed analysis destinations against the selected year's template, including custom IDs. */
function tmBodExactAnalysisTargetIds(array $template,string $destination): array {
    $normalize=fn($value)=>strtoupper(preg_replace('/\s+/u',' ',trim($value)));
    $matches=[];
    foreach($template['slides']??[] as $slide){
        if($normalize(($slide['subtitle']??'')?:($slide['title']??''))===$normalize($destination)&&!empty($slide['enabled']))$matches[]=$slide['id'];
    }
    if(count($matches)!==1)throw new InvalidArgumentException('Template tahun ini harus mempunyai satu slide '.$destination.' yang aktif.');
    return $matches;
}
function tmBodAnalysisTargetIds(array $template,string $report,string $tab): array {
    if(in_array(trim($report),['UPB Konimex (RP)','UPB MNJ (RP)','Service Level'],true)){
        return tmBodExactAnalysisTargetIds($template,tmBodAnalysisDestination($report,$tab));
    }
    return $template['routes'][$report.'|'.$tab]??[];
}
function tmBodQuarterForPeriod(string $period): string {
    if(!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D',$period))throw new InvalidArgumentException('Periode analisis tidak valid.');
    return 'Q'.(int)ceil((int)substr($period,5,2)/3);
}
function tmBodQuarterRange(string $year,string $quarter): array {
    if(!preg_match('/^20\d{2}$/D',$year)||!preg_match('/^Q[1-4]$/D',$quarter))throw new InvalidArgumentException('Tahun atau kuartal tidak valid.');
    return [$year.'-'.str_pad(((int)$quarter[1]-1)*3+1,2,'0',STR_PAD_LEFT),$year.'-'.str_pad((int)$quarter[1]*3,2,'0',STR_PAD_LEFT)];
}
