<?php
session_start();
require '../db.php'; 

// 1. Seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}

// 2. Obtener el alumno_id y el nombre real desde la BD
$stmt_al = $pdo->prepare("SELECT a.alumno_id, u.nombre, u.apellido_paterno, u.apellido_materno 
                          FROM alumnos a 
                          JOIN usuarios u ON a.usuario_id = u.usuario_id 
                          WHERE a.usuario_id = ?");
$stmt_al->execute([$_SESSION['user_id']]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);

$alumno_id = $alumno['alumno_id'];
$nombre_completo = trim($alumno['nombre'] . ' ' . $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno']);

// 3. Obtener materias inscritas del alumno 
$sql_materias = "SELECT i.inscripcion_id, m.materia_id, m.nombre, m.nivel, g.nrc 
                 FROM inscripciones i
                 JOIN grupos g ON i.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'";
$stmt_mat = $pdo->prepare($sql_materias);
$stmt_mat->execute([$alumno_id]);
$materias_inscritas = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

// 4. LÓGICA INTELIGENTE DE PRÓXIMAS CLASES (BLINDADA PARA PHP 8)
$dias_map = [ 'Monday' => 'L', 'Tuesday' => 'M', 'Wednesday' => 'I', 'Thursday' => 'J', 'Friday' => 'V', 'Saturday' => 'S', 'Sunday' => 'D' ];
$dia_hoy_letra = $dias_map[date('l')]; 

$sql_horarios = "SELECT h.hora_inicio, h.hora_fin, h.aula, h.modalidad, h.dias_patron, m.nombre, m.nivel 
                 FROM horarios h
                 JOIN grupos g ON h.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN inscripciones i ON g.nrc = i.nrc
                 WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'
                 ORDER BY h.hora_inicio ASC";
$stmt_hor = $pdo->prepare($sql_horarios);
$stmt_hor->execute([$alumno_id]);
$todos_horarios = $stmt_hor->fetchAll(PDO::FETCH_ASSOC);

$clases_hoy = [];
foreach ($todos_horarios as $h) {
    // Nos aseguramos de convertir el valor a String, incluso si es null
    $patron = isset($h['dias_patron']) ? (string)$h['dias_patron'] : '';
    
    // Eliminamos números, guiones y espacios (Solo letras)
    $solo_letras = preg_replace('/[^A-Za-z]/', '', strtoupper($patron));
    
    // CIBERSEGURIDAD PHP 8: Evitar el ValueError asegurando que NO esté vacío
    if (!empty($solo_letras) && strlen($solo_letras) > 0) {
        $dias_clase = str_split($solo_letras);
        if (in_array($dia_hoy_letra, $dias_clase)) {
            $clases_hoy[] = $h;
        }
    }
}

// CREACIÓN DE FECHA EN ESPAÑOL (Evita usar strftime que está obsoleto)
$meses_es = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$dias_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$fecha_texto_es = $dias_es[date('w')] . ', ' . date('d') . ' de ' . $meses_es[date('n') - 1];


// 5. OBTENER NOTIFICACIONES DINÁMICAS (CADUCAN A LOS 15 DÍAS AUTOMÁTICAMENTE)
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
                        <span style="font-size:0.8rem; color:#888;">Sin materias</span>
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
                    
                    // 1. Obtener máximo
                    $stmt_max = $pdo->prepare("SELECT SUM(puntos_maximos) FROM criterios_evaluacion WHERE materia_id = ?");
                    $stmt_max->execute([$materia_id]);
                    $max_puntos = $stmt_max->fetchColumn() ?: 0;
                    
                    // 2. Sumar puntos
                    $stmt_cal = $pdo->prepare("SELECT puntaje FROM calificaciones WHERE inscripcion_id = ?");
                    $stmt_cal->execute([$insc_id]);
                    $suma_puntos = 0;
                    while($row = $stmt_cal->fetch(PDO::FETCH_ASSOC)){
                        if($row['puntaje'] !== null) $suma_puntos += floatval($row['puntaje']);
                    }

                    // 3. Calcular porcentaje
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
                        <button onclick="window.location.href='calificaciones.php?ins=<?php echo $insc_id; ?>'" style="padding: 8px 15px; background: transparent; border: 1px solid var(--udg-blue); color: var(--udg-blue); border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s;">Criterios</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

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
                            No tienes clases programadas para hoy.
                        </div>
                    <?php endif; ?>
                </ul>
                <div style="margin-top: 25px; text-align: center;">
                    <button onclick="window.location.href='horario.php'" style="width: auto; padding: 10px 20px; background-color: white; border: 1px solid #ddd; border-radius: 6px; font-weight:bold; cursor:pointer; color: #555; transition: 0.2s;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">Ver Horario Completo</button>
                </div>
            </div>

             <div class="card">
                <h3><i class="far fa-bell"></i> Avisos Recientes</h3>
                <div style="font-size: 0.9rem; color: #555;">
                    
                    <?php foreach($avisos_dinamicos as $aviso): ?>
                        <div class="aviso-item">
                            <span class="tag-aviso tag-sistema" style="background: #fff3cd; color: #856404; border: 1px solid #ffeeba;">Administración</span>
                            <?php if($aviso['estatus'] == 'APROBADA'): ?>
                                <span class="tag-aprobada" style="float: right;">Aprobada</span><br>
                                <strong style="color: #333;">Solicitud de Baja:</strong> Tu petición para abandonar <strong style="color:var(--udg-blue);"><?php echo htmlspecialchars($aviso['nombre'] . ' ' . $aviso['nivel']); ?></strong> fue aprobada.
                            <?php else: ?>
                                <span class="tag-rechazada" style="float: right;">Rechazada</span><br>
                                <strong style="color: #333;">Solicitud de Baja:</strong> Tu petición para abandonar <strong style="color:var(--udg-blue);"><?php echo htmlspecialchars($aviso['nombre'] . ' ' . $aviso['nivel']); ?></strong> fue rechazada. Consulta los detalles en tus calificaciones.
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="aviso-item">
                        <span class="tag-aviso tag-sistema">Sistema</span><br>
                        <strong>Aviso Global:</strong> Mantenimiento de la plataforma Moodle programado para este sábado a las 00:00 hrs.
                    </div>
                    
                    <?php if(count($materias_inscritas) == 0 && count($avisos_dinamicos) == 0): ?>
                        <div style="text-align:center; padding: 20px 0; color:#aaa; font-style: italic;">
                            No tienes notificaciones nuevas.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

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

    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }

        function cambiarMateriaDash(idContenedor) {
            let contenedores = document.querySelectorAll('.eval-container');
            contenedores.forEach(function(cont) {
                cont.style.display = 'none';
            });
            document.getElementById(idContenedor).style.display = 'block';
        }
    </script>
</body>
</html>
