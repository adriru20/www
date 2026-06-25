// ==========================================
// 1. LÓGICA DEL CONTADOR DE TIEMPO
// ==========================================
// Fecha de inicio: 25 de junio de 2023 a las 00:00:00
const startDate = new Date('2023-06-25T00:00:00');

function updateCounter() {
    const now = new Date();
    const diff = now - startDate;

    // Cálculos de tiempo
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    // Actualizar el DOM
    document.getElementById('days').textContent = days;
    // padStart añade un '0' a la izquierda si el número es menor a 10 (ej. '08')
    document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
    document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
    document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
}

// Actualizar cada segundo
setInterval(updateCounter, 1000);
updateCounter(); // Llamada inicial


// ==========================================
// 2. GENERACIÓN DEL ÁRBOL DE CORAZONES
// ==========================================
const colors = ['#e61936', '#ff4d6d', '#ff8fa3', '#c9182b', '#ff758f', '#ff1a1a'];

function createSvgHeart(color, size) {
    return `<svg viewBox="0 0 32 29.6" width="${size}" height="${size}">
        <path d="M23.6,0c-3.4,0-6.3,2.7-7.6,5.6C14.7,2.7,11.8,0,8.4,0C3.8,0,0,3.8,0,8.4c0,9.4,9.5,11.9,16,21.2 c6.1-9.3,16-12.1,16-21.2C32,3.8,28.2,0,23.6,0z" fill="${color}"/>
    </svg>`;
}

function buildTree() {
    const leavesContainer = document.getElementById('tree-leaves');
    const numHearts = 180; // Cantidad de corazones en el árbol

    for (let i = 0; i < numHearts; i++) {
        const heart = document.createElement('div');
        heart.classList.add('tree-heart');

        // Matemáticas para distribuir los corazones en forma de copa de árbol (círculo disperso)
        const angle = Math.random() * Math.PI * 2;
        const radius = Math.sqrt(Math.random()) * 130; 
        
        const x = Math.cos(angle) * radius;
        const y = (Math.sin(angle) * radius) - 30; // Desplazar un poco hacia arriba

        const size = Math.random() * 15 + 10; // Tamaño entre 10px y 25px
        const color = colors[Math.floor(Math.random() * colors.length)];

        heart.innerHTML = createSvgHeart(color, size);
        
        // Posicionar desde el centro del contenedor
        heart.style.left = `calc(50% + ${x}px)`;
        heart.style.top = `calc(50% + ${y}px)`;
        heart.style.transform = `translate(-50%, -50%) rotate(${Math.random() * 60 - 30}deg)`;

        leavesContainer.appendChild(heart);
    }
}
buildTree();


// ==========================================
// 3. EFECTO GIF (CORAZONES CAYENDO)
// ==========================================
function spawnFallingHeart() {
    const container = document.getElementById('falling-hearts-container');
    const heart = document.createElement('div');
    heart.classList.add('falling-heart');

    const size = Math.random() * 10 + 10; 
    const color = colors[Math.floor(Math.random() * colors.length)];
    const leftPos = Math.random() * 100; // Posición horizontal aleatoria (0% - 100%)
    const duration = Math.random() * 5 + 5; // Duración de caída entre 5s y 10s

    heart.innerHTML = createSvgHeart(color, size);
    heart.style.left = `${leftPos}%`;
    heart.style.animationDuration = `${duration}s`;

    container.appendChild(heart);

    // Eliminar el corazón del DOM una vez que termina la animación para no consumir memoria
    setTimeout(() => {
        heart.remove();
    }, duration * 1000);
}

// Crear un corazón nuevo cada 400 milisegundos
setInterval(spawnFallingHeart, 400);