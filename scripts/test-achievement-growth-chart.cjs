const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict'),XLSX=require('/tmp/target-sheetjs.js'),c={window:{XLSX}};vm.createContext(c);vm.runInContext(fs.readFileSync('dashboard/achievement-growth-chart.js','utf8'),c);
for(const period of ['2026-10','2027-02']){
 const sheet=XLSX.utils.aoa_to_sheet([['OTHER'],['','CORPORATE',999],['ACHIEVEMENT & GROWTH SELL OUT & SELL IN (RP & UNIT)'],['No','Parameter'],[],['','CORPORATE',.8,-.2,1.2,.3,0,0,-.1,null],['','PHARMA',.5,.1,.6,.2,.7,.3,.8,.4],['REVENUE OTHER'],['','HCE',1]]),book={Sheets:{LAPORAN:sheet}};
 const d=c.window.achievementGrowthChartData(book,'Corporate',period);assert.equal(JSON.stringify(d.values),'[0.8,1.2,0,-0.1]');assert.equal(JSON.stringify(d.growth),'[-0.2,0.3,0,null]');
 const svg=c.window.achievementGrowthChartSvg(d);assert.equal((svg.match(/<rect x=/g)||[]).length,4);for(const label of ['SELL OUT (RUPIAH)','SELL OUT (UNIT)','SELL IN (RUPIAH)','SELL IN (UNIT)','Growth -20%'])assert.ok(svg.includes(label));assert.ok(!/NaN|Infinity/.test(svg));
 assert.equal(c.window.achievementGrowthChartData(book,'HCE',period),null);assert.equal(c.window.achievementGrowthChartData(book,'Pharma',period).values[0],.5);
}
const html=fs.readFileSync('dashboard/Achievement Growth.html','utf8');assert.ok(html.indexOf('shared-report-download.js')<html.indexOf('async function loadData'));
for(const m of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g))if(m[1].trim())new vm.Script(m[1]);
console.log('PASS dummy four Achievement bars, Growth labels only, correct paired columns, source isolation, zero/negative/missing, dynamic periods, page syntax.');
