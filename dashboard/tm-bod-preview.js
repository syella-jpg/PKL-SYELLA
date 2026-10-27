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
  function loadDraft() {
    const values = drafts[$('quarter').value];
    fields.forEach((field, index) => {
      const value = Array.isArray(values) ? values[index] : '';
      if (field.richEditor) field.richEditor.set(value); else field.value = readValue(value).text;
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
  }
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
    panel.hidden = false;
    $('report-workspace').classList.add('with-preview');
    $('preview-download').disabled = true;
    try {
      quarter = savedQuarter;
      slides = await Promise.all(savedSections.map(async ({ title, subtitle, value, rich }) => {
        const layout = [
          { ...fitText(title, 32, 710, 70, 16), x: 190, y: 18 },
          { ...fitText(subtitle, 24, 820, 54, 14), x: 34, y: 96 },
          { ...fitText(value, 24, 820, 310, 10), x: 34, y: 158 }
        ];
        const body = rich && (rich.html || rich.image) ? await window.TmBodEditor.bodyImage(rich, layout[2].size) : null;
        if (body) layout[2].overflow = body.overflow;
        return { name: subtitle || title, fields: layout.map(item => item.lines.join('\n')), fontSizes: layout.map(item => item.size), layout, bodyImage: body?.data || '' };
      }));
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
    } catch (error) { $('preview-status').textContent = error.message; }
  }
  sections.forEach((section, sectionIndex) => {
    const button = saveButtons[sectionIndex];
    const status = statuses[sectionIndex];
    const sectionFields = Array.from(section.querySelectorAll('textarea'));
    button.onclick = async () => {
      if (busy) return;
      if (sectionFields.some(field => field.richEditor?.pending())) { status.textContent = 'Tunggu sampai gambar selesai dimuat, lalu klik Simpan.'; return; }
      const savedQuarter = $('quarter').value;
      const previous = drafts[savedQuarter];
      const values = fields.map((_, index) => Array.isArray(previous) ? previous[index] || '' : '');
      sectionFields.forEach(field => { values[fields.indexOf(field)] = field.richEditor ? field.richEditor.get() : field.value; });
      const nextDrafts = { ...drafts, [savedQuarter]: values };
      try {
        localStorage.setItem(storageKey, JSON.stringify(nextDrafts));
        drafts = nextDrafts;
      } catch (_) {
        status.textContent = 'Gagal menyimpan di browser. Periksa ruang penyimpanan lalu coba kembali.';
        return;
      }
      const savedSections = sections.map(item => ({
        title: Array.from(item.querySelectorAll('h3'), node => node.textContent.trim()).join(' '),
        subtitle: Array.from(item.querySelectorAll('h4'), node => node.textContent.trim()).join(' '),
        value: Array.from(item.querySelectorAll('textarea'), field => readValue(values[fields.indexOf(field)]).text.trim()).filter(Boolean).join('\n'),
        rich: readValue(values[fields.indexOf(item.querySelectorAll('textarea')[0])])
      }));
      busy = true;
      disableSaving(true);
      $('quarter').disabled = true;
      status.textContent = 'Tersimpan di browser untuk ' + savedQuarter + '.';
      try {
        await openPreview(savedSections, savedQuarter, sectionIndex);
      } finally {
        busy = false;
        disableSaving(false);
        $('quarter').disabled = false;
      }
    };
    sectionFields.forEach(field => field.addEventListener('input', () => {
      status.textContent = 'Ada perubahan. Klik Simpan pada kotak ini untuk memperbarui preview.';
    }));
  });
  $('quarter').addEventListener('change', loadDraft);
  const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
  $('cover-date').value = today;
  $('cover-year').value = today.slice(0, 4);
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
