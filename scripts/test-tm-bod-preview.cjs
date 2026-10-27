const fs = require('fs');
const vm = require('vm');
const assert = require('assert/strict');
const path = require('path');
const root = path.resolve(__dirname, '..');
const html = fs.readFileSync(path.join(root, 'dashboard/TM BOD.html'), 'utf8');
function node() {
  return { value: '', hidden: false, disabled: false, textContent: '', children: [], events: {}, attrs: {},
    classList: { add() {}, remove() {} },
    addEventListener(type, fn) { this.events[type] = fn; }, dispatchEvent() {},
    replaceChildren(...children) { this.children = children; }, append(child) { this.children.push(child); },
    setAttribute(key, value) { this.attrs[key] = String(value); }
  };
}
const nodes = new Map();
const $ = id => { if (!nodes.has(id)) nodes.set(id, node()); return nodes.get(id); };
const sections = [...html.matchAll(/<section class="analysis">([\s\S]*?)<\/section>/g)].map(([, content]) => {
  assert(content.includes('section-save'));
  const field = node(), button = node(), status = node();
  return { field, button, status,
    querySelector: selector => selector === '.section-save' ? button : status,
    querySelectorAll: selector => selector === 'textarea' ? [field] : [...content.matchAll(new RegExp(`<${selector}>([\\s\\S]*?)<\/${selector}>`, 'g'))].map(([, text]) => ({ textContent: text }))
  };
});
const stored = {};
let request;
$('quarter').value = 'Q1';
vm.runInNewContext(fs.readFileSync(path.join(root, 'dashboard/tm-bod-preview.js'), 'utf8'), {
  document: { getElementById: $, querySelectorAll: () => sections, createElementNS: node, createElement: node },
  localStorage: { getItem: key => stored[key] || null, setItem: (key, value) => { stored[key] = value; } },
  window: {}, TextEncoder, Event, URL, setTimeout, location: { protocol: 'https:', href: 'https://example.test/dashboard/TM%20BOD.html' },
  fetch: async (_, options) => { request = JSON.parse(options.body); return { ok: false, status: 503, json: async () => ({ message: 'Test response' }) }; }
});
const slideText = () => {
  const walk = n => n.textContent + n.children.map(walk).join('\n');
  return $('preview-slide').children.map(walk).join('\n');
};
(async () => {
  assert.equal($('ppt-preview').hidden, false);
  assert($('preview-slide').children[0].textContent.includes('Klik Simpan'));
  assert($('previous-slide').disabled);
  assert($('next-slide').disabled);
  sections[0].field.value = 'Saved summary';
  sections[1].field.value = 'Unsaved financial text';
  await sections[0].button.onclick();
  assert.equal($('slide-position').textContent, `Slide 2 dari ${sections.length + 3}`);
  assert(slideText().includes('Saved summary'));
  $('next-slide').onclick();
  assert.equal($('preview-slide').children[0].children[0].attrs.href, 'assets/tm-bod/manufacturing-support.png');
  $('next-slide').onclick();
  assert(!slideText().includes('Unsaved financial text'));
  await sections[1].button.onclick();
  assert.equal($('slide-position').textContent, `Slide 4 dari ${sections.length + 3}`);
  assert(slideText().includes('Unsaved financial text'));
  assert(!slideText().includes('Saved summary'));
  sections[1].field.value = 'Uncommitted change';
  await sections[0].button.onclick();
  await $('preview-download').onclick();
  assert.equal(request.slides.length, sections.length + 3);
  assert.equal(request.slides[2].type, 'manufacturing-support');
  const oemIndex = sections.findIndex(section => section.querySelectorAll('h3').some(node => node.textContent.trim() === 'Rekap keluhan OEM (justified)'));
  assert(oemIndex >= 0);
  assert.equal(request.slides[oemIndex + 3].type, 'marketing-sales');
  assert.equal(request.slides.filter(slide => slide.type === 'marketing-sales').length, 1);
  assert(request.slides.every(slide => slide.fields.length === 3 && slide.fontSizes.length === 3));
  assert.equal(request.slides[3].fields[2], 'Unsaved financial text');
  assert(!request.slides.some(slide => slide.fields.join('').includes('Uncommitted change')));
  $('quarter').value = 'Q2'; $('quarter').events.change();
  assert(sections.every(section => section.field.value === ''));
  $('quarter').value = 'Q1'; $('quarter').events.change();
  assert.equal(sections[1].field.value, 'Unsaved financial text');
  sections[0].field.value = 'Very long text '.repeat(3000);
  await sections[0].button.onclick();
  assert($('preview-download').disabled);
  assert($('preview-status').textContent.includes('terlalu panjang'));
  console.log(`PASS: ${sections.length} analysis boxes map to separate slides; saved-only content, selected slide, template preview, download payload, quarter drafts, overflow protection`);
})().catch(error => { console.error(error); process.exitCode = 1; });
