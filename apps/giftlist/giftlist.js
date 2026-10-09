// GIFT LIST: buscador en la lista, edición y confirmación de borrado.
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const DATA = JSON.parse(($('gift-data') || {}).textContent || '{}');
  const OWNER = ($('modalDeleteGift') || { dataset: {} }).dataset.ownerName || '';   // vacío en mi propia lista

  // Buscador instantáneo + filtro por estado (Todos / Pendientes / Comprados)
  const search = $('giftSearch');
  const cols = [...document.querySelectorAll('.gift-col')];
  let state = 'all';
  const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  function applyFilters() {
    const q = search ? norm(search.value.trim()) : '';
    let shown = 0;
    cols.forEach((c) => {
      const ok = (!q || norm(c.dataset.search).includes(q)) && (state === 'all' || c.dataset.state === state);
      c.classList.toggle('d-none', !ok);
      if (ok) shown++;
    });
    if ($('giftNoResults')) $('giftNoResults').classList.toggle('d-none', shown > 0);
    if ($('giftCount')) $('giftCount').textContent = (q || state !== 'all') ? shown + ' de ' + cols.length : cols.length;
  }
  if (search) search.addEventListener('input', applyFilters);
  document.querySelectorAll('[data-gift-filter]').forEach((b) => b.addEventListener('click', () => {
    state = b.dataset.giftFilter;
    document.querySelectorAll('[data-gift-filter]').forEach((x) => x.classList.toggle('active', x === b));
    applyFilters();
  }));

  // Editar / borrar (delegación)
  document.addEventListener('click', (e) => {
    const edit = e.target.closest('[data-gift-edit]');
    if (edit) {
      const g = DATA[edit.dataset.giftEdit]; if (!g) return;
      $('e_id').value = edit.dataset.giftEdit;
      $('e_name').value = g.name || '';
      $('e_url').value = g.url || '';
      $('e_desc').value = g.desc || '';
      bootstrap.Modal.getOrCreateInstance($('modalEditGift')).show();
      return;
    }
    const del = e.target.closest('[data-gift-delete]');
    if (del) {
      $('d_id').value = del.dataset.giftDelete;
      $('d_text').textContent = '¿Eliminar «' + del.dataset.name + '» de ' + (OWNER ? 'la lista de ' + OWNER : 'tu lista') + '?';
      bootstrap.Modal.getOrCreateInstance($('modalDeleteGift')).show();
    }
  });

  // Al abrir el formulario de añadir, foco en el nombre
  const add = $('addGift');
  if (add) add.addEventListener('shown.bs.collapse', () => $('g_name').focus());
})();
