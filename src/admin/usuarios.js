// ADMIN DE USUARIOS: formularios de rol/secciones, contraseñas y borrado.
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const DATA = JSON.parse(($('admin-data') || {}).textContent || '{"users":{},"presets":{}}');
  const modal = (id) => bootstrap.Modal.getOrCreateInstance($(id));

  // Marca/desmarca la lista de permisos de un formulario
  function setPerms(form, perms) {
    form.querySelectorAll('[data-perm-list] input[type=checkbox]').forEach((c) => { c.checked = perms.includes(c.value); });
    syncSubs(form);
  }
  // Los subpermisos solo se pueden marcar si la sección está marcada
  function syncSubs(form) {
    form.querySelectorAll('[data-sub-of]').forEach((sub) => {
      const parent = form.querySelector('[data-app="' + sub.dataset.subOf + '"]');
      sub.disabled = !parent.checked;
      if (!parent.checked) sub.checked = false;
    });
  }
  // Un administrador lo tiene todo: se oculta la lista
  function syncRole(form) {
    const role = form.querySelector('[data-role-select]').value;
    form.querySelector('[data-perm-list]').classList.toggle('d-none', role === 'admin');
    form.querySelector('[data-admin-note]').classList.toggle('d-none', role !== 'admin');
  }
  // Al cambiar de rol se proponen los subpermisos habituales (sin tocar las secciones)
  function applyPreset(form) {
    const role = form.querySelector('[data-role-select]').value;
    const presetSubs = (DATA.presets[role] || []);
    form.querySelectorAll('[data-sub-of]').forEach((sub) => {
      const parent = form.querySelector('[data-app="' + sub.dataset.subOf + '"]');
      if (role !== 'admin') sub.checked = parent.checked && presetSubs.includes(sub.value);
    });
    syncSubs(form); syncRole(form);
  }

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('change', (e) => {
      if (e.target.matches('[data-role-select]')) applyPreset(form);
      else if (e.target.matches('[data-app]')) {
        // al marcar una sección, se proponen los subpermisos del rol actual
        if (e.target.checked) {
          const presetSubs = DATA.presets[form.querySelector('[data-role-select]').value] || [];
          form.querySelectorAll('[data-sub-of="' + e.target.dataset.app + '"]').forEach((s) => { s.checked = presetSubs.includes(s.value); });
        }
        syncSubs(form);
      }
    });
  });

  function randomPass(len = 12) {
    const chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    const buf = new Uint32Array(len); crypto.getRandomValues(buf);
    return [...buf].map((n) => chars[n % chars.length]).join('');
  }

  document.addEventListener('click', (e) => {
    const t = e.target;
    if (t.closest('[data-user-new]')) {
      const f = $('modalNew').querySelector('form'); f.reset();
      f.querySelector('[data-role-select]').value = 'visitante';
      setPerms(f, []); syncRole(f);   // por defecto, ninguna sección
      return modal('modalNew').show();
    }
    const edit = t.closest('[data-user-edit]');
    if (edit) {
      const u = DATA.users[edit.dataset.userEdit]; if (!u) return;
      const f = $('modalEdit').querySelector('form');
      $('u_id').value = edit.dataset.userEdit; $('u_name').textContent = u.user;
      f.querySelector('[data-role-select]').value = u.role;
      setPerms(f, u.perms); syncRole(f);
      return modal('modalEdit').show();
    }
    const pass = t.closest('[data-user-pass]');
    if (pass) {
      const u = DATA.users[pass.dataset.userPass]; if (!u) return;
      $('p_id').value = pass.dataset.userPass; $('p_name').textContent = u.user; $('p_pass').value = '';
      return modal('modalPass').show();
    }
    const del = t.closest('[data-user-delete]');
    if (del) { $('d_id').value = del.dataset.userDelete; $('d_text').textContent = '¿Eliminar a «' + del.dataset.name + '»?'; return modal('modalDelete').show(); }
    const gen = t.closest('[data-gen-pass]');
    if (gen) { $(gen.dataset.genPass).value = randomPass(); }
  });
})();
