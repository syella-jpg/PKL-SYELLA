const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const c={window:{XLSX}};vm.createContext(c);
for(const file of ['upb-konimex-chart.js','upb-mnj-chart.js'])vm.runInContext(fs.readFileSync('dashboard/'+file,'utf8'),c);
// Entire workbook is synthetic; no user files, network or database.
for(const period of ['2026-09','2027-02']){
 const rows=[['UPB (000) KONIMEX'],[],[],['','Ethical',999,999,999],['UPB (000) MNJ'],['No','Parameter'],[]];
 for(const [i,name]of ['UPB (000) MNJ','GB 1 S/D GB 6','Ethical','Non Konimex'].entries())rows.push([i||'',name,900,100+i,120+i,.2,.99,.02,.03,.5,.04,.75]);
 rows.push(['ON TIME FORMULATION'],[],[],['','Missing',999,999,999]);
 const book={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet(rows)}};
 for(const [i,tab]of ['Total UPB MNJ','GB 1 S/D GB 6','Ethical','Non Konimex'].entries()){
  const d=c.window.upbMnjChartData(book,tab,period);
  assert.equal(d.summary.prior,100+i);assert.equal(d.summary.actual,120+i);
  assert.equal(d.ratio.prior,.02);assert.equal(d.ratio.actual,.03);assert.equal(d.ratio.target,.04);assert.equal(d.ratio.achievement,.75);
  const svg=c.window.upbMnjChartSvg(d);
  for(const text of ['UPB MNJ','UPB (Rp. 000)','Growth 20%',String(Number(period.slice(0,4))-1),period.slice(0,4)])assert.ok(svg.includes(text));
  assert.deepEqual(Array.from(d.cards),['99%','2%','3%','50%','4%','75%']);
  assert.ok(!svg.includes('RATIO UPB (%)'));assert.ok(!svg.includes('Target Ratio'));
  assert.ok(svg.includes('viewBox="0 0 800 430"'));
  assert.equal((svg.match(/<rect x=/g)||[]).length,4);assert.ok(!/NaN|Infinity|KONIMEX/.test(svg));
 }
 assert.equal(c.window.upbMnjChartData(book,'Missing',period),null);
}
const zero={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet([['UPB (000) MNJ'],[],[],['','Ethical','',0,0,0,'',0,0,'','',null]])}};
const z=c.window.upbMnjChartData(zero,'Ethical','2026-10');assert.equal(z.summary.actual,0);assert.deepEqual(Array.from(z.cards),['—','0%','0%','—','—','—']);
assert.ok(!/NaN|Infinity/.test(c.window.upbMnjChartSvg(z)));
assert.equal(c.window.upbMnjChartData({Sheets:{}},'Ethical','2026-10'),null);
const html=fs.readFileSync('dashboard/UPB MNJ.html','utf8');
for(const m of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))if(m[1].trim())new vm.Script(m[1]);
assert.ok(html.includes('const CURRENT = "UPB MNJ (RP)"'));
assert.ok(html.includes('const TABS = window.PortalAccess.tabs(["Total UPB MNJ", "GB 1 S/D GB 6", "Ethical", "Non Konimex"])'));
assert.ok(html.indexOf('shared-report-download.js')<html.indexOf('async function loadData'));
assert.ok(html.includes('window.upbMnjChartData'));assert.ok(html.includes('window.upbMnjChartSvg'));
const cardRenderer=html.match(/function renderCards\(\) \{([\s\S]*?)\n  \}/)[1];
for(const year of [2026,2027]){
 const cards={innerHTML:''};
 new Function('st','cur','$','esc',cardRenderer)({period:year+'-09'},{cards:['1%','2%','3%','4%','5%','6%']},()=>cards,String);
 assert.equal((cards.innerHTML.match(/class="kpi"/g)||[]).length,6);
 for(const label of [String(year-1),'YTD '+(year-1),'YTD '+year,'Growth','Target Ratio','% Achievement'])assert.ok(cards.innerHTML.includes('<p>'+label+'</p>'));
}
assert.ok(html.includes('st.report + "|" + st.tab + "|" + st.period')||html.includes('st.report+"|"+st.tab+"|"+st.period'));
console.log('PASS dummy MNJ source isolation, four tabs, YTD/ratio columns, percentages, periods, zero/missing, shared charts and page syntax.');
