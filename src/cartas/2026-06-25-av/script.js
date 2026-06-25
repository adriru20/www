// ==========================================
// 1. LÓGICA DEL CONTADOR DE TIEMPO
// ==========================================
const startDate = new Date('2023-06-25T00:00:00');

function updateCounter() {
    const now = new Date();
    const diff = now - startDate;

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    document.getElementById('days').textContent = days;
    document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
    document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
    document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
}

setInterval(updateCounter, 1000);
updateCounter(); 


// ==========================================
// 2. GENERACIÓN DEL ÁRBOL EN FORMA DE CORAZÓN
// ==========================================
const colors = ['#e61936', '#ff4d6d', '#ff8fa3', '#c9182b', '#ff758f', '#ff1a1a', '#f08080'];

function createSvgHeart(color, size) {
    return `<svg viewBox="0 0 32 29.6" width="${size}" height="${size}">
        <path d="M23.6,0c-3.4,0-6.3,2.7-7.6,5.6C14.7,2.7,11.8,0,8.4,0C3.8,0,0,3.8,0,8.4c0,9.4,9.5,11.9,16,21.2 c6.1-9.3,16-12.1,16-21.2C32,3.8,28.2,0,23.6,0z" fill="${color}"/>
    </svg>`;
}

function buildTree() {
    const leavesContainer = document.getElementById('tree-leaves');
    const numHearts = 450; // Aumentamos la cantidad de corazones
    let created = 0;
    const scale = 115; // Escala de la copa del árbol

    // Utilizamos un bucle while para generar coordenadas hasta rellenar la forma
    while (created < numHearts) {
        // Puntos aleatorios en un eje de -1.5 a 1.5
        const x = (Math.random() * 3) - 1.5;
        const y = (Math.random() * 3) - 1.5;

        // Ecuación matemática del corazón: (x^2 + y^2 - 1)^3 - x^2 * y^3 <= 0
        const equation = Math.pow(x * x + y * y - 1, 3) - (x * x * Math.pow(y, 3));

        if (equation <= 0) {
            const heart = document.createElement('div');
            heart.classList.add('tree-heart');

            // Convertimos las coordenadas a píxeles. 
            // Invertimos 'y' porque en la pantalla los píxeles crecen hacia abajo.
            const pxX = x * scale;
            const pxY = -y * scale - 25; 

            // Corazones más pequeños para mayor detalle (entre 5px y 18px)
            const size = Math.random() * 13 + 5; 
            const color = colors[Math.floor(Math.random() * colors.length)];

            heart.innerHTML = createSvgHeart(color, size);
            
            // Posicionamiento
            heart.style.left = `calc(50% + ${pxX}px)`;
            heart.style.top = `calc(50% + ${pxY}px)`;
            
            // Rotación aleatoria para que parezcan hojas naturales
            heart.style.transform = `translate(-50%, -50%) rotate(${Math.random() * 80 - 40}deg)`;

            // Retraso aleatorio en la animación inicial para que aparezcan poco a poco
            heart.style.animationDelay = `${Math.random() * 1.5 + 0.5}s`;

            leavesContainer.appendChild(heart);
            created++;
        }
    }
}
buildTree();


// ==========================================
// 3. EFECTO GIF (CORAZONES CAYENDO CON BALANCEO)
// ==========================================
function spawnFallingHeart() {
    const container = document.getElementById('falling-hearts-container');
    
    // Contenedor que maneja la caída vertical
    const wrapper = document.createElement('div');
    wrapper.classList.add('falling-heart-wrapper');
    
    // El corazón en sí, que manejará el balanceo (sway)
    const heart = document.createElement('div');
    heart.classList.add('falling-heart');

    const size = Math.random() * 12 + 8; 
    const color = colors[Math.floor(Math.random() * colors.length)];
    const leftPos = Math.random() * 100; 
    
    // Tiempos aleatorios para que el movimiento sea irregular y natural
    const fallDuration = Math.random() * 6 + 6; // Caen entre 6s y 12s
    const swayDuration = Math.random() * 2 + 1.5; // Se balancean cada 1.5s - 3.5s

    heart.innerHTML = createSvgHeart(color, size);
    
    wrapper.style.left = `${leftPos}%`;
    wrapper.style.animationDuration = `${fallDuration}s`;
    heart.style.animationDuration = `${swayDuration}s`;

    wrapper.appendChild(heart);
    container.appendChild(wrapper);

    // Limpieza de memoria una vez completada la caída
    setTimeout(() => {
        wrapper.remove();
    }, fallDuration * 1000);
}

setInterval(spawnFallingHeart, 500);