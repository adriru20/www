// INVENTARIO — comportamiento de las pantallas (formularios únicos, etiquetas, fotos, confirmaciones).
(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const modal = (id) => bootstrap.Modal.getOrCreateInstance($(id));
  const DATA = JSON.parse(($('inv-data') || {}).textContent || '{"obj":{},"loc":{}}');

  // ---------- Utilidades ----------
  const imgUrl = (v) => {
    v = (v || '').trim();
    if (!v) return fallbackSvg;
    if (/^(https?:|data:)/i.test(v)) return v;
    return 'img/' + encodeURIComponent(v.split('/').pop());
  };

  function slug(text) {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_]/g, '').toLowerCase();
  }
  function autoName(form, entity) {
    let a, b;
    if (entity === 'objeto') { a = form.querySelector('[name="tipo"]')?.value || 'Objeto'; b = form.querySelector('[name="titulo"]')?.value || 'SinTitulo'; }
    else if (entity === 'localizacion') { a = form.querySelector('[name="categoria"]')?.value || 'Cat'; b = form.querySelector('[name="nombre"]')?.value || 'Loc'; }
    else { a = 'img'; b = Date.now().toString().slice(-6); }
    return slug(a + '_' + b);
  }

  // ---------- Etiquetas (localizaciones, géneros, plataformas) ----------
  const tagsOf = (input) => input.value.split(',').map((t) => t.trim()).filter(Boolean);
  function refreshTags(inputId) {
    const input = $(inputId); if (!input) return;
    const current = tagsOf(input);
    document.querySelectorAll('[data-tag-target="' + inputId + '"]').forEach((chip) => {
      const on = current.includes(chip.dataset.tag);
      chip.classList.toggle('bg-secondary', !on);
      chip.classList.toggle('bg-primary', on);
    });
  }
  function toggleTag(inputId, value) {
    const input = $(inputId); if (!input) return;
    const tags = tagsOf(input), i = tags.indexOf(value);
    i > -1 ? tags.splice(i, 1) : tags.push(value);
    input.value = tags.join(', ');
    refreshTags(inputId);
  }

  // ---------- Formulario de objeto ----------
  function toggleConditionals() {
    const tipo = $('o_tipo').value;
    const game = tipo === 'Juegos' || tipo === 'Películas';
    $('o_wrap_cat').classList.toggle('d-none', game);
    $('o_wrap_tipo').classList.toggle('col-6', !game);
    $('o_wrap_tipo').classList.toggle('col-12', game);
    $('o_wrap_plat').classList.toggle('d-none', tipo !== 'Juegos');
    $('o_game').classList.toggle('d-none', !game);
    $('o_phys').classList.toggle('d-none', !(game && $('o_formato').value === 'Físico'));
  }

  function fillObj(o) {
    const isNew = !o;
    o = o || {};
    $('modalObjTitle').textContent = isNew ? 'Nuevo objeto' : 'Editar: ' + (o.objeto || 'Sin título');
    $('o_id').value = o.id || '';
    $('o_return').value = isNew ? '' : location.search;
    $('o_titulo').value = o.objeto || '';
    $('o_cantidad').value = o.cantidad || 1;
    $('o_portada').value = (o.portada_http || '').startsWith('http') ? o.portada_http : (o.portada_http || '').split('/').pop();
    $('o_preview').src = imgUrl(o.portada_http);
    $('o_custom').value = '';
    $('o_file_cam').value = ''; $('o_file_folder').value = '';
    $('o_tipo').value = o.tipo || 'Objetos';
    $('o_cat').value = o.tipo_de_objeto || '';
    $('o_gen').value = o.generos || '';
    $('o_formato').value = o.formato || 'Físico';
    $('o_fa').value = o.formato_de_archivo || '';
    $('o_caja').checked = !!Number(o.en_la_caja);
    $('o_precio').value = Number(o.precio_de_venta) > 0 ? o.precio_de_venta : 0;
    $('o_loc').value = o.localizacion || '';
    $('o_plat').value = o.plataformas || '';
    $('o_desc').value = o.descripcion || '';
    $('o_delete').classList.toggle('d-none', isNew);
    if (!isNew) { const b = $('o_delete'); b.dataset.id = o.id; b.dataset.label = '«' + (o.objeto || 'este objeto') + '»'; b.dataset.warn = ''; }
    ['o_gen', 'o_loc', 'o_plat'].forEach(refreshTags);
    toggleConditionals();
    modal('modalObj').show();
  }

  function fillLoc(l) {
    const isNew = !l;
    l = l || {};
    $('modalLocTitle').textContent = isNew ? 'Nueva localización' : 'Editar: ' + (l.nombre || '');
    $('l_id').value = l.id || '';
    $('l_return').value = isNew ? '' : location.search;
    $('l_nombre').value = l.nombre || '';
    $('l_cat').value = l.categoria || '';
    $('l_foto').value = (l.foto_http || '').startsWith('http') ? l.foto_http : (l.foto_http || '').split('/').pop();
    $('l_preview').src = imgUrl(l.foto_http);
    $('l_custom').value = '';
    $('l_file_cam').value = ''; $('l_file_folder').value = '';
    $('l_desc').value = l.descripcion_del_contenido || '';
    $('l_uses').textContent = isNew ? '' : (l.usos ? l.usos + ' objeto(s) están en esta localización.' : 'Ningún objeto está en esta localización.');
    $('l_delete').classList.toggle('d-none', isNew);
    if (!isNew) { const b = $('l_delete'); b.dataset.id = l.id; b.dataset.label = '«' + l.nombre + '»'; b.dataset.warn = l.usos ? l.usos + ' objeto(s) seguirán apuntando a este nombre.' : ''; }
    modal('modalLoc').show();
  }

  // ---------- Confirmar borrado ----------
  function confirmDelete(btn) {
    $('c_action').value = btn.dataset.confirm;
    $('c_id').value = btn.dataset.id || '';
    $('c_name').value = btn.dataset.name || '';
    $('c_return').value = location.search;
    $('c_text').textContent = '¿Eliminar ' + (btn.dataset.label || 'este elemento') + '?';
    $('c_warn').textContent = btn.dataset.warn || '';
    ['modalObj', 'modalLoc'].forEach((m) => { const inst = bootstrap.Modal.getInstance($(m)); if (inst) inst.hide(); });
    modal('modalConfirm').show();
  }

  // ---------- Eventos (delegación: funciona con cualquier tarjeta) ----------
  document.addEventListener('click', (e) => {
    const t = e.target;
    const card = t.closest('[data-edit]');
    if (card && !t.closest('a, button')) {
      const kind = card.dataset.edit, id = card.dataset.id;
      return kind === 'obj' ? fillObj(DATA.obj[id]) : fillLoc(DATA.loc[id]);
    }
    const nw = t.closest('[data-inv-new]');
    if (nw) return nw.dataset.invNew === 'obj' ? fillObj(null) : fillLoc(null);

    const chip = t.closest('[data-tag-target]');
    if (chip) return toggleTag(chip.dataset.tagTarget, chip.dataset.tag);

    const pick = t.closest('[data-pick]');
    if (pick) return $(pick.dataset.pick).click();

    const auto = t.closest('[data-autoname]');
    if (auto) { $(auto.dataset.autoname).value = autoName(auto.closest('form'), auto.dataset.entity); return; }

    const del = t.closest('[data-confirm]');
    if (del) return confirmDelete(del);

    const ren = t.closest('[data-rename-img]');
    if (ren) {
      $('r_old').value = ren.dataset.renameImg; $('r_return').value = location.search;
      $('r_old_label').textContent = ren.dataset.renameImg;
      $('r_new').value = ren.dataset.renameImg.replace(/\.[^.]+$/, '');
      return modal('modalRenameImg').show();
    }

    const lb = t.closest('[data-lightbox]');
    if (lb) { $('lb_img').src = lb.dataset.lightbox; $('lb_caption').textContent = lb.dataset.caption || ''; modal('modalLightbox').show(); }
  });

  // Teclado: Enter/Espacio abren la tarjeta enfocada
  document.addEventListener('keydown', (e) => {
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-edit]')) { e.preventDefault(); e.target.click(); }
  });

  // Cambios en el formulario de objeto
  ['o_tipo', 'o_formato'].forEach((id) => $(id) && $(id).addEventListener('change', toggleConditionals));
  ['o_gen', 'o_loc', 'o_plat'].forEach((id) => $(id) && $(id).addEventListener('input', () => refreshTags(id)));
  $('o_portada') && $('o_portada').addEventListener('input', (e) => { $('o_preview').src = imgUrl(e.target.value); });
  $('l_foto') && $('l_foto').addEventListener('input', (e) => { $('l_preview').src = imgUrl(e.target.value); });

  // Vista previa al elegir un archivo
  function bindFile(inputId, previewId, textId, nameId, entity) {
    const input = $(inputId); if (!input) return;
    input.addEventListener('change', () => {
      const file = input.files && input.files[0]; if (!file) return;
      const reader = new FileReader();
      reader.onload = (ev) => { $(previewId).src = ev.target.result; };
      reader.readAsDataURL(file);
      $(textId).value = file.name;
      // El otro selector se vacía para que solo se suba una foto
      const other = inputId.endsWith('cam') ? inputId.replace('cam', 'folder') : inputId.replace('folder', 'cam');
      if ($(other)) $(other).value = '';
      if (!$(nameId).value.trim()) $(nameId).value = autoName(input.closest('form'), entity);
    });
  }
  bindFile('o_file_cam', 'o_preview', 'o_portada', 'o_custom', 'objeto');
  bindFile('o_file_folder', 'o_preview', 'o_portada', 'o_custom', 'objeto');
  bindFile('l_file_cam', 'l_preview', 'l_foto', 'l_custom', 'localizacion');
  bindFile('l_file_folder', 'l_preview', 'l_foto', 'l_custom', 'localizacion');

  // Subida múltiple: miniaturas de lo elegido
  const up = $('u_files');
  if (up) up.addEventListener('change', () => {
    const box = $('u_previews'); box.innerHTML = '';
    [...up.files].slice(0, 12).forEach((f) => {
      const img = document.createElement('img'); img.className = 'preview-img'; img.alt = f.name; img.title = f.name;
      const r = new FileReader(); r.onload = (ev) => { img.src = ev.target.result; }; r.readAsDataURL(f); box.appendChild(img);
    });
    $('u_custom').disabled = up.files.length > 1;
  });

  // Si una miniatura falla, prueba la imagen original y luego el marcador
  document.addEventListener('error', (e) => {
    const img = e.target;
    if (!(img instanceof HTMLImageElement) || !img.classList.contains('memento-img')) return;
    if (img.dataset.full && img.dataset.tried !== '1') { img.dataset.tried = '1'; img.src = img.dataset.full; }
    else if (img.src !== fallbackSvg) img.src = fallbackSvg;
  }, true);

  // ---------- Service Worker (PWA) ----------
  if ('serviceWorker' in navigator && location.pathname.startsWith('/apps/inventario/')) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/apps/inventario/sw.js', { scope: '/apps/inventario/' }).catch(() => {});
    });
  }
})();
