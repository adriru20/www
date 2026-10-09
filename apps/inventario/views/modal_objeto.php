<?php // Formulario único de objeto (sirve para añadir y editar; lo rellena inventario.js). Variables: $opts ?>
<div class="modal fade" id="modalObj" tabindex="-1" aria-labelledby="modalObjTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" method="POST" enctype="multipart/form-data" id="formObj">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="save_obj">
      <input type="hidden" name="id" id="o_id">
      <input type="hidden" name="return" id="o_return">
      <div class="modal-header">
        <h5 class="modal-title fs-6" id="modalObjTitle">Nuevo objeto</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-9 mb-2"><label class="small text-muted" for="o_titulo">Título</label>
            <input type="text" name="titulo" id="o_titulo" class="form-control form-control-sm" required></div>
          <div class="col-3 mb-2"><label class="small text-muted" for="o_cantidad">Cantidad</label>
            <input type="number" name="cantidad" id="o_cantidad" class="form-control form-control-sm" value="1" min="1"></div>
        </div>

        <div class="mb-2">
          <label class="small text-muted" for="o_portada">Imagen</label>
          <div class="d-flex gap-2 align-items-center">
            <input type="text" name="portada_http" id="o_portada" class="form-control form-control-sm" list="listaImagenes" placeholder="Nombre de imagen o URL" autocomplete="off">
            <input type="file" name="portada_file_cam" id="o_file_cam" class="d-none" accept="image/*" capture="environment">
            <input type="file" name="portada_file_folder" id="o_file_folder" class="d-none" accept="image/*">
            <div class="btn-group btn-group-sm">
              <button type="button" class="btn btn-secondary" data-pick="o_file_cam" title="Hacer foto"><i class="fa-solid fa-camera"></i></button>
              <button type="button" class="btn btn-secondary" data-pick="o_file_folder" title="Elegir archivo"><i class="fa-solid fa-folder-open"></i></button>
            </div>
            <img id="o_preview" class="preview-img" alt="">
          </div>
          <div class="input-group input-group-sm mt-2">
            <input type="text" name="portada_custom_name" id="o_custom" class="form-control" placeholder="Nombre para la foto nueva (opcional)">
            <button class="btn btn-outline-secondary" type="button" data-autoname="o_custom" data-entity="objeto" title="Generar nombre automático"><i class="fa-solid fa-wand-magic-sparkles"></i></button>
          </div>
        </div>

        <div class="row">
          <div class="col-6 mb-2" id="o_wrap_tipo"><label class="small text-muted" for="o_tipo">Tipo</label>
            <select name="tipo" id="o_tipo" class="form-select form-select-sm">
              <?php foreach (INV_TIPOS as $t): ?><option value="<?= h($t) ?>"><?= h($t) ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-6 mb-2" id="o_wrap_cat"><label class="small text-muted" for="o_cat">Categoría</label>
            <input type="text" name="tipo_de_objeto" id="o_cat" class="form-control form-control-sm" list="listaCategorias" autocomplete="off"></div>
        </div>

        <div id="o_game" class="d-none game-box mb-2">
          <div class="mb-2"><label class="small fw-bold" for="o_gen">Géneros</label>
            <input type="text" name="generos" id="o_gen" class="form-control form-control-sm">
            <div class="tag-container mt-1">
              <?php foreach ($opts['gens'] as $g): ?><span class="badge bg-secondary tag-btn" data-tag-target="o_gen" data-tag="<?= h($g) ?>">+ <?= h($g) ?></span><?php endforeach; ?>
            </div></div>
          <div class="mb-2"><label class="small fw-bold" for="o_formato">Formato</label>
            <select name="formato" id="o_formato" class="form-select form-select-sm">
              <option value="Físico">💿 Físico</option><option value="Digital">☁️ Digital</option>
            </select></div>
          <div id="o_phys" class="d-none border-top pt-2">
            <div class="mb-2"><label class="small fw-bold" for="o_fa">Formato de archivo</label>
              <input type="text" name="formato_de_archivo" id="o_fa" class="form-control form-control-sm" list="listaFormatosArchivo" autocomplete="off"></div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" name="en_la_caja" id="o_caja" value="1">
              <label class="form-check-label small" for="o_caja">📦 En la caja original (completo)</label>
            </div>
          </div>
        </div>

        <div class="mb-2">
          <label class="small fw-bold text-success" for="o_precio">Precio de venta (€) <span class="text-muted fw-normal">— déjalo en 0 si no está a la venta</span></label>
          <input type="number" name="precio_de_venta" id="o_precio" class="form-control form-control-sm" step="0.01" min="0" value="0">
        </div>

        <div class="mb-2"><label class="small text-muted fw-bold" for="o_loc">Localizaciones</label>
          <input type="text" name="localizacion" id="o_loc" class="form-control form-control-sm" placeholder="Separadas por comas">
          <div class="tag-container mt-1">
            <?php foreach ($opts['locs'] as $l): ?><span class="badge bg-secondary tag-btn" data-tag-target="o_loc" data-tag="<?= h($l) ?>">+ <?= h($l) ?></span><?php endforeach; ?>
          </div></div>

        <div class="mb-2" id="o_wrap_plat"><label class="small text-muted fw-bold" for="o_plat">Plataformas</label>
          <input type="text" name="plataformas" id="o_plat" class="form-control form-control-sm">
          <div class="tag-container mt-1">
            <?php foreach ($opts['plats'] as $p): ?><span class="badge bg-secondary tag-btn" data-tag-target="o_plat" data-tag="<?= h($p) ?>">+ <?= h($p) ?></span><?php endforeach; ?>
          </div></div>

        <div class="mb-1"><label class="small text-muted" for="o_desc">Descripción</label>
          <textarea name="descripcion" id="o_desc" class="form-control form-control-sm" rows="2"></textarea></div>
      </div>
      <div class="modal-footer justify-content-between p-2">
        <button type="button" class="btn btn-sm btn-outline-danger" id="o_delete" data-confirm="delete_obj"><i class="fa-solid fa-trash"></i> Eliminar</button>
        <button type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-check"></i> Guardar</button>
      </div>
    </form>
  </div>
</div>
