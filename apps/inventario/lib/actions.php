<?php
// Acciones de escritura (siempre POST + token CSRF). Todas terminan en redirección con aviso.

function inv_portada_value(string $posted): string {
  $posted = trim($posted);
  return stripos($posted, 'http') === 0 ? $posted : basename($posted);
}

function inv_handle_actions(): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
  csrf_verify_post();

  $return = (string) ($_POST['return'] ?? '');
  $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;

  // Subpermisos del inventario: qué permiso exige cada acción
  $action = (string) ($_POST['action'] ?? '');
  $needs = match ($action) {
    'save_obj', 'save_loc' => $id ? 'inventario.edit' : 'inventario.add',
    'upload_img'           => 'inventario.add',
    'rename_img'           => 'inventario.edit',
    'delete_obj', 'delete_loc', 'delete_img', 'delete_unused_img' => 'inventario.delete',
    default                => null,
  };
  if ($needs !== null && !has_perm($needs)) {
    inv_flash('No tienes permiso para hacer eso en el inventario.', 'danger');
    inv_redirect($return);
  }

  switch ($action) {

    case 'save_obj':
      $err = null;
      $up = inv_upload_dual($_FILES['portada_file_cam'] ?? [], $_FILES['portada_file_folder'] ?? [], trim((string) ($_POST['portada_custom_name'] ?? '')), $err);
      if ($err) inv_flash($err, 'warning');
      $portada = $up ?? inv_portada_value((string) ($_POST['portada_http'] ?? ''));
      [$ok, $res, $newLocs] = array_pad(inv_obj_save($_POST, $portada, $id), 3, 0);
      if (!$ok) { inv_flash($res, 'danger'); break; }
      $titulo = trim((string) $_POST['titulo']);
      inv_flash('Objeto «' . $titulo . '» ' . ($id ? 'actualizado' : 'añadido') . '.');
      if ($newLocs > 0) inv_flash($newLocs . ' localización(es) nueva(s) creada(s) con los nombres que escribiste.', 'info');
      if (inv_title_exists($titulo, (int) $res)) inv_flash('Ya tenías otro objeto llamado «' . $titulo . '». Revisa que no esté duplicado.', 'warning');
      break;

    case 'delete_obj':
      $name = $id ? inv_obj_delete($id) : null;
      $name === null ? inv_flash('El objeto ya no existe.', 'warning') : inv_flash('Objeto «' . $name . '» eliminado.', 'info');
      break;

    case 'save_loc':
      $err = null;
      $up = inv_upload_dual($_FILES['foto_file_cam'] ?? [], $_FILES['foto_file_folder'] ?? [], trim((string) ($_POST['foto_custom_name'] ?? '')), $err);
      if ($err) inv_flash($err, 'warning');
      $foto = $up ?? inv_portada_value((string) ($_POST['foto_http'] ?? ''));
      $res = inv_loc_save($_POST, $foto, $id);
      if (!$res[0]) { inv_flash($res[1], 'danger'); break; }
      inv_flash('Localización «' . trim((string) $_POST['nombre']) . '» ' . ($id ? 'actualizada' : 'añadida') . '.');
      if (($res[2] ?? 0) > 0) inv_flash($res[2] . ' objeto(s) se han actualizado con el nuevo nombre.', 'info');
      $return = $return ?: '?tab=localizaciones';
      break;

    case 'delete_loc':
      $d = $id ? inv_loc_delete($id) : null;
      if ($d === null) { inv_flash('La localización ya no existe.', 'warning'); break; }
      inv_flash('Localización «' . $d['nombre'] . '» eliminada.' . ($d['objetos'] ? ' Ojo: ' . $d['objetos'] . ' objeto(s) siguen apuntando a ese nombre.' : ''), $d['objetos'] ? 'warning' : 'info');
      break;

    case 'upload_img':
      $files = inv_files_list($_FILES['img_files'] ?? []);
      $custom = count($files) === 1 ? trim((string) ($_POST['img_custom_name'] ?? '')) : '';
      $done = 0;
      foreach ($files as $f) {
        $err = null;
        if (inv_upload_image($f, $custom, $err)) $done++; elseif ($err) inv_flash($err, 'warning');
      }
      if ($done) inv_flash($done . ' imagen(es) subida(s).');
      elseif (!$files) inv_flash('No has seleccionado ninguna imagen.', 'warning');
      $return = '?tab=imagenes';
      break;

    case 'rename_img':
      $new = inv_rename_image((string) ($_POST['old_name'] ?? ''), (string) ($_POST['new_name'] ?? ''));
      $new ? inv_flash('Imagen renombrada a «' . $new . '» (y actualizadas sus referencias).') : inv_flash('No se pudo renombrar: nombre vacío o ya existe.', 'danger');
      break;

    case 'delete_img':
      $name = basename((string) ($_POST['name'] ?? ''));
      $uses = inv_image_usage()[$name] ?? 0;
      if (inv_delete_image($name)) inv_flash('Imagen «' . $name . '» eliminada.' . ($uses ? " Estaba usada en $uses elemento(s)." : ''), $uses ? 'warning' : 'info');
      else inv_flash('No se pudo eliminar la imagen.', 'danger');
      break;

    case 'delete_unused_img':
      $unused = inv_unused_images();
      if ($unused === null) { inv_flash('No se pudo comprobar qué imágenes se usan, así que no se ha borrado nada.', 'danger'); break; }
      $freed = inv_images_size($unused);
      $n = 0;
      foreach ($unused as $img) if (inv_delete_image($img)) $n++;
      if (!$unused) inv_flash('No había imágenes sin usar.', 'info');
      else inv_flash($n . ' imagen(es) sin usar eliminada(s). Espacio liberado: ' . inv_format_bytes($freed) . '.' . ($n < count($unused) ? ' (' . (count($unused) - $n) . ' no se pudieron borrar.)' : ''), $n < count($unused) ? 'warning' : 'success');
      break;

    default:
      inv_flash('Acción no reconocida.', 'danger');
  }
  inv_redirect($return);
}
