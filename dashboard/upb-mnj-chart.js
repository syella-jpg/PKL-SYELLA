(function(g){
 const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
 const num=v=>typeof v==='number'&&Number.isFinite(v)?v:null;
 const pct=v=>v===null?'—':(v*100).toLocaleString('id-ID',{maximumFractionDigits:2})+'%';
 g.upbMnjChartData=function(book,tab,period){
  const sheet=book.Sheets.LAPORAN;if(!sheet)return null;
  const X=g.XLSX.utils,rows=X.sheet_to_json(sheet,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:X.decode_range(sheet['!ref']||'A1').e}});
  const start=rows.findIndex(r=>norm(r[0])==='UPB (000) MNJ');if(start<0)return null;
  const label=norm(tab)==='TOTAL UPB MNJ'?'UPB (000) MNJ':norm(tab);
  for(let r=start+3;r<rows.length;r++){
   if(norm(rows[r][0])&&!/^\d+$/.test(norm(rows[r][0])))break;
   if(norm(rows[r][1])!==label)continue;
   const row=rows[r],summary={prior:num(row[3]),actual:num(row[4]),growth:num(row[5])};
   const ratio={priorFullYear:num(row[6]),prior:num(row[7]),target:num(row[10]),actual:num(row[8]),achievement:num(row[11]),growth:num(row[9])};
   if([summary.prior,summary.actual,...Object.values(ratio)].every(v=>v===null))return null;
   return {period,tab,reportName:'MNJ',summary,ratio,cards:[ratio.priorFullYear,ratio.prior,ratio.actual,ratio.growth,ratio.target,ratio.achievement].map(pct)};
  }
  return null;
 };
 g.upbMnjChartSvg=data=>g.upbSummaryChartSvg(data);
})(window);
