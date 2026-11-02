const fs=require('fs'),vm=require('vm'),assert=require('assert/strict'),XLSX=require(process.env.SHEETJS_PATH||'/tmp/target-sheetjs.js');
const context={window:{XLSX}};vm.createContext(context);vm.runInContext(fs.readFileSync('dashboard/selling-in-chart.js','utf8'),context);
const data=[['REVENUE KONIMEX (SELLING OUT)'],['','Ekspor',999,999,999,999,999,999,999],['REVENUE KONIMEX (SELLING IN) – RP. (000)'],['No','Parameter'],['',''],
 ['','PHARMA',99999,10,99999,20,100,30,40],['','GB 1',0,6,0,12,0,18,24],['','GB 4',0,4,0,8,0,12,16],
 ['','HCE',0,2,0,4,10,6,8],['','Biskuit',0,1,0,2,10,3,4],['','Candy',0,1,0,2,10,3,4],['','Ethical',0,1,0,2,10,3,4],
 ['','Ekstraksi Solonat',0,0,0,0,0,0,0],['','KODC',0,0,0,0,0,0,0],['','Toll In',0,0,0,0,0,0,0],
 ['','Ekspor',0,3,0,6,30,9,12],['','IB Farma',0,1,0,2,0,3,4],['','IB Food',0,2,0,4,0,6,8],['RATIO BIAYA'],['','Ekspor',999,999,999,999,999,999,999]];
const book={Sheets:{LAPORAN:XLSX.utils.aoa_to_sheet(data)}},before=JSON.stringify(book);
const domestic=context.window.sellingInChartData(book,'Domestik','2027-09');
assert.deepEqual(Array.from(domestic.values),[15,30,45,60]);assert.equal(domestic.ratios[0],60/140);assert.equal(domestic.ratios[1],1);assert.equal(domestic.ratios[2],60/45);
const corporate=context.window.sellingInChartData(book,'Korporat','2027-09');assert.deepEqual(Array.from(corporate.values),[18,36,54,72]);
const exportData=context.window.sellingInChartData(book,'Ekspor','2027-09');assert.deepEqual(Array.from(exportData.values),[3,6,9,12]);
const svg=context.window.sellingInChartSvg(exportData);assert.ok(svg.includes('Target 2026 YTD SEPTEMBER'));assert.ok(svg.includes('Target 2027 YTD SEPTEMBER'));assert.ok(svg.includes('RP(000)'));assert.ok(svg.includes('Ach 40,0%'));assert.ok(svg.includes('Growth 100,0%'));
assert.equal((svg.match(/<title>[^<]*RP \(000\)<\/title>/g)||[]).length,4);assert.ok(!/NaN|Infinity/.test(svg));
assert.equal(JSON.stringify(book),before);
const missing=context.window.sellingInChartData(book,'No such row','2027-09');assert.equal(missing,null);
const zero={values:[0,0,0,0],ratios:[null,null,null],period:'2027-01',tab:'Ekspor'};assert.ok(!/NaN|Infinity/.test(context.window.sellingInChartSvg(zero)));
const negative={...zero,values:[-20,-10,30,-5]};assert.ok(!/NaN|Infinity/.test(context.window.sellingInChartSvg(negative)));
console.log('PASS Selling In scoped YTD series, nonduplicated division/corporate/export aggregation, report-unit values, FY achievement/growth, period labels, PNG SVG, zero/negative/missing and source unchanged');

const divisionTabs=['PHARMA','HCE','Biskuit','Candy','Ethical','Ekstraksi Solonat','KODC','Toll In'];
const page=fs.readFileSync('dashboard/Revenue KX SI.html','utf8');
assert.deepEqual(JSON.parse(page.match(/const TABS = (\[[^;]+\]);/)[1]),divisionTabs);
for(const tab of divisionTabs){
 const row=data.find(row=>String(row[1]).toUpperCase()===tab.toUpperCase());
 const actual=context.window.sellingInChartData(book,tab,'2027-09');
 assert.deepEqual(Array.from(actual.values),[3,5,7,8].map(column=>row[column]),tab);
 assert.equal(actual.tab,tab);
 const denominator=row[6];
 assert.equal(actual.ratios[0],denominator?row[8]/denominator:null,tab+' FY achievement');
 assert.equal(actual.ratios[1],row[5]?row[8]/row[5]-1:null,tab+' growth');
}
console.log('PASS eight Selling In tabs use their own division rows and ratios');
