<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú de Documentos</title>
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
            background-color: #f4f4f9;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .menu-botones {
            display: flex;
            flex-direction: column;
            gap: 15px; /* Espacio entre los botones */
        }
        .btn-pdf {
            display: block;
            padding: 18px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            text-align: center;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: bold;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: background-color 0.3s ease;
        }
        .btn-pdf:hover {
            background-color: #2980b9;
        }
        .mensaje-vacio {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
        }
    </style>
</head>
<body>

    <h1>Documentos Disponibles</h1>

    <div class="menu-botones">
        <?php
        // Ruta donde buscar los PDFs (por defecto, la carpeta actual)
        // Si están en una carpeta, usa por ejemplo: $directorio = './pdfs/';
        $directorio = './';

        // Usamos glob() para encontrar todos los archivos .pdf
        $archivos_pdf = glob($directorio . '*.pdf');

        // Si existen PDFs, generamos un botón para cada uno
        if (count($archivos_pdf) > 0) {
            foreach ($archivos_pdf as $archivo) {
                // Extraemos solo el nombre del archivo para mostrarlo limpio
                $nombre_archivo = basename($archivo);
                
                // Imprimimos el enlace con formato de botón
                echo "<a href='" . htmlspecialchars($archivo) . "' class='btn-pdf' target='_blank'>" . htmlspecialchars($nombre_archivo) . "</a>";
            }
        } else {
            // Si no hay PDFs, mostramos un aviso
            echo "<p class='mensaje-vacio'>No se han encontrado archivos PDF en esta carpeta.</p>";
        }
        ?>
    </div>

</body>
</html>