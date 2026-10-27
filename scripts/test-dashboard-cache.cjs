const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
(async () => {
  for (const page of ['Rev SO(UNIT).html', 'Revenue KX SI.html', 'Ratio Biaya.html']) {
    const html = fs.readFileSync('dashboard/' + page, 'utf8');
    const code = html.slice(html.indexOf('  const dashboardData = new Map();'), html.indexOf('  async function loadData()'));
    let calls = 0, listener, fail = false;
    const context = {st: {report: 'Report', tab: 'Pharma', period: '2026-09'},
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
    listener({key: 'konimex_notes'}); await context.getData(); assert.equal(calls, 3);
    listener({key: 'konimex_revenue_so_processed'}); await context.getData(); assert.equal(calls, 4);
    listener({key: null}); fail = true;
    await assert.rejects(context.getData()); fail = false;
    await context.getData(); assert.equal(calls, 6, 'failed requests must be retryable');
  }
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
