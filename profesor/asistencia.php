<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') { header("Location: ../index.php"); exit; }

$clave = $_GET['clave'] ?? '';
$profesor_id = $_SESSION['user_id'];

// 1. Obtener información del grupo
$stmt = $pdo->prepare("SELECT m.nombre as materia, m.nivel, c.nombre as ciclo, g.nrc 
                       FROM grupos g 
                       JOIN materias m ON g.materia_id = m.materia_id 
                       JOIN ciclos c ON g.ciclo_id = c.ciclo_id 
                       WHERE g.clave_grupo = ? AND g.profesor_id = ?");
$stmt->execute([$clave, $profesor_id]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$grupo) { header("Location: mis_grupos.php"); exit; }

// 2. Obtener lista de alumnos inscritos
$stmt_al = $pdo->prepare("SELECT i.inscripcion_id, u.nombre, u.apellido_paterno, u.apellido_materno, u.codigo, u.foto_perfil 
                          FROM inscripciones i 
                          JOIN alumnos a ON i.alumno_id = a.alumno_id 
                          JOIN usuarios u ON a.usuario_id = u.usuario_id 
                          WHERE i.nrc = ? AND i.estatus = 'INSCRITO' 
                          ORDER BY u.apellido_paterno ASC, u.apellido_materno ASC");
$stmt_al->execute([$grupo['nrc']]);
$alumnos_brutos = $stmt_al->fetchAll(PDO::FETCH_ASSOC);

$alumnos = [];
foreach($alumnos_brutos as $alum) {
    $foto_url = "../img/avatar-default.png";
    if (!empty($alum['foto_perfil']) && file_exists("../img/perfiles/" . $alum['foto_perfil'])) {
        $foto_url = "../img/perfiles/" . $alum['foto_perfil'];
    }
    $alum['foto_url'] = $foto_url;
    $alumnos[] = $alum;
}

// 3. Obtener fechas de clases
$stmt_fechas = $pdo->prepare("SELECT DISTINCT fecha FROM asistencias a 
                              JOIN inscripciones i ON a.inscripcion_id = i.inscripcion_id 
                              WHERE i.nrc = ? ORDER BY fecha ASC");
$stmt_fechas->execute([$grupo['nrc']]);
$fechas_clase = $stmt_fechas->fetchAll(PDO::FETCH_COLUMN);
$total_sesiones = count($fechas_clase);

// 4. Mapear asistencias
$asistencias_log = [];
$stmt_log = $pdo->prepare("SELECT a.inscripcion_id, a.fecha, a.estatus FROM asistencias a 
                           JOIN inscripciones i ON a.inscripcion_id = i.inscripcion_id 
                           WHERE i.nrc = ?");
$stmt_log->execute([$grupo['nrc']]);
while($row = $stmt_log->fetch(PDO::FETCH_ASSOC)) {
    $asistencias_log[$row['inscripcion_id']][$row['fecha']] = $row['estatus'];
}

$hoy = date('Y-m-d');
$asistencia_hoy_completada = in_array($hoy, $fechas_clase);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Asistencia | e-PALE</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profesor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        <div class="header-asistencia">
            <div>
                <h1 class="title-asistencia"><?php echo htmlspecialchars($grupo['materia'] . " " . $grupo['nivel']); ?></h1>
                <p class="subtitle-asistencia">
                    <i class="fas fa-calendar-day"></i> Registro de Asistencia &nbsp;|&nbsp; 
                    <i class="fas fa-users"></i> <?php echo count($alumnos); ?> Alumnos &nbsp;|&nbsp; 
                    <i class="fas fa-chalkboard"></i> Clases impartidas: <strong><?php echo $total_sesiones; ?></strong>
                </p>
            </div>
            
            <button id="btnTomarAsist" class="btn-save btn-tomar-asist" 
                    <?php echo $asistencia_hoy_completada ? 'disabled' : ''; ?>
                    onclick="iniciarTomaAsistencia()">
                <i class="fas fa-user-check"></i> <?php echo $asistencia_hoy_completada ? 'Asistencia de Hoy Completada' : 'Pasar Lista de Asistencia'; ?>
            </button>
        </div>

        <div class="card table-card-asist">
            <div class="table-wrapper">
                <table class="history-table admin-table">
                    <thead>
                        <tr>
                            <th class="th-alumno">Alumno</th>
                            <?php foreach($fechas_clase as $f): ?>
                                <th class="th-fecha"><?php echo date('d/m', strtotime($f)); ?></th>
                            <?php endforeach; ?>
                            <th class="th-total">Total Asist.</th>
                            <th class="th-total">% Asistencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach($alumnos as $a): 
                            $id_ins = $a['inscripcion_id'];
                            $conteo_asist = 0; 
                            
                            foreach($fechas_clase as $f) {
                                $st = $asistencias_log[$id_ins][$f] ?? '';
                                if($st == 'ASISTENCIA' || $st == 'RETARDO') {
                                    $conteo_asist++;
                                }
                            }
                            
                            $porcentaje_asist = ($total_sesiones > 0) ? ($conteo_asist / $total_sesiones) * 100 : 100;
                            
                            $row_class = 'status-row-good';
                            $color_porcentaje = '#28a745'; 

                            if ($total_sesiones > 0) {
                                if ($porcentaje_asist < 90) {
                                    $row_class = 'status-row-fail';
                                    $color_porcentaje = '#dc3545';
                                } elseif ($porcentaje_asist < 95) {
                                    $row_class = 'status-row-risk';
                                    $color_porcentaje = '#d39e00'; 
                                }
                            }
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td class="td-alumno">
                                <img src="<?php echo $a['foto_url']; ?>" class="td-foto">
                                <div>
                                    <div class="td-nombre"><?php echo htmlspecialchars($a['apellido_paterno'] . " " . $a['apellido_materno'] . " " . $a['nombre']); ?></div>
                                    <div class="td-codigo">Código: <?php echo htmlspecialchars($a['codigo']); ?></div>
                                </div>
                            </td>
                            <?php foreach($fechas_clase as $f): 
                                $st = $asistencias_log[$id_ins][$f] ?? 'DEFAULT';
                            ?>
                                <td class="td-center">
                                    <select class="select-asist sel-<?php echo $st; ?>" onchange="cambiarAsistencia(this, <?php echo $id_ins; ?>, '<?php echo $f; ?>')" title="Cambiar estado">
                                        <option value="DEFAULT" style="display:none;" <?php echo ($st == 'DEFAULT' || $st == '') ? 'selected' : ''; ?>>---</option>
                                        <option value="ASISTENCIA" class="opt-asist" <?php echo ($st == 'ASISTENCIA') ? 'selected' : ''; ?>>Asistencia</option>
                                        <option value="RETARDO" class="opt-retar" <?php echo ($st == 'RETARDO') ? 'selected' : ''; ?>>Retardo</option>
                                        <option value="FALTA" class="opt-falta" <?php echo ($st == 'FALTA') ? 'selected' : ''; ?>>Falta</option>
                                    </select>
                                </td>
                            <?php endforeach; ?>
                            <td class="td-total-num">
                                <?php echo $conteo_asist; ?>
                            </td>
                            <td class="td-porcentaje" style="color: <?php echo $color_porcentaje; ?>;">
                                <?php echo round($porcentaje_asist, 1); ?>%
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(count($alumnos) == 0): ?>
                            <tr><td colspan="<?php echo count($fechas_clase) + 3; ?>" class="empty-table-msg">No hay alumnos inscritos en este grupo.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="modalAsistencia" class="modal-asistencia">
        <div class="card-asistencia">
            <div class="asist-header">
                <button class="btn-close-modal" onclick="cerrarTomaAsistencia()" title="Cancelar toma de lista">&times;</button>
                <div class="asist-progress">Alumno <span id="currentStep">1</span> de <?php echo count($alumnos); ?></div>
                <img src="../img/avatar-default.png" id="modalAvatarAlumno" alt="Avatar Alumno" class="asist-avatar">
            </div>
            
            <div class="asist-body">
                <h2 id="modalNombreAlumno" class="asist-name">Cargando...</h2>
                <div id="modalCodigoAlumno" class="asist-code">Código: ---</div>
                
                <div class="btn-group-asist">
                    <button class="btn-asist-action btn-action-green" onclick="grabarPaso('ASISTENCIA')">
                        <i class="fas fa-check-circle"></i> Asistencia
                    </button>
                    <button class="btn-asist-action btn-action-yellow" onclick="grabarPaso('RETARDO')">
                        <i class="fas fa-clock"></i> Retardo
                    </button>
                    <button class="btn-asist-action btn-action-red" onclick="grabarPaso('FALTA')">
                        <i class="fas fa-times-circle"></i> Falta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function cambiarAsistencia(selectObj, ins_id, fecha) {
            const nuevoEstatus = selectObj.value;
            selectObj.className = 'select-asist sel-' + nuevoEstatus;
            
            fetch('asistencia_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'single', ins_id: ins_id, fecha: fecha, estatus: nuevoEstatus })
            })
            .then(res => res.json())
            .then(res => { 
                if(res.success) { location.reload(); } 
                else { Swal.fire('Error', 'Hubo un problema al actualizar la asistencia.', 'error'); }
            });
        }

        const alumnos = <?php echo json_encode($alumnos); ?>;
        let indexActual = 0;
        let resultados = [];

        function iniciarTomaAsistencia() {
            if(alumnos.length === 0) return;
            indexActual = 0;
            resultados = [];
            mostrarAlumno();
            document.getElementById('modalAsistencia').style.display = 'flex';
        }

        function cerrarTomaAsistencia() { document.getElementById('modalAsistencia').style.display = 'none'; }

        function mostrarAlumno() {
            const alum = alumnos[indexActual];
            let nombreCompleto = `${alum.apellido_paterno} ${alum.apellido_materno || ''} ${alum.nombre}`;
            nombreCompleto = nombreCompleto.replace(/\s+/g, ' ').trim(); 
            
            document.getElementById('modalNombreAlumno').innerText = nombreCompleto;
            document.getElementById('modalCodigoAlumno').innerText = `Código: ${alum.codigo || 'N/A'}`;
            document.getElementById('modalAvatarAlumno').src = alum.foto_url;
            document.getElementById('currentStep').innerText = indexActual + 1;
        }

        function grabarPaso(estatus) {
            resultados.push({ inscripcion_id: alumnos[indexActual].inscripcion_id, estatus: estatus });
            indexActual++;
            if(indexActual < alumnos.length) {
                mostrarAlumno();
            } else {
                finalizarTomaAsistencia();
            }
        }

        function finalizarTomaAsistencia() {
            document.getElementById('modalAsistencia').style.display = 'none';
            Swal.fire({ title: 'Procesando lista...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
            
            fetch('asistencia_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'batch', data: resultados })
            })
            .then(res => res.json())
            .then(res => {
                if(res.success) { Swal.fire('¡Lista Guardada!', 'La asistencia de hoy ha sido registrada exitosamente.', 'success').then(() => location.reload()); } 
                else { Swal.fire('Error', 'Hubo un problema al guardar la asistencia.', 'error'); }
            });
        }

        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
    </script>
</body>
</html>