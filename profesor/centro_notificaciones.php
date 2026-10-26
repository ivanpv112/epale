<?php
/**
 * centro_notificaciones.php
 * Componente visual y asíncrono del Centro de Notificaciones para Profesores E-PALE.
 * Carga la estructura visual al instante (0 consultas a la base de datos en la carga de página)
 * y consume los datos en segundo plano a través de api_notificaciones.php con caché en cliente.
 */

if (!isset($_SESSION['user_id'])) {
    return;
}

$user_id_notif = (int)$_SESSION['user_id'];
?>

<!-- BOTÓN CAMPANITA CON CONTADOR (IDÉNTICO AL DEL ALUMNO) -->
<div class="notif-dropdown-wrapper" style="position: relative; display: inline-block;">
    <button type="button" class="notif-bell-btn" id="btnCampanaNotifProf" onclick="toggleDropdownNotificacionesProf(event)" title="Centro de Notificaciones" aria-label="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="notif-badge-pill" id="badgeNotifCountProf" style="display: none;">0</span>
    </button>

    <!-- PANEL DESPLEGABLE FLOTANTE (DROPDOWN) -->
    <div class="notif-panel-dropdown" id="panelNotificacionesDropdownProf" onclick="event.stopPropagation()">
        <!-- Header Visual Armonizado con acción de marcar leídas -->
        <div class="notif-panel-header" style="padding-bottom: 14px;">
            <div class="notif-title-row" style="margin-bottom: 0;">
                <span class="notif-header-title">
                    <i class="fas fa-bell" style="color: var(--udg-blue);"></i> Comunicados Oficiales
                </span>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="notif-total-badge" id="notifTotalBadgeProf"><span id="notifTotalNumProf">0</span> activa(s)</span>
                    <button type="button" class="notif-header-action-btn" onclick="abrirHistorialAvisosModalProf()" title="Historial de avisos por ciclo escolar">
                        <i class="fas fa-folder"></i>
                    </button>
                    <button type="button" class="notif-header-action-btn" onclick="marcarTodasNotificacionesLeidasProf()" title="Marcar todas como leídas">
                        <i class="fas fa-check-double"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- CUERPO SCROLLABLE CON DESVANECIDO REACTIVO -->
        <div class="notif-panel-body custom-scroll-mask" id="notifBodyProfesor">
            <div class="notif-empty-state" id="notifLoadingProfesor">
                <i class="fas fa-circle-notch fa-spin"></i>
                <p>Cargando comunicados...</p>
            </div>
        </div>

        <div class="notif-panel-footer">
            <span>E-PALE • Coordinación y Administración</span>
        </div>
    </div>
</div>

<!-- MODAL ESPEJO DE HISTORIAL DE AVISOS (PROFESOR) -->
<div id="modalHistorialAvisosProfesor" class="modal-overlay" style="display: none; z-index: 9999;" onclick="cerrarHistorialAvisosModalProf(event)">
    <div class="modal-historial-avisos-content" onclick="event.stopPropagation()">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid rgba(128,128,128,0.15);">
            <div style="display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-folder-open" style="color: var(--udg-blue); font-size: 1.15rem;"></i>
                <h3 style="margin: 0; font-size: 1.15rem; color: var(--text-dark);">Historial de Comunicados</h3>
            </div>
            <span id="historialTotalNumProf" class="notif-total-badge">0 aviso(s)</span>
        </div>

        <!-- Barra de selección de ciclo escolar (Cajitas / Chips) -->
        <div class="hist-ciclos-bar" id="histCiclosBarProf">
            <!-- Chips inyectados por JS -->
        </div>

        <!-- Listado espejo de avisos del ciclo seleccionado -->
        <div class="notif-panel-body custom-scroll-mask" id="histBodyProfesor" style="max-height: 52vh; border: 1px solid rgba(128,128,128,0.12); border-radius: 10px; padding: 12px; background: rgba(0,0,0,0.01);">
            <div class="notif-empty-state">
                <i class="fas fa-circle-notch fa-spin"></i>
                <p>Cargando historial...</p>
            </div>
        </div>

        <!-- Footer con botón rojo neón estandarizado -->
        <div style="display: flex; justify-content: flex-end; margin-top: 15px; padding-top: 12px; border-top: 1px solid rgba(128,128,128,0.15);">
            <button type="button" class="btn-neon-close" onclick="cerrarHistorialAvisosModalProf()">
                Cerrar
            </button>
        </div>
    </div>
</div>

<!-- SCRIPTS PARA INTERACTIVIDAD Y CARGA ASÍNCRONA -->
<script>
const PROF_NOTIF_STORAGE_KEY = 'epale_prof_notif_read_<?php echo $user_id_notif; ?>';
const PROF_NOTIF_CACHE_KEY = 'epale_prof_notif_cache_<?php echo $user_id_notif; ?>';
const PROF_NOTIF_CACHE_TTL_MS = 45000; // 45 segundos de caché en sessionStorage

let profNotifLoaded = false;
let profNotifFetching = false;

function escapeHtmlNotifProf(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getNotificacionesLeidasProf() {
    try {
        const stored = localStorage.getItem(PROF_NOTIF_STORAGE_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch (e) {
        return [];
    }
}

function marcarNotificacionLeidaProf(key) {
    if (!key) return;
    try {
        let leidas = getNotificacionesLeidasProf();
        if (!leidas.includes(key)) {
            leidas.push(key);
            localStorage.setItem(PROF_NOTIF_STORAGE_KEY, JSON.stringify(leidas));
        }
        
        const card = document.querySelector(`.notif-item-card[data-notif-key="${key}"]`);
        if (card) {
            card.classList.add('is-read');
        }

        actualizarContadoresNotificacionesProf();
    } catch (e) {
        console.error('Error al guardar notificación leída:', e);
    }
}

function marcarTodasNotificacionesLeidasProf() {
    try {
        const cards = document.querySelectorAll('#notifBodyProfesor .notif-item-card');
        let leidas = getNotificacionesLeidasProf();
        cards.forEach(card => {
            const k = card.getAttribute('data-notif-key');
            if (k && !leidas.includes(k)) {
                leidas.push(k);
            }
            card.classList.add('is-read');
        });
        localStorage.setItem(PROF_NOTIF_STORAGE_KEY, JSON.stringify(leidas));
        actualizarContadoresNotificacionesProf();
    } catch (e) {
        console.error('Error al marcar todas como leídas:', e);
    }
}

function actualizarContadoresNotificacionesProf() {
    const leidas = getNotificacionesLeidasProf();
    const cards = document.querySelectorAll('#notifBodyProfesor .notif-item-card');
    let unreadCount = 0;

    cards.forEach(card => {
        const k = card.getAttribute('data-notif-key');
        const expirado = card.getAttribute('data-expirado') === 'true';
        if (k && leidas.includes(k)) {
            card.classList.add('is-read');
        } else if (!expirado) {
            unreadCount++;
        }
    });

    const badge = document.getElementById('badgeNotifCountProf');
    const totalNum = document.getElementById('notifTotalNumProf');
    
    if (totalNum) {
        totalNum.innerText = unreadCount;
    }
    
    if (badge) {
        if (unreadCount > 0) {
            badge.innerText = unreadCount > 9 ? '9+' : unreadCount;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

function renderizarNotificacionesProf(data) {
    const body = document.getElementById('notifBodyProfesor');
    if (!body) return;

    const notificaciones = data.notificaciones || [];
    if (notificaciones.length > 0) {
        let html = '';
        notificaciones.forEach(n => {
            let claseBordeInst = 'notif-item-inst-global';
            let claseBadgeInst = 'neon-sub-global';

            if (n.tipo_audiencia === 'IDIOMA') {
                claseBordeInst = 'notif-item-inst-idioma';
                claseBadgeInst = 'neon-sub-idioma';
            } else if (n.tipo_audiencia === 'MATERIA' || n.tipo_audiencia === 'GRUPO') {
                claseBordeInst = 'notif-item-inst-materia';
                claseBadgeInst = 'neon-sub-materia';
            }

            const cartelBadge = n.imagen ? '<span style="font-size: 0.7rem; color: var(--udg-blue); font-weight: bold;"><i class="fas fa-image"></i> Cartel</span>' : '';
            const expiradoPill = n.expirado ? '<span class="notif-status-pill" style="background: #64748b;">EXPIRADO</span>' : '';

            html += `
                <div class="notif-item-card ${claseBordeInst}" 
                     data-notif-key="${escapeHtmlNotifProf(n.key)}" 
                     data-expirado="${n.expirado ? 'true' : 'false'}"
                     onclick="abrirModalDesdeNotifProf(this)">
                    <div class="notif-item-top">
                        <span class="notif-sub-badge ${claseBadgeInst}">
                            <i class="${n.icono}"></i> ${escapeHtmlNotifProf(n.etiqueta)}
                        </span>
                        <div style="display:flex; align-items:center; gap:5px;">
                            ${cartelBadge}
                            ${expiradoPill}
                        </div>
                    </div>
                    <div class="notif-item-title">${escapeHtmlNotifProf(n.titulo)}</div>
                    <div class="notif-item-desc">${escapeHtmlNotifProf(n.cuerpo_corto)}</div>
                    <div class="notif-item-footer">
                        <span><i class="fas fa-university"></i> Coordinación E-PALE</span>
                        <span><i class="far fa-clock"></i> ${escapeHtmlNotifProf(n.fecha_formateada)}</span>
                    </div>
                </div>
            `;
        });
        body.innerHTML = html;

        // Inyectar datos JSON limpios en data-info
        const renderedCards = body.querySelectorAll('.notif-item-card');
        notificaciones.forEach((n, idx) => {
            if (renderedCards[idx]) {
                renderedCards[idx].setAttribute('data-info', JSON.stringify({
                    titulo: n.titulo,
                    etiqueta: n.etiqueta,
                    cuerpo: escapeHtmlNotifProf(n.cuerpo).replace(/\n/g, '<br>'),
                    imagen: n.imagen,
                    fecha: n.fecha_completa,
                    fecha_expiracion: n.fecha_expiracion
                }));
            }
        });
    } else {
        body.innerHTML = `
            <div class="notif-empty-state">
                <i class="fas fa-inbox"></i>
                <p>No hay comunicados administrativos pendientes.</p>
            </div>
        `;
    }

    actualizarContadoresNotificacionesProf();
    profNotifLoaded = true;

    // Aplicar máscara de scroll reactiva estandarizada si existe
    if (typeof aplicarMascaraScrollUniversal === 'function') {
        aplicarMascaraScrollUniversal(body);
    }
}

async function cargarCentroNotificacionesProf(forzar = false) {
    if (profNotifFetching) return;

    if (!forzar) {
        try {
            const cached = sessionStorage.getItem(PROF_NOTIF_CACHE_KEY);
            if (cached) {
                const parsed = JSON.parse(cached);
                if (Date.now() - parsed.timestamp < PROF_NOTIF_CACHE_TTL_MS) {
                    renderizarNotificacionesProf(parsed.data);
                    return;
                }
            }
        } catch (e) {}
    }

    profNotifFetching = true;
    try {
        const response = await fetch('api_notificaciones.php');
        if (!response.ok) throw new Error('Error de red al consultar notificaciones');
        const resJson = await response.json();

        if (resJson && resJson.status === 'success') {
            sessionStorage.setItem(PROF_NOTIF_CACHE_KEY, JSON.stringify({
                data: resJson.data,
                timestamp: Date.now()
            }));
            renderizarNotificacionesProf(resJson.data);
        }
    } catch (err) {
        console.error('Error cargando notificaciones del profesor:', err);
        const body = document.getElementById('notifBodyProfesor');
        if (body && !profNotifLoaded) {
            body.innerHTML = `
                <div class="notif-empty-state">
                    <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>
                    <p>No fue posible conectar con el centro de avisos.</p>
                </div>
            `;
        }
    } finally {
        profNotifFetching = false;
    }
}

function toggleDropdownNotificacionesProf(event) {
    if (event) event.stopPropagation();
    const panel = document.getElementById('panelNotificacionesDropdownProf');
    if (!panel) return;

    const isActive = panel.classList.contains('active');

    if (isActive) {
        panel.classList.remove('active');
    } else {
        panel.classList.add('active');

        // Si no se han cargado datos, cargar al abrir
        if (!profNotifLoaded) {
            cargarCentroNotificacionesProf(true);
        } else {
            // Re-disparar animación pop-up en cascada de los avisos
            const cards = panel.querySelectorAll('.notif-item-card');
            cards.forEach((card, idx) => {
                card.style.animation = 'none';
                void card.offsetWidth;
                card.style.animation = `notifCardPopIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) ${Math.min(idx * 0.04, 0.2)}s both`;
            });
        }
    }
}

// Cerrar el panel al hacer clic en cualquier parte fuera
document.addEventListener('click', function(e) {
    const panel = document.getElementById('panelNotificacionesDropdownProf');
    const btn = document.getElementById('btnCampanaNotifProf');
    if (panel && panel.classList.contains('active')) {
        if (!panel.contains(e.target) && !btn.contains(e.target)) {
            panel.classList.remove('active');
        }
    }
});

// Modal para visualizar el aviso completo (idéntico al del alumno y admin)
function abrirModalDesdeNotifProf(elem) {
    const key = elem.getAttribute('data-notif-key');
    if (key) {
        marcarNotificacionLeidaProf(key);
    }

    const panel = document.getElementById('panelNotificacionesDropdownProf');
    if (panel) panel.classList.remove('active');

    try {
        const info = JSON.parse(elem.getAttribute('data-info'));
        
        // Si la página cuenta con el modal dedicado de aviso (index.php), lo poblamos y mostramos con el estilo oficial
        const modalProf = document.getElementById('modalDetalleAvisoProfesor');
        if (modalProf) {
            const badgeTipo = document.getElementById('detAvisoBadgeTipo');
            if (badgeTipo) {
                badgeTipo.className = 'tag-aviso notif-sub-badge neon-sub-global';
                badgeTipo.innerHTML = `<i class="fas fa-bullhorn"></i> Comunicado ${escapeHtmlNotifProf(info.etiqueta)}`;
            }

            const tituloElem = document.getElementById('detAvisoTitulo');
            if (tituloElem) tituloElem.innerText = info.titulo;

            const cuerpoElem = document.getElementById('detAvisoCuerpo');
            if (cuerpoElem) cuerpoElem.innerHTML = info.cuerpo;

            const imgContainer = document.getElementById('detAvisoImgContainer');
            const imgEl = document.getElementById('detAvisoImg');
            if (imgContainer && imgEl) {
                if (info.imagen) {
                    imgEl.src = info.imagen;
                    imgContainer.style.display = 'block';
                } else {
                    imgContainer.style.display = 'none';
                    imgEl.src = '';
                }
            }

            const claseElem = document.getElementById('detAvisoClase');
            if (claseElem) claseElem.innerText = 'Administración E-PALE';

            const inicioElem = document.getElementById('detAvisoInicio');
            if (inicioElem) inicioElem.innerText = info.fecha || '--';

            const finRow = document.getElementById('detAvisoFinRow');
            const finElem = document.getElementById('detAvisoFin');
            if (finRow && finElem) {
                if (info.fecha_expiracion) {
                    finElem.innerText = info.fecha_expiracion;
                    finRow.style.display = 'inline';
                } else {
                    finRow.style.display = 'none';
                }
            }

            // Ocultar botón de editar para avisos institucionales de administración
            const btnEdit = document.getElementById('btnDetalleEditarAviso');
            if (btnEdit) {
                btnEdit.style.display = 'none';
            }

            modalProf.style.display = 'flex';
            return;
        }

        // Si estamos en otra página (horario, perfil, etc.) que no tiene el modal HTML incrustado, usar SweetAlert2 adaptado al tema
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                background: isDark ? '#151e32' : '#ffffff',
                color: isDark ? '#f8fafc' : '#1e293b',
                title: `<span style="font-size:1.25rem; font-weight:800; color:var(--udg-blue);">${escapeHtmlNotifProf(info.titulo)}</span>`,
                html: `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; font-size:0.82rem; color:var(--text-muted); border-bottom:1px solid ${isDark ? '#1e293b' : '#eef2f6'}; padding-bottom:10px;">
                        <span><i class="fas fa-university" style="color:var(--udg-blue);"></i> ${escapeHtmlNotifProf(info.etiqueta)}</span>
                        <span><i class="far fa-clock"></i> ${escapeHtmlNotifProf(info.fecha)}</span>
                    </div>
                    <div style="text-align:justify; font-size:0.98rem; line-height:1.65; color:${isDark ? '#f1f5f9' : '#334155'};">
                        ${info.cuerpo}
                    </div>
                    ${info.imagen ? `<div style="margin-top:16px; text-align:center;"><img src="${info.imagen}" style="max-width:100%; max-height:360px; border-radius:10px; box-shadow:0 6px 20px rgba(0,0,0,0.25);"></div>` : ''}
                `,
                confirmButtonColor: 'var(--udg-blue)',
                confirmButtonText: 'Entendido'
            });
        } else {
            alert(info.titulo + '\n\n' + info.cuerpo.replace(/<br>/g, '\n'));
        }
    } catch (err) {
        console.error('Error abriendo modal de aviso:', err);
    }
}

function cerrarDetalleAvisoGlobal(e) {
    if (e && e.target && e.target !== document.getElementById('modalDetalleAvisoProfesor')) {
        return;
    }
    const modal = document.getElementById('modalDetalleAvisoProfesor');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Cerrar con tecla Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('modalDetalleAvisoProfesor');
        if (modal && modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
        const modalHist = document.getElementById('modalHistorialAvisosProfesor');
        if (modalHist && modalHist.style.display === 'flex') {
            modalHist.style.display = 'none';
        }
    }
});

// ==========================================
// MÓDULO DE HISTORIAL DE AVISOS POR CICLO
// ==========================================
let histCiclosCargadosProf = false;
let histCiclosDataProf = [];
let histCicloActivoIdProf = null;

async function abrirHistorialAvisosModalProf() {
    // Cerrar el dropdown de la campanita
    const panel = document.getElementById('panelNotificacionesDropdownProf');
    if (panel) panel.classList.remove('active');

    const modal = document.getElementById('modalHistorialAvisosProfesor');
    if (modal) modal.style.display = 'flex';

    if (!histCiclosCargadosProf) {
        await cargarCiclosHistorialProf();
    }
}

function cerrarHistorialAvisosModalProf(e) {
    if (e && e.target && e.target !== document.getElementById('modalHistorialAvisosProfesor')) {
        return;
    }
    const modal = document.getElementById('modalHistorialAvisosProfesor');
    if (modal) modal.style.display = 'none';
}

async function cargarCiclosHistorialProf() {
    const bar = document.getElementById('histCiclosBarProf');
    const body = document.getElementById('histBodyProfesor');
    if (!bar || !body) return;

    bar.innerHTML = '<span style="font-size:0.8rem; color:var(--text-muted);"><i class="fas fa-circle-notch fa-spin"></i> Cargando ciclos...</span>';

    try {
        const res = await fetch('../api_historial_avisos.php?action=ciclos');
        const json = await res.json();

        if (json.status === 'success' && json.data.ciclos) {
            histCiclosDataProf = json.data.ciclos;
            histCiclosCargadosProf = true;

            if (histCiclosDataProf.length === 0) {
                bar.innerHTML = '';
                body.innerHTML = `
                    <div class="notif-empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>No hay ningún historial para mostrar.</p>
                    </div>
                `;
                return;
            }

            // Renderizar chips de ciclos
            let chipsHtml = '';
            histCiclosDataProf.forEach((c, idx) => {
                const esActivoSemestre = (parseInt(c.activo) === 1);
                const tagActual = esActivoSemestre ? ' <span style="font-size:0.68rem; opacity:0.85;">(Actual)</span>' : '';
                chipsHtml += `
                    <button type="button" class="hist-ciclo-chip ${idx === 0 ? 'active' : ''}" 
                            data-ciclo-id="${c.ciclo_id}" 
                            onclick="seleccionarCicloHistorialProf(${c.ciclo_id}, this)">
                        <i class="fas fa-calendar-alt"></i> ${escapeHtmlNotifProf(c.nombre)}${tagActual}
                    </button>
                `;
            });
            bar.innerHTML = chipsHtml;

            // Seleccionar automáticamente el primer ciclo de la lista
            seleccionarCicloHistorialProf(histCiclosDataProf[0].ciclo_id);
        } else {
            throw new Error('Respuesta inválida');
        }
    } catch (err) {
        console.error('Error cargando ciclos del historial:', err);
        bar.innerHTML = '';
        body.innerHTML = `
            <div class="notif-empty-state">
                <i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i>
                <p>No fue posible conectar con el historial.</p>
            </div>
        `;
    }
}

async function seleccionarCicloHistorialProf(cicloId, btnElem = null) {
    histCicloActivoIdProf = cicloId;

    if (btnElem) {
        document.querySelectorAll('#histCiclosBarProf .hist-ciclo-chip').forEach(b => b.classList.remove('active'));
        btnElem.classList.add('active');
    }

    const body = document.getElementById('histBodyProfesor');
    const totalNum = document.getElementById('historialTotalNumProf');
    if (!body) return;

    body.innerHTML = `
        <div class="notif-empty-state">
            <i class="fas fa-circle-notch fa-spin"></i>
            <p>Cargando comunicados del ciclo...</p>
        </div>
    `;

    try {
        const res = await fetch(`../api_historial_avisos.php?action=avisos&ciclo_id=${cicloId}`);
        if (!res.ok) {
            const errTxt = await res.text();
            throw new Error(`Servidor respondió con código ${res.status}: ${errTxt}`);
        }
        const json = await res.json();

        if (json.status === 'success') {
            const avisos = json.data.avisos || [];
            if (totalNum) totalNum.innerText = `${avisos.length} aviso(s)`;

            if (avisos.length === 0) {
                body.innerHTML = `
                    <div class="notif-empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No se encontraron comunicados registrados en este ciclo.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            avisos.forEach(n => {
                const cartelBadge = n.imagen ? '<span style="font-size: 0.7rem; color: var(--udg-blue); font-weight: bold;"><i class="fas fa-image"></i> Cartel</span>' : '';
                html += `
                    <div class="notif-item-card ${n.borde_card}" 
                         data-notif-key="${escapeHtmlNotifProf(n.key)}"
                         onclick="abrirModalDesdeNotifProf(this)">
                        <div class="notif-item-top">
                            <span class="notif-sub-badge ${n.tipo_badge}">
                                <i class="${n.icono}"></i> ${escapeHtmlNotifProf(n.categoria)}
                            </span>
                            ${cartelBadge}
                        </div>
                        <div class="notif-item-title">${escapeHtmlNotifProf(n.titulo)}</div>
                        <div class="notif-item-desc">${escapeHtmlNotifProf(n.cuerpo_corto)}</div>
                        <div class="notif-item-footer">
                            <span><i class="fas fa-user-edit"></i> ${escapeHtmlNotifProf(n.autor)}</span>
                            <span><i class="far fa-clock"></i> ${escapeHtmlNotifProf(n.fecha_formateada)}</span>
                        </div>
                    </div>
                `;
            });
            body.innerHTML = html;

            // Inyectar data-info
            const cards = body.querySelectorAll('.notif-item-card');
            avisos.forEach((n, idx) => {
                if (cards[idx]) {
                    cards[idx].setAttribute('data-info', JSON.stringify({
                        titulo: n.titulo,
                        etiqueta: n.materia || n.categoria,
                        cuerpo: escapeHtmlNotifProf(n.cuerpo).replace(/\n/g, '<br>'),
                        imagen: n.imagen,
                        fecha: n.fecha_inicio,
                        fecha_expiracion: n.fecha_fin
                    }));
                }
            });
        } else {
            throw new Error(json.message || 'Error desconocido');
        }
    } catch (err) {
        console.error('Error cargando avisos del ciclo:', err);
        body.innerHTML = `
            <div class="notif-empty-state">
                <i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i>
                <p>Error al cargar comunicados de este ciclo.</p>
                <span style="font-size: 0.72rem; color: #94a3b8; display: block; margin-top: 4px;">${escapeHtmlNotifProf(err.message)}</span>
            </div>
        `;
    }
}


// Carga en segundo plano sin bloquear el hilo principal
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => cargarCentroNotificacionesProf(false));
} else {
    cargarCentroNotificacionesProf(false);
}
</script>
