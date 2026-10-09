<?php
require_once __DIR__ . '/../../backend/config/bootstrap.php';
require_app('parking');
$page_title   = 'Parking';
$page_styles  = ['/js/vendor/leaflet/leaflet.css', '/styles/parking.css'];
$page_scripts = ['/js/vendor/leaflet/leaflet.js', '/apps/parking/parking.js'];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<?php include __DIR__ . '/../../backend/config/ini.php'; ?>
<body>
  <?php include __DIR__ . '/../../frontend/menu.php'; ?>

  <main class="page-container" id="parking-root" data-csrf="<?= e(csrf_token()) ?>">
    <div class="page-header">
      <h1 class="h3">🚗 ¿Dónde he aparcado?</h1>
      <span class="sync-chip" id="syncChip" title="Dónde se guardan tus ubicaciones">Cargando…</span>
    </div>

    <div id="alerts" aria-live="assertive"></div>

    <div class="parking-layout">
      <section class="area-now" aria-labelledby="nowTitle">
        <h2 class="h5 mb-3" id="nowTitle">Aparcado ahora</h2>
        <div id="nowList"><p class="text-muted">Cargando…</p></div>
      </section>

      <section class="area-map" aria-label="Mapa">
        <div id="map" class="park-map" role="application" aria-label="Mapa con tus sitios de aparcamiento"></div>
        <div class="d-flex flex-wrap gap-2 mt-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLocate"><i class="fa-solid fa-location-crosshairs"></i> Mi posición</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" id="btnFit"><i class="fa-solid fa-expand"></i> Ver todo</button>
        </div>
      </section>

      <section class="area-form" aria-labelledby="formTitle">
        <div class="card"><div class="card-body">
          <h2 class="h5 mb-3" id="formTitle">Guardar ubicación</h2>
          <form id="saveForm" autocomplete="off">
            <div class="row g-2">
              <div class="col-5">
                <label class="form-label small text-muted mb-1" for="fLabel">Vehículo / sitio</label>
                <input type="text" id="fLabel" class="form-control" list="labelList" value="Coche" maxlength="40" required>
                <datalist id="labelList"><option value="Coche"><option value="Moto"><option value="Bici"><option value="Furgoneta"></datalist>
              </div>
              <div class="col-7">
                <label class="form-label small text-muted mb-1" for="fMeter">Parquímetro</label>
                <select id="fMeter" class="form-select">
                  <option value="">Sin límite de tiempo</option>
                  <option value="30">30 minutos</option><option value="60">1 hora</option><option value="90">1 h 30 min</option>
                  <option value="120">2 horas</option><option value="180">3 horas</option><option value="at">Hasta una hora…</option>
                </select>
              </div>
              <div class="col-12 d-none" id="fMeterAtWrap">
                <label class="form-label small text-muted mb-1" for="fMeterAt">Hora de fin</label>
                <input type="time" id="fMeterAt" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label small text-muted mb-1" for="fNote">Nota</label>
                <input type="text" id="fNote" class="form-control" maxlength="255" placeholder="Planta -2, columna B7…">
              </div>
              <div class="col-12">
                <label class="form-label small text-muted mb-1" for="fPhoto">Foto (opcional)</label>
                <div class="d-flex align-items-center gap-2">
                  <input type="file" id="fPhoto" class="d-none" accept="image/*" capture="environment">
                  <button type="button" class="btn btn-secondary" id="btnPhoto"><i class="fa-solid fa-camera"></i> Hacer foto</button>
                  <img id="photoPreview" class="photo-thumb d-none" alt="Vista previa">
                  <button type="button" class="btn btn-sm btn-outline-danger d-none" id="btnPhotoClear" title="Quitar foto"><i class="fa-solid fa-xmark"></i></button>
                </div>
              </div>
              <div class="col-12 d-grid mt-2">
                <button type="submit" class="btn btn-accent btn-lg" id="btnSave"><i class="fa-solid fa-location-dot"></i> Guardar aquí</button>
              </div>
            </div>
          </form>
          <p class="small text-muted mt-3 mb-0" id="saveMsg" aria-live="polite"></p>
        </div></div>
      </section>

      <section class="area-hist" aria-labelledby="histTitle">
        <h2 class="h5 mb-3" id="histTitle">Historial <span class="count-pill" id="histCount">0</span></h2>
        <div id="histList"><p class="text-muted small">Aquí aparecerán los sitios donde ya has recogido el coche.</p></div>
      </section>
    </div>
  </main>

  <!-- Editar nota -->
  <div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content" id="editForm">
      <div class="modal-header"><h5 class="modal-title fs-6">Editar <span id="editLabel"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
      <div class="modal-body">
        <label class="form-label small text-muted mb-1" for="editNote">Nota</label>
        <input type="text" id="editNote" class="form-control" maxlength="255">
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button></div>
    </form></div>
  </div>

  <!-- Confirmar -->
  <div class="modal fade" id="modalAsk" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
      <div class="modal-body text-center pt-4"><p class="mb-0" id="askText"></p></div>
      <div class="modal-footer justify-content-center border-0 pt-0">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger btn-sm" id="askOk">Aceptar</button>
      </div>
    </div></div>
  </div>

  <!-- Foto ampliada -->
  <div class="modal fade" id="modalPhoto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content bg-transparent border-0 text-center">
      <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" style="z-index:5" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      <img id="photoBig" src="" alt="Foto del sitio" class="img-fluid rounded shadow mx-auto" style="max-height:82vh">
    </div></div>
  </div>

  <?php include __DIR__ . '/../../frontend/footer.php'; ?>
</body>
</html>
