<?php
require_once 'mis_grupos_logic.php';

/**
 * Declaración de variables importadas desde mis_grupos_logic.php
 * para evitar falsos positivos de "Undefined variable" en el IDE.
 * 
 * @var array $grupos
 * @var array $ciclos_profesor
 * @var array|null $ciclo_seleccionado_obj
 * @var int $ciclo_filtro_id
 */
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Grupos | Portal Docente</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profesor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    
    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1 style="color: var(--udg-blue); font-size: 2.2rem; margin-bottom: 5px;"><i class="fas fa-chalkboard-teacher"></i> Mis Grupos</h1>
            <p style="color: var(--text-muted);">Selecciona una clase para ver la lista de alumnos y registrar calificaciones.</p>
        </div>

        <!-- Barra de herramientas: Buscador Inteligente y Selector de Ciclos -->
        <div class="search-toolbar">
            <div class="search-input-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Buscar por materia, NRC, salón o nivel...">
            </div>

            <!-- Botón Disparador del Modal Selector de Ciclos -->
            <button type="button" class="btn-selector-ciclo-trigger" onclick="abrirModalSelectorCiclos()" title="Cambiar ciclo escolar">
                <i class="fas fa-calendar-alt" style="color: var(--udg-blue); font-size: 1.05rem;"></i>
                <span>Ciclo: <b><?php echo htmlspecialchars($ciclo_seleccionado_obj['nombre'] ?? 'Seleccionar'); ?></b></span>
                <?php if (!empty($ciclo_seleccionado_obj['activo'])): ?>
                    <span class="neon-badge-activo">ACTIVO</span>
                <?php endif; ?>
                <i class="fas fa-chevron-down" style="font-size: 0.75rem; color: #64748b; margin-left: 2px;"></i>
            </button>
        </div>

        <div class="content-card table-card-container">
            <div class="table-responsive-wrapper">
                <table class="prof-table group-list-table">                    <thead>
                        <tr>
                            <th style="width: 140px;">Semestre</th>
                            <th>Materia y Nivel</th>
                            <th>Sesiones y Modalidades (NRC / Aula / Horario)</th>
                            <th style="text-align: center; width: 130px;">Estudiantes</th>
                        </tr>
                    </thead>
                    <tbody id="groupsTableBody">
                        <?php if (count($grupos) > 0): ?>
                            <?php foreach ($grupos as $g): 
                                $es_ciclo_pasado_grupo = ((int)($g['activo'] ?? 0) === 0 || $g['estado'] === 'CERRADO');
                                $esta_activa = ($g['activo'] == 1 && $g['estado'] == 'ACTIVO');
                                $estado_class = $esta_activa ? 'row-active' : 'row-inactive'; 
                                $romano = nivel_a_romano($g['nivel']);
                                $param_pasado = $es_ciclo_pasado_grupo ? '&pasado=1' : '';
                            ?>
                                <tr class="group-row <?php echo $estado_class; ?>" onclick="window.location.href='detalle_grupo?clave=<?php echo urlencode($g['clave_grupo']) . $param_pasado; ?>'">
                                    
                                    <td class="col-semester" style="vertical-align: middle;">
                                        <div class="semester-title"><?php echo htmlspecialchars($g['ciclo']); ?></div>
                                    </td>
                                    
                                    <td class="col-subject" style="vertical-align: middle;">
                                        <div class="g-subject-grid">
                                            <span class="g-badge col-cod" style="padding: 4px 8px; font-size: 0.8rem;" title="Código de Materia"><?php echo htmlspecialchars($g['materia_codigo'] ?: ($g['nrc_p'] ?: $g['nrc_v'])); ?></span>
                                            <span class="col-name" title="<?php echo htmlspecialchars($g['materia']); ?>"><?php echo htmlspecialchars($g['materia']); ?></span>
                                            <span class="level-badge col-lvl" title="Nivel del idioma">Nivel <?php echo htmlspecialchars($romano); ?></span>
                                        </div>
                                    </td>
                                    
                                    <td class="col-schedule" style="vertical-align: middle;">
                                        <div class="g-expanded-sessions-list" style="gap: 8px;">
                                            <!-- LÍNEA PRESENCIAL -->
                                            <div class="g-inline-session session-presencial">
                                                <span class="session-modality-badge presencial">
                                                    <i class="fas fa-building"></i> Presencial
                                                </span>
                                                <span class="g-session-pill col-nrc">
                                                    <i class="fas fa-building" style="color: #ffc107;" title="NRC Presencial"></i>
                                                    <span><?php echo !empty($g['nrc_p']) ? htmlspecialchars($g['nrc_p']) : 'NA'; ?></span>
                                                </span>
                                                <span class="g-meta-sep">|</span>
                                                <span class="g-session-pill col-aula">
                                                    <i class="fas fa-map-marker-alt" style="color: #ffc107;" title="Aula Presencial"></i>
                                                    <span><?php echo !empty($g['aula_p']) ? htmlspecialchars($g['aula_p']) : 'NA'; ?></span>
                                                </span>
                                                <span class="g-meta-sep">|</span>
                                                <span class="g-session-pill col-dias">
                                                    <i class="far fa-calendar-alt" style="color: #ffc107;" title="Días Presenciales"></i>
                                                    <span><?php echo !empty($g['dias_p']) ? htmlspecialchars(formatear_dias_es($g['dias_p'])) : 'NA'; ?></span>
                                                </span>
                                                <span class="g-meta-sep">|</span>
                                                <span class="g-session-pill col-hora">
                                                    <i class="far fa-clock" style="color: #ffc107;" title="Horario Presencial"></i>
                                                    <span>
                                                        <?php echo (!empty($g['inicio_p']) && !empty($g['fin_p'])) ? date('H:i', strtotime($g['inicio_p'])) . ' - ' . date('H:i', strtotime($g['fin_p'])) : 'NA'; ?>
                                                    </span>
                                                </span>
                                            </div>

                                            <!-- LÍNEA VIRTUAL -->
                                            <div class="g-inline-session session-virtual">
                                                <span class="session-modality-badge virtual">
                                                    <i class="fas fa-laptop"></i> Virtual
                                                </span>
                                                <span class="g-session-pill col-nrc">
                                                    <i class="fas fa-laptop" style="color: #00bcd4;" title="NRC Virtual"></i>
                                                    <span><?php echo !empty($g['nrc_v']) ? htmlspecialchars($g['nrc_v']) : 'NA'; ?></span>
                                                </span>
                                                <span class="g-meta-sep">|</span>
                                                <span class="g-session-pill col-aula">
                                                    <i class="fas fa-video" style="color: #00bcd4;" title="Aula Virtual"></i>
                                                    <span><?php echo !empty($g['aula_v']) ? htmlspecialchars($g['aula_v']) : (!empty($g['nrc_v']) ? 'Zoom' : 'NA'); ?></span>
                                                </span>
                                                <span class="g-meta-sep">|</span>
                                                <span class="g-session-pill col-dias">
                                                    <i class="far fa-calendar-alt" style="color: #00bcd4;" title="Días Virtuales"></i>
                                                    <span><?php echo !empty($g['dias_v']) ? htmlspecialchars(formatear_dias_es($g['dias_v'])) : 'NA'; ?></span>
                                                </span>
                                                <span class="g-meta-sep">|</span>
                                                <span class="g-session-pill col-hora">
                                                    <i class="far fa-clock" style="color: #00bcd4;" title="Horario Virtual"></i>
                                                    <span>
                                                        <?php echo (!empty($g['inicio_v']) && !empty($g['fin_v'])) ? date('H:i', strtotime($g['inicio_v'])) . ' - ' . date('H:i', strtotime($g['fin_v'])) : 'NA'; ?>
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="col-students" style="vertical-align: middle; text-align: center;">
                                        <div class="g-alumnos-box-compact" style="margin: 0 auto;" title="Total de alumnos inscritos en este grupo">
                                            <i class="fas fa-user-friends g-alumnos-mini-icon"></i>
                                            <span class="g-alumnos-mini-num"><?php echo (int)$g['inscritos']; ?></span>
                                        </div>
                                    </td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="emptyRow">
                                <td colspan="4" class="empty-table-msg" style="padding: 50px 20px;">
                                    <i class="fas fa-folder-open" style="font-size: 2.6rem; opacity: 0.5; margin-bottom: 12px; display: block;"></i>
                                    <span style="font-size: 1.05rem; font-weight: 600;">No tienes grupos asignados en este ciclo escolar.</span>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr id="noResultsRow" style="display: none;">
                            <td colspan="4" class="empty-table-msg">
                                <i class="fas fa-search"></i>
                                No se encontraron clases con esa búsqueda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Modal Estandarizado de Selección de Ciclos -->
    <div id="modalSelectorCiclosProf" class="modal-ciclos-overlay" onclick="if(event.target === this) cerrarModalSelectorCiclos();">
        <div class="modal-ciclos-card" role="dialog" aria-modal="true" aria-labelledby="modalCiclosTitle">
            <div class="modal-ciclos-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-calendar-alt" style="font-size: 1.15rem; color: #93c5fd;"></i>
                    <h3 id="modalCiclosTitle">Seleccionar Ciclo Escolar</h3>
                </div>
                <button type="button" class="modal-ciclos-close-btn" onclick="cerrarModalSelectorCiclos()" aria-label="Cerrar modal">&times;</button>
            </div>
            <div class="modal-ciclos-body">
                <p class="modal-ciclos-desc">Selecciona el ciclo escolar para consultar tus clases:</p>
                <div class="modal-ciclos-list">
                    <!-- Opción: Ver todos los ciclos -->
                    <a href="mis_grupos.php?ciclo_ver=-1" class="ciclo-item-link <?php echo ($ciclo_filtro_id === -1) ? 'is-selected' : ''; ?>">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-layer-group" style="font-size: 1rem;"></i>
                            <span style="font-size: 0.92rem; font-weight: <?php echo ($ciclo_filtro_id === -1) ? '700' : '600'; ?>;">Todos los ciclos impartidos</span>
                        </div>
                        <?php if ($ciclo_filtro_id === -1): ?>
                            <i class="fas fa-check-circle" style="font-size: 1.05rem;"></i>
                        <?php endif; ?>
                    </a>

                    <!-- Ciclos individuales impartidos por el profesor -->
                    <?php if (!empty($ciclos_profesor)): ?>
                        <?php foreach($ciclos_profesor as $cp): 
                            $es_sel = ($cp['ciclo_id'] == $ciclo_filtro_id);
                        ?>
                            <a href="mis_grupos.php?ciclo_ver=<?php echo (int)$cp['ciclo_id']; ?>" class="ciclo-item-link <?php echo $es_sel ? 'is-selected' : ''; ?>">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-folder<?php echo $es_sel ? '-open' : ''; ?>" style="font-size: 1rem;"></i>
                                    <span style="font-size: 0.92rem; font-weight: <?php echo $es_sel ? '700' : '600'; ?>;">Ciclo <?php echo htmlspecialchars($cp['nombre']); ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <?php if (!empty($cp['activo'])): ?>
                                        <span class="neon-badge-activo">ACTIVO</span>
                                    <?php endif; ?>
                                    <?php if ($es_sel): ?>
                                        <i class="fas fa-check-circle" style="font-size: 1.05rem;"></i>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 16px; text-align: center; color: var(--text-muted); font-size: 0.88rem;">
                            No hay ciclos registrados con clases asignadas.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-ciclos-footer">
                <button type="button" class="modal-ciclos-footer-btn" onclick="cerrarModalSelectorCiclos()">Cerrar</button>
            </div>
        </div>
    </div>

    <?php include '../main_footer.php'; ?>

    <script>
        // CONTROL DEL MODAL DE SELECCIÓN DE CICLOS
        function abrirModalSelectorCiclos() {
            const modal = document.getElementById('modalSelectorCiclosProf');
            if (modal) {
                modal.style.display = 'flex';
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        function cerrarModalSelectorCiclos() {
            const modal = document.getElementById('modalSelectorCiclosProf');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalSelectorCiclos();
            }
        });

        // LÓGICA DEL BUSCADOR INTELIGENTE EN TIEMPO REAL
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const rows = document.querySelectorAll('.group-row');
            const noResultsRow = document.getElementById('noResultsRow');

            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const term = e.target.value.toLowerCase().trim();
                    let hasVisibleRows = false;

                    rows.forEach(row => {
                        const rowText = row.innerText.toLowerCase();
                        if (rowText.includes(term)) {
                            row.style.display = ''; 
                            hasVisibleRows = true;
                        } else {
                            row.style.display = 'none'; 
                        }
                    });

                    if (!hasVisibleRows && rows.length > 0) {
                        noResultsRow.style.display = '';
                    } else {
                        noResultsRow.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>