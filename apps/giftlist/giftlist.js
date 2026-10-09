// GIFT LIST: buscador en la lista, edición y confirmación de borrado.
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const DATA = JSON.parse(($('gift-data') || {}).textContent || '{}');

  // Buscador instantáneo
  const search = $('giftSearch');
  if (search) {
    const cols = [...document.querySelectorAll('.gift-col')];
    const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    search.addEventListener('input', () => {
      const q = norm(search.value.trim());
      let shown = 0;
      cols.forEach((c) => {
        const ok = !q || norm(c.dataset.search).includes(q);
        c.classList.toggle('d-none', !ok);
        if (ok) shown++;
      });
      $('giftNoResults').classList.toggle('d-none', shown > 0);
      $('giftCount').textContent = q ? shown + ' de ' + cols.length : cols.length;
    });
  }

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
      $('d_text').textContent = '¿Eliminar «' + del.dataset.name + '» de tu lista?';
      bootstrap.Modal.getOrCreateInstance($('modalDeleteGift')).show();
    }
  });

  // Al abrir el formulario de añadir, foco en el nombre
  const add = $('addGift');
  if (add) add.addEventListener('shown.bs.collapse', () => $('g_name').focus());
})();
