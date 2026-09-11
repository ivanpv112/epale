// js/avisos_profesor.js

let avisosActuales = [];

document.addEventListener('DOMContentLoaded', cargarAvisos);

function toggleGroup(event, index) {
    let body = document.getElementById('body-group-' + index);
    let btn = document.getElementById('btn-toggle-' + index);
    if (body.style.display === 'none') {
        body.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-chevron-up"></i>';
    } else {
        body.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-chevron-down"></i>';
    }
}

function abrirPanelAvisos() { 
    document.getElementById('modalGestionAvisos').style.display = 'flex'; 
    cargarAvisos(); 
}

function cerrarPanelAvisos() { 
    document.getElementById('modalGestionAvisos').style.display = 'none'; 
}

function cerrarFormularioAviso() { 
    document.getElementById('modalFormAviso').style.display = 'none'; 
}

function cargarAvisos() {
    const tbody = document.getElementById('tablaAvisosBody');
    const resumen = document.getElementById('lista_resumen_avisos');

    if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Cargando...</td></tr>';
    if (resumen) resumen.innerHTML = '<p style="color: #888; margin: 0;">Cargando avisos...</p>';
    
    fetch('avisos_api.php', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
        body: `action=get_avisos&csrf_token=${encodeURIComponent(csrfToken)}` 
    })
    .then(res => res.json())
    .then(data => {
        avisosActuales = data;
        let htmlResumen = '';
        let contadorPendientes = 0;
        let avisosMostradosDash = 0; 

        if (tbody) tbody.innerHTML = '';

        if (data.length === 0) {
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No tienes avisos en el ciclo actual.</td></tr>';
            if (resumen) resumen.innerHTML = '<p style="color: #888; margin: 0;">No hay avisos activos.</p>';
            actualizarContadorEstadisticas(0);
            return;
        }

        data.forEach(a => {
            let badgeClass = '';
            if (a.estatus === 'PENDIENTE') badgeClass = 'badge-gray';
            else if (a.estatus === 'PRÓXIMA') badgeClass = 'badge-warning';
            else if (a.estatus === 'ACTIVA') badgeClass = 'badge-success';
            else if (a.estatus === 'FINALIZADA') badgeClass = 'badge-danger';

            let icono = a.tipo === 'AVISO' ? '📢' : '📝';
            
            if (a.estatus !== 'FINALIZADA') contadorPendientes++;

            if (tbody) {
                let btnPublicar = (a.estatus === 'PENDIENTE' || a.estatus === 'PRÓXIMA') 
                    ? `<button onclick="publicarAviso(${a.aviso_id})" class="action-btn publish-btn" title="Publicar Ahora"><i class="fas fa-play"></i></button>` : '';
                
                let btnFinalizar = (a.estatus === 'ACTIVA') 
                    ? `<button onclick="finalizarAviso(${a.aviso_id})" class="action-btn finish-btn" title="Finalizar Ahora"><i class="fas fa-stop"></i></button>` : '';

                tbody.innerHTML += `
                    <tr>
                        <td>${icono} ${a.tipo}</td>
                        <td style="font-weight:bold;">${a.titulo}</td>
                        <td><small>${a.materia_nombre} (NRC: ${a.nrc})</small></td>
                        <td>${new Date(a.fecha_inicio).toLocaleString()}</td>
                        <td>${new Date(a.fecha_fin).toLocaleString()}</td>
                        <td><span class="badge ${badgeClass}">${a.estatus}</span></td>
                        <td style="white-space: nowrap;">
                            ${btnPublicar}
                            ${btnFinalizar}
                            <button onclick="editarAviso(${a.aviso_id})" class="action-btn edit-btn" title="Editar"><i class="fas fa-pen"></i></button>
                            <button onclick="borrarAviso(${a.aviso_id})" class="action-btn delete-btn" title="Borrar"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `;
            }

            if (resumen && a.estatus !== 'FINALIZADA' && avisosMostradosDash < 3) {
                let isUrgent = (a.estatus === 'PRÓXIMA') ? 'task-urgent' : '';
                let tituloCorto = a.titulo.length > 35 ? a.titulo.substring(0, 35) + '...' : a.titulo;

                htmlResumen += `
                    <div class="task-item ${isUrgent}" onclick="abrirPanelAvisos()" title="Clic para gestionar">
                        <div style="display:flex; justify-content: space-between; align-items:flex-start;">
                            <div style="max-width: 75%; overflow: hidden;">
                                <div class="task-title" style="word-break: break-all;">${icono} ${tituloCorto}</div>
                                <div class="task-meta"><i class="fas fa-chalkboard"></i> ${a.materia_nombre} (NRC: ${a.nrc})</div>
                            </div>
                            <span class="badge ${badgeClass}">${a.estatus}</span>
                        </div>
                        <div class="task-meta" style="margin-top: 8px; color: #555;">
                            <i class="far fa-calendar-alt"></i> Cierre: <strong>${new Date(a.fecha_fin).toLocaleString()}</strong>
                        </div>
                    </div>
                `;
                avisosMostradosDash++;
            }
        });

        if (resumen) {
            resumen.innerHTML = htmlResumen || '<p style="color: #888; margin: 0;">No hay avisos activos en este momento.</p>';
            
            let avisosOcultos = contadorPendientes - avisosMostradosDash;
            if (avisosOcultos > 0) {
                resumen.innerHTML += `
                    <div style="text-align:center; margin-top:15px;">
                        <button onclick="abrirPanelAvisos()" style="background:none; border:none; color:var(--udg-blue); text-decoration:underline; cursor:pointer; font-size: 0.9rem;">
                            Ver ${avisosOcultos} avisos más...
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
        if (box.innerHTML.includes('Avisos Activos')) {
            const numDiv = box.querySelector('.number');
            if (numDiv) numDiv.innerText = numero;
        }
    });
}

function abrirFormularioAviso() {
    document.getElementById('formAviso').reset();
    document.getElementById('aviso_id').value = '';
    document.getElementById('tituloModalAviso').innerText = 'Nuevo Aviso';
    cargarGruposSelect();
    document.getElementById('modalFormAviso').style.display = 'flex';
}

function cargarGruposSelect(nrc_seleccionado = '') {
    fetch('avisos_api.php', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
        body: `action=get_grupos_activos&csrf_token=${encodeURIComponent(csrfToken)}` 
    })
    .then(res => res.json())
    .then(data => {
        let select = document.getElementById('aviso_nrc');
        select.innerHTML = '<option value="">-- Selecciona una Clase --</option>';
        data.forEach(g => {
            let sel = (g.nrc == nrc_seleccionado) ? 'selected' : '';
            select.innerHTML += `<option value="${g.nrc}" ${sel}>${g.nombre} (NRC: ${g.nrc})</option>`;
        });
    });
}

function editarAviso(id) {
    let a = avisosActuales.find(x => x.aviso_id == id);
    if (!a) return;
    document.getElementById('aviso_id').value = a.aviso_id;
    document.querySelector(`input[name="tipo"][value="${a.tipo}"]`).checked = true;
    document.getElementById('aviso_titulo').value = a.titulo;
    document.getElementById('aviso_descripcion').value = a.descripcion;
    document.getElementById('aviso_inicio').value = a.fecha_inicio_input;
    document.getElementById('aviso_fin').value = a.fecha_fin_input;
    document.getElementById('tituloModalAviso').innerText = 'Editar Aviso';
    cargarGruposSelect(a.nrc);
    document.getElementById('modalFormAviso').style.display = 'flex';
}

function guardarAviso(e) {
    e.preventDefault();
    let formData = new FormData(document.getElementById('formAviso'));
    formData.append('action', 'save_aviso');
    formData.append('csrf_token', csrfToken);

    fetch('avisos_api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            cerrarFormularioAviso(); 
            cargarAvisos();
            Swal.fire('¡Guardado!', 'El aviso ha sido guardado.', 'success');
        }
    });
}

function publicarAviso(id) {
    Swal.fire({
        title: '¿Publicar ahora?', 
        text: "El aviso será visible para los alumnos de inmediato.", 
        icon: 'info',
        showCancelButton: true, confirmButtonColor: '#28a745', confirmButtonText: 'Sí, publicar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('avisos_api.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                body: `action=publicar_manual&aviso_id=${id}&csrf_token=${encodeURIComponent(csrfToken)}` 
            })
            .then(res => res.json())
            .then(data => { if (data.status === 'success') { cargarAvisos(); Swal.fire('¡Publicado!', '', 'success'); } });
        }
    });
}

function finalizarAviso(id) {
    Swal.fire({
        title: '¿Finalizar ahora?', 
        text: "El aviso se cerrará y ya no se mostrará como activo.", 
        icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#fd7e14', confirmButtonText: 'Sí, finalizar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('avisos_api.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                body: `action=finalizar_manual&aviso_id=${id}&csrf_token=${encodeURIComponent(csrfToken)}` 
            })
            .then(res => res.json())
            .then(data => { if (data.status === 'success') { cargarAvisos(); Swal.fire('¡Finalizado!', '', 'success'); } });
        }
    });
}

function borrarAviso(id) {
    Swal.fire({
        title: '¿Estás seguro?', 
        text: "Se borrará permanentemente.", 
        icon: 'error',
        showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, borrar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('avisos_api.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                body: `action=delete_aviso&aviso_id=${id}&csrf_token=${encodeURIComponent(csrfToken)}` 
            })
            .then(res => res.json())
            .then(data => { if (data.status === 'success') { cargarAvisos(); Swal.fire('¡Borrado!', '', 'success'); } });
        }
    });
}