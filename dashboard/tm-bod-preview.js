(() => {
  const $ = id => document.getElementById(id);
  const panel = $('ppt-preview');
  const sections = Array.from(document.querySelectorAll('main section.analysis'));
  const fields = sections.flatMap(section => Array.from(section.querySelectorAll('textarea')));
  const saveButtons = sections.map(section => section.querySelector('.section-save'));
  const statuses = sections.map(section => section.querySelector('.section-save-status'));
  function disableSaving(disabled) { saveButtons.forEach(button => { button.disabled = disabled; }); }
  const readValue = value => window.TmBodEditor ? window.TmBodEditor.read(value) : { text: typeof value === 'string' ? value : '', html: '', image: '' };
  const storageKey = 'tm-bod-sections-v1';
  let drafts = {};
  try {
    const stored = JSON.parse(localStorage.getItem(storageKey));
    if (stored && typeof stored === 'object' && !Array.isArray(stored)) drafts = stored;
  } catch (_) {}
  async function loadDraft() {
    previewVersion++;
    const requestedQuarter=$('quarter').value,requestedYear=$('cover-year').value;
    try { await window.TmBodIncoming?.load(requestedYear,requestedQuarter); }
    catch(error) { $('preview-status').textContent=error.message; }
    if(requestedQuarter!==$('quarter').value || requestedYear!==$('cover-year').value)return;
    window.TmBodIncoming?.render(sections);
    if (window.TmBodEditor?.initEditor) restoreCustomSlides();
    const values = drafts[$('quarter').value];
    fields.forEach((field, index) => {
      const value = Array.isArray(values) ? values[index] : '';
      if (field.richEditor) field.richEditor.set(value); else field.value = readValue(value).text;
      setSectionLocked(sections[index], hasSavedValue(value));
    });
    slides = [];
    panel.hidden = false;
    const placeholder = document.createElement('div');
    placeholder.className = 'preview-placeholder';
    placeholder.textContent = 'Klik Simpan pada salah satu kotak analisis untuk melihat slide di sini.';
    $('preview-slide').replaceChildren(placeholder);
    $('slide-position').textContent = '';
    $('preview-status').textContent = '';
    $('previous-slide').disabled = true;
    $('next-slide').disabled = true;
    $('preview-download').disabled = true;
    $('report-workspace').classList.remove('with-preview');
    statuses.forEach(status => { status.textContent = ''; });
    $('report-search')?.dispatchEvent(new Event('input'));
    const q = $('quarter').value;
    const customs = savedCustom(q);
    const ordered = allSections();
    const firstSaved = ordered.findIndex(section => hasSavedValue(savedValue(section, q, Array.isArray(values) ? values : [], customs)));
    if (firstSaved >= 0) await openPreview(snapshotSections(ordered, q, Array.isArray(values) ? values : [], customs), q, firstSaved);
  }
  let previewVersion = 0;
  let slides = [], position = 0, quarter = '', busy = false;
  // Use the same 960 x 540 coordinates and wrapped lines in preview and ODP.
  function wrapText(value, fontSize, width) {
    const maxWidth = width / fontSize;
    const measure = char => /[ilI.,'!:;| ]/.test(char) ? 0.3 : /[MW@#%]/.test(char) ? 0.9 : 0.56;
    const lines = [];
    value.replace(/\r\n?/g, '\n').split('\n').forEach(paragraph => {
      let line = '', length = 0;
      for (const word of paragraph.split(/(\s+)/)) {
        const wordWidth = Array.from(word).reduce((total, char) => total + measure(char), 0);
        if (line && length + wordWidth > maxWidth) {
          lines.push(line.trimEnd()); line = ''; length = 0;
        }
        for (const char of word) {
          if (!line && /\s/.test(char)) continue;
          if (length + measure(char) > maxWidth && line) {
            lines.push(line); line = ''; length = 0;
          }
          line += char; length += measure(char);
        }
      }
      lines.push(line.trimEnd());
    });
    return lines;
  }
  function fitText(value, initialSize, width, height, minSize) {
    let size = initialSize, lines;
    do {
      lines = wrapText(value, size, width);
      if (lines.length * size * 1.2 <= height || size === minSize) break;
      size--;
    } while (size >= minSize);
    return { lines, size, overflow: lines.length * size * 1.2 > height };
  }
  function render() {
    const slide = slides[position];
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 960 540');
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', `Slide ${position + 1}: ${slide.name}`);
    function image(href, x, y, width, height) {
      const node = document.createElementNS(ns, 'image');
      Object.entries({ href, x, y, width, height }).forEach(([key, value]) => node.setAttribute(key, value));
      svg.append(node);
    }
    if (['manufacturing-support', 'marketing-sales'].includes(slide.type)) {
      image(`assets/tm-bod/${slide.type}.png`, 0, 0, 960, 540);
    } else {
    image(slide.type === 'cover' ? 'assets/tm-bod/cover-background.png' : 'assets/tm-bod/slide-background.png', 0, 0, 960, 540);
    if (slide.type !== 'cover') image('assets/tm-bod/slide-logo.png', 33, 8, 140, 83);
    if (slide.bodyImage) image(slide.bodyImage, 34, 158, 820, 310);
    slide.layout.forEach((layout, index) => {
      if (index === 2 && slide.bodyImage) return;
      const text = document.createElementNS(ns, 'text');
      Object.entries({ x: layout.x, y: layout.y + layout.size, 'font-family': 'Arial, sans-serif', 'font-size': layout.size, fill: slide.type === 'cover' ? '#fff' : '#111', 'font-weight': index < 2 ? 'bold' : 'normal' }).forEach(([key, value]) => text.setAttribute(key, value));
      layout.lines.forEach((line, lineIndex) => {
        const span = document.createElementNS(ns, 'tspan');
        span.setAttribute('x', layout.x);
        span.setAttribute('dy', lineIndex ? layout.size * 1.2 : 0);
        span.textContent = line;
        text.append(span);
      });
      svg.append(text);
    });
    }
    $('preview-slide').replaceChildren(svg);
    $('slide-position').textContent = `Slide ${position + 1} dari ${slides.length}`;
    $('previous-slide').disabled = position === 0;
    $('next-slide').disabled = position === slides.length - 1;
  }
  async function openPreview(savedSections, savedQuarter, selectedIndex) {
    const version = ++previewVersion;
    panel.hidden = false;
    $('report-workspace').classList.add('with-preview');
    $('preview-download').disabled = true;
    try {
      const restoredSlides = await Promise.all(savedSections.map(async ({ title, subtitle, value, rich }) => {
        const layout = [
          { ...fitText(title, 32, 710, 70, 16), x: 190, y: 18 },
          { ...fitText(subtitle, 24, 820, 54, 14), x: 34, y: 96 },
          { ...fitText(value, 24, 820, 310, 10), x: 34, y: 158 }
        ];
        const body = rich && (rich.html || rich.images?.length || rich.image) ? await window.TmBodEditor.bodyImage(rich, layout[2].size) : null;
        if (body) layout[2].overflow = body.overflow;
        return { name: subtitle || title, fields: layout.map(item => item.lines.join('\n')), fontSizes: layout.map(item => item.size), layout, bodyImage: body?.data || '' };
      }));
      if (version !== previewVersion || savedQuarter !== $('quarter').value) return;
      slides = restoredSlides;
      quarter = savedQuarter;
      const date = $('cover-date').value;
      const year = $('cover-year').value;
      const dateLabel = date ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta' }).format(new Date(date + 'T12:00:00+07:00')) : '';
      const coverLayout = [
        { ...fitText('Tinjauan Manajemen BOD', 42, 500, 60, 24), x: 40, y: 164 },
        { ...fitText(`${quarter} - ${year}`, 42, 500, 60, 24), x: 40, y: 218 },
        { ...fitText(dateLabel, 24, 500, 40, 16), x: 40, y: 324 }
      ];
      slides.unshift({ type: 'cover', name: 'Cover', fields: coverLayout.map(item => item.lines.join('\n')), fontSizes: coverLayout.map(item => item.size), layout: coverLayout, bodyImage: '' });
      const dividers = [
        { after: /^Executive Summary$/i, type: 'manufacturing-support', name: 'Manufacturing & Support' },
        { after: /^Rekap keluhan OEM\s*\(justified\)$/i, type: 'marketing-sales', name: 'Marketing & Sales' }
      ];
      // Locate source analyses by title so added dividers do not shift later anchors.
      const insertions = dividers.map(divider => ({ ...divider, index: savedSections.findIndex(section => divider.after.test(section.title.trim())) })).filter(divider => divider.index >= 0);
      insertions.sort((a, b) => b.index - a.index).forEach(divider => {
        slides.splice(divider.index + 2, 0, { type: divider.type, name: divider.name, fields: ['', '', ''], fontSizes: [24,24,24], layout: [], bodyImage: '' });
      });
      position = selectedIndex + 1 + insertions.filter(divider => selectedIndex > divider.index).length;
      render();
      const tooLong = slides.some(slide => slide.layout.some(item => item.overflow) || slide.fields.some(value => new TextEncoder().encode(value).length > 12000));
      $('preview-status').textContent = tooLong ? 'Ada analisis terlalu panjang untuk satu slide. Ringkas isinya lalu simpan kembali sebelum mengunduh.' : '';
      $('preview-download').disabled = tooLong;
    } catch (error) { if (version === previewVersion) $('preview-status').textContent = error.message; }
  }
  // Base values keep their original indices; inserted slides use stable IDs.
  const customKey = q => q + '-custom-slides';
  let customSections = [];
  const baseId = section => 'base-' + sections.indexOf(section);
  function allSections() { return window.TmBodEditor?.initEditor ? Array.from(document.querySelectorAll('main section.analysis')) : sections; }
  function sectionId(section) { return section.dataset?.customId || baseId(section); }
  function savedCustom(q) { return Array.isArray(drafts[customKey(q)]) ? drafts[customKey(q)] : []; }
  function savedValue(section, q, values, customs) {
    if (section.dataset?.customId) return customs.find(item => item.id === section.dataset.customId)?.value || '';
    const value=values[sections.indexOf(section)] || '';
    return window.TmBodIncoming?.merge(section,value) || value;
  }
  function snapshotSections(ordered, q, values, customs) {
    return ordered.map(item => {
      const rich = readValue(savedValue(item, q, values, customs));
      const record = item.dataset?.customId ? customs.find(saved => saved.id === item.dataset.customId) : null;
      return { title: record ? record.title : Array.from(item.querySelectorAll('h3'), node => node.textContent.trim()).join(' '),
        subtitle: record ? '' : Array.from(item.querySelectorAll('h4'), node => node.textContent.trim()).join(' '), value: rich.text.trim(), rich };
    });
  }
  async function deleteCustomSlide(section) {
    if (busy) return;
    const q = $('quarter').value;
    const before = allSections();
    const selectedIndex = Math.max(0, before.indexOf(section) - 1);
    const ordered = before.filter(item => item !== section);
    const records = [];
    ordered.forEach((item, index) => {
      if (!item.dataset?.customId) return;
      const saved = savedCustom(q).find(record => record.id === item.dataset.customId);
      records.push({ id: item.dataset.customId, after: sectionId(ordered[index - 1]), title: saved?.title || 'Slide baru', value: saved?.value || '' });
    });
    const next = { ...drafts, [customKey(q)]: records };
    try { localStorage.setItem(storageKey, JSON.stringify(next)); drafts = next; }
    catch (_) { section.querySelector('.section-save-status').textContent = 'Slide belum dihapus karena penyimpanan browser gagal. Coba kembali.'; return; }
    records.forEach(record => { const item = customSections.find(item => item.dataset.customId === record.id); item.dataset.after = record.after; });
    if (section.nextElementSibling?.classList.contains('add-slide-row')) section.nextElementSibling.remove();
    section.remove(); customSections = customSections.filter(item => item !== section);
    const values = Array.isArray(drafts[q]) ? drafts[q] : [];
    busy = true; disableSaving(true); $('quarter').disabled = true;
    try { await openPreview(snapshotSections(ordered, q, values, records), q, Math.min(selectedIndex, ordered.length - 1)); }
    finally { busy = false; disableSaving(false); $('quarter').disabled = false; }
    $('report-search')?.dispatchEvent(new Event('input'));
  }
  function hasSavedValue(value) { return value !== null && typeof value === 'object' || typeof value === 'string' && value.trim() !== ''; }
  function setSectionLocked(section, locked) {
    if (!window.TmBodEditor?.initEditor) return;
    section._locked = locked;
    section.classList.toggle('slide-locked', locked);
    const field = section.querySelector('textarea'); field.richEditor?.setLocked(locked);
    const title = section.querySelector('.slide-title'); if (title) title.disabled = locked;
    section.querySelector('.section-save').hidden = locked;
    const edit = section.querySelector('.edit-slide'); if (edit) edit.hidden = !locked;
  }
  function bindSave(section) {
    const button = section.querySelector('.section-save');
    const status = section.querySelector('.section-save-status');
    const field = section.querySelectorAll('textarea')[0];
    if (window.TmBodEditor?.initEditor) {
      const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'btn cancel edit-slide'; edit.textContent = 'Edit'; edit.hidden = true;
      edit.onclick = () => { if (busy) return; setSectionLocked(section, false); status.textContent = ''; (section.querySelector('.slide-title') || section.querySelector('.rich-editor')).focus(); };
      button.parentElement.append(edit);
    }
    button.onclick = async () => {
      if (busy || section._locked) return;
      if (field.richEditor?.pending()) { status.textContent = 'Tunggu sampai gambar selesai dimuat, lalu klik Simpan.'; return; }
      const q = $('quarter').value;
      const values = fields.map((_, index) => Array.isArray(drafts[q]) ? drafts[q][index] || '' : '');
      const customs = customSections.map(item => {
        const previous = savedCustom(q).find(saved => saved.id === item.dataset.customId);
        return { id: item.dataset.customId, after: item.dataset.after, title: previous?.title || 'Slide baru', value: previous?.value || '' };
      });
      const value = field.richEditor ? field.richEditor.get() : field.value;
      if (section.dataset?.customId) {
        const title = section.querySelector('.slide-title').value.trim();
        if (!title) { status.textContent = 'Isi judul slide terlebih dahulu.'; section.querySelector('.slide-title').focus(); return; }
        const record = customs.find(item => item.id === section.dataset.customId); record.title = title; record.value = value;
      } else values[sections.indexOf(section)] = value;
      const next = { ...drafts, [q]: values, [customKey(q)]: customs };
      try { localStorage.setItem(storageKey, JSON.stringify(next)); drafts = next; }
      catch (_) { status.textContent = 'Gagal menyimpan di browser. Periksa ruang penyimpanan lalu coba kembali.'; return; }
      const ordered = allSections();
      const savedSections = snapshotSections(ordered, q, values, customs);
      busy = true; disableSaving(true); customSections.forEach(item => { item.querySelector('.section-save').disabled = true; });
      $('quarter').disabled = true;
      setSectionLocked(section, true);
      status.textContent = '';
      try { await openPreview(savedSections, q, ordered.indexOf(section)); }
      finally { busy = false; disableSaving(false); customSections.forEach(item => { item.querySelector('.section-save').disabled = false; }); $('quarter').disabled = false; }
    };
    field.addEventListener('input', () => { status.textContent = 'Ada perubahan. Klik Simpan pada kotak ini untuk memperbarui preview.'; });
  }
  function addControl(section) {
    if (section.nextElementSibling?.classList.contains('add-slide-row')) return;
    const row = document.createElement('div'); row.className = 'add-slide-row';
    const button = document.createElement('button'); button.type = 'button'; button.textContent = '+ Tambah slide';
    button.onclick = () => {
      if (busy) return;
      if (allSections().length >= 190) { alert('Jumlah slide sudah mencapai batas.'); return; }
      const item = createCustom({ id: 'custom-' + crypto.randomUUID(), after: sectionId(section), title: '', value: '' }, section);
      if ($('report-search')) { $('report-search').value = ''; $('report-search').dispatchEvent(new Event('input')); }
      item.querySelector('.slide-title').focus();
    };
    row.append(button); section.after(row);
  }
  function createCustom(record, anchor) {
    const section = document.createElement('section'); section.className = 'analysis';
    section.dataset.customId = record.id; section.dataset.after = record.after;
    const label = document.createElement('label'); label.textContent = 'Judul slide';
    const title = document.createElement('input'); title.className = 'slide-title'; title.type = 'text'; title.placeholder = 'Masukkan judul slide'; title.maxLength = 200; title.value = record.title;
    label.append(title);
    const field = document.createElement('textarea'); field.placeholder = 'Tulis isi di sini…';
    const acts = document.createElement('div'); acts.className = 'acts';
    const button = document.createElement('button'); button.type = 'button'; button.className = 'btn save section-save'; button.textContent = 'Simpan'; acts.append(button);
    const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'btn delete-slide'; remove.textContent = 'Hapus slide'; remove.onclick = () => deleteCustomSlide(section); acts.prepend(remove);
    const status = document.createElement('p'); status.className = 'section-save-status'; status.setAttribute('role','status');
    section.append(label, field, acts, status);
    const row = anchor.nextElementSibling;
    if (row?.classList.contains('add-slide-row')) row.after(section); else anchor.after(section);
    customSections.push(section); window.TmBodEditor.initEditor(section, allSections().length);
    field.richEditor.set(record.value); bindSave(section); addControl(section);
    setSectionLocked(section, hasSavedValue(record.value));
    title.addEventListener('input', () => { status.textContent = 'Judul berubah. Klik Simpan untuk memperbarui slide.'; });
    return section;
  }
  function restoreCustomSlides() {
    customSections.forEach(section => { if (section.nextElementSibling?.classList.contains('add-slide-row')) section.nextElementSibling.remove(); section.remove(); }); customSections = [];
    sections.forEach(addControl);
    const records = savedCustom($('quarter').value);
    const byId = new Map(sections.map(section => [sectionId(section), section]));
    // Resolve nested insertions even when records were stored in another order.
    let remaining = records.slice();
    for (let pass = 0; remaining.length && pass <= records.length; pass++) {
      const unresolved = [];
      remaining.forEach(record => { const anchor = byId.get(record.after); if (!anchor || typeof record.id !== 'string' || byId.has(record.id)) { unresolved.push(record); return; } byId.set(record.id, createCustom(record, anchor)); });
      if (unresolved.length === remaining.length) break; remaining = unresolved;
    }
  }
  sections.forEach(bindSave);
  $('quarter').addEventListener('change', loadDraft);
  $('cover-year').addEventListener('change',loadDraft);
  async function refreshIncoming() {
    try {
      const q=$('quarter').value,year=$('cover-year').value;
      const changed=await window.TmBodIncoming?.load(year,q);
      if(q!==$('quarter').value || year!==$('cover-year').value)return;
      window.TmBodIncoming?.render(sections);
      if(changed && !busy) {
        const values=Array.isArray(drafts[q])?drafts[q]:[],ordered=allSections();
        const first=ordered.findIndex(section=>hasSavedValue(savedValue(section,q,values,savedCustom(q))));
        if(first>=0)await openPreview(snapshotSections(ordered,q,values,savedCustom(q)),q,slides.length?Math.min(position,ordered.length-1):first);
      }
    } catch (_) { /* Keep the previously loaded analysis visible while offline. */ }
  }
  window.addEventListener('storage',event=>{if(event.key==='tm-bod-incoming-updated')refreshIncoming();});
  window.addEventListener('focus',refreshIncoming);
  setInterval(()=>{if(!document.hidden)refreshIncoming();},15000);
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
  $('cover-date').value = today;
  $('cover-year').value = today.slice(0, 4);
  try {
    const incomingPeriod=localStorage.getItem('tm-bod-incoming-period');
    if(/^20\d{2}-(0[1-9]|1[0-2])$/.test(incomingPeriod||'')){
      $('cover-year').value=incomingPeriod.slice(0,4);
      $('quarter').value='Q'+Math.ceil(Number(incomingPeriod.slice(5,7))/3);
    }
  } catch (_) {}
  loadDraft();
  $('previous-slide').onclick = () => { if (position > 0) { position--; render(); } };
  $('next-slide').onclick = () => { if (position < slides.length - 1) { position++; render(); } };
  $('preview-download').onclick = async () => {
    if (busy) return;
    busy = true;
    $('preview-download').disabled = true;
    disableSaving(true);
    $('quarter').disabled = true;
    $('preview-status').textContent = 'Menyiapkan unduhan…';
    try {
      if (location.protocol === 'file:') {
        throw new Error('Untuk mengunduh presentasi, buka halaman melalui server aplikasi (PHP). Simpan dan preview tetap dapat digunakan dari file lokal.');
      }
      const endpoint = new URL('../api/report.php?endpoint=tm_bod_download', location.href);
      const response = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ slides: slides.map(({ type, fields, fontSizes, bodyImage }) => ({ type, fields, fontSizes, bodyImage })) }) });
      if (!response.ok) {
        let message = `Unduhan gagal (HTTP ${response.status}).`;
        try { message = (await response.json()).message || message; } catch (_) {}
        throw new Error(message);
      }
      if (!response.headers.get('content-type')?.includes('application/vnd.oasis.opendocument.presentation')) throw new Error('Server tidak mengembalikan presentasi. Buka melalui server aplikasi.');
      const url = URL.createObjectURL(await response.blob());
      const link = document.createElement('a');
      link.href = url;
      link.download = `TM-BOD-${quarter}.odp`;
      document.body.append(link);
      link.click();
      link.remove();
      setTimeout(() => URL.revokeObjectURL(url), 30000);
      $('preview-status').textContent = 'Unduhan selesai.';
    } catch (error) {
      $('preview-status').textContent = error instanceof TypeError
        ? 'Tidak dapat terhubung ke server unduhan. Periksa koneksi dan pastikan server aplikasi aktif, lalu coba lagi.'
        : error.message;
    }
    finally {
      busy = false;
      $('preview-download').disabled = false;
      disableSaving(false);
      $('quarter').disabled = false;
    }
  };
})();
