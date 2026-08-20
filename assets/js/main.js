// assets/js/main.js

// Funciones para modales
function openModal(idModal) {
    document.getElementById(idModal).classList.add('active');
}

function closeModal(idModal) {
    document.getElementById(idModal).classList.remove('active');
}

// Toggle dropdown del usuario
function toggleDropdown() {
    const menuDropdown = document.getElementById('dropdownMenu');
    if (menuDropdown) {
        menuDropdown.classList.toggle('show');
    }
}

// Toggle sidebar en móvil
function toggleSidebar() {
    const barraLateral = document.getElementById('sidebar');
    const capaSuperpuesta = document.getElementById('sidebarOverlay');
    if (barraLateral) {
        barraLateral.classList.toggle('open');
        if (capaSuperpuesta) capaSuperpuesta.classList.toggle('show');
    }
}

// Cerrar modales al hacer clic fuera
document.addEventListener('click', function(evento) {
    // Cerrar dropdowns
    if (!evento.target.closest('.user-menu') && !evento.target.closest('.dropdown-menu')) {
        const menuDropdown = document.getElementById('dropdownMenu');
        if (menuDropdown) menuDropdown.classList.remove('show');
    }
    // Cerrar modales
    if (evento.target.classList.contains('modal')) {
        evento.target.classList.remove('active');
    }
});

// Mostrar alerta (para mensajes temporales)
function mostrarAlerta(mensajeAlerta, tipoAlerta) {
    const contenedorAlertas = document.getElementById('alertContainer');
    if (!contenedorAlertas) return;
    
    const elementoAlerta = document.createElement('div');
    elementoAlerta.className = 'alert alert-' + (tipoAlerta || 'success');
    const iconoAlerta = tipoAlerta === 'success' ? 'fa-check-circle' : 
                 tipoAlerta === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
    elementoAlerta.innerHTML = '<i class="fas ' + iconoAlerta + '"></i><span>' + mensajeAlerta + '</span>';
    contenedorAlertas.appendChild(elementoAlerta);
    
    setTimeout(function() {
        elementoAlerta.remove();
    }, 3000);
}

// Confirmar eliminación
function confirmarEliminar(urlEliminar) {
    if (confirm('¿Estás seguro de eliminar este registro? Esta acción no se puede deshacer.')) {
        window.location.href = urlEliminar;
    }
}

// Formatear moneda
function formatMoney(cantidadDinero) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    }).format(cantidadDinero || 0);
}
function toggleDarkMode() {
    document.body.classList.toggle('theme-dark');
    localStorage.setItem('theme', document.body.classList.contains('theme-dark') ? 'dark' : 'light');
}

// Cargar tema guardado
if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('theme-dark');
}

// ========== EFECTO DE PARTÍCULAS FLOTANTES ==========
function crearParticulas() {
    const contenedorParticulas = document.createElement('div');
    contenedorParticulas.className = 'particles';
    document.body.appendChild(contenedorParticulas);
    
    const cantidadParticulas = 30;
    
    for (let i = 0; i < cantidadParticulas; i++) {
        const particula = document.createElement('div');
        particula.className = 'particle';
        
        const tamanioParticula = Math.random() * 6 + 2;
        const posicionIzquierda = Math.random() * 100;
        const duracionAnimacion = Math.random() * 20 + 10;
        const demoraAnimacion = Math.random() * 10;
        
        particula.style.cssText = `
            width: ${tamanioParticula}px;
            height: ${tamanioParticula}px;
            left: ${posicionIzquierda}%;
            animation-duration: ${duracionAnimacion}s;
            animation-delay: -${demoraAnimacion}s;
            opacity: ${Math.random() * 0.3 + 0.1};
        `;
        
        contenedorParticulas.appendChild(particula);
    }
}

// ========== EFECTO DE ONDA EN BOTONES ==========
function agregarEfectoOnda() {
    const botones = document.querySelectorAll('.btn-primary, .quick-action-btn');
    
    botones.forEach(boton => {
        boton.addEventListener('click', function(evento) {
            const rectangulo = this.getBoundingClientRect();
            const posX = evento.clientX - rectangulo.left;
            const posY = evento.clientY - rectangulo.top;
            
            const onda = document.createElement('span');
            onda.className = 'wave';
            onda.style.left = posX + 'px';
            onda.style.top = posY + 'px';
            
            this.appendChild(onda);
            
            setTimeout(() => {
                onda.remove();
            }, 600);
        });
    });
}

// ========== EFECTO DE REVELADO AL HACER SCROLL ==========
function initScrollReveal() {
    const elementosRevelar = document.querySelectorAll('.glass-card, .kpi-card, .user-card');
    
    const observador = new IntersectionObserver((entradas) => {
        entradas.forEach(entrada => {
            if (entrada.isIntersecting) {
                entrada.target.style.opacity = '0';
                entrada.target.style.animation = 'fadeInUp 0.6s ease forwards';
                observador.unobserve(entrada.target);
            }
        });
    }, { threshold: 0.1 });
    
    elementosRevelar.forEach(elemento => {
        elemento.style.opacity = '0';
        observador.observe(elemento);
    });
}

// ========== EFECTO DE CARGA ESCALONADA ==========
function initStaggerAnimation() {
    const contenedores = document.querySelectorAll('.kpi-grid, .users-grid, .charts-grid');
    
    contenedores.forEach(contenedor => {
        const hijos = contenedor.children;
        for (let i = 0; i < hijos.length; i++) {
            hijos[i].style.animationDelay = (i * 0.1) + 's';
            hijos[i].classList.add('animate-scale');
        }
    });
}

// ========== EFECTO DE TEXTO TIPEO EN BIENVENIDA ==========
function initTypingEffect() {
    const tituloBienvenida = document.querySelector('.welcome-content h1');
    if (tituloBienvenida && !tituloBienvenida.classList.contains('typing-done')) {
        const textoOriginal = tituloBienvenida.innerText;
        tituloBienvenida.style.width = '0';
        tituloBienvenida.style.overflow = 'hidden';
        tituloBienvenida.style.whiteSpace = 'nowrap';
        tituloBienvenida.style.borderRight = '2px solid white';
        
        let indiceCaracter = 0;
        function escribirTexto() {
            if (indiceCaracter < textoOriginal.length) {
                tituloBienvenida.style.width = (indiceCaracter + 1) + 'ch';
                indiceCaracter++;
                setTimeout(escribirTexto, 100);
            } else {
                tituloBienvenida.style.borderRight = 'none';
                tituloBienvenida.classList.add('typing-done');
            }
        }
        
        setTimeout(escribirTexto, 500);
    }
}

// ========== INICIALIZAR ANIMACIONES ==========
document.addEventListener('DOMContentLoaded', function() {
    crearParticulas();
    agregarEfectoOnda();
    initStaggerAnimation();
    
    // Inicializar scroll reveal después de un tiempo
    setTimeout(initScrollReveal, 500);
    
    // Efecto de tipeo en dashboard
    if (document.querySelector('.welcome-content h1')) {
        initTypingEffect();
    }
});

// ========== EFECTO DE PARALLAX AL MOVER MOUSE ==========
function initParallax() {
    const tarjetas = document.querySelectorAll('.kpi-card, .glass-card');
    
    document.addEventListener('mousemove', (evento) => {
        const mouseX = evento.clientX / window.innerWidth;
        const mouseY = evento.clientY / window.innerHeight;
        
        tarjetas.forEach(tarjeta => {
            const rectangulo = tarjeta.getBoundingClientRect();
            const centroX = rectangulo.left + rectangulo.width / 2;
            const centroY = rectangulo.top + rectangulo.height / 2;
            
            const deltaX = (evento.clientX - centroX) / 50;
            const deltaY = (evento.clientY - centroY) / 50;
            
            if (tarjeta.matches(':hover')) {
                tarjeta.style.transform = `perspective(1000px) rotateY(${deltaX}deg) rotateX(${-deltaY}deg) translateY(-5px)`;
            } else {
                tarjeta.style.transform = 'perspective(1000px) rotateY(0deg) rotateX(0deg) translateY(0)';
            }
        });
    });
}

// Inicializar parallax solo en desktop
if (window.innerWidth > 768) {
    initParallax();
}

// Generar QR
function verQR(idEquipo, codigoEquipo) {
    const urlEquipo = window.location.origin + '/mic/equipo.php?id=' + idEquipo;
    const contenedorQR = document.getElementById('qrCodeContainer');
    contenedorQR.innerHTML = '';
    
    const imagenQR = document.createElement('img');
    imagenQR.src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(urlEquipo)}`;
    imagenQR.style.width = '200px';
    imagenQR.style.height = '200px';
    contenedorQR.appendChild(imagenQR);
    
    document.getElementById('qrEquipoNombre').textContent = document.querySelector(`tr[data-id="${idEquipo}"] .equipo-nombre`).textContent;
    document.getElementById('qrEquipoCodigo').textContent = codigoEquipo;
    openModal('qrModal');
}

function imprimirQR() {
    const imagenQR = document.querySelector('#qrCodeContainer img');
    const ventanaImpresion = window.open('');
    ventanaImpresion.document.write('<img src="' + imagenQR.src + '">');
    ventanaImpresion.print();
}

function descargarQR() {
    const imagenQR = document.querySelector('#qrCodeContainer img');
    const enlaceDescarga = document.createElement('a');
    enlaceDescarga.download = 'qr-equipo.png';
    enlaceDescarga.href = imagenQR.src;
    enlaceDescarga.click();
}
