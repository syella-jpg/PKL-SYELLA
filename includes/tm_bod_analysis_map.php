<?php
function tmBodAnalysisDestination(string $report,string $tab): ?string {
    $maps=[
      'Revenue Konimex Selling Out (RP)'=>['Corporate'=>'KX Revenue Selling Out (Rp)','Pharma'=>'Revenue Divisi Farma (Rp)','HCE'=>'HEC Revenue (Rp)','Food'=>'GB 5 Revenue (Rp)|GB 6 Revenue (Rp)','MTM'=>'KX Revenue - MTM','Ethical'=>'Ethical Revenue Selling Out (Rp)','International Business'=>'IB Revenue (Rp) IB','IB'=>'IB Revenue (Rp) IB'],
      'Revenue Konimex Selling In (RP)'=>['Korporat'=>'KX Revenue Selling In (Rp)','Ekspor'=>'IB Revenue (Rp) IB'],
      'MCM Konimex Sell In (RP)'=>['Pharma'=>'Rasio Biaya & Rasio MCM Farma','HCE'=>'Rasio Biaya & Rasio MCM HEC','Food'=>'Rasio Biaya & Rasio MCM GB 5|Rasio Biaya & Rasio MCM GB 6','Ethical'=>'Rasio Biaya & Rasio MCM ETHICAL','EBITDA IB'=>'Rasio Biaya & EBITDA IB'],
      'Ratio Biaya (Biaya vs Selling in)'=>['Pharma'=>'Rasio Biaya & Rasio MCM Farma','HCE'=>'Rasio Biaya & Rasio MCM HEC','Food'=>'Rasio Biaya & Rasio MCM GB 5|Rasio Biaya & Rasio MCM GB 6','Ethical'=>'Rasio Biaya & Rasio MCM ETHICAL'],
      'Revenue Konimex Sell Out (Unit)'=>['Corporate'=>'KX Revenue Selling Out','Pharma'=>'Selling Out Farma','Food'=>'Rasio Sell Out vs Sell in GB 5 (unit)|Rasio Sell Out vs Sell in GB 6 (unit)','Ethical'=>'Rasio Sell Out vs Sell in ETHICAL']
    ];
    return $maps[$report][$tab]??null;
}
