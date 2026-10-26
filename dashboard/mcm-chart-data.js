/* Read displayed MCM values; never calculate or modify report cells. */
(function(global){
  global.mcmChartData=function(book,tab,period){
    const sheet=book.Sheets.LAPORAN;
    if(!sheet)return null;
    const norm=(v)=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
    const grid=global.XLSX.utils.sheet_to_json(sheet,{header:1,defval:'',raw:true});
    const start=grid.findIndex((row)=>row.some((v)=>norm(v).startsWith('MCM KONIMEX')));
    if(start<0)return null;
    const labels={
      'Corporate(incl.Bi No Product)':'KORPORAT LOKAL (INCL. BI. NON PRODUK)',
      'Corporate (excl. Bi. Non Product)':'KORPORAT LOKAL (EXCL. BI. NON PRODUK)',
      Pharma:'PHARMA',HCE:'HCE',Food:'FOOD','EBITDA IB':'EBITDA IB',Ethical:'ETHICAL'
    };
    const row=grid.find((row,i)=>i>start && norm(row[1])===labels[tab]);
    if(!row)return null;
    const value=(column)=>typeof row[column]==='number' && Number.isFinite(row[column])?row[column]:null;
    return {values:[value(3),value(5),value(6)],ratios:[value(7),value(8),value(9)]};
  };
})(window);
