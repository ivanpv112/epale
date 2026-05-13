<?php
session_start();
require '../db.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

// VERIFICAR ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: expedientes.php"); exit;
}
$usuario_id = $_GET['id'];

// OBTENER DATOS DEL USUARIO PROFESOR
$sql_perfil = "SELECT u.*, p.nacionalidad, p.experiencia 
               FROM usuarios u 
               JOIN profesores p ON u.usuario_id = p.usuario_id 
               WHERE u.usuario_id = ?";
$stmt_perfil = $pdo->prepare($sql_perfil);
$stmt_perfil->execute([$usuario_id]);
$perfil = $stmt_perfil->fetch(PDO::FETCH_ASSOC);

if (!$perfil) { header("Location: expedientes.php"); exit; }

$nombre_completo = trim($perfil['nombre'] . ' ' . $perfil['apellido_paterno'] . ' ' . $perfil['apellido_materno']);
$foto_perfil = "../img/avatar-default.png"; 
if($perfil['foto_perfil'] && file_exists("../img/perfiles/" . $perfil['foto_perfil'])) {
    $foto_perfil = "../img/perfiles/" . $perfil['foto_perfil'];
}

$grupos_profesor_activos = [];
$grupos_profesor_historial = [];

// OBTENER GRUPOS DEL PROFESOR
$sql_grupos = "SELECT g.clave_grupo, m.nombre AS materia, m.nivel, c.nombre AS ciclo, c.activo, g.estado,
                      MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END) AS nrc_p,
                      MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END) AS nrc_v,
                      (SELECT COUNT(DISTINCT i.alumno_id) FROM inscripciones i JOIN grupos g2 ON i.nrc = g2.nrc WHERE g2.clave_grupo = g.clave_grupo AND i.estatus = 'INSCRITO') AS inscritos
               FROM grupos g
               JOIN materias m ON g.materia_id = m.materia_id
               JOIN ciclos c ON g.ciclo_id = c.ciclo_id
               LEFT JOIN horarios h ON g.nrc = h.nrc
               WHERE g.profesor_id = ?
               GROUP BY g.clave_grupo, m.nombre, m.nivel, c.nombre, c.activo, g.estado
               ORDER BY c.activo DESC, g.estado ASC, c.nombre DESC, m.nivel ASC";
$stmt_g = $pdo->prepare($sql_grupos);
$stmt_g->execute([$usuario_id]);
$todos_grupos_prof = $stmt_g->fetchAll(PDO::FETCH_ASSOC);

foreach ($todos_grupos_prof as $g) {
    if ($g['estado'] == 'ACTIVO' && $g['activo'] == 1) {
        $grupos_profesor_activos[] = $g;
    } else {
        $grupos_profesor_historial[] = $g;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente | <?php echo htmlspecialchars($nombre_completo); ?></title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">

        <div class="expediente-header">
            <img src="<?php echo $foto_perfil; ?>" alt="Foto" class="expediente-avatar">
            <div>
                <h1 style="margin: 0 0 10px 0; font-size: 2.2rem;"><?php echo htmlspecialchars($nombre_completo); ?></h1>
                <p style="margin: 0; font-size: 1.1rem; opacity: 0.9; display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <span><i class="fas fa-chalkboard-teacher"></i> Docente</span>
                    | <span><i class="fas fa-id-badge"></i> Código: <?php echo htmlspecialchars($perfil['codigo'] ?: 'N/A'); ?></span>
                    
                    | <span><i class="fas fa-briefcase"></i> Exp: <?php echo htmlspecialchars($perfil['experiencia'] ?: 'N/A'); ?></span>
                    | <span><i class="fas fa-globe-americas"></i> Nac: <?php echo htmlspecialchars($perfil['nacionalidad'] ?: 'N/A'); ?></span>
                    | <span>
                        <?php 
                            if ($perfil['genero'] == 'MASCULINO') echo '<i class="fas fa-mars" style="color:#60a5fa;"></i> Masculino';
                            elseif ($perfil['genero'] == 'FEMENINO') echo '<i class="fas fa-venus" style="color:#f472b6;"></i> Femenino';
                            elseif ($perfil['genero'] == 'OTRO') echo '<i class="fas fa-transgender-alt" style="color:#c084fc;"></i> Otro';
                            else echo '<i class="fas fa-genderless" style="color:#ccc;"></i> No especificado';
                        ?>
                    </span>
                </p>
            </div>
        </div>

        <div class="card" style="margin-bottom: 20px;">
            <h3 style="color: var(--udg-blue); border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 0;">
                <i class="fas fa-info-circle"></i> Información de Contacto
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">
                <div>
                    <label style="color: #888; font-size: 0.85rem; text-transform: uppercase; font-weight: bold;">Correo Electrónico</label>
                    <div style="font-size: 1.1rem; color: #333;"><i class="fas fa-envelope" style="color:#ccc;"></i> <?php echo htmlspecialchars($perfil['correo']); ?></div>
                </div>
                <div>
                    <label style="color: #888; font-size: 0.85rem; text-transform: uppercase; font-weight: bold;">Teléfono</label>
                    <div style="font-size: 1.1rem; color: #333;"><i class="fas fa-phone" style="color:#ccc;"></i> <?php echo htmlspecialchars($perfil['telefono'] ?: 'No registrado'); ?></div>
                </div>
                <div>
                    <label style="color: #888; font-size: 0.85rem; text-transform: uppercase; font-weight: bold;">Estado del Usuario</label>
                    <div style="margin-top: 5px;">
                        <?php if($perfil['estatus'] == 'ACTIVO'): ?>
                            <span style="background: #d4edda; color: #155724; padding: 5px 12px; border-radius: 12px; font-weight: bold; font-size: 0.9rem;"><i class="fas fa-check-circle"></i> Activo</span>
                        <?php else: ?>
                            <span style="background: #f8d7da; color: #721c24; padding: 5px 12px; border-radius: 12px; font-weight: bold; font-size: 0.9rem;"><i class="fas fa-times-circle"></i> Inactivo</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
            
            <div class="card" style="margin-top: 0;">
                <h3 style="color: var(--udg-blue); border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 0;">
                    <i class="fas fa-chalkboard-teacher"></i> Grupos Activos en Curso (<?php echo count($grupos_profesor_activos); ?>)
                </h3>
                <?php if (count($grupos_profesor_activos) > 0): ?>
                    <div class="prof-classes-grid">
                        <?php foreach ($grupos_profesor_activos as $g): ?>
                            <a href="gestionar_grupo.php?clave=<?php echo urlencode($g['clave_grupo']); ?>" class="class-click-card" title="Haz clic para gestionar este grupo">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <h4 class="class-title"><?php echo htmlspecialchars($g['materia'] . ' ' . $g['nivel']); ?></h4>
                                    <span class="class-status" style="color: #28a745; background: #e6f8ec; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: bold;"><i class="fas fa-circle" style="font-size: 0.5rem;"></i> Activo</span>
                                </div>
                                <div style="font-size: 0.85rem; color: #666;"><i class="far fa-calendar-alt"></i> Semestre <?php echo htmlspecialchars($g['ciclo']); ?> &nbsp;|&nbsp; <i class="fas fa-users"></i> <?php echo $g['inscritos']; ?> Alumnos</div>
                                <div style="margin-top: 5px;">
                                    <?php if($g['nrc_p']): ?><span class="class-nrc"><strong style="color:#28a745;">P:</strong> <?php echo $g['nrc_p']; ?></span><?php endif; ?>
                                    <?php if($g['nrc_v']): ?><span class="class-nrc"><strong style="color:#17a2b8;">V:</strong> <?php echo $g['nrc_v']; ?></span><?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #888; text-align: center; padding: 20px 0;"><i class="fas fa-folder-open" style="font-size: 2rem; color: #ddd; display: block; margin-bottom: 10px;"></i>No tiene grupos activos en este momento.</p>
                <?php endif; ?>
            </div>

            <div class="card" style="margin-top: 0;">
                <h3 style="color: #555; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 0;">
                    <i class="fas fa-archive"></i> Historial de Clases Impartidas (<?php echo count($grupos_profesor_historial); ?>)
                </h3>
                <?php if (count($grupos_profesor_historial) > 0): ?>
                    <div class="prof-classes-grid">
                        <?php foreach ($grupos_profesor_historial as $g): ?>
                            <a href="gestionar_grupo.php?clave=<?php echo urlencode($g['clave_grupo']); ?>" class="class-click-card" style="opacity: 0.7; background: #fafafa;" title="Ver grupo cerrado">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <h4 class="class-title" style="color: #555;"><?php echo htmlspecialchars($g['materia'] . ' ' . $g['nivel']); ?></h4>
                                    <span class="class-status" style="color: #6c757d; background: #e2e3e5; padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: bold;"><i class="fas fa-lock" style="font-size: 0.6rem;"></i> Finalizada</span>
                                </div>
                                <div style="font-size: 0.85rem; color: #666;"><i class="far fa-calendar-alt"></i> Semestre <?php echo htmlspecialchars($g['ciclo']); ?> &nbsp;|&nbsp; <i class="fas fa-users"></i> <?php echo $g['inscritos']; ?> Alumnos</div>
                                <div style="margin-top: 5px;">
                                    <?php if($g['nrc_p']): ?><span class="class-nrc" style="background:#eee; color:#555;"><strong>P:</strong> <?php echo $g['nrc_p']; ?></span><?php endif; ?>
                                    <?php if($g['nrc_v']): ?><span class="class-nrc" style="background:#eee; color:#555;"><strong>V:</strong> <?php echo $g['nrc_v']; ?></span><?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #888; text-align: center; padding: 20px 0;"><i class="fas fa-ghost" style="font-size: 2rem; color: #ddd; display: block; margin-bottom: 10px;"></i>No hay registro de clases pasadas.</p>
                <?php endif; ?>
            </div>

        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
    </script>
</body>
</html>