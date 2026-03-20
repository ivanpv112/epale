<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

if (isset($_GET['del_clave'])) {
    $pdo->prepare("DELETE FROM grupos WHERE clave_grupo=?")->execute([$_GET['del_clave']]);
    header("Location: grupos_nrc.php?success_del=1"); exit;
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$filtro_materia = isset($_GET['materia']) ? $_GET['materia'] : '';

// FILTRO MAESTRO: Solo mostramos los grupos que están ACTIVOS
$where = "g.estado = 'ACTIVO'"; 
$params = [];

if ($search !== '') {
    $where .= " AND (m.nombre LIKE :q1 OR m.clave LIKE :q2 OR g.nrc LIKE :q3)";
    $termino = "%" . $search . "%"; $params[':q1'] = $termino; $params[':q2'] = $termino; $params[':q3'] = $termino;
}
if ($filtro_materia !== '') { $where .= " AND m.nombre = :materia"; $params[':materia'] = $filtro_materia; }

$materias_unicas = $pdo->query("SELECT DISTINCT nombre FROM materias ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

$sql = "SELECT g.clave_grupo, c.nombre AS periodo, c.ciclo_id,
               m.clave AS curso, m.materia_id, m.nombre AS materia, m.nivel AS nivel,
               u.nombre AS profesor, u.apellido_paterno AS prof_ap, u.usuario_id AS profesor_id,
               MAX(g.cupo) AS cupo,
               (SELECT COUNT(DISTINCT i.alumno_id) FROM inscripciones i JOIN grupos g2 ON i.nrc = g2.nrc WHERE g2.clave_grupo = g.clave_grupo AND i.estatus = 'INSCRITO') AS inscritos,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END) AS nrc_presencial,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END) AS nrc_virtual,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.aula END) AS aula_presencial,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.aula END) AS aula_virtual,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.dias_patron END) AS dias_presencial,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.dias_patron END) AS dias_virtual,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_inicio END) AS inicio_presencial,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_inicio END) AS inicio_virtual,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_fin END) AS fin_presencial,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_fin END) AS fin_virtual
        FROM grupos g
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN usuarios u ON g.profesor_id = u.usuario_id AND u.rol = 'PROFESOR'
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        LEFT JOIN horarios h ON g.nrc = h.nrc
        WHERE $where
        GROUP BY g.clave_grupo, g.materia_id, g.profesor_id, g.ciclo_id, c.nombre, m.clave, m.nombre, m.nivel, u.nombre, u.apellido_paterno, u.usuario_id
        ORDER BY c.nombre DESC, m.nivel ASC, u.nombre ASC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grupos y NRC | Admin</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'menu_admin.php'; ?>
    <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-chalkboard"></i> Gestión de Grupos y Alumnos</h1>
            <p>Administra los horarios, aulas y el cupo de los grupos que están <strong>En Curso</strong>.</p>
        </div>

        <?php if(isset($_GET['success'])): ?><div class="alert alert-success" style="margin-bottom: 20px;"><i class="fas fa-check-circle"></i> ¡El grupo ha sido guardado correctamente!</div>
        <?php elseif(isset($_GET['success_del'])): ?><div class="alert alert-success" style="margin-bottom: 20px; background-color: #f8d7da; color: #721c24; border-color:#f5c6cb;"><i class="fas fa-trash"></i> ¡El grupo fue eliminado con éxito!</div>
        <?php elseif(isset($_GET['error'])): ?><div class="alert" style="margin-bottom: 20px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 15px; border-radius: 8px;"><i class="fas fa-exclamation-triangle"></i> <strong>Error:</strong> <?php echo htmlspecialchars($_GET['error']); ?></div><?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <form method="GET" action="grupos_nrc.php" style="display: flex; gap: 10px; flex-grow: 1; max-width: 800px; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <div style="display: flex; align-items: center; flex-grow: 1; border: 1px solid #ddd; border-radius: 6px; padding: 0 10px;">
                    <i class="fas fa-search" style="color:#aaa;"></i>
                    <input type="text" name="q" placeholder="Buscar por Nombre o NRC..." value="<?php echo htmlspecialchars($search); ?>" style="border: none; outline: none; padding: 10px; width: 100%;">
                </div>
                <select name="materia" style="padding: 10px; border-radius: 6px; border: 1px solid #ddd; outline: none; cursor: pointer;">
                    <option value="">Todas</option>
                    <?php foreach($materias_unicas as $mat_name): ?>
                        <option value="<?php echo htmlspecialchars($mat_name); ?>" <?php if($filtro_materia == $mat_name) echo 'selected'; ?>><?php echo htmlspecialchars($mat_name); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-save" style="padding: 10px 20px;"><i class="fas fa-search"></i> Buscar</button>
                <?php if($search !== '' || $filtro_materia !== ''): ?><a href="grupos_nrc.php" class="btn-cancel" style="text-decoration: none; display:flex; align-items:center; padding: 10px 15px;">Limpiar</a><?php endif; ?>
            </form>
            <a href="gestionar_grupo.php" class="btn-save" style="text-decoration: none; display: flex; align-items: center; height: 44px;"><i class="fas fa-plus-circle"></i> Nuevo Grupo</a>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-wrapper" style="overflow-x:auto;">
                <table class="history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Materia / Ciclo</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Profesor</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">NRC y Aula</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Horario</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Estudiantes</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($grupos) > 0): ?>
                        <?php foreach ($grupos as $g): 
                            $cupo = $g['cupo']; $inscritos = $g['inscritos'];
                            if ($inscritos == 0) { $badge_bg = '#f1f3f5'; $badge_color = '#6c757d'; $txt_cupo = "Vacía"; } 
                            elseif ($inscritos >= $cupo) { $badge_bg = '#f8d7da'; $badge_color = '#dc3545'; $txt_cupo = "Llena"; } 
                            else { $badge_bg = '#d4edda'; $badge_color = '#28a745'; $txt_cupo = "Con cupo"; }
                        ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 15px;">
                                    <div style="color: var(--udg-blue); font-weight: bold; font-size: 1.1rem;"><?php echo htmlspecialchars($g['materia']); ?></div>
                                    <div style="font-size: 0.85rem; color: #888;">Ciclo: <?php echo htmlspecialchars($g['periodo']); ?> | Nivel <?php echo htmlspecialchars($g['nivel']); ?></div>
                                </td>
                                <td style="padding: 15px;"><i class="fas fa-chalkboard-teacher" style="color: #ccc; margin-right: 5px;"></i><?php echo htmlspecialchars($g['profesor'] . ' ' . $g['prof_ap']); ?></td>
                                <td style="padding: 15px; text-align: center; font-size: 0.85rem; white-space: nowrap;">
                                    <div style="color: #28a745; margin-bottom: 3px;"><strong>P:</strong> <?php echo $g['nrc_presencial'] ? $g['nrc_presencial'] . ' | ' . htmlspecialchars($g['aula_presencial']?:'S/A') : '---'; ?></div>
                                    <div style="color: #17a2b8;"><strong>V:</strong> <?php echo $g['nrc_virtual'] ? $g['nrc_virtual'] . ' | ' . htmlspecialchars($g['aula_virtual']?:'S/A') : '---'; ?></div>
                                </td>
                                <td style="padding: 15px; text-align: center; font-size: 0.85rem; white-space: nowrap;">
                                    <div style="color: #28a745; margin-bottom: 3px;"><strong>P:</strong> <?php echo $g['dias_presencial'] ? htmlspecialchars($g['dias_presencial']) . ' ' . date('H:i', strtotime($g['inicio_presencial'])) . '-' . date('H:i', strtotime($g['fin_presencial'])) : '---'; ?></div>
                                    <div style="color: #17a2b8;"><strong>V:</strong> <?php echo $g['dias_virtual'] ? htmlspecialchars($g['dias_virtual']) . ' ' . date('H:i', strtotime($g['inicio_virtual'])) . '-' . date('H:i', strtotime($g['fin_virtual'])) : '---'; ?></div>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <div style="font-weight: bold; font-size: 1.1rem; color: #333; margin-bottom: 4px;"><?php echo $inscritos; ?> <span style="color: #999; font-weight: normal; font-size: 0.9rem;">/ <?php echo $cupo; ?></span></div>
                                    <span style="background-color: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: bold;"><?php echo $txt_cupo; ?></span>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <a href="gestionar_grupo.php?clave=<?php echo $g['clave_grupo']; ?>" class="btn-save" style="display: inline-flex; width: auto; font-size: 0.85rem; padding: 8px 15px; text-decoration: none; margin-right: 5px;"><i class="fas fa-cog"></i> Gestionar</a>
                                    <a href="#" onclick="confirmarBorrado('grupos_nrc.php?del_clave=<?php echo $g['clave_grupo']; ?>')" style="color: #dc3545; font-size: 1.2rem; margin-left: 10px;" title="Eliminar"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding: 40px; color: var(--text-light);"><i class="fas fa-search" style="font-size: 2.5rem; margin-bottom: 10px; display: block; color: #ddd;"></i>No se encontraron grupos activos. Las clases finalizadas están en Ciclos Escolares.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>
    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        
        function confirmarBorrado(url) {
            Swal.fire({
                title: '¿Eliminar Grupo?', text: "Se borrará este grupo y todos los alumnos inscritos perderán su espacio en la clase.", icon: 'error',
                showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', reverseButtons: true
            }).then((result) => { if (result.isConfirmed) { window.location.href = url; } });
        }

        if (window.history.replaceState) {
            const url = new URL(window.location);
            if (url.searchParams.has('success') || url.searchParams.has('success_del') || url.searchParams.has('error')) {
                url.searchParams.delete('success');
                url.searchParams.delete('success_del');
                url.searchParams.delete('error');
                window.history.replaceState({path:url.href}, '', url.href);
            }
        }
    </script>
</body>
</html>
