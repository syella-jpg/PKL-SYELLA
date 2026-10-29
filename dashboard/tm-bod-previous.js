(function (global) {
  function period(year, quarter) {
    const number = Number(String(quarter).replace('Q', ''));
    return { year: Number(year) - (number === 1 ? 1 : 0), quarter: 'Q' + (number === 1 ? 4 : number - 1) };
  }
  function stored(storage, year) {
    try {
      const raw = storage.getItem('tm-bod-sections-v2-' + year)
        || (Number(year) === 2026 ? storage.getItem('tm-bod-sections-v1') : null);
      const data = JSON.parse(raw || '{}');
      return data && typeof data === 'object' && !Array.isArray(data) ? data : {};
    } catch (_) { return {}; }
  }
  function value(data, quarter, id, baselineIds) {
    if (id.startsWith('custom-')) return (data[quarter + '-custom-slides'] || []).find(item => item.id === id)?.value || '';
    const saved = data[quarter + '-section-values'] || {};
    if (Object.prototype.hasOwnProperty.call(saved, id)) return saved[id];
    const ids = data[quarter + '-section-ids'] || baselineIds || [];
    const index = ids.indexOf(id);
    return index >= 0 ? data[quarter]?.[index] || '' : '';
  }
  global.TmBodPrevious = { period, stored, value };
})(window);
