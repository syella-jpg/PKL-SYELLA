// Run in an isolated browser profile after all TM BOD scripts load.
(async () => {
  const key = 'tm-bod-refresh-test-stage';
  const check = (condition, message) => { if (!condition) throw Error(message); };
  try {
    const section = document.querySelector('main section.analysis');
    if (!sessionStorage.getItem(key)) {
      const canvas = document.createElement('canvas'); canvas.width = 100; canvas.height = 60;
      const ctx = canvas.getContext('2d'); ctx.fillStyle = 'red'; ctx.fillRect(0,0,100,60);
      section.querySelector('textarea').richEditor.set({ text: 'Persisted analysis', html: '<b>Persisted analysis</b>', images: [canvas.toDataURL('image/png')] });
      await section.querySelector('.section-save').onclick();
      check(section._locked, 'save locks slide');
      sessionStorage.setItem(key, 'saved');
      location.reload();
      return;
    }
    for (let i = 0; i < 100 && !document.querySelector('#preview-slide svg'); i++) await new Promise(resolve => setTimeout(resolve, 50));
    check(section._locked, 'locked after actual refresh');
    check(section.querySelector('.rich-editor').contentEditable === 'false', 'editor read-only after refresh');
    check(document.querySelector('#preview-slide svg'), 'preview restored after refresh');
    check(document.getElementById('slide-position').textContent.startsWith('Slide 2 dari'), 'restored selected analysis');
    check(!document.getElementById('preview-download').disabled, 'download available without editing or saving');
    check(document.querySelector('#preview-slide svg image:last-of-type').getAttribute('href').startsWith('data:image/png'), 'saved formatting and image rendered');
    document.body.dataset.testResult = 'PASS: real browser refresh restores locked slide, formatted preview, images, navigation and download without edit/save';
  } catch (error) { document.body.dataset.testResult = 'FAIL: ' + error.stack; }
})();
