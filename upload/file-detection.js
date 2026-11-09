/* Local-only suggestions. Upload parsers remain the final source of validation. */
(function (root) {
  'use strict';
  const normalize = value => String(value || '').toUpperCase().replace(/[_\s]+/g, ' ').trim();
  function detect(book, filename) {
    const names = book.SheetNames.map(normalize);
    const labels = [];
    for (const name of book.SheetNames) {
      for (const [address, cell] of Object.entries(book.Sheets[name])) {
        if (!/^[A-Z]+\d+$/.test(address) || Number(address.match(/\d+/)[0]) > 1000) continue;
        if (typeof cell.v === 'string') labels.push(normalize(cell.v));
      }
    }
    const text = labels.join('\n');
    const file = normalize(filename);
    // Downloaded templates declare their destination; shared KPI contents must not
    // turn a known IB template into an ambiguous IB/target upload.
    const metaName = book.SheetNames.find(name => String(name).trim().toUpperCase() === '_TEMPLATE');
    const meta = metaName ? book.Sheets[metaName] : null;
    const marker = String(meta?.A1?.v || '').trim();
    const declaredSection = String(meta?.A2?.v || '').trim();
    let templateSection = null;
    if (marker === 'KONIMEX_INPUT_V1' && ['IB', 'MTM'].includes(declaredSection)) templateSection = declaredSection;
    else if (/^KONIMEX_UNIT_SELL_OUT_V[1-6]$/.test(marker)) templateSection = 'TARGET UNIT SELL OUT';
    else if (/^KONIMEX_ETHICAL_SOURCE_V[1-6]$/.test(marker)) templateSection = 'ETHICAL';
    else if (['KONIMEX_UNIT_REVENUE_V1','KONIMEX_UNIT_REVENUE_V2'].includes(marker)) templateSection = 'UNIT & Revenue';
    else if (['KONIMEX_BIAYA_V1','KONIMEX_BIAYA_V2'].includes(marker)) templateSection = 'BIAYA';
    else if (marker === 'KONIMEX_TARGET_REVENUE_V1') templateSection = 'TARGET REVENUE';
    else if (marker === 'KONIMEX_BOD_KX_SELL_IN_V1') templateSection = 'BOD KX Sell In';
    const choices = [];
    const add = (section, label) => { if (!choices.some(c => c.section === section)) choices.push({ section, label: label || section }); };
    const ethicalSource = ['KONIMEX_ETHICAL_SOURCE_V1', 'KONIMEX_ETHICAL_SOURCE_V2', 'KONIMEX_ETHICAL_SOURCE_V3', 'KONIMEX_ETHICAL_SOURCE_V4', 'KONIMEX_ETHICAL_SOURCE_V5','KONIMEX_ETHICAL_SOURCE_V6'].includes(book.Sheets._TEMPLATE?.A1?.v) || (names.includes('PRODUK NAS') && /TOTAL EXISTING/.test(text) && /\bHNA\b/.test(text) && /\bUNIT\b/.test(text));
    if (ethicalSource) add('ETHICAL');
    if (['KONIMEX_UNIT_REVENUE_V1','KONIMEX_UNIT_REVENUE_V2'].includes(book.Sheets._TEMPLATE?.A1?.v)) add('UNIT & Revenue');
    if (['KONIMEX_UNIT_SELL_OUT_V1','KONIMEX_UNIT_SELL_OUT_V2','KONIMEX_UNIT_SELL_OUT_V3','KONIMEX_UNIT_SELL_OUT_V4','KONIMEX_UNIT_SELL_OUT_V5','KONIMEX_UNIT_SELL_OUT_V6'].includes(book.Sheets._TEMPLATE?.A1?.v)) add('TARGET UNIT SELL OUT');
    if (['KONIMEX_BIAYA_V1','KONIMEX_BIAYA_V2'].includes(book.Sheets._TEMPLATE?.A1?.v)) add('BIAYA');
    if (names.includes('PRODUK NAS') && /TOTAL EXISTING/.test(text) && /\bHNA\b/.test(text)) add('ETHICAL');
    if (!(book.Sheets._TEMPLATE?.A1?.v === 'KONIMEX_INPUT_V1' && book.Sheets._TEMPLATE?.A2?.v === 'IB') && names.includes('HRU') && /SALES REVENUE.*\(UNIT\)/.test(text)) add('TARGET UNIT SELL OUT', 'Target unit IB');
    if (names.some(n => /YFR\s*-\s*IBFM|^IAP$/.test(n))) add('IB', 'Laporan IB / ekspor');
    if (!ethicalSource && names.includes('GMEB') && /EXISTING PRODUCT\s*\(UNIT\)/.test(text)) add('TARGET UNIT SELL OUT', 'Target unit Ethical');
    if (names.some(n => /^PRODUK\s+(NAS|PID|PE_1|PE_2)$/.test(n))) add('ETHICAL', 'Data produk Ethical');
    if (names.some(n => /TARGET.*GB\s*[1-6]/.test(n)) && /\bUNIT\b/.test(text) && /KATEGORI\s*[A-D]/.test(text)) add('TARGET UNIT SELL OUT', 'Target unit GB');
    if (/ROD UNIT TERKECIL DARI UNIT PESAN/.test(text) || /LAPORAN\s*PENJUALAN\s*BULANAN/.test(file)) add('BOD KX Sell In');
    if (/BIAYA PROMOSI MNJ|BIAYA NON PRODUCT/.test(text)) add('BOD MNJ');
    if (/BIAYA EXISTING/.test(text) && /BIAYA NEW PRODUCT/.test(text)) add('BIAYA');
    if (/INCREASE REVENUE/.test(text) || (/BUDGET SPENDING/.test(text) && /\bMTM\b/.test(text))) add('MTM');
    if (!choices.length) {
      if (/REKAP REVENUE PER PRODUCT TAHUN/.test(text)) {
        add('TARGET REVENUE'); add('REVENUE VERSI EDP MNJ Target');
      } else if (/REV TOTAL/.test(text)) {
        add('UNIT & Revenue'); add('REVENUE VERSI EDP MNJ');
      } else if (/REVENUE TAHUN/.test(text) && /EXIST(?:ING)?\s+GB/.test(text) && /NP\s+GB/.test(text)) add('REVENUE VERSI EDP MNJ Target');
    }
    if (templateSection) { choices.length = 0; add(templateSection); }
    // An appendix's left-hand year is authoritative, even with next year's block.
    const first = book.Sheets[book.SheetNames[0]];
    const appendix = choices.some(c => c.section === 'MTM') && /BUDGET SPENDING/.test(text) && normalize(first.A1 && first.A1.v).match(/^Y\s*(20\d{2})$/);
    const fileYears = [...new Set(file.match(/\b20\d{2}\b/g) || [])];
    const titleYears = [...new Set((labels.filter(l => /TAHUN|\bTH\.?\s|LAPORAN PENJUALAN|PERIODE/.test(l)).join(' ').match(/\b20\d{2}\b/g) || []))];
    const bodPeriod = ['KONIMEX_BOD_KX_SELL_IN_V1','KONIMEX_TARGET_REVENUE_V1'].includes(marker) && String(meta?.A4?.v || '').match(/^(20\d{2})-(0[1-9]|1[0-2])$/);
    const year = bodPeriod ? Number(bodPeriod[1]) : appendix ? Number(appendix[1]) : fileYears.length === 1 ? Number(fileYears[0]) : titleYears.length === 1 ? Number(titleYears[0]) : null;
    const aliases = ['JANUARI|JANUARY|JAN','FEBRUARI|FEBRUARY|FEB','MARET|MARCH|MAR','APRIL|APR','MEI|MAY','JUNI|JUNE|JUN','JULI|JULY|JUL','AGUSTUS|AUGUST|AGT|AGS|AUG','SEPTEMBER|SEP','OKTOBER|OCTOBER|OKT|OCT','NOVEMBER|NOPEMBER|NOV|NOP','DESEMBER|DECEMBER|DES|DEC'];
    const monthsIn = input => aliases.flatMap((a,i) => new RegExp('\\b(?:'+a+')\\b').test(input) ? [i+1] : []);
    let months = bodPeriod ? [Number(bodPeriod[2])] : monthsIn(file);
    const isoPeriod = file.match(/\b(20\d{2})-(0[1-9]|1[0-2])(?!\d)/);
    if (!months.length && isoPeriod && Number(isoPeriod[1]) === year) months = [Number(isoPeriod[2])];
    const numericPeriod = file.match(/(?:^|[^\d])(0?[1-9]|1[0-2])[-/](20\d{2})(?!\d)/);
    if (!months.length && numericPeriod && Number(numericPeriod[2]) === year) months = [Number(numericPeriod[1])];
    if (!months.length) months = monthsIn(labels.filter(l => /^(?:BULAN|PERIODE)\s/.test(l) && !/S\s*\/\s*D/.test(l)).join(' '));
    return { choices, year, month: months.length === 1 ? months[0] : null };
  }
  root.detectUploadFile = detect;
  if (typeof module !== 'undefined') module.exports = detect;
})(typeof window === 'undefined' ? globalThis : window);
