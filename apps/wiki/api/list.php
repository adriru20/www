<?php
// api/list.php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
header('Content-Type: application/json');
require_app_api('wiki');

// Nombre normalizado para comparar (sin selector de emoji, sin mayúsculas, Unicode compuesto)
function wikiNorm(string $s): string {
    if (class_exists('Normalizer')) $s = Normalizer::normalize($s, Normalizer::FORM_C) ?: $s;
    return mb_strtolower(str_replace("\u{FE0F}", '', $s));
}
$cfg = require __DIR__ . '/../config.php';
$HIDE = array_flip(array_map('wikiNorm', $cfg['hide'] ?? []));

function buildTree($baseDir, $currentRelDir = '') {
    global $HIDE;
    $result = [];
    $absolutePath = $baseDir . ($currentRelDir ? '/' . $currentRelDir : '');
    $items = scandir($absolutePath);

    foreach ($items as $item) {
        // Ignorar el sistema de archivos actual/padre, lo oculto (.obsidian, .trash…) y lo que se haya marcado como oculto en config.php
        if ($item === '.' || $item === '..' || $item[0] === '.' || isset($HIDE[wikiNorm($item)])) continue;

        $itemPath = $absolutePath . '/' . $item;
        $relPath = $currentRelDir ? $currentRelDir . '/' . $item : $item;

        if (is_dir($itemPath)) {
            // Es una carpeta: llamamos a la función dentro de sí misma (recursividad)
            $result[] = [
                'type' => 'folder',
                'name' => $item,
                'children' => buildTree($baseDir, $relPath)
            ];
        } else if (pathinfo($item, PATHINFO_EXTENSION) === 'md') {
            // Es un archivo markdown
            $result[] = [
                'type' => 'file',
                'name' => str_replace('.md', '', $item),
                'path' => $relPath
            ];
        }
    }
    return $result;
}

echo json_encode(buildTree('../vault'));
?>