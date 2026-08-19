<?php
session_start();
require '../db.php'; 
date_default_timezone_set('America/Mexico_City'); 

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}

$stmt_al = $pdo->prepare("SELECT a.alumno_id, u.nombre, u.apellido_paterno, u.apellido_materno 
                          FROM alumnos a 
                          JOIN usuarios u ON a.usuario_id = u.usuario_id 
                          WHERE a.usuario_id = ?");
$stmt_al->execute([$_SESSION['user_id']]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);

$alumno_id = $alumno['alumno_id'];
$nombre_completo = trim($alumno['nombre'] . ' ' . $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno']);

// MATERIAS ACTIVAS (AQUÍ SE FILTRA INTELIGENTEMENTE POR EL CICLO ESCOLAR ACTIVO)
$sql_materias = "SELECT i.inscripcion_id, m.materia_id, m.nombre, m.nivel, g.nrc 
                 FROM inscripciones i
                 JOIN grupos g ON i.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                 WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO' AND g.estado = 'ACTIVO' AND c.activo = 1";
$stmt_mat = $pdo->prepare($sql_materias);
$stmt_mat->execute([$alumno_id]);
$materias_inscritas = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

$dias_map = [ 'Monday' => 'L', 'Tuesday' => 'M', 'Wednesday' => 'I', 'Thursday' => 'J', 'Friday' => 'V', 'Saturday' => 'S', 'Sunday' => 'D' ];
$dia_hoy_letra = $dias_map[date('l')]; 

// HORARIOS ACTIVOS
$sql_horarios = "SELECT h.hora_inicio, h.hora_fin, h.aula, h.modalidad, h.dias_patron, m.nombre, m.nivel 
                 FROM horarios h
                 JOIN grupos g ON h.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN inscripciones i ON g.nrc = i.nrc
                 JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                 WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO' AND g.estado = 'ACTIVO' AND c.activo = 1
                 ORDER BY h.hora_inicio ASC";
$stmt_hor = $pdo->prepare($sql_horarios);
$stmt_hor->execute([$alumno_id]);
$todos_horarios = $stmt_hor->fetchAll(PDO::FETCH_ASSOC);

$clases_hoy = [];
foreach ($todos_horarios as $h) {
    $patron = isset($h['dias_patron']) ? (string)$h['dias_patron'] : '';
    $solo_letras = preg_replace('/[^A-Za-z]/', '', strtoupper($patron));
    
    if (!empty($solo_letras) && strlen($solo_letras) > 0) {
        $dias_clase = str_split($solo_letras);
        if (in_array($dia_hoy_letra, $dias_clase)) {
            $clases_hoy[] = $h;
        }
    }
}

$meses_es = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$dias_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$fecha_texto_es = $dias_es[date('w')] . ', ' . date('d') . ' de ' . $meses_es[date('n') - 1];

$sql_notif_bajas = "SELECT sb.estatus, sb.fecha_respuesta, m.nombre, m.nivel
                    FROM solicitudes_bajas sb
                    JOIN inscripciones i ON sb.inscripcion_id = i.inscripcion_id
                    JOIN grupos g ON i.nrc = g.nrc
                    JOIN materias m ON g.materia_id = m.materia_id
                    WHERE i.alumno_id = ? 
                      AND (sb.estatus = 'APROBADA' OR sb.estatus = 'RECHAZADA')
                      AND sb.fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 15 DAY)
                    ORDER BY sb.fecha_respuesta DESC LIMIT 3";
$stmt_notif = $pdo->prepare($sql_notif_bajas);
$stmt_notif->execute([$alumno_id]);
$avisos_dinamicos = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('format_score')) {
    function format_score($num) { return floatval($num) == intval($num) ? intval($num) : floatval($num); }
}

// === FUNCIÓN PARA RECORTAR TEXTOS LARGOS ===
if (!function_exists('recortar_texto')) {
    function recortar_texto($texto, $limite = 60) {
        if (mb_strlen($texto, 'UTF-8') > $limite) {
            return mb_substr($texto, 0, $limite, 'UTF-8') . '... <span style="color:var(--udg-blue); font-size:0.8rem; font-weight:bold;">Ver más</span>';
        }
        return $texto;
    }
}

// ===============================================
// LECTURA DE AVISOS DEL ADMINISTRADOR
// ===============================================
$pdo->exec("DELETE FROM avisos WHERE fecha_expiracion IS NOT NULL AND fecha_expiracion < NOW()");

$mis_nrcs = []; $mis_materias_ids = []; $mis_idiomas = [];
foreach ($materias_inscritas as $m) {
    $mis_nrcs[] = $m['nrc'];
    $mis_materias_ids[] = $m['materia_id'];
    $idioma_puro = trim(preg_replace('/[0-9]+/', '', $m['nombre']));
    $mis_idiomas[] = $idioma_puro;
}
$mis_idiomas = array_unique($mis_idiomas);

$sql_avisos = "SELECT * FROM avisos WHERE tipo_audiencia = 'GLOBAL'";

if (count($mis_idiomas) > 0) {
    $in_idiomas = "'" . implode("','", $mis_idiomas) . "'";
    $sql_avisos .= " OR (tipo_audiencia = 'IDIOMA' AND audiencia_ref IN ($in_idiomas))";
}
if (count($mis_materias_ids) > 0) {
    $in_mats = implode(",", $mis_materias_ids);
    $sql_avisos .= " OR (tipo_audiencia = 'MATERIA' AND audiencia_ref IN ($in_mats))";
}
if (count($mis_nrcs) > 0) {
    $in_nrc = implode(",", $mis_nrcs);
    $sql_avisos .= " OR (tipo_audiencia = 'GRUPO' AND audiencia_ref IN ($in_nrc))";
}

$sql_avisos .= " ORDER BY fecha_creacion DESC";
$avisos_admin = $pdo->query($sql_avisos)->fetchAll(PDO::FETCH_ASSOC);

// ===============================================
// LECTURA DE TAREAS Y AVISOS DE PROFESORES
// ===============================================
$tareas_dashboard = [];
$tareas_todas = [];

if (count($mis_nrcs) > 0) {
    $in_nrc_tareas = implode(",", $mis_nrcs);
    $sql_tareas = "
        SELECT t.*, m.nombre as materia_nombre, g.clave_grupo, u.nombre as prof_nombre, u.apellido_paterno as prof_ap
        FROM tareas_profesor t
        JOIN grupos g ON t.nrc = g.nrc
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN usuarios u ON t.profesor_id = u.usuario_id
        WHERE t.nrc IN ($in_nrc_tareas)
        ORDER BY t.fecha_inicio DESC
    ";
    $tareas_raw = $pdo->query($sql_tareas)->fetchAll(PDO::FETCH_ASSOC);
    
    $hoy = new DateTime();
    foreach ($tareas_raw as $t) {
        $inicio = new DateTime($t['fecha_inicio']);
        $fin = new DateTime($t['fecha_fin']);
        
        if ($hoy > $fin) {
            $estatus = 'FINALIZADA';
        } elseif ($hoy >= $inicio && $hoy <= $fin) {
            $estatus = 'ACTIVA';
        } else {
            $diff = $hoy->diff($inicio);
            $dias_faltantes = $diff->days;
            $invert = $diff->invert; 
            if ($invert == 0 && $dias_faltantes <= 3) {
                $estatus = 'PRÓXIMA';
            } else {
                $estatus = 'PENDIENTE';
            }
        }
        
        // El alumno jamás ve tareas pendientes de un futuro lejano
        if ($estatus !== 'PENDIENTE') {
            $t['estatus'] = $estatus;
            // Se guardan en el array global
            $tareas_todas[] = $t;
            
            // Lógica para el Dashboard: Solo Mostrar 'ACTIVA' y 'PRÓXIMA', máximo 3
            if ($estatus !== 'FINALIZADA' && count($tareas_dashboard) < 3) {
                $tareas_dashboard[] = $t;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio | E-Pale</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content">
        
        <div class="welcome-banner">
            <h2>Página de inicio</h2>
            <h1><?php echo htmlspecialchars($nombre_completo); ?></h1>
        </div>

        <div class="dashboard-grid">
            
            <!-- TARJETA: PROGRESO -->
            <div class="card">
                <h3 style="display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fas fa-chart-pie"></i> Progreso General</span>
                    
                    <?php if(count($materias_inscritas) > 0): ?>
                        <select class="subject-selector" onchange="cambiarMateriaDash(this.value)">
                            <?php foreach($materias_inscritas as $index => $mat): ?>
                                <option value="eval-<?php echo $mat['inscripcion_id']; ?>">
                                    <?php echo htmlspecialchars($mat['nombre'] . ' ' . $mat['nivel']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <span style="font-size:0.8rem; color:#888;">Sin materias activas</span>
                    <?php endif; ?>
                </h3>
                
                <?php 
                if(count($materias_inscritas) == 0): ?>
                    <div style="text-align:center; padding:40px 20px; color:#888;">
                        <i class="fas fa-bed" style="font-size: 3rem; color: #ddd; margin-bottom: 15px; display: block;"></i>
                        No estás inscrito en ninguna materia actualmente.
                    </div>
                <?php endif; ?>

                <?php foreach($materias_inscritas as $index => $mat): 
                    $insc_id = $mat['inscripcion_id'];
                    $materia_id = $mat['materia_id'];
                    $display = ($index === 0) ? 'block' : 'none';
                    
                    $stmt_max = $pdo->prepare("SELECT SUM(puntos_maximos) FROM criterios_evaluacion WHERE materia_id = ?");
                    $stmt_max->execute([$materia_id]);
                    $max_puntos = $stmt_max->fetchColumn() ?: 0;
                    
                    $stmt_cal = $pdo->prepare("SELECT puntaje FROM calificaciones WHERE inscripcion_id = ?");
                    $stmt_cal->execute([$insc_id]);
                    $suma_puntos = 0;
                    while($row = $stmt_cal->fetch(PDO::FETCH_ASSOC)){
                        if($row['puntaje'] !== null) $suma_puntos += floatval($row['puntaje']);
                    }

                    $porcentaje = ($max_puntos > 0) ? ($suma_puntos / $max_puntos) * 100 : 0;
                    if ($porcentaje > 100) $porcentaje = 100;
                    
                    if ($porcentaje < 60) {
                        $color = '#dc3545'; $mensaje = 'En riesgo'; $bg_msg = '#f8d7da'; $col_msg = '#721c24';
                    } elseif ($porcentaje < 80) {
                        $color = '#ffc107'; $mensaje = 'Regular'; $bg_msg = '#fff3cd'; $col_msg = '#856404';
                    } elseif ($porcentaje < 95) {
                        $color = '#28a745'; $mensaje = 'Buen desempeño'; $bg_msg = '#d4edda'; $col_msg = '#155724';
                    } else {
                        $color = 'var(--udg-blue)'; $mensaje = '¡Excelente!'; $bg_msg = '#cce5ff'; $col_msg = '#004085';
                    }
                ?>
                
                <div id="eval-<?php echo $insc_id; ?>" class="eval-container" style="display: <?php echo $display; ?>;">
                    <div class="chart-wrapper">
                        
                        <?php if($max_puntos == 0): ?>
                            <div style="padding:30px 0; color:#999; text-align:center;">
                                <i class="fas fa-hourglass-half" style="font-size:2rem; margin-bottom:10px;"></i><br>
                                Criterios sin configurar
                            </div>
                        <?php else: ?>
                            <svg viewBox="0 0 36 36" class="circular-chart">
                                <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <path class="circle" 
                                      stroke-dasharray="<?php echo round($porcentaje); ?>, 100" 
                                      style="stroke: <?php echo $color; ?>;" 
                                      d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                <text x="18" y="20.35" class="percentage"><?php echo round($porcentaje); ?>%</text>
                            </svg>
                            
                            <div class="chart-subtitle">
                                <?php echo format_score($suma_puntos); ?> / <?php echo $max_puntos; ?> puntos
                            </div>
                            <div class="chart-status" style="background-color: <?php echo $bg_msg; ?>; color: <?php echo $col_msg; ?>;">
                                <?php echo $mensaje; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                    
                    <div style="text-align: center; margin-top: 15px;">
                        <button onclick="window.location.href='calificaciones.php?ins=<?php echo $insc_id; ?>'" style="padding: 8px 15px; background: transparent; border: 1px solid var(--udg-blue); color: var(--udg-blue); border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s;">Ver Desglose</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- TARJETA: HORARIOS DE HOY -->
            <div class="card">
                <h3 style="margin-bottom: 5px;"><i class="far fa-clock"></i> Clases de Hoy</h3>
                <p style="font-size: 0.85rem; color: #888; margin-top: 0; margin-bottom: 20px;">
                    <i class="far fa-calendar-alt"></i> <?php echo $fecha_texto_es; ?>
                </p>
                
                <ul class="next-classes-list">
                    <?php if(count($clases_hoy) > 0): ?>
                        <?php foreach($clases_hoy as $h): ?>
                            <li style="border-left: 4px solid <?php echo ($h['modalidad'] == 'VIRTUAL') ? '#17a2b8' : '#28a745'; ?>; padding-left: 10px;">
                                <div>
                                    <div style="font-weight: bold; color: #333; font-size: 0.95rem;"><?php echo htmlspecialchars($h['nombre'] . ' ' . $h['nivel']); ?></div>
                                    <div style="font-size: 0.8rem; color: #666; margin-top: 3px;">
                                        <i class="fas <?php echo ($h['modalidad'] == 'VIRTUAL') ? 'fa-laptop-house' : 'fa-building'; ?>"></i> 
                                        <?php echo htmlspecialchars($h['aula']); ?>
                                    </div>
                                </div>
                                <span class="grade-value" style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; border: 1px solid #eee;">
                                    <?php echo date('H:i', strtotime($h['hora_inicio'])) . ' - ' . date('H:i', strtotime($h['hora_fin'])); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding: 20px 0; color:#888;">
                            <i class="fas fa-mug-hot" style="font-size: 2.5rem; color: #eee; margin-bottom: 10px; display: block;"></i>
                            ¡Día libre! No tienes clases programadas para hoy.
                        </div>
                    <?php endif; ?>
                </ul>
                <div style="margin-top: 25px; text-align: center;">
                    <button onclick="window.location.href='horario.php'" style="width: auto; padding: 10px 20px; background-color: white; border: 1px solid #ddd; border-radius: 6px; font-weight:bold; cursor:pointer; color: #555; transition: 0.2s;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">Ver Horario Completo</button>
                </div>
            </div>

            <!-- TARJETA: TAREAS Y AVISOS DE CLASE (Limitado a 3 y sin "Finalizadas") -->
            <div class="card">
                <h3 style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
                    <span><i class="fas fa-tasks"></i> Actividades de tus Clases</span>
                    <?php if(count($tareas_todas) > 0): ?>
                        <button onclick="abrirModalTodasActividades()" style="background: none; border: 1px solid var(--udg-blue); color: var(--udg-blue); padding: 4px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: bold; transition: 0.2s;" onmouseover="this.style.background='var(--udg-blue)'; this.style.color='white';" onmouseout="this.style.background='none'; this.style.color='var(--udg-blue)';">Ver todas (<?php echo count($tareas_todas); ?>)</button>
                    <?php endif; ?>
                </h3>
                <div style="font-size: 0.9rem; color: #555;">
                <?php if(count($tareas_dashboard) > 0): ?>
                    <?php foreach($tareas_dashboard as $t): 
                        $badgeClass = '';
                        if($t['estatus'] === 'PRÓXIMA') $badgeClass = 'background-color: #ffc107; color: #000;';
                        elseif($t['estatus'] === 'ACTIVA') $badgeClass = 'background-color: #28a745; color: white;';
                        
                        $icono = $t['tipo'] === 'AVISO' ? '📢' : '📝';
                        $borde_izq = $t['tipo'] === 'AVISO' ? '#17a2b8' : '#0056b3';
                        
                        // JSON Data-Info Limpio
                        $tarea_data = [
                            'materia' => $t['materia_nombre'],
                            'estatus_html' => '<span style="'.$badgeClass.' font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; font-weight: bold;">'.$t['estatus'].'</span>',
                            'titulo' => $icono . ' ' . $t['titulo'],
                            'descripcion' => nl2br(htmlspecialchars($t['descripcion'])),
                            'profesor' => $t['prof_nombre'] . ' ' . $t['prof_ap'],
                            'fecha_inicio' => date('d/m/Y h:i A', strtotime($t['fecha_inicio'])),
                            'fecha_fin' => date('d/m/Y h:i A', strtotime($t['fecha_fin']))
                        ];
                        $json_info = htmlspecialchars(json_encode($tarea_data), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="aviso-item aviso-clickable texto-seguro" onclick="abrirModalDetalle(this)" data-info="<?php echo $json_info; ?>" style="border-left: 4px solid <?php echo $borde_izq; ?>; padding-left: 10px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <span class="tag-aviso" style="background: #f8f9fa; color: #555; border: 1px solid #ddd; font-weight:bold; font-size:0.75rem; padding: 3px 8px; border-radius: 12px;"><?php echo htmlspecialchars($t['materia_nombre']); ?></span>
                                <span style="<?php echo $badgeClass; ?> font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; font-weight: bold;"><?php echo $t['estatus']; ?></span>
                            </div>
                            <div style="margin-top: 8px;">
                                <strong style="color: #333; font-size:1.05rem;"><?php echo $icono; ?> <?php echo htmlspecialchars($t['titulo']); ?></strong>
                            </div>
                            <div style="margin-top: 5px; color: #666; font-size: 0.9rem;">
                                <?php echo recortar_texto(htmlspecialchars($t['descripcion']), 60); ?>
                            </div>
                            <div style="margin-top: 8px; font-size: 0.8rem; color: #888;">
                                <i class="far fa-user"></i> Prof. <?php echo htmlspecialchars($t['prof_nombre'] . ' ' . $t['prof_ap']); ?> <br>
                                <i class="far fa-calendar-alt"></i> Cierre: <strong><?php echo date('d/m/Y h:i A', strtotime($t['fecha_fin'])); ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding: 20px 0; color:#aaa; font-style: italic;">
                        <i class="fas fa-clipboard-check" style="font-size: 2rem; margin-bottom: 10px; display: block; color: #eee;"></i>
                        No hay tareas ni avisos activos en tus clases.
                    </div>
                <?php endif; ?>
                </div>
            </div>

            <!-- TARJETA: AVISOS ADMINISTRATIVOS -->
            <div class="card">
                <h3><i class="far fa-bell"></i> Avisos Generales</h3>
                <div style="font-size: 0.9rem; color: #555;">
                    
                    <?php foreach($avisos_dinamicos as $aviso): ?>
                        <div class="aviso-item texto-seguro">
                            <span class="tag-aviso tag-sistema" style="background: #fff3cd; color: #856404; border: 1px solid #ffeeba;">Control Escolar</span>
                            <?php if($aviso['estatus'] == 'APROBADA'): ?>
                                <span class="tag-aprobada" style="float: right;">Aprobada</span><br>
                                <strong style="color: #333;">Solicitud de Baja:</strong> Tu petición para abandonar <strong style="color:var(--udg-blue);"><?php echo htmlspecialchars($aviso['nombre'] . ' ' . $aviso['nivel']); ?></strong> fue aprobada.
                            <?php else: ?>
                                <span class="tag-rechazada" style="float: right;">Rechazada</span><br>
                                <strong style="color: #333;">Solicitud de Baja:</strong> Tu petición para abandonar <strong style="color:var(--udg-blue);"><?php echo htmlspecialchars($aviso['nombre'] . ' ' . $aviso['nivel']); ?></strong> fue rechazada. Revisa el Kárdex.
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach($avisos_admin as $aviso): 
                        $tag_color = '#e2e3e5'; $tag_text = '#383d41'; $etiqueta = 'Aviso Global';
                        if ($aviso['tipo_audiencia'] == 'IDIOMA') { $etiqueta = 'Aviso de Idioma'; $tag_color = '#cce5ff'; $tag_text = '#004085'; }
                        elseif ($aviso['tipo_audiencia'] == 'MATERIA') { $etiqueta = 'Aviso de Nivel'; $tag_color = '#d4edda'; $tag_text = '#155724'; }
                        elseif ($aviso['tipo_audiencia'] == 'GRUPO') { $etiqueta = 'Aviso de tu Clase'; $tag_color = '#f8d7da'; $tag_text = '#721c24'; }
                    ?>
                        <div class="aviso-item texto-seguro">
                            <span class="tag-aviso tag-sistema" style="background: <?php echo $tag_color; ?>; color: <?php echo $tag_text; ?>; border: 1px solid <?php echo $tag_text; ?>;"><?php echo $etiqueta; ?></span><br>
                            <strong style="color: #333;"><?php echo htmlspecialchars($aviso['titulo']); ?>:</strong> <?php echo nl2br(htmlspecialchars($aviso['cuerpo'])); ?>
                            
                            <?php if($aviso['fecha_expiracion']): ?>
                                <div style="font-size: 0.75rem; color: #aaa; margin-top: 5px;"><i class="fas fa-stopwatch"></i> Desaparecerá pronto.</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if(count($avisos_dinamicos) == 0 && count($avisos_admin) == 0): ?>
                        <div style="text-align:center; padding: 20px 0; color:#aaa; font-style: italic;">
                            <i class="far fa-check-circle" style="font-size: 2rem; margin-bottom: 10px; display: block; color: #eee;"></i>
                            No tienes notificaciones administrativas nuevas.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

    <!-- MODAL 1: HISTORIAL DE TODAS LAS ACTIVIDADES (Activas, Próximas y Finalizadas) -->
    <div id="modalTodasActividades" class="modal-overlay">
        <div class="modal-card" style="max-height: 90vh;">
            <div class="modal-header" style="border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px;">
                <h2 style="margin:0; color:var(--udg-blue); font-size: 1.4rem;"><i class="fas fa-tasks"></i> Historial de Actividades</h2>
                <button class="close-btn" onclick="cerrarModalTodasActividades()" style="position:relative; top:0; right:0;">&times;</button>
            </div>
            <div class="modal-card-body" style="padding-right: 15px;">
                <?php if(count($tareas_todas) > 0): ?>
                    <?php foreach($tareas_todas as $t): 
                        $badgeClass = '';
                        if($t['estatus'] === 'PRÓXIMA') $badgeClass = 'background-color: #ffc107; color: #000;';
                        elseif($t['estatus'] === 'ACTIVA') $badgeClass = 'background-color: #28a745; color: white;';
                        elseif($t['estatus'] === 'FINALIZADA') $badgeClass = 'background-color: #dc3545; color: white;';
                        
                        $icono = $t['tipo'] === 'AVISO' ? '📢' : '📝';
                        $borde_izq = $t['tipo'] === 'AVISO' ? '#17a2b8' : '#0056b3';
                        
                        // JSON Data-Info Limpio
                        $tarea_data = [
                            'materia' => $t['materia_nombre'],
                            'estatus_html' => '<span style="'.$badgeClass.' font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; font-weight: bold;">'.$t['estatus'].'</span>',
                            'titulo' => $icono . ' ' . $t['titulo'],
                            'descripcion' => nl2br(htmlspecialchars($t['descripcion'])),
                            'profesor' => $t['prof_nombre'] . ' ' . $t['prof_ap'],
                            'fecha_inicio' => date('d/m/Y h:i A', strtotime($t['fecha_inicio'])),
                            'fecha_fin' => date('d/m/Y h:i A', strtotime($t['fecha_fin']))
                        ];
                        $json_info = htmlspecialchars(json_encode($tarea_data), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="aviso-item aviso-clickable texto-seguro" onclick="abrirModalDetalle(this)" data-info="<?php echo $json_info; ?>" style="border-left: 4px solid <?php echo $borde_izq; ?>; padding-left: 10px; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                <span class="tag-aviso" style="background: #f8f9fa; color: #555; border: 1px solid #ddd; font-weight:bold; font-size:0.75rem; padding: 3px 8px; border-radius: 12px;"><?php echo htmlspecialchars($t['materia_nombre']); ?></span>
                                <span style="<?php echo $badgeClass; ?> font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; font-weight: bold;"><?php echo $t['estatus']; ?></span>
                            </div>
                            <div style="margin-top: 8px;">
                                <strong style="color: #333; font-size:1.05rem;"><?php echo $icono; ?> <?php echo htmlspecialchars($t['titulo']); ?></strong>
                            </div>
                            <div style="margin-top: 5px; color: #666; font-size: 0.9rem;">
                                <?php echo recortar_texto(htmlspecialchars($t['descripcion']), 60); ?>
                            </div>
                            <div style="margin-top: 8px; font-size: 0.8rem; color: #888;">
                                <i class="far fa-calendar-alt"></i> Cierre: <strong><?php echo date('d/m/Y h:i A', strtotime($t['fecha_fin'])); ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align:center; color:#888;">No hay actividades registradas en este periodo.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL 2: DETALLE FLOTANTE DE LA TAREA INDIVIDUAL (Superpuesto) -->
    <div id="modalDetalleActividad" class="modal-overlay" style="z-index: 3100;">
        <div class="modal-card">
            <button class="close-btn" onclick="cerrarModalDetalle()">&times;</button>
            <div class="modal-card-body">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px; margin-right: 25px;">
                    <span id="modMateria" class="tag-aviso" style="background:#f8f9fa; color:#555; border:1px solid #ddd; font-weight:bold; font-size:0.8rem; padding:4px 10px; border-radius:12px;"></span>
                    <span id="modBadge"></span>
                </div>
                
                <h2 id="modTitulo" style="color:var(--udg-blue); margin-top:0; margin-bottom:15px; font-size:1.4rem; padding-right: 15px;"></h2>
                
                <div id="modDesc" class="texto-seguro" style="color:#444; font-size:1.05rem; line-height:1.6; margin-bottom:25px;"></div>
                
                <div style="background:#f4f8fb; padding:15px; border-radius:8px; border-left: 4px solid var(--udg-blue); font-size:0.9rem; color:#555;">
                    <i class="fas fa-chalkboard-teacher"></i> <strong>Publicado por:</strong> <span id="modProf"></span><br>
                    <i class="far fa-calendar-alt" style="margin-top: 8px;"></i> <strong>Fecha de Publicación:</strong> <span id="modInicio"></span><br>
                    <i class="far fa-calendar-check" style="margin-top: 8px;"></i> <strong>Fecha Límite:</strong> <span id="modFin"></span>
                </div>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-section">
                <div style="font-weight:bold; font-size:1.1rem; margin-bottom:5px;">CUCEA PALE</div>
                <i class="fab fa-facebook" style="opacity:0.8;"></i>
            </div>
            <div class="footer-section">
                 <img src="../img/logo-udg.png" alt="Universidad de Guadalajara" class="footer-logo-img">
            </div>
            <div class="footer-section">
                <strong>Contacto</strong><br>
                +52 (33)-3770-3300<br>
                <span style="font-size:0.85rem; opacity:0.9;">plataforma.pale@cucea.udg.mx</span>
            </div>
        </div>
        <div class="address-bar">
            Periférico Norte N° 799, Núcleo Universitario Los Belenes, C.P. 45100, Zapopan, Jalisco, México.<br>
            Copyright © 2026 E-PALE
        </div>
    </footer>

    <!-- Enlace al archivo JS -->
    <script src="../js/index_estudiante.js?v=<?php echo time(); ?>"></script>
    
</body>
</html>
