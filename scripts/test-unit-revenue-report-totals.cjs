/* Synthetic inputs only. Runs current report readers against normalized upload rows. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
// Run: php scripts/test-unit-revenue-report-totals.php | node scripts/test-unit-revenue-report-totals.cjs
const {fixtures,normalized} = JSON.parse(fs.readFileSync(0,'utf8'));
const months = ['jan','feb','mar','apr','mei','jun','jul','agt','sep','okt','nov','des'];
const records = normalized.map((values, id) => {
  const row = {id,gb:values[0],product_code:values[1],principal_product_code:values[2],product_name:values[3],kategori:values[4],qty_total:values[17],rev_total:values[30]};
  months.forEach((month, i) => { row['unit_'+month]=values[5+i]; row['qty_'+month]=values[5+i]; row['rev_'+month]=values[18+i]; });
  return row;
});
assert.equal(records[0].product_code, '00001', 'Leading product code zeros were lost');
assert.equal(records[0].principal_product_code, '000001', 'Leading principal code zeros were lost');

const expected = new Map();
const add = (code, values) => {
  const old = expected.get(code) || Array(12).fill(0);
  expected.set(code, old.map((v,i) => v+values[i]));
};
for (const item of fixtures) {
  const code = item.gb === 'ET' ? 'ethicalTotal' : 'gb'+item.gb;
  const kind = item.kategori === 'D' ? 'new' : 'existing';
  add(code,item.rev);
  add(item.gb === 'ET' ? (kind === 'new' ? 'ethicalNew' : 'ethicalExisting') : kind+item.gb,item.rev);
}
const expectedUnits = new Map();
for (const item of fixtures) {
  const code = item.gb === 'ET' ? 'ethical' : 'gb'+item.gb;
  const old=expectedUnits.get(code)||Array(12).fill(0);
  expectedUnits.set(code,old.map((v,i)=>v+item.qty[i]));
}
function aggregate(items) {
  const sums=new Map();
  for(const item of items){
    const old=sums.get(item.code)||Array(12).fill(0);
    sums.set(item.code,old.map((v,i)=>v+item.revMonths[i]));
  }
  return sums;
}
function verifyTotals(actual, label) {
  assert.equal(actual.size, expected.size, label+': unexpected/missing classification');
  for(const [code,values] of expected) assert.deepEqual(Array.from(actual.get(code)||[]),values,label+': incorrect '+code+' totals');
}

const reportSource=fs.readFileSync(path.join(root,'dashboard/report-templates.js'),'utf8');
const edpStart=reportSource.indexOf('  async function loadRevenueAchievement(period) {');
const edpEnd=reportSource.indexOf('\n  const UNIT_TEMPLATE_BASE64',edpStart);
assert(edpStart>=0&&edpEnd>edpStart,'EDP report reader not found');
const htmlSource=fs.readFileSync(path.join(root,'dashboard/Revenue SO(RP).html'),'utf8');
const chartStart=htmlSource.indexOf('  async function loadPreviousYearRevenue(period) {');
const chartEnd=htmlSource.indexOf('  async function buildReportWorkbook(',chartStart);
assert(chartStart>=0&&chartEnd>chartStart,'Dashboard revenue readers not found');
const qtyStart=reportSource.indexOf('    const readQtyTotals = (rows) => {');
const qtyEnd=reportSource.indexOf('    const writeQtyTotals',qtyStart);
assert(qtyStart>=0&&qtyEnd>qtyStart,'UNIT report reader not found');
const fetchResponse=async()=>({ok:true,json:async()=>({success:true,data:records})});
const context=vm.createContext({
  URL, URLSearchParams,
  apiBase:'http://synthetic.invalid/dashboard/',
  fetchReportApi:fetchResponse,
  fetch:fetchResponse,
  localStorage:{getItem:()=>null},
  st:{report:'Synthetic',tab:'Corporate'},
  DATA_URL:'http://synthetic.invalid/revenue_so_api.php',
  EDP_API_URL:'http://synthetic.invalid/revenue_edp_mnj_api.php'
});
vm.runInContext(reportSource.slice(edpStart,edpEnd),context);
vm.runInContext(htmlSource.slice(chartStart,chartEnd),context);
vm.runInContext(`const normalize=value=>String(value??'').trim().toUpperCase().replace(/\\s+/g,' ');const qtyFields=${JSON.stringify(months.map(m=>'unit_'+m))};`+reportSource.slice(qtyStart,qtyEnd)+'globalThis.testReadQty=readQtyTotals;',context);

(async()=>{
  verifyTotals(aggregate((await context.loadRevenueAchievement('2026-11')).rows),'EDP report');
  verifyTotals(aggregate((await context.loadAchievement('2026-11')).rows),'Current-year dashboard');
  const previous=await context.loadPreviousYearRevenue('2025-12');
  verifyTotals(previous.monthTotals,'Previous-year dashboard');
  for(const [code,values] of expected)assert.equal(previous.totals.get(code),values.reduce((a,b)=>a+b,0),'Previous FY total '+code);
  const unit=context.testReadQty(records);
  assert.equal(unit.size,expectedUnits.size,'Unexpected/missing UNIT group');
  for(const [code,values] of expectedUnits)assert.deepEqual(Array.from(unit.get(code)||[]),values,'UNIT '+code);
  console.log('PASS: normalized product rows preserve code zeros and feed current EDP, dashboard current/prior, and UNIT readers with exact GB/Ethical Existing/New/Total sums, without double counting.');
})().catch(error=>{console.error(error.message);process.exitCode=1;});
