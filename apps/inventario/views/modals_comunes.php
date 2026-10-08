<?php // Modales compartidos: confirmar borrado, subir imágenes, renombrar y ampliar imagen. ?>
<div class="modal fade" id="modalConfirm" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <form class="modal-content" method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="action" id="c_action">
      <input type="hidden" name="id" id="c_id">
      <input type="hidden" name="name" id="c_name">
      <input type="hidden" name="return" id="c_return">
      <div class="modal-body text-center pt-4">
        <p class="mb-1" id="c_text">¿Eliminar?</p>
        <p class="small text-warning mb-0" id="c_warn"></p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalUploadImg" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="upload_img">
      <div class="modal-header"><h5 class="modal-title fs-6">Subir imágenes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
      <div class="modal-body">
        <label class="small text-muted mb-1" for="u_files">Elige una o varias fotos (máx. 15 MB cada una)</label>
        <input type="file" name="img_files[]" id="u_files" class="form-control" accept="image/*" multiple required>
        <div id="u_previews" class="d-flex flex-wrap gap-2 mt-3"></div>
        <div class="mt-3"><label class="small text-muted" for="u_custom">Nombre personalizado <span>(solo si subes una)</span></label>
          <div class="input-group input-group-sm">
            <input type="text" name="img_custom_name" id="u_custom" class="form-control" placeholder="Ej: caja_vacia">
            <button class="btn btn-outline-secondary" type="button" data-autoname="u_custom" data-entity="imagen" title="Generar nombre automático"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
          </div></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary w-100">Subir</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalRenameImg" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <form class="modal-content" method="POST">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="rename_img">
      <input type="hidden" name="old_name" id="r_old">
      <input type="hidden" name="return" id="r_return">
      <div class="modal-header"><h5 class="modal-title fs-6">Renombrar imagen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
      <div class="modal-body">
        <p class="small text-muted mb-2" id="r_old_label"></p>
        <label class="small text-muted" for="r_new">Nuevo nombre (sin extensión)</label>
        <input type="text" name="new_name" id="r_new" class="form-control form-control-sm" required pattern="[A-Za-z0-9_\-]+" title="Solo letras, números, guiones y guiones bajos">
        <p class="small text-muted mt-2 mb-0">Se actualizarán los objetos y localizaciones que la usan.</p>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm">Renombrar</button></div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalLightbox" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-transparent border-0 text-center">
      <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" style="z-index:5" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      <img id="lb_img" src="" alt="" class="img-fluid rounded shadow mx-auto" style="max-height:82vh">
      <div class="text-white mt-2"><span class="bg-dark bg-opacity-75 px-2 py-1 rounded small" id="lb_caption"></span></div>
    </div>
  </div>
</div>
