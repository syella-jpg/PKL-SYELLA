(function(global){
 const norm=v=>String(v??'').trim().toUpperCase().replace(/\s+/g,' ');
 global.sellOutInUnitChartData=function(book,tab,period,kind="UNIT"){
  const sheet=book.Sheets.LAPORAN,utils=global.XLSX.utils;if(!sheet)return null;
  const rows=utils.sheet_to_json(sheet,{header:1,defval:'',raw:true,range:{s:{r:0,c:0},e:utils.decode_range(sheet['!ref']||'A1').e}});
  const start=rows.findIndex(row=>norm(row[0])===`RATIO SELL OUT VS SELL IN (${kind})`);if(start<0)return null;
  for(let r=start+3;r<rows.length;r++){
   if(/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0])))break;
   if(norm(rows[r][1])!==norm(tab))continue;
   const read=c=>typeof rows[r][c]==='number'&&Number.isFinite(rows[r][c])?rows[r][c]:null;
   const values=[2,3,5,6].map(read),ratios=(kind==='RUPIAH'?[8,9,10,11]:[8,9,10]).map(read);
   if(values.every(v=>v===null)&&ratios.every(v=>v===null))return null;
   return {kind,values,ratios,growth:[4,7].map(read),period,tab};
  }
  return null;
 };
 global.sellOutInUnitChartSvg=function(data){
  const esc=v=>String(v).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const {values,growth,period,tab}=data,year=Number(period.slice(0,4));
  const rupiah=data.kind==='RUPIAH',unit=rupiah?'RP (000)':'UNIT';
  const month=['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'][Number(period.slice(5,7))-1];
  const W=800,H=470,L=110,R=30,T=65,B=125,pw=W-L-R,ph=H-T-B;
  const valid=values.filter(v=>typeof v==='number'&&Number.isFinite(v));
  const min=Math.min(0,...valid),max=Math.max(0,...valid),span=max-min||1;
  const magnitude=10**Math.floor(Math.log10(span/5)),step=[1,2,2.5,5,10].map(n=>n*magnitude).find(n=>n>=span/5);
  const lo=Math.floor(min/step)*step,hi=Math.max(lo+step,Math.ceil(max/step)*step),y=v=>T+ph*(hi-v)/(hi-lo);
  const number=v=>v.toLocaleString('id-ID',{maximumFractionDigits:2});
  const percent=v=>typeof v==='number'&&Number.isFinite(v)?(v*100).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2})+'%':'—';
  const centers=[L+pw*.25,L+pw*.75],colors=['#004586','#579d1c'];
  let out=`<title>${esc(tab)} — Ratio Sell Out vs Sell In (${rupiah?'Rupiah':'Unit'})</title><text x="${L}" y="20" font-size="11" fill="#666">${unit}</text>`;
  for(let i=0;i<=Math.round((hi-lo)/step);i++){
   const v=lo+i*step;out+=`<line x1="${L}" x2="${W-R}" y1="${y(v)}" y2="${y(v)}" stroke="#ddd"/><text x="${L-12}" y="${y(v)+4}" text-anchor="end" font-size="11" fill="#555">${number(v)}</text>`;
  }
  values.forEach((value,i)=>{
   if(typeof value!=='number'||!Number.isFinite(value))return;
   const group=Math.floor(i/2),current=i%2,x=centers[group]+(current?4:-40);
   out+=`<rect x="${x}" y="${Math.min(y(value),y(0))}" width="36" height="${Math.max(1,Math.abs(y(value)-y(0)))}" fill="${colors[current]}"><title>${group?'SELL IN':'SELL OUT'} YTD ${month} ${current?year:year-1}: ${number(value)} ${unit}</title></rect>`;
   if(current)out+=`<text x="${x+18}" y="${Math.max(24,Math.min(y(value),y(0))-16)}" text-anchor="middle" font-size="11" font-weight="700">Growth ${percent(growth[group])}</text>`;
  });
  centers.forEach((center,i)=>{out+=`<text x="${center}" y="${H-B+26}" text-anchor="middle" font-size="13">${i?'SELL IN - RP':'SELL OUT - RP'}</text>`;});
  [year-1,year].forEach((year,i)=>{
   const x=L+i*340;out+=`<rect x="${x}" y="${H-52}" width="10" height="10" rx="2" fill="${colors[i]}"/><text x="${x+18}" y="${H-42}" font-size="11" fill="#333">YTD ${month} ${year}</text>`;
  });
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(tab)} Sell Out dan Sell In ${unit}" font-family="Arial, Helvetica, sans-serif">${out}</svg>`;
 };
})(window);
