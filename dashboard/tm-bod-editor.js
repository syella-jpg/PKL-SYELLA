(() => {
  const allowed = new Set(['B', 'STRONG', 'I', 'EM', 'U', 'P', 'DIV', 'BR', 'UL', 'OL', 'LI', 'S', 'STRIKE', 'DEL', 'BLOCKQUOTE']);
  function clean(html) {
    const source = document.createElement('template'); source.innerHTML = html;
    const output = document.createElement('div');
    function copy(from, to) {
      from.childNodes.forEach(node => {
        if (node.nodeType === 3) { to.append(document.createTextNode(node.textContent)); return; }
        if (node.nodeType !== 1 || ['SCRIPT','STYLE','IFRAME','OBJECT','SVG'].includes(node.tagName)) return;
        const target = document.createElement(allowed.has(node.tagName) ? node.tagName.toLowerCase() : 'span');
        if (['left','center','right'].includes(node.style.textAlign)) target.style.textAlign = node.style.textAlign;
        to.append(target); copy(node, target);
      });
    }
    copy(source.content, output); return output.innerHTML;
  }
  const read = value => typeof value === 'string' ? { text: value, html: '', image: '' } : { text: value?.text || '', html: clean(value?.html || ''), image: /^data:image\/(png|jpeg);base64,/.test(value?.image || '') ? value.image : '' };
  document.querySelectorAll('main section.analysis').forEach((section, index) => {
    const field = section.querySelector('textarea');
    const editor = document.createElement('div');
    editor.className = 'rich-editor'; editor.contentEditable = 'true'; editor.role = 'textbox';
    editor.setAttribute('aria-multiline', 'true'); editor.setAttribute('aria-label', 'Isi analisis ' + (index + 1));
    editor.dataset.placeholder = 'Tulis isi di sini…';
    const toolbar = document.createElement('div'); toolbar.className = 'editor-toolbar'; toolbar.setAttribute('role', 'toolbar'); toolbar.setAttribute('aria-label', 'Format analisis');
    let selection;
    function remember() { const current = window.getSelection(); if (current.rangeCount && editor.contains(current.anchorNode)) selection = current.getRangeAt(0).cloneRange(); }
    function sync() { field.value = editor.innerText; field.dispatchEvent(new Event('input', { bubbles: true })); }
    ['keyup','mouseup','focus','input'].forEach(type => editor.addEventListener(type, remember));
    editor.addEventListener('input', sync);
    editor.addEventListener('paste', event => { event.preventDefault(); document.execCommand('insertText', false, event.clipboardData.getData('text/plain')); sync(); });
    const commands = [
      ['bold','B','Tebal'], ['italic','I','Miring'], ['strikeThrough','S','Coret'],
      ['underline','U','Garis bawah'], ['insertUnorderedList','•≡','Daftar poin'],
      ['insertOrderedList','1≡','Daftar nomor'], ['blockquote','❝','Kutipan'],
      ['undo','↶','Urungkan'], ['redo','↷','Ulangi']
    ];
    const buttons = [];
    function updateToolbar() {
      buttons.forEach(({ button, command }) => {
        if (['undo','redo'].includes(command)) return;
        const active = command === 'blockquote'
          ? document.queryCommandValue('formatBlock').toLowerCase() === 'blockquote'
          : document.queryCommandState(command);
        button.setAttribute('aria-pressed', String(active));
      });
    }
    commands.forEach(([command,label,title]) => {
      const button = document.createElement('button'); button.type = 'button'; button.textContent = label;
      button.dataset.command = command; button.title = title; button.setAttribute('aria-label', title);
      button.addEventListener('mousedown', event => event.preventDefault());
      button.onclick = () => {
        editor.focus();
        if (selection && editor.contains(selection.commonAncestorContainer)) {
          const current = window.getSelection(); current.removeAllRanges(); current.addRange(selection);
        }
        if (command === 'blockquote') {
          document.execCommand('formatBlock', false, document.queryCommandValue('formatBlock').toLowerCase() === 'blockquote' ? 'div' : 'blockquote');
        } else document.execCommand(command, false);
        remember(); sync(); updateToolbar();
      };
      buttons.push({ button, command }); toolbar.append(button);
    });
    ['keyup','mouseup','input','focus'].forEach(type => editor.addEventListener(type, updateToolbar));
    const upload = document.createElement('input'); upload.type = 'file'; upload.accept = 'image/png,image/jpeg,image/webp'; upload.hidden = true;
    const uploadButton = document.createElement('button'); uploadButton.type = 'button'; uploadButton.textContent = '▧ Gambar'; uploadButton.className = 'toolbar-upload'; uploadButton.title = 'Unggah gambar'; uploadButton.setAttribute('aria-label', 'Unggah gambar'); uploadButton.onclick = () => upload.click();
    toolbar.append(uploadButton, upload);
    const imageBox = document.createElement('div'); imageBox.className = 'editor-image'; imageBox.hidden = true;
    const thumbnail = document.createElement('img'); thumbnail.alt = 'Gambar analisis';
    const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Hapus gambar';
    imageBox.append(thumbnail, remove);
    let image = '', pending = 0, revision = 0;
    function showImage(value) { image = value; imageBox.hidden = !value; if (value) thumbnail.src = value; else thumbnail.removeAttribute('src'); }
    remove.onclick = () => { showImage(''); sync(); };
    upload.onchange = async () => {
      const file = upload.files[0]; if (!file) return;
      const status = section.querySelector('.section-save-status');
      if (!['image/png','image/jpeg','image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) { status.textContent = 'Pilih gambar PNG, JPG, atau WebP maksimal 5 MB.'; upload.value = ''; return; }
      const currentRevision = revision;
      pending++; uploadButton.disabled = true;
      try {
        const bitmap = await createImageBitmap(file);
        const ratio = Math.min(1, 1200 / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas'); canvas.width = Math.max(1, Math.round(bitmap.width * ratio)); canvas.height = Math.max(1, Math.round(bitmap.height * ratio));
        const ctx = canvas.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0,0,canvas.width,canvas.height); ctx.drawImage(bitmap,0,0,canvas.width,canvas.height); bitmap.close();
        if (revision === currentRevision) { showImage(canvas.toDataURL('image/jpeg', .8)); sync(); }
      } catch (_) { status.textContent = 'Gambar tidak dapat dibaca. Pilih gambar lain.'; }
      finally { pending--; uploadButton.disabled = false; upload.value = ''; }
    };
    field.hidden = true; field.before(toolbar, editor, imageBox);
    field.richEditor = {
      get: () => ({ text: editor.innerText, html: clean(editor.innerHTML), image }),
      set: value => { revision++; const saved = read(value); editor.innerHTML = saved.html; if (!saved.html) editor.textContent = saved.text; field.value = saved.text; showImage(saved.image); selection = null; },
      pending: () => pending > 0
    };
  });
  async function bodyImage(saved, fontSize) {
    const canvas = document.createElement('canvas'); canvas.width = 1640; canvas.height = 620;
    const ctx = canvas.getContext('2d'); ctx.scale(2,2); ctx.textBaseline = 'top'; ctx.fillStyle = '#111';
    const hasImage = !!saved.image, width = hasImage && saved.text.trim() ? 470 : 820;
    const container = document.createElement('div'); container.innerHTML = clean(saved.html || ''); if (!saved.html) container.textContent = saved.text;
    const paragraphs = []; let runs = [], align = 'left';
    const flush = () => { if (runs.length) paragraphs.push({ runs, align }); runs = []; };
    function walk(node, style = {}) {
      if (node.nodeType === 3) { runs.push({ text: node.textContent, ...style }); return; }
      if (node.nodeType !== 1) return;
      const block = ['P','DIV','LI','BLOCKQUOTE'].includes(node.tagName);
      if (block) { flush(); align = node.style.textAlign || 'left'; }
      if (node.tagName === 'BR') { flush(); if (!paragraphs.length) paragraphs.push({ runs: [], align }); return; }
      if (node.tagName === 'BLOCKQUOTE') runs.push({ text: '“ ', italic: true });
      if (node.tagName === 'LI') runs.push({ text: node.parentElement.tagName === 'OL' ? `${Array.from(node.parentElement.children).indexOf(node)+1}. ` : '• ', ...style });
      const next = { bold: style.bold || ['B','STRONG'].includes(node.tagName), italic: style.italic || ['I','EM','BLOCKQUOTE'].includes(node.tagName), underline: style.underline || node.tagName === 'U', strike: style.strike || ['S','STRIKE','DEL'].includes(node.tagName) };
      node.childNodes.forEach(child => walk(child, next)); if (block) flush();
    }
    container.childNodes.forEach(child => walk(child)); flush();
    function layout(size) {
      const lines = [];
      paragraphs.forEach(paragraph => {
        let parts = [], length = 0;
        const push = () => { lines.push({ parts, length, align: paragraph.align }); parts = []; length = 0; };
        paragraph.runs.forEach(run => {
          ctx.font = `${run.italic ? 'italic ' : ''}${run.bold ? 'bold ' : ''}${size}px Arial`;
          for (const token of run.text.split(/(\n|\s+)/)) {
            if (token.includes('\n')) { push(); continue; }
            if (!token) continue;
            const add = text => { const w = ctx.measureText(text).width; if (length + w > width && parts.length) push(); parts.push({ ...run, text, width: w }); length += w; };
            if (ctx.measureText(token).width > width) Array.from(token).forEach(add); else add(token);
          }
        }); push();
      });
      return lines;
    }
    let size = Math.min(24, Math.max(10,fontSize)), lines = layout(size);
    while (lines.length * size * 1.2 > 310 && size > 10) lines = layout(--size);
    lines.forEach((line, index) => {
      let x = line.align === 'center' ? (width-line.length)/2 : line.align === 'right' ? width-line.length : 0;
      const y = index * size * 1.2;
      line.parts.forEach(part => {
        ctx.font = `${part.italic ? 'italic ' : ''}${part.bold ? 'bold ' : ''}${size}px Arial`; ctx.fillText(part.text,x,y);
        if (part.underline) { ctx.fillRect(x,y+size,part.width,Math.max(1,size/16)); }
        if (part.strike) ctx.fillRect(x,y+size*.55,part.width,Math.max(1,size/16));
        x += part.width;
      });
    });
    if (hasImage) {
      const picture = new Image(); picture.src = saved.image; await picture.decode();
      const boxX = saved.text.trim() ? 500 : 0, boxWidth = saved.text.trim() ? 320 : 820;
      const scale = Math.min(boxWidth/picture.width,310/picture.height);
      const w = picture.width*scale,h = picture.height*scale;
      ctx.drawImage(picture,boxX+(boxWidth-w)/2,(310-h)/2,w,h);
    }
    return { data: canvas.toDataURL('image/png'), overflow: lines.length * size * 1.2 > 310 };
  }
  window.TmBodEditor = { read, bodyImage };
})();
