// js/index_estudiante.js

function toggleMobileMenu() {
    const sidebar = document.getElementById('navWrapper');
    const overlay = document.getElementById('menuOverlay');
    if (!sidebar || !overlay) return;

    const isOpen = sidebar.classList.toggle('active');
    overlay.classList.toggle('active', isOpen);

    if (isOpen) {
        document.body.classList.add('menu-open');
    } else {
        document.body.classList.remove('menu-open');
    }
}

function cambiarMateriaDash(idContenedor) {
    let contenedores = document.querySelectorAll('.eval-container');
    contenedores.forEach(function(cont) {
        cont.style.display = 'none';
    });
    let targetCont = document.getElementById(idContenedor);
    if (targetCont) {
        targetCont.style.display = 'block';
    }

    // Sincronizar el selector desplegable si existe
    let selector = document.querySelector('.subject-selector');
    if (selector && selector.value !== idContenedor) {
        selector.value = idContenedor;
    }

    // Resaltar el item correspondiente en el widget de asistencias
    let asistItems = document.querySelectorAll('.asist-item');
    asistItems.forEach(function(item) {
        if (item.getAttribute('data-eval-id') === idContenedor) {
            item.classList.add('asist-item-active');
        } else {
            item.classList.remove('asist-item-active');
        }
    });

    // Resaltar también la clase de hoy si coincide con la materia seleccionada
    let hoyItems = document.querySelectorAll('.hoy-class-card');
    hoyItems.forEach(function(item) {
        if (item.getAttribute('data-eval-id') === idContenedor) {
            item.classList.add('hoy-class-active');
        } else {
            item.classList.remove('hoy-class-active');
        }
    });
}

function irADesgloseActual() {
    let selector = document.querySelector('.subject-selector');
    let insId = '';
    if (selector && selector.value) {
        insId = selector.value.replace('eval-', '');
    } else {
        let firstCont = document.querySelector('.eval-container');
        if (firstCont && firstCont.id) {
            insId = firstCont.id.replace('eval-', '');
        }
    }
    if (insId) {
        window.location.href = 'calificaciones?ins=' + encodeURIComponent(insId);
    } else {
        window.location.href = 'calificaciones';
    }
}

// Sincronizar estados visuales de las tarjetas activas al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    let selector = document.querySelector('.subject-selector');
    let evalId = selector ? selector.value : '';
    if (!evalId) {
        let firstCont = document.querySelector('.eval-container');
        if (firstCont && firstCont.id) {
            evalId = firstCont.id;
        }
    }
    if (evalId) {
        cambiarMateriaDash(evalId);
    }
});

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
