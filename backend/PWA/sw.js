const CACHE_NAME = 'inventario-cache-v1';
const urlsToCache = [
  '../../apps/inventario/index.php',
  '../../js/index.js'
  // Aquí podrías añadir tu CSS, Bootstrap o logos estáticos si los tienes
];

// Instalar el Service Worker y guardar en caché los archivos básicos
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

// Interceptar peticiones (Estrategia: Primero red, si falla, usa caché)
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request);
    })
  );
});

// Limpiar cachés antiguas al actualizar
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});