<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos · Adriru</title>
    <link rel="shortcut icon" href="/img/favicon.ico" type="image/x-icon">
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
            background-color: #22252a;
        }
        h1 {
            text-align: center;
            color: #e6e8ea;
            margin-bottom: 30px;
        }
        .menu-botones {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .btn-doc {
            display: block;
            padding: 18px 20px;
            background: linear-gradient(135deg, #ff3385, #4a90e2);
            color: white;
            text-decoration: none;
            text-align: center;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: background-color 0.3s ease;
        }
        .btn-doc:hover {
            filter: brightness(1.1);
        }
        .mensaje-vacio {
            text-align: center;
            color: #95a5a6;
            font-style: italic;
        }
    </style>
</head>
<body>

    <h1>Documentos Disponibles</h1>

    <div class="menu-botones">
        <?php
        // Directorio actual
        $directorio = './';

        // Buscamos todos los archivos con extensión .html
        $archivos_html = glob($directorio . '*.html');

        // Si existen archivos HTML, generamos un botón para cada uno
        if (count($archivos_html) > 0) {
            foreach ($archivos_html as $archivo) {
                // Extraemos el nombre del archivo
                $nombre_archivo = basename($archivo);

                // Le quitamos la extensión .html para que el botón quede más limpio
                $nombre_limpio = pathinfo($nombre_archivo, PATHINFO_FILENAME);

                // Imprimimos el enlace (abriendo en la misma pestaña)
                echo "<a href='" . htmlspecialchars($archivo) . "' class='btn-doc'>" . htmlspecialchars($nombre_limpio) . "</a>";
            }
        } else {
            // Si no hay archivos, mostramos un aviso
            echo "<p class='mensaje-vacio'>No se han encontrado archivos en esta carpeta.</p>";
        }
        ?>
    </div>

</body>
</html>