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

// PROCESAR ACTUALIZACIÓN MANUAL DE CALIFICACIONES (Solo para Alumnos)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_calificaciones'])) {
    $insc_id = $_POST['inscripcion_id'];
    if (isset($_POST['calificaciones']) && is_array($_POST['calificaciones'])) {
        foreach ($_POST['calificaciones'] as $codigo_examen => $puntaje) {
            $check = $pdo->prepare("SELECT calificacion_id FROM calificaciones WHERE inscripcion_id = ? AND tipo_examen = ?");
            $check->execute([$insc_id, $codigo_examen]);
            $exists = $check->fetchColumn();

            if (trim($puntaje) === '') {
                if ($exists) $pdo->prepare("DELETE FROM calificaciones WHERE calificacion_id = ?")->execute([$exists]);
            } else {
                $puntaje_val = floatval($puntaje);
                if ($exists) {
                    $pdo->prepare("UPDATE calificaciones SET puntaje = ? WHERE calificacion_id = ?")->execute([$puntaje_val, $exists]);
                } else {
                    $pdo->prepare("INSERT INTO calificaciones (inscripcion_id, tipo_examen, puntaje) VALUES (?, ?, ?)")->execute([$insc_id, $codigo_examen, $puntaje_val]);
                }
            }
        }
    }
    header("Location: ver_expediente.php?id=" . $usuario_id . "&exito=calificaciones"); exit;
}

// OBTENER DATOS DEL USUARIO (Maestro o Alumno)
$sql_perfil = "SELECT u.*, a.carrera, a.alumno_id 
               FROM usuarios u 
               LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id 
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

$es_alumno = ($perfil['rol'] === 'ALUMNO');
$es_profesor = ($perfil['rol'] === 'PROFESOR');

// ============================================
// LÓGICA SI ES ALUMNO
// ============================================
if ($es_alumno) {
    $alumno_id = $perfil['alumno_id'];
    $sql_materias = "SELECT i.*, m.nombre as materia, m.nivel, m.materia_id, c.nombre as ciclo, c.activo 
                     FROM inscripciones i
                     JOIN grupos g ON i.nrc = g.nrc
                     JOIN materias m ON g.materia_id = m.materia_id
                     JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                     WHERE i.alumno_id = ?
                     ORDER BY c.nombre DESC, m.nivel DESC";
    $stmt_mat = $pdo->prepare($sql_materias);
    $stmt_mat->execute([$alumno_id]);
    $todas_materias = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

    $materias_actuales = [];
    $historial = [];
    $ha_cursado_nivel_4 = false;

    foreach ($todas_materias as $mat) {
        if ($mat['activo'] == 1 && $mat['estatus'] == 'INSCRITO') {
            $materias_actuales[] = $mat;
        } else {
            $historial[] = $mat;
        }
        if ($mat['nivel'] == 4) $ha_cursado_nivel_4 = true;
    }
}

// ============================================
// LÓGICA SI ES PROFESOR
// ============================================
$grupos_profesor = [];
if ($es_profesor) {
    $sql_grupos = "SELECT g.clave_grupo, m.nombre AS materia, m.nivel, c.nombre AS ciclo, c.activo,
                          MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END) AS nrc_p,
                          MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END) AS nrc_v,
                          (SELECT COUNT(DISTINCT i.alumno_id) FROM inscripciones i JOIN grupos g2 ON i.nrc = g2.nrc WHERE g2.clave_grupo = g.clave_grupo AND i.estatus = 'INSCRITO') AS inscritos
                   FROM grupos g
                   JOIN materias m ON g.materia_id = m.materia_id
                   JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                   LEFT JOIN horarios h ON g.nrc = h.nrc
                   WHERE g.profesor_id = ?
                   GROUP BY g.clave_grupo, m.nombre, m.nivel, c.nombre, c.activo
                   ORDER BY c.activo DESC, c.nombre DESC, m.nivel ASC";
    $stmt_g = $pdo->prepare($sql_grupos);
    $stmt_g->execute([$usuario_id]);
    $grupos_profesor = $stmt_g->fetchAll(PDO::FETCH_ASSOC);
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">

        <?php if(isset($_GET['exito'])): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: 'Los cambios fueron guardados correctamente.',
                        icon: 'success',
                        confirmButtonColor: 'var(--udg-blue)'
                    });
                    
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.delete('exito');
                    window.history.replaceState({}, document.title, currentUrl.pathname + currentUrl.search);
                });
            </script>
        <?php endif; ?>

        <div class="expediente-header">
            <img src="<?php echo $foto_perfil; ?>" alt="Foto" class="expediente-avatar">
            <div>
                <h1 style="margin: 0 0 10px 0; font-size: 2.2rem;"><?php echo htmlspecialchars($nombre_completo); ?></h1>
                <p style="margin: 0; font-size: 1.1rem; opacity: 0.9; display: flex; align-items: center; gap: 15px;">
                    <?php if($es_alumno): ?>
                        <span><i class="fas fa-user-graduate"></i> Alumno</span> | 
                        <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($perfil['carrera'] ?? 'Sin Carrera'); ?></span>
                    <?php else: ?>
                        <span><i class="fas fa-chalkboard-teacher"></i> Docente</span>
                    <?php endif; ?>
                    | <span><i class="fas fa-id-badge"></i> Código: <?php echo htmlspecialchars($perfil['codigo'] ?: 'N/A'); ?></span>
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

        <?php if($es_alumno): ?>
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
            <div>
                <div class="card" style="margin-top: 0;">
                    <h3><i class="fas fa-book-reader"></i> Cursando Actualmente</h3>
                    <?php if (count($materias_actuales) > 0): ?>
                        <?php foreach ($materias_actuales as $mat): 
                            $stmt_max = $pdo->prepare("SELECT SUM(puntos_maximos) FROM criterios_evaluacion WHERE materia_id = ?");
                            $stmt_max->execute([$mat['materia_id']]);
                            $max_puntos = $stmt_max->fetchColumn() ?: 0;

                            $stmt_cal = $pdo->prepare("SELECT SUM(puntaje) FROM calificaciones WHERE inscripcion_id = ?");
                            $stmt_cal->execute([$mat['inscripcion_id']]);
                            $puntos_actuales = $stmt_cal->fetchColumn() ?: 0;

                            $porcentaje = ($max_puntos > 0) ? ($puntos_actuales / $max_puntos) * 100 : 0;
                            $color_bar = ($porcentaje >= 60) ? 'var(--udg-light)' : '#dc3545';
                        ?>
                            <div style="border: 1px solid #eee; padding: 15px; border-radius: 8px; margin-bottom: 15px; transition: box-shadow 0.2s;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                    <strong><?php echo htmlspecialchars($mat['materia'] . ' Nivel ' . $mat['nivel']); ?></strong>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <span style="font-weight: bold; color: var(--udg-blue);"><?php echo floatval($puntos_actuales); ?> / <?php echo $max_puntos; ?> pts</span>
                                        <button class="btn-save" onclick="abrirModalCalif(<?php echo $mat['inscripcion_id']; ?>)" style="padding: 6px 12px; font-size: 0.85rem;">
                                            <i class="fas fa-edit"></i> Calificar
                                        </button>
                                    </div>
                                </div>
                                <span style="font-size: 0.8rem; color: #888;">Ciclo: <?php echo htmlspecialchars($mat['ciclo']); ?> | NRC: <?php echo $mat['nrc']; ?></span>
                                <div style="width: 100%; background-color: #eee; border-radius: 10px; height: 12px; margin-top: 8px; overflow: hidden;">
                                    <div style="height: 100%; transition: width 0.4s; background-color: <?php echo $color_bar; ?>; width: <?php echo min($porcentaje, 100); ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: #888; text-align: center; padding: 20px 0;"><i class="fas fa-bed" style="font-size: 2rem; color: #ddd; display: block; margin-bottom: 10px;"></i>No está inscrito en ninguna materia activa.</p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3><i class="fas fa-history"></i> Historial Académico (Kárdex)</h3>
                    <div style="overflow-x:auto;">
                        <table class="history-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="padding: 10px; border-bottom: 2px solid #eee; text-align:left;">Ciclo</th>
                                    <th style="padding: 10px; border-bottom: 2px solid #eee; text-align:left;">Materia</th>
                                    <th style="padding: 10px; border-bottom: 2px solid #eee; text-align:center;">Calificación</th>
                                    <th style="padding: 10px; border-bottom: 2px solid #eee; text-align:center;">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($historial) > 0): ?>
                                    <?php foreach($historial as $h): ?>
                                        <tr style="border-bottom: 1px solid #f9f9f9;">
                                            <td style="padding: 10px;"><?php echo htmlspecialchars($h['ciclo']); ?></td>
                                            <td style="padding: 10px; font-weight: 500; color: #555;"><?php echo htmlspecialchars($h['materia'] . ' ' . $h['nivel']); ?></td>
                                            <td style="padding: 10px; text-align: center; font-weight: bold;"><?php echo floatval($h['calificacion_final']); ?></td>
                                            <td style="padding: 10px; text-align: center;">
                                                <?php if($h['calificacion_final'] >= 60): ?>
                                                    <span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 10px; font-size: 0.8rem;">Aprobado</span>
                                                <?php else: ?>
                                                    <span style="background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 10px; font-size: 0.8rem;">Reprobado</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align: center; padding: 20px; color:#aaa;">Sin registros en ciclos anteriores.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                <?php if($ha_cursado_nivel_4): ?>
                    <div class="card" style="margin-top: 0; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; border: none;">
                        <div style="text-align: center;">
                            <i class="fas fa-certificate" style="font-size: 3rem; color: #ffc107; margin-bottom: 10px;"></i>
                            <h3 style="color: white; margin: 0;">Certificación</h3>
                        </div>
                        <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; margin-top: 20px; text-align: center;">
                            <span style="display: block; font-size: 0.9rem; margin-bottom: 5px;">Puntaje TOEFL</span>
                            <span style="font-size: 2.5rem; font-weight: bold;">--</span>
                        </div>
                        <button style="width: 100%; padding: 12px; background: #ffc107; color: #000; border: none; border-radius: 6px; font-weight: bold; margin-top: 15px; cursor: pointer;">
                            <i class="fas fa-edit"></i> Registrar Puntaje
                        </button>
                    </div>
                <?php else: ?>
                    <div class="card" style="margin-top: 0; text-align: center; padding: 40px 20px;">
                        <i class="fas fa-lock" style="font-size: 2.5rem; color: #ddd; margin-bottom: 15px;"></i>
                        <h4 style="color: #666; margin: 0;">Certificación Bloqueada</h4>
                        <p style="font-size: 0.85rem; color: #999; margin-top: 10px;">Disponible en Nivel 4.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($materias_actuales as $mat): 
            $stmt_crit = $pdo->prepare("SELECT * FROM criterios_evaluacion WHERE materia_id = ? ORDER BY categoria ASC");
            $stmt_crit->execute([$mat['materia_id']]);
            $criterios_materia = $stmt_crit->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt_cal_exist = $pdo->prepare("SELECT tipo_examen, puntaje FROM calificaciones WHERE inscripcion_id = ?");
            $stmt_cal_exist->execute([$mat['inscripcion_id']]);
            $calif_existentes = [];
            while($row = $stmt_cal_exist->fetch(PDO::FETCH_ASSOC)) { $calif_existentes[$row['tipo_examen']] = $row['puntaje']; }
        ?>
        <div id="modalCalif_<?php echo $mat['inscripcion_id']; ?>" class="modal-overlay" style="display:none;">
            <div class="modal-content" style="padding: 0;">
                <div class="modal-header" style="padding: 20px 30px; margin: 0; border-bottom: 1px solid #eee;">
                    <h2 style="margin: 0; font-size: 1.3rem;"><i class="fas fa-edit"></i> <?php echo htmlspecialchars($mat['materia']); ?></h2>
                    <button class="close-btn" onclick="cerrarModalCalif(<?php echo $mat['inscripcion_id']; ?>)">&times;</button>
                </div>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="actualizar_calificaciones" value="1">
                    <input type="hidden" name="inscripcion_id" value="<?php echo $mat['inscripcion_id']; ?>">
                    <div style="padding: 20px 30px; max-height: 60vh; overflow-y: auto;">
                        <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                            <?php foreach($criterios_materia as $crit): 
                                $codigo = $crit['codigo_examen'];
                                $val = isset($calif_existentes[$codigo]) ? floatval($calif_existentes[$codigo]) : '';
                            ?>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label><i class="fas <?php echo $crit['icono']; ?>" style="color: <?php echo $crit['color']; ?>;"></i> <?php echo $crit['nombre_examen']; ?> <span style="color:#888;">(Máx: <?php echo floatval($crit['puntos_maximos']); ?>)</span></label>
                                    <input type="number" step="0.01" min="0" max="<?php echo floatval($crit['puntos_maximos']); ?>" name="calificaciones[<?php echo $codigo; ?>]" value="<?php echo $val; ?>" placeholder="Sin evaluar">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding: 20px 30px; margin: 0; border-top: 1px solid #eee; background-color: #fcfcfc;">
                        <button type="button" class="btn-cancel" onclick="cerrarModalCalif(<?php echo $mat['inscripcion_id']; ?>)">Cancelar</button>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; // FIN LÓGICA ALUMNO ?>


        <?php if ($es_profesor): ?>
            <div class="card" style="margin-top: 20px;">
                <h3 style="color: var(--udg-blue); border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 0;">
                    <i class="fas fa-chalkboard"></i> Grupos Asignados (<?php echo count($grupos_profesor); ?>)
                </h3>
                
                <?php if (count($grupos_profesor) > 0): ?>
                    <div class="prof-classes-grid">
                        <?php foreach ($grupos_profesor as $g): 
                            $color_estado = ($g['activo'] == 1) ? '#28a745' : '#6c757d';
                            $texto_estado = ($g['activo'] == 1) ? 'En Curso' : 'Finalizado';
                        ?>
                            <a href="gestionar_grupo.php?clave=<?php echo urlencode($g['clave_grupo']); ?>" class="class-click-card" title="Haz clic para gestionar este grupo">
                                
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <h4 class="class-title"><?php echo htmlspecialchars($g['materia'] . ' ' . $g['nivel']); ?></h4>
                                    <span class="class-status" style="color: <?php echo $color_estado; ?>;">
                                        <i class="fas fa-circle" style="font-size: 0.6rem;"></i> <?php echo $texto_estado; ?>
                                    </span>
                                </div>
                                
                                <div style="font-size: 0.85rem; color: #666;">
                                    <i class="far fa-calendar-alt"></i> Semestre <?php echo htmlspecialchars($g['ciclo']); ?> &nbsp;|&nbsp; 
                                    <i class="fas fa-users"></i> <?php echo $g['inscritos']; ?> Alumnos
                                </div>
                                
                                <div style="margin-top: 5px;">
                                    <?php if($g['nrc_p']): ?>
                                        <span class="class-nrc"><strong style="color:#28a745;">P:</strong> <?php echo $g['nrc_p']; ?></span>
                                    <?php endif; ?>
                                    <?php if($g['nrc_v']): ?>
                                        <span class="class-nrc"><strong style="color:#17a2b8;">V:</strong> <?php echo $g['nrc_v']; ?></span>
                                    <?php endif; ?>
                                </div>

                                <div style="margin-top: 10px; text-align: right; color: var(--udg-blue); font-size: 0.85rem; font-weight: bold;">
                                    Gestionar Grupo <i class="fas fa-arrow-right"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #888; text-align: center; padding: 20px 0;"><i class="fas fa-folder-open" style="font-size: 2rem; color: #ddd; display: block; margin-bottom: 10px;"></i>Este profesor no tiene grupos asignados actualmente.</p>
                <?php endif; ?>
            </div>
        <?php endif; // FIN LÓGICA PROFESOR ?>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }
        function abrirModalCalif(id) { document.getElementById('modalCalif_' + id).style.display = 'flex'; }
        function cerrarModalCalif(id) { document.getElementById('modalCalif_' + id).style.display = 'none'; }
        window.onclick = function(e) { 
            if(e.target.classList.contains('modal-overlay')) e.target.style.display = 'none';
        };
    </script>
</body>
</html>
