<?php
session_start();
require '../db.php'; // CONEXIÓN A LA BD

// 1. Seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}
$nombre_completo = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : "Estudiante";
// Compatible con ambas estructuras: nueva y antigua
if(isset($_SESSION['apellido_paterno'])) { 
    $nombre_completo .= " " . $_SESSION['apellido_paterno']; 
    if(isset($_SESSION['apellido_materno']) && !empty($_SESSION['apellido_materno'])) {
        $nombre_completo .= " " . $_SESSION['apellido_materno'];
    }
} elseif(isset($_SESSION['apellidos'])) {
    $nombre_completo .= " " . $_SESSION['apellidos'];
}

// 2. Obtener el alumno_id
$stmt_al = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
$stmt_al->execute([$_SESSION['user_id']]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);
$alumno_id = $alumno['alumno_id'];

// 3. Obtener materias inscritas del alumno
$sql_materias = "SELECT i.inscripcion_id, m.nombre, m.nivel, g.nrc 
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

// 5. PLANTILLA FIJA PARA EL DASHBOARD (Asegura que siempre salgan los 5 apartados)
$estructura_dashboard = [
    'quizzes' => ['label' => 'Quizzes (3)', 'icon' => 'fa-pen-alt', 'max' => 30, 'keys' => ['Q1', 'Q2', 'Q3']],
    'orales'  => ['label' => 'Quizzes Orales (2)', 'icon' => 'fa-comments', 'max' => 20, 'keys' => ['QO1', 'QO2']],
    'writing' => ['label' => 'Writing Project', 'icon' => 'fa-file-signature', 'max' => 20, 'keys' => ['WRITING']],
    'moodle'  => ['label' => 'Plataforma Moodle', 'icon' => 'fa-laptop-code', 'max' => 40, 'keys' => ['PLATAFORMA']],
    'partic'  => ['label' => 'Participación', 'icon' => 'fa-hand-paper', 'max' => 10, 'keys' => ['PARTICIPACION']]
];
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

    <header class="main-header">
        <div class="logo-container">
            <img src="../img/logo-pale.png" alt="E-PALE" class="logo-img">
            <span>e-PALE</span>
        </div>
        
        <nav class="main-nav">
            <ul>
                <li><a href="dashboard.php" style="color:white; font-weight:bold;"><i class="fas fa-home"></i> Inicio</a></li>
                <li><a href="#"><i class="far fa-calendar-alt"></i> Horario</a></li>
                <li><a href="calificaciones.php"><i class="fas fa-star"></i> Calificaciones</a></li>
                <li><a href="oferta.php"><i class="fas fa-bullhorn"></i> Oferta</a></li>
            </ul>
        </nav>

        <div class="user-actions">
            <a href="perfil.php" class="profile-btn">
                <i class="fas fa-user-circle"></i>
                <span><?php echo strtok($_SESSION['nombre'], " "); ?></span>
            </a>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Salir
            </a>
        </div>
    </header>

    <main class="main-content">
        
        <div class="welcome-banner">
            <h2>Página de inicio</h2>
            <h1><?php echo htmlspecialchars($nombre_completo); ?></h1>
        </div>

        <div class="dashboard-grid">
            
            <div class="card">
                <h3 style="display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fas fa-chart-line"></i> Evaluación Continua</span>
                    
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
                // Generar un contenedor oculto/visible por cada materia
                foreach($materias_inscritas as $index => $mat): 
                    $insc_id = $mat['inscripcion_id'];
                    $display = ($index === 0) ? 'block' : 'none';
                    
                    // 1. Traer todas las calificaciones de esta materia desde la BD
                    $stmt_cal = $pdo->prepare("SELECT tipo_examen, puntaje FROM calificaciones WHERE inscripcion_id = ?");
                    $stmt_cal->execute([$insc_id]);
                    $califs_bd = [];
                    while($row = $stmt_cal->fetch(PDO::FETCH_ASSOC)){
                        $califs_bd[$row['tipo_examen']] = $row['puntaje'];
                    }
                ?>
                
                <div id="eval-<?php echo $insc_id; ?>" class="grades-summary eval-container" style="display: <?php echo $display; ?>;">
                    
                    <?php 
                    // 2. Recorrer la plantilla fija para dibujar siempre los 5 apartados
                    foreach($estructura_dashboard as $id_cat => $cat): 
                        $suma = 0;
                        $tiene_datos = false;
                        
                        // Sumar los puntos si existen en la BD (ej. Q1 + Q2 + Q3)
                        foreach($cat['keys'] as $key_examen) {
                            if(array_key_exists($key_examen, $califs_bd) && $califs_bd[$key_examen] !== null) {
                                $suma += floatval($califs_bd[$key_examen]);
                                $tiene_datos = true;
                            }
                        }

                        // Lógica visual: Si es Writing y no hay datos, mostrar "Pendiente"
                        if($id_cat == 'writing' && !$tiene_datos) {
                            $texto_valor = '<span style="background:#fff3cd; color:#856404; padding:2px 6px; border-radius:4px; font-size:0.8rem;">Pendiente</span>';
                            $porcentaje = 0;
                            $color_barra = '#eee';
                        } else {
                            // Mostrar la suma de puntos (quitando decimales si es exacto)
                            $suma_fmt = floatval($suma) == intval($suma) ? intval($suma) : floatval($suma);
                            $texto_valor = $suma_fmt . ' / ' . $cat['max'] . ' pts';
                            
                            $porcentaje = ($suma / $cat['max']) * 100;
                            if($porcentaje == 0) $color_barra = '#eee';
                            elseif($porcentaje >= 60) $color_barra = 'var(--success)'; // Verde
                            else $color_barra = '#ffc107'; // Amarillo/Naranja si va bajo
                        }
                    ?>
                        <div class="grade-item">
                            <div class="grade-label"><i class="fas <?php echo $cat['icon']; ?>"></i> <?php echo $cat['label']; ?></div>
                            <div class="grade-value"><?php echo $texto_valor; ?></div>
                        </div>
                        <div class="progress-mini"><div class="progress-bar" style="width: <?php echo $porcentaje; ?>%; background-color: <?php echo $color_barra; ?>;"></div></div>
                    <?php endforeach; ?>

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