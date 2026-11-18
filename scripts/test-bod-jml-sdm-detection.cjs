const assert = require('assert');
const detect = require('../upload/file-detection.js');

const workbook = {
  SheetNames: ['BOD JML SDM'],
  Sheets: {
    'BOD JML SDM': {
      A1: {v: 'KETERANGAN'}, B1: {v: 'REAL 2025'}, C1: {v: 'PLAN 2026'},
      D1: {v: 'PLAN S/D BULAN INI'}, E1: {v: 'REAL S/D BULAN INI'}, F1: {v: 'SELISIH'},
      F3: {v: 0, f: 'D3-E3'}, B6: {v: 0, f: 'SUM(B3:B5)'}
    }
  }
};
let result = detect(workbook, 'Template_BOD JML SDM_2026-11.ods');
assert.deepStrictEqual(result.choices.map(choice => choice.section), ['BOD JML SDM']);
assert.strictEqual(result.year, 2026);
assert.strictEqual(result.month, 11);
result = detect(workbook, 'BOD JML SDM.ods');
assert.deepStrictEqual(result.choices.map(choice => choice.section), ['BOD JML SDM']);
assert.strictEqual(result.year, null);
assert.strictEqual(result.month, null);
console.log('PASS: BOD JML SDM is auto-selected from its headers and period is read from the downloaded template filename.');
