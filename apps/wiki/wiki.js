// WIKI: lista jerárquica de notas, buscador y visor Markdown.
// Necesita marked y DOMPurify (js/vendor/) cargados antes que este fichero.
(function () {
  const API = '/apps/wiki/api/';
  const list = document.getElementById('file-list');
  const viewer = document.getElementById('viewer');
  const searchInput = document.getElementById('search-input');
  const notePaths = new Map(); // nombre de nota (minúsculas) -> ruta completa

  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // Construye el árbol de carpetas y notas (acordeón)
  function createTree(items, container) {
    const ul = document.createElement('ul');
    if (container.id !== 'file-list') ul.style.display = 'none';

    items.forEach((item) => {
      const li = document.createElement('li');

      if (item.type === 'folder') {
        li.classList.add('folder');
        const header = document.createElement('div');
        header.classList.add('folder-header');
        header.textContent = '📁 ' + item.name;
        header.addEventListener('click', () => {
          const childUl = li.querySelector('ul');
          const open = childUl.style.display === 'none';
          childUl.style.display = open ? 'block' : 'none';
          header.textContent = (open ? '📂 ' : '📁 ') + item.name;
        });
        li.appendChild(header);
        createTree(item.children, li);
      } else {
        li.classList.add('file');
        li.textContent = '📄 ' + item.name;
        li.dataset.path = item.path;
        notePaths.set(item.name.toLowerCase(), item.path);
        li.addEventListener('click', () => loadNote(item.path));
      }
      ul.appendChild(li);
    });
    container.appendChild(ul);
  }

  async function loadNote(path) {
    try {
      const res = await fetch(API + 'read.php?file=' + encodeURIComponent(path), { credentials: 'same-origin' });
      if (res.status === 401) { location.href = '/src/login/?next=' + encodeURIComponent(location.pathname); return; }
      if (!res.ok) throw new Error('No se pudo cargar la nota');

      let markdown = await res.text();

      // Wikilinks de Obsidian: [[Nota]] o [[Nota|texto]]
      markdown = markdown.replace(/\[\[(.*?)\]\]/g, (m, inner) => {
        const [target, alias] = inner.split('|');
        return '<a href="#" class="internal-link" data-note="' + esc(target.trim()) + '">' + esc((alias || target).trim()) + '</a>';
      });

      const title = path.split('/').pop().replace(/\.md$/i, '');
      // DOMPurify elimina scripts y atributos peligrosos del HTML generado
      viewer.innerHTML = DOMPurify.sanitize('<h1 class="note-title">' + esc(title) + '</h1>' + marked.parse(markdown));

      document.querySelectorAll('.file.active').forEach((f) => f.classList.remove('active'));
      const current = [...document.querySelectorAll('.file')].find((f) => f.dataset.path === path);
      if (current) current.classList.add('active');
      document.getElementById('content').scrollTo({ top: 0 });
      window.scrollTo({ top: 0 });
    } catch (err) {
      console.error('Error cargando la nota:', err);
      viewer.innerHTML = '<p>Error: no se pudo cargar la nota.</p>';
    }
  }

  // Clic en un wikilink: busca la nota por nombre en toda la wiki
  viewer.addEventListener('click', (e) => {
    const link = e.target.closest('.internal-link');
    if (!link) return;
    e.preventDefault();
    const name = link.dataset.note || '';
    loadNote(notePaths.get(name.toLowerCase()) || name + '.md');
  });

  // Buscador: filtra notas y despliega las carpetas con resultados
  searchInput.addEventListener('input', (e) => {
    const term = e.target.value.toLowerCase().trim();

    document.querySelectorAll('.file').forEach((file) => {
      file.style.display = file.textContent.toLowerCase().includes(term) ? 'block' : 'none';
    });

    // De dentro hacia fuera para que las carpetas anidadas se calculen bien
    [...document.querySelectorAll('.folder')].reverse().forEach((folder) => {
      const hasVisible = [...folder.querySelectorAll('.file')].some((f) => f.style.display !== 'none');
      const childUl = folder.querySelector('ul');
      const header = folder.querySelector('.folder-header');
      const name = header.textContent.replace(/^📁 |^📂 /, '');

      folder.style.display = hasVisible ? 'block' : 'none';
      if (term !== '' && hasVisible) {
        childUl.style.display = 'block';
        header.textContent = '📂 ' + name;
      } else if (term === '') {
        childUl.style.display = 'none';
        header.textContent = '📁 ' + name;
      }
    });
  });

  // (Re)carga la lista de notas. Al recargar tras una sincronización se conserva la nota abierta.
  function loadList() {
    return fetch(API + 'list.php', { credentials: 'same-origin' })
      .then((res) => {
        if (res.status === 401) { location.href = '/src/login/?next=' + encodeURIComponent(location.pathname); throw new Error('sin sesión'); }
        return res.json();
      })
      .then((data) => { list.textContent = ''; notePaths.clear(); createTree(data, list); })
      .catch((err) => console.error('Error cargando la lista de notas:', err));
  }

  // ---- Sincronización con OneDrive (solo si el administrador la ha conectado)
  const sidebar = document.getElementById('sidebar');
  const syncBtn = document.getElementById('sync-btn');
  const syncStatus = document.getElementById('sync-status');
  let syncing = false;

  function agoText(s) {
    if (s === null || s === undefined) return 'sin sincronizar todavía';
    if (s < 90) return 'hace un momento';
    if (s < 5400) return 'hace ' + Math.round(s / 60) + ' min';
    if (s < 172800) return 'hace ' + Math.round(s / 3600) + ' h';
    return 'hace ' + Math.round(s / 86400) + ' días';
  }

  async function syncOnce(mode) {
    const fd = new FormData();
    fd.append('csrf', sidebar.dataset.csrf);
    fd.append('mode', mode);
    const res = await fetch(API + 'sync.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    if (res.status === 401) { location.href = '/src/login/?next=' + encodeURIComponent(location.pathname); throw new Error('sin sesión'); }
    return res.json();
  }

  async function runSync(mode) {
    if (!sidebar || sidebar.dataset.sync !== '1' || syncing) return;
    syncing = true;
    if (syncBtn) { syncBtn.disabled = true; syncBtn.classList.add('spin'); }
    syncStatus.textContent = 'Sincronizando con OneDrive…';
    let changed = 0, last = null;
    try {
      for (let i = 0; i < 40; i++) {                 // cada llamada trabaja unos segundos; se repite hasta terminar
        last = await syncOnce(i === 0 ? mode : 'continue');
        changed += last.changed || 0;
        if (last.state !== 'synced' || !(last.pending > 0)) break;
        syncStatus.textContent = 'Sincronizando con OneDrive… (' + (last.notes || 0) + ' notas)';
      }
      if (changed > 0) {
        const open = document.querySelector('.file.active');
        const openPath = open ? open.dataset.path : null;
        await loadList();
        if (openPath && [...document.querySelectorAll('.file')].some((f) => f.dataset.path === openPath)) loadNote(openPath);
      }
      if (last && last.state === 'error') syncStatus.textContent = '⚠ ' + last.message;
      else if (last && last.state === 'busy') syncStatus.textContent = 'Otra sincronización está en marcha…';
      else syncStatus.textContent = 'OneDrive · ' + agoText(last ? last.ago : null) + (changed > 0 ? ' · ' + changed + ' cambio(s)' : '');
    } catch (err) {
      console.error('Error sincronizando:', err);
      syncStatus.textContent = '⚠ No se pudo sincronizar con OneDrive.';
    } finally {
      syncing = false;
      if (syncBtn) { syncBtn.disabled = false; syncBtn.classList.remove('spin'); }
    }
  }

  if (syncBtn) syncBtn.addEventListener('click', () => runSync('force'));

  loadList().then(() => runSync('auto'));
})();
