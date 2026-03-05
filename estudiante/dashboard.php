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

// 3. Obtener materias inscritas del alumno (Traemos m.materia_id para buscar sus máximos)
$sql_materias = "SELECT i.inscripcion_id, m.materia_id, m.nombre, m.nivel, g.nrc 
                 FROM inscripciones i
                 JOIN grupos g ON i.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'";
$stmt_mat = $pdo->prepare($sql_materias);
$stmt_mat->execute([$alumno_id]);
$materias_inscritas = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

// 4. Obtener horarios del alumno
$sql_horarios = "SELECT h.hora_inicio, h.hora_fin, h.aula, m.nombre, m.nivel 
                 FROM horarios h
                 JOIN grupos g ON h.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN inscripciones i ON g.nrc = i.nrc
                 WHERE i.alumno_id = ? ORDER BY h.hora_inicio ASC";
$stmt_hor = $pdo->prepare($sql_horarios);
$stmt_hor->execute([$alumno_id]);
$horarios = $stmt_hor->fetchAll(PDO::FETCH_ASSOC);
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

    <header class="main-header" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; height: 65px;">
        <div class="logo-container" style="display: flex; align-items: center; width: auto; margin: 0;">
            <a href="dashboard.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: white;">
                <img src="../img/logo-pale.png" alt="E-PALE" class="logo-img">
                <span style="font-size: 1.2rem; font-weight: bold;">e-PALE</span>
            </a>
        </div>

        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="perfil.php" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); padding: 5px 15px 5px 5px; border-radius: 20px;">
                <?php 
                $stmt_foto = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE usuario_id = ?");
                $stmt_foto->execute([$_SESSION['user_id']]);
                $user_foto = $stmt_foto->fetchColumn();
                
                if($user_foto && file_exists("../img/perfiles/" . $user_foto)): ?>
                    <img src="../img/perfiles/<?php echo $user_foto; ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid white;">
                <?php else: ?>
                    <i class="fas fa-user-circle" style="font-size: 1.8rem;"></i>
                <?php endif; ?>
                <span class="profile-name" style="font-weight: 500;"><?php echo strtok($_SESSION['nombre'], " "); ?></span>
            </a>

            <button onclick="toggleMobileMenu()" style="background: transparent; border: none; color: white; font-size: 1.8rem; cursor: pointer; padding: 0;">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <div class="menu-overlay" id="menuOverlay" onclick="toggleMobileMenu()"></div>

    <aside class="yt-sidebar" id="navWrapper">
        <div class="yt-sidebar-header">
            <span style="color: white; font-size: 1.1rem; font-weight: bold;">Menú Principal</span>
            <button onclick="toggleMobileMenu()" style="background: transparent; border: none; color: #aaa; font-size: 2rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <ul class="yt-sidebar-menu">
            <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="#"><i class="far fa-calendar-alt"></i> Horario</a></li>
            <li><a href="calificaciones.php"><i class="fas fa-star"></i> Calificaciones</a></li>
            <li><a href="oferta.php"><i class="fas fa-bullhorn"></i> Oferta</a></li>
        </ul>

        <div class="sidebar-divider"></div>

        <ul class="yt-sidebar-menu">
            <li><a href="perfil.php"><i class="far fa-user-circle"></i> Mi Perfil</a></li>
            <li><a href="../logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt" style="color: #ff6b6b;"></i> Cerrar Sesión</a></li>
        </ul>
    </aside>

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
                foreach($materias_inscritas as $index => $mat): 
                    $insc_id = $mat['inscripcion_id'];
                    $materia_id = $mat['materia_id'];
                    $display = ($index === 0) ? 'block' : 'none';
                    
                    // 1. OBTENER EL MÁXIMO DINÁMICAMENTE DE LA NUEVA TABLA
                    $stmt_max = $pdo->prepare("SELECT SUM(puntos_maximos) FROM criterios_evaluacion WHERE materia_id = ?");
                    $stmt_max->execute([$materia_id]);
                    $max_puntos = $stmt_max->fetchColumn();
                    
                    // Si el administrador aún no configura la materia, mostrar 0 en lugar de crashear
                    if (!$max_puntos) $max_puntos = 0; 

                    // 2. Sumar puntos actuales
                    $stmt_cal = $pdo->prepare("SELECT puntaje FROM calificaciones WHERE inscripcion_id = ?");
                    $stmt_cal->execute([$insc_id]);
                    $suma_puntos = 0;
                    while($row = $stmt_cal->fetch(PDO::FETCH_ASSOC)){
                        if($row['puntaje'] !== null) {
                            $suma_puntos += floatval($row['puntaje']);
                        }
                    }

                    // 3. Calcular porcentaje y colores (Evitando división entre cero)
                    if ($max_puntos > 0) {
                        $porcentaje = ($suma_puntos / $max_puntos) * 100;
                        if ($porcentaje > 100) $porcentaje = 100;
                    } else {
                        $porcentaje = 0;
                    }
                    
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
                </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <h3><i class="far fa-clock"></i> Próximas Clases</h3>
                <ul class="next-classes-list">
                    <?php if(count($horarios) > 0): ?>
                        <?php foreach($horarios as $h): ?>
                            <li>
                                <span><?php echo htmlspecialchars($h['nombre'] . ' ' . $h['nivel'] . ' - ' . $h['aula']); ?></span>
                                <span class="grade-value"><?php echo date('H:i', strtotime($h['hora_inicio'])) . ' - ' . date('H:i', strtotime($h['hora_fin'])); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li style="color:#888; justify-content:center;">No tienes clases asignadas.</li>
                    <?php endif; ?>
                </ul>
                <div style="margin-top: 25px; text-align: center;">
                    <button style="width: auto; padding: 10px 20px; background-color: white; border: 1px solid #ddd; border-radius: 6px; font-weight:bold; cursor:pointer;">Ver Horario Completo</button>
                </div>
            </div>

             <div class="card">
                <h3><i class="far fa-bell"></i> Avisos</h3>
                <div style="font-size: 0.9rem; color: #555;">
                    <?php if(count($materias_inscritas) > 0): ?>
                        <p style="margin-bottom: 15px; padding-bottom:15px; border-bottom:1px solid #eee;">
                            <span class="tag-aviso tag-materia"><?php echo htmlspecialchars($materias_inscritas[0]['nombre'] . ' ' . $materias_inscritas[0]['nivel']); ?></span><br>
                            <strong style="color: #002677;">Recordatorio:</strong> La fecha límite para subir el Writing Project es el próximo viernes.
                        </p>
                    <?php endif; ?>
                    <p style="margin-bottom: 15px; padding-bottom:15px; border-bottom:1px solid #eee;">
                        <span class="tag-aviso tag-sistema">Sistema</span><br>
                        <strong>Aviso:</strong> Mantenimiento de la plataforma Moodle este sábado.
                    </p>
                    <p>
                        <span class="tag-aviso tag-sistema">Academia</span><br>
                        <span style="color:#666; font-size:0.85rem;">Las inscripciones para el siguiente semestre abren el 3 de marzo.</span>
                    </p>
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

<?php
function format_score($num) {
    return floatval($num) == intval($num) ? intval($num) : floatval($num);
}
?>
