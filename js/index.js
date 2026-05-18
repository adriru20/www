/* -- HEADER -- */
const toggleBtn = document.querySelector('.toggle_btn')
const toggleBtnIcon = document.querySelector('.toggle_btn i')
const dropDownMenu = document.querySelector('.dropdown_menu')

toggleBtn.onclick = function () {
    dropDownMenu.classList.toggle('open')
    const isOpen = dropDownMenu.classList.contains('open')
    toggleBtnIcon.classList = isOpen
    ? 'fa-solid fa-xmark'
    : 'fa-solid fa-bars'
}

console.log("JavaScript funciona");

/* -- FUNCIONES PRINCIPALES DE APARCAMIENTO -- */
function guardar() {
  if (!navigator.geolocation) {
    alert("Tu navegador no soporta geolocalización");
    return;
  }

  document.getElementById("info").innerHTML =
    '<div class="text-muted">Obteniendo ubicación...</div>';

  navigator.geolocation.getCurrentPosition(pos => {
    const lat = pos.coords.latitude;
    const lon = pos.coords.longitude;
    localStorage.setItem("coche", JSON.stringify({ lat, lon }));

    document.getElementById("info").innerHTML = `
      <div class="alert alert-info mt-3">
        Ubicación guardada correctamente.<br>
        <strong>Lat:</strong> ${lat.toFixed(6)}<br>
        <strong>Lon:</strong> ${lon.toFixed(6)}
      </div>
    `;
  }, () => {
    alert("No se pudo obtener la ubicación");
  });
}

function mostrar() {
  const data = localStorage.getItem("coche");
  if (!data) {
    document.getElementById("info").innerHTML = `
      <div class="alert alert-warning mt-3">
        No hay ninguna ubicación guardada.
      </div>`;
    return;
  }

  const { lat, lon } = JSON.parse(data);
  const url = `https://www.google.com/maps?q=${lat},${lon}`;

  document.getElementById("info").innerHTML = `
    <div class="alert alert-success mt-3">
      Tu coche está aquí:<br>
      <strong>Lat:</strong> ${lat.toFixed(6)}<br>
      <strong>Lon:</strong> ${lon.toFixed(6)}<br><br>
      <a class="btn btn-sm btn-dark" href="${url}" target="_blank">
        Abrir en Google Maps
      </a>
    </div>
  `;
}

function borrar() {
  localStorage.removeItem("coche");
  document.getElementById("info").innerHTML = `
    <div class="alert alert-secondary mt-3">
      Ubicación borrada.
    </div>`;
}


// -- WIKI --
// Función para construir el menú de forma jerárquica
function createTree(items, container) {
    const ul = document.createElement('ul');

    // Si no es el contenedor principal, ocultamos el ul para hacer el efecto acordeón
    if (container.id !== 'file-list') {
        ul.style.display = 'none';
    }

    items.forEach(item => {
        const li = document.createElement('li');

        if (item.type === 'folder') {
            li.classList.add('folder');

            // Creamos la cabecera de la carpeta
            const folderHeader = document.createElement('div');
            folderHeader.classList.add('folder-header');
            folderHeader.innerHTML = `📁 ${item.name}`;

            // Lógica para abrir/cerrar carpeta al hacer clic
            folderHeader.onclick = () => {
                const childUl = li.querySelector('ul');
                if (childUl.style.display === 'none') {
                    childUl.style.display = 'block';
                    folderHeader.innerHTML = `📂 ${item.name}`; // Cambia icono al abrir
                } else {
                    childUl.style.display = 'none';
                    folderHeader.innerHTML = `📁 ${item.name}`; // Cambia icono al cerrar
                }
            };
            li.appendChild(folderHeader);

            // Llamada recursiva para los archivos/carpetas internos
            createTree(item.children, li);
        } else {
            // Es un archivo
            li.classList.add('file');
            li.innerHTML = `📄 ${item.name}`;
            // Al hacer clic, pasamos la ruta completa
            li.onclick = () => loadNote(item.path);
        }

        ul.appendChild(li);
    });

    container.appendChild(ul);
}

// Cargar la lista al iniciar
fetch('api/list.php')
    .then(res => res.json())
    .then(data => {
        const list = document.getElementById('file-list');
        createTree(data, list);
    });

// (Mantén debajo tu función async function loadNote(fileName) { ... } tal cual la tienes)

// Función buscare
const searchInput = document.getElementById('search-input');

searchInput.addEventListener('input', function(e) {
    // Convertimos lo que el usuario escribe a minúsculas para comparar fácilmente
    const term = e.target.value.toLowerCase();

    const folders = document.querySelectorAll('.folder');
    const files = document.querySelectorAll('.file');

    // 1. Filtramos los archivos (Notas)
    files.forEach(file => {
        const fileName = file.textContent.toLowerCase();
        if (fileName.includes(term)) {
            file.style.display = 'block'; // Mostrar
        } else {
            file.style.display = 'none';  // Ocultar
        }
    });

    // 2. Ajustamos las carpetas según los archivos que contengan
    folders.forEach(folder => {
        // Buscamos todos los archivos dentro de ESTA carpeta
        const filesInFolder = folder.querySelectorAll('.file');
        let hasVisibleFiles = false;

        // Comprobamos si al menos uno de sus archivos está visible
        filesInFolder.forEach(file => {
            if (file.style.display === 'block') {
                hasVisibleFiles = true;
            }
        });

        const childUl = folder.querySelector('ul');
        const folderHeader = folder.querySelector('.folder-header');

        if (hasVisibleFiles) {
            folder.style.display = 'block'; // Mostramos la carpeta entera

            // Si el usuario está buscando algo, abrimos las carpetas automáticamente
            if (term !== '') {
                childUl.style.display = 'block';
                folderHeader.innerHTML = folderHeader.innerHTML.replace('📁', '📂');
            }
        } else {
            // Si ningún archivo coincide, ocultamos la carpeta
            folder.style.display = 'none';
        }

        // 3. Reset: Si el buscador se queda vacío, colapsamos de nuevo todo
        if (term === '' && folder.parentElement.id !== 'file-list') {
            childUl.style.display = 'none';
            folderHeader.innerHTML = folderHeader.innerHTML.replace('📂', '📁');
        }
    });
});

// Función para cargar y procesar una nota
async function loadNote(fileName) {
    try {
        // 1. Llamamos a PHP para que nos dé el texto del archivo
        const response = await fetch(`api/read.php?file=${encodeURIComponent(fileName)}`);

        if (!response.ok) {
            throw new Error("No se pudo cargar la nota");
        }

        let markdown = await response.text();

        // 2. Lógica para Wikilinks: [[Nombre de Nota]] -> Enlace clicable
        const wikilinkRegex = /\[\[(.*?)\]\]/g;
        markdown = markdown.replace(wikilinkRegex, (match, p1) => {
            return `<span class="internal-link" onclick="loadNote('${p1}.md')">${p1}</span>`;
        });

        // 3. Convertir Markdown a HTML usando la librería Marked y mostrarlo
        document.getElementById('viewer').innerHTML = marked.parse(markdown);

    } catch (error) {
        console.error("Error cargando la nota:", error);
        document.getElementById('viewer').innerHTML = "<p>Error: No se pudo cargar el archivo. Comprueba la consola.</p>";
    }
}

// Función para cargar y procesar una nota
async function loadNote(fileName) {
    try {
        // 1. Llamamos a PHP
        const response = await fetch(`api/read.php?file=${encodeURIComponent(fileName)}`);

        if (!response.ok) {
            throw new Error("No se pudo cargar la nota");
        }

        let markdown = await response.text();

        // 2. Lógica para Wikilinks: [[Nombre de Nota]] -> Enlace clicable
        const wikilinkRegex = /\[\[(.*?)\]\]/g;
        markdown = markdown.replace(wikilinkRegex, (match, p1) => {
            return `<span class="internal-link" onclick="loadNote('${p1}.md')">${p1}</span>`;
        });

        // 3. Extraer el nombre puro del archivo para el título
        // Divide por "/" y se queda la última parte, luego quita el ".md"
        const cleanName = fileName.split('/').pop().replace('.md', '');

        // Creamos el HTML del título
        const titleHtml = `<h1 class="note-title">${cleanName}</h1>`;

        // 4. Convertir Markdown a HTML y juntarlo con el título
        const contentHtml = marked.parse(markdown);
        document.getElementById('viewer').innerHTML = titleHtml + contentHtml;

    } catch (error) {
        console.error("Error cargando la nota:", error);
        document.getElementById('viewer').innerHTML = "<p>Error: No se pudo cargar el archivo. Comprueba la consola.</p>";
    }
}

// ===== INVENTARIO =====
// FUNCIÓN PARA GESTIONAR LOS CAMPOS CONDICIONALES DE JUEGOS
function toggleConditionals(prefix) {
    const tipoSel = document.getElementById('tipo_' + prefix);
    if (!tipoSel) return;

    const tipo = tipoSel.value;
    const gameFields = document.getElementById('game_fields_' + prefix);
    const physFields = document.getElementById('phys_fields_' + prefix);
    const priceFields = document.getElementById('price_fields_' + prefix);

    if (tipo === 'Juegos') {
        if (gameFields) gameFields.classList.remove('d-none');
        const formatoSel = document.getElementById('formato_' + prefix);
        const formato = formatoSel ? formatoSel.value : 'Físico';

        if (formato === 'Físico') {
            if (physFields) physFields.classList.remove('d-none');
            const enLaCaja = document.getElementById('en_la_caja_' + prefix);

            if (enLaCaja && enLaCaja.checked) {
                if (priceFields) priceFields.classList.remove('d-none');
            } else {
                if (priceFields) priceFields.classList.add('d-none');
            }
        } else {
            if (physFields) physFields.classList.add('d-none');
            if (priceFields) priceFields.classList.add('d-none');
        }
    } else {
        if (gameFields) gameFields.classList.add('d-none');
    }
}

function toggleTag(inputId, value) {
    let input = document.getElementById(inputId);
    let parts = input.value.split(',');
    let currentTags = [];
    for(let i = 0; i < parts.length; i++) {
        let t = parts[i].trim();
        if(t !== '') currentTags.push(t);
    }
    let index = currentTags.indexOf(value);
    if (index > -1) currentTags.splice(index, 1);
    else currentTags.push(value);
    input.value = currentTags.join(', ');
}

function updatePreview(imgId, val) {
    const img = document.getElementById(imgId);
    if(val.trim() === '') {
        img.src = 'https://via.placeholder.com/540x720?text=Foto';
    } else if (val.startsWith('http') || val.startsWith('data:')) {
        img.src = val;
    } else {
        let cleanName = val.split('/').pop();
        img.src = './img/' + cleanName;
    }
}

function previewFile(input, imgId, txtId) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();
        reader.onload = function(e) { document.getElementById(imgId).src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
        document.getElementById(txtId).value = input.files[0].name;
    }
}

// Asegurar que al cargar la página en Añadir Objeto esté correcta la visibilidad inicial
document.addEventListener("DOMContentLoaded", function() { toggleConditionals("new"); });