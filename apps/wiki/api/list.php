<?php
// api/list.php
header('Content-Type: application/json');

function buildTree($baseDir, $currentRelDir = '') {
    $result = [];
    $absolutePath = $baseDir . ($currentRelDir ? '/' . $currentRelDir : '');
    $items = scandir($absolutePath);

    foreach ($items as $item) {
        // Ignorar el sistema de archivos actual/padre y las carpetas internas de Obsidian
        if (in_array($item, ['.', '..', '.obsidian', '.trash', '0. Rápidas', '7. Test', '8. Plantillas', '9. Documentos'])) continue;

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