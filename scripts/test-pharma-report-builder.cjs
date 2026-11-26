// Offline dependency regression: never contacts APIs or reads real workbooks.
const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const XLSX=require(process.env.SHEETJS_PATH||'/tmp/target-sheetjs.js');
const page=fs.readFileSync('dashboard/Revenue SO(RP).html','utf8');
const builder=page.slice(page.indexOf('  async function buildReportWorkbook('),page.indexOf('  // Use the same Selling In formulas'));
async function run(pharma,full=false){
 const calls=[],messages=[];
 const context={XLSX,window:{XLSX,PortalAccess:{pharma:()=>pharma}},localStorage:{getItem:()=>null},
 BULAN:Array(12).fill('DUMMY'),TARGET_API_URL:'https://dummy.invalid/targets',TARGET_RP_SCALE:1,SELL_OUT_TARGET_SOURCE:{},
 toast:message=>messages.push(message),
 loadAchievement:async()=>({rows:[{code:'existing1',revMonths:Array(12).fill(10)},{code:'existing4',revMonths:Array(12).fill(20)}]}),
 loadPreviousYearRevenue:async()=>({totals:new Map(),monthTotals:new Map()}),
 fetch:async()=>({ok:true,json:async()=>({success:true,data:{rows:[{code:'existing1',months:Array(12).fill(30)}]}})})};
 const forbidden=['replaceDaftarTemplateSheet','replaceServiceLevelSheet','replaceLaunchTemplateSheet','replaceBodJmlSdmTemplateSheet','replaceBodBiReportSheets','replaceIBTemplateSheet','replaceMtmTemplateSheet','getEthicalHjpData','replaceEthicalTemplateSheet',...(full?['fillMcmEthicalTarget','replaceMnjReportSheet']:[])];
 const helpers=[...new Set([...builder.matchAll(/\b((?:append|replace|fill|format|addReport|getEthical)\w+)\(/g)].map(m=>m[1]))];
 for(const name of helpers)context[name]=async()=>{calls.push(name);if(pharma&&forbidden.includes(name))throw Error('Forbidden dependency: '+name);};
 context.fillReportAchievement=book=>{calls.push('fillReportAchievement');book.Sheets.LAPORAN.I7={t:'n',v:100};};
 vm.createContext(context);vm.runInContext(builder+';this.build=buildReportWorkbook',context);
 const book=await context.build('2026-10',{chartOnly:!full});
 assert.ok(book?.Sheets.LAPORAN,messages.join('; '));
 assert.equal(book.Sheets.LAPORAN.I7.v,100);
 assert.ok(calls.includes('replaceEdpTemplateSheet'));assert.ok(calls.includes('replaceUnitTemplateSheet'));
 if(!full)for(const name of forbidden)assert.equal(calls.includes(name),!pharma,name);
 if(full){assert.ok(calls.includes('appendAchievementGrowthReportTable'));assert.ok(!calls.includes('appendSalesMnjReportTable'));}
 assert.deepEqual(messages,[]);
}
(async()=>{await run(true);await run(false);await run(true,true);console.log('PASS Pharma Sell Out builder skips denied report sources, retains scoped UNIT/EDP and numeric chart values; admin dependencies unchanged.');})().catch(error=>{console.error(error);process.exitCode=1;});
