<?php // Formulario único de localización (añadir y editar). ?>
<div class="modal fade" id="modalLoc" tabindex="-1" aria-labelledby="modalLocTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" method="POST" enctype="multipart/form-data" id="formLoc">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="save_loc">
      <input type="hidden" name="id" id="l_id">
      <input type="hidden" name="return" id="l_return">
      <div class="modal-header">
        <h5 class="modal-title fs-6" id="modalLocTitle">Nueva localización</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2"><label class="small text-muted" for="l_nombre">Nombre</label>
          <input type="text" name="nombre" id="l_nombre" class="form-control form-control-sm" required></div>
        <div class="mb-2"><label class="small text-muted" for="l_cat">Categoría</label>
          <input type="text" name="categoria" id="l_cat" class="form-control form-control-sm" list="listaCategoriasLoc" placeholder="Caja, Estantería, Trastero…" autocomplete="off"></div>
        <div class="mb-2">
          <label class="small text-muted" for="l_foto">Foto</label>
          <div class="d-flex gap-2 align-items-center">
            <input type="text" name="foto_http" id="l_foto" class="form-control form-control-sm" list="listaImagenes" placeholder="Nombre de imagen o URL" autocomplete="off">
            <input type="file" name="foto_file_cam" id="l_file_cam" class="d-none" accept="image/*" capture="environment">
            <input type="file" name="foto_file_folder" id="l_file_folder" class="d-none" accept="image/*">
            <div class="btn-group btn-group-sm">
              <button type="button" class="btn btn-secondary" data-pick="l_file_cam" title="Hacer foto"><i class="fa-solid fa-camera"></i></button>
              <button type="button" class="btn btn-secondary" data-pick="l_file_folder" title="Elegir archivo"><i class="fa-solid fa-folder-open"></i></button>
            </div>
            <img id="l_preview" class="preview-loc" alt="">
          </div>
          <div class="input-group input-group-sm mt-2">
            <input type="text" name="foto_custom_name" id="l_custom" class="form-control" placeholder="Nombre para la foto nueva (opcional)">
            <button class="btn btn-outline-secondary" type="button" data-autoname="l_custom" data-entity="localizacion" title="Generar nombre automático"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
          </div>
        </div>
        <div class="mb-1"><label class="small text-muted" for="l_desc">Contenido</label>
          <textarea name="descripcion_del_contenido" id="l_desc" class="form-control form-control-sm" rows="3" placeholder="Qué hay dentro"></textarea></div>
        <p class="small text-muted mb-0" id="l_uses"></p>
      </div>
      <div class="modal-footer justify-content-between p-2">
        <button type="button" class="btn btn-sm btn-outline-danger" id="l_delete" data-confirm="delete_loc"><i class="fa-solid fa-trash"></i> Eliminar</button>
        <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-check"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>
