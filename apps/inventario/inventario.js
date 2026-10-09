// INVENTARIO — comportamiento de las pantallas (formularios únicos, etiquetas, fotos, confirmaciones).
(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const modal = (id) => bootstrap.Modal.getOrCreateInstance($(id));
  const DATA = JSON.parse(($('inv-data') || {}).textContent || '{"obj":{},"loc":{}}');
  const PERM = DATA.perm || { add: true, edit: true, delete: true, backup: true };

  // Sin permiso de edición, el formulario solo sirve para ver los datos
  function setReadOnly(form, ro) {
    form.querySelectorAll('input:not([type=hidden]), select, textarea, [data-pick], [data-autoname]').forEach((el) => { el.disabled = ro; });
    form.querySelectorAll('[type=submit]').forEach((b) => b.classList.toggle('d-none', ro));
  }

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
    const ro = !isNew && !PERM.edit;
    setReadOnly($('formObj'), ro);
    if (ro) $('modalObjTitle').textContent = o.objeto || 'Detalle';
    $('o_delete').classList.toggle('d-none', isNew || !PERM.delete);
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
    const ro = !isNew && !PERM.edit;
    setReadOnly($('formLoc'), ro);
    if (ro) $('modalLocTitle').textContent = l.nombre || 'Detalle';
    $('l_delete').classList.toggle('d-none', isNew || !PERM.delete);
    if (!isNew) { const b = $('l_delete'); b.dataset.id = l.id; b.dataset.label = '«' + l.nombre + '»'; b.dataset.warn = l.usos ? l.usos + ' objeto(s) seguirán apuntando a este nombre.' : ''; }
    modal('modalLoc').show();
  }

  // ---------- Panel «qué hay en esta localización» ----------
  const thumbUrl = (v) => {
    v = (v || '').trim();
    if (!v) return fallbackSvg;
    if (/^(https?:|data:)/i.test(v)) return v;
    return 'thumb.php?f=' + encodeURIComponent(v.split('/').pop());
  };
  const locCache = {};
  let locObjsCurrent = null;

  function renderLocObjs(res) {
    const grid = $('locObjsGrid');
    grid.textContent = '';
    $('locObjsLoading').classList.add('d-none');
    const n = res.objs.length;
    $('locObjsSub').textContent = (res.loc.categoria ? res.loc.categoria + ' · ' : '') + n + (n === 1 ? ' objeto' : ' objetos');
    const desc = $('locObjsDesc');
    desc.textContent = res.loc.descripcion_del_contenido || '';
    desc.classList.toggle('d-none', !res.loc.descripcion_del_contenido);
    $('locObjsEmpty').classList.toggle('d-none', n > 0);
    res.objs.forEach((o) => {
      DATA.obj[o.id] = o;                                   // el formulario de objeto lo rellena desde aquí
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'loc-obj'; b.dataset.locObj = o.id;
      b.title = o.objeto + (Number(o.cantidad) > 1 ? ' (x' + o.cantidad + ')' : '');
      const img = document.createElement('img');
      img.src = thumbUrl(o.portada_http); img.alt = ''; img.loading = 'lazy';
      img.addEventListener('error', () => { img.src = fallbackSvg; }, { once: true });
      const name = document.createElement('span');
      name.className = 'loc-obj-name'; name.textContent = o.objeto || 'Sin título';
      const kind = document.createElement('small');
      kind.className = 'loc-obj-kind'; kind.textContent = o.tipo || '';
      b.append(img, name, kind);
      grid.appendChild(b);
    });
  }

  function openLocObjs(id) {
    const l = DATA.loc[id]; if (!l) return;
    locObjsCurrent = id;
    $('locObjsTitle').textContent = l.nombre || 'Localización';
    $('locObjsSub').textContent = '';
    $('locObjsDesc').classList.add('d-none');
    $('locObjsGrid').textContent = '';
    $('locObjsEmpty').classList.add('d-none');
    $('locObjsLoading').classList.remove('d-none');
    $('locObjsEdit').querySelector('span').textContent = PERM.edit ? 'Editar localización' : 'Ver detalle';
    modal('modalLocObjs').show();
    if (locCache[id]) return renderLocObjs(locCache[id]);
    fetch('index.php?ajax=loc_objs&id=' + encodeURIComponent(id), { headers: { Accept: 'application/json' } })
      .then((r) => { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then((res) => { locCache[id] = res; if (locObjsCurrent === id) renderLocObjs(res); })
      .catch(() => { $('locObjsLoading').textContent = 'No se pudo cargar el contenido. Inténtalo de nuevo.'; });
  }

  // Cierra el panel y, cuando termina de cerrarse, ejecuta la acción (evita dos ventanas a la vez)
  function afterLocPanel(fn) {
    const el = $('modalLocObjs');
    el.addEventListener('hidden.bs.modal', fn, { once: true });
    bootstrap.Modal.getOrCreateInstance(el).hide();
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
      return kind === 'obj' ? fillObj(DATA.obj[id]) : openLocObjs(id);
    }

    const lo = t.closest('[data-loc-obj]');
    if (lo) { const o = DATA.obj[lo.dataset.locObj]; return o && afterLocPanel(() => fillObj(o)); }
    if (t.closest('#locObjsEdit')) { const id = locObjsCurrent; return afterLocPanel(() => fillLoc(DATA.loc[id])); }
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
