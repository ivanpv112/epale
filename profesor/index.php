<?php
require_once 'index_logic.php';

/**
 * Declaración de variables importadas desde index_logic.php
 * para evitar falsos positivos de "Undefined variable" en el IDE.
 * 
 * @var string $nombre_profesor
 * @var int $total_alumnos
 * @var int $total_grupos
 * @var int $total_criterios_pendientes
 * @var int $total_avisos_activos
 * @var array $avisos_activos_prof
 * @var array $grupos
 * @var array $clases_hoy
 * @var string $fecha_texto_es
 */
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Profesor | e-PALE</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profesor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
    <?php include 'menu_profesor.php'; ?>
    <main class="main-content">
        <div class="welcome-banner">
            <h2><i class="fas <?php echo $icono_saludo; ?>" style="color: <?php echo $color_icono_saludo; ?>; margin-right: 6px;"></i> <?php echo $saludo_tiempo; ?>!</h2>
            <h1>Prof. <?php echo htmlspecialchars($nombre_profesor); ?></h1>
        </div>

        <!-- TARJETAS DE ESTADÍSTICAS (KPIs) -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-icon-wrapper"><i class="fas fa-users"></i></div>
                <div>
                    <div class="number"><?php echo $total_alumnos; ?></div>
                    <div class="label">Alumnos Totales</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon-wrapper"><i class="fas fa-chalkboard-teacher"></i></div>
                <div>
                    <div class="number"><?php echo $total_grupos; ?></div>
                    <div class="label">Grupos Activos</div>
                </div>
            </div>
            <div class="stat-box" onclick="abrirPanelAvisos()" style="cursor: pointer;" title="Clic para gestionar avisos y tareas">
                <div class="stat-icon-wrapper"><i class="fas fa-bullhorn"></i></div>
                <div>
                    <div class="number" id="stat_avisos_activos"><?php echo $total_avisos_activos; ?></div>
                    <div class="label">Avisos Activos</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon-wrapper"><i class="far fa-bell"></i></div>
                <div>
                    <div class="number"><?php echo $total_criterios_pendientes; ?></div>
                    <div class="label" style="text-transform: none; letter-spacing: 0;">Criterios pendientes</div>
                </div>
            </div>
        </div>

        <div class="main-grid">

            <!-- CONTENEDOR IZQUIERDO: MIS GRUPOS Y CRITERIOS -->
            <div class="dashboard-main-column">
                <div style="display:flex; align-items:center; gap: 10px; margin-bottom: 5px;">
                    <h3 class="card-title" style="margin:0;"><i class="fas fa-book-open"></i> Mis Grupos</h3>
                    <span style="color:var(--text-muted); font-size:0.9rem; margin-top:2px;">— progreso de calificación por criterio</span>
                </div>

                <div class="g-legend">
                    <span><i class="fas fa-comments" style="color: #00a859;"></i> Exámenes Orales</span>
                    <span><i class="fas fa-file-signature" style="color: #f59e0b;"></i> Proyecto Escrito</span>
                    <span><i class="fas fa-hand-paper" style="color: #00a3c4;"></i> Participación</span>
                </div>

                <!-- LISTA DE GRUPOS CON CONTENEDOR LIMITADO Y DESVANECIDO -->
                <?php if ($total_grupos > 0): ?>
                    <div class="grupos-scroll-wrapper" id="gruposScrollWrapper">
                        <div class="grupos-scroll-list custom-scroll-mask" id="gruposScrollList">
                            <?php foreach ($grupos as $index => $g): ?>
                                <div class="group-progress-card"
                                    id="group-card-<?php echo $index; ?>"
                                    data-clave-grupo="<?php echo htmlspecialchars($g['clave_grupo']); ?>"
                                    data-group-index="<?php echo $index; ?>">

                                    <div class="g-header"
                                        onclick="seleccionarGrupoProfesor('<?php echo htmlspecialchars($g['clave_grupo']); ?>', <?php echo $index; ?>)"
                                        role="button"
                                        tabindex="0"
                                        title="Clic para desplegar criterios y sincronizar con clase de hoy"
                                        onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault(); seleccionarGrupoProfesor('<?php echo htmlspecialchars($g['clave_grupo']); ?>', <?php echo $index; ?>);}">
                                        <div class="g-header-flex">
                                            <div class="g-title-sec">
                                                <span class="g-badge" title="Código de Materia"><?php echo htmlspecialchars($g['materia_codigo'] ?: ($g['nrc_p'] ?: $g['nrc_v'])); ?></span>
                                                <span class="g-name"><?php echo htmlspecialchars($g['materia'] . ' ' . nivel_a_romano($g['nivel'])); ?></span>
                                            </div>
                                            <div class="g-stats-sec">
                                                <div class="g-perc-block">
                                                    <span class="g-perc animated-perc" data-target-perc="<?php echo (int)$g['progreso_global']; ?>" style="<?php echo ($g['progreso_global'] == 100) ? 'color: var(--success);' : (($g['progreso_global'] < 50) ? 'color: var(--warning);' : ''); ?>"><?php echo $g['progreso_global']; ?>%</span>
                                                    <span class="g-perc-label">calificado</span>
                                                </div>
                                                <a href="detalle_grupo.php?clave=<?php echo urlencode($g['clave_grupo']); ?>" class="btn-icon-grupo" onclick="event.stopPropagation();" title="Ir a Mis Grupos / Detalle del Grupo">
                                                    <i class="fas fa-chalkboard-teacher"></i>
                                                </a>
                                                <button type="button" class="g-toggle-btn" id="btn-toggle-<?php echo $index; ?>" onclick="event.stopPropagation(); toggleGroup(event, <?php echo $index; ?>);" title="Desplegar criterios"><i class="fas fa-chevron-down"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- CUERPO DESPLEGABLE: CRITERIOS DE EVALUACIÓN (VISTAZO RÁPIDO) -->
                                    <div class="g-body" id="body-group-<?php echo $index; ?>" style="padding-top: 15px;">
                                        <h4 class="g-criterios-title" style="margin-top: 0;"><i class="fas fa-tasks"></i> Progreso por Criterios de Evaluación</h4>
                                        <?php if (empty($g['criterios_progreso'])): ?>
                                            <p style="text-align:center; color:var(--text-muted); font-size:0.9rem;">Criterios no configurados para esta materia.</p>
                                        <?php else: ?>
                                            <?php foreach ($g['criterios_progreso'] as $crit): ?>
                                                <div class="crit-row">
                                                    <div class="crit-header">
                                                        <span class="crit-name" style="color: <?php echo $crit['color']; ?>;"><i class="<?php echo $crit['icono']; ?>"></i> <?php echo htmlspecialchars($crit['nombre']); ?></span>
                                                        <div class="crit-stats">
                                                            <span class="crit-count"><?php echo $crit['calificados']; ?> / <?php echo $crit['total']; ?> alumnos</span>
                                                            <span class="crit-perc anim-crit-perc" data-target-perc="<?php echo (int)$crit['porcentaje']; ?>" style="color: <?php echo $crit['color']; ?>;"><?php echo $crit['porcentaje']; ?>%</span>
                                                        </div>
                                                    </div>
                                                    <div class="crit-bar-bg">
                                                        <div class="crit-bar-fill anim-crit-fill" data-target-fill="<?php echo (int)$crit['porcentaje']; ?>" style="width: 0%; background-color: <?php echo $crit['color']; ?>;"></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="content-card empty-table-msg">
                        <i class="fas fa-chalkboard"></i>
                        <h3>No tienes grupos activos asignados</h3>
                        <p style="font-size: 0.9rem; margin-top: 10px;">Contacta a administración si crees que esto es un error.</p>
                    </div>
                <?php endif; ?>
            </div> <!-- FIN CONTENEDOR IZQUIERDO -->

            <!-- CONTENEDOR DERECHO: PRÓXIMAS CLASES ARRIBA Y AVISOS ABAJO -->
            <div class="dashboard-sidebar-column">
                <!-- 1. TARJETA DE CLASES DE HOY (SUPERIOR) -->
                <div class="content-card card-clases-widget">
                    <h3 class="card-title" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
                        <span><i class="far fa-calendar-check" style="color: #3ba5ff;"></i> Próximas Clases</span>
                        <?php if (count($clases_hoy) > 0): ?>
                            <span class="dash-badge-pill" style="background: rgba(59, 165, 255, 0.12); color: #3ba5ff; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600;"><?php echo count($clases_hoy); ?> sesión(es)</span>
                        <?php endif; ?>
                    </h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0; margin-bottom: 12px;">
                        <i class="far fa-calendar-alt"></i> <?php echo $fecha_texto_es; ?>
                    </p>
                    <?php if (count($clases_hoy) > 0): ?>
                        <div class="classes-scroll-wrapper" id="classesScrollWrapper">
                            <div class="classes-scroll-list custom-scroll-mask" id="classesScrollList">
                                <?php foreach ($clases_hoy as $c):
                                    $es_presencial = ($c['tipo'] === 'Presencial');
                                    $color_tipo = $es_presencial ? '#10b981' : '#3ba5ff';
                                    $icono_tipo = $es_presencial ? 'fa-building' : 'fa-laptop-house';
                                ?>
                                    <div class="class-card"
                                        data-clave-grupo="<?php echo htmlspecialchars($c['clave_grupo']); ?>"
                                        onclick="seleccionarClaseHoyProfesor('<?php echo htmlspecialchars($c['clave_grupo']); ?>')"
                                        title="Clic para seleccionar y desplegar criterios de este grupo"
                                        role="button"
                                        tabindex="0"
                                        onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault(); seleccionarClaseHoyProfesor('<?php echo htmlspecialchars($c['clave_grupo']); ?>');}">
                                        <div class="class-strip" style="background: <?php echo $color_tipo; ?>;"></div>
                                        <div class="class-info-flex">
                                            <div class="class-info-main">
                                                <div class="class-name"><?php echo htmlspecialchars($c['materia']); ?></div>
                                                <div class="class-room">
                                                    <i class="fas <?php echo $icono_tipo; ?>" style="color: <?php echo $color_tipo; ?>;"></i>
                                                    <?php echo htmlspecialchars($c['aula']); ?>
                                                    <span class="class-mod-badge" style="background: <?php echo $es_presencial ? 'rgba(16, 185, 129, 0.12)' : 'rgba(59, 165, 255, 0.12)'; ?>; color: <?php echo $color_tipo; ?>;"><?php echo htmlspecialchars($c['tipo']); ?></span>
                                                </div>
                                            </div>
                                            <div class="class-time"><?php echo date('H:i', strtotime($c['inicio'])) . ' - ' . date('H:i', strtotime($c['fin'])); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding: 25px 15px; color:var(--text-muted); margin: auto;">
                            <i class="fas fa-mug-hot" style="font-size: 2rem; color: var(--text-muted); margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                            ¡Sin clases programadas para hoy!
                        </div>
                    <?php endif; ?>

                    <!-- Botón para ver horario completo -->
                    <div class="card-footer-action" style="margin-top: 15px; padding-top: 12px; border-top: 1px solid rgba(128, 128, 128, 0.12); text-align: center;">
                        <button class="btn-dash-action" onclick="window.location.href='horario'">
                            <i class="far fa-calendar-check"></i> Ver Horario Completo
                        </button>
                    </div>
                </div>
            </div> <!-- FIN CONTENEDOR DERECHO -->

        </div> <!-- FIN MAIN GRID -->
    </main>

    <!-- MODAL 1: PANEL DE GESTIÓN DE AVISOS Y TAREAS -->
    <div id="modalGestionAvisos" class="modal-overlay">
        <div class="modal-content-lg">
            <div class="modal-header">
                <div>
                    <h2><i class="fas fa-bullhorn" style="color: #3ba5ff;"></i> Gestión de Avisos y Tareas</h2>
                    <p style="margin: 4px 0 0 0; font-size: 0.88rem; color: var(--text-muted);">Administra y supervisa los comunicados visibles para tus alumnos</p>
                </div>
                <button class="close-btn" onclick="cerrarPanelAvisos()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Barra de herramientas superior -->
                <div class="modal-toolbar-avisos">
                    <button type="button" onclick="abrirFormularioAviso()" class="btn-widget-action btn-widget-nuevo">
                        <i class="fas fa-plus"></i> Crear Nuevo Comunicado
                    </button>

                    <!-- Pestañas de filtrado rápido -->
                    <div class="filtro-tabs-container">
                        <button type="button" class="filtro-tab-btn active" onclick="setFiltroAvisos('TODOS', this)">
                            Todos (<span id="count_filtro_todos">0</span>)
                        </button>
                        <button type="button" class="filtro-tab-btn" onclick="setFiltroAvisos('ACTIVA', this)">
                            Activos (<span id="count_filtro_activas">0</span>)
                        </button>
                        <button type="button" class="filtro-tab-btn" onclick="setFiltroAvisos('PRÓXIMA', this)">
                            Próximos / Prog. (<span id="count_filtro_proximas">0</span>)
                        </button>
                        <button type="button" class="filtro-tab-btn" onclick="setFiltroAvisos('FINALIZADA', this)">
                            Finalizados (<span id="count_filtro_finalizadas">0</span>)
                        </button>
                    </div>

                    <!-- Buscador en tiempo real -->
                    <div class="search-box-modal">
                        <i class="fas fa-search" style="color: var(--text-muted);"></i>
                        <input type="text" id="filtroAvisosBusqueda" placeholder="Buscar por título o materia..." oninput="filtrarAvisosTabla()">
                    </div>
                </div>

                <!-- Tabla moderna de comunicados -->
                <div style="overflow-x: auto;">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Título y Contenido</th>
                                <th>Clase / Grupo</th>
                                <th>Período</th>
                                <th>Estatus</th>
                                <th style="text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaAvisosBody">
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 25px;">Cargando comunicados...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 2: FORMULARIO DE CREACIÓN / EDICIÓN -->
    <div id="modalFormAviso" class="modal-overlay" style="z-index: 3100;">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <div>
                    <h2 id="tituloModalAviso" style="margin: 0;">Nuevo Comunicado</h2>
                    <p id="subtituloModalAviso" style="margin: 4px 0 0 0; font-size: 0.85rem; color: var(--text-muted);">Emite un aviso informativo o asigna una tarea a un grupo</p>
                </div>
                <button class="close-btn" onclick="cerrarFormularioAviso()">&times;</button>
            </div>
            <form id="formAviso" onsubmit="guardarAviso(event)" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="aviso_id" name="aviso_id">
                    <input type="hidden" id="input_eliminar_imagen" name="eliminar_imagen" value="0">

                    <!-- Selector visual del tipo de comunicado -->
                    <div class="radio-card-group">
                        <label class="radio-card selected" onclick="actualizarEstiloRadioTipo()">
                            <input type="radio" name="tipo" value="AVISO" checked onchange="actualizarEstiloRadioTipo()">
                            <div>
                                <div class="radio-card-label">📢 Aviso General</div>
                                <div style="font-size: 0.78rem; color: var(--text-muted);">Información, cambios de aula o recordatorios</div>
                            </div>
                        </label>
                        <label class="radio-card" onclick="actualizarEstiloRadioTipo()">
                            <input type="radio" name="tipo" value="ASIGNACION" onchange="actualizarEstiloRadioTipo()">
                            <div>
                                <div class="radio-card-label">📝 Asignación / Tarea</div>
                                <div style="font-size: 0.78rem; color: var(--text-muted);">Actividades o entregables con fecha límite</div>
                            </div>
                        </label>
                    </div>

                    <!-- Grupo destinatario -->
                    <div style="margin-bottom: 16px;">
                        <label style="display:block; margin-bottom: 6px; font-weight: 700; font-size: 0.9rem;">
                            ¿A qué grupo va dirigido? <span style="color:#ef4444;">*</span>
                        </label>
                        <select id="aviso_nrc" name="nrc" required style="width:100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.92rem; box-sizing: border-box;"></select>
                    </div>

                    <!-- Título -->
                    <div style="margin-bottom: 16px;">
                        <label style="display:block; margin-bottom: 6px; font-weight: 700; font-size: 0.9rem;">
                            Título del comunicado <span style="color:#ef4444;">*</span>
                        </label>
                        <input type="text" id="aviso_titulo" name="titulo" required maxlength="150" placeholder="Ej. Cambio de aula para la sesión de mañana / Entrega de Tarea 1" style="width:100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.92rem; box-sizing: border-box;">
                    </div>

                    <!-- Descripción -->
                    <div style="margin-bottom: 16px;">
                        <label style="display:block; margin-bottom: 6px; font-weight: 700; font-size: 0.9rem;">
                            Indicaciones y detalles <span style="color:#ef4444;">*</span>
                        </label>
                        <textarea id="aviso_descripcion" name="descripcion" rows="4" required placeholder="Escribe aquí los detalles que verán los alumnos..." style="width:100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.92rem; box-sizing: border-box; font-family: inherit; resize: vertical;"></textarea>
                    </div>

                    <!-- Subida de Cartel o Imagen Opcional -->
                    <div style="margin-bottom: 18px;">
                        <label style="display:block; margin-bottom: 6px; font-weight: 700; font-size: 0.9rem;">
                            <i class="fas fa-image" style="color: #3ba5ff;"></i> Cartel o Imagen adjunta (Opcional)
                        </label>
                        <div class="image-upload-wrapper" onclick="document.getElementById('aviso_imagen').click()">
                            <input type="file" id="aviso_imagen" name="imagen" accept="image/jpeg,image/png,image/webp" style="display:none;" onchange="previsualizarImagenAviso(event)">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 1.8rem; color: #3ba5ff; margin-bottom: 6px; display: block;"></i>
                            <div style="font-size: 0.88rem; font-weight: 600; color: var(--text-dark);">Haz clic para seleccionar o cambiar imagen</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Formatos permitidos: JPG, PNG o WEBP (Máx. 5MB). Se optimizará a WebP automáticamente.</div>
                        </div>

                        <!-- Previsualización de imagen seleccionada -->
                        <div id="preview_card_aviso" class="image-preview-card" style="display: none;">
                            <img id="img_preview_aviso" src="" class="image-preview-thumb" alt="Vista previa">
                            <div>
                                <div id="preview_nombre_archivo" style="font-weight: 600; font-size: 0.85rem; color: var(--text-dark);">archivo.jpg</div>
                                <div style="font-size: 0.75rem; color: #10b981;"><i class="fas fa-check-circle"></i> Imagen lista para subir</div>
                            </div>
                            <button type="button" class="btn-remove-image" onclick="quitarImagenAviso()">
                                <i class="fas fa-trash"></i> Quitar
                            </button>
                        </div>
                    </div>

                    <!-- Fechas de Publicación y Vigencia -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display:block; margin-bottom: 6px; font-weight: 700; font-size: 0.88rem;">Fecha de Publicación</label>
                            <input type="datetime-local" id="aviso_inicio" name="fecha_inicio" required style="width:100%; padding: 9px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; box-sizing: border-box;">
                        </div>
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                <label style="font-weight: 700; font-size: 0.88rem; margin: 0;">Fecha Límite</label>
                                <label style="font-size: 0.75rem; font-weight: 600; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                    <input type="checkbox" id="chk_sin_expiracion" name="sin_expiracion" value="1" onchange="toggleSinExpiracion()"> Sin expiración
                                </label>
                            </div>
                            <input type="datetime-local" id="aviso_fin" name="fecha_fin" style="width:100%; padding: 9px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; box-sizing: border-box;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eef2f6;">
                    <button type="button" class="btn-cancel" onclick="cerrarFormularioAviso()" style="padding: 9px 18px; border: 1px solid #cbd5e1; background: transparent; border-radius: 8px; cursor: pointer; font-weight: 600; color: var(--text-dark);">
                        Cancelar
                    </button>
                    <button type="submit" id="btnSubmitFormAviso" class="btn-save" style="padding: 9px 20px; background: var(--udg-blue); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fas fa-paper-plane"></i> Guardar Comunicado
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../main_footer.php'; ?>

    <script>
        // VARIABLE GLOBAL DEL CSRF TOKEN
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";
    </script>
    <script src="../js/avisos_profesor.js?v=<?php echo time(); ?>"></script>
</body>

</html>