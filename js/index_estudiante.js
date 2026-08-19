// js/index_estudiante.js

function toggleMobileMenu() {
    document.getElementById('navWrapper').classList.toggle('active');
    document.getElementById('menuOverlay').classList.toggle('active');
}

function cambiarMateriaDash(idContenedor) {
    let contenedores = document.querySelectorAll('.eval-container');
    contenedores.forEach(function(cont) {
        cont.style.display = 'none';
    });
    document.getElementById(idContenedor).style.display = 'block';
}

// --- MODAL DE TODAS LAS ACTIVIDADES (Historial) ---
function abrirModalTodasActividades() {
    let modal = document.getElementById('modalTodasActividades');
    modal.style.display = 'flex';
    modal.style.opacity = 0;
    setTimeout(() => { 
        modal.style.transition = 'opacity 0.2s'; 
        modal.style.opacity = 1; 
    }, 10);
}

function cerrarModalTodasActividades() {
    let modal = document.getElementById('modalTodasActividades');
    modal.style.opacity = 0;
    setTimeout(() => { 
        modal.style.display = 'none'; 
    }, 200);
}

// --- MODAL DE DETALLE INDIVIDUAL DE TAREA/AVISO ---
function abrirModalDetalle(elementoHtml) {
    // Leemos el atributo data-info que inyectamos en PHP
    let rawData = elementoHtml.getAttribute('data-info');
    let data = JSON.parse(rawData);
    
    // Pintamos los datos
    document.getElementById('modMateria').innerHTML = data.materia;
    document.getElementById('modBadge').innerHTML = data.estatus_html;
    document.getElementById('modTitulo').innerHTML = data.titulo;
    document.getElementById('modDesc').innerHTML = data.descripcion;
    document.getElementById('modProf').innerText = data.profesor;
    document.getElementById('modInicio').innerText = data.fecha_inicio;
    document.getElementById('modFin').innerText = data.fecha_fin;
    
    let modal = document.getElementById('modalDetalleActividad');
    modal.style.display = 'flex';
    modal.style.opacity = 0;
    setTimeout(() => { 
        modal.style.transition = 'opacity 0.2s'; 
        modal.style.opacity = 1; 
    }, 10);
}

function cerrarModalDetalle() {
    let modal = document.getElementById('modalDetalleActividad');
    modal.style.opacity = 0;
    setTimeout(() => { 
        modal.style.display = 'none'; 
    }, 200);
}

// --- CERRADO INTELIGENTE AL CLICAR AFUERA ---
window.onclick = function(event) {
    let modDetalle = document.getElementById('modalDetalleActividad');
    let modTodas = document.getElementById('modalTodasActividades');
    
    // Si cliquea el fondo gris del modal detalle, lo cerramos
    if (event.target === modDetalle) {
        cerrarModalDetalle();
    } 
    // Si cliquea el fondo gris del modal "Todas", lo cerramos (OJO: No cerrará el detalle si está abierto encima)
    else if (event.target === modTodas) {
        cerrarModalTodasActividades();
    }
}