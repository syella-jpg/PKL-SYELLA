const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js'),c={window:{XLSX}};vm.createContext(c);for(const p of ['unit-sell-out-chart.js','on-time-formulation-chart.js'])vm.runInContext(fs.readFileSync('dashboard/'+p,'utf8'),c);
for(const period of ['2026-10','2027-02']){
 const s=XLSX.utils.aoa_to_sheet([['ON TIME FORMULATION'],['No','Parameter'],[],['','ON TIME FORMULATION'],['','jumlah produk didaftarkan',100,120,150,300,.8,.4],['','Farmasi',10,20,25,100,.8,.2],['','Candy',0,0,0],['','ketepatan pendaftaran',90,80,100,200,.8,.4],['','Farmasi',5,6,10,20,.6,.3],['','PRODUCT LAUNCH'],['','Farmasi',999,999,999]]),b={Sheets:{LAPORAN:s}};
 const d=c.window.onTimeFormulationChartData(b,'Jumlah produk didaftarkan / Farmasi',period);assert.equal(d.summary.prior,10);assert.equal(d.summary.actual,20);assert.equal(d.summary.target,25);
 const svg=c.window.onTimeFormulationChartSvg(d);assert.ok(svg.includes('Full Year'));assert.ok(svg.includes('Ach Tahun 20%'));assert.ok(!svg.includes('Growth'));assert.ok(!/NaN|Infinity/.test(svg));
 assert.equal(c.window.onTimeFormulationChartData(b,'Ketepatan pendaftaran / Farmasi',period).summary.actual,6);assert.equal(c.window.onTimeFormulationChartData(b,'Jumlah produk didaftarkan / Candy',period).summary.actual,0);
}
const raw=[['ON TIME FORMULATION'],['No','Parameter'],[]];
for(const [tab,headings]of [['ON TIME FORMULATION',['jumlah produk didaftarkan','ketepatan pendaftaran']],['PRODUCT LAUNCH',['jumlah produk dilaunch','ketepatan waktu launch']]]){
 raw.push(['',tab]);for(const heading of headings){raw.push(['',heading]);for(const name of ['Farmasi','Candy','Biskuit'])raw.push(['',name,10,20,25,100,.8,.2]);}
}
const book={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet(raw)}};
for(const tab of ['ON TIME FORMULATION','PRODUCT LAUNCH']){
 const data=c.window.onTimeFormulationChartData(book,tab,'2027-02');assert.equal(data.sections.length,2);assert.equal(data.sections[0].items.length,3);assert.equal(data.sections[1].items.length,3);
 const svg=c.window.onTimeFormulationChartSvg(data);for(const name of ['FARMASI','CANDY','BISKUIT'])assert.ok(svg.includes(name));assert.equal((svg.match(/<rect x=/g)||[]).length,24);assert.ok(!/NaN|Infinity/.test(svg));
}
const html=fs.readFileSync('dashboard/On Time Formulation.html','utf8');for(const m of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))if(m[1].trim())new vm.Script(m[1]);console.log('PASS dummy OTF sections and repeated categories, FY/YTD labels, percentages, zero, source boundary, dynamic years and page syntax.');
