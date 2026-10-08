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

  fetch(API + 'list.php', { credentials: 'same-origin' })
    .then((res) => {
      if (res.status === 401) { location.href = '/src/login/?next=' + encodeURIComponent(location.pathname); throw new Error('sin sesión'); }
      return res.json();
    })
    .then((data) => createTree(data, list))
    .catch((err) => console.error('Error cargando la lista de notas:', err));
})();
