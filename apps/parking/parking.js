// PARKING: guarda en el navegador (localStorage) la ubicación del coche.
(function () {
  const info = document.getElementById('info');
  const show = (cls, html) => { info.innerHTML = '<div class="alert alert-' + cls + ' mt-3">' + html + '</div>'; };

  window.guardar = function () {
    if (!navigator.geolocation) {
      show('danger', 'Tu navegador no soporta geolocalización.');
      return;
    }
    show('secondary', 'Obteniendo ubicación…');
    navigator.geolocation.getCurrentPosition((pos) => {
      const lat = pos.coords.latitude;
      const lon = pos.coords.longitude;
      localStorage.setItem('coche', JSON.stringify({ lat, lon, fecha: Date.now() }));
      show('info', 'Ubicación guardada correctamente.<br><strong>Lat:</strong> ' + lat.toFixed(6) + '<br><strong>Lon:</strong> ' + lon.toFixed(6));
    }, () => show('danger', 'No se pudo obtener la ubicación. Revisa el permiso del navegador.'));
  };

  window.mostrar = function () {
    const data = localStorage.getItem('coche');
    if (!data) { show('warning', 'No hay ninguna ubicación guardada.'); return; }
    const { lat, lon, fecha } = JSON.parse(data);
    const url = 'https://www.google.com/maps?q=' + lat + ',' + lon;
    const when = fecha ? '<br><small class="text-muted">Guardado el ' + new Date(fecha).toLocaleString('es-ES') + '</small>' : '';
    show('success', 'Tu coche está aquí:<br><strong>Lat:</strong> ' + lat.toFixed(6) + '<br><strong>Lon:</strong> ' + lon.toFixed(6) + when +
      '<br><br><a class="btn btn-sm btn-primary" href="' + url + '" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-map-location-dot"></i> Abrir en Google Maps</a>');
  };

  window.borrar = function () {
    localStorage.removeItem('coche');
    show('secondary', 'Ubicación borrada.');
  };
})();
