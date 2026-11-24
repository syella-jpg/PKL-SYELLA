(function(g){
 const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' '),num=v=>typeof v==='number'&&Number.isFinite(v)?v:null;
 const pct=v=>num(v)===null?'—':(v*100).toLocaleString('id-ID',{maximumFractionDigits:2})+'%';
 g.salesMnjChartData=function(book,tab,period){
  const s=book.Sheets.LAPORAN;if(!s)return null;const X=g.XLSX.utils;
  const rows=X.sheet_to_json(s,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:X.decode_range(s['!ref']||'A1').e}}),start=rows.findIndex(r=>norm(r[0])==='SALES MNJ');if(start<0)return null;
  for(let r=start+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM |UPB |ACHIEVEMENT |SALES MNJ)/.test(norm(rows[r][0])))break;
   if(norm(rows[r][1])!==norm(tab))continue;
   const row=rows[r],summary={prior:num(row[3]),target:num(row[5]),actual:num(row[6]),achievement:num(row[7]),annualAchievement:num(row[8]),growth:num(row[9])};
   if([summary.prior,summary.target,summary.actual].every(v=>v===null))return null;
   const unit=norm(tab)==='RASIO BIAYA'?'%':'Rp (000)',display=v=>unit==='%'?pct(v):num(v)===null?'—':v.toLocaleString('id-ID',{maximumFractionDigits:0});
   return {period,tab,summary,unit,chartTitle:'SALES MNJ',cards:[display(summary.actual),display(summary.target),pct(summary.achievement),pct(summary.growth)]};
  }return null;
 };
 g.salesMnjChartSvg=data=>g.unitSellOutChartSvg(data);
})(window);
