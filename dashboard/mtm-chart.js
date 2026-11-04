(function (root) {
  'use strict';
  const esc = value => String(value).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const numeric = v => typeof v === 'number' && Number.isFinite(v);
  const percent = v => numeric(v) ? (v * 100).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%' : '—';
  root.mtmChartSvg = function (data, period, tab) {
    const year = Number(period.slice(0, 4));
    const month = ['JANUARI','FEBRUARI','MARET','APRIL','MEI','JUNI','JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER'][Number(period.slice(5, 7)) - 1];
    const values = [0, 1, 3, 4].map(i => data.values[i]);
    if (!values.some(numeric)) return '';
    const growth = [data.values[2], data.values[5]];
    const W = 900, H = 470, L = 110, R = 30, T = 78, B = 115, ph = H - T - B, pw = W - L - R;
    const valid = values.filter(numeric);
    const min = Math.min(0, ...valid), max = Math.max(0, ...valid), span = max - min || 0.01;
    const magnitude = 10 ** Math.floor(Math.log10(span / 5));
    const step = [1, 2, 2.5, 5, 10].map(n => n * magnitude).find(n => n >= span / 5);
    const lo = Math.floor(min / step) * step, hi = Math.max(lo + step, Math.ceil(max / step) * step);
    const y = value => T + ph * (hi - value) / (hi - lo);
    const centers = [L + pw * .25, L + pw * .75], colors = ['#004586', '#579d1c'];
    let out = `<title>${esc(tab)} — MTM Contribution</title><desc>Perbandingan Revenue dan Budget Spending FY ${year - 1} dengan YTD ${month} ${year}. Growth ditampilkan di atas batang YTD.</desc>`;
    for (let i = 0; i <= Math.round((hi - lo) / step); i++) {
      const value = lo + i * step;
      out += `<line x1="${L}" x2="${W - R}" y1="${y(value)}" y2="${y(value)}" stroke="#ddd"/><text x="${L - 12}" y="${y(value) + 4}" text-anchor="end" font-size="11" fill="#555">${percent(value)}</text>`;
    }
    values.forEach((value, i) => {
      const group = Math.floor(i / 2), current = i % 2, x = centers[group] + (current ? 5 : -45);
      if (!numeric(value)) return;
      const top = Math.min(y(value), y(0));
      out += `<rect data-series="${current ? 'ytd' : 'fy'}" x="${x}" y="${top}" width="40" height="${Math.max(1, Math.abs(y(value) - y(0)))}" fill="${colors[current]}"><title>${group ? 'BUDGET SPENDING' : 'REVENUE'} ${current ? 'YTD ' + month + ' ' + year : 'FY ' + (year - 1)}: ${percent(value)}</title></rect>`;
      if (current) out += `<text data-growth="${group}" x="${x + 20}" y="${top - 16}" text-anchor="middle" font-size="12" font-weight="700">Growth ${percent(growth[group])}</text>`;
    });
    centers.forEach((center, i) => { out += `<text x="${center}" y="${H - B + 30}" text-anchor="middle" font-size="13">${i ? 'BUDGET SPENDING' : 'REVENUE'}</text>`; });
    [`FY ${year - 1}`, `YTD ${month} ${year}`].forEach((label, i) => {
      const x = L + i * 350;
      out += `<rect x="${x}" y="${H - 40}" width="10" height="10" rx="2" fill="${colors[i]}"/><text x="${x + 18}" y="${H - 30}" font-size="12" fill="#333">${label}</text>`;
    });
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(tab)} — MTM Contribution" font-family="Arial, Helvetica, sans-serif">${out}</svg>`;
  };
})(window);
