(() => {
  const $ = id => document.getElementById(id);
  const search = $('report-search');
  if (search) {
    const normalize = value => value.toLocaleLowerCase('id').replace(/\s+/g, ' ').trim();
    function filterReports() {
      const sections = Array.from(document.querySelectorAll('main section.analysis'));
      const query = normalize(search.value);
      const terms = query.split(' ').filter(Boolean);
      let matches = 0;
      sections.forEach(section => {
        const headings = Array.from(section.querySelectorAll('h3, h4'), heading => heading.textContent);
        const values = Array.from(section.querySelectorAll('textarea'), field => field.value);
        const title = section.querySelector('.slide-title')?.value || '';
        const content = normalize([...headings, title, ...values].join(' '));
        if (section.nextElementSibling?.classList.contains('add-slide-row')) section.nextElementSibling.hidden = !terms.every(term => content.includes(term));
        section.hidden = !terms.every(term => content.includes(term));
        if (!section.hidden) matches++;
      });
      $('clear-search').hidden = !search.value;
      $('search-result').textContent = !query
        ? `${sections.length} bagian laporan tersedia.`
        : matches ? `${matches} dari ${sections.length} bagian ditemukan.`
          : 'Tidak ada bagian yang cocok. Coba kata kunci lain.';
    }
    search.addEventListener('input', filterReports);
    $('clear-search').addEventListener('click', () => {
      search.value = '';
      filterReports();
      search.focus();
    });
    document.querySelector('main').addEventListener('input', event => { if (event.target.matches('textarea, .slide-title')) filterReports(); });
    filterReports();
  }
  // The multi-section page does not use the legacy single-analysis editor below.
  if (!$('analysis')) {
    $('burger').onclick = () => { location.href = '../menu/page.html'; };
    document.querySelector('[data-sec="Dashboard"]').onclick = () => { location.href = '../menu/page.html'; };
    return;
  }
  const storageKey = 'tm-bod-analysis-v1';
  let template, drafts = {};
  try {
    const stored = JSON.parse(localStorage.getItem(storageKey));
    if (stored && typeof stored === 'object' && !Array.isArray(stored)) drafts = stored;
  } catch (_) {}
  function showQuarter() {
    $('analysis').value = typeof drafts[$('quarter').value] === 'string' ? drafts[$('quarter').value] : '';
    $('status').textContent = '';
  }
  function save() {
    drafts[$('quarter').value] = $('analysis').value;
    try { localStorage.setItem(storageKey, JSON.stringify(drafts)); $('status').textContent = 'Tersimpan'; }
    catch (_) { $('status').textContent = 'Draf belum tersimpan. Unduh untuk menyimpan analisis.'; }
  }
  $('analysis').addEventListener('input', save);
  $('quarter').addEventListener('change', showQuarter);
  $('more').onclick = () => {
    const open = $('options').hidden;
    $('options').hidden = !open; $('more').setAttribute('aria-expanded', String(open));
  };
  document.addEventListener('click', event => {
    if (!$('options').contains(event.target) && !$('more').contains(event.target)) {
      $('options').hidden = true; $('more').setAttribute('aria-expanded', 'false');
    }
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { $('options').hidden = true; $('more').setAttribute('aria-expanded', 'false'); }
  });
  $('reset').onclick = () => {
    if (!confirm('Kosongkan analisis untuk ' + $('quarter').value + '?')) return;
    $('analysis').value = ''; save(); $('options').hidden = true; $('more').setAttribute('aria-expanded', 'false');
  };
  $('download').onclick = async () => {
    const quarter = $('quarter').value;
    const analysis = $('analysis').value;
    $('download').disabled = true; $('status').textContent = 'Menyiapkan unduhan…';
    try {
      const slides = structuredClone(template);
      slides[0].fields[0] = 'TM BOD — ' + quarter;
      slides[0].fields[1] = quarter;
      slides[1].fields[1] = analysis;
      // Call the PHP function directly: /dashboard/*.php may be served as static files.
      const endpoint = location.protocol === 'file:'
        ? 'http://127.0.0.1:8000/api/report.php?endpoint=tm_bod_download'
        : new URL('../api/report.php?endpoint=tm_bod_download', location.href).href;
      const response = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ slides }) });
      if (!response.ok) {
        let message = response.status === 405
          ? 'API unduhan menolak POST (HTTP 405). Pastikan deployment terbaru sudah aktif.'
          : `Unduhan gagal (HTTP ${response.status}). Periksa log deployment API.`;
        try { message = (await response.json()).message || message; } catch (_) {}
        throw new Error(message);
      }
      if (!response.headers.get('content-type')?.includes('application/vnd.oasis.opendocument.presentation')) throw new Error('Server tidak mengembalikan presentasi. Buka melalui server aplikasi.');
      const url = URL.createObjectURL(await response.blob());
      const link = document.createElement('a'); link.href = url; link.download = `TM-BOD-${quarter}.odp`; document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 30000);
      $('status').textContent = 'Unduhan selesai.';
    } catch (error) { $('status').textContent = error.message; }
    finally { $('download').disabled = false; }
  };
  $('burger').onclick = () => { location.href = '../menu/page.html'; };
  document.querySelector('[data-sec="Dashboard"]').onclick = () => { location.href = '../menu/page.html'; };
  document.querySelector('[data-sec="TM BOD"]').onclick = () => { $('side').classList.remove('open'); };
  showQuarter();
  fetch('assets/tm-bod/slides.json').then(response => {
    if (!response.ok) throw new Error('Template gagal dimuat.'); return response.json();
  }).then(data => { template = data; $('download').disabled = false; })
    .catch(() => { $('status').textContent = 'Template gagal dimuat. Buka halaman melalui server aplikasi.'; });
})();
