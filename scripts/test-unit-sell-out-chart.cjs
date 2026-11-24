const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js');
const c={window:{XLSX}};vm.createContext(c);vm.runInContext(fs.readFileSync('dashboard/unit-sell-out-chart.js','utf8'),c);
for(const period of ['2026-10','2027-02']){
 const labels=['CORPORATE','PHARMA','HCE','FOOD','ETHICAL','INTERNATIONAL BUSINESS','GB 1','GB 4','GB 2','GB 3','GB 5','GB 6'];
 const sheet=XLSX.utils.aoa_to_sheet([['REVENUE KONIMEX (UNIT) – SELL OUT'],['No','Parameter'],[],...labels.map((l,i)=>['',l,1,100,900,200,250,1.25,.3,i===2?-.2:.5]),['REVENUE OTHER'],['','CORPORATE',0,0,0,1,999]]),book={Sheets:{LAPORAN:sheet}};
 const data=c.window.unitSellOutChartData(book,'Corporate',period);assert.equal(data.items.length,5);assert.equal(data.summary.actual,250);assert.equal(data.summary.target,200);assert.equal(data.cards[2],'125%');
 const svg=c.window.unitSellOutChartSvg(data);assert.ok(svg.includes('UNIT'));assert.ok(svg.includes('Ach Tahun 30%'));assert.ok(svg.includes('Ach YTD 125%'));assert.ok(svg.includes('Growth 50%'));assert.equal((svg.match(/<rect x=/g)||[]).length,6);assert.ok(!/NaN|Infinity/.test(svg));
 assert.equal(c.window.unitSellOutChartData(book,'Pharma',period).items.length,2);assert.equal(c.window.unitSellOutChartData(book,'MTM',period),null);
 sheet.G7={t:'n',v:0};assert.equal(c.window.unitSellOutChartData(book,'Food',period).summary.actual,0);
}
assert.equal(c.window.unitSellOutChartData({Sheets:{}},'Corporate','2026-10'),null);
for(const period of ['2026-10','2027-02']){
 const sheet=XLSX.utils.aoa_to_sheet([
 ['REVENUE KONIMEX (UNIT) – SELL OUT'],['No','Parameter'],[],['','CORPORATE',0,100,0,200,250,1.25,.3,.5],
 ['REVENUE KONIMEX (UNIT) – SELL IN'],['No','Parameter'],[],['','CORPORATE',0,80,0,120,160,160/120,.8,1]
 ]),book={Sheets:{LAPORAN:sheet}};
 const data=c.window.unitSellInChartData(book,'Corporate',period);
 assert.equal(data.summary.actual,160);assert.equal(data.summary.prior,80);assert.equal(data.summary.target,120);assert.equal(data.summary.annualAchievement,.8);
 const svg=c.window.unitSellInChartSvg(data);assert.ok(svg.includes('SELL IN'));assert.ok(svg.includes('Ach Tahun 80%'));assert.ok(svg.includes('Growth 100%'));
 assert.equal(c.window.unitSellOutChartData(book,'Corporate',period).summary.actual,250);
}
const si=fs.readFileSync('dashboard/Rev SI(UNIT).html','utf8');assert.ok(si.includes('unitSellInChartData'));assert.ok(si.includes('const CURRENT = "Revenue Konimex sell in (Unit)"'));
for(const match of si.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))if(match[1].trim())new vm.Script(match[1]);

const html=fs.readFileSync('dashboard/Rev SO(UNIT).html','utf8');assert.ok(html.indexOf('shared-report-download.js')<html.indexOf('async function loadData()'),'Report loader must be installed before initial render');for(const match of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))if(match[1].trim())new vm.Script(match[1]);
console.log('PASS dummy UNIT SELL OUT source isolation, KPI/units/percent, divisions, zero/missing, 2026/2027, SVG and page syntax.');
