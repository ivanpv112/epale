<?php
session_start();
require '../db.php';

// SEGURIDAD: Solo Profesores
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') {
    header("Location: ../index.php"); exit;
}

$profesor_id = $_SESSION['user_id'];
$nombre_profesor = $_SESSION['nombre'] . ' ' . $_SESSION['apellido_paterno'];

// 1. OBTENER GRUPOS ACTIVOS (CORRECCIÓN MODO ESTRICTO SQL)
$sql_grupos = "SELECT g.clave_grupo, m.nombre AS materia, m.nivel, c.nombre AS ciclo, g.materia_id, g.ciclo_id, g.profesor_id,
                      MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END) AS nrc_p,
                      MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END) AS nrc_v,
                      MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.aula END) AS aula_p,
                      MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.aula END) AS aula_v,
                      MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.dias_patron END) AS dias_p,
                      MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.dias_patron END) AS dias_v,
                      MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_inicio END) AS inicio_p,
                      MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_inicio END) AS inicio_v,
                      MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_fin END) AS fin_p,
                      MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_fin END) AS fin_v
               FROM grupos g
               JOIN materias m ON g.materia_id = m.materia_id
               JOIN ciclos c ON g.ciclo_id = c.ciclo_id
               LEFT JOIN horarios h ON g.nrc = h.nrc
               WHERE g.profesor_id = ? AND c.activo = 1 AND g.estado = 'ACTIVO'
               GROUP BY g.clave_grupo, g.materia_id, c.ciclo_id, g.profesor_id, m.nombre, m.nivel, c.nombre
               ORDER BY m.nivel ASC";

$stmt = $pdo->prepare($sql_grupos);
$stmt->execute([$profesor_id]);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_alumnos = 0;
// CONTADOR INTELIGENTE DE CRITERIOS PENDIENTES
$total_criterios_pendientes = 0;

// MOTOR DE PROGRESO DE CALIFICACIÓN POR GRUPO
foreach ($grupos as &$g) {
    // A. Obtener SOLO Criterios del Profesor (Filtro Estricto)
    $stmtCriterios = $pdo->prepare("
        SELECT codigo_examen, nombre_examen, color, icono 
        FROM criterios_evaluacion 
        WHERE materia_id = ? 
        AND (nombre_examen LIKE '%Oral%' OR nombre_examen LIKE '%Participaci%' OR nombre_examen LIKE '%Proyecto%')
        ORDER BY categoria ASC
    ");
    $stmtCriterios->execute([$g['materia_id']]);
    $criterios = $stmtCriterios->fetchAll(PDO::FETCH_ASSOC);

    // B. Obtener Inscripciones del Grupo
    $stmtAlumnos = $pdo->prepare("SELECT i.inscripcion_id FROM inscripciones i JOIN grupos g2 ON i.nrc = g2.nrc WHERE g2.clave_grupo = ? AND i.estatus = 'INSCRITO'");
    $stmtAlumnos->execute([$g['clave_grupo']]);
    $inscripciones = $stmtAlumnos->fetchAll(PDO::FETCH_COLUMN);
    $total_inscritos = count($inscripciones);
    
    $g['inscritos'] = $total_inscritos;
    $total_alumnos += $total_inscritos;

    $g['criterios_progreso'] = [];
    $total_calificados_global = 0;
    $total_esperados_global = $total_inscritos * count($criterios);

    // C. Calcular Avance e Inyectar los Iconos Oficiales
    if ($total_inscritos > 0 && count($criterios) > 0) {
        $in_ins = implode(',', array_fill(0, $total_inscritos, '?'));
        foreach ($criterios as $crit) {
            $nombre = $crit['nombre_examen'];
            $icono = $crit['icono'];
            $color = $crit['color'];

            if (stripos($nombre, 'Oral') !== false) {
                $icono = 'fas fa-comments'; if (!$color) $color = '#00a859';
            } elseif (stripos($nombre, 'Proyecto') !== false) {
                $icono = 'fas fa-file-signature'; if (!$color) $color = '#f59e0b';
            } elseif (stripos($nombre, 'Participaci') !== false) {
                $icono = 'fas fa-hand-paper'; if (!$color) $color = '#00a3c4';
            } else {
                if (!$icono) $icono = 'fas fa-check-circle'; if (!$color) $color = 'var(--udg-blue)';
            }

            $params = array_merge([$crit['codigo_examen']], $inscripciones);
            $stmtCal = $pdo->prepare("SELECT COUNT(puntaje) FROM calificaciones WHERE tipo_examen = ? AND inscripcion_id IN ($in_ins) AND puntaje IS NOT NULL");
            $stmtCal->execute($params);
            $calificados = $stmtCal->fetchColumn();

            $porcentaje = round(($calificados / $total_inscritos) * 100);
            if ($porcentaje < 100) { $total_criterios_pendientes++; }

            $g['criterios_progreso'][] = [
                'nombre' => $nombre, 'color' => $color, 'icono' => $icono,
                'calificados' => $calificados, 'total' => $total_inscritos, 'porcentaje' => $porcentaje
            ];
            $total_calificados_global += $calificados;
        }
    }
    $g['progreso_global'] = ($total_esperados_global > 0) ? round(($total_calificados_global / $total_esperados_global) * 100) : 0;
}
unset($g);

$total_grupos = count($grupos);

// 3. DETERMINAR CLASES DE HOY
$dia_num = date('N'); 
$letra_hoy = '';
switch($dia_num) { case 1: $letra_hoy = 'L'; break; case 2: $letra_hoy = 'M'; break; case 3: $letra_hoy = 'I'; break; case 4: $letra_hoy = 'J'; break; case 5: $letra_hoy = 'V'; break; }

$clases_hoy = [];
if ($letra_hoy !== '') {
    foreach ($grupos as $g) {
        if ($g['dias_p'] && (strpos(strtoupper($g['dias_p']), $letra_hoy) !== false || ($letra_hoy == 'I' && (strpos(strtoupper($g['dias_p']), 'MIE') !== false || strpos(strtoupper($g['dias_p']), 'X') !== false)))) {
            $clases_hoy[] = ['materia' => $g['materia'] . ' ' . $g['nivel'], 'aula' => $g['aula_p'] ?: 'Sin Aula', 'inicio' => $g['inicio_p'], 'fin' => $g['fin_p'], 'tipo' => 'Presencial'];
        }
        if ($g['dias_v'] && (strpos(strtoupper($g['dias_v']), $letra_hoy) !== false || ($letra_hoy == 'I' && (strpos(strtoupper($g['dias_v']), 'MIE') !== false || strpos(strtoupper($g['dias_v']), 'X') !== false)))) {
            $clases_hoy[] = ['materia' => $g['materia'] . ' ' . $g['nivel'], 'aula' => $g['aula_v'] ?: 'Virtual', 'inicio' => $g['inicio_v'], 'fin' => $g['fin_v'], 'tipo' => 'Virtual'];
        }
    }
}
usort($clases_hoy, function($a, $b) { return strtotime($a['inicio']) - strtotime($b['inicio']); });
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del Profesor | e-PALE</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profesor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'menu_profesor.php'; ?>
    <main class="main-content">
        <div class="dash-header"><h1>Panel del Profesor</h1><p>Prof. <?php echo htmlspecialchars($nombre_profesor); ?></p></div>
        
        <div class="stats-row">
            <div class="stat-box">
                <div style="background: rgba(255,255,255,0.15); width: 60px; height: 60px; border-radius: 12px; display: flex; justify-content: center; align-items: center;"><i class="fas fa-users" style="color: #4dabf7; font-size: 2rem; margin:0;"></i></div>
                <div><div class="number"><?php echo $total_alumnos; ?></div><div class="label">Alumnos Totales</div></div>
            </div>
            <div class="stat-box">
                <div style="background: rgba(255,255,255,0.15); width: 60px; height: 60px; border-radius: 12px; display: flex; justify-content: center; align-items: center;"><i class="fas fa-book-open" style="color: #4dabf7; font-size: 2rem; margin:0;"></i></div>
                <div><div class="number"><?php echo $total_grupos; ?></div><div class="label">Grupos Activos</div></div>
            </div>
            <div class="stat-box">
                <div style="background: rgba(255,255,255,0.15); width: 60px; height: 60px; border-radius: 12px; display: flex; justify-content: center; align-items: center;"><i class="far fa-bell" style="color: #4dabf7; font-size: 2.2rem; margin:0;"></i></div>
                <div><div class="number"><?php echo $total_criterios_pendientes; ?></div><div class="label" style="text-transform: none; letter-spacing: 0;">Criterios pendientes</div></div>
            </div>
        </div>

        <div class="main-grid">
            
            <!-- CONTENEDOR IZQUIERDO: TÍTULO, LEYENDA Y TARJETAS INDIVIDUALES -->
            <div>
                <!-- TÍTULO Y LEYENDA (Sueltos) -->
                <div style="display:flex; align-items:center; gap: 10px; margin-bottom: 5px;">
                    <h3 class="card-title" style="margin:0;"><i class="fas fa-book-open"></i> Mis Grupos</h3>
                    <span style="color:var(--text-muted); font-size:0.9rem; margin-top:2px;">— progreso de calificación por criterio</span>
                </div>
                
                <div class="g-legend">
                    <span><i class="fas fa-comments" style="color: #00a859;"></i> Examen Oral 1</span>
                    <span><i class="fas fa-comments" style="color: #00a859;"></i> Examen Oral 2</span>
                    <span><i class="fas fa-file-signature" style="color: #f59e0b;"></i> Proyecto Escrito</span>
                    <span><i class="fas fa-hand-paper" style="color: #00a3c4;"></i> Participación</span>
                </div>

                <!-- LISTA DE GRUPOS COMO TARJETAS INDIVIDUALES -->
                <?php if($total_grupos > 0): ?>
                    <?php foreach($grupos as $index => $g): ?>
                        <div class="group-progress-card">
                            <div class="g-top-bar-bg"><div class="g-top-bar-fill" style="width: <?php echo $g['progreso_global']; ?>%;"></div></div>
                            
                            <div class="g-header" onclick="toggleGroup(event, <?php echo $index; ?>)">
                                <div class="g-header-flex">
                                    <div class="g-title-sec">
                                        <span class="g-badge"><?php echo htmlspecialchars($g['nrc_p'] ?: $g['nrc_v']); ?></span>
                                        <span class="g-name"><?php echo htmlspecialchars($g['materia'] . ' ' . $g['nivel']); ?></span>
                                    </div>
                                    <div class="g-stats-sec">
                                        <div class="g-perc-block">
                                            <span class="g-perc" style="<?php echo ($g['progreso_global']==100) ? 'color: var(--success);' : (($g['progreso_global']<50) ? 'color: var(--warning);' : ''); ?>"><?php echo $g['progreso_global']; ?>%</span>
                                            <span class="g-perc-label">calificado</span>
                                        </div>
                                        <button class="g-toggle-btn" id="btn-toggle-<?php echo $index; ?>"><i class="fas fa-chevron-down"></i></button>
                                    </div>
                                </div>
                                <div class="g-meta-row">
                                    <span><i class="fas fa-user-friends"></i> <?php echo $g['inscritos']; ?> alumnos</span>
                                    <span><i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($g['dias_p'] ?: $g['dias_v']); ?> <?php echo date('H:i', strtotime($g['inicio_p']?:$g['inicio_v'])); ?>-<?php echo date('H:i', strtotime($g['fin_p']?:$g['fin_v'])); ?></span>
                                    <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($g['aula_p'] ?: ($g['aula_v'] ?: 'Virtual')); ?></span>
                                </div>
                                
                                <a href="detalle_grupo.php?clave=<?php echo urlencode($g['clave_grupo']); ?>" class="btn-ver-grupo" onclick="event.stopPropagation();">
                                    Ver Grupo <i class="fas fa-arrow-right" style="font-size:1rem; margin-left:3px;"></i>
                                </a>
                            </div>

                            <div class="g-body" id="body-group-<?php echo $index; ?>">
                                <?php if(empty($g['criterios_progreso'])): ?>
                                    <p style="text-align:center; color:var(--text-muted); font-size:0.9rem;">Criterios no configurados para esta materia.</p>
                                <?php else: ?>
                                    <?php foreach($g['criterios_progreso'] as $crit): ?>
                                        <div class="crit-row">
                                            <div class="crit-header">
                                                <span class="crit-name" style="color: <?php echo $crit['color']; ?>;"><i class="<?php echo $crit['icono']; ?>"></i> <?php echo htmlspecialchars($crit['nombre']); ?></span>
                                                <div class="crit-stats">
                                                    <span class="crit-count"><?php echo $crit['calificados']; ?> / <?php echo $crit['total']; ?> alumnos</span>
                                                    <span class="crit-perc" style="color: <?php echo $crit['color']; ?>;"><?php echo $crit['porcentaje']; ?>%</span>
                                                </div>
                                            </div>
                                            <div class="crit-bar-bg"><div class="crit-bar-fill" style="width: <?php echo $crit['porcentaje']; ?>%; background-color: <?php echo $crit['color']; ?>;"></div></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="background: white; border-radius: 12px; padding: 40px; text-align: center; color: #aaa; border: 1px solid #f1f3f5;">No tienes grupos activos asignados.</div>
                <?php endif; ?>
            </div> <!-- FIN CONTENEDOR IZQUIERDO -->

            <!-- CONTENEDOR DERECHO: AVISOS Y PRÓXIMAS CLASES -->
            <div>
                <!-- TARJETA DE AVISOS -->
                <div class="content-card" style="height: fit-content; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 class="card-title" style="margin: 0;"><i class="fas fa-bullhorn"></i> Avisos Activos</h3>
                        <button onclick="abrirPanelTareas()" class="btn-save" style="padding: 6px 12px; font-size: 0.85rem; background-color: #3ba5ff; border: none; color: white; cursor: pointer; border-radius: 8px;"><i class="fas fa-edit"></i> Gestionar</button>
                    </div>
                    <div id="lista_resumen_tareas">
                        <p style="color: #888; margin: 0;">Cargando avisos...</p>
                    </div>
                </div>

                <!-- TARJETA DE CLASES -->
                <div class="content-card">
                    <h3 class="card-title"><i class="far fa-calendar-check"></i> Próximas Clases (Hoy)</h3>
                    <?php if(count($clases_hoy) > 0): ?>
                        <div class="today-classes" style="flex-direction: column;"> <!-- EN MÓDULO DERECHO VAN APILADAS -->
                            <?php foreach($clases_hoy as $c): ?>
                                <div class="class-card">
                                    <div><div class="class-name"><?php echo htmlspecialchars($c['materia']); ?></div><div class="class-room"><?php echo ($c['tipo'] == 'Presencial') ? '<i class="fas fa-building" style="color:#28a745;"></i>' : '<i class="fas fa-laptop-house" style="color:#17a2b8;"></i>'; ?> <?php echo htmlspecialchars($c['aula']); ?></div></div>
                                    <div class="class-time"><?php echo date('H:i', strtotime($c['inicio'])) . ' - ' . date('H:i', strtotime($c['fin'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?><p style="color: #888; margin: 0;">No tienes clases programadas para hoy.</p><?php endif; ?>
                </div>
            </div> <!-- FIN CONTENEDOR DERECHO -->

        </div> <!-- FIN MAIN GRID -->
    </main>

    <!-- MODALES DE GESTIÓN DE AVISOS -->
    <div id="modalGestionTareas" class="modal-overlay">
        <div class="modal-content-lg">
            <div class="modal-header">
                <h2><i class="fas fa-bullhorn"></i> Panel de Avisos y Asignaciones</h2>
                <button class="close-btn" onclick="cerrarPanelTareas()">&times;</button>
            </div>
            <div class="modal-body">
                <button onclick="abrirFormularioTarea()" class="btn-save mb-15" style="background-color: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer;"><i class="fas fa-plus"></i> Crear Nuevo</button>
                <div style="overflow-x: auto;">
                    <table class="table-modern">
                        <thead>
                            <tr><th>Tipo</th><th>Título</th><th>Clase</th><th>Inicio</th><th>Fin</th><th>Estatus</th><th>Acciones</th></tr>
                        </thead>
                        <tbody id="tablaTareasBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- z-index ALTO -->
    <div id="modalFormTarea" class="modal-overlay" style="z-index: 3100;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="tituloModalTarea">Nueva Publicación</h2>
                <button class="close-btn" onclick="cerrarFormularioTarea()">&times;</button>
            </div>
            <form id="formTarea" onsubmit="guardarTarea(event)">
                <div class="modal-body">
                    <input type="hidden" id="tarea_id" name="tarea_id">
                    <div class="form-group-inline mb-15">
                        <label style="margin-right: 15px; cursor: pointer;"><input type="radio" name="tipo" value="AVISO" checked> 📢 Aviso General</label>
                        <label style="cursor: pointer;"><input type="radio" name="tipo" value="ASIGNACION"> 📝 Asignación / Tarea</label>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px; font-weight:bold;">Título</label>
                        <input type="text" id="tarea_titulo" name="titulo" required placeholder="Ej. Tarea 1: Ensayo" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;">
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px; font-weight:bold;">Descripción</label>
                        <textarea id="tarea_descripcion" name="descripcion" rows="3" required placeholder="Detalles de lo que deben hacer..." style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;"></textarea>
                    </div>
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label style="display:block; margin-bottom:5px; font-weight:bold;">¿A qué clase va dirigido?</label>
                        <select id="tarea_nrc" name="nrc" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;"></select>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label style="display:block; margin-bottom:5px; font-weight:bold;">Fecha de Publicación</label>
                            <input type="datetime-local" id="tarea_inicio" name="fecha_inicio" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;">
                        </div>
                        <div class="form-group">
                            <label style="display:block; margin-bottom:5px; font-weight:bold;">Fecha Límite</label>
                            <input type="datetime-local" id="tarea_fin" name="fecha_fin" required style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn-cancel" onclick="cerrarFormularioTarea()" style="padding: 8px 15px; border: 1px solid #ccc; background: #fff; border-radius: 4px; cursor: pointer;">Cancelar</button>
                    <button type="submit" class="btn-save" style="padding: 8px 15px; background: var(--udg-blue); color: #fff; border: none; border-radius: 4px; cursor: pointer;"><i class="fas fa-paper-plane"></i> Publicar</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../main_footer.php'; ?>

    <script>
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";
    </script>
    <script src="../js/tareas_profesor.js?v=<?php echo time(); ?>"></script>
</body>
</html>
