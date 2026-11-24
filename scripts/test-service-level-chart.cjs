const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),path=require('path'),XLSX=require('/tmp/target-sheetjs.js');
const c={window:{XLSX}};vm.createContext(c);vm.runInContext(fs.readFileSync('dashboard/service-level-chart.js','utf8'),c);
const tabs=['SL Eksternal (MNJ to Outlet) – UNIT','SL Internal (KX to MNJ) – UNIT'];
// Synthetic workbook only; no spreadsheets, database or network accessed.
for(const period of ['2026-09','2026-10','2027-02']){
 const rows=[['OTHER TABLE'],['','GB 1',999,999,999],['No','Parameter','YTD','','Growth'],[],['','Service Level']];
 tabs.forEach((tab,t)=>{rows.push(['',tab]);for(let i=0;i<7;i++)rows.push(['',i===6?'GB ET':'GB '+(i+1),'unused',0,0]);
  for(let i=0;i<7;i++)rows[rows.length-7+i]=['',i===6?'GB ET':'GB '+(i+1),.5+t*.1+i*.01,.6+t*.1+i*.01,.2+t*.1];
  rows.push(['','TOTAL',.55+t*.1,.65+t*.1,.18+t*.1],[]);
 });
 rows.push(['UPB (000) MNJ'],[],[],['','GB 1',888,888,888]);
 const book={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet(rows)}};
 tabs.forEach((tab,t)=>{
  const d=c.window.serviceLevelChartData(book,tab,period);assert.equal(d.items.length,7);assert.equal(d.items[0].prior,.5+t*.1);assert.equal(d.items[0].actual,.6+t*.1);
  assert.equal(d.items[6].label,'ET');assert.equal(d.items[6].growth,.2+t*.1);
  assert.equal(d.cards[0],t?'65%':'55%');assert.equal(d.cards[1],t?'75%':'65%');
  const svg=c.window.serviceLevelChartSvg(d);assert.equal((svg.match(/<rect x=/g)||[]).length,16);
  for(const label of ['GB 1','GB 2','GB 3','GB 4','GB 5','GB 6','ET','YTD '+period.slice(0,4),'YTD '+(Number(period.slice(0,4))-1),'Growth '+(t?'30%':'20%')])assert.ok(svg.includes(label),label);
  assert.ok(!/NaN|Infinity/.test(svg));assert.ok(d.items.every(item=>item.actual<1&&item.prior<1));
  // Each growth label is centered above the current-year bar.
  const bar=svg.match(/<rect x="([\d.]+)" y="([\d.]+)" width="30"[^>]*fill="#579d1c"/);
  const growth=svg.match(/<text x="([\d.]+)" y="([\d.]+)"[^>]*>Growth/);
  assert.ok(Math.abs(Number(growth[1])-(Number(bar[1])+15))<1e-8);assert.ok(Number(growth[2])<Number(bar[2]));
 });
}
const sparse={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet([['','Service Level'],['',tabs[0]],['','GB 1',0,0,''],['','Ethical',.8,'',-.1],['',tabs[1]],['','GB 1',.9,1,.1]])}};
const d=c.window.serviceLevelChartData(sparse,tabs[0],'2026-10');assert.equal(d.items[0].prior,0);assert.equal(d.items[0].actual,0);assert.equal(d.items[1].actual,null);assert.equal(d.items[6].actual,null);assert.equal(d.items[6].prior,.8);
assert.deepEqual(Array.from(d.cards),['—','—','—']);const svg=c.window.serviceLevelChartSvg(d);assert.ok(svg.includes('Growth —'));assert.ok(svg.includes('Growth -10%'));assert.ok(!/NaN|Infinity/.test(svg));
assert.equal(c.window.serviceLevelChartData({Sheets:{}},tabs[0],'2026-10'),null);
assert.equal(c.window.serviceLevelChartData(sparse,'invalid','2026-10'),null);
const html=fs.readFileSync('dashboard/Service Level.html','utf8');
for(const m of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))if(m[1].trim())new vm.Script(m[1]);
assert.ok(html.includes('const CURRENT = "Service Level"'));assert.ok(html.includes('window.serviceLevelChartData'));assert.ok(html.includes('window.serviceLevelChartSvg'));
assert.ok(html.indexOf('shared-report-download.js')<html.indexOf('async function loadData'));
assert.ok(html.includes('st.report+"|"+st.tab+"|"+st.period')||html.includes('st.report + "|" + st.tab + "|" + st.period'));
const menu=fs.readFileSync('menu/page.html','utf8');const routes=vm.runInNewContext('('+menu.match(/const reportPages = (\{[\s\S]*?\n  \});/)[1]+')');
assert.equal(routes['Service Level'],'../dashboard/Service%20Level.html');
for(const [name,url]of Object.entries(routes))assert.ok(fs.existsSync(path.resolve('menu',decodeURIComponent(url))),name);
console.log('PASS dummy Service Level: tab/source isolation, seven GB groups, year/month, 14 bars, percent Growth above current bars, zeros/missing, totals, page syntax and menu links.');
