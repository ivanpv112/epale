<?php
session_start();
require '../db.php';

// SEGURIDAD: Solo Profesores
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') {
    header("Location: ../index.php"); exit;
}

$profesor_id = $_SESSION['user_id'];
$nombre_profesor = $_SESSION['nombre'] . ' ' . $_SESSION['apellido_paterno'];

// 1. OBTENER GRUPOS ACTIVOS (AHORA TAMBIÉN FILTRA g.estado = 'ACTIVO')
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
                      MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_fin END) AS fin_v,
                      (SELECT COUNT(DISTINCT i.alumno_id) FROM inscripciones i JOIN grupos g2 ON i.nrc = g2.nrc WHERE g2.clave_grupo = g.clave_grupo AND i.estatus = 'INSCRITO') AS inscritos
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

// 2. CALCULAR ESTADÍSTICAS
$total_alumnos = 0;
$total_grupos = count($grupos);
foreach ($grupos as $g) { $total_alumnos += $g['inscritos']; }

// 3. DETERMINAR QUÉ CLASES TIENE "HOY"
$dia_num = date('N'); 
$letra_hoy = '';
switch($dia_num) {
    case 1: $letra_hoy = 'L'; break; case 2: $letra_hoy = 'M'; break; case 3: $letra_hoy = 'I'; break;
    case 4: $letra_hoy = 'J'; break; case 5: $letra_hoy = 'V'; break;
}

$clases_hoy = [];
if ($letra_hoy !== '') {
    foreach ($grupos as $g) {
        if ($g['dias_p']) {
            $dias_p = strtoupper($g['dias_p']);
            if (strpos($dias_p, $letra_hoy) !== false || ($letra_hoy == 'I' && (strpos($dias_p, 'MIE') !== false || strpos($dias_p, 'X') !== false))) {
                $clases_hoy[] = ['materia' => $g['materia'] . ' ' . $g['nivel'], 'aula' => $g['aula_p'] ?: 'Sin Aula', 'inicio' => $g['inicio_p'], 'fin' => $g['fin_p'], 'tipo' => 'Presencial'];
            }
        }
        if ($g['dias_v']) {
            $dias_v = strtoupper($g['dias_v']);
            if (strpos($dias_v, $letra_hoy) !== false || ($letra_hoy == 'I' && (strpos($dias_v, 'MIE') !== false || strpos($dias_v, 'X') !== false))) {
                $clases_hoy[] = ['materia' => $g['materia'] . ' ' . $g['nivel'], 'aula' => $g['aula_v'] ?: 'Virtual', 'inicio' => $g['inicio_v'], 'fin' => $g['fin_v'], 'tipo' => 'Virtual'];
            }
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
</head>
<body>
    <?php include 'menu_profesor.php'; ?>
    <main class="main-content">
        <div class="dash-header"><h1>Panel del Profesor</h1><p>Prof. <?php echo htmlspecialchars($nombre_profesor); ?></p></div>
        <div class="stats-row">
            <div class="stat-box"><i class="fas fa-users"></i><div><div class="number"><?php echo $total_alumnos; ?></div><div class="label">Alumnos Totales</div></div></div>
            <div class="stat-box"><i class="fas fa-book-open"></i><div><div class="number"><?php echo $total_grupos; ?></div><div class="label">Grupos Activos</div></div></div>
            <div class="stat-box"><i class="fas fa-clipboard-list"></i><div><div class="number">3</div><div class="label">Tareas Pendientes</div></div></div>
        </div>

        <div class="main-grid">
            <div class="content-card" style="overflow-x: auto;">
                <h3 class="card-title"><i class="fas fa-chalkboard"></i> Mis Grupos Activos</h3>
                <table class="prof-table">
                    <thead><tr><th>Materia</th><th>Nivel</th><th>Alumnos</th><th>Horario</th><th>Aula</th></tr></thead>
                    <tbody>
                        <?php if($total_grupos > 0): ?>
                            <?php foreach($grupos as $g): 
                                $horario_resumen = '---'; $aula_resumen = '---';
                                if($g['dias_p']) { $horario_resumen = $g['dias_p'] . ' ' . date('H:i', strtotime($g['inicio_p'])) . '-' . date('H:i', strtotime($g['fin_p'])); $aula_resumen = $g['aula_p']; } 
                                elseif($g['dias_v']) { $horario_resumen = $g['dias_v'] . ' ' . date('H:i', strtotime($g['inicio_v'])) . '-' . date('H:i', strtotime($g['fin_v'])); $aula_resumen = $g['aula_v'] ?: 'Virtual'; }
                            ?>
                                <tr onclick="window.location.href='detalle_grupo.php?clave=<?php echo $g['clave_grupo']; ?>'">
                                    <td style="font-weight: bold; color: var(--udg-blue);"><?php echo htmlspecialchars($g['materia']); ?></td>
                                    <td>Nivel <?php echo htmlspecialchars($g['nivel']); ?></td>
                                    <td style="font-weight: bold;"><?php echo $g['inscritos']; ?></td>
                                    <td style="color: #555; font-size: 0.85rem;"><?php echo htmlspecialchars($horario_resumen); ?></td>
                                    <td style="font-family: monospace; color: #888;"><?php echo htmlspecialchars($aula_resumen); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; color:#aaa; padding: 30px;">No tienes grupos activos asignados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="content-card">
                <h3 class="card-title"><i class="fas fa-tasks"></i> Tareas Pendientes</h3>
                <div class="task-item task-urgent"><div class="task-title">Calificar Quizzes</div><div class="task-meta">Fecha límite: Hoy</div></div>
                <div class="task-item"><div class="task-title">Revisión Writing</div><div class="task-meta">Próximamente</div></div>
            </div>
        </div>

        <div class="content-card">
            <h3 class="card-title"><i class="far fa-calendar-check"></i> Próximas Clases (Hoy)</h3>
            <?php if(count($clases_hoy) > 0): ?>
                <div class="today-classes">
                    <?php foreach($clases_hoy as $c): ?>
                        <div class="class-card">
                            <div><div class="class-name"><?php echo htmlspecialchars($c['materia']); ?></div><div class="class-room"><?php echo ($c['tipo'] == 'Presencial') ? '<i class="fas fa-building" style="color:#28a745;"></i>' : '<i class="fas fa-laptop-house" style="color:#17a2b8;"></i>'; ?> <?php echo htmlspecialchars($c['aula']); ?></div></div>
                            <div class="class-time"><?php echo date('H:i', strtotime($c['inicio'])) . ' - ' . date('H:i', strtotime($c['fin'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><p style="color: #888; margin: 0;">No tienes clases programadas para hoy.</p><?php endif; ?>
        </div>
    </main>
    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Portal de Profesores</div></footer>
</body>
</html>
