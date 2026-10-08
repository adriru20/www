<?php // Sugerencias para los campos de texto de los formularios. Variables: $opts, $images ?>
<datalist id="listaCategorias"><?php foreach ($opts['cats'] as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist>
<datalist id="listaFormatosArchivo"><?php foreach ($opts['fa'] as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist>
<datalist id="listaCategoriasLoc"><?php foreach ($opts['loc_cats'] as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist>
<datalist id="listaImagenes"><?php foreach ($images as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist>
