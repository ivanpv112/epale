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

// 3. Obtener materias inscritas para el menú desplegable
$sql_materias = "SELECT i.inscripcion_id, m.nombre AS materia, m.nivel, c.nombre AS ciclo,
                        u.nombre AS prof_nombre, u.apellidos AS prof_apellidos
                 FROM inscripciones i
                 JOIN grupos g ON i.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                 JOIN usuarios u ON g.profesor_id = u.usuario_id
                 WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'";
$stmt_mat = $pdo->prepare($sql_materias);
$stmt_mat->execute([$alumno_id]);
$materias_inscritas = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

// 4. Determinar qué materia mostrar (por defecto la primera)
$ins_activa = isset($_GET['ins']) ? $_GET['ins'] : (count($materias_inscritas) > 0 ? $materias_inscritas[0]['inscripcion_id'] : null);

$materia_actual = null;
foreach ($materias_inscritas as $m) {
    if ($m['inscripcion_id'] == $ins_activa) {
        $materia_actual = $m;
        break;
    }
}

// 5. Configuración de evaluación (BASE)
$evaluacion = [
    'Quizzes' => [
        'icono' => 'fa-book-open',
        'color' => 'var(--udg-light)', 
        'items' => [
            'Q1' => ['nombre' => 'Quiz 1', 'max' => 10],
            'Q2' => ['nombre' => 'Quiz 2', 'max' => 10],
            'Q3' => ['nombre' => 'Quiz 3', 'max' => 10]
        ]
    ],
    'Quizzes Orales' => [
        'icono' => 'fa-comments',
        'color' => 'var(--success)', 
        'items' => [
            'QO1' => ['nombre' => 'Quiz Oral 1', 'max' => 5],
            'QO2' => ['nombre' => 'Quiz Oral 2', 'max' => 5]
        ]
    ],
    'Proyectos' => [
        'icono' => 'fa-file-signature',
        'color' => 'var(--warning)', 
        'items' => [
            'WRITING' => ['nombre' => 'Writing Project', 'max' => 5]
        ]
    ],
    'Plataforma' => [
        'icono' => 'fa-laptop-code',
        'color' => '#dc3545', 
        'items' => [
            'PLATAFORMA' => ['nombre' => 'Actividades Moodle', 'max' => 40]
        ]
    ],
    'Participación' => [
        'icono' => 'fa-hand-paper',
        'color' => '#17a2b8', 
        'items' => [
            'PARTICIPACION' => ['nombre' => 'Participación en clase', 'max' => 5]
        ]
    ]
];

// --- 5.1 MAGIA CONDICIONAL: AGREGAR TOEFL SOLO PARA INGLÉS 4 ---
// Se usan strips para buscar "ingl" y evitar problemas con los acentos
if ($materia_actual && stripos($materia_actual['materia'], 'ingl') !== false && $materia_actual['nivel'] == 4) {
    $evaluacion['Certificación'] = [
        'icono' => 'fa-certificate',
        'color' => '#6f42c1', // Un color morado elegante para diferenciarlo
        'items' => [
            'TOEFL' => ['nombre' => 'Examen TOEFL', 'max' => 15] // Valor máximo del TOEFL
        ]
    ];
}

// --- 5.2 CALCULAR PUNTOS TOTALES DINÁMICAMENTE ---
$puntos_totales_posibles = 0;
foreach($evaluacion as $cat) {
    foreach($cat['items'] as $item) {
        $puntos_totales_posibles += $item['max'];
    }
}

// 6. Obtener las calificaciones de la materia activa
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
// Evitar división por cero
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

    <header class="main-header">
        <div class="logo-container">
            <img src="../img/logo-pale.png" alt="E-PALE" class="logo-img">
            <span>e-PALE</span>
        </div>
        
        <nav class="main-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
                <li><a href="#"><i class="far fa-calendar-alt"></i> Horario</a></li>
                <li><a href="calificaciones.php" style="color:white; font-weight:bold;"><i class="fas fa-star"></i> Calificaciones</a></li>
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

    <main class="main-content" style="max-width: 800px;"> 
        
        <?php if($materia_actual): ?>
            <div class="page-title-center">
                <h1><i class="fas fa-award"></i> Calificaciones</h1>
                <p><?php echo htmlspecialchars($materia_actual['materia'] . ' ' . $materia_actual['nivel'] . ' - Semestre ' . $materia_actual['ciclo']); ?></p>
                <p style="color: var(--text-dark); font-weight: 500; margin-top: 10px;">
                    <i class="fas fa-chalkboard-teacher" style="color:#aaa;"></i> Profesor: <?php echo htmlspecialchars($materia_actual['prof_nombre'] . ' ' . $materia_actual['prof_apellidos']); ?>
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

        <?php else: ?>
            <div style="text-align:center; padding:50px; color:#888;">
                <h2>No estás inscrito en ninguna materia.</h2>
            </div>
        <?php endif; ?>

    </main>

    <footer class="main-footer">
        <div class="address-bar" style="border:none; padding-top:0;">Copyright © 2026 E-PALE</div>
    </footer>

</body>
</html>

<?php
function format_score($num) {
    return floatval($num) == intval($num) ? intval($num) : floatval($num);
}
?>