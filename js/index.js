function toggleConditionals(prefix) {
    const tipoSel = document.getElementById('tipo_' + prefix);
    if (!tipoSel) return; 
    
    const tipo = tipoSel.value;
    
    // Contenedores existentes
    const gameFields = document.getElementById('game_fields_' + prefix);
    const physFields = document.getElementById('phys_fields_' + prefix);
    const priceFields = document.getElementById('price_fields_' + prefix);
    
    // Nuevos contenedores dinámicos
    const wrapperCat = document.getElementById('wrapper_cat_' + prefix);
    const wrapperTipo = document.getElementById('wrapper_tipo_' + prefix);
    const wrapperPlat = document.getElementById('wrapper_plat_' + prefix);

    // 1. Ocultar Categoría si es Juegos o Películas
    if (wrapperCat && wrapperTipo) {
        if (tipo === 'Juegos' || tipo === 'Películas') {
            wrapperCat.classList.add('d-none');
            wrapperTipo.classList.remove('col-6');
            wrapperTipo.classList.add('col-12');
        } else {
            wrapperCat.classList.remove('d-none');
            wrapperTipo.classList.remove('col-12');
            wrapperTipo.classList.add('col-6');
        }
    }

    // 2. Mostrar Plataformas SOLO si es Juegos
    if (wrapperPlat) {
        if (tipo === 'Juegos') {
            wrapperPlat.classList.remove('d-none');
        } else {
            wrapperPlat.classList.add('d-none');
        }
    }

    // 3. Lógica para mostrar apartados de Juegos/Películas
    if (tipo === 'Juegos' || tipo === 'Películas') {
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
    if(!input) return;
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
    if(!img) return;
    
    if(val.trim() === '') {
        if (typeof fallbackSvg !== 'undefined') img.src = fallbackSvg;
    } else if (val.startsWith('http') || val.startsWith('data:')) {
        img.src = val; 
    } else {
        let cleanName = val.split('/').pop(); 
        img.src = './img/' + cleanName;
    }
}

// Función auxiliar para generar nombres limpios
function autoGenerateName(form, entityType) {
    let part1 = ''; 
    let part2 = '';
    
    if (entityType === 'objeto') {
        part1 = form.querySelector('select[name="tipo"]')?.value || 'Objeto';
        part2 = form.querySelector('input[name="titulo"]')?.value || 'SinTitulo';
    } else if (entityType === 'localizacion') {
        part1 = form.querySelector('input[name="categoria"]')?.value || 'Cat';
        part2 = form.querySelector('input[name="nombre"]')?.value || 'Loc';
    } else {
        part1 = 'img';
        part2 = Date.now().toString().slice(-6); // Nombre aleatorio para imágenes sueltas
    }
    
    return `${part1}_${part2}`
        .normalize("NFD").replace(/[\u0300-\u036f]/g, "") // Quitar acentos
        .replace(/\s+/g, '_') // Espacios por guiones bajos
        .replace(/[^a-zA-Z0-9_]/g, '') // Quitar caracteres raros
        .toLowerCase(); // Todo a minúsculas
}

// Función para el nuevo botón mágico
function generateImageNameBtn(customNameId, entityType, btnElement) {
    let form = btnElement.closest('form');
    let input = document.getElementById(customNameId);
    if (form && input) {
        input.value = autoGenerateName(form, entityType);
    }
}

// Preview al adjuntar archivo
function previewFile(input, imgId, txtId, customNameId, entityType) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();
        reader.onload = function(e) { 
            let img = document.getElementById(imgId);
            if(img) img.src = e.target.result; 
        }
        reader.readAsDataURL(input.files[0]);
        
        let txt = document.getElementById(txtId);
        if(txt) txt.value = input.files[0].name;

        // Auto-nombramiento inteligente al subir (si está vacío)
        let form = input.closest('form');
        if (form && customNameId && entityType) {
            let customNameInput = document.getElementById(customNameId);
            if (customNameInput && customNameInput.value.trim() === '') {
                customNameInput.value = autoGenerateName(form, entityType);
            }
        }
    }
}

document.addEventListener("DOMContentLoaded", function() { 
    toggleConditionals("new"); 
    
    const images = document.querySelectorAll('.placeholder-fallback');
    images.forEach(img => {
        if(!img.getAttribute('src') || img.getAttribute('src').trim() === '') {
            if (typeof fallbackSvg !== 'undefined') img.src = fallbackSvg;
        }
    });
});

// REGISTRO DEL SERVICE WORKER PARA LA PWA
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/apps/inventario/sw.js')
      .then(registration => {
        console.log('ServiceWorker registrado con éxito con alcance: ', registration.scope);
      })
      .catch(err => {
        console.log('Fallo al registrar el ServiceWorker: ', err);
      });
  });
}