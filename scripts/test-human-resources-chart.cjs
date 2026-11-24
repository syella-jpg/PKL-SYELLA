const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js'),c={window:{XLSX}};vm.createContext(c);vm.runInContext(fs.readFileSync('dashboard/human-resources-chart.js','utf8'),c);
for(const period of ['2026-10','2027-02']){
 const rows=[['No','Parameter'],[],['','BIDANG SUMBER DAYA MANUSIA'],['','Biaya SDM (Rp. 000)'],['','Gaji, upah, dll',999,100,120,200,.6,.2],['','Recruitment',999,10,20,40,.5,1],['','Jumlah SDM'],...['KX – excluded Ethical & Export','KX – Ethical','KX – Export'].map(n=>['',n,999,'',0,10,0,'']),['','BIDANG BIAYA'],['','Biaya Promosi (Rp. 000)',999,50,60,100,.6,.2],['','Realisasi Anggaran Biaya Operasional (Rp. 000)',999,90,80,'','',''],['','COMPLIANCE LEVEL'],['','Recruitment',999,999,999,999]];
 const book={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet(rows)}},data=c.window.humanResourcesChartData(book,'Biaya SDM (Rp. 000)',period);assert.equal(data.items.length,2);assert.equal(data.cards[0],'140');assert.equal(data.cards[1],'240');assert.equal(data.items[0].prior,100);
 const svg=c.window.humanResourcesChartSvg(data);assert.equal((svg.match(/<rect x=/g)||[]).length,9);assert.ok(svg.includes('setahun'));assert.ok(svg.includes('Realisasi 60%'));assert.ok(!/NaN|Infinity/.test(svg));
 const head=c.window.humanResourcesChartData(book,'Jumlah SDM',period);assert.equal(head.unit,'Orang');assert.equal(head.cards[0],'0');assert.equal(head.cards[3],'—');assert.equal(head.items[0].prior,null);
 for(const tab of ['Biaya Promosi (Rp. 000)','Realisasi Anggaran Biaya Operasional (Rp. 000)'])assert.equal(c.window.humanResourcesChartData(book,tab,period).items.length,1);
 assert.equal(c.window.humanResourcesChartData(book,'Missing',period),null);
}
const html=fs.readFileSync('dashboard/Sumber Daya Manusia.html','utf8');for(const m of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))if(m[1].trim())new vm.Script(m[1]);assert.ok(html.indexOf('shared-report-download.js')<html.indexOf('async function loadData'));
console.log('PASS dummy four HR/cost tabs, exact YTD/budget columns, groups/units, percent labels, zero/missing, table boundary, dynamic year and page syntax.');
