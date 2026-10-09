<?php // Pestaña Resumen. Variable: $stats ?>
<?php
$bar = function (array $data, int $max = 8, string $link = '') {
  if (!$data) { echo '<p class="text-muted small mb-0">Sin datos todavía.</p>'; return; }
  $top = max($data);
  foreach (array_slice($data, 0, $max, true) as $label => $n) {
    $pct = $top ? round($n / $top * 100) : 0;
    $href = $link ? $link . urlencode($label) : '';
    echo '<div class="stat-row">'
       . '<div class="d-flex justify-content-between small"><span class="text-truncate">' . ($href ? '<a href="' . h($href) . '">' . h($label) . '</a>' : h($label)) . '</span><strong>' . (int) $n . '</strong></div>'
       . '<div class="stat-bar"><span style="width:' . $pct . '%"></span></div></div>';
  }
};
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-num"><?= $stats['objetos'] ?></div><div class="stat-label">objetos</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-num"><?= $stats['unidades'] ?></div><div class="stat-label">unidades en total</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-num"><?= $stats['localizaciones'] ?></div><div class="stat-label">localizaciones</div></div></div>
  <div class="col-6 col-lg-3">
    <a class="stat-card d-block" href="?tab=objetos&ver=venta">
      <div class="stat-num text-success"><?= number_format($stats['venta_total'], 2, ',', '.') ?> €</div>
      <div class="stat-label"><?= $stats['venta_n'] ?> a la venta</div>
    </a>
  </div>
</div>

<?php if ($stats['sin_foto'] || $stats['sin_loc']): ?>
  <div class="alert alert-secondary d-flex flex-wrap gap-3 align-items-center">
    <strong>Por revisar:</strong>
    <?php if ($stats['sin_foto']): ?><a href="?tab=objetos&ver=sin_foto">🖼️ <?= $stats['sin_foto'] ?> sin foto</a><?php endif; ?>
    <?php if ($stats['sin_loc']): ?><a href="?tab=objetos&ver=sin_loc">📍 <?= $stats['sin_loc'] ?> sin localización</a><?php endif; ?>
  </div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-12 col-md-6 col-xl-4"><div class="card p-3 h-100"><h2 class="h6 mb-3">Por tipo</h2><?php $bar($stats['por_tipo'], 8, '?tab=objetos&f_tipo='); ?></div></div>
  <div class="col-12 col-md-6 col-xl-4"><div class="card p-3 h-100"><h2 class="h6 mb-3">Categorías más grandes</h2><?php $bar($stats['por_cat'], 8, '?tab=objetos&f_t_obj='); ?></div></div>
  <div class="col-12 col-xl-4"><div class="card p-3 h-100"><h2 class="h6 mb-3">Localizaciones con más objetos</h2><?php $bar($stats['por_loc'], 8, '?tab=objetos&f_loc%5B%5D='); ?></div></div>
</div>
