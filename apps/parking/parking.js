// PARKING — guarda dónde has aparcado: mapa, notas, foto, parquímetro, historial y sincronización.
// Guarda en el servidor (api.php); si no está disponible, usa el almacenamiento del navegador.
(function () {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const CSRF = $('parking-root').dataset.csrf;
  const LS_DATA = 'parking_v2', LS_OUTBOX = 'parking_outbox', LS_LEGACY = 'coche';
  const HISTORY_KEEP = 30;

  // ---------- Utilidades ----------
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const nowSec = () => Math.floor(Date.now() / 1000);
  const fmtDate = (t) => new Date(t * 1000).toLocaleString('es-ES', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });

  function fmtDur(sec) {
    sec = Math.max(0, Math.round(sec));
    const d = Math.floor(sec / 86400), h = Math.floor((sec % 86400) / 3600), m = Math.floor((sec % 3600) / 60);
    if (d) return d + (d === 1 ? ' día ' : ' días ') + h + ' h';
    if (h) return h + ' h ' + m + ' min';
    return m < 1 ? 'menos de 1 min' : m + ' min';
  }
  function fmtDist(m) { return m < 1000 ? Math.round(m / 10) * 10 + ' m' : (m / 1000).toFixed(1).replace('.', ',') + ' km'; }
  function haversine(a, b) {
    const R = 6371000, rad = Math.PI / 180;
    const dLat = (b.lat - a.lat) * rad, dLon = (b.lon - a.lon) * rad;
    const x = Math.sin(dLat / 2) ** 2 + Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(dLon / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(x));
  }
  const walkMin = (m) => Math.max(1, Math.round(m / 80)); // ~4,8 km/h

  const mapsDir = (s) => 'https://www.google.com/maps/dir/?api=1&destination=' + s.lat + ',' + s.lon + '&travelmode=walking';
  const mapsPoint = (s) => 'https://www.google.com/maps?q=' + s.lat + ',' + s.lon;

  // Reduce la foto en el navegador (más rápido de subir y sin metadatos GPS)
  function resizeImage(file, max = 1280, quality = 0.75) {
    return new Promise((resolve, reject) => {
      const img = new Image(), url = URL.createObjectURL(file);
      img.onload = () => {
        const k = Math.min(1, max / Math.max(img.width, img.height));
        const c = document.createElement('canvas');
        c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        c.toBlob((b) => (b ? resolve(b) : reject(new Error('blob'))), 'image/jpeg', quality);
      };
      img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('img')); };
      img.src = url;
    });
  }
  const blobToDataUrl = (blob) => new Promise((res) => { const r = new FileReader(); r.onload = () => res(r.result); r.readAsDataURL(blob); });
  const dataUrlToBlob = (u) => fetch(u).then((r) => r.blob());

  // ---------- Geolocalización ----------
  let lastFix = null; // última posición que nos dio el seguimiento (watchPosition)
  function getPosition() {
    return new Promise((resolve, reject) => {
      if (lastFix && Date.now() - lastFix.t < 15000) return resolve({ lat: lastFix.lat, lon: lastFix.lon, accuracy: lastFix.accuracy });
      if (!window.isSecureContext) return reject({ msg: 'La ubicación solo funciona en una conexión segura (https).' });
      if (!navigator.geolocation) return reject({ msg: 'Tu navegador no permite obtener la ubicación.' });
      navigator.geolocation.getCurrentPosition(
        (p) => resolve({ lat: p.coords.latitude, lon: p.coords.longitude, accuracy: p.coords.accuracy }),
        (err) => reject({ msg: ({
          1: 'Has bloqueado el permiso de ubicación. Actívalo en los ajustes del navegador (icono del candado junto a la dirección) y vuelve a intentarlo.',
          2: 'No se ha podido determinar tu posición. Sal a un sitio abierto e inténtalo de nuevo.',
          3: 'Tarda demasiado en encontrar tu posición. Inténtalo de nuevo en un sitio con mejor cobertura.',
        })[err.code] || 'No se pudo obtener la ubicación.' }),
        { enableHighAccuracy: true, timeout: 20000, maximumAge: 5000 }
      );
    });
  }

  // ---------- Almacenes ----------
  const post = async (action, data, file) => {
    const fd = new FormData();
    fd.append('action', action); fd.append('csrf', CSRF);
    Object.entries(data || {}).forEach(([k, v]) => { if (v !== null && v !== undefined) fd.append(k, v); });
    if (file) fd.append('photo', file, 'foto.jpg');
    const r = await fetch('api.php', { method: 'POST', body: fd, credentials: 'same-origin' });
    const j = await r.json();
    if (!j.ok) throw Object.assign(new Error(j.error || 'error'), { api: true });
    return j;
  };

  const ServerStore = {
    kind: 'server',
    async list() {
      const r = await fetch('api.php?action=list', { credentials: 'same-origin' });
      const j = await r.json();
      if (!j.ok) throw new Error(j.error);
      return { active: j.active, history: j.history };
    },
    async save(spot, blob) {
      const j = await post('save', { label: spot.label, lat: spot.lat, lon: spot.lon, accuracy: spot.accuracy, note: spot.note, meter_until: spot.meter_until }, blob);
      return j.spot;
    },
    async update(id, patch) { return (await post('update', Object.assign({ id }, patch))).spot; },
    async archive(id) { await post('archive', { id }); },
    async remove(id) { await post('delete', { id }); },
    photoUrl: (s) => (s.has_photo ? 'api.php?action=photo&id=' + s.id : ''),
  };

  const readLocal = () => { try { return JSON.parse(localStorage.getItem(LS_DATA)) || { spots: [] }; } catch (e) { return { spots: [] }; } };
  const writeLocal = (d) => {
    const hist = d.spots.filter((s) => !s.active).sort((a, b) => b.archived_at - a.archived_at).slice(0, HISTORY_KEEP);
    d.spots = d.spots.filter((s) => s.active).concat(hist);
    localStorage.setItem(LS_DATA, JSON.stringify(d));
  };
  const LocalStore = {
    kind: 'local',
    async list() {
      const d = readLocal();
      return { active: d.spots.filter((s) => s.active).sort((a, b) => b.parked_at - a.parked_at), history: d.spots.filter((s) => !s.active).sort((a, b) => b.archived_at - a.archived_at).slice(0, 20) };
    },
    async save(spot, blob) {
      const d = readLocal(), t = nowSec();
      d.spots.forEach((s) => { if (s.active && s.label === spot.label) { s.active = false; s.archived_at = t; } });
      const s = Object.assign({}, spot, { id: 'l' + Date.now(), parked_at: t, active: true, archived_at: null, has_photo: false });
      if (blob) { s.photo_data = await blobToDataUrl(blob); s.has_photo = true; }
      d.spots.push(s);
      try { writeLocal(d); } catch (e) { delete s.photo_data; s.has_photo = false; writeLocal(d); }
      return s;
    },
    async update(id, patch) {
      const d = readLocal(), s = d.spots.find((x) => String(x.id) === String(id)); if (!s) throw new Error('nf');
      if ('note' in patch) s.note = patch.note;
      if ('meter_until' in patch) s.meter_until = Number(patch.meter_until) > nowSec() ? Number(patch.meter_until) : null;
      writeLocal(d); return s;
    },
    async archive(id) { const d = readLocal(), s = d.spots.find((x) => String(x.id) === String(id)); if (s) { s.active = false; s.archived_at = nowSec(); writeLocal(d); } },
    async remove(id) { const d = readLocal(); d.spots = d.spots.filter((x) => String(x.id) !== String(id)); writeLocal(d); },
    photoUrl: (s) => s.photo_data || '',
  };

  // Cola de guardados pendientes (sin conexión, p. ej. en un garaje subterráneo)
  const outbox = {
    get() { try { return JSON.parse(localStorage.getItem(LS_OUTBOX)) || []; } catch (e) { return []; } },
    set(a) { try { localStorage.setItem(LS_OUTBOX, JSON.stringify(a)); } catch (e) {} },
    add(item) { const a = this.get(); a.push(item); this.set(a); },
  };

  // ---------- Estado ----------
  let store = LocalStore;
  let state = { active: [], history: [] };
  let userPos = null, watchId = null, map = null, layer = null, meMarker = null, timers = [];

  function setChip() {
    const chip = $('syncChip'), pending = outbox.get().length;
    if (store.kind === 'server') { chip.textContent = pending ? '⏳ ' + pending + ' pendiente(s) de sincronizar' : '☁️ Sincronizado'; chip.className = 'sync-chip ' + (pending ? 'local' : 'ok'); }
    else { chip.textContent = '📱 Solo en este dispositivo'; chip.className = 'sync-chip local'; }
  }

  // ---------- Avisos ----------
  function banner(text, cls) {
    const el = document.createElement('div');
    el.className = 'alert alert-' + (cls || 'info') + ' alert-dismissible fade show';
    el.innerHTML = esc(text) + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>';
    $('alerts').appendChild(el);
    if (cls !== 'danger' && cls !== 'warning') setTimeout(() => el.remove(), 8000);
  }
  function notify(title, body, tag) {
    if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    if ('Notification' in window && Notification.permission === 'granted') { try { new Notification(title, { body, tag, icon: '/img/icon-192.png' }); } catch (e) {} }
  }

  function ask(text) {
    return new Promise((resolve) => {
      $('askText').textContent = text;
      const m = bootstrap.Modal.getOrCreateInstance($('modalAsk')); let done = false;
      const ok = $('askOk'), onOk = () => { done = true; m.hide(); };
      ok.addEventListener('click', onOk, { once: true });
      $('modalAsk').addEventListener('hidden.bs.modal', () => { ok.removeEventListener('click', onOk); resolve(done); }, { once: true });
      m.show();
    });
  }

  // ---------- Mapa ----------
  const carIcon = (label) => L.divIcon({ className: '', iconSize: [30, 30], iconAnchor: [15, 28], html: '<div style="position:relative"><span class="pin-label">' + esc(label) + '</span><div class="pin">🚗</div></div>' });
  function initMap() {
    const el = $('map');
    if (typeof L === 'undefined') { el.innerHTML = '<div class="map-fallback">El mapa no está disponible sin conexión.<br>Tus datos y el botón «Cómo llegar» siguen funcionando.</div>'; return; }
    map = L.map(el, { zoomControl: true }).setView([40.4, -3.7], 5);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(map);
    layer = L.layerGroup().addTo(map);
  }
  function drawMap(fit) {
    if (!map) return;
    layer.clearLayers();
    const pts = [];
    state.active.forEach((s) => {
      L.marker([s.lat, s.lon], { icon: carIcon(s.label), title: s.label }).addTo(layer)
        .bindPopup('<strong>' + esc(s.label) + '</strong>' + (s.note ? '<br>' + esc(s.note) : '') + '<br><a href="' + esc(mapsDir(s)) + '" target="_blank" rel="noopener">Cómo llegar</a>');
      pts.push([s.lat, s.lon]);
    });
    if (userPos) { drawMe(); pts.push([userPos.lat, userPos.lon]); }
    if (fit && pts.length) { pts.length === 1 ? map.setView(pts[0], 17) : map.fitBounds(pts, { padding: [40, 40], maxZoom: 18 }); }
    setTimeout(() => map.invalidateSize(), 50);
  }
  function drawMe() {
    if (!map || !userPos) return;
    if (meMarker) meMarker.setLatLng([userPos.lat, userPos.lon]);
    else meMarker = L.marker([userPos.lat, userPos.lon], { icon: L.divIcon({ className: '', iconSize: [16, 16], html: '<div class="me-dot"></div>' }), interactive: false, zIndexOffset: -100 }).addTo(map);
  }

  // ---------- Pintado ----------
  function spotCard(s) {
    const photo = store.photoUrl(s);
    return '<article class="spot-card" data-id="' + esc(s.id) + '">' +
      '<div class="spot-head"><div><div class="spot-title">🚗 ' + esc(s.label) + '</div>' +
      '<div class="spot-meta">Aparcado el ' + esc(fmtDate(s.parked_at)) + '</div></div>' +
      (photo ? '<img class="spot-photo" src="' + esc(photo) + '" alt="Foto del sitio" data-act="photo" data-id="' + esc(s.id) + '">' : '') + '</div>' +
      (s.note ? '<p class="spot-note">📝 ' + esc(s.note) + '</p>' : '') +
      '<div class="spot-chips">' +
        '<span class="chip" data-elapsed="' + s.parked_at + '"></span>' +
        '<span class="chip d-none" data-meter="' + (s.meter_until || '') + '"></span>' +
        '<span class="chip d-none" data-dist data-lat="' + s.lat + '" data-lon="' + s.lon + '"></span>' +
        (s.accuracy ? '<span class="chip" title="Precisión del GPS al guardar">±' + Math.round(s.accuracy) + ' m</span>' : '') +
        (String(s.id).startsWith('tmp') ? '<span class="chip chip-warn">⏳ pendiente de sincronizar</span>' : '') +
      '</div>' +
      '<div class="spot-actions">' +
        '<a class="btn btn-sm btn-accent" href="' + esc(mapsDir(s)) + '" target="_blank" rel="noopener"><i class="fa-solid fa-route"></i> Cómo llegar</a>' +
        '<button class="btn btn-sm btn-outline-secondary" data-act="focus" data-id="' + esc(s.id) + '"><i class="fa-solid fa-map-pin"></i> En el mapa</button>' +
        (s.meter_until ? '<button class="btn btn-sm btn-outline-secondary" data-act="extend" data-id="' + esc(s.id) + '"><i class="fa-solid fa-clock"></i> +30 min</button>' : '') +
        '<button class="btn btn-sm btn-outline-secondary" data-act="share" data-id="' + esc(s.id) + '"><i class="fa-solid fa-share-nodes"></i></button>' +
        '<button class="btn btn-sm btn-outline-secondary" data-act="edit" data-id="' + esc(s.id) + '"><i class="fa-solid fa-pen"></i></button>' +
        '<button class="btn btn-sm btn-success" data-act="pickup" data-id="' + esc(s.id) + '"><i class="fa-solid fa-check"></i> Ya lo he recogido</button>' +
      '</div></article>';
  }

  function renderNow() {
    $('nowList').innerHTML = state.active.length ? state.active.map(spotCard).join('')
      : '<div class="empty-state py-4"><p class="mb-0">No tienes nada aparcado.<br>Pulsa <strong>Guardar aquí</strong> cuando dejes el coche.</p></div>';
    tick();
  }
  function renderHistory() {
    $('histCount').textContent = state.history.length;
    $('histList').innerHTML = state.history.length ? state.history.map((s) =>
      '<div class="hist-item"><div class="grow"><div class="t">' + esc(s.label) + ' <span class="text-muted fw-normal small">· ' + esc(fmtDate(s.parked_at)) + '</span></div>' +
      '<div class="s">' + (s.note ? esc(s.note) : 'Sin nota') + '</div></div>' +
      '<a class="btn btn-sm btn-outline-secondary" href="' + esc(mapsPoint(s)) + '" target="_blank" rel="noopener" title="Ver en Google Maps"><i class="fa-solid fa-map-location-dot"></i></a>' +
      '<button class="btn btn-sm btn-outline-danger" data-act="del" data-id="' + esc(s.id) + '" title="Borrar"><i class="fa-solid fa-trash"></i></button></div>').join('')
      : '<p class="text-muted small">Aquí aparecerán los sitios donde ya has recogido el coche.</p>';
  }

  // Textos que cambian con el tiempo / la posición (no repinta toda la lista)
  function tick() {
    const t = nowSec();
    document.querySelectorAll('[data-elapsed]').forEach((el) => { el.textContent = '⏱️ hace ' + fmtDur(t - Number(el.dataset.elapsed)); });
    document.querySelectorAll('.spot-card').forEach((card) => {
      const chip = card.querySelector('[data-meter]'), until = Number(chip.dataset.meter);
      card.classList.remove('warn', 'expired'); chip.classList.remove('chip-warn', 'chip-bad', 'chip-ok');
      if (!until) return chip.classList.add('d-none');
      chip.classList.remove('d-none');
      const left = until - t;
      if (left <= 0) { chip.textContent = '⚠️ Caducó hace ' + fmtDur(-left); chip.classList.add('chip-bad'); card.classList.add('expired'); }
      else if (left <= 600) { chip.textContent = '⏳ Quedan ' + fmtDur(left); chip.classList.add('chip-warn'); card.classList.add('warn'); }
      else { chip.textContent = '🅿️ Quedan ' + fmtDur(left); chip.classList.add('chip-ok'); }
    });
    document.querySelectorAll('[data-dist]').forEach((el) => {
      if (!userPos) return el.classList.add('d-none');
      const m = haversine(userPos, { lat: Number(el.dataset.lat), lon: Number(el.dataset.lon) });
      el.textContent = '📍 a ' + fmtDist(m) + (m <= 5000 ? ' (≈ ' + walkMin(m) + ' min a pie)' : ''); el.classList.remove('d-none');
    });
  }

  // Avisos del parquímetro mientras la página esté abierta
  function scheduleAlerts() {
    timers.forEach(clearTimeout); timers = [];
    const t = nowSec();
    state.active.forEach((s) => {
      if (!s.meter_until || s.meter_until <= t) return;
      const warnIn = (s.meter_until - 600 - t) * 1000, endIn = (s.meter_until - t) * 1000;
      if (warnIn > 0 && warnIn < 2 ** 31) timers.push(setTimeout(() => { banner('Al ' + s.label + ' le quedan 10 minutos de parquímetro.', 'warning'); notify('Parquímetro: 10 minutos', s.label + (s.note ? ' · ' + s.note : ''), 'w' + s.id); }, warnIn));
      if (endIn < 2 ** 31) timers.push(setTimeout(() => { banner('¡Se acabó el parquímetro del ' + s.label + '!', 'danger'); notify('¡Parquímetro agotado!', s.label, 'e' + s.id); }, endIn));
    });
  }

  function render(fit) { setChip(); renderNow(); renderHistory(); drawMap(fit); scheduleAlerts(); }

  // ---------- Carga y sincronización ----------
  const withOutbox = (data) => {
    const pend = outbox.get().map((o) => Object.assign({}, o.spot, { id: o.id, has_photo: !!o.photo, photo_data: o.photo || '', active: true }));
    return { active: pend.concat(data.active), history: data.history };
  };
  // photoUrl para pendientes (foto aún local)
  const origPhotoUrl = ServerStore.photoUrl;
  ServerStore.photoUrl = (s) => (s.photo_data ? s.photo_data : origPhotoUrl(s));

  async function flushOutbox() {
    if (store.kind !== 'server') return;
    const items = outbox.get(); if (!items.length) return;
    const left = [];
    for (const it of items) {
      try { await ServerStore.save(it.spot, it.photo ? await dataUrlToBlob(it.photo) : null); }
      catch (e) { if (e.api) continue; left.push(it); } // error del servidor: se descarta; sin red: se reintenta
    }
    outbox.set(left);
    if (left.length < items.length) banner('Se han sincronizado ' + (items.length - left.length) + ' ubicación(es) guardadas sin conexión.', 'success');
  }

  async function reload(fit) {
    try { state = withOutbox(await store.list()); }
    catch (e) { // sin red: se conserva lo que había y se añaden los guardados pendientes
      state = withOutbox({ active: state.active.filter((s) => !String(s.id).startsWith('tmp')), history: state.history });
      if (navigator.onLine !== false) banner('No se pudo actualizar desde el servidor.', 'warning');
    }
    render(fit);
  }

  // Los datos del antiguo botón (localStorage "coche") pasan al nuevo sistema
  async function migrateLegacy() {
    const raw = localStorage.getItem(LS_LEGACY); if (!raw) return;
    try {
      const o = JSON.parse(raw);
      if (typeof o.lat === 'number' && typeof o.lon === 'number') await store.save({ label: 'Coche', lat: o.lat, lon: o.lon, accuracy: null, note: 'Guardado con la versión anterior', meter_until: null }, null);
      localStorage.removeItem(LS_LEGACY);
    } catch (e) { localStorage.removeItem(LS_LEGACY); }
  }

  async function init() {
    initMap();
    try { await ServerStore.list(); store = ServerStore; } catch (e) { store = LocalStore; }
    await flushOutbox();
    await migrateLegacy();
    await reload(true);
    setInterval(tick, 30000);
    window.addEventListener('online', async () => { await flushOutbox(); await reload(false); });
    // Si ya hay permiso de ubicación, se sigue tu posición para las distancias
    if (navigator.permissions && navigator.permissions.query) {
      try { const p = await navigator.permissions.query({ name: 'geolocation' }); if (p.state === 'granted') startWatch(); } catch (e) {}
    }
  }

  function startWatch() {
    if (watchId !== null || !navigator.geolocation) return;
    watchId = navigator.geolocation.watchPosition((p) => { userPos = { lat: p.coords.latitude, lon: p.coords.longitude }; lastFix = { lat: userPos.lat, lon: userPos.lon, accuracy: p.coords.accuracy, t: Date.now() }; drawMe(); tick(); },
      () => {}, { enableHighAccuracy: true, maximumAge: 15000 });
  }

  // ---------- Formulario: guardar ----------
  let photoBlob = null;
  $('btnPhoto').addEventListener('click', () => $('fPhoto').click());
  $('fPhoto').addEventListener('change', async () => {
    const f = $('fPhoto').files[0]; if (!f) return;
    try {
      photoBlob = await resizeImage(f);
      $('photoPreview').src = URL.createObjectURL(photoBlob);
      $('photoPreview').classList.remove('d-none'); $('btnPhotoClear').classList.remove('d-none');
    } catch (e) { banner('No se pudo procesar la foto.', 'warning'); }
  });
  $('btnPhotoClear').addEventListener('click', () => { photoBlob = null; $('fPhoto').value = ''; $('photoPreview').classList.add('d-none'); $('btnPhotoClear').classList.add('d-none'); });
  $('fMeter').addEventListener('change', () => $('fMeterAtWrap').classList.toggle('d-none', $('fMeter').value !== 'at'));

  function meterUntil() {
    const v = $('fMeter').value; if (!v) return null;
    if (v !== 'at') return nowSec() + Number(v) * 60;
    const t = $('fMeterAt').value; if (!t) return null;
    const d = new Date(); const [h, m] = t.split(':').map(Number); d.setHours(h, m, 0, 0);
    if (d.getTime() <= Date.now()) d.setDate(d.getDate() + 1);
    return Math.floor(d.getTime() / 1000);
  }

  $('saveForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const btn = $('btnSave'), msg = $('saveMsg'), original = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Buscando tu posición…'; msg.textContent = '';
    try {
      const pos = await getPosition();
      const meter = meterUntil();
      if (meter && 'Notification' in window && Notification.permission === 'default') Notification.requestPermission();
      const spot = { label: $('fLabel').value.trim() || 'Coche', lat: pos.lat, lon: pos.lon, accuracy: pos.accuracy, note: $('fNote').value.trim(), meter_until: meter };
      userPos = { lat: pos.lat, lon: pos.lon };
      try {
        await store.save(spot, photoBlob);
        msg.textContent = '✅ Guardado. Precisión del GPS: ±' + Math.round(pos.accuracy) + ' m.' + (pos.accuracy > 50 ? ' La precisión es baja: si puedes, sal a un sitio abierto y vuelve a guardar.' : '');
      } catch (e) {
        if (store.kind === 'server' && !e.api) { // sin conexión: se guarda en el móvil y se sube después
          outbox.add({ id: 'tmp' + Date.now(), spot: Object.assign({ parked_at: nowSec() }, spot), photo: photoBlob ? await blobToDataUrl(photoBlob) : null });
          msg.textContent = '📴 Sin conexión: guardado en el móvil. Se sincronizará cuando vuelvas a tener internet.';
        } else throw e;
      }
      $('fNote').value = ''; $('fMeter').value = ''; $('fMeterAtWrap').classList.add('d-none'); $('btnPhotoClear').click();
      await reload(true);
    } catch (e) {
      msg.textContent = '❌ ' + (e.msg || 'No se pudo guardar. Inténtalo de nuevo.');
    } finally { btn.disabled = false; btn.innerHTML = original; }
  });

  // ---------- Acciones de las tarjetas ----------
  const findSpot = (id) => state.active.concat(state.history).find((s) => String(s.id) === String(id));
  let editingId = null;

  document.addEventListener('click', async (ev) => {
    const b = ev.target.closest('[data-act]'); if (!b) return;
    const s = findSpot(b.dataset.id); if (!s) return;
    if (String(s.id).startsWith('tmp') && ['pickup', 'extend', 'edit'].includes(b.dataset.act)) return banner('Este sitio aún se está sincronizando. Inténtalo en unos segundos.', 'info');
    try {
      switch (b.dataset.act) {
        case 'focus': if (map) { map.setView([s.lat, s.lon], 18); document.getElementById('map').scrollIntoView({ behavior: 'smooth', block: 'center' }); } break;
        case 'photo': $('photoBig').src = store.photoUrl(s); bootstrap.Modal.getOrCreateInstance($('modalPhoto')).show(); break;
        case 'share': {
          const data = { title: s.label + ' aparcado', text: s.label + ' aparcado' + (s.note ? ' (' + s.note + ')' : ''), url: mapsPoint(s) };
          if (navigator.share) await navigator.share(data).catch(() => {});
          else { await navigator.clipboard.writeText(data.url); banner('Enlace copiado al portapapeles.', 'success'); }
          break;
        }
        case 'extend': {
          const base = Math.max(Number(s.meter_until), nowSec());
          await store.update(s.id, { meter_until: base + 1800 }); banner('Añadidos 30 minutos al parquímetro.', 'success'); await reload(false); break;
        }
        case 'edit': editingId = s.id; $('editLabel').textContent = s.label; $('editNote').value = s.note || ''; bootstrap.Modal.getOrCreateInstance($('modalEdit')).show(); break;
        case 'pickup': await store.archive(s.id); banner(s.label + ' recogido. Lo tienes en el historial.', 'success'); await reload(false); break;
        case 'del': if (await ask('¿Borrar este sitio del historial?')) { await store.remove(s.id); await reload(false); } break;
      }
    } catch (e) { banner('No se pudo completar la acción. Comprueba tu conexión.', 'danger'); }
  });

  $('editForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try { await store.update(editingId, { note: $('editNote').value.trim() }); bootstrap.Modal.getInstance($('modalEdit')).hide(); await reload(false); }
    catch (e) { banner('No se pudo guardar la nota.', 'danger'); }
  });

  // ---------- Mapa: botones ----------
  $('btnLocate').addEventListener('click', async () => {
    try { const p = await getPosition(); userPos = { lat: p.lat, lon: p.lon }; startWatch(); drawMe(); tick(); if (map) map.setView([p.lat, p.lon], 17); }
    catch (e) { banner(e.msg || 'No se pudo obtener tu posición.', 'warning'); }
  });
  $('btnFit').addEventListener('click', () => drawMap(true));

  init().catch((e) => { console.error(e); banner('No se pudo iniciar el parking.', 'danger'); });
})();
