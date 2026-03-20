<?php
session_start();
require '../db.php';

// SEGURIDAD: Solo Profesores
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') { 
    header("Location: ../index.php"); exit; 
}

$profesor_id = $_SESSION['user_id'];
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$where_extra = ""; 
$params = [$profesor_id];

if ($search !== '') { 
    $where_extra = " AND m.nombre LIKE ?"; 
    $params[] = "%" . $search . "%"; 
}

// OBTENER GRUPOS (Ahora incluye g.estado)
$sql = "SELECT g.clave_grupo, m.nombre AS materia, m.nivel, c.nombre AS ciclo, c.activo, g.estado, g.materia_id, g.ciclo_id, g.profesor_id,
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
               (SELECT COUNT(DISTINCT i.alumno_id) 
                FROM inscripciones i 
                JOIN grupos g2 ON i.nrc = g2.nrc 
                WHERE g2.clave_grupo = g.clave_grupo AND i.estatus = 'INSCRITO') AS inscritos
        FROM grupos g
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        LEFT JOIN horarios h ON g.nrc = h.nrc
        WHERE g.profesor_id = ? $where_extra
        GROUP BY g.clave_grupo, m.nombre, m.nivel, c.nombre, c.activo, g.estado, g.materia_id, g.ciclo_id, g.profesor_id
        ORDER BY c.activo DESC, g.estado ASC, c.nombre DESC, m.nivel ASC";

$stmt = $pdo->prepare($sql); 
$stmt->execute($params); 
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Grupos | Portal Docente</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profesor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    
    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1 style="color: var(--udg-blue); font-size: 2.2rem; margin-bottom: 5px;"><i class="fas fa-chalkboard-teacher"></i> Mis Grupos</h1>
            <p style="color: #666;">Selecciona una clase para ver la lista de alumnos y registrar calificaciones.</p>
        </div>

        <form class="toolbar" method="GET" action="mis_grupos.php" style="margin-bottom: 20px; max-width: 600px; margin-left: auto; margin-right: auto; display: flex; gap: 10px;">
            <div style="display: flex; align-items: center; flex-grow: 1; border: 1px solid #ddd; border-radius: 6px; padding: 0 15px; background: white;">
                <i class="fas fa-search" style="color:#aaa;"></i>
                <input type="text" name="q" placeholder="Buscar materia..." value="<?php echo htmlspecialchars($search); ?>" style="border: none; outline: none; padding: 12px; width: 100%;">
            </div>
            <button type="submit" class="btn-save"><i class="fas fa-search"></i> Buscar</button>
            <?php if($search !== ''): ?>
                <a href="mis_grupos.php" class="btn-cancel" style="text-decoration: none; display:flex; align-items:center;">Limpiar</a>
            <?php endif; ?>
        </form>

        <div class="content-card" style="padding: 0; overflow: hidden;">
            <div style="overflow-x:auto;">
                <table class="prof-table" style="margin: 0;">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th style="padding: 15px 20px;">Semestre</th>
                            <th style="padding: 15px 20px;">Materia</th>
                            <th style="padding: 15px 20px; text-align: center;">Estudiantes</th>
                            <th style="padding: 15px 20px; text-align: center;">Horario</th>
                            <th style="padding: 15px 20px; text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($grupos) > 0): ?>
                            <?php foreach ($grupos as $g): 
                                $esta_activa = ($g['activo'] == 1 && $g['estado'] == 'ACTIVO');
                                $opacidad = $esta_activa ? '1' : '0.6'; 
                                $bg_tr = $esta_activa ? '#fff' : '#fcfcfc';
                            ?>
                                <tr style="background-color: <?php echo $bg_tr; ?>; opacity: <?php echo $opacidad; ?>;" onclick="window.location.href='detalle_grupo.php?clave=<?php echo $g['clave_grupo']; ?>'">
                                    
                                    <td style="padding: 15px 20px; font-weight: bold; color: #555;">
                                        <?php echo htmlspecialchars($g['ciclo']); ?>
                                        <?php if($esta_activa): ?>
                                            <span style="display: block; font-size: 0.75rem; color: #28a745; margin-top: 3px;"><i class="fas fa-circle" style="font-size: 0.5rem; margin-right:3px;"></i>En curso</span>
                                        <?php else: ?>
                                            <span style="display: block; font-size: 0.75rem; color: #6c757d; margin-top: 3px;"><i class="fas fa-archive" style="font-size: 0.6rem; margin-right:3px;"></i>Finalizada</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="padding: 15px 20px;">
                                        <div style="color: var(--udg-blue); font-weight: bold; font-size: 1.1rem;"><?php echo htmlspecialchars($g['materia']); ?></div>
                                        <div style="font-size: 0.85rem; color: #888; margin-top: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            <span style="background-color: #e7f3ff; color: var(--udg-blue); padding: 2px 8px; border-radius: 12px; font-weight: bold;">Nivel <?php echo htmlspecialchars($g['nivel']); ?></span>
                                            
                                            <?php if($g['nrc_p'] || $g['nrc_v']): ?>
                                                <span style="font-family: monospace; border-left: 1px solid #ddd; padding-left: 8px;">
                                                    <?php if($g['nrc_p']) echo "P: " . $g['nrc_p'] . " "; ?>
                                                    <?php if($g['nrc_v']) echo ($g['nrc_p'] ? '| V: ' : 'V: ') . $g['nrc_v']; ?>
                                                </span>
                                            <?php endif; ?>

                                        </div>
                                    </td>
                                    
                                    <td style="padding: 15px 20px; text-align: center;">
                                        <div style="font-size: 1.2rem; font-weight: bold; color: #333;"><i class="fas fa-users" style="color:#aaa; font-size: 0.9rem; margin-right: 5px;"></i><?php echo $g['inscritos']; ?></div>
                                    </td>
                                    
                                    <td style="padding: 15px 20px; text-align: center; font-size: 0.85rem; white-space: nowrap;">
                                        <?php if($g['dias_p']): ?>
                                            <div style="color: #28a745; margin-bottom: 3px;"><strong>P:</strong> <?php echo htmlspecialchars($g['dias_p']) . ' ' . date('H:i', strtotime($g['inicio_p'])) . '-' . date('H:i', strtotime($g['fin_p'])); ?></div>
                                        <?php endif; ?>
                                        <?php if($g['dias_v']): ?>
                                            <div style="color: #17a2b8;"><strong>V:</strong> <?php echo htmlspecialchars($g['dias_v']) . ' ' . date('H:i', strtotime($g['inicio_v'])) . '-' . date('H:i', strtotime($g['fin_v'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="padding: 15px 20px; text-align: center;">
                                        <button class="btn-save" style="padding: 8px 15px; font-size: 0.9rem; pointer-events: none;"><i class="fas fa-list-ul"></i> Ver</button>
                                    </td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 50px 20px; color: #888;">
                                    <i class="fas fa-folder-open" style="font-size: 3rem; color: #ddd; margin-bottom: 15px; display: block;"></i>
                                    No tienes grupos asignados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Portal Docente</div></footer>
</body>
</html>
