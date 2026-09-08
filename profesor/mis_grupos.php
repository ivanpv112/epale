<?php
session_start();
require '../db.php';

// SEGURIDAD: Solo Profesores (Validación de Sesión y Rol)
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') { 
    header("Location: ../index.php"); exit; 
}

$profesor_id = $_SESSION['user_id'];

// OBTENER TODOS LOS GRUPOS DEL PROFESOR (Modo Estricto de MySQL)
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
        WHERE g.profesor_id = ?
        GROUP BY g.clave_grupo, m.nombre, m.nivel, c.nombre, c.activo, g.estado, g.materia_id, g.ciclo_id, g.profesor_id
        ORDER BY c.activo DESC, g.estado ASC, c.nombre DESC, m.nivel ASC";

$stmt = $pdo->prepare($sql); 
$stmt->execute([$profesor_id]); 
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
            <p style="color: var(--text-muted);">Selecciona una clase para ver la lista de alumnos y registrar calificaciones.</p>
        </div>

        <!-- Buscador Inteligente -->
        <div class="search-toolbar">
            <div class="search-input-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Buscar por materia, NRC, salón o nivel...">
            </div>
        </div>

        <div class="content-card table-card-container">
            <div class="table-responsive-wrapper">
                <table class="prof-table group-list-table">
                    <thead>
                        <tr>
                            <th>Semestre</th>
                            <th>Materia y NRC</th>
                            <th style="text-align: center;">Estudiantes</th>
                            <th>Horario y Salón</th>
                        </tr>
                    </thead>
                    <tbody id="groupsTableBody">
                        <?php if (count($grupos) > 0): ?>
                            <?php foreach ($grupos as $g): 
                                $esta_activa = ($g['activo'] == 1 && $g['estado'] == 'ACTIVO');
                                // CSS se encarga de TODO el diseño mediante estas dos clases
                                $estado_class = $esta_activa ? 'row-active' : 'row-inactive'; 
                            ?>
                                <tr class="group-row <?php echo $estado_class; ?>" onclick="window.location.href='detalle_grupo.php?clave=<?php echo $g['clave_grupo']; ?>'">
                                    
                                    <td class="col-semester">
                                        <?php echo htmlspecialchars($g['ciclo']); ?>
                                        <?php if($esta_activa): ?>
                                            <span class="status-badge status-active"><i class="fas fa-circle"></i> En curso</span>
                                        <?php else: ?>
                                            <span class="status-badge status-archived"><i class="fas fa-archive"></i> Finalizada</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="col-subject">
                                        <div class="subject-title"><?php echo htmlspecialchars($g['materia']); ?></div>
                                        <span class="level-badge">Nivel <?php echo htmlspecialchars($g['nivel']); ?></span>
                                        <br>
                                        
                                        <?php if($g['nrc_p']): ?>
                                            <div class="nrc-badge nrc-presencial" title="NRC Presencial">
                                                <i class="fas fa-hashtag"></i> <?php echo $g['nrc_p']; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if($g['nrc_v']): ?>
                                            <div class="nrc-badge nrc-virtual" title="NRC Virtual">
                                                <i class="fas fa-laptop"></i> <?php echo $g['nrc_v']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="col-students">
                                        <div class="student-count"><i class="fas fa-users"></i> <?php echo $g['inscritos']; ?></div>
                                    </td>
                                    
                                    <td class="col-schedule">
                                        <?php if($g['dias_p']): ?>
                                            <div class="schedule-item">
                                                <div class="schedule-time presencial"><i class="far fa-clock"></i> <?php echo htmlspecialchars($g['dias_p']) . ' ' . date('H:i', strtotime($g['inicio_p'])) . '-' . date('H:i', strtotime($g['fin_p'])); ?></div>
                                                <div class="schedule-room"><i class="fas fa-door-open"></i> Salón: <span><?php echo htmlspecialchars($g['aula_p'] ?: 'Sin asignar'); ?></span></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if($g['dias_v']): ?>
                                            <div class="schedule-item">
                                                <div class="schedule-time virtual"><i class="far fa-clock"></i> <?php echo htmlspecialchars($g['dias_v']) . ' ' . date('H:i', strtotime($g['inicio_v'])) . '-' . date('H:i', strtotime($g['fin_v'])); ?></div>
                                                <div class="schedule-room"><i class="fas fa-video"></i> Plataforma: <span><?php echo htmlspecialchars($g['aula_v'] ?: 'Sin asignar'); ?></span></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="emptyRow">
                                <td colspan="4" class="empty-table-msg">
                                    <i class="fas fa-folder-open"></i>
                                    No tienes grupos asignados.
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

    <?php include '../main_footer.php'; ?>

    <script>
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
