const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
(async () => {
  for (const page of ['Rev SO(UNIT).html', 'Ratio Biaya.html']) {
    const html = fs.readFileSync('dashboard/' + page, 'utf8');
    const code = html.slice(html.indexOf('  const dashboardData = new Map();'), html.indexOf('  async function loadData()'));
    let calls = 0, listener, fail = false;
    const context = {localStorage:{},st: {report: 'Report', tab: 'Pharma', period: '2026-09'},
      window: {addEventListener: (_, fn) => { listener = fn; }}, render() {},
      async loadData() { calls++; if (fail) throw Error('offline'); return {value: calls}; }};
    vm.createContext(context); vm.runInContext(code, context);
    const first = context.getData();
    assert.equal(first, context.getData(), 'in-flight requests must be shared');
    await first;
    context.st.tab = 'HCE'; await context.getData();
    context.st.tab = 'Pharma'; await context.getData();
    assert.equal(calls, 2, page + ': returning to Pharma must reuse data');
    context.st.period = '2026-10'; await context.getData(); assert.equal(calls, 3);
    listener({key:'corporate_api_cache_v2:targets',storageArea:{}});await context.getData();assert.equal(calls,3,'Session cache events do not rebuild charts');
    listener({key: 'konimex_notes'}); await context.getData(); assert.equal(calls, 3);
    listener({key: 'konimex_revenue_so_processed'}); await context.getData(); assert.equal(calls, 4);
    listener({key: null}); fail = true;
    await assert.rejects(context.getData()); fail = false;
    await context.getData(); assert.equal(calls, 6, 'failed requests must be retryable');
  }
  const sellingIn=fs.readFileSync('dashboard/Revenue KX SI.html','utf8');
  const chartCode=sellingIn.slice(sellingIn.indexOf('  const chartBooks=new Map();'),sellingIn.indexOf('  window.setDashboardData='));
  let chartCalls=0,chartListener;
  const chartContext={localStorage:{},st:{period:'2026-09',tab:'Corporate'},render(){},toast(message){throw Error(message);},XLSX:{read:buffer=>buffer},window:{addEventListener:(_,fn)=>{chartListener=fn;},loadCorporateReportBuffer:async()=>{chartCalls++;return {available:true};},sellingInChartData:(book,tab)=>({book,tab})}};
  vm.createContext(chartContext);vm.runInContext(chartCode,chartContext);
  await Promise.all([chartContext.getData(),chartContext.getData()]);assert.equal(chartCalls,1,'Selling In concurrent chart reads share one build');
  chartContext.st.tab='Pharma';await chartContext.getData();assert.equal(chartCalls,1,'Selling In tabs reuse their period workbook');
  chartListener({key:'corporate_api_cache_v2:targets',storageArea:{}});await chartContext.getData();assert.equal(chartCalls,1,'Temporary cache does not spawn more chart builders');
  chartListener({key:'konimex_report_revision'});await chartContext.getData();assert.equal(chartCalls,2,'New upload invalidates chart workbook');
  const so = fs.readFileSync('dashboard/Revenue SO(RP).html', 'utf8');
  const render = so.slice(so.indexOf('  async function render()'), so.indexOf('  function hScroll'));
  assert.doesNotMatch(render, /await getData/);
  assert.doesNotMatch(so, /addEventListener\("focus"/);
  for (const file of fs.readdirSync('dashboard').filter(name => name.endsWith('.html'))) {
    const html = fs.readFileSync('dashboard/' + file, 'utf8');
    for (const match of html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)) new vm.Script(match[1], {filename: file});
  }
  console.log('PASS tab reuse, concurrent requests, periods, upload invalidation, retry, SO loading path, inline syntax');
})().catch(error => { console.error(error); process.exitCode = 1; });
