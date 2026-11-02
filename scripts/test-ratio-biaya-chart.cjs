const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),XLSX=require(process.env.SHEETJS_PATH||'/tmp/target-sheetjs.js');
const context={window:{XLSX}};vm.createContext(context);vm.runInContext(fs.readFileSync('dashboard/ratio-biaya-chart.js','utf8'),context);
const tabs=['Total (Domestik + Export)','FARMASI','HCE','BISKUIT','CANDY','BIAYA CM','ETHICAL','IB'];
const rows=[['OTHER TABLE'],['','FARMASI',999,999,999,999],['RATIO BIAYA (Biaya vs Selling in)'],[],[],...tabs.map((tab,i)=>['',tab,999,i+1,i+3,i+2]),['REVENUE NEXT'],['','Unknown',0,100,100,100]];
const book={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet(rows)}},before=JSON.stringify(book);
for(const [i,tab] of tabs.entries()){
 const data=context.window.ratioBiayaChartData(book,tab,'2027-09');
 assert.deepEqual(Array.from(data.chart.series,s=>s.values[0]),[i+1,i+2,i+3]);
 assert.match(data.chart.series[0].name,/2026 YTD SEPTEMBER/);assert.equal(data.chart.series[1].name,'Anggaran 2027');assert.match(data.chart.series[2].name,/2027 YTD SEPTEMBER/);
}
assert.equal(context.window.ratioBiayaChartData(book,'Unknown','2027-09'),null);
assert.equal(JSON.stringify(book),before);
const sheet=book.Sheets.LAPORAN;sheet.D7={t:'n',v:0};sheet.E7={t:'s',v:''};sheet.F7={t:'n',v:0};
assert.deepEqual(Array.from(context.window.ratioBiayaChartData(book,'FARMASI','2027-09').chart.series,s=>s.values[0]),[0,0,null]);
for(const file of ['dashboard/Ratio Biaya.html','dashboard/Revenue SO(RP).html'])for(const match of fs.readFileSync(file,'utf8').matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))if(match[1].trim())new vm.Script(match[1]);
console.log('PASS Ratio Biaya eight tabs, prior YTD/budget/current Actual order, year/month, zero/missing, table boundaries, source unchanged and page syntax');
for(const values of [[1200000,1800000,1500000],[0,0,0],[-12,35,4],[null,25,null]]){
 const chart={labels:['Total (Domestik + Export)'],series:values.map((v,i)=>({name:['Actual 2025 YTD SEPTEMBER','Anggaran 2026','Actual 2026 YTD SEPTEMBER'][i],values:[v]}))};
 const svg=context.window.ratioBiayaChartSvg(chart);
 assert.match(svg,/viewBox="0 0 800 470"/);assert.match(svg,/RP \(000\)/);
 assert.ok(!/NaN|Infinity|rotate\(/.test(svg));
 assert.equal((svg.match(/width="36"/g)||[]).length,values.filter(v=>v!==null).length);
 assert.equal((svg.match(/rx="2"/g)||[]).length,3);
}
console.log('PASS Ratio Biaya matching chart dimensions, slim spaced bars, horizontal labels, three legends, compact axis and zero/negative/missing values');

const yearSvg=context.window.ratioBiayaChartSvg(context.window.ratioBiayaChartData(book,'HCE','2027-09').chart);
assert.ok(yearSvg.includes('>YTD 2026</text>'));assert.ok(yearSvg.includes('>Anggaran 2027</text>'));assert.ok(yearSvg.includes('>YTD 2027</text>'));
console.log('PASS dynamic YTD and budget year labels');
sheet['!ref']='A1:K15';sheet.J8={t:'n',v:0.12};sheet.K8={t:'n',v:-0.025};
const ratios=context.window.ratioBiayaChartData(book,'HCE','2027-09');
assert.deepEqual(Array.from(ratios.chart.ratios),[0.12,-0.025]);
const annotated=context.window.ratioBiayaChartSvg(ratios.chart);
assert.ok(annotated.includes('Target Ratio Biaya 2027: 12,00%'));assert.ok(annotated.includes('Ratio Growth YTD: -2,50%'));
const total=context.window.ratioBiayaChartSvg(context.window.ratioBiayaChartData(book,tabs[0],'2027-09').chart);
assert.ok(total.includes('Target Ratio Biaya 2027: —'));assert.ok(total.includes('Ratio Growth YTD: —'));
console.log('PASS target ratio and growth from report J/K, negative percentage, dynamic year, blank ratios retained');

const fullAxis=context.window.ratioBiayaChartSvg({period:'2026-09',labels:['FARMASI'],series:[100000000,500000000,300000000].map(v=>({name:'Actual 2026',values:[v]}))});
assert.ok(fullAxis.includes('500.000.000</text>'));assert.ok(!/>[^<]* jt<\/text>/.test(fullAxis));
console.log('PASS full numeric axis without jt abbreviation');
