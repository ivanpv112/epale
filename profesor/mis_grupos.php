<?php
session_start();
require '../db.php';

// SEGURIDAD: Solo Profesores
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') { 
    header("Location: ../index.php"); exit; 
}

$profesor_id = $_SESSION['user_id'];

// OBTENER TODOS LOS GRUPOS DEL PROFESOR (Sin filtro PHP, la búsqueda ahora es en tiempo real con JS)
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
    <style>
        /* EFECTO HOVER INTELIGENTE PARA LAS FILAS */
        .group-row {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .group-row:hover {
            background-color: #f0f7ff !important; /* Azul muy clarito */
            transform: translateY(-2px); /* Se levanta un poco */
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); /* Proyecta sombra */
            z-index: 10;
            position: relative;
        }
        
        /* DISEÑO DE NRC GIGANTE */
        .nrc-badge {
            display: inline-block;
            background: #fff3cd; color: #856404;
            padding: 4px 12px; border-radius: 6px;
            font-size: 1.15rem; font-weight: bold;
            border: 1px solid #ffeeba;
            margin-top: 8px; margin-bottom: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .nrc-badge-virtual {
            background: #cff4fc; color: #055160; border-color: #b6effb;
        }
    </style>
</head>
<body>
    
    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1 style="color: var(--udg-blue); font-size: 2.2rem; margin-bottom: 5px;"><i class="fas fa-chalkboard-teacher"></i> Mis Grupos</h1>
            <p style="color: #666;">Selecciona una clase para ver la lista de alumnos y registrar calificaciones.</p>
        </div>

        <div class="toolbar" style="margin-bottom: 25px; max-width: 700px; margin-left: auto; margin-right: auto;">
            <div style="display: flex; align-items: center; width: 100%; border: 2px solid #e2e8f0; border-radius: 8px; padding: 0 15px; background: white; transition: border-color 0.3s;">
                <i class="fas fa-search" style="color: var(--udg-blue); font-size: 1.2rem;"></i>
                <input type="text" id="searchInput" placeholder="Buscar por materia, NRC, salón o nivel..." style="border: none; outline: none; padding: 15px; width: 100%; font-size: 1.05rem;">
            </div>
        </div>

        <div class="content-card" style="padding: 0; overflow: hidden; border-top: 4px solid var(--udg-light);">
            <div style="overflow-x:auto;">
                <table class="prof-table" style="margin: 0; width: 100%; border-collapse: collapse;">
                    <thead style="background-color: #f8f9fa; border-bottom: 2px solid #eee;">
                        <tr>
                            <th style="padding: 15px 20px; text-align: left; color: #555;">Semestre</th>
                            <th style="padding: 15px 20px; text-align: left; color: #555;">Materia y NRC</th>
                            <th style="padding: 15px 20px; text-align: center; color: #555;">Estudiantes</th>
                            <th style="padding: 15px 20px; text-align: left; color: #555;">Horario y Salón</th>
                        </tr>
                    </thead>
                    <tbody id="groupsTableBody">
                        <?php if (count($grupos) > 0): ?>
                            <?php foreach ($grupos as $g): 
                                $esta_activa = ($g['activo'] == 1 && $g['estado'] == 'ACTIVO');
                                $opacidad = $esta_activa ? '1' : '0.6'; 
                                $bg_tr = $esta_activa ? '#fff' : '#fcfcfc';
                            ?>
                                <tr class="group-row" style="background-color: <?php echo $bg_tr; ?>; opacity: <?php echo $opacidad; ?>; border-bottom: 1px solid #eee;" onclick="window.location.href='detalle_grupo.php?clave=<?php echo $g['clave_grupo']; ?>'">
                                    
                                    <td style="padding: 15px 20px; font-weight: bold; color: #555;">
                                        <?php echo htmlspecialchars($g['ciclo']); ?>
                                        <?php if($esta_activa): ?>
                                            <span style="display: block; font-size: 0.8rem; color: #28a745; margin-top: 5px; background: #e6f8ec; padding: 2px 8px; border-radius: 12px; width: max-content;"><i class="fas fa-circle" style="font-size: 0.5rem; margin-right:3px;"></i>En curso</span>
                                        <?php else: ?>
                                            <span style="display: block; font-size: 0.8rem; color: #6c757d; margin-top: 5px; background: #e2e3e5; padding: 2px 8px; border-radius: 12px; width: max-content;"><i class="fas fa-archive" style="font-size: 0.6rem; margin-right:3px;"></i>Finalizada</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="padding: 15px 20px;">
                                        <div style="color: var(--udg-blue); font-weight: bold; font-size: 1.2rem;"><?php echo htmlspecialchars($g['materia']); ?></div>
                                        <span style="background-color: #e7f3ff; color: var(--udg-blue); padding: 3px 8px; border-radius: 6px; font-weight: bold; font-size: 0.85rem; display: inline-block; margin-top: 4px;">Nivel <?php echo htmlspecialchars($g['nivel']); ?></span>
                                        <br>
                                        
                                        <?php if($g['nrc_p']): ?>
                                            <div class="nrc-badge" title="NRC Presencial">
                                                <i class="fas fa-hashtag" style="font-size: 0.9rem; opacity: 0.7;"></i> <?php echo $g['nrc_p']; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if($g['nrc_v']): ?>
                                            <div class="nrc-badge nrc-badge-virtual" title="NRC Virtual">
                                                <i class="fas fa-laptop" style="font-size: 0.9rem; opacity: 0.7;"></i> <?php echo $g['nrc_v']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="padding: 15px 20px; text-align: center;">
                                        <div style="font-size: 1.4rem; font-weight: bold; color: #333;"><i class="fas fa-users" style="color:#aaa; font-size: 1.1rem; margin-right: 5px;"></i><?php echo $g['inscritos']; ?></div>
                                    </td>
                                    
                                    <td style="padding: 15px 20px; font-size: 0.95rem; white-space: nowrap;">
                                        <?php if($g['dias_p']): ?>
                                            <div style="margin-bottom: 8px;">
                                                <div style="color: #28a745; font-weight: bold; margin-bottom: 2px;"><i class="far fa-clock"></i> <?php echo htmlspecialchars($g['dias_p']) . ' ' . date('H:i', strtotime($g['inicio_p'])) . '-' . date('H:i', strtotime($g['fin_p'])); ?></div>
                                                <div style="color: #555; font-weight: bold; font-size: 0.9rem;"><i class="fas fa-door-open" style="color: #888;"></i> Salón: <span style="color: var(--udg-blue);"><?php echo htmlspecialchars($g['aula_p'] ?: 'Sin asignar'); ?></span></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if($g['dias_v']): ?>
                                            <div>
                                                <div style="color: #17a2b8; font-weight: bold; margin-bottom: 2px;"><i class="far fa-clock"></i> <?php echo htmlspecialchars($g['dias_v']) . ' ' . date('H:i', strtotime($g['inicio_v'])) . '-' . date('H:i', strtotime($g['fin_v'])); ?></div>
                                                <div style="color: #555; font-weight: bold; font-size: 0.9rem;"><i class="fas fa-video" style="color: #888;"></i> Plataforma: <span style="color: var(--udg-blue);"><?php echo htmlspecialchars($g['aula_v'] ?: 'Sin asignar'); ?></span></div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="emptyRow">
                                <td colspan="4" style="text-align: center; padding: 50px 20px; color: #888;">
                                    <i class="fas fa-folder-open" style="font-size: 3rem; color: #ddd; margin-bottom: 15px; display: block;"></i>
                                    No tienes grupos asignados.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr id="noResultsRow" style="display: none;">
                            <td colspan="4" style="text-align: center; padding: 40px; color: #888; font-size: 1.1rem;">
                                <i class="fas fa-search" style="font-size: 2.5rem; color: #eee; margin-bottom: 15px; display: block;"></i>
                                No se encontraron clases con esa búsqueda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Portal Docente</div></footer>

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
                        // Obtenemos todo el texto de la fila (materia, nrc, salón, días, etc.)
                        const rowText = row.innerText.toLowerCase();
                        
                        if (rowText.includes(term)) {
                            row.style.display = ''; // Mostrar
                            hasVisibleRows = true;
                        } else {
                            row.style.display = 'none'; // Ocultar
                        }
                    });

                    // Mostrar mensaje de "Sin resultados" si no hay coincidencias
                    if (!hasVisibleRows && rows.length > 0) {
                        noResultsRow.style.display = '';
                    } else {
                        noResultsRow.style.display = 'none';
                    }
                });
            }
            
            // Efecto focus del input de búsqueda
            const searchContainer = searchInput.parentElement;
            searchInput.addEventListener('focus', () => searchContainer.style.borderColor = 'var(--udg-blue)');
            searchInput.addEventListener('blur', () => searchContainer.style.borderColor = '#e2e8f0');
        });

        function toggleMobileMenu() { 
            document.getElementById('navWrapper').classList.toggle('active'); 
            document.getElementById('menuOverlay').classList.toggle('active'); 
        }
    </script>
</body>
</html>
