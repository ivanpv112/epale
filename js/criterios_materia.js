// js/criterios_materia.js

function toggleMobileMenu() {
    document.getElementById('navWrapper').classList.toggle('active');
    document.getElementById('menuOverlay').classList.toggle('active');
}

const modal = document.getElementById('criterioModal');
const overlayMenu = document.getElementById('menuOverlay');

function openModal() { 
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Agregar Criterio';
    document.getElementById('criterioId').value = '';
    document.getElementById('formCriterio').reset(); 
    document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-plus"></i> Agregar Criterio';
    modal.style.display = 'flex'; 
}

function editCriterio(crit) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Criterio';
    document.getElementById('criterioId').value = crit.criterio_id;
    
    document.getElementById('critCategoria').value = crit.categoria;
    document.getElementById('critCodigo').value = crit.codigo_examen; 
    document.getElementById('critPuntos').value = crit.puntos_maximos;
    document.getElementById('critNombre').value = crit.nombre_examen;
    document.getElementById('critIcono').value = crit.icono;
    document.getElementById('critColor').value = crit.color;

    document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
    modal.style.display = 'flex';
}

function closeModal() { modal.style.display = 'none'; }

window.onclick = function(e) { 
    if(e.target == modal) closeModal(); 
    if(e.target == overlayMenu) toggleMobileMenu();
};

// =====================================================================
// SWEETALERT2 PARA ALERTAS NATIVAS
// =====================================================================
function confirmarPlantilla(nivel) {
    let texto = nivel == 4 
        ? "Se cargarán 9 criterios de evaluación (incluyendo Certificación) sumando 100 puntos totales." 
        : "Se cargarán 8 criterios de evaluación sumando 100 puntos totales (La Plataforma valdrá 50 pts al no haber Certificación).";
    
    Swal.fire({
        title: '¿Generar plantilla base?',
        text: texto,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#6f42c1',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, generar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formAutoCriterios').submit();
        }
    });
}

function confirmarBorrado(e, url) {
    e.preventDefault();
    Swal.fire({
        title: '¿Borrar este criterio?',
        text: "Esto podría afectar las calificaciones de los alumnos si ya fueron evaluados en este rubro.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, borrar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}

// =====================================================================
// LÓGICA DEL MENÚ DESPLEGABLE INTELIGENTE (AUTOCOMPLETE)
// =====================================================================
const dataCategoria = ['Exámenes', 'Exámenes Orales', 'Proyectos', 'Plataforma', 'Participación', 'Certificación', 'Examen Final'];
const dataNombre = ['Examen 1', 'Examen 2', 'Examen 3', 'Examen Oral 1', 'Examen Oral 2', 'Proyecto Escrito', 'Actividades en Plataforma', 'Participación en Clase', 'Examen de Certificación', 'Examen Final'];

function setupAutocomplete(inputId, dropId, list) {
    const input = document.getElementById(inputId);
    const drop = document.getElementById(dropId);

    function renderOptions(filter) {
        drop.innerHTML = '';
        const lowerFilter = filter.toLowerCase();
        const filtered = list.filter(item => item.toLowerCase().includes(lowerFilter));
        
        if(filtered.length === 0) { drop.style.display = 'none'; return; }

        filtered.forEach(item => {
            const div = document.createElement('div');
            div.className = 'smart-option';
            div.textContent = item;
            div.onmousedown = function(e) { e.preventDefault(); }
            div.onclick = function() {
                input.value = item;
                drop.style.display = 'none';
            };
            drop.appendChild(div);
        });
        drop.style.display = 'block';
    }

    input.addEventListener('focus', () => renderOptions(input.value));
    input.addEventListener('input', (e) => renderOptions(e.target.value));
    input.addEventListener('blur', () => { drop.style.display = 'none'; });
}

setupAutocomplete('critCategoria', 'dropCategoria', dataCategoria);
setupAutocomplete('critNombre', 'dropNombre', dataNombre);