<?php
// Qué carpetas y notas NO salen en la lista de la wiki. Siguen existiendo en el vault: las imágenes y archivos de esas
// carpetas se siguen encontrando por nombre al incrustarlos (![[foto.png]]).
// Se compara sin distinguir mayúsculas y sin el selector de emoji (U+FE0F), así «🖥️.md» y «🖥.md» valen igual.
// Las carpetas y ficheros que empiezan por punto (.obsidian, .trash…) nunca se muestran.
return [
    'hide' => ['6. Test', '7. Dashboards', '8. Plantillas', '9. Documentos', '🖥.md'],
];
