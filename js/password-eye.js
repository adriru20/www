// OJITO DE CONTRASEÑA: añade a cada <input type="password" data-eye> un botón para mostrar u ocultar lo escrito.
// Por defecto la contraseña está oculta; el ojo permite comprobarla antes de enviar.
(function () {
  'use strict';
  document.querySelectorAll('input[type="password"][data-eye]').forEach((input) => {
    const group = document.createElement('div');
    group.className = 'input-group';
    input.parentNode.insertBefore(group, input);
    group.appendChild(input);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-outline-secondary pass-eye';
    btn.setAttribute('aria-label', 'Mostrar contraseña');
    btn.setAttribute('aria-pressed', 'false');
    btn.title = 'Mostrar u ocultar la contraseña';
    btn.innerHTML = '<i class="fa-solid fa-eye" aria-hidden="true"></i>';
    group.appendChild(btn);

    const set = (visible) => {
      input.type = visible ? 'text' : 'password';
      btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
      btn.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
      btn.firstElementChild.className = 'fa-solid ' + (visible ? 'fa-eye-slash' : 'fa-eye');
    };
    btn.addEventListener('click', () => { set(input.type === 'password'); input.focus(); });
    input.form && input.form.addEventListener('submit', () => set(false));   // no se queda visible al enviar
  });
})();
