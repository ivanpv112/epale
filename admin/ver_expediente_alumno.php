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

// PROCESAR ACTUALIZACIÓN MANUAL DE CALIFICACIONES
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_calificaciones'])) {
    $insc_id = $_POST['inscripcion_id'];
    if (isset($_POST['calificaciones']) && is_array($_POST['calificaciones'])) {
        foreach ($_POST['calificaciones'] as $codigo_examen => $puntaje) {
            $check = $pdo->prepare("SELECT calificacion_id FROM calificaciones WHERE inscripcion_id = ? AND tipo_examen = ?");
            $check->execute([$insc_id, $codigo_examen]);
            $exists = $check->fetchColumn();

            $puntaje_str = (string)$puntaje;
            if (trim($puntaje_str) === '') {
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
    header("Location: ver_expediente_alumno.php?id=" . $usuario_id . "&exito=calificaciones"); exit;
}

// PROCESAR REGISTRO DE CERTIFICACIONES
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_certificacion'])) {
    $alum_id_cert = $_POST['alumno_id_cert'];
    $idioma_cert = $_POST['idioma_cert'];
    $nivel_cert = strtoupper(trim($_POST['nivel_cert'] ?? ''));
    $puntaje_cert = trim($_POST['puntaje_cert'] ?? '');
    $periodo_cert = strtoupper(trim($_POST['periodo_cert'] ?? ''));
    $fecha_cert = !empty($_POST['fecha_cert']) ? $_POST['fecha_cert'] : null;
    
    $stmt_chk = $pdo->prepare("SELECT certificacion_id FROM certificaciones WHERE alumno_id = ? AND idioma = ?");
    $stmt_chk->execute([$alum_id_cert, $idioma_cert]);
    $exists = $stmt_chk->fetchColumn();
    
    if ($exists) {
        $pdo->prepare("UPDATE certificaciones SET nivel_obtenido = ?, puntaje = ?, periodo = ?, fecha_aplicacion = ? WHERE certificacion_id = ?")->execute([$nivel_cert, $puntaje_cert, $periodo_cert, $fecha_cert, $exists]);
    } else {
        $pdo->prepare("INSERT INTO certificaciones (alumno_id, idioma, nivel_obtenido, puntaje, periodo, fecha_aplicacion) VALUES (?, ?, ?, ?, ?, ?)")->execute([$alum_id_cert, $idioma_cert, $nivel_cert, $puntaje_cert, $periodo_cert, $fecha_cert]);
    }
    header("Location: ver_expediente_alumno.php?id=" . $usuario_id . "&exito=certificacion"); exit;
}

// PROCESAR GUARDADO/EDICIÓN DE EXAMEN DIAGNÓSTICO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_diagnostico'])) {
    $examen_id = $_POST['examen_id'] ?? '';
    $alum_id = $_POST['alumno_id_diag'];
    $idioma = trim($_POST['idioma_diag'] ?? '');
    $periodo = strtoupper(trim($_POST['periodo_diag'] ?? ''));
    $nivel = intval($_POST['nivel_diag'] ?? 0);
    $calificacion = strtoupper(trim($_POST['calif_diag'] ?? ''));
    $fecha = $_POST['fecha_diag'];

    if (!empty($examen_id)) {
        $pdo->prepare("UPDATE examenes_diagnosticos SET idioma=?, periodo=?, nivel_asignado=?, calificacion_texto=?, fecha_realizacion=? WHERE examen_id=?")
            ->execute([$idioma, $periodo, $nivel, $calificacion, $fecha, $examen_id]);
    } else {
        $pdo->prepare("INSERT INTO examenes_diagnosticos (alumno_id, idioma, periodo, nivel_asignado, calificacion_texto, fecha_realizacion) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$alum_id, $idioma, $periodo, $nivel, $calificacion, $fecha]);
    }
    header("Location: ver_expediente_alumno.php?id=" . $usuario_id . "&exito=diagnostico"); exit;
}

// OBTENER DATOS DEL USUARIO
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

$alumno_id = $perfil['alumno_id'];

// OBTENER MATERIAS E HISTORIAL
$sql_materias = "SELECT i.*, m.nombre as materia, m.nivel, m.materia_id, c.nombre as ciclo, c.activo, g.estado as grupo_estado, g.nrc 
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
$idiomas_nivel_4 = [];

foreach ($todas_materias as $mat) {
    $stmt_cal = $pdo->prepare("SELECT SUM(puntaje) FROM calificaciones WHERE inscripcion_id = ?");
    $stmt_cal->execute([$mat['inscripcion_id']]);
    $mat['calificacion_final'] = $stmt_cal->fetchColumn() ?: 0;

    if ($mat['activo'] == 1 && $mat['grupo_estado'] == 'ACTIVO' && $mat['estatus'] == 'INSCRITO') {
        $materias_actuales[] = $mat;
    } else {
        $historial[] = $mat;
    }
    
    if ($mat['nivel'] >= 4) {
        $idiomas_nivel_4[$mat['materia']] = true;
    }
}

$idiomas_nivel_4 = array_keys($idiomas_nivel_4);

$stmt_cert = $pdo->prepare("SELECT * FROM certificaciones WHERE alumno_id = ?");
$stmt_cert->execute([$alumno_id]);
$certificaciones_bd = [];
while($row = $stmt_cert->fetch(PDO::FETCH_ASSOC)) {
    $certificaciones_bd[mb_strtoupper(trim($row['idioma']), 'UTF-8')] = $row;
}

$stmt_diag = $pdo->prepare("SELECT * FROM examenes_diagnosticos WHERE alumno_id = ? ORDER BY fecha_realizacion DESC");
$stmt_diag->execute([$alumno_id]);
$examenes_diagnosticos = $stmt_diag->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente Alumno | <?php echo htmlspecialchars($nombre_completo); ?></title>
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
                    Swal.fire({ title: '¡Éxito!', text: 'Los cambios fueron guardados correctamente.', icon: 'success', confirmButtonColor: 'var(--udg-blue)' });
                    const currentUrl = new URL(window.location.href); currentUrl.searchParams.delete('exito');
                    window.history.replaceState({}, document.title, currentUrl.pathname + currentUrl.search);
                });
            </script>
        <?php endif; ?>

        <div class="expediente-header">
            <img src="<?php echo $foto_perfil; ?>" alt="Foto" class="expediente-avatar">
            <div>
                <h1 class="expediente-title"><?php echo htmlspecialchars($nombre_completo); ?></h1>
                <p class="expediente-badges">
                    <span><i class="fas fa-user-graduate"></i> Alumno</span> | 
                    <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($perfil['carrera'] ?? 'Sin Carrera'); ?></span> | 
                    <span><i class="fas fa-id-badge"></i> Código: <?php echo htmlspecialchars($perfil['codigo'] ?: 'N/A'); ?></span> | 
                    <span><i class="fas fa-calendar-check"></i> Ingreso: <?php echo htmlspecialchars($perfil['periodo_ingreso'] ?: 'N/A'); ?></span> | 
                    <span>
                        <?php 
                            if ($perfil['genero'] == 'MASCULINO') echo '<i class="fas fa-mars" style="color:#60a5fa;"></i> Masculino';
                            elseif ($perfil['genero'] == 'FEMENINO') echo '<i class="fas fa-venus" style="color:#f472b6;"></i> Femenino';
                            elseif ($perfil['genero'] == 'OTRO') echo '<i class="fas fa-transgender-alt" style="color:#c084fc;"></i> Otro';
                            else echo '<i class="fas fa-genderless text-muted"></i> No especificado';
                        ?>
                    </span>
                </p>
            </div>
        </div>

        <div class="card card-mb-20">
            <h3 class="section-title-border"><i class="fas fa-info-circle"></i> Información de Contacto</h3>
            <div class="info-contact-grid">
                <div>
                    <label class="info-label">Correo Electrónico</label>
                    <div class="info-value"><i class="fas fa-envelope info-icon"></i> <?php echo htmlspecialchars($perfil['correo']); ?></div>
                </div>
                <div>
                    <label class="info-label">Teléfono</label>
                    <div class="info-value"><i class="fas fa-phone info-icon"></i> <?php echo htmlspecialchars($perfil['telefono'] ?: 'No registrado'); ?></div>
                </div>
                <div>
                    <label class="info-label">Estado del Usuario</label>
                    <div class="info-value">
                        <?php if($perfil['estatus'] == 'ACTIVO'): ?>
                            <span class="tag-active-status"><i class="fas fa-check-circle"></i> Activo</span>
                        <?php else: ?>
                            <span class="tag-inactive-status"><i class="fas fa-times-circle"></i> Inactivo</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="expediente-grid-main">
            
            <div>
                <div class="card card-mt-0">
                    <h3><i class="fas fa-book-reader"></i> Cursando Actualmente</h3>
                    <?php if (count($materias_actuales) > 0): ?>
                        <?php foreach ($materias_actuales as $mat): 
                            $stmt_max = $pdo->prepare("SELECT SUM(puntos_maximos) FROM criterios_evaluacion WHERE materia_id = ?");
                            $stmt_max->execute([$mat['materia_id']]);
                            $max_puntos = $stmt_max->fetchColumn() ?: 0;
                            $puntos_actuales = $mat['calificacion_final'];
                            $porcentaje = ($max_puntos > 0) ? ($puntos_actuales / $max_puntos) * 100 : 0;
                            $color_bar = ($porcentaje >= 60) ? 'var(--udg-light)' : '#dc3545';
                        ?>
                            <div class="subject-card">
                                <div class="subject-header">
                                    <strong><?php echo htmlspecialchars($mat['materia'] . ' Nivel ' . $mat['nivel']); ?></strong>
                                    <div class="subject-actions">
                                        <span class="subject-score"><?php echo floatval($puntos_actuales); ?> / <?php echo $max_puntos; ?> pts</span>
                                        <button class="btn-save btn-sm" onclick="abrirModalCalif(<?php echo $mat['inscripcion_id']; ?>)"><i class="fas fa-edit"></i> Calificar</button>
                                    </div>
                                </div>
                                <span class="subject-meta">Ciclo: <?php echo htmlspecialchars($mat['ciclo']); ?> | NRC: <?php echo $mat['nrc']; ?></span>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="background-color: <?php echo $color_bar; ?>; width: <?php echo min($porcentaje, 100); ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state"><i class="fas fa-bed"></i><p>No está inscrito en ninguna materia activa.</p></div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h3><i class="fas fa-history"></i> Historial Académico (Kárdex)</h3>
                    <p class="section-subtitle">Haz clic en cualquier materia para ver o editar sus calificaciones.</p>
                    <div style="overflow-x:auto;">
                        <table class="table-clean history-table">
                            <thead>
                                <tr><th>Ciclo</th><th>Materia</th><th class="text-center">Calificación</th><th class="text-center">Estado y Resultado</th></tr>
                            </thead>
                            <tbody>
                                <?php if(count($historial) > 0): ?>
                                    <?php foreach($historial as $h): $calif = floatval($h['calificacion_final']); ?>
                                        <tr class="clickable-row" onclick="abrirModalCalif(<?php echo $h['inscripcion_id']; ?>)">
                                            <td><?php echo htmlspecialchars($h['ciclo']); ?></td>
                                            <td class="subject-score"><?php echo htmlspecialchars($h['materia'] . ' ' . $h['nivel']); ?></td>
                                            <td class="text-center" style="font-size:1.1rem; font-weight:bold;"><?php echo $calif; ?></td>
                                            <td class="text-center">
                                                <?php if($h['grupo_estado'] == 'ACTIVO' && $h['activo'] == 1): ?>
                                                    <span class="tag-active-mini"><i class="fas fa-circle" style="font-size:0.5rem;"></i> Activa</span><br>
                                                <?php else: ?>
                                                    <span class="tag-closed-mini"><i class="fas fa-archive" style="font-size:0.6rem;"></i> Finalizada</span><br>
                                                <?php endif; ?>
                                                
                                                <?php if($calif >= 60): ?>
                                                    <span class="tag-aprobado" style="padding: 3px 8px; border-radius: 10px; font-size: 0.8rem;">Aprobado</span>
                                                <?php else: ?>
                                                    <span class="tag-rechazada" style="padding: 3px 8px; border-radius: 10px; font-size: 0.8rem;">Reprobado</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted" style="padding: 20px;">Sin registros en ciclos anteriores.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div>
                <div class="card card-highlighted">
                    <div class="card-actions-top">
                        <button class="btn-save btn-sm" onclick="abrirModalDiag('', '', '', '', '', '')" title="Agregar Nuevo Diagnóstico"><i class="fas fa-plus"></i> Agregar</button>
                        <?php if(count($examenes_diagnosticos) > 0): ?>
                            <button class="btn-cancel-sm" onclick="handleEditDiagnostico()" title="Editar Diagnóstico"><i class="fas fa-pen"></i> Editar</button>
                        <?php endif; ?>
                    </div>

                    <div class="card-header-center">
                        <i class="fas fa-clipboard-check card-icon-medium"></i>
                        <h3 style="color: var(--udg-blue); margin: 0;">Examen Diagnóstico</h3>
                        <p style="font-size: 0.85rem; color: #666; margin-top: 5px;">Resultados de ubicación inicial</p>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <?php if(count($examenes_diagnosticos) > 0): ?>
                            <?php foreach($examenes_diagnosticos as $diag): ?>
                                <div class="diag-card">
                                    <div class="diag-header">
                                        <strong class="diag-title"><?php echo htmlspecialchars($diag['idioma']); ?></strong>
                                        <span class="diag-badge"><?php echo htmlspecialchars($diag['periodo']); ?></span>
                                    </div>
                                    <div class="diag-details">
                                        <div><i class="fas fa-layer-group text-muted"></i> Nivel: <strong><?php echo htmlspecialchars($diag['nivel_asignado']); ?></strong></div>
                                        <div><i class="fas fa-star text-muted"></i> Calif: <strong><?php echo htmlspecialchars($diag['calificacion_texto']); ?></strong></div>
                                        <div class="col-span-2"><i class="far fa-calendar-alt text-muted"></i> Fecha: <?php echo date('d/m/Y', strtotime($diag['fecha_realizacion'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state-box">
                                <i class="fas fa-search" style="font-size: 2rem; color: #ddd; margin-bottom: 10px; display: block;"></i>
                                Sin registro de examen diagnóstico.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if(count($idiomas_nivel_4) > 0): ?>
                    <div class="card card-highlighted">
                        <div class="card-header-center">
                            <i class="fas fa-certificate card-icon-large"></i>
                            <h3 style="color: var(--udg-blue); margin: 0;">Certificaciones</h3>
                            <p style="font-size: 0.85rem; color: #666;">Idiomas con Nivel 4 o superior</p>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <?php foreach($idiomas_nivel_4 as $idioma): 
                                $idioma_key = mb_strtoupper(trim($idioma), 'UTF-8');
                                $cert = $certificaciones_bd[$idioma_key] ?? null;
                                
                                $nivel_obt = $cert['nivel_obtenido'] ?? 'Sin registrar';
                                $puntaje_obt = $cert['puntaje'] ?? '';
                                $periodo_obt = $cert['periodo'] ?? '';
                                $fecha_obt = (!empty($cert['fecha_aplicacion']) && $cert['fecha_aplicacion'] !== '0000-00-00') ? $cert['fecha_aplicacion'] : '';
                            ?>
                                <div class="cert-card">
                                    <div>
                                        <strong class="cert-title"><?php echo htmlspecialchars((string)$idioma); ?></strong>
                                        <span class="cert-meta">Nivel: <strong style="color:var(--udg-blue);"><?php echo htmlspecialchars((string)$nivel_obt); ?></strong></span>
                                        
                                        <?php if($cert): ?>
                                            <div class="cert-details">
                                                <div><i class="fas fa-star text-muted"></i> Pts: <strong><?php echo htmlspecialchars((string)($puntaje_obt ?: '-')); ?></strong></div>
                                                <div><i class="fas fa-calendar-alt text-muted"></i> Per: <strong><?php echo htmlspecialchars((string)($periodo_obt ?: '-')); ?></strong></div>
                                                <div class="col-span-2"><i class="far fa-calendar-check text-muted"></i> Fecha: <?php echo $fecha_obt ? date('d/m/Y', strtotime($fecha_obt)) : '-'; ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn-save btn-sm" onclick="abrirModalCert('<?php echo htmlspecialchars((string)$idioma); ?>', '<?php echo htmlspecialchars((string)($nivel_obt == 'Sin registrar' ? '' : $nivel_obt)); ?>', '<?php echo htmlspecialchars((string)$puntaje_obt); ?>', '<?php echo htmlspecialchars((string)$periodo_obt); ?>', '<?php echo htmlspecialchars((string)$fecha_obt); ?>')">
                                        <i class="fas fa-edit"></i> Asignar
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card card-mt-0 cert-locked">
                        <i class="fas fa-lock" style="font-size: 2.5rem; color: #ddd; margin-bottom: 15px;"></i>
                        <h4 style="color: #666; margin: 0;">Certificación Bloqueada</h4>
                        <p style="font-size: 0.85rem; color: #999; margin-top: 10px;">Disponible al cursar Nivel 4.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach ($todas_materias as $mat): 
            $stmt_crit = $pdo->prepare("SELECT * FROM criterios_evaluacion WHERE materia_id = ? ORDER BY categoria ASC");
            $stmt_crit->execute([$mat['materia_id']]);
            $criterios_materia = $stmt_crit->fetchAll(PDO::FETCH_ASSOC);
            
            $stmt_cal_exist = $pdo->prepare("SELECT tipo_examen, puntaje FROM calificaciones WHERE inscripcion_id = ?");
            $stmt_cal_exist->execute([$mat['inscripcion_id']]);
            $calif_existentes = [];
            while($row = $stmt_cal_exist->fetch(PDO::FETCH_ASSOC)) { $calif_existentes[$row['tipo_examen']] = $row['puntaje']; }
        ?>
        <div id="modalCalif_<?php echo $mat['inscripcion_id']; ?>" class="modal-overlay" style="display:none;">
            <div class="modal-content clean-modal">
                <div class="modal-header-clean">
                    <h2><i class="fas fa-edit"></i> <?php echo htmlspecialchars((string)($mat['materia'] . ' ' . $mat['nivel'])); ?></h2>
                    <button class="close-btn" onclick="cerrarModalCalif(<?php echo $mat['inscripcion_id']; ?>)">&times;</button>
                </div>
                <form method="POST" class="form-margin-0">
                    <input type="hidden" name="actualizar_calificaciones" value="1">
                    <input type="hidden" name="inscripcion_id" value="<?php echo $mat['inscripcion_id']; ?>">
                    <div class="modal-body-scroll">
                        <?php if($mat['grupo_estado'] == 'CERRADO' || $mat['activo'] == 0): ?>
                            <div class="alert-warning-mini">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Atención:</strong> Estás editando las calificaciones de una clase finalizada.
                            </div>
                        <?php endif; ?>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                            <?php foreach($criterios_materia as $crit): 
                                $codigo = $crit['codigo_examen'];
                                $val = isset($calif_existentes[$codigo]) ? floatval($calif_existentes[$codigo]) : '';
                            ?>
                                <div class="form-group mb-0">
                                    <label><i class="fas <?php echo htmlspecialchars((string)$crit['icono']); ?>" style="color: <?php echo htmlspecialchars((string)$crit['color']); ?>;"></i> <?php echo htmlspecialchars((string)$crit['nombre_examen']); ?> <span class="text-muted">(Máx: <?php echo floatval($crit['puntos_maximos']); ?>)</span></label>
                                    <input type="number" step="0.01" min="0" max="<?php echo floatval($crit['puntos_maximos']); ?>" name="calificaciones[<?php echo htmlspecialchars((string)$codigo); ?>]" value="<?php echo htmlspecialchars((string)$val); ?>" placeholder="Sin evaluar">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer-clean">
                        <button type="button" class="btn-cancel" onclick="cerrarModalCalif(<?php echo $mat['inscripcion_id']; ?>)">Cancelar</button>
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>

        <div id="modalCert" class="modal-overlay" style="display:none;">
            <div class="modal-content clean-modal">
                <div class="modal-header-clean">
                    <h2><i class="fas fa-award"></i> Asignar Certificación</h2>
                    <button class="close-btn" onclick="cerrarModalCert()">&times;</button>
                </div>
                <form method="POST" class="form-margin-0">
                    <input type="hidden" name="guardar_certificacion" value="1">
                    <input type="hidden" name="alumno_id_cert" value="<?php echo $alumno_id; ?>">
                    <input type="hidden" name="idioma_cert" id="inputIdiomaCert">
                    <div class="modal-body-scroll">
                        <p style="font-size: 0.9rem; color: #666; margin-top: 0; margin-bottom: 15px;">Actualizando nivel oficial obtenido en: <strong id="textoIdiomaCert" style="color: var(--udg-blue);"></strong></p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group mb-0"><label>Nivel (Ej. B1, B2, C1)</label><input type="text" name="nivel_cert" id="inputNivelCert" required placeholder="Ej. B2" style="text-transform: uppercase;"></div>
                            <div class="form-group mb-0"><label>Puntaje Obtenido</label><input type="text" name="puntaje_cert" id="inputPuntajeCert" placeholder="Ej. 550"></div>
                            <div class="form-group mb-0"><label>Periodo</label><input type="text" name="periodo_cert" id="inputPeriodoCert" placeholder="Ej. 2022B" style="text-transform: uppercase;"></div>
                            <div class="form-group mb-0"><label>Fecha de Aplicación</label><input type="date" name="fecha_cert" id="inputFechaCert"></div>
                        </div>
                    </div>
                    <div class="modal-footer-clean">
                        <button type="button" class="btn-cancel" onclick="cerrarModalCert()">Cancelar</button>
                        <button type="submit" class="btn-save" style="background:var(--udg-blue); color:white;"><i class="fas fa-save"></i> Guardar Nivel</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="modalDiag" class="modal-overlay" style="display:none;">
            <div class="modal-content clean-modal">
                <div class="modal-header-clean">
                    <h2 id="modalDiagTitle"><i class="fas fa-clipboard-check"></i> Examen Diagnóstico</h2>
                    <button class="close-btn" onclick="cerrarModalDiag()">&times;</button>
                </div>
                <form method="POST" class="form-margin-0">
                    <input type="hidden" name="guardar_diagnostico" value="1">
                    <input type="hidden" name="examen_id" id="inputExamenId">
                    <input type="hidden" name="alumno_id_diag" value="<?php echo $alumno_id; ?>">
                    <div class="modal-body-scroll">
                        <div class="form-group"><label>Idioma</label><input type="text" name="idioma_diag" id="inputIdiomaDiag" required placeholder="Ej. Inglés"></div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div class="form-group mb-0"><label>Nivel Asignado</label><input type="number" name="nivel_diag" id="inputNivelDiag" required min="1" max="10"></div>
                            <div class="form-group mb-0"><label>Calificación Textual</label><input type="text" name="calif_diag" id="inputCalifDiag" required placeholder="Ej. A2 INICIAL" style="text-transform: uppercase;"></div>
                            <div class="form-group mb-0"><label>Periodo</label><input type="text" name="periodo_diag" id="inputPeriodoDiag" required placeholder="Ej. 2022-B" style="text-transform: uppercase;"></div>
                            <div class="form-group mb-0"><label>Fecha de Realización</label><input type="date" name="fecha_diag" id="inputFechaDiag" required></div>
                        </div>
                    </div>
                    <div class="modal-footer-clean">
                        <button type="button" class="btn-cancel" onclick="cerrarModalDiag()">Cancelar</button>
                        <button type="submit" class="btn-save" style="background:#17a2b8; color:white;"><i class="fas fa-save"></i> Guardar Diagnóstico</button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <script>
        const examenesGuardados = <?php echo json_encode($examenes_diagnosticos ?? []); ?>;
    </script>
    <script src="../js/expediente_alumno.js?v=<?php echo time(); ?>"></script>
</body>
</html>