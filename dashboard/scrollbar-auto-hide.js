/* Keep a thin scrollbar visible only while its own pane is scrolling. */
(() => {
  const style = document.createElement('style');
  style.textContent = `
    .dashboard-scroll-pane { scrollbar-width: thin; scrollbar-color: transparent transparent; }
    .dashboard-scroll-pane::-webkit-scrollbar { display: block; width: 5px; height: 5px; }
    .dashboard-scroll-pane::-webkit-scrollbar-track { background: transparent; }
    .dashboard-scroll-pane::-webkit-scrollbar-thumb { background: transparent; border-radius: 10px; }
    .dashboard-scroll-pane.is-scrolling { scrollbar-color: #aaa3bd transparent; }
    .dashboard-scroll-pane.is-scrolling::-webkit-scrollbar-thumb { background: #aaa3bd; }
    aside.dashboard-scroll-pane.is-scrolling { scrollbar-color: #ffffff80 transparent; }
    aside.dashboard-scroll-pane.is-scrolling::-webkit-scrollbar-thumb { background: #ffffff80; }
    @media (forced-colors: active) { .dashboard-scroll-pane { scrollbar-color: auto; } }
  `;
  document.head.append(style);
  document.querySelectorAll('main, aside, .tabs, .cards').forEach(pane => pane.classList.add('dashboard-scroll-pane'));
  const timers = new WeakMap();
  document.addEventListener('scroll', event => {
    const pane = event.target;
    if (!pane.classList?.contains('dashboard-scroll-pane')) return;
    pane.classList.add('is-scrolling');
    clearTimeout(timers.get(pane));
    timers.set(pane, setTimeout(() => {
      pane.classList.remove('is-scrolling');
      timers.delete(pane);
    }, 900));
  }, { capture: true, passive: true });
})();
