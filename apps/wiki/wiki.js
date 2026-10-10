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

  // ---- Enlaces y archivos incrustados de Obsidian: [[Nota]], [[Nota|texto]], ![[foto.png]], ![[doc.pdf]], ![[Nota]] (se incrusta la nota)
  const IMG_RE = /\.(png|jpe?g|gif|webp|bmp|avif|svg)$/i;
  const AUDIO_RE = /\.(mp3|m4a|wav|ogg|oga|flac)$/i;
  const VIDEO_RE = /\.(mp4|m4v|webm|mov|ogv)$/i;
  const PDF_RE = /\.pdf$/i;
  const fileUrl = (name) => API + 'file.php?name=' + encodeURIComponent(name);
  const baseName = (t) => t.replace(/\\/g, '/').split('/').pop();
  const hasExt = (t) => /\.[A-Za-z0-9]{1,8}$/.test(t);
  const isNoteName = (t) => !hasExt(t) || /\.md$/i.test(t);

  // Un hueco [[...]] o ![[...]] -> HTML. Los adjuntos se buscan por nombre en todo el vault (lo hace file.php).
  function wikiToHtml(isEmbed, inner) {
    const [rawTarget, alias] = inner.split('|');
    const [targetFull, frag] = rawTarget.split('#');
    const target = baseName(targetFull.trim());
    const label = (alias || rawTarget).trim();
    if (!target) return esc(inner);
    if (isNoteName(target)) {
      const note = target.replace(/\.md$/i, '');
      if (isEmbed) return '<div class="wiki-embed-note" data-embed-note="' + esc(note) + '" data-frag="' + esc((frag || '').trim()) + '">Cargando «' + esc(note) + '»…</div>';
      return '<a href="#" class="internal-link" data-note="' + esc(note) + '">' + esc((alias || note).trim()) + '</a>';
    }
    const url = fileUrl(target);
    const width = /^(\d{2,4})(x\d{1,4})?$/.test((alias || '').trim()) ? ' width="' + parseInt(alias, 10) + '"' : '';
    if (isEmbed && IMG_RE.test(target)) return '<img class="wiki-embed-img" src="' + esc(url) + '" alt="' + esc(target) + '" data-name="' + esc(target) + '" loading="lazy"' + width + '>';
    if (isEmbed && AUDIO_RE.test(target)) return '<audio class="wiki-embed-media" controls preload="none" src="' + esc(url) + '"></audio>';
    if (isEmbed && VIDEO_RE.test(target)) return '<video class="wiki-embed-media" controls preload="none" src="' + esc(url) + '"></video>';
    if (isEmbed && PDF_RE.test(target)) return '<div class="wiki-embed-pdf"><iframe src="' + esc(url) + '" title="' + esc(target) + '" loading="lazy"></iframe>'
      + '<a href="' + esc(url) + '" target="_blank" rel="noopener noreferrer" class="wiki-attach">📄 Abrir ' + esc(target) + ' en otra pestaña</a></div>';
    return '<a href="' + esc(url) + '" target="_blank" rel="noopener noreferrer" class="wiki-attach">' + (IMG_RE.test(target) ? '🖼️ ' : '📎 ') + esc(alias && !/^\d+(x\d+)?$/.test(alias.trim()) ? alias.trim() : target) + '</a>';
  }

  // Convierte los huecos de Obsidian fuera de bloques de código (``` y `código`)
  function convertWikiSyntax(markdown) {
    return markdown.split(/(```[\s\S]*?```|~~~[\s\S]*?~~~|`[^`\n]*`)/g).map((part, i) => {
      if (i % 2 === 1) {            // bloque de código: los tabuladores iniciales se protegen (marked los cambia por espacios) y se restauran al pintar
        return /^(```|~~~)/.test(part) ? part.replace(/^( *)(\t+)/gm, (m, sp, tabs) => sp + '\uE000'.repeat(tabs.length)) : part;
      }
      return part.replace(/(!?)\[\[(!?)(.*?)\]\]/g, (m, bang, bang2, inner) => wikiToHtml(!!(bang || bang2), inner));
    }).join('');
  }

  // Las imágenes con ruta relativa de Markdown normal ![](carpeta/foto.png) también se buscan por nombre
  marked.use({ renderer: { image(href, title, text) {
    const h = typeof href === 'object' && href ? href.href : href;
    const t = typeof href === 'object' && href ? href.text : text;
    if (!h || /^(https?:|data:|\/)/i.test(h)) return '<img src="' + esc(h || '') + '" alt="' + esc(t || '') + '">';
    let name = h; try { name = decodeURIComponent(h); } catch (e) { /* se usa tal cual */ }
    return '<img class="wiki-embed-img" src="' + esc(fileUrl(baseName(name))) + '" alt="' + esc(t || name) + '" data-name="' + esc(baseName(name)) + '" loading="lazy">';
  } } });

  // DOMPurify: los iframes solo valen si apuntan a nuestro file.php (PDF incrustados)
  DOMPurify.addHook('uponSanitizeElement', (node, data) => {
    if (data.tagName === 'iframe' && !(node.getAttribute('src') || '').startsWith(API + 'file.php?')) node.parentNode && node.parentNode.removeChild(node);
  });
  const sanitize = (html) => DOMPurify.sanitize(html, { ADD_TAGS: ['iframe'], ADD_ATTR: ['loading', 'allowfullscreen'] });

  // Extrae de una nota la sección de un encabezado (hasta el siguiente de su mismo nivel o superior)
  function sectionOf(markdown, frag) {
    const lines = markdown.split('\n'); const want = frag.trim().toLowerCase();
    let start = -1, level = 0;
    for (let i = 0; i < lines.length; i++) {
      const m = /^(#{1,6})\s+(.*?)\s*#*\s*$/.exec(lines[i]);
      if (!m) continue;
      if (start < 0) { if (m[2].trim().toLowerCase() === want) { start = i; level = m[1].length; } }
      else if (m[1].length <= level) return lines.slice(start, i).join('\n');
    }
    return start < 0 ? markdown : lines.slice(start).join('\n');
  }

  // ---- Botón «Copiar» en los bloques de código (el bloque se envuelve para que el botón no se desplace con el scroll horizontal)
  function decorateCode(root) {
    root.querySelectorAll('pre code').forEach((c) => { if (c.textContent.includes('\uE000')) c.innerHTML = c.innerHTML.replace(/\uE000/g, '\t'); });
    root.querySelectorAll('pre').forEach((pre) => {
      if (pre.parentElement && pre.parentElement.classList.contains('code-block')) return;
      const wrap = document.createElement('div');
      wrap.className = 'code-block';
      pre.parentNode.insertBefore(wrap, pre);
      wrap.appendChild(pre);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'code-copy';
      btn.title = 'Copiar el código';
      btn.setAttribute('aria-label', 'Copiar el código');
      btn.innerHTML = '<i class="fa-regular fa-copy" aria-hidden="true"></i><span>Copiar</span>';
      wrap.appendChild(btn);
    });
  }

  async function copyText(text) {
    try {
      if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(text); return true; }
    } catch (e) { /* se prueba el método antiguo */ }
    const ta = document.createElement('textarea');
    ta.value = text; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
    document.body.appendChild(ta); ta.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    ta.remove();
    return ok;
  }

  viewer.addEventListener('click', async (e) => {
    const btn = e.target.closest('.code-copy');
    if (!btn) return;
    const pre = btn.parentElement.querySelector('pre');
    const text = (pre.querySelector('code') || pre).textContent.replace(/\n$/, '');
    const ok = await copyText(text);
    const label = btn.querySelector('span');
    btn.classList.add('done');
    btn.classList.toggle('fail', !ok);
    label.textContent = ok ? '¡Copiado!' : 'No se pudo';
    clearTimeout(btn._t);
    btn._t = setTimeout(() => { btn.classList.remove('done', 'fail'); label.textContent = 'Copiar'; }, 1600);
  });

  // ---- Ampliar imágenes al pulsarlas (visor a pantalla completa; ← → para pasar de una a otra, clic en la imagen para tamaño real)
  let lb = null, lbImgs = [], lbIdx = 0, lbOpener = null;
  const lbEl = (sel) => lb.querySelector(sel);

  function lbBuild() {
    lb = document.createElement('div');
    lb.className = 'wiki-lb';
    lb.hidden = true;
    lb.setAttribute('role', 'dialog');
    lb.setAttribute('aria-modal', 'true');
    lb.setAttribute('aria-label', 'Imagen ampliada');
    lb.innerHTML = '<div class="wiki-lb-bar"><span class="wiki-lb-count"></span><span class="wiki-lb-name"></span>'
      + '<a class="wiki-lb-open" target="_blank" rel="noopener noreferrer">Abrir original</a>'
      + '<button type="button" class="wiki-lb-close" aria-label="Cerrar">✕</button></div>'
      + '<button type="button" class="wiki-lb-nav wiki-lb-prev" aria-label="Imagen anterior">‹</button>'
      + '<div class="wiki-lb-stage"><img alt=""></div>'
      + '<button type="button" class="wiki-lb-nav wiki-lb-next" aria-label="Imagen siguiente">›</button>';
    document.body.appendChild(lb);
    lbEl('.wiki-lb-close').addEventListener('click', lbClose);
    lbEl('.wiki-lb-prev').addEventListener('click', () => lbShow(lbIdx - 1));
    lbEl('.wiki-lb-next').addEventListener('click', () => lbShow(lbIdx + 1));
    lbEl('.wiki-lb-stage').addEventListener('click', (e) => {
      if (e.target.tagName === 'IMG') { lbEl('.wiki-lb-stage').classList.toggle('actual'); } else { lbClose(); }   // fuera de la imagen: cerrar
    });
  }

  function lbShow(i) {
    if (!lbImgs.length) return;
    lbIdx = (i + lbImgs.length) % lbImgs.length;
    const src = lbImgs[lbIdx];
    const img = lbEl('.wiki-lb-stage img');
    lbEl('.wiki-lb-stage').classList.remove('actual');
    img.src = src.currentSrc || src.src;
    img.alt = src.alt || '';
    lbEl('.wiki-lb-name').textContent = src.dataset.name || src.alt || '';
    lbEl('.wiki-lb-open').href = src.currentSrc || src.src;
    lbEl('.wiki-lb-count').textContent = lbImgs.length > 1 ? (lbIdx + 1) + ' / ' + lbImgs.length : '';
    lb.classList.toggle('single', lbImgs.length < 2);
  }

  function lbOpen(img) {
    if (!lb) lbBuild();
    lbImgs = [...viewer.querySelectorAll('img')].filter((i) => !i.closest('a') && (i.naturalWidth > 0 || /\.svg/i.test(i.src)));
    if (!lbImgs.includes(img)) lbImgs = [img];
    lbOpener = document.activeElement;
    lb.hidden = false;
    document.body.classList.add('wiki-lb-open-body');
    lbShow(lbImgs.indexOf(img));
    lbEl('.wiki-lb-close').focus();
  }

  function lbClose() {
    if (!lb || lb.hidden) return;
    lb.hidden = true;
    lbEl('.wiki-lb-stage img').removeAttribute('src');
    document.body.classList.remove('wiki-lb-open-body');
    if (lbOpener && lbOpener.focus) lbOpener.focus();
  }

  document.addEventListener('keydown', (e) => {
    if (lb && !lb.hidden) {
      if (e.key === 'Escape') { e.preventDefault(); lbClose(); }
      else if (e.key === 'ArrowLeft') { e.preventDefault(); lbShow(lbIdx - 1); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); lbShow(lbIdx + 1); }
      else if (e.key === 'Tab') {                       // el foco se queda dentro del visor
        const f = [...lb.querySelectorAll('button:not([hidden]), a[href]')].filter((x) => x.offsetParent !== null);
        if (f.length) { const first = f[0], last = f[f.length - 1];
          if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
          else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); } }
      }
      return;
    }
    if ((e.key === 'Enter' || e.key === ' ') && e.target.matches && e.target.matches('#viewer img[tabindex]')) { e.preventDefault(); lbOpen(e.target); }
  });

  viewer.addEventListener('click', (e) => {
    const img = e.target.closest('img');
    if (img && !img.closest('a') && (img.naturalWidth > 0 || /\.svg/i.test(img.src))) lbOpen(img);
  });

  // Rellena las notas incrustadas (![[Nota]]) y marca los archivos que no existen
  async function hydrateEmbeds(root, depth) {
    decorateCode(root);
    root.querySelectorAll('img').forEach((img) => {
      if (img.closest('a')) return;
      img.tabIndex = 0; img.setAttribute('role', 'button'); img.setAttribute('aria-label', 'Ampliar imagen: ' + (img.dataset.name || img.alt || ''));
    });
    root.querySelectorAll('img.wiki-embed-img').forEach((img) => {
      img.addEventListener('error', () => {
        const span = document.createElement('span');
        span.className = 'wiki-missing';
        span.textContent = '⚠ No se encontró «' + (img.dataset.name || img.alt) + '» en el vault';
        img.replaceWith(span);
      }, { once: true });
    });
    for (const box of root.querySelectorAll('.wiki-embed-note[data-embed-note]')) {
      const name = box.dataset.embedNote;
      const path = notePaths.get(name.toLowerCase());
      box.removeAttribute('data-embed-note');
      if (!path) { box.innerHTML = '<span class="wiki-missing">⚠ No se encontró la nota «' + esc(name) + '»</span>'; continue; }
      if (depth >= 2) { box.innerHTML = '<a href="#" class="internal-link" data-note="' + esc(name) + '">' + esc(name) + '</a>'; continue; }
      try {
        const res = await fetch(API + 'read.php?file=' + encodeURIComponent(path), { credentials: 'same-origin' });
        if (!res.ok) throw new Error('no se pudo leer');
        let md = await res.text();
        if (box.dataset.frag) md = sectionOf(md, box.dataset.frag);
        box.innerHTML = sanitize('<div class="wiki-embed-title"><a href="#" class="internal-link" data-note="' + esc(name) + '">' + esc(name) + '</a></div>' + marked.parse(convertWikiSyntax(md)));
        await hydrateEmbeds(box, depth + 1);
      } catch (e) { box.innerHTML = '<span class="wiki-missing">⚠ No se pudo cargar «' + esc(name) + '»</span>'; }
    }
  }

  async function loadNote(path) {
    try {
      const res = await fetch(API + 'read.php?file=' + encodeURIComponent(path), { credentials: 'same-origin' });
      if (res.status === 401) { location.href = '/src/login/?next=' + encodeURIComponent(location.pathname); return; }
      if (!res.ok) throw new Error('No se pudo cargar la nota');

      const markdown = convertWikiSyntax(await res.text());

      const title = path.split('/').pop().replace(/\.md$/i, '');
      // DOMPurify elimina scripts y atributos peligrosos del HTML generado
      viewer.innerHTML = sanitize('<h1 class="note-title">' + esc(title) + '</h1>' + marked.parse(markdown));
      hydrateEmbeds(viewer, 0);

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
