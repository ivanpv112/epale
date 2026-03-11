<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

// Ampliar silenciosamente la columna de días para evitar el error "Data truncated"
try { $pdo->exec("ALTER TABLE horarios MODIFY dias_patron VARCHAR(50)"); } catch (Exception $e) { }

$clave = $_GET['clave'] ?? '';
$es_edicion = !empty($clave);

$mensaje = ''; $tipo_mensaje = '';

if (isset($_GET['msg']) && $_GET['msg'] == 'created') {
    $mensaje = "¡Grupo creado exitosamente! Ahora puedes comenzar a añadir estudiantes."; $tipo_mensaje = "success";
}

// ==========================================
// FUNCIONES INTELIGENTES DE DETECCIÓN DE CHOQUES
// ==========================================

// 1. Validar que el AULA no esté ocupada
function checkAulaCollision($nrc, $aula, $dias, $inicio, $fin, $ciclo_id, $pdo) {
    if (empty($aula) || empty($dias) || empty($inicio) || empty($fin)) return false;

    $sql = "SELECT h.nrc, h.dias_patron, h.hora_inicio, h.hora_fin, m.nombre as mat_nombre 
            FROM horarios h JOIN grupos g ON h.nrc = g.nrc JOIN materias m ON g.materia_id = m.materia_id 
            WHERE g.ciclo_id = ? AND h.aula = ? AND h.nrc != ?";
    $stmt = $pdo->prepare($sql); $stmt->execute([$ciclo_id, $aula, $nrc]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $start1 = strtotime($inicio); $end1 = strtotime($fin);

    foreach ($matches as $m) {
        if (empty($m['hora_inicio']) || empty($m['hora_fin']) || empty($m['dias_patron'])) continue;
        $start2 = strtotime($m['hora_inicio']); $end2 = strtotime($m['hora_fin']);
        
        if ($start1 < $end2 && $end1 > $start2) {
            $d1 = str_split(preg_replace('/[^A-Za-z]/', '', strtoupper($dias)));
            $d2 = str_split(preg_replace('/[^A-Za-z]/', '', strtoupper($m['dias_patron'])));
            
            if (count(array_intersect($d1, $d2)) > 0) {
                return "¡Choque de Aula! El aula '{$aula}' ya está ocupada por '{$m['mat_nombre']}' (NRC: {$m['nrc']}) en esos días y horas.";
            }
        }
    }
    return false;
}

// 2. Validar que el PROFESOR no esté ocupado
function checkProfesorCollision($nrc, $profesor_id, $dias, $inicio, $fin, $ciclo_id, $pdo) {
    if (empty($profesor_id) || empty($dias) || empty($inicio) || empty($fin)) return false;

    $sql = "SELECT h.nrc, h.dias_patron, h.hora_inicio, h.hora_fin, m.nombre as mat_nombre 
            FROM horarios h JOIN grupos g ON h.nrc = g.nrc JOIN materias m ON g.materia_id = m.materia_id 
            WHERE g.ciclo_id = ? AND g.profesor_id = ? AND h.nrc != ?";
    $stmt = $pdo->prepare($sql); $stmt->execute([$ciclo_id, $profesor_id, $nrc]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $start1 = strtotime($inicio); $end1 = strtotime($fin);

    foreach ($matches as $m) {
        if (empty($m['hora_inicio']) || empty($m['hora_fin']) || empty($m['dias_patron'])) continue;
        $start2 = strtotime($m['hora_inicio']); $end2 = strtotime($m['hora_fin']);
        
        if ($start1 < $end2 && $end1 > $start2) {
            $d1 = str_split(preg_replace('/[^A-Za-z]/', '', strtoupper($dias)));
            $d2 = str_split(preg_replace('/[^A-Za-z]/', '', strtoupper($m['dias_patron'])));
            
            if (count(array_intersect($d1, $d2)) > 0) {
                return "¡Cruce de Docente! El profesor seleccionado ya imparte '{$m['mat_nombre']}' (NRC: {$m['nrc']}) en ese mismo horario.";
            }
        }
    }
    return false;
}

// ==========================================
// PROCESAMIENTO DE FORMULARIOS 
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- QUITAR ALUMNO ---
    if (isset($_POST['action']) && $_POST['action'] === 'remove_student') {
        $alumno_quitar = $_POST['alumno_id']; $nrc_grupo = $_POST['nrc_base'];
        $pdo->prepare("UPDATE inscripciones SET estatus = 'BAJA' WHERE alumno_id = ? AND nrc = ?")->execute([$alumno_quitar, $nrc_grupo]);
        $mensaje = "El alumno fue dado de baja exitosamente."; $tipo_mensaje = "success";
    }
    
    // --- AGREGAR ALUMNO ---
    if (isset($_POST['action']) && $_POST['action'] === 'add_student') {
        $nuevo_alumno_id = $_POST['nuevo_alumno_id']; $nrc_grupo = $_POST['nrc_base'];
        $cupo_actual = $_POST['cupo_actual']; $inscritos_actuales = $_POST['inscritos_actuales'];

        if (empty($nuevo_alumno_id) || !is_numeric($nuevo_alumno_id)) { $mensaje = "Selecciona un alumno de la lista."; $tipo_mensaje = "error"; } 
        elseif ($inscritos_actuales >= $cupo_actual) { $mensaje = "El grupo ya está lleno. Aumenta la capacidad máxima primero."; $tipo_mensaje = "error"; } 
        else {
            $check = $pdo->prepare("SELECT estatus FROM inscripciones WHERE alumno_id = ? AND nrc = ?"); $check->execute([$nuevo_alumno_id, $nrc_grupo]);
            $registro = $check->fetch(PDO::FETCH_ASSOC);
            if ($registro) {
                if ($registro['estatus'] === 'INSCRITO') { $mensaje = "El alumno ya está inscrito en esta clase."; $tipo_mensaje = "error"; } 
                else { $pdo->prepare("UPDATE inscripciones SET estatus = 'INSCRITO' WHERE alumno_id = ? AND nrc = ?")->execute([$nuevo_alumno_id, $nrc_grupo]); $mensaje = "Alumno re-inscrito correctamente."; $tipo_mensaje = "success"; }
            } else {
                $pdo->prepare("INSERT INTO inscripciones (alumno_id, nrc, estatus) VALUES (?, ?, 'INSCRITO')")->execute([$nuevo_alumno_id, $nrc_grupo]);
                $mensaje = "Alumno inscrito correctamente."; $tipo_mensaje = "success";
            }
        }
    }

    // --- GUARDAR O CREAR GRUPO (LÓGICA BLINDADA) ---
    if (isset($_POST['action']) && $_POST['action'] === 'save_group') {
        try {
            $n_prof = $_POST['profesor_id'] ?? ''; $n_mat = $_POST['materia_id'] ?? ''; $n_ciclo = $_POST['ciclo_id'] ?? '';
            $n_cupo = intval($_POST['cupo'] ?? 30); $n_edicion_total = isset($_POST['edicion_total']) ? 1 : 0;
            $nrc_p = trim($_POST['rnc_presencial']); $nrc_v = trim($_POST['rnc_virtual']);

            // 1. Validaciones Básicas
            if (empty($n_prof) || empty($n_mat) || empty($n_ciclo)) throw new Exception("Faltan campos obligatorios en la configuración (Profesor, Materia o Ciclo).");
            if (empty($nrc_p) && empty($nrc_v)) throw new Exception("Debes ingresar al menos un número de NRC (Presencial o Virtual).");

            // 2. Validaciones Presencial
            if (!empty($nrc_p)) {
                if (empty($_POST['dias_presencial']) || empty($_POST['inicio_presencial']) || empty($_POST['fin_presencial'])) {
                    throw new Exception("Si ingresas un NRC Presencial, es obligatorio llenar los Días, Hora de Inicio y Hora de Fin.");
                }
                $choque_aula = checkAulaCollision($nrc_p, $_POST['aula_presencial'], $_POST['dias_presencial'], $_POST['inicio_presencial'], $_POST['fin_presencial'], $n_ciclo, $pdo);
                if ($choque_aula) throw new Exception($choque_aula);

                $choque_prof = checkProfesorCollision($nrc_p, $n_prof, $_POST['dias_presencial'], $_POST['inicio_presencial'], $_POST['fin_presencial'], $n_ciclo, $pdo);
                if ($choque_prof) throw new Exception($choque_prof);
            }

            // 3. Validaciones Virtuales
            if (!empty($nrc_v)) {
                if (empty($_POST['dias_virtual']) || empty($_POST['inicio_virtual']) || empty($_POST['fin_virtual'])) {
                    throw new Exception("Si ingresas un NRC Virtual, es obligatorio llenar los Días, Hora de Inicio y Hora de Fin.");
                }
                $choque_v_aula = checkAulaCollision($nrc_v, $_POST['aula_virtual'], $_POST['dias_virtual'], $_POST['inicio_virtual'], $_POST['fin_virtual'], $n_ciclo, $pdo);
                if ($choque_v_aula) throw new Exception($choque_v_aula);

                $choque_v_prof = checkProfesorCollision($nrc_v, $n_prof, $_POST['dias_virtual'], $_POST['inicio_virtual'], $_POST['fin_virtual'], $n_ciclo, $pdo);
                if ($choque_v_prof) throw new Exception($choque_v_prof);
            }

            // 4. Auto-choque Presencial vs Virtual
            if (!empty($nrc_p) && !empty($nrc_v) && !empty($_POST['dias_presencial']) && !empty($_POST['dias_virtual'])) {
                $start_p = strtotime($_POST['inicio_presencial']); $end_p = strtotime($_POST['fin_presencial']);
                $start_v = strtotime($_POST['inicio_virtual']); $end_v = strtotime($_POST['fin_virtual']);
                if ($start_p < $end_v && $end_p > $start_v) {
                    $d_p = str_split(preg_replace('/[^A-Za-z]/', '', strtoupper($_POST['dias_presencial'])));
                    $d_v = str_split(preg_replace('/[^A-Za-z]/', '', strtoupper($_POST['dias_virtual'])));
                    if (count(array_intersect($d_p, $d_v)) > 0) {
                        throw new Exception("¡Choque interno! Los horarios Presencial y Virtual que ingresaste se cruzan entre sí.");
                    }
                }
            }

            // 5. Ejecución en Base de Datos
            $pdo->beginTransaction();

            if ($es_edicion) {
                // MODO EDICIÓN
                $inscritos_actuales = $_POST['inscritos_actuales'] ?? 0;
                if ($n_cupo < $inscritos_actuales) throw new Exception("No puedes reducir la capacidad a {$n_cupo}. Ya tienes {$inscritos_actuales} alumnos inscritos.");
                
                $pdo->prepare("UPDATE grupos SET profesor_id=?, materia_id=?, ciclo_id=?, cupo=?, edicion_total=? WHERE clave_grupo=?")->execute([$n_prof, $n_mat, $n_ciclo, $n_cupo, $n_edicion_total, $clave]);
                if ($nrc_p) $pdo->prepare("UPDATE horarios SET dias_patron=?, hora_inicio=?, hora_fin=?, aula=? WHERE nrc=?")->execute([$_POST['dias_presencial'], $_POST['inicio_presencial'], $_POST['fin_presencial'], $_POST['aula_presencial'], $nrc_p]);
                if ($nrc_v) $pdo->prepare("UPDATE horarios SET dias_patron=?, hora_inicio=?, hora_fin=?, aula=? WHERE nrc=?")->execute([$_POST['dias_virtual'], $_POST['inicio_virtual'], $_POST['fin_virtual'], $_POST['aula_virtual'], $nrc_v]);
                
                $pdo->commit();
                $mensaje = "Datos del grupo actualizados correctamente."; $tipo_mensaje = "success";
            } else {
                // MODO CREACIÓN
                $nueva_clave = uniqid('grp_');
                $insertGrupo = $pdo->prepare("INSERT INTO grupos (nrc, materia_id, profesor_id, ciclo_id, cupo, edicion_total, clave_grupo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insertHorario = $pdo->prepare("INSERT INTO horarios (nrc, dias_patron, hora_inicio, hora_fin, modalidad, aula) VALUES (?, ?, ?, ?, ?, ?)");

                if ($nrc_p !== '') {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE nrc = ?"); $chk->execute([$nrc_p]);
                    if($chk->fetchColumn() > 0) throw new Exception("El NRC Presencial $nrc_p ya está siendo utilizado en otro grupo.");
                    $insertGrupo->execute([$nrc_p, $n_mat, $n_prof, $n_ciclo, $n_cupo, $n_edicion_total, $nueva_clave]);
                    $insertHorario->execute([$nrc_p, $_POST['dias_presencial'], $_POST['inicio_presencial'], $_POST['fin_presencial'], 'PRESENCIAL', $_POST['aula_presencial']]);
                }
                if ($nrc_v !== '') {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE nrc = ?"); $chk->execute([$nrc_v]);
                    if($chk->fetchColumn() > 0) throw new Exception("El NRC Virtual $nrc_v ya está siendo utilizado en otro grupo.");
                    $insertGrupo->execute([$nrc_v, $n_mat, $n_prof, $n_ciclo, $n_cupo, $n_edicion_total, $nueva_clave]);
                    $insertHorario->execute([$nrc_v, $_POST['dias_virtual'], $_POST['inicio_virtual'], $_POST['fin_virtual'], 'VIRTUAL', $_POST['aula_virtual']]);
                }
                $pdo->commit();
                header("Location: gestionar_grupo.php?clave=$nueva_clave&msg=created"); exit;
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mensaje = $e->getMessage(); $tipo_mensaje = "error";
        }
    }
}

// Obtener catálogos para los selects
$list_profesores = $pdo->query("SELECT usuario_id, codigo, nombre, apellido_paterno, apellido_materno FROM usuarios WHERE rol='PROFESOR' AND estatus='ACTIVO' ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$list_materias = $pdo->query("SELECT materia_id, clave, nombre FROM materias ORDER BY clave")->fetchAll(PDO::FETCH_ASSOC);
$list_ciclos = $pdo->query("SELECT ciclo_id, nombre FROM ciclos ORDER BY nombre DESC")->fetchAll(PDO::FETCH_ASSOC);
$list_alumnos = $pdo->query("SELECT a.alumno_id, u.codigo, u.nombre, u.apellido_paterno, u.apellido_materno, a.carrera FROM alumnos a JOIN usuarios u ON a.usuario_id = u.usuario_id WHERE u.estatus='ACTIVO' ORDER BY u.nombre")->fetchAll(PDO::FETCH_ASSOC);

// Variables para los campos del formulario
$g = []; $alumnos_inscritos = []; $total_inscritos = 0; $nrc_base = '';

if ($es_edicion) {
    $sql = "SELECT g.clave_grupo, m.materia_id, m.nombre AS materia, u.usuario_id AS profesor_id, u.nombre AS prof_nombre, u.apellido_paterno AS prof_ap, u.foto_perfil AS prof_foto, u.correo AS prof_correo, c.ciclo_id,
                   MAX(g.cupo) AS cupo, MAX(g.edicion_total) AS edicion_total,
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
            FROM grupos g JOIN materias m ON g.materia_id = m.materia_id JOIN usuarios u ON g.profesor_id = u.usuario_id JOIN ciclos c ON g.ciclo_id = c.ciclo_id LEFT JOIN horarios h ON g.nrc = h.nrc
            WHERE g.clave_grupo=? GROUP BY g.clave_grupo, m.materia_id, m.nombre, u.usuario_id, u.nombre, u.apellido_paterno, u.foto_perfil, u.correo, c.ciclo_id";
    $stmt = $pdo->prepare($sql); $stmt->execute([$clave]); $g = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$g) { header("Location: grupos_nrc.php"); exit; }

    $nrc_base = $g['nrc_presencial'] ?: $g['nrc_virtual'];
    $sql_alum = "SELECT i.inscripcion_id, a.alumno_id, u.usuario_id, u.codigo, u.nombre, u.apellido_paterno, u.correo, a.carrera FROM inscripciones i JOIN alumnos a ON i.alumno_id = a.alumno_id JOIN usuarios u ON a.usuario_id = u.usuario_id WHERE i.nrc = ? AND i.estatus = 'INSCRITO' ORDER BY u.apellido_paterno ASC";
    $stmt_alum = $pdo->prepare($sql_alum); $stmt_alum->execute([$nrc_base]);
    $alumnos_inscritos = $stmt_alum->fetchAll(PDO::FETCH_ASSOC); $total_inscritos = count($alumnos_inscritos);
    $foto_profesor = "../img/avatar-default.png"; if($g['prof_foto'] && file_exists("../img/perfiles/" . $g['prof_foto'])) { $foto_profesor = "../img/perfiles/" . $g['prof_foto']; }
}

// INYECCIÓN DE VALORES AL FORMULARIO
$v_prof = ($tipo_mensaje == 'error' && isset($_POST['profesor_id'])) ? $_POST['profesor_id'] : ($g['profesor_id'] ?? '');
$v_mat = ($tipo_mensaje == 'error' && isset($_POST['materia_id'])) ? $_POST['materia_id'] : ($g['materia_id'] ?? '');
$v_ciclo = ($tipo_mensaje == 'error' && isset($_POST['ciclo_id'])) ? $_POST['ciclo_id'] : ($g['ciclo_id'] ?? '');
$v_cupo = ($tipo_mensaje == 'error' && isset($_POST['cupo'])) ? $_POST['cupo'] : ($g['cupo'] ?? 30);
$v_edit_total = ($tipo_mensaje == 'error' && isset($_POST['edicion_total'])) ? 1 : ($g['edicion_total'] ?? 0);

$v_nrc_p = ($tipo_mensaje == 'error' && isset($_POST['rnc_presencial'])) ? $_POST['rnc_presencial'] : ($g['nrc_presencial'] ?? '');
$v_aula_p = ($tipo_mensaje == 'error' && isset($_POST['aula_presencial'])) ? $_POST['aula_presencial'] : ($g['aula_presencial'] ?? '');
$v_dias_p = ($tipo_mensaje == 'error' && isset($_POST['dias_presencial'])) ? $_POST['dias_presencial'] : ($g['dias_presencial'] ?? '');
$v_ini_p = ($tipo_mensaje == 'error' && isset($_POST['inicio_presencial'])) ? $_POST['inicio_presencial'] : ($g['inicio_presencial'] ?? '');
$v_fin_p = ($tipo_mensaje == 'error' && isset($_POST['fin_presencial'])) ? $_POST['fin_presencial'] : ($g['fin_presencial'] ?? '');

$v_nrc_v = ($tipo_mensaje == 'error' && isset($_POST['rnc_virtual'])) ? $_POST['rnc_virtual'] : ($g['nrc_virtual'] ?? '');
$v_aula_v = ($tipo_mensaje == 'error' && isset($_POST['aula_virtual'])) ? $_POST['aula_virtual'] : ($g['aula_virtual'] ?? '');
$v_dias_v = ($tipo_mensaje == 'error' && isset($_POST['dias_virtual'])) ? $_POST['dias_virtual'] : ($g['dias_virtual'] ?? '');
$v_ini_v = ($tipo_mensaje == 'error' && isset($_POST['inicio_virtual'])) ? $_POST['inicio_virtual'] : ($g['inicio_virtual'] ?? '');
$v_fin_v = ($tipo_mensaje == 'error' && isset($_POST['fin_virtual'])) ? $_POST['fin_virtual'] : ($g['fin_virtual'] ?? '');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Grupo | Admin</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="grupos_nrc.php" style="color: var(--udg-blue); text-decoration: none; font-weight: bold;"><i class="fas fa-arrow-left"></i> Volver al listado</a>
            <?php if($es_edicion): ?><span style="font-weight: bold; color: #555;">Gestionando: <span style="color: var(--udg-blue);"><?php echo htmlspecialchars($g['materia']); ?></span></span>
            <?php else: ?><span style="font-weight: bold; color: #28a745;">Creando Nuevo Grupo</span><?php endif; ?>
        </div>

        <?php if($mensaje): ?>
            <div class="alert <?php echo ($tipo_mensaje == 'success') ? 'alert-success' : 'alert-error'; ?>" style="margin-bottom: 20px; <?php echo ($tipo_mensaje == 'error') ? 'background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:15px; border-radius:8px;' : ''; ?>">
                <i class="fas <?php echo ($tipo_mensaje == 'success') ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> 
                <strong><?php echo ($tipo_mensaje == 'error') ? 'Atención:' : 'Éxito:'; ?></strong> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 20px; align-items: start;">
            
            <div>
                <?php if($es_edicion): ?>
                    <a href="ver_expediente.php?id=<?php echo $g['profesor_id']; ?>" class="hover-lift" style="background: white; border: 1px solid #eee; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                        <img src="<?php echo $foto_profesor; ?>" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--udg-light);">
                        <div>
                            <div style="font-size: 0.8rem; color: #888; font-weight: bold; text-transform: uppercase;">Profesor Asignado</div>
                            <h3 style="margin: 0; color: var(--udg-blue); font-size: 1.1rem;"><?php echo htmlspecialchars($g['prof_nombre'] . ' ' . $g['prof_ap']); ?></h3>
                            <div style="font-size: 0.85rem; color: #666;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($g['prof_correo']); ?></div>
                        </div>
                    </a>

                    <div class="card" style="margin-top: 0; background: linear-gradient(135deg, var(--udg-blue) 0%, #001a57 100%); color: white; border: none; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 10px 0; color: white;"><i class="fas fa-user-plus"></i> Inscribir Alumno</h3>
                        <form method="POST" id="formAddStudent">
                            <input type="hidden" name="action" value="add_student"><input type="hidden" name="nrc_base" value="<?php echo $nrc_base; ?>"><input type="hidden" name="cupo_actual" value="<?php echo $g['cupo']; ?>"><input type="hidden" name="inscritos_actuales" value="<?php echo $total_inscritos; ?>"><input type="hidden" name="nuevo_alumno_id" id="hiddenAlumnoId" required>
                            <div style="display: flex; gap: 10px; align-items: stretch;">
                                <div class="custom-select-wrapper">
                                    <input type="text" id="searchInput" placeholder="Escribe el nombre o código..." style="width: 100%; height: 42px; padding: 10px 15px; border-radius: 6px; border: none; box-sizing: border-box; font-family: inherit;" autocomplete="off">
                                    <div class="custom-options" id="optionsContainer"></div>
                                </div>
                                <button type="submit" class="btn-save" style="background: #28a745; white-space: nowrap; height: 42px;"><i class="fas fa-plus"></i> Añadir</button>
                            </div>
                        </form>
                    </div>

                    <div class="card" style="margin-top: 0; padding: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px;"><i class="fas fa-users"></i> Estudiantes (<?php echo $total_inscritos; ?>/<?php echo $g['cupo']; ?>)</h3>
                        <?php if(count($alumnos_inscritos) > 0): ?>
                            <div style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                                <?php foreach($alumnos_inscritos as $alum): ?>
                                    <div class="student-row">
                                        <a href="ver_expediente.php?id=<?php echo $alum['usuario_id']; ?>" class="student-link">
                                            <div style="font-weight: bold; color: var(--udg-blue); font-size: 0.95rem;"><?php echo htmlspecialchars($alum['nombre'] . ' ' . $alum['apellido_paterno']); ?></div>
                                            <div style="font-size: 0.8rem; color: #888; font-family: monospace; margin-top: 3px;">Cod: <?php echo htmlspecialchars($alum['codigo']); ?></div>
                                        </a>
                                        <div class="student-action">
                                            <form method="POST" style="margin: 0;"><input type="hidden" name="action" value="remove_student"><input type="hidden" name="alumno_id" value="<?php echo $alum['alumno_id']; ?>"><input type="hidden" name="nrc_base" value="<?php echo $nrc_base; ?>"><button type="button" onclick="confirmarBajaAlumno(this, '<?php echo htmlspecialchars($alum['nombre']); ?>')" style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 1.2rem; padding: 5px;" title="Expulsar"><i class="fas fa-user-minus"></i></button></form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?><p style="text-align: center; color: #aaa; padding: 20px;">La clase está vacía.</p><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card" style="text-align: center; padding: 50px 20px; color: #888; border: 2px dashed #ddd; background: transparent;">
                        <i class="fas fa-magic" style="font-size: 3rem; margin-bottom: 15px; color: #ccc;"></i>
                        <h3 style="color: #555;">Grupo Nuevo</h3>
                        <p style="font-size: 0.9rem;">Llena la configuración a la derecha y haz clic en "Crear Grupo". Si cometes un error, el sistema te avisará sin borrar tu progreso.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <form method="POST" action="gestionar_grupo.php<?php echo $es_edicion ? '?clave='.$clave : ''; ?>" id="configForm">
                    <input type="hidden" name="action" value="save_group">
                    <?php if($es_edicion): ?><input type="hidden" name="inscritos_actuales" value="<?php echo $total_inscritos; ?>"><?php endif; ?>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        
                        <div class="card" style="margin: 0; border-top: 4px solid #28a745;">
                            <h4 style="margin: 0 0 15px 0; color: #28a745;"><i class="fas fa-building"></i> Presencial</h4>
                            <div class="form-group"><label>NRC</label><input type="number" name="rnc_presencial" value="<?php echo htmlspecialchars($v_nrc_p); ?>" <?php if($es_edicion && $g['nrc_presencial']) echo 'readonly class="readonly-input" title="El NRC no se puede editar"'; ?> placeholder="Ej. 60495"></div>
                            <div class="form-group"><label>Aula</label><input type="text" name="aula_presencial" value="<?php echo htmlspecialchars($v_aula_p); ?>" placeholder="Ej. A-202"></div>
                            <div class="form-group"><label>Días</label><input type="text" name="dias_presencial" value="<?php echo htmlspecialchars($v_dias_p); ?>" placeholder="Ej. L-M o L-M-V" title="Usa L, M, I, J, V separados por guiones"></div>
                            <div class="form-group" style="display: flex; gap: 5px; margin-bottom: 0;">
                                <div style="flex:1"><label>De</label><input type="time" name="inicio_presencial" value="<?php echo htmlspecialchars($v_ini_p); ?>"></div><div style="flex:1"><label>A</label><input type="time" name="fin_presencial" value="<?php echo htmlspecialchars($v_fin_p); ?>"></div>
                            </div>
                        </div>

                        <div class="card" style="margin: 0; border-top: 4px solid #17a2b8;">
                            <h4 style="margin: 0 0 15px 0; color: #17a2b8;"><i class="fas fa-laptop-house"></i> Virtual</h4>
                            <div class="form-group"><label>NRC</label><input type="number" name="rnc_virtual" value="<?php echo htmlspecialchars($v_nrc_v); ?>" <?php if($es_edicion && $g['nrc_virtual']) echo 'readonly class="readonly-input"'; ?> placeholder="Ej. 60501"></div>
                            <div class="form-group"><label>Plataforma</label><input type="text" name="aula_virtual" value="<?php echo htmlspecialchars($v_aula_v); ?>" placeholder="Ej. Zoom o Meet"></div>
                            <div class="form-group"><label>Días</label><input type="text" name="dias_virtual" value="<?php echo htmlspecialchars($v_dias_v); ?>" placeholder="Ej. J-V"></div>
                            <div class="form-group" style="display: flex; gap: 5px; margin-bottom: 0;">
                                <div style="flex:1"><label>De</label><input type="time" name="inicio_virtual" value="<?php echo htmlspecialchars($v_ini_v); ?>"></div><div style="flex:1"><label>A</label><input type="time" name="fin_virtual" value="<?php echo htmlspecialchars($v_fin_v); ?>"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="margin: 0; padding-bottom: 5px;">
                        <h3 style="margin: 0 0 15px 0; border-bottom: 1px solid #eee; padding-bottom: 10px; color: var(--udg-blue);"><i class="fas fa-sliders-h"></i> Configuración General</h3>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                            
                            <div class="form-group" style="background: #f8fbff; padding: 15px; border-radius: 8px; border: 1px solid #d0e3ff; display: flex; justify-content: space-between; align-items: center; margin-bottom: 0;">
                                <div><label style="margin-bottom:2px; color:var(--udg-blue); font-weight:bold;"><i class="fas fa-unlock-alt"></i> Habilitar Edición Total</label></div>
                                <label class="switch"><input type="checkbox" name="edicion_total" value="1" <?php if($v_edit_total == 1) echo 'checked'; ?>><span class="slider"></span></label>
                            </div>
                            
                            <div class="form-group">
                                <label>Profesor Asignado <span style="color:red;">*</span></label>
                                <?php
                                $nombre_profesor_actual = '';
                                foreach($list_profesores as $p) {
                                    if($v_prof == $p['usuario_id']) {
                                        $nombre_profesor_actual = trim($p['nombre'] . ' ' . $p['apellido_paterno'] . ' ' . ($p['apellido_materno'] ?? ''));
                                        break;
                                    }
                                }
                                ?>
                                <input type="hidden" name="profesor_id" id="hiddenProfesorId" value="<?php echo htmlspecialchars($v_prof); ?>">
                                
                                <div class="custom-select-wrapper">
                                    <input type="text" id="searchProfInput" placeholder="Buscar docente por nombre o código..." value="<?php echo htmlspecialchars($nombre_profesor_actual); ?>" style="width: 100%; padding: 10px 15px; border-radius: 6px; border: 1px solid #ccc; box-sizing: border-box; font-family: inherit; font-size: 1rem;" autocomplete="off">
                                    <div class="custom-options" id="optionsProfContainer"></div>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="form-group">
                                    <label>Materia <span style="color:red;">*</span></label>
                                    <select name="materia_id" required>
                                        <option value="">Seleccionar...</option>
                                        <?php foreach($list_materias as $m): ?>
                                            <option value="<?php echo $m['materia_id']; ?>" <?php if($v_mat == $m['materia_id']) echo 'selected'; ?>><?php echo htmlspecialchars($m['clave'] . ' - ' . $m['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Ciclo Escolar <span style="color:red;">*</span></label>
                                    <select name="ciclo_id" required>
                                        <option value="">Seleccionar...</option>
                                        <?php foreach($list_ciclos as $c): ?>
                                            <option value="<?php echo $c['ciclo_id']; ?>" <?php if($v_ciclo == $c['ciclo_id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Capacidad Máxima (Cupo)</label>
                                <input type="number" name="cupo" min="<?php echo $es_edicion ? max(1, $total_inscritos) : 1; ?>" value="<?php echo htmlspecialchars($v_cupo); ?>" required style="font-weight: bold; color: var(--udg-blue); font-size: 1.1rem;">
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <a href="grupos_nrc.php" class="btn-cancel" style="text-decoration: none; flex: 1; text-align: center; display: flex; align-items: center; justify-content: center;">Cancelar</a>
                        <button type="submit" class="btn-save" style="flex: 2; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; gap: 10px;"><i class="fas fa-save"></i> <?php echo $es_edicion ? 'Guardar Cambios' : 'Crear Nuevo Grupo'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        
        function confirmarBajaAlumno(btn, nombreAlumno) {
            Swal.fire({ title: '¿Dar de baja?', html: `Estás a punto de dar de baja a <b>${nombreAlumno}</b>.`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', confirmButtonText: 'Sí, dar de baja', reverseButtons: true
            }).then((result) => { if (result.isConfirmed) { btn.closest('form').submit(); } });
        }

        <?php if($es_edicion): ?>
        const alumnosData = <?php echo json_encode($list_alumnos, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const searchInput = document.getElementById('searchInput'); const optionsContainer = document.getElementById('optionsContainer'); const hiddenInput = document.getElementById('hiddenAlumnoId');
        function renderOptions(filterText = '') {
            optionsContainer.innerHTML = ''; const lowerFilter = filterText.toLowerCase();
            const filtered = alumnosData.filter(a => { const fullName = `${a.nombre} ${a.apellido_paterno} ${a.apellido_materno || ''}`.toLowerCase(); return fullName.includes(lowerFilter) || (a.codigo||'').toLowerCase().includes(lowerFilter); });
            if (filtered.length === 0) { optionsContainer.innerHTML = '<div style="padding:15px; color:#888; text-align:center;">Sin resultados</div>'; optionsContainer.style.display = 'block'; return; }
            filtered.forEach(a => {
                const div = document.createElement('div'); div.className = 'custom-option'; const fullName = `${a.nombre} ${a.apellido_paterno} ${a.apellido_materno || ''}`;
                div.innerHTML = `<div class="opt-name">${fullName}</div><div class="opt-details"><span>Cód: ${a.codigo || 'N/A'}</span></div>`;
                div.onclick = () => { searchInput.value = fullName; hiddenInput.value = a.alumno_id; optionsContainer.style.display = 'none'; };
                optionsContainer.appendChild(div);
            });
            optionsContainer.style.display = 'block';
        }
        searchInput.addEventListener('input', (e) => { hiddenInput.value = ''; renderOptions(e.target.value); });
        searchInput.addEventListener('focus', () => { renderOptions(searchInput.value); });
        document.addEventListener('click', (e) => { if (!searchInput.contains(e.target) && !optionsContainer.contains(e.target)) optionsContainer.style.display = 'none'; });
        <?php endif; ?>

        const profesData = <?php echo json_encode($list_profesores, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const searchProfInput = document.getElementById('searchProfInput');
        const optionsProfContainer = document.getElementById('optionsProfContainer');
        const hiddenProfInput = document.getElementById('hiddenProfesorId');

        function renderProfOptions(filterText = '') {
            optionsProfContainer.innerHTML = '';
            const lowerFilter = filterText.toLowerCase();

            const filteredProf = profesData.filter(p => {
                const fullName = `${p.nombre} ${p.apellido_paterno} ${p.apellido_materno || ''}`.toLowerCase();
                const code = (p.codigo || '').toLowerCase();
                return fullName.includes(lowerFilter) || code.includes(lowerFilter);
            });

            if (filteredProf.length === 0) {
                optionsProfContainer.innerHTML = '<div style="padding:15px; color:#888; text-align:center;">No se encontró ningún docente</div>';
                optionsProfContainer.style.display = 'block'; return;
            }

            filteredProf.forEach(p => {
                const div = document.createElement('div');
                div.className = 'custom-option';
                const fullName = `${p.nombre} ${p.apellido_paterno} ${p.apellido_materno || ''}`;
                div.innerHTML = `<div class="opt-name">${fullName}</div><div class="opt-details"><span>Cód: ${p.codigo || 'N/A'}</span></div>`;
                div.onclick = () => {
                    searchProfInput.value = fullName; 
                    hiddenProfInput.value = p.usuario_id; 
                    optionsProfContainer.style.display = 'none';
                };
                optionsProfContainer.appendChild(div);
            });
            optionsProfContainer.style.display = 'block';
        }

        searchProfInput.addEventListener('input', (e) => { hiddenProfInput.value = ''; renderProfOptions(e.target.value); });
        searchProfInput.addEventListener('focus', () => { renderProfOptions(searchProfInput.value); });
        document.addEventListener('click', (e) => { if (!searchProfInput.contains(e.target) && !optionsProfContainer.contains(e.target)) optionsProfContainer.style.display = 'none'; });

        document.getElementById('configForm').addEventListener('submit', function(e) {
            if (!hiddenProfInput.value) {
                e.preventDefault();
                Swal.fire({ title: 'Profesor Inválido', text: 'Debes buscar y seleccionar un profesor de la lista desplegable.', icon: 'warning', confirmButtonColor: '#001a57' });
            }
        });

        if (window.history.replaceState) {
            const url = new URL(window.location);
            if (url.searchParams.has('msg')) {
                url.searchParams.delete('msg');
                window.history.replaceState({path:url.href}, '', url.href);
            }
        }
    </script>
</body>
</html>
