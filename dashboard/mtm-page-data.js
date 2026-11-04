(function (root) {
  root.mtmPageData = function (book, tab) {
    const sheet = book.Sheets.LAPORAN;
    if (!sheet) return null;
    const rows = root.XLSX.utils.sheet_to_json(sheet, { header: 1, raw: true, defval: '' });
    const norm = value => String(value || '').trim().toUpperCase().replace(/\s+/g, ' ');
    const start = rows.findIndex(row => norm(row[0]) === 'MTM CONTRIBUTION');
    if (start < 0) return null;
    for (let r = start + 3; r < rows.length; r++) {
      if (/^(REVENUE |RATIO |MCM |MTM )/.test(norm(rows[r][0]))) break;
      if (norm(rows[r][1]) === norm(tab)) return {
        values: [2, 3, 4, 5, 6, 7].map(c => typeof rows[r][c] === 'number' && Number.isFinite(rows[r][c]) ? rows[r][c] : null)
      };
    }
    return null;
  };
})(window);
