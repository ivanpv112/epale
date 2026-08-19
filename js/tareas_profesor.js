// js/tareas_profesor.js

let tareasActuales = [];

document.addEventListener('DOMContentLoaded', cargarTareas);

function abrirPanelTareas() { 
    document.getElementById('modalGestionTareas').style.display = 'flex'; 
    cargarTareas(); 
}

function cerrarPanelTareas() { 
    document.getElementById('modalGestionTareas').style.display = 'none'; 
}

function cerrarFormularioTarea() { 
    document.getElementById('modalFormTarea').style.display = 'none'; 
}

function cargarTareas() {
    const tbody = document.getElementById('tablaTareasBody');
    const resumen = document.getElementById('lista_resumen_tareas');

    if(tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Cargando...</td></tr>';
    if(resumen) resumen.innerHTML = '<p style="color: #888; margin: 0;">Cargando tareas...</p>';
    
    fetch('tareas_api.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=get_tareas' })
    .then(res => res.json())
    .then(data => {
        tareasActuales = data;
        let htmlResumen = '';
        let contadorPendientes = 0;
        let tareasMostradasDash = 0; // Límite para evitar saturación visual

        if(tbody) tbody.innerHTML = '';

        if(data.length === 0) {
            if(tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No tienes tareas o avisos en el ciclo actual.</td></tr>';
            if(resumen) resumen.innerHTML = '<p style="color: #888; margin: 0;">No hay tareas ni avisos activos.</p>';
            actualizarContadorEstadisticas(0);
            return;
        }

        data.forEach(t => {
            let badgeClass = '';
            if(t.estatus === 'PENDIENTE') badgeClass = 'badge-gray';
            else if(t.estatus === 'PRÓXIMA') badgeClass = 'badge-warning';
            else if(t.estatus === 'ACTIVA') badgeClass = 'badge-success';
            else if(t.estatus === 'FINALIZADA') badgeClass = 'badge-danger';

            let icono = t.tipo === 'AVISO' ? '📢' : '📝';
            
            // Sumamos al contador global solo las que no han finalizado
            if(t.estatus !== 'FINALIZADA') contadorPendientes++;

            // --- 1. CONSTRUIR FILA PARA EL MODAL DE GESTIÓN (HISTORIAL COMPLETO) ---
            if(tbody) {
                let btnPublicar = (t.estatus === 'PENDIENTE' || t.estatus === 'PRÓXIMA') 
                    ? `<button onclick="publicarTarea(${t.tarea_id})" class="action-btn publish-btn" title="Publicar Ahora"><i class="fas fa-play"></i></button>` : '';
                
                let btnFinalizar = (t.estatus === 'ACTIVA') 
                    ? `<button onclick="finalizarTarea(${t.tarea_id})" class="action-btn finish-btn" title="Finalizar Ahora"><i class="fas fa-stop"></i></button>` : '';

                tbody.innerHTML += `
                    <tr>
                        <td>${icono} ${t.tipo}</td>
                        <td style="font-weight:bold;">${t.titulo}</td>
                        <td><small>${t.materia_nombre} (NRC: ${t.nrc})</small></td>
                        <td>${new Date(t.fecha_inicio).toLocaleString()}</td>
                        <td>${new Date(t.fecha_fin).toLocaleString()}</td>
                        <td><span class="badge ${badgeClass}">${t.estatus}</span></td>
                        <td style="white-space: nowrap;">
                            ${btnPublicar}
                            ${btnFinalizar}
                            <button onclick="editarTarea(${t.tarea_id})" class="action-btn edit-btn" title="Editar"><i class="fas fa-pen"></i></button>
                            <button onclick="borrarTarea(${t.tarea_id})" class="action-btn delete-btn" title="Borrar"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `;
            }

            // --- 2. CONSTRUIR TARJETA PARA EL DASHBOARD AZUL (MÁXIMO 3) ---
            if(resumen && t.estatus !== 'FINALIZADA' && tareasMostradasDash < 3) {
                let isUrgent = (t.estatus === 'PRÓXIMA') ? 'task-urgent' : '';
                
                // Recortamos el título por si es exageradamente largo (Ej. sin espacios)
                let tituloCorto = t.titulo.length > 35 ? t.titulo.substring(0, 35) + '...' : t.titulo;

                htmlResumen += `
                    <div class="task-item ${isUrgent}" onclick="abrirPanelTareas()" title="Clic para gestionar">
                        <div style="display:flex; justify-content: space-between; align-items:flex-start;">
                            <div style="max-width: 75%; overflow: hidden;">
                                <div class="task-title" style="word-break: break-all;">${icono} ${tituloCorto}</div>
                                <div class="task-meta"><i class="fas fa-chalkboard"></i> ${t.materia_nombre} (NRC: ${t.nrc})</div>
                            </div>
                            <span class="badge ${badgeClass}">${t.estatus}</span>
                        </div>
                        <div class="task-meta" style="margin-top: 8px; color: #555;">
                            <i class="far fa-calendar-alt"></i> Cierre: <strong>${new Date(t.fecha_fin).toLocaleString()}</strong>
                        </div>
                    </div>
                `;
                tareasMostradasDash++;
            }
        });

        // 3. RENDERIZADO DEL RESUMEN Y EL LINK DE "VER MÁS"
        if(resumen) {
            resumen.innerHTML = htmlResumen || '<p style="color: #888; margin: 0;">No hay tareas pendientes en este momento.</p>';
            
            // Si el profesor tiene, por ejemplo, 5 tareas activas, mostramos que hay 2 ocultas
            let tareasOcultas = contadorPendientes - tareasMostradasDash;
            if (tareasOcultas > 0) {
                resumen.innerHTML += `
                    <div style="text-align:center; margin-top:15px;">
                        <button onclick="abrirPanelTareas()" style="background:none; border:none; color:rgba(255,255,255,0.8); text-decoration:underline; cursor:pointer; font-size: 0.9rem;">
                            Ver ${tareasOcultas} actividades más...
                        </button>
                    </div>
                `;
            }
        }
        
        actualizarContadorEstadisticas(contadorPendientes);
    });
}

function actualizarContadorEstadisticas(numero) {
    const statBoxes = document.querySelectorAll('.stat-box');
    statBoxes.forEach(box => {
        if(box.innerHTML.includes('Tareas Pendientes')) {
            const numDiv = box.querySelector('.number');
            if(numDiv) numDiv.innerText = numero;
        }
    });
}

function abrirFormularioTarea() {
    document.getElementById('formTarea').reset();
    document.getElementById('tarea_id').value = '';
    document.getElementById('tituloModalTarea').innerText = 'Nueva Publicación';
    cargarGruposSelect();
    document.getElementById('modalFormTarea').style.display = 'flex';
}

function cargarGruposSelect(nrc_seleccionado = '') {
    fetch('tareas_api.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=get_grupos_activos' })
    .then(res => res.json())
    .then(data => {
        let select = document.getElementById('tarea_nrc');
        select.innerHTML = '<option value="">-- Selecciona una Clase --</option>';
        data.forEach(g => {
            let sel = (g.nrc == nrc_seleccionado) ? 'selected' : '';
            select.innerHTML += `<option value="${g.nrc}" ${sel}>${g.nombre} (NRC: ${g.nrc})</option>`;
        });
    });
}

function editarTarea(id) {
    let t = tareasActuales.find(x => x.tarea_id == id);
    if(!t) return;
    document.getElementById('tarea_id').value = t.tarea_id;
    document.querySelector(`input[name="tipo"][value="${t.tipo}"]`).checked = true;
    document.getElementById('tarea_titulo').value = t.titulo;
    document.getElementById('tarea_descripcion').value = t.descripcion;
    document.getElementById('tarea_inicio').value = t.fecha_inicio_input;
    document.getElementById('tarea_fin').value = t.fecha_fin_input;
    document.getElementById('tituloModalTarea').innerText = 'Editar Publicación';
    cargarGruposSelect(t.nrc);
    document.getElementById('modalFormTarea').style.display = 'flex';
}

function guardarTarea(e) {
    e.preventDefault();
    let formData = new FormData(document.getElementById('formTarea'));
    formData.append('action', 'save_tarea');

    fetch('tareas_api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            cerrarFormularioTarea(); 
            cargarTareas();
            Swal.fire('¡Guardado!', 'La publicación ha sido guardada.', 'success');
        }
    });
}

function publicarTarea(id) {
    Swal.fire({
        title: '¿Publicar ahora?', 
        text: "La tarea será visible para los alumnos de inmediato, respetando su fecha de cierre original.", 
        icon: 'info',
        showCancelButton: true, confirmButtonColor: '#28a745', confirmButtonText: 'Sí, publicar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('tareas_api.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: `action=publicar_manual&tarea_id=${id}` })
            .then(res => res.json())
            .then(data => { if(data.status === 'success') { cargarTareas(); Swal.fire('¡Publicada!', '', 'success'); } });
        }
    });
}

function finalizarTarea(id) {
    Swal.fire({
        title: '¿Finalizar ahora?', 
        text: "La tarea se cerrará y ya no recibirá más entregas.", 
        icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#fd7e14', confirmButtonText: 'Sí, finalizar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('tareas_api.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: `action=finalizar_manual&tarea_id=${id}` })
            .then(res => res.json())
            .then(data => { if(data.status === 'success') { cargarTareas(); Swal.fire('¡Finalizada!', '', 'success'); } });
        }
    });
}

function borrarTarea(id) {
    Swal.fire({
        title: '¿Estás seguro?', 
        text: "Se borrará permanentemente.", 
        icon: 'error',
        showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, borrar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('tareas_api.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: `action=delete_tarea&tarea_id=${id}` })
            .then(res => res.json())
            .then(data => { if(data.status === 'success') { cargarTareas(); Swal.fire('¡Borrado!', '', 'success'); } });
        }
    });
}