// ADMIN ONEDRIVE: lanza la sincronización «por tramos» y la repite sola hasta terminar, con barra de progreso.
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const box = $('odProgress');
  if (!box) return;
  const bar = $('odBar'), text = $('odText'), note = $('odNote'), result = $('odResult');
  const buttons = [...document.querySelectorAll('[data-od-run]')];
  let running = false;

  const warn = (e) => { if (running) { e.preventDefault(); e.returnValue = ''; } };
  window.addEventListener('beforeunload', warn);

  function setBar(done, total) {
    const pct = total > 0 ? Math.min(100, Math.round(done * 100 / total)) : 0;
    bar.style.width = pct + '%';
    bar.textContent = total > 0 ? pct + '%' : '';
    bar.classList.toggle('progress-bar-animated', running);
  }
  const wait = (ms) => new Promise((r) => setTimeout(r, ms));

  async function call(mode) {
    const fd = new FormData();
    fd.append('csrf', box.dataset.csrf);
    fd.append('mode', mode);
    const res = await fetch('/src/onedrive/run.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    let data = null;
    try { data = await res.json(); } catch (e) { /* respuesta que no es JSON */ }
    if (!data) throw new Error('El servidor no respondió como se esperaba (HTTP ' + res.status + ').');
    return data;
  }

  async function run(mode) {
    if (running) return;
    running = true;
    buttons.forEach((b) => { b.disabled = true; });
    result.className = 'd-none'; result.textContent = '';
    box.classList.remove('d-none'); note.classList.remove('d-none');
    text.textContent = 'Empezando…'; setBar(0, 0);
    let changed = 0, busy = 0, last = null;
    try {
      for (let i = 0; i < 80; i++) {                     // cada tramo trabaja unos 12 s; se repite hasta terminar
        last = await call(i === 0 ? mode : 'continue');
        if (last.state === 'busy') { if (++busy > 8) break; text.textContent = 'Esperando a otra sincronización en marcha…'; await wait(2500); i--; continue; }
        busy = 0;
        changed += last.changed || 0;
        if (last.state !== 'synced') break;
        const done = Math.max(0, (last.total || 0) - (last.todo || 0));
        setBar(done, last.total || 0);
        text.textContent = last.pending > 0
          ? 'Descargando notas… ' + done + (last.total ? ' de ' + last.total : '')
          : 'Terminando…';
        if (!(last.pending > 0)) break;
      }
      if (last && last.state === 'synced' && !(last.pending > 0)) {
        running = false; setBar(last.total || last.notes || 1, last.total || last.notes || 1);
        result.className = 'alert alert-success mt-3 mb-0';
        result.textContent = '✅ Listo. Sincronización terminada: ' + (last.notes || 0) + ' notas en el servidor' + (changed ? ' (' + changed + ' cambio(s) en esta ejecución).' : ' (ya estaba todo al día).');
        if ($('odNotes')) $('odNotes').textContent = last.notes || 0;
        if ($('odLast')) $('odLast').textContent = 'hace un momento';
        if (changed && $('odChange')) $('odChange').textContent = 'hace un momento';
        note.classList.add('d-none'); text.textContent = 'Sincronización terminada.';
      } else {
        running = false;
        result.className = 'alert alert-warning mt-3 mb-0';
        result.textContent = '⚠ ' + ((last && last.message) || 'La sincronización se ha detenido.') + (last && last.pending > 0 ? ' Puedes volver a pulsar «Sincronizar ahora» para continuar.' : '');
        note.classList.add('d-none'); text.textContent = 'Detenida.';
      }
    } catch (err) {
      running = false;
      result.className = 'alert alert-danger mt-3 mb-0';
      result.textContent = '⚠ ' + err.message + ' Puedes volver a pulsar «Sincronizar ahora» para continuar.';
      note.classList.add('d-none'); text.textContent = 'Detenida.';
    } finally {
      running = false; bar.classList.remove('progress-bar-animated');
      buttons.forEach((b) => { b.disabled = false; });
    }
  }

  buttons.forEach((b) => b.addEventListener('click', (e) => {
    e.preventDefault();
    if (b.dataset.odConfirm && !confirm(b.dataset.odConfirm)) return;
    run(b.dataset.odRun);
  }));
})();
