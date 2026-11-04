const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const XLSX = require('/tmp/target-sheetjs.js');
const context = { window: { XLSX } };
vm.runInNewContext(fs.readFileSync('dashboard/mtm-page-data.js', 'utf8'), context);
const sheet = XLSX.utils.aoa_to_sheet([
 ['MTM CONTRIBUTION'], [], [],
 ['', 'FARMASI', 0, 0.2, '', 0.3, 0.4, 0.5],
 ['', 'BISKUIT', 0.1, 0.2, 1, 0.2, 0.4, 1],
 ['RATIO OTHER'], ['', 'CANDY', 99]
]);
const book = { Sheets: { LAPORAN: sheet } };
assert.equal(JSON.stringify(context.window.mtmPageData(book, 'FARMASI').values), '[0,0.2,null,0.3,0.4,0.5]');
assert.equal(context.window.mtmPageData(book, 'CANDY'), null);
assert.equal(context.window.mtmPageData({ Sheets: {} }, 'FARMASI'), null);
const page = fs.readFileSync('dashboard/MTM Contribution.html', 'utf8');
assert(page.includes('const CURRENT = "MTM Contribution"'));
assert(!page.includes('scope:\'ratio-unit-chart\''));
for (const file of fs.readdirSync('dashboard').filter(f=>f.endsWith('.html'))) {
 const content = fs.readFileSync('dashboard/'+file,'utf8');
 assert(!content.includes('"MTM Contribution": "",'), file);
}
assert(fs.readFileSync('menu/page.html','utf8').includes("'MTM Contribution': '../dashboard/MTM%20Contribution.html'"));
console.log('MTM page: values, zero/blank handling, section boundaries, menu and sidebar links passed.');
