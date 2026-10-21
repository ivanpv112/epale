<?php
/**
 * centro_notificaciones.php
 * Componente visual y asíncrono del Centro de Notificaciones para Estudiantes E-PALE.
 * Carga la estructura visual al instante (0 consultas a la base de datos en la carga de página)
 * y consume los datos en segundo plano a través de api_notificaciones.php con caché en cliente.
 */

if (!isset($_SESSION['user_id'])) {
    return;
}

$user_id_notif = (int)$_SESSION['user_id'];
?>

<!-- BOTÓN CAMPANITA CON CONTADOR -->
<div class="notif-dropdown-wrapper" style="position: relative; display: inline-block;">
    <button type="button" class="notif-bell-btn" id="btnCampanaNotif" onclick="toggleDropdownNotificaciones(event)" title="Centro de Notificaciones" aria-label="Notificaciones">
        <i class="fas fa-bell"></i>
        <span class="notif-badge-pill" id="badgeNotifCount" style="display: none;">0</span>
    </button>

    <!-- PANEL DESPLEGABLE FLOTANTE (DROPDOWN) -->
    <div class="notif-panel-dropdown" id="panelNotificacionesDropdown" onclick="event.stopPropagation()">
        <!-- Header con Switch de Pestañas Elegante -->
        <div class="notif-panel-header">
            <div class="notif-title-row">
                <span class="notif-header-title"><i class="fas fa-bell" style="color: var(--udg-blue);"></i> Notificaciones</span>
                <span class="notif-total-badge" id="notifTotalBadge"><span id="notifTotalNum">0</span> activa(s)</span>
            </div>

            <!-- Switch Tabs con Glider Líquido Conectado -->
            <div class="notif-tabs-switch" id="notifTabsSwitch">
                <span class="notif-tab-glider" id="notifTabGlider"></span>
                <button type="button" class="notif-tab-item active" id="tabNotifBtnProf" onclick="switchTabNotificaciones('profesores')">
                    <i class="fas fa-chalkboard-teacher"></i> Mis Clases <span class="notif-tab-count-pill" id="badgeTabProf">0</span>
                </button>
                <button type="button" class="notif-tab-item" id="tabNotifBtnInst" onclick="switchTabNotificaciones('institucionales')">
                    <i class="fas fa-university"></i> Institucionales <span class="notif-tab-count-pill" id="badgeTabInst">0</span>
                </button>
            </div>
        </div>

        <!-- CUERPO SCROLLABLE: PESTAÑA 1 (PROFESORES) -->
        <div class="notif-panel-body" id="notifBodyProfesores">
            <div class="notif-empty-state" id="notifLoadingProf">
                <i class="fas fa-circle-notch fa-spin"></i>
                <p>Cargando avisos...</p>
            </div>
        </div>

        <!-- CUERPO SCROLLABLE: PESTAÑA 2 (INSTITUCIONALES Y TRÁMITES) -->
        <div class="notif-panel-body" id="notifBodyInstitucionales" style="display: none;">
            <div class="notif-empty-state" id="notifLoadingInst">
                <i class="fas fa-circle-notch fa-spin"></i>
                <p>Cargando comunicados...</p>
            </div>
        </div>

        <div class="notif-panel-footer">
            <span>E-PALE • Centro de Notificaciones</span>
        </div>
    </div>
</div>

<!-- SCRIPTS PARA INTERACTIVIDAD Y CARGA ASÍNCRONA -->
<script>
const USER_NOTIF_STORAGE_KEY = 'epale_notif_read_<?php echo $user_id_notif; ?>';
const USER_NOTIF_CACHE_KEY = 'epale_notif_cache_<?php echo $user_id_notif; ?>';
const NOTIF_CACHE_TTL_MS = 45000; // 45 segundos de caché en sessionStorage para navegación ultrarrápida

let notifDataLoaded = false;
let notifIsFetching = false;

function escapeHtmlNotif(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getNotificacionesLeidas() {
    try {
        const stored = localStorage.getItem(USER_NOTIF_STORAGE_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch (e) {
        return [];
    }
}

function marcarNotificacionLeida(key, tab) {
    if (!key) return;
    try {
        let leidas = getNotificacionesLeidas();
        if (!leidas.includes(key)) {
            leidas.push(key);
            localStorage.setItem(USER_NOTIF_STORAGE_KEY, JSON.stringify(leidas));
        }
        
        // Marcar visualmente la tarjeta si existe en el DOM
        const card = document.querySelector(`.notif-item-card[data-notif-key="${key}"]`);
        if (card) {
            card.classList.add('is-read');
        }

        actualizarContadoresNotificaciones();
    } catch (e) {
        console.error('Error al guardar notificación leída:', e);
    }
}

function actualizarContadoresNotificaciones() {
    const leidas = getNotificacionesLeidas();
    
    // Contar no leídas en Mis Clases (solo avisos con estatus ACTIVA o PRÓXIMA)
    const cardsProf = document.querySelectorAll('#notifBodyProfesores .notif-item-card');
    let unreadProf = 0;
    cardsProf.forEach(card => {
        const k = card.getAttribute('data-notif-key');
        const est = card.getAttribute('data-estatus');
        const esActiva = (est === 'ACTIVA' || est === 'PRÓXIMA');
        if (k && leidas.includes(k)) {
            card.classList.add('is-read');
        } else if (esActiva) {
            unreadProf++;
        }
    });

    // Contar no leídas en Institucionales
    const cardsInst = document.querySelectorAll('#notifBodyInstitucionales .notif-item-card');
    let unreadInst = 0;
    cardsInst.forEach(card => {
        const k = card.getAttribute('data-notif-key');
        if (k && leidas.includes(k)) {
            card.classList.add('is-read');
        } else {
            unreadInst++;
        }
    });

    const totalUnread = unreadProf + unreadInst;

    // Actualizar píldoras de las pestañas
    const badgeTabProf = document.getElementById('badgeTabProf');
    if (badgeTabProf) badgeTabProf.textContent = unreadProf;

    const badgeTabInst = document.getElementById('badgeTabInst');
    if (badgeTabInst) badgeTabInst.textContent = unreadInst;

    // Actualizar badge total del panel
    const notifTotalNum = document.getElementById('notifTotalNum');
    if (notifTotalNum) notifTotalNum.textContent = totalUnread;

    // Actualizar campanita global
    const badgeCampana = document.getElementById('badgeNotifCount');
    if (badgeCampana) {
        if (totalUnread > 0) {
            badgeCampana.textContent = totalUnread > 9 ? '9+' : totalUnread;
            badgeCampana.style.display = 'flex';
        } else {
            badgeCampana.style.display = 'none';
        }
    }
}

function renderCentroNotificaciones(data) {
    const bodyProf = document.getElementById('notifBodyProfesores');
    const bodyInst = document.getElementById('notifBodyInstitucionales');
    if (!bodyProf || !bodyInst) return;

    // 1. Renderizar Profesores (Mis Clases)
    const profesores = data.profesores || [];
    if (profesores.length > 0) {
        let htmlProf = '';
        profesores.forEach(ap => {
            const claseBorde = (ap.tipo === 'TAREA') ? 'notif-item-tarea-clase' : 'notif-item-aviso-clase';
            const claseSubBadge = (ap.tipo === 'TAREA') ? 'neon-sub-tarea' : 'neon-sub-aviso';
            const etiqueta = ap.materia_nombre + ' ' + ap.materia_nivel;

            const infoJson = JSON.stringify({
                titulo: ap.icono + ' ' + ap.titulo,
                etiqueta: etiqueta,
                tag_color: 'var(--bg-gray)',
                tag_text: 'var(--text-dark)',
                cuerpo: escapeHtmlNotif(ap.descripcion).replace(/\n/g, '<br>'),
                imagen: ap.imagen,
                profesor: ap.profesor,
                fecha: ap.fecha_completa,
                fecha_expiracion: ap.fecha_expiracion
            });

            const cartelBadge = ap.imagen ? '<span style="font-size: 0.7rem; color: var(--udg-blue); font-weight: bold;"><i class="fas fa-image"></i> Cartel</span>' : '';

            htmlProf += `
                <div class="notif-item-card ${claseBorde}" 
                     data-notif-key="${escapeHtmlNotif(ap.key)}" 
                     data-notif-tab="profesores"
                     data-estatus="${escapeHtmlNotif(ap.estatus)}"
                     onclick="abrirModalDesdeNotif(this)">
                    <div class="notif-item-top">
                        <span class="notif-sub-badge ${claseSubBadge}">
                            ${escapeHtmlNotif(etiqueta)}
                        </span>
                        <div style="display:flex; align-items:center; gap:5px;">
                            ${cartelBadge}
                            <span class="notif-status-pill" style="background: ${ap.badge_bg}">${escapeHtmlNotif(ap.estatus)}</span>
                        </div>
                    </div>
                    <div class="notif-item-title">${escapeHtmlNotif(ap.icono + ' ' + ap.titulo)}</div>
                    <div class="notif-item-desc">${escapeHtmlNotif(ap.desc_corta)}</div>
                    <div class="notif-item-footer">
                        <span><i class="far fa-user"></i> Prof. ${escapeHtmlNotif(ap.profesor)}</span>
                        <span><i class="far fa-clock"></i> ${escapeHtmlNotif(ap.fecha_formateada)}</span>
                    </div>
                </div>
            `;
        });
        bodyProf.innerHTML = htmlProf;

        // Inyectar data-info de forma segura para no romper comillas
        const cardsProf = bodyProf.querySelectorAll('.notif-item-card');
        profesores.forEach((ap, idx) => {
            if (cardsProf[idx]) {
                cardsProf[idx].setAttribute('data-info', JSON.stringify({
                    titulo: ap.icono + ' ' + ap.titulo,
                    etiqueta: ap.materia_nombre + ' ' + ap.materia_nivel,
                    tag_color: 'var(--bg-gray)',
                    tag_text: 'var(--text-dark)',
                    cuerpo: escapeHtmlNotif(ap.descripcion).replace(/\n/g, '<br>'),
                    imagen: ap.imagen,
                    profesor: ap.profesor,
                    fecha: ap.fecha_completa,
                    fecha_expiracion: ap.fecha_expiracion
                }));
            }
        });
    } else {
        bodyProf.innerHTML = `
            <div class="notif-empty-state">
                <i class="fas fa-clipboard-check"></i>
                <p>No tienes tareas ni avisos de clase pendientes.</p>
            </div>
        `;
    }

    // 2. Renderizar Institucionales y Trámites
    const institucionales = data.institucionales || [];
    if (institucionales.length > 0) {
        let htmlInst = '';
        institucionales.forEach(ag => {
            if (ag.tipo === 'SOLICITUD') {
                let statusClass = 'tramite-pendiente';
                let neonBadgeClass = 'neon-badge-pendiente';
                let statusIcon = 'fa-clock';
                const destinoUrl = ag.tipo_solicitud === 'BAJA' ? 'calificaciones' : 'oferta_academica';
                const destinoLabel = ag.tipo_solicitud === 'BAJA' ? 'Ver en Calificaciones' : 'Ver en Oferta Académica';

                if (ag.estatus === 'APROBADA') {
                    statusClass = 'tramite-aprobada';
                    neonBadgeClass = 'neon-badge-aprobada';
                    statusIcon = 'fa-check-circle';
                } else if (ag.estatus === 'RECHAZADA') {
                    statusClass = 'tramite-rechazada';
                    neonBadgeClass = 'neon-badge-rechazada';
                    statusIcon = 'fa-times-circle';
                }

                let descTexto = '';
                if (ag.estatus === 'PENDIENTE') {
                    descTexto = `Tu solicitud para ${ag.tipo_solicitud === 'BAJA' ? 'dar de baja' : 'ingresar a'} <strong>${escapeHtmlNotif(ag.nombre + ' ' + ag.nivel)}</strong> está siendo revisada por Administración.`;
                } else if (ag.estatus === 'APROBADA') {
                    descTexto = `¡Tu petición para ${ag.tipo_solicitud === 'BAJA' ? 'abandonar' : 'ingresar a'} <strong>${escapeHtmlNotif(ag.nombre + ' ' + ag.nivel)}</strong> fue <strong style="color:#10b981;">aprobada</strong>!`;
                } else {
                    descTexto = `Tu solicitud para <strong>${escapeHtmlNotif(ag.nombre + ' ' + ag.nivel)}</strong> no pudo ser autorizada. Revisa los detalles en tu expediente.`;
                }

                htmlInst += `
                    <div class="notif-item-card notif-item-tramite ${statusClass}" 
                         data-notif-key="${escapeHtmlNotif(ag.key)}" 
                         data-notif-tab="institucionales"
                         onclick="marcarNotificacionLeida('${escapeHtmlNotif(ag.key)}', 'institucionales'); window.location.href='${destinoUrl}';" 
                         title="${escapeHtmlNotif(destinoLabel)}">
                        <div class="notif-item-top">
                            <span class="notif-sub-badge neon-sub-tramite">
                                <i class="fas fa-file-signature"></i> Control Escolar
                            </span>
                            <span class="neon-badge ${neonBadgeClass}">
                                <i class="fas ${statusIcon}"></i> ${escapeHtmlNotif(ag.estatus)}
                            </span>
                        </div>
                        <div class="notif-item-title" style="display:flex; align-items:center; gap:6px;">
                            <span>Solicitud de ${escapeHtmlNotif(ag.tipo_solicitud)}</span>
                            <span style="font-size:0.75rem; color:var(--text-muted); font-weight:normal;">• ${escapeHtmlNotif(ag.nombre + ' ' + ag.nivel)}</span>
                        </div>
                        <div class="notif-item-desc">
                            ${descTexto}
                        </div>
                        <div class="notif-item-footer">
                            <span><i class="far fa-calendar-alt"></i> ${escapeHtmlNotif(ag.fecha_formateada)}</span>
                            <span style="color: var(--udg-blue); font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                ${ag.tipo_solicitud === 'BAJA' ? 'Calificaciones' : 'Oferta'} <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
                            </span>
                        </div>
                    </div>
                `;
            } else {
                let claseBordeInst = 'notif-item-inst-global';
                let claseBadgeInst = 'neon-sub-global';

                if (ag.etiqueta === 'Idioma') {
                    claseBordeInst = 'notif-item-inst-idioma';
                    claseBadgeInst = 'neon-sub-idioma';
                } else if (ag.etiqueta === 'Nivel' || ag.etiqueta === 'Clase') {
                    claseBordeInst = 'notif-item-inst-materia';
                    claseBadgeInst = 'neon-sub-materia';
                }

                const cartelBadge = ag.imagen ? '<span style="font-size: 0.7rem; color: var(--udg-blue); font-weight: bold;"><i class="fas fa-image"></i> Cartel</span>' : '';

                htmlInst += `
                    <div class="notif-item-card ${claseBordeInst}" 
                         data-notif-key="${escapeHtmlNotif(ag.key)}" 
                         data-notif-tab="institucionales"
                         onclick="abrirModalDesdeNotif(this)">
                        <div class="notif-item-top">
                            <span class="notif-sub-badge ${claseBadgeInst}">
                                ${escapeHtmlNotif(ag.etiqueta)}
                            </span>
                            ${cartelBadge}
                        </div>
                        <div class="notif-item-title">${escapeHtmlNotif(ag.titulo)}</div>
                        <div class="notif-item-desc">${escapeHtmlNotif(ag.cuerpo_corto)}</div>
                        <div class="notif-item-footer">
                            <span><i class="fas fa-university"></i> Coordinación E-PALE</span>
                            <span><i class="far fa-clock"></i> ${escapeHtmlNotif(ag.fecha_formateada)}</span>
                        </div>
                    </div>
                `;
            }
        });
        bodyInst.innerHTML = htmlInst;

        // Inyectar data-info para los avisos institucionales (tipo ADMIN)
        const cardsInst = bodyInst.querySelectorAll('.notif-item-card');
        institucionales.forEach((ag, idx) => {
            if (ag.tipo === 'ADMIN' && cardsInst[idx]) {
                cardsInst[idx].setAttribute('data-info', JSON.stringify({
                    titulo: ag.titulo,
                    etiqueta: 'Aviso ' + ag.etiqueta,
                    tag_color: '#eef2ff',
                    tag_text: 'var(--udg-blue)',
                    cuerpo: escapeHtmlNotif(ag.cuerpo).replace(/\n/g, '<br>'),
                    imagen: ag.imagen,
                    profesor: null,
                    fecha: ag.fecha_completa,
                    fecha_expiracion: ag.fecha_expiracion
                }));
            }
        });
    } else {
        bodyInst.innerHTML = `
            <div class="notif-empty-state">
                <i class="far fa-check-circle"></i>
                <p>No tienes comunicados institucionales pendientes.</p>
            </div>
        `;
    }

    notifDataLoaded = true;
    actualizarContadoresNotificaciones();
}

function cargarCentroNotificaciones(forceRefresh = false) {
    if (notifIsFetching) return;

    // Verificar si existe caché fresca en sessionStorage
    if (!forceRefresh) {
        try {
            const rawCache = sessionStorage.getItem(USER_NOTIF_CACHE_KEY);
            if (rawCache) {
                const parsed = JSON.parse(rawCache);
                if (parsed.timestamp && (Date.now() - parsed.timestamp < NOTIF_CACHE_TTL_MS)) {
                    renderCentroNotificaciones(parsed.data);
                    return;
                }
            }
        } catch (e) {}
    }

    notifIsFetching = true;
    fetch('api_notificaciones.php')
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(json => {
            if (json && json.status === 'success' && json.data) {
                try {
                    sessionStorage.setItem(USER_NOTIF_CACHE_KEY, JSON.stringify({
                        timestamp: Date.now(),
                        data: json.data
                    }));
                } catch (e) {}
                renderCentroNotificaciones(json.data);
            }
        })
        .catch(err => {
            console.error('Error cargando notificaciones:', err);
            const loadProf = document.getElementById('notifLoadingProf');
            const loadInst = document.getElementById('notifLoadingInst');
            if (loadProf) loadProf.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#ef4444;"></i><p>No se pudieron cargar los avisos.</p>';
            if (loadInst) loadInst.innerHTML = '<i class="fas fa-exclamation-circle" style="color:#ef4444;"></i><p>No se pudieron cargar los comunicados.</p>';
        })
        .finally(() => {
            notifIsFetching = false;
        });
}

function actualizarGliderNotif(tab) {
    const glider = document.getElementById('notifTabGlider');
    const container = document.getElementById('notifTabsSwitch');
    const activeBtn = tab === 'institucionales' ? document.getElementById('tabNotifBtnInst') : document.getElementById('tabNotifBtnProf');
    if (glider && container && activeBtn) {
        const leftPos = activeBtn.offsetLeft;
        const width = activeBtn.offsetWidth;
        glider.style.left = leftPos + 'px';
        glider.style.width = width + 'px';
        container.setAttribute('data-active-tab', tab);
    }
}

function toggleDropdownNotificaciones(e) {
    e.stopPropagation();
    const panel = document.getElementById('panelNotificacionesDropdown');
    if (panel) {
        const willOpen = !panel.classList.contains('active');
        panel.classList.toggle('active');
        if (willOpen) {
            if (!notifDataLoaded) {
                cargarCentroNotificaciones(false);
            } else {
                actualizarContadoresNotificaciones();
            }
            requestAnimationFrame(() => {
                const btnInst = document.getElementById('tabNotifBtnInst');
                const isInst = btnInst && btnInst.classList.contains('active');
                actualizarGliderNotif(isInst ? 'institucionales' : 'profesores');
            });
        }
    }
}

function switchTabNotificaciones(tab) {
    const btnProf = document.getElementById('tabNotifBtnProf');
    const btnInst = document.getElementById('tabNotifBtnInst');
    const bodyProf = document.getElementById('notifBodyProfesores');
    const bodyInst = document.getElementById('notifBodyInstitucionales');

    const targetBody = tab === 'profesores' ? bodyProf : bodyInst;
    const otherBody = tab === 'profesores' ? bodyInst : bodyProf;

    if (tab === 'profesores') {
        btnProf.classList.add('active');
        btnInst.classList.remove('active');
    } else {
        btnInst.classList.add('active');
        btnProf.classList.remove('active');
    }

    otherBody.style.display = 'none';
    targetBody.style.display = 'flex';
    
    // Re-disparar animación pop-up en cascada de los avisos
    const cards = targetBody.querySelectorAll('.notif-item-card');
    cards.forEach((card, idx) => {
        card.style.animation = 'none';
        void card.offsetWidth; // Forzar reflow
        card.style.animation = `notifCardPopIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1) ${Math.min(idx * 0.04, 0.2)}s both`;
    });

    actualizarGliderNotif(tab);
}

// Cerrar el panel al hacer clic en cualquier parte fuera
document.addEventListener('click', function(e) {
    const panel = document.getElementById('panelNotificacionesDropdown');
    const btn = document.getElementById('btnCampanaNotif');
    if (panel && panel.classList.contains('active')) {
        if (!panel.contains(e.target) && !btn.contains(e.target)) {
            panel.classList.remove('active');
        }
    }
});

// Abre el modal de aviso reutilizando abrirModalAvisoAdmin o SweetAlert2
function abrirModalDesdeNotif(elem) {
    const key = elem.getAttribute('data-notif-key');
    const tab = elem.getAttribute('data-notif-tab') || 'profesores';
    if (key) {
        marcarNotificacionLeida(key, tab);
    }

    const panel = document.getElementById('panelNotificacionesDropdown');
    if (panel) panel.classList.remove('active');
    
    if (typeof abrirModalAvisoAdmin === 'function') {
        abrirModalAvisoAdmin(elem);
    } else {
        try {
            const info = JSON.parse(elem.getAttribute('data-info'));
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: info.titulo,
                    html: `<div style="text-align:justify; font-size:0.95rem; line-height:1.6; margin-top:10px;">${info.cuerpo}</div>` + 
                          (info.imagen ? `<div style="margin-top:15px; text-align:center;"><img src="${info.imagen}" style="max-width:100%; max-height:300px; border-radius:8px;"></div>` : ''),
                    icon: 'info',
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
}

// Iniciar la carga en segundo plano sin bloquear el renderizado de la página
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => cargarCentroNotificaciones(false));
} else {
    cargarCentroNotificaciones(false);
}
</script>
