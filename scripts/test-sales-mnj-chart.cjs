const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js'),c={window:{XLSX}};vm.createContext(c);for(const p of ['unit-sell-out-chart.js','sales-mnj-chart.js'])vm.runInContext(fs.readFileSync('dashboard/'+p,'utf8'),c);
for(const period of ['2026-10','2027-02']){
 const sheet=XLSX.utils.aoa_to_sheet([['SALES MNJ'],['No','Parameter'],[],[],[1,'REVENUE (000)',999,100,900,200,250,1.25,.8,1.5],[2,'BIAYA (000)',999,50,500,100,80,.8,.16,.6],[3,'RASIO BIAYA',.9,.5,.5,.5,.32],['UPB NEXT'],['','REVENUE (000)',0,0,0,0,9999]]),b={Sheets:{LAPORAN:sheet}};
 const d=c.window.salesMnjChartData(b,'REVENUE (000)',period);assert.equal(d.summary.prior,100);assert.equal(d.summary.target,200);assert.equal(d.summary.actual,250);assert.equal(d.unit,'Rp (000)');
 const svg=c.window.salesMnjChartSvg(d);for(const t of ['SALES MNJ','Rp (000)','Ach YTD 125%','Ach Tahun 80%','Growth 150%'])assert.ok(svg.includes(t));assert.ok(!/NaN|Infinity/.test(svg));
 const ratio=c.window.salesMnjChartData(b,'RASIO BIAYA',period);assert.equal(ratio.cards[0],'32%');assert.ok(c.window.salesMnjChartSvg(ratio).includes('50%'));assert.equal(c.window.salesMnjChartData(b,'Missing',period),null);
}
const html=fs.readFileSync('dashboard/Sales MNJ.html','utf8');for(const m of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))if(m[1].trim())new vm.Script(m[1]);
console.log('PASS dummy SALES MNJ exact table/columns, revenue/cost/ratio units, three bars/labels, dynamic periods, missing data, page syntax.');
