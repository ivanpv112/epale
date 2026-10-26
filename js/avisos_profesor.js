// js/avisos_profesor.js
/**
 * Gestión interactiva y moderna de avisos y tareas del profesor.
 * Conectado a la API centralizada 'avisos_api' y tabla 'avisos'.
 */

let avisosActuales = [];
let filtroActual = 'TODOS';
let imagenMarcadaParaEliminar = false;

document.addEventListener('DOMContentLoaded', () => {
    cargarAvisos();
    sincronizarWidgetsIniciales();

    // Si viene redirigido con ?abrir_avisos=1 abrir el modal automáticamente
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('abrir_avisos') === '1') {
        setTimeout(() => {
            abrirPanelAvisos();
        }, 150);
    }
});

// =========================================================================
// SINCRONIZACIÓN BIDIRECCIONAL: PRÓXIMAS CLASES <-> MIS GRUPOS (CRITERIOS)
// =========================================================================

/**
 * Al hacer clic en una tarjeta de "Próximas Clases (Hoy)":
 * 1. Retroilumina la tarjeta de la clase seleccionada.
 * 2. Si el grupo no está entre los primeros o se encuentra abajo, lo traslada
 *    suavemente al inicio de la lista de grupos con animación de transición.
 * 3. Retroilumina la tarjeta del grupo correspondiente en "Mis Grupos".
 * 4. Cierra cualquier otro grupo abierto (acordeón exclusivo) y despliega sus criterios.
 * 5. Desplaza suavemente el scroll hacia la parte superior.
 */
function seleccionarClaseHoyProfesor(claveGrupo) {
    if (!claveGrupo) return;

    // A. Resaltar tarjeta de clase en "Próximas Clases"
    const classCards = document.querySelectorAll('.class-card');
    classCards.forEach(card => {
        if (card.getAttribute('data-clave-grupo') === claveGrupo) {
            card.classList.add('class-card-active');
        } else {
            card.classList.remove('class-card-active');
        }
    });

    // B. Buscar tarjeta de grupo en "Mis Grupos"
    const groupCards = document.querySelectorAll('.group-progress-card');
    let targetCard = null;
    let targetIndex = null;

    groupCards.forEach(card => {
        if (card.getAttribute('data-clave-grupo') === claveGrupo) {
            targetCard = card;
            targetIndex = card.getAttribute('data-group-index');
        }
    });

    if (!targetCard) return;

    const scrollList = document.getElementById('gruposScrollList');
    const scrollWrapper = document.getElementById('gruposScrollWrapper');

    // C. Si la tarjeta no es la primera o está más abajo, moverla arriba con animación
    if (scrollList && scrollList.firstElementChild !== targetCard) {
        // Remover animación previa si existía
        targetCard.classList.remove('group-card-promoted');
        void targetCard.offsetWidth; // Forzar reflow para reiniciar animación
        
        // Mover dinámicamente al inicio
        scrollList.prepend(targetCard);
        targetCard.classList.add('group-card-promoted');
        
        // Scroll suave al inicio del contenedor
        if (scrollList) {
            scrollList.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    // D. Activar retroiluminación en la tarjeta seleccionada y quitar de las demás
    document.querySelectorAll('.group-progress-card').forEach(card => {
        if (card === targetCard) {
            card.classList.add('group-card-active');
        } else {
            card.classList.remove('group-card-active');
        }
    });

    // E. Acordeón exclusivo: cerrar todos los demás grupos y abrir el seleccionado
    cerrarTodosLosGruposExcepto(targetIndex);
    if (targetIndex !== null) {
        desplegarCriteriosGrupo(targetIndex, true);
    }
}

/**
 * Al hacer clic en una tarjeta de "Mis Grupos" (o su cabecera):
 * 1. Retroilumina la tarjeta del grupo.
 * 2. Cierra otros grupos abiertos y abre/alterna los criterios de este.
 * 3. Retroilumina la tarjeta correspondiente en "Próximas Clases" si tiene clase hoy.
 */
function seleccionarGrupoProfesor(claveGrupo, index) {
    const groupCards = document.querySelectorAll('.group-progress-card');
    groupCards.forEach(card => {
        const cClave = card.getAttribute('data-clave-grupo');
        const cIdx = card.getAttribute('data-group-index');
        if ((claveGrupo && cClave === claveGrupo) || (index !== undefined && String(cIdx) === String(index))) {
            card.classList.add('group-card-active');
        } else {
            card.classList.remove('group-card-active');
        }
    });

    // Alternar visibilidad con exclusividad de acordeón
    if (index !== undefined && index !== null) {
        const body = document.getElementById('body-group-' + index);
        const estaCerrado = (!body || body.style.display === 'none' || body.style.display === '');
        if (estaCerrado) {
            cerrarTodosLosGruposExcepto(index);
            desplegarCriteriosGrupo(index, true);
        } else {
            desplegarCriteriosGrupo(index, false);
        }
    }

    // Sincronizar tarjeta de clase de hoy si coincide
    const classCards = document.querySelectorAll('.class-card');
    let tieneClaseHoy = false;

    classCards.forEach(card => {
        if (card.getAttribute('data-clave-grupo') === claveGrupo) {
            card.classList.add('class-card-active');
            tieneClaseHoy = true;
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            card.classList.remove('class-card-active');
        }
    });

    if (!tieneClaseHoy) {
        classCards.forEach(card => card.classList.remove('class-card-active'));
    }
}

/**
 * Cierra todos los acordeones de criterios excepto uno indicado
 */
function cerrarTodosLosGruposExcepto(exceptIndex) {
    const allBodies = document.querySelectorAll('.g-body');
    allBodies.forEach(body => {
        const idParts = body.id.split('body-group-');
        if (idParts.length === 2) {
            const idx = idParts[1];
            if (String(idx) !== String(exceptIndex)) {
                desplegarCriteriosGrupo(idx, false);
            }
        }
    });
}

/**
 * Control del acordeón de criterios de evaluación de un grupo
 */
function toggleGroup(event, index) {
    if (event) event.stopPropagation();

    const body = document.getElementById('body-group-' + index);
    const card = document.getElementById('group-card-' + index);
    if (!body) return;

    const estaCerrado = (body.style.display === 'none' || body.style.display === '');

    if (estaCerrado) {
        cerrarTodosLosGruposExcepto(index);
        desplegarCriteriosGrupo(index, true);
        if (card) {
            document.querySelectorAll('.group-progress-card').forEach(c => c.classList.remove('group-card-active'));
            card.classList.add('group-card-active');
            const claveGrupo = card.getAttribute('data-clave-grupo');
            if (claveGrupo) {
                sincronizarClaseHoy(claveGrupo);
            }
        }
    } else {
        desplegarCriteriosGrupo(index, false);
    }
}

/**
 * Forzar despliegue o cierre de criterios de un grupo
 */
function desplegarCriteriosGrupo(index, forzarAbrir = true) {
    const body = document.getElementById('body-group-' + index);
    const btn = document.getElementById('btn-toggle-' + index);
    if (!body || !btn) return;

    if (forzarAbrir) {
        body.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-chevron-up"></i>';
        btn.classList.add('g-toggle-btn-active');
        // Animación progresiva gradual de llenado de criterios y números
        animarCriteriosGrupo(body);
    } else {
        body.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-chevron-down"></i>';
        btn.classList.remove('g-toggle-btn-active');
        // Reiniciar barras para que al volver a abrir se llenen nuevamente
        resetearCriteriosGrupo(body);
    }
}

/**
 * Anima el llenado progresivo gradual de las barras de criterios y sus porcentajes
 */
function animarCriteriosGrupo(bodyElement) {
    if (!bodyElement) return;

    const fillBars = bodyElement.querySelectorAll('.anim-crit-fill');
    const percTexts = bodyElement.querySelectorAll('.anim-crit-perc');

    fillBars.forEach((bar, idx) => {
        const targetFill = parseInt(bar.getAttribute('data-target-fill') || '0', 10);
        bar.style.width = '0%';
        // Pequeño retardo escalonado para dar sensación viva
        setTimeout(() => {
            bar.style.width = targetFill + '%';
        }, 60 + (idx * 40));
    });

    percTexts.forEach((el, idx) => {
        const target = parseInt(el.getAttribute('data-target-perc') || '0', 10);
        el.innerText = '0%';
        animarContadorNumero(el, 0, target, 650 + (idx * 50));
    });
}

/**
 * Restablece las barras de criterios a 0 para permitir su re-animación
 */
function resetearCriteriosGrupo(bodyElement) {
    if (!bodyElement) return;
    const fillBars = bodyElement.querySelectorAll('.anim-crit-fill');
    fillBars.forEach(bar => {
        bar.style.width = '0%';
    });
}

/**
 * Anima un contador numérico de 0 hasta target con efecto ease-out
 */
function animarContadorNumero(element, start, end, duration = 800) {
    if (!element || start === end) {
        if (element) element.innerText = end + '%';
        return;
    }

    const startTime = performance.now();
    const step = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        // Easing cubic ease-out
        const ease = 1 - Math.pow(1 - progress, 3);
        const currentVal = Math.round(start + (end - start) * ease);
        element.innerText = currentVal + '%';

        if (progress < 1) {
            requestAnimationFrame(step);
        } else {
            element.innerText = end + '%';
        }
    };
    requestAnimationFrame(step);
}

/**
 * Anima únicamente los porcentajes globales de las cabeceras al cargar la página por primera vez
 */
function animarPorcentajesIniciales() {
    const globalPercs = document.querySelectorAll('.animated-perc');
    globalPercs.forEach((el, idx) => {
        const target = parseInt(el.getAttribute('data-target-perc') || '0', 10);
        el.innerText = '0%';
        setTimeout(() => {
            animarContadorNumero(el, 0, target, 850);
        }, 120 + (idx * 70));
    });
}

/**
 * Resaltar clase de hoy por clave de grupo
 */
function sincronizarClaseHoy(claveGrupo) {
    const classCards = document.querySelectorAll('.class-card');
    classCards.forEach(card => {
        if (card.getAttribute('data-clave-grupo') === claveGrupo) {
            card.classList.add('class-card-active');
        } else {
            card.classList.remove('class-card-active');
        }
    });
}

/**
 * Inicialización de la máscara de scroll reactiva para desvanecimiento arriba/abajo
/**
 * Utilidad universal de scroll reactivo con desvanecido (fade mask).
 * Detecta cualquier contenedor con la clase .custom-scroll-mask (o selector provisto)
 * y alterna .mask-both según el desplazamiento independiente de cada elemento.
 *
 * @param {string|HTMLElement|null} target
 * @param {number} threshold
 */
function aplicarMascaraScrollUniversal(target = '.custom-scroll-mask', threshold = 12) {
    const elementos = (typeof target === 'string') 
        ? document.querySelectorAll(target) 
        : (target instanceof HTMLElement ? [target] : []);

    elementos.forEach(el => {
        if (!el || el.dataset.maskInitialized === 'true') return;
        el.dataset.maskInitialized = 'true';

        const updateMask = () => {
            if (el.scrollTop > threshold) {
                el.classList.add('mask-both');
            } else {
                el.classList.remove('mask-both');
            }
        };

        el.addEventListener('scroll', updateMask, { passive: true });
        updateMask();
    });
}

/**
 * Sincronizar el estado inicial al cargar la página:
 * Retroilumina la primera clase y su tarjeta de grupo correspondiente,
 * pero mantiene todos los grupos cerrados para que se aprecie la animación del porcentaje global.
 */
function sincronizarWidgetsIniciales() {
    // Inicializa la máscara de desvanecimiento para todos los scrollables del dashboard
    aplicarMascaraScrollUniversal('.custom-scroll-mask');

    // Animación de los porcentajes globales de los grupos solo al cargar la página
    animarPorcentajesIniciales();

    const primerClase = document.querySelector('.class-card[data-clave-grupo]');
    if (primerClase) {
        const clave = primerClase.getAttribute('data-clave-grupo');
        // Resaltar tarjeta de clase
        primerClase.classList.add('class-card-active');
        
        // Resaltar tarjeta de grupo sin desplegar el acordeón
        const targetGroup = document.querySelector(`.group-progress-card[data-clave-grupo="${clave}"]`);
        if (targetGroup) {
            targetGroup.classList.add('group-card-active');
        }
    } else {
        const primerGrupo = document.querySelector('.group-progress-card[data-group-index]');
        if (primerGrupo) {
            primerGrupo.classList.add('group-card-active');
        }
    }
}

// ==========================================
// 1. CARGA Y SINCRONIZACIÓN DE AVISOS
// ==========================================
function cargarAvisos() {
    const tbody = document.getElementById('tablaAvisosBody');
    const resumen = document.getElementById('lista_resumen_avisos');

    fetch('avisos_api', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
        body: `action=get_avisos&csrf_token=${encodeURIComponent(csrfToken)}` 
    })
    .then(res => res.json())
    .then(data => {
        if (!Array.isArray(data)) return;
        avisosActuales = data;

        // Contar avisos no finalizados
        let contadorActivos = 0;
        avisosActuales.forEach(a => {
            if (a.estatus !== 'FINALIZADA') contadorActivos++;
        });

        // Actualizar métrica en el dashboard
        actualizarContadorEstadisticas(contadorActivos);

        // Renderizar vistas
        renderResumenAvisos();
        renderTablaAvisos(filtroActual);
    })
    .catch(err => {
        console.error('Error al cargar avisos:', err);
        if (resumen && resumen.children.length === 0) {
            resumen.innerHTML = '<p style="color: var(--text-muted); font-size: 0.9rem;">No se pudieron cargar los avisos.</p>';
        }
    });
}

function actualizarContadorEstadisticas(numero) {
    const statAvisos = document.getElementById('stat_avisos_activos');
    if (statAvisos) {
        statAvisos.innerText = numero;
    }
    const statBoxes = document.querySelectorAll('.stat-box');
    statBoxes.forEach(box => {
        if (box.innerHTML.includes('Avisos Activos')) {
            const numDiv = box.querySelector('.number');
            if (numDiv) numDiv.innerText = numero;
        }
    });
}

// ==========================================
// 2. RENDERIZADO DEL WIDGET DASHBOARD
// ==========================================
function renderResumenAvisos() {
    const resumen = document.getElementById('lista_resumen_avisos');
    if (!resumen) return;

    const noFinalizados = avisosActuales.filter(a => a.estatus !== 'FINALIZADA');

    if (noFinalizados.length === 0) {
        resumen.innerHTML = `
            <div style="text-align: center; padding: 25px 10px; color: var(--text-muted);">
                <i class="far fa-bell-slash" style="font-size: 2.2rem; opacity: 0.4; margin-bottom: 10px; display: block;"></i>
                <p style="margin: 0 0 10px 0; font-size: 0.95rem; font-weight: 500;">No tienes avisos activos en este momento.</p>
                <button type="button" onclick="abrirFormularioAviso()" class="btn-widget-action btn-widget-nuevo" style="font-size: 0.8rem; padding: 5px 12px;">
                    <i class="fas fa-plus"></i> Crear Primer Aviso
                </button>
            </div>
        `;
        return;
    }

    const mostrados = noFinalizados.slice(0, 3);
    let html = '';

    mostrados.forEach(a => {
        const esTarea = a.tipo === 'ASIGNACION';
        const claseTipo = esTarea ? 'tipo-tarea' : '';
        const claseSubBadge = esTarea ? 'neon-sub-tarea' : 'neon-sub-aviso';
        const iconoTipo = esTarea ? '<i class="fas fa-tasks"></i>' : '<i class="fas fa-bullhorn"></i>';
        const nombreTipo = esTarea ? 'Tarea' : 'Aviso';

        let pillClass = 'pill-activa';
        let pillIcon = '<i class="fas fa-circle"></i>';
        if (a.estatus === 'PRÓXIMA') { pillClass = 'pill-proxima'; pillIcon = '<i class="fas fa-clock"></i>'; }
        else if (a.estatus === 'PENDIENTE') { pillClass = 'pill-pendiente'; pillIcon = '<i class="fas fa-hourglass-start"></i>'; }

        const cartelBadge = a.imagen ? '<span class="cartel-indicator" title="Contiene imagen o cartel"><i class="fas fa-image"></i> Cartel</span>' : '';
        const fechaCierreTexto = a.fecha_fin ? `<i class="far fa-calendar-alt"></i> Cierre: <strong>${formatearFechaAviso(a.fecha_fin)}</strong>` : '<i class="far fa-calendar-check"></i> Permanente';

        const nivelRomano = a.materia_nivel ? ' ' + convertirNivelRomano(parseInt(a.materia_nivel)) : '';
        const grupoTexto = `${escapeHtmlAviso(a.materia_nombre)}${nivelRomano}`;

        html += `
            <div class="prof-aviso-card ${claseTipo}" onclick="abrirDetalleAviso(${a.aviso_id})" title="Clic para ver detalles y gestionar">
                <div class="prof-aviso-top">
                    <span class="notif-sub-badge ${claseSubBadge}">
                        ${iconoTipo} ${grupoTexto}
                    </span>
                    <div style="display: flex; align-items: center; gap: 6px;">
                        ${cartelBadge}
                        <span class="status-pill ${pillClass}">${pillIcon} ${a.estatus}</span>
                    </div>
                </div>
                <div class="prof-aviso-title">${escapeHtmlAviso(a.titulo)}</div>
                <div class="prof-aviso-snippet">${escapeHtmlAviso(a.descripcion)}</div>
                <div class="prof-aviso-footer">
                    <span><i class="fas fa-barcode"></i> NRC: ${escapeHtmlAviso(a.nrc)}</span>
                    <span>${fechaCierreTexto}</span>
                </div>
            </div>
        `;
    });

    const restantes = noFinalizados.length - mostrados.length;
    if (restantes > 0) {
        html += `
            <div style="text-align: center; margin-top: 10px;">
                <button type="button" onclick="abrirPanelAvisos()" style="background: none; border: none; color: var(--udg-blue); font-weight: 700; cursor: pointer; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-eye"></i> Ver ${restantes} comunicado(s) más...
                </button>
            </div>
        `;
    }

    resumen.innerHTML = html;
}

// ==========================================
// 3. RENDERIZADO DEL MODAL DE GESTIÓN (TABLA)
// ==========================================
function renderTablaAvisos(filtro = 'TODOS', busqueda = '') {
    const tbody = document.getElementById('tablaAvisosBody');
    if (!tbody) return;

    actualizarContadoresFiltros();

    let avisosFiltrados = avisosActuales.slice();

    // Filtro por estatus
    if (filtro !== 'TODOS') {
        avisosFiltrados = avisosFiltrados.filter(a => a.estatus === filtro);
    }

    // Filtro por búsqueda
    if (busqueda && busqueda.trim() !== '') {
        const q = busqueda.trim().toLowerCase();
        avisosFiltrados = avisosFiltrados.filter(a => 
            (a.titulo && a.titulo.toLowerCase().includes(q)) ||
            (a.descripcion && a.descripcion.toLowerCase().includes(q)) ||
            (a.materia_nombre && a.materia_nombre.toLowerCase().includes(q)) ||
            (a.nrc && a.nrc.toString().includes(q)) ||
            (a.clave_grupo && a.clave_grupo.toLowerCase().includes(q))
        );
    }

    if (avisosFiltrados.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <i class="fas fa-folder-open" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 10px; display: block;"></i>
                    No se encontraron comunicados con el criterio seleccionado.
                </td>
            </tr>
        `;
        return;
    }

    let rowsHtml = '';
    avisosFiltrados.forEach(a => {
        const esTarea = a.tipo === 'ASIGNACION';
        const iconoTipo = esTarea ? '📝' : '📢';
        const claseSubBadge = esTarea ? 'neon-sub-tarea' : 'neon-sub-aviso';
        const cartelIcon = a.imagen ? '<span class="cartel-indicator" style="margin-left:6px;"><i class="fas fa-image"></i> Cartel</span>' : '';

        let pillClass = 'pill-activa';
        if (a.estatus === 'PRÓXIMA') pillClass = 'pill-proxima';
        else if (a.estatus === 'PENDIENTE') pillClass = 'pill-pendiente';
        else if (a.estatus === 'FINALIZADA') pillClass = 'pill-finalizada';

        const nivelRomano = a.materia_nivel ? ' ' + convertirNivelRomano(parseInt(a.materia_nivel)) : '';
        const grupoTexto = `${escapeHtmlAviso(a.materia_nombre)}${nivelRomano} (NRC: ${escapeHtmlAviso(a.nrc)})`;

        // Botones de ciclo de vida
        let btnPublicar = (a.estatus === 'PENDIENTE' || a.estatus === 'PRÓXIMA') 
            ? `<button type="button" onclick="publicarAviso(${a.aviso_id})" class="action-btn publish-btn" title="Publicar Ahora"><i class="fas fa-play"></i></button>` : '';

        let btnFinalizar = (a.estatus === 'ACTIVA') 
            ? `<button type="button" onclick="finalizarAviso(${a.aviso_id})" class="action-btn finish-btn" title="Finalizar / Cerrar Ahora"><i class="fas fa-stop"></i></button>` : '';

        const inicioFormateado = formatearFechaAviso(a.fecha_inicio || a.fecha_creacion);
        const finFormateado = a.fecha_fin ? formatearFechaAviso(a.fecha_fin) : '<span style="color:var(--text-muted); font-style:italic;">Permanente</span>';

        rowsHtml += `
            <tr>
                <td style="white-space: nowrap;">
                    <span class="notif-sub-badge ${claseSubBadge}">
                        ${iconoTipo} ${esTarea ? 'Tarea' : 'Aviso'}
                    </span>
                </td>
                <td>
                    <div style="font-weight: 700; color: var(--text-dark);">${escapeHtmlAviso(a.titulo)} ${cartelIcon}</div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        ${escapeHtmlAviso(a.descripcion)}
                    </div>
                </td>
                <td>
                    <div style="font-size: 0.88rem; font-weight: 600;">${grupoTexto}</div>
                </td>
                <td style="font-size: 0.82rem; white-space: nowrap;">
                    <div><i class="far fa-calendar-check" style="color: #10b981;"></i> ${inicioFormateado}</div>
                    <div><i class="far fa-calendar-times" style="color: #ef4444;"></i> ${finFormateado}</div>
                </td>
                <td style="white-space: nowrap;">
                    <span class="status-pill ${pillClass}">${a.estatus}</span>
                </td>
                <td style="white-space: nowrap;">
                    <button type="button" onclick="abrirDetalleAviso(${a.aviso_id})" class="action-btn view-btn" title="Ver Detalles"><i class="fas fa-eye"></i></button>
                    ${btnPublicar}
                    ${btnFinalizar}
                    <button type="button" onclick="editarAviso(${a.aviso_id})" class="action-btn edit-btn" title="Editar"><i class="fas fa-pen"></i></button>
                    <button type="button" onclick="borrarAviso(${a.aviso_id})" class="action-btn delete-btn" title="Eliminar"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = rowsHtml;
}

function actualizarContadoresFiltros() {
    let cantTodos = avisosActuales.length;
    let cantActivas = 0, cantProximas = 0, cantFinalizadas = 0;

    avisosActuales.forEach(a => {
        if (a.estatus === 'ACTIVA') cantActivas++;
        else if (a.estatus === 'PRÓXIMA' || a.estatus === 'PENDIENTE') cantProximas++;
        else if (a.estatus === 'FINALIZADA') cantFinalizadas++;
    });

    const cTodos = document.getElementById('count_filtro_todos');
    const cActivas = document.getElementById('count_filtro_activas');
    const cProximas = document.getElementById('count_filtro_proximas');
    const cFinalizadas = document.getElementById('count_filtro_finalizadas');

    if (cTodos) cTodos.innerText = cantTodos;
    if (cActivas) cActivas.innerText = cantActivas;
    if (cProximas) cProximas.innerText = cantProximas;
    if (cFinalizadas) cFinalizadas.innerText = cantFinalizadas;
}

function setFiltroAvisos(filtro, btnEl) {
    filtroActual = filtro;
    document.querySelectorAll('.filtro-tab-btn').forEach(btn => btn.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');

    const searchInput = document.getElementById('filtroAvisosBusqueda');
    const busqueda = searchInput ? searchInput.value : '';
    renderTablaAvisos(filtroActual, busqueda);
}

function filtrarAvisosTabla() {
    const searchInput = document.getElementById('filtroAvisosBusqueda');
    const busqueda = searchInput ? searchInput.value : '';
    renderTablaAvisos(filtroActual, busqueda);
}

// ==========================================
// 4. MODAL DE DETALLE DE AVISO
// ==========================================
function abrirDetalleAviso(id) {
    const a = avisosActuales.find(x => x.aviso_id == id);
    if (!a) return;

    const modal = document.getElementById('modalDetalleAvisoProfesor');
    if (!modal) return;

    const esTarea = a.tipo === 'ASIGNACION';
    const claseSubBadge = esTarea ? 'neon-sub-tarea' : 'neon-sub-aviso';
    const iconoTipo = esTarea ? '📝' : '📢';
    const nombreTipo = esTarea ? 'Tarea / Asignación' : 'Aviso General';

    let pillClass = 'pill-activa';
    if (a.estatus === 'PRÓXIMA') pillClass = 'pill-proxima';
    else if (a.estatus === 'PENDIENTE') pillClass = 'pill-pendiente';
    else if (a.estatus === 'FINALIZADA') pillClass = 'pill-finalizada';

    const nivelRomano = a.materia_nivel ? ' ' + convertirNivelRomano(parseInt(a.materia_nivel)) : '';

    document.getElementById('detAvisoBadgeTipo').className = `notif-sub-badge ${claseSubBadge}`;
    document.getElementById('detAvisoBadgeTipo').innerHTML = `${iconoTipo} ${nombreTipo}`;
    document.getElementById('detAvisoStatus').className = `status-pill ${pillClass}`;
    document.getElementById('detAvisoStatus').innerText = a.estatus;
    document.getElementById('detAvisoTitulo').innerText = a.titulo;
    document.getElementById('detAvisoClase').innerText = `${a.materia_nombre}${nivelRomano} (NRC: ${a.nrc})`;
    document.getElementById('detAvisoInicio').innerText = formatearFechaAviso(a.fecha_inicio || a.fecha_creacion);
    document.getElementById('detAvisoFin').innerText = a.fecha_fin ? formatearFechaAviso(a.fecha_fin) : 'Permanente / Sin fecha límite';
    document.getElementById('detAvisoCuerpo').innerText = a.descripcion;

    const imgContainer = document.getElementById('detAvisoImgContainer');
    const imgEl = document.getElementById('detAvisoImg');
    if (a.imagen) {
        imgEl.src = '../' + a.imagen;
        imgContainer.style.display = 'block';
    } else {
        imgContainer.style.display = 'none';
        imgEl.src = '';
    }

    // Botón de edición directa
    const btnEditar = document.getElementById('btnDetalleEditarAviso');
    if (btnEditar) {
        btnEditar.onclick = () => {
            cerrarDetalleAviso();
            editarAviso(a.aviso_id);
        };
    }

    modal.style.display = 'flex';
}

function cerrarDetalleAviso() {
    const modal = document.getElementById('modalDetalleAvisoProfesor');
    if (modal) modal.style.display = 'none';
}

// ==========================================
// 5. APERTURA Y CONTROL DE MODALES
// ==========================================
function abrirPanelAvisos() {
    const modal = document.getElementById('modalGestionAvisos');
    if (modal) {
        modal.style.display = 'flex';
        renderTablaAvisos(filtroActual);
    }
}

function cerrarPanelAvisos() {
    const modal = document.getElementById('modalGestionAvisos');
    if (modal) modal.style.display = 'none';
}

function abrirFormularioAviso(id = null) {
    const form = document.getElementById('formAviso');
    if (!form) return;

    form.reset();
    document.getElementById('aviso_id').value = '';
    imagenMarcadaParaEliminar = false;
    document.getElementById('input_eliminar_imagen').value = '0';
    quitarImagenPreviewVisual();

    if (id === null) {
        document.getElementById('tituloModalAviso').innerText = 'Nuevo Comunicado';
        document.getElementById('subtituloModalAviso').innerText = 'Emite un aviso informativo o asigna una tarea a un grupo';
        document.querySelector('input[name="tipo"][value="AVISO"]').checked = true;
        actualizarEstiloRadioTipo();

        // Fechas por defecto: ahora
        const ahoraLocal = new Date();
        ahoraLocal.setMinutes(ahoraLocal.getMinutes() - ahoraLocal.getTimezoneOffset());
        document.getElementById('aviso_inicio').value = ahoraLocal.toISOString().slice(0, 16);

        // Sin expiración por defecto en avisos generales
        document.getElementById('chk_sin_expiracion').checked = false;
        document.getElementById('aviso_fin').disabled = false;
        document.getElementById('aviso_fin').value = '';

        cargarGruposSelect();
    } else {
        editarAviso(id);
        return;
    }

    document.getElementById('modalFormAviso').style.display = 'flex';
}

function cerrarFormularioAviso() {
    const modal = document.getElementById('modalFormAviso');
    if (modal) modal.style.display = 'none';
}

function toggleSinExpiracion() {
    const chk = document.getElementById('chk_sin_expiracion');
    const inputFin = document.getElementById('aviso_fin');
    if (!chk || !inputFin) return;

    if (chk.checked) {
        inputFin.disabled = true;
        inputFin.value = '';
        inputFin.removeAttribute('required');
    } else {
        inputFin.disabled = false;
    }
}

function actualizarEstiloRadioTipo() {
    const radios = document.querySelectorAll('input[name="tipo"]');
    radios.forEach(r => {
        const card = r.closest('.radio-card');
        if (card) {
            if (r.checked) card.classList.add('selected');
            else card.classList.remove('selected');
        }
    });
}

// Cargar opciones en el select de clases activas
function cargarGruposSelect(nrc_seleccionado = '') {
    const select = document.getElementById('aviso_nrc');
    if (!select) return;

    select.innerHTML = '<option value="">-- Cargando grupos disponibles... --</option>';

    fetch('avisos_api', { 
        method: 'POST', 
        headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
        body: `action=get_grupos_activos&csrf_token=${encodeURIComponent(csrfToken)}` 
    })
    .then(res => res.json())
    .then(data => {
        if (!Array.isArray(data) || data.length === 0) {
            select.innerHTML = '<option value="">No tienes grupos activos asignados</option>';
            return;
        }

        let options = '<option value="">-- Selecciona una Clase / Grupo --</option>';
        data.forEach(g => {
            const sel = (g.nrc == nrc_seleccionado) ? 'selected' : '';
            const nivelRomano = g.nivel ? ' ' + convertirNivelRomano(parseInt(g.nivel)) : '';
            const mod = g.modalidad ? ` (${g.modalidad})` : '';
            options += `<option value="${g.nrc}" ${sel}>${escapeHtmlAviso(g.nombre)}${nivelRomano}${mod} — NRC: ${g.nrc} [${escapeHtmlAviso(g.clave_grupo)}]</option>`;
        });
        select.innerHTML = options;
    })
    .catch(() => {
        select.innerHTML = '<option value="">Error al cargar grupos</option>';
    });
}

// ==========================================
// 6. GESTIÓN Y PREVIEW DE IMAGEN
// ==========================================
function previsualizarImagenAviso(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Validación ligera en cliente
    const formatosValidos = ['image/jpeg', 'image/png', 'image/webp'];
    if (!formatosValidos.includes(file.type)) {
        Swal.fire('Formato no permitido', 'Por favor selecciona una imagen JPG, PNG o WEBP.', 'warning');
        event.target.value = '';
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        Swal.fire('Imagen muy pesada', 'El archivo no debe superar 5MB.', 'warning');
        event.target.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        const previewImg = document.getElementById('img_preview_aviso');
        const previewCard = document.getElementById('preview_card_aviso');
        const previewNombre = document.getElementById('preview_nombre_archivo');

        if (previewImg) previewImg.src = e.target.result;
        if (previewNombre) previewNombre.innerText = file.name;
        if (previewCard) previewCard.style.display = 'flex';

        imagenMarcadaParaEliminar = false;
        document.getElementById('input_eliminar_imagen').value = '0';
    };
    reader.readAsDataURL(file);
}

function quitarImagenAviso() {
    const inputImg = document.getElementById('aviso_imagen');
    if (inputImg) inputImg.value = '';

    quitarImagenPreviewVisual();
    imagenMarcadaParaEliminar = true;
    document.getElementById('input_eliminar_imagen').value = '1';
}

function quitarImagenPreviewVisual() {
    const previewCard = document.getElementById('preview_card_aviso');
    const previewImg = document.getElementById('img_preview_aviso');
    const previewNombre = document.getElementById('preview_nombre_archivo');

    if (previewCard) previewCard.style.display = 'none';
    if (previewImg) previewImg.src = '';
    if (previewNombre) previewNombre.innerText = '';
}

// ==========================================
// 7. EDICIÓN, GUARDADO Y ACCIONES CRUD
// ==========================================
function editarAviso(id) {
    const a = avisosActuales.find(x => x.aviso_id == id);
    if (!a) return;

    const form = document.getElementById('formAviso');
    if (!form) return;

    form.reset();
    document.getElementById('aviso_id').value = a.aviso_id;
    document.getElementById('tituloModalAviso').innerText = 'Editar Comunicado';
    document.getElementById('subtituloModalAviso').innerText = 'Modifica los detalles, indicaciones o fechas del comunicado';

    // Tipo radio
    const radioTipo = document.querySelector(`input[name="tipo"][value="${a.tipo}"]`);
    if (radioTipo) radioTipo.checked = true;
    actualizarEstiloRadioTipo();

    document.getElementById('aviso_titulo').value = a.titulo;
    document.getElementById('aviso_descripcion').value = a.descripcion;
    document.getElementById('aviso_inicio').value = a.fecha_inicio_input;

    // Fecha fin y expiración
    const chkSinExp = document.getElementById('chk_sin_expiracion');
    const inputFin = document.getElementById('aviso_fin');

    if (!a.fecha_fin) {
        chkSinExp.checked = true;
        inputFin.disabled = true;
        inputFin.value = '';
    } else {
        chkSinExp.checked = false;
        inputFin.disabled = false;
        inputFin.value = a.fecha_fin_input;
    }

    // Imagen previa
    imagenMarcadaParaEliminar = false;
    document.getElementById('input_eliminar_imagen').value = '0';
    if (a.imagen) {
        const previewCard = document.getElementById('preview_card_aviso');
        const previewImg = document.getElementById('img_preview_aviso');
        const previewNombre = document.getElementById('preview_nombre_archivo');

        if (previewImg) previewImg.src = '../' + a.imagen;
        if (previewNombre) previewNombre.innerText = 'Cartel actual adjunto';
        if (previewCard) previewCard.style.display = 'flex';
    } else {
        quitarImagenPreviewVisual();
    }

    cargarGruposSelect(a.nrc);
    document.getElementById('modalFormAviso').style.display = 'flex';
}

function guardarAviso(e) {
    e.preventDefault();

    const form = document.getElementById('formAviso');
    const btnSubmit = document.getElementById('btnSubmitFormAviso');
    const nrc = document.getElementById('aviso_nrc').value;
    const titulo = document.getElementById('aviso_titulo').value.trim();
    const descripcion = document.getElementById('aviso_descripcion').value.trim();

    if (!nrc) {
        Swal.fire('Atención', 'Por favor selecciona el grupo al que va dirigido el aviso.', 'warning');
        return;
    }
    if (!titulo || !descripcion) {
        Swal.fire('Atención', 'Por favor ingresa el título y las indicaciones del aviso.', 'warning');
        return;
    }

    const formData = new FormData(form);
    formData.append('action', 'save_aviso');
    formData.append('csrf_token', csrfToken);

    if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    }

    fetch('avisos_api', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fas fa-paper-plane"></i> Guardar Comunicado';
        }

        if (data.status === 'success') {
            cerrarFormularioAviso();
            cargarAvisos();
            Swal.fire({
                title: '¡Guardado!',
                text: data.message || 'El comunicado ha sido guardado exitosamente.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', data.message || 'No se pudo guardar el comunicado.', 'error');
        }
    })
    .catch(err => {
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fas fa-paper-plane"></i> Guardar Comunicado';
        }
        console.error(err);
        Swal.fire('Error', 'Ocurrió un error al procesar la solicitud.', 'error');
    });
}

function publicarAviso(id) {
    Swal.fire({
        title: '¿Publicar ahora?',
        text: 'El comunicado se activará y será visible para los alumnos de inmediato.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-play"></i> Sí, publicar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('avisos_api', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                body: `action=publicar_manual&aviso_id=${id}&csrf_token=${encodeURIComponent(csrfToken)}` 
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    cargarAvisos();
                    Swal.fire('¡Publicado!', data.message || 'El aviso ahora está activo.', 'success');
                } else {
                    Swal.fire('Error', data.message || 'No se pudo publicar.', 'error');
                }
            });
        }
    });
}

function finalizarAviso(id) {
    Swal.fire({
        title: '¿Finalizar comunicado?',
        text: 'El aviso se cerrará y dejará de mostrarse como activo para los alumnos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ea580c',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-stop"></i> Sí, finalizar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('avisos_api', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                body: `action=finalizar_manual&aviso_id=${id}&csrf_token=${encodeURIComponent(csrfToken)}` 
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    cargarAvisos();
                    Swal.fire('¡Finalizado!', data.message || 'El aviso ha concluido.', 'success');
                } else {
                    Swal.fire('Error', data.message || 'No se pudo finalizar.', 'error');
                }
            });
        }
    });
}

function borrarAviso(id) {
    Swal.fire({
        title: '¿Eliminar comunicado?',
        text: 'Esta acción no se puede deshacer. Se eliminarán los archivos adjuntos relacionados.',
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-trash"></i> Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('avisos_api', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                body: `action=delete_aviso&aviso_id=${id}&csrf_token=${encodeURIComponent(csrfToken)}` 
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    cargarAvisos();
                    Swal.fire('¡Eliminado!', data.message || 'El aviso ha sido borrado.', 'success');
                } else {
                    Swal.fire('Error', data.message || 'No se pudo eliminar.', 'error');
                }
            });
        }
    });
}

// ==========================================
// 8. HELPERS DE FORMATO
// ==========================================
function formatearFechaAviso(fechaStr) {
    if (!fechaStr) return '';
    try {
        const d = new Date(fechaStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return fechaStr;

        return d.toLocaleDateString('es-MX', {
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return fechaStr;
    }
}

function convertirNivelRomano(nivel) {
    const mapa = {1:'I', 2:'II', 3:'III', 4:'IV', 5:'V', 6:'VI', 7:'VII', 8:'VIII', 9:'IX', 10:'X'};
    return mapa[nivel] || nivel;
}

function escapeHtmlAviso(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}