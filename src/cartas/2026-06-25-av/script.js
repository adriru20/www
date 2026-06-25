// ==========================================
// 1. LÓGICA DEL CONTADOR EN VIVO (DESDE 25/06/2023)
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
// 2. GENERACIÓN MATEMÁTICA ADAPTATIVA DEL ÁRBOL
// ==========================================
const colors = ['#e61936', '#ff4d6d', '#ff8fa3', '#c9182b', '#ff758f', '#ff1a1a', '#f08080'];

function createSvgHeart(color, size) {
    return `<svg viewBox="0 0 32 29.6" width="${size}" height="${size}">
        <path d="M23.6,0c-3.4,0-6.3,2.7-7.6,5.6C14.7,2.7,11.8,0,8.4,0C3.8,0,0,3.8,0,8.4c0,9.4,9.5,11.9,16,21.2 c6.1-9.3,16-12.1,16-21.2C32,3.8,28.2,0,23.6,0z" fill="${color}"/>
    </svg>`;
}

function buildTree() {
    const leavesContainer = document.getElementById('tree-leaves');
    leavesContainer.innerHTML = ''; // Limpiamos contenedor por si hay reajustes
    
    const numHearts = 450; 
    let created = 0;
    
    // DETECCIÓN RESPONSIVE: Si la pantalla es pequeña, encogemos la copa matemáticamente
    const isMobile = window.innerWidth <= 768;
    const scale = isMobile ? 85 : 115; 
    const offsetY = isMobile ? -30 : -45;

    while (created < numHearts) {
        const x = (Math.random() * 3) - 1.5;
        const y = (Math.random() * 3) - 1.5;

        // Fórmula matemática del corazón: (x^2 + y^2 - 1)^3 - x^2 * y^3 <= 0
        const equation = Math.pow(x * x + y * y - 1, 3) - (x * x * Math.pow(y, 3));

        if (equation <= 0) {
            const heart = document.createElement('div');
            heart.classList.add('tree-heart');

            const pxX = x * scale;
            const pxY = -y * scale + offsetY; 

            // Corazones un poquito más pequeños en móvil para mantener la definición artística
            const size = isMobile ? (Math.random() * 9 + 5) : (Math.random() * 12 + 6);
            const color = colors[Math.floor(Math.random() * colors.length)];

            heart.innerHTML = createSvgHeart(color, size);
            
            heart.style.left = `calc(50% + ${pxX}px)`;
            heart.style.top = `calc(50% + ${pxY}px)`;
            heart.style.transform = `translate(-50%, -50%) rotate(${Math.random() * 80 - 40}deg)`;
            heart.style.animationDelay = `${Math.random() * 1.4 + 0.2}s`;

            leavesContainer.appendChild(heart);
            created++;
        }
    }
}

// Construir el árbol al cargar la página
buildTree();

// Recalcular la distribución si el usuario cambia la orientación del móvil
let resizeTimeout;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(buildTree, 300);
});


// ==========================================
// 3. EFECTO ANIMADO DE FONDO (HOJAS CAYENDO)
// ==========================================
function spawnFallingHeart() {
    const container = document.getElementById('falling-hearts-container');
    if (!container) return;
    
    const wrapper = document.createElement('div');
    wrapper.classList.add('falling-heart-wrapper');
    const heart = document.createElement('div');
    heart.classList.add('falling-heart');

    const size = Math.random() * 12 + 8; 
    const color = colors[Math.floor(Math.random() * colors.length)];
    const leftPos = Math.random() * 100; 
    
    const fallDuration = Math.random() * 6 + 6; 
    const swayDuration = Math.random() * 2 + 1.5; 

    heart.innerHTML = createSvgHeart(color, size);
    
    wrapper.style.left = `${leftPos}%`;
    wrapper.style.animationDuration = `${fallDuration}s`;
    heart.style.animationDuration = `${swayDuration}s`;

    wrapper.appendChild(heart);
    container.appendChild(wrapper);

    setTimeout(() => {
        wrapper.remove();
    }, fallDuration * 1000);
}

setInterval(spawnFallingHeart, 600);