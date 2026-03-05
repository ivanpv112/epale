<?php
session_start();
require '../db.php';

// 1. Seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}

// 2. Obtener el alumno_id
$stmt_al = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
$stmt_al->execute([$_SESSION['user_id']]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);
$alumno_id = $alumno['alumno_id'];

// 3. Obtener materias inscritas (Agregamos m.materia_id para poder buscar sus criterios)
$sql_materias = "SELECT i.inscripcion_id, m.materia_id, m.nombre AS materia, m.nivel, c.nombre AS ciclo,
                        u.nombre AS prof_nombre, u.apellido_paterno AS prof_ap_pat, u.apellido_materno AS prof_ap_mat
                 FROM inscripciones i
                 JOIN grupos g ON i.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                 JOIN usuarios u ON g.profesor_id = u.usuario_id
                 WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'";
$stmt_mat = $pdo->prepare($sql_materias);
$stmt_mat->execute([$alumno_id]);
$materias_inscritas = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

// 4. Determinar qué materia mostrar
$ins_activa = isset($_GET['ins']) ? $_GET['ins'] : (count($materias_inscritas) > 0 ? $materias_inscritas[0]['inscripcion_id'] : null);

$materia_actual = null;
foreach ($materias_inscritas as $m) {
    if ($m['inscripcion_id'] == $ins_activa) {
        $materia_actual = $m;
        break;
    }
}

// 5. MAGIA DINÁMICA: Leer los criterios desde la Base de Datos
$evaluacion = [];
$puntos_totales_posibles = 0;

if ($materia_actual) {
    // Le preguntamos a la base de datos: ¿Cuáles son las reglas para esta materia en específico?
    $stmt_crit = $pdo->prepare("SELECT * FROM criterios_evaluacion WHERE materia_id = ? ORDER BY criterio_id ASC");
    $stmt_crit->execute([$materia_actual['materia_id']]);
    $criterios_bd = $stmt_crit->fetchAll(PDO::FETCH_ASSOC);

    foreach ($criterios_bd as $crit) {
        $cat = $crit['categoria'];
        // Si la categoría (ej. Quizzes) no existe en nuestro arreglo, la creamos
        if (!isset($evaluacion[$cat])) {
            $evaluacion[$cat] = [
                'icono' => $crit['icono'],
                'color' => $crit['color'],
                'items' => []
            ];
        }
        // Metemos el examen dentro de su categoría
        $evaluacion[$cat]['items'][$crit['codigo_examen']] = [
            'nombre' => $crit['nombre_examen'],
            'max' => floatval($crit['puntos_maximos'])
        ];
        // Vamos sumando el total máximo
        $puntos_totales_posibles += floatval($crit['puntos_maximos']);
    }
}

// 6. Obtener las calificaciones que el alumno ya sacó de la BD
$calificaciones_bd = [];
$puntaje_total_acumulado = 0;

if ($ins_activa) {
    $stmt_cal = $pdo->prepare("SELECT tipo_examen, puntaje FROM calificaciones WHERE inscripcion_id = ?");
    $stmt_cal->execute([$ins_activa]);
    while ($row = $stmt_cal->fetch(PDO::FETCH_ASSOC)) {
        $calificaciones_bd[$row['tipo_examen']] = $row['puntaje'];
        if ($row['puntaje'] !== null) {
            $puntaje_total_acumulado += floatval($row['puntaje']);
        }
    }
}
$porcentaje_total = $puntos_totales_posibles > 0 ? ($puntaje_total_acumulado / $puntos_totales_posibles) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificaciones | E-Pale</title>
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
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="#"><i class="far fa-calendar-alt"></i> Horario</a></li>
            <li><a href="calificaciones.php" class="active"><i class="fas fa-star"></i> Calificaciones</a></li>
            <li><a href="oferta.php"><i class="fas fa-bullhorn"></i> Oferta</a></li>
        </ul>
        <div class="sidebar-divider"></div>
        <ul class="yt-sidebar-menu">
            <li><a href="perfil.php"><i class="far fa-user-circle"></i> Mi Perfil</a></li>
            <li><a href="../logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt" style="color: #ff6b6b;"></i> Cerrar Sesión</a></li>
        </ul>
    </aside>

    <main class="main-content" style="max-width: 800px;"> 
        
        <?php if($materia_actual): ?>
            <div class="page-title-center">
                <h1><i class="fas fa-award"></i> Calificaciones</h1>
                <p><?php echo htmlspecialchars($materia_actual['materia'] . ' ' . $materia_actual['nivel'] . ' - Semestre ' . $materia_actual['ciclo']); ?></p>
                <p style="color: var(--text-dark); font-weight: 500; margin-top: 10px;">
                    <i class="fas fa-chalkboard-teacher" style="color:#aaa;"></i> Profesor: 
                    <?php echo htmlspecialchars(trim($materia_actual['prof_nombre'] . ' ' . $materia_actual['prof_ap_pat'] . ' ' . $materia_actual['prof_ap_mat'])); ?>
                </p>

                <?php if(count($materias_inscritas) > 0): ?>
                    <div style="margin-top: 20px;">
                        <select class="subject-selector" style="font-size: 1rem; padding: 10px 20px; text-align: center; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #ddd;" onchange="window.location.href='calificaciones.php?ins='+this.value">
                            <?php foreach($materias_inscritas as $m): ?>
                                <option value="<?php echo $m['inscripcion_id']; ?>" <?php echo ($m['inscripcion_id'] == $ins_activa) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['materia'] . ' ' . $m['nivel'] . ' - ' . $m['ciclo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <?php if(empty($evaluacion)): ?>
                <div style="text-align:center; padding:50px; background:white; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
                    <i class="fas fa-tools" style="font-size: 3rem; color: #ccc; margin-bottom: 15px;"></i>
                    <h3 style="color:#666;">Criterios de evaluación no disponibles</h3>
                    <p style="color:#999; font-size: 0.9rem;">El administrador aún no ha configurado cómo se calificará esta materia.</p>
                </div>
            <?php else: ?>

                <div class="total-score-banner">
                    <h3>Calificación Total Acumulada</h3>
                    <span class="big-score"><?php echo format_score($puntaje_total_acumulado); ?></span>
                    <p>de <?php echo $puntos_totales_posibles; ?> puntos posibles</p>
                    <div class="total-progress-bg">
                        <div class="total-progress-fill" style="width: <?php echo $porcentaje_total; ?>%;"></div>
                    </div>
                </div>

                <?php foreach($evaluacion as $categoria => $datos_cat): ?>
                    <div class="grade-category-card" style="border-top-color: <?php echo $datos_cat['color']; ?>;">
                        <h4><i class="fas <?php echo $datos_cat['icono']; ?>"></i> <?php echo $categoria; ?></h4>
                        <?php foreach($datos_cat['items'] as $codigo => $item): 
                            $esta_registrada = array_key_exists($codigo, $calificaciones_bd);
                            $puntaje = $esta_registrada ? $calificaciones_bd[$codigo] : null;
                            $max = $item['max'];
                            
                            if ($puntaje === null) {
                                $html_score = '<span class="badge-pending">Pendiente</span>';
                                $html_progress = '';
                            } else {
                                $porcentaje = ($puntaje / $max) * 100;
                                $color = ($porcentaje >= 60) ? '#28a745' : '#dc3545';
                                
                                $html_score = '<span class="score-text">' . format_score($puntaje) . ' / ' . $max . '</span>';
                                $html_progress = '<div class="progress-mini"><div class="progress-bar" style="width: ' . $porcentaje . '%; background-color:' . $color . ';"></div></div>';
                            }
                        ?>
                            <div class="detailed-grade-item">
                                <div class="dg-info">
                                    <strong><?php echo $item['nombre']; ?></strong>
                                    <?php if($puntaje !== null): ?>
                                        <span>Calificado</span>
                                    <?php else: ?>
                                        <span>Aún no evaluado</span>
                                    <?php endif; ?>
                                </div>
                                <div class="dg-score">
                                    <?php echo $html_score; ?>
                                    <?php echo $html_progress; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

            <?php endif; // Fin comprobación si hay criterios ?>

        <?php else: ?>
            <div style="text-align:center; padding:50px; color:#888;"><h2>No estás inscrito en ninguna materia.</h2></div>
        <?php endif; ?>
    </main>

    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }
    </script>
</body>
</html>

<?php
function format_score($num) { return floatval($num) == intval($num) ? intval($num) : floatval($num); }
?>
