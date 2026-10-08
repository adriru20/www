<?php
// Variable opcional: $page_scripts (array de rutas JS propias de la página)
$page_scripts = $page_scripts ?? [];
?>
<footer class="site-footer">
  <div class="container-xxl">© <?= date('Y') ?> Adriru</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<?php foreach ($page_scripts as $js): ?>
<script src="<?= htmlspecialchars($js) ?>"></script>
<?php endforeach; ?>
