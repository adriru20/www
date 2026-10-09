<?php // Panel pequeño con los objetos de una localización (se rellena desde inventario.js). ?>
<div class="modal fade" id="modalLocObjs" tabindex="-1" aria-labelledby="locObjsTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable loc-objs-dialog">
    <div class="modal-content">
      <div class="modal-header py-2">
        <div class="min-w-0">
          <h5 class="modal-title fs-6 text-truncate" id="locObjsTitle">Localización</h5>
          <small class="text-muted" id="locObjsSub"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2 d-none" id="locObjsDesc"></p>
        <div class="loc-objs-grid" id="locObjsGrid"></div>
        <p class="text-center text-muted my-3 d-none" id="locObjsEmpty">No hay ningún objeto en esta localización.</p>
        <p class="text-center text-muted my-3" id="locObjsLoading"><span class="spinner-border spinner-border-sm"></span> Cargando…</p>
      </div>
      <div class="modal-footer py-2 justify-content-between">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="locObjsEdit"><i class="fa-solid fa-pen"></i> <span>Editar localización</span></button>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
