<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') { header("Location: ../index.php"); exit; }

$stmt_al = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
$stmt_al->execute([$_SESSION['user_id']]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);
$alumno_id = $alumno['alumno_id'];

$mensaje = ''; $tipo_mensaje = '';

// =======================================================
// PROCESAR FORMULARIOS DE BAJA (CON CIBERSEGURIDAD)
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'solicitar_baja') {
        $insc_baja = $_POST['inscripcion_id'];
        $motivo = strip_tags(trim($_POST['motivo']));
        $descripcion = substr(strip_tags(trim($_POST['descripcion'])), 0, 250);
        
        $chk = $pdo->prepare("SELECT COUNT(*) FROM solicitudes_bajas WHERE inscripcion_id = ? AND estatus = 'PENDIENTE'");
        $chk->execute([$insc_baja]);
        if ($chk->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO solicitudes_bajas (inscripcion_id, motivo, descripcion) VALUES (?, ?, ?)")->execute([$insc_baja, $motivo, $descripcion]);
            $mensaje = "Tu solicitud de baja ha sido enviada a Administración."; $tipo_mensaje = "success";
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] == 'cancelar_baja') {
        $solicitud_id = $_POST['solicitud_id'];
        $pdo->prepare("UPDATE solicitudes_bajas SET estatus = 'CANCELADA' WHERE solicitud_id = ?")->execute([$solicitud_id]);
        $mensaje = "Has retirado tu solicitud de baja exitosamente."; $tipo_mensaje = "success";
    }
}

// Obtener materias inscritas
$sql_materias = "SELECT i.inscripcion_id, m.materia_id, m.nombre AS materia, m.nivel, c.nombre AS ciclo,
                        u.nombre AS prof_nombre, u.apellido_paterno AS prof_ap_pat, u.apellido_materno AS prof_ap_mat
                 FROM inscripciones i
                 JOIN grupos g ON i.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                 JOIN usuarios u ON g.profesor_id = u.usuario_id
                 WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'";
$stmt_mat = $pdo->prepare($sql_materias); $stmt_mat->execute([$alumno_id]);
$materias_inscritas = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

$ins_activa = isset($_GET['ins']) ? $_GET['ins'] : (count($materias_inscritas) > 0 ? $materias_inscritas[0]['inscripcion_id'] : null);

$materia_actual = null;
foreach ($materias_inscritas as $m) { if ($m['inscripcion_id'] == $ins_activa) { $materia_actual = $m; break; } }

// =======================================================
// OBTENER HISTORIAL COMPLETO DE SOLICITUDES DE ESTA MATERIA
// =======================================================
$historial_solicitudes = [];
$solicitud_pendiente = null;
$hay_rechazada_reciente = false;

if ($ins_activa) {
    $stmt_sol = $pdo->prepare("SELECT * FROM solicitudes_bajas WHERE inscripcion_id = ? ORDER BY fecha_solicitud DESC");
    $stmt_sol->execute([$ins_activa]);
    $historial_solicitudes = $stmt_sol->fetchAll(PDO::FETCH_ASSOC);

    if (count($historial_solicitudes) > 0) {
        $ultima = $historial_solicitudes[0];
        if ($ultima['estatus'] === 'PENDIENTE') {
            $solicitud_pendiente = $ultima;
        } elseif ($ultima['estatus'] === 'RECHAZADA') {
            $hay_rechazada_reciente = true;
        }
    }
}

// Cargar Criterios
$evaluacion = []; $puntos_totales_posibles = 0;
if ($materia_actual) {
    $stmt_crit = $pdo->prepare("SELECT * FROM criterios_evaluacion WHERE materia_id = ? ORDER BY criterio_id ASC");
    $stmt_crit->execute([$materia_actual['materia_id']]);
    $criterios_bd = $stmt_crit->fetchAll(PDO::FETCH_ASSOC);
    foreach ($criterios_bd as $crit) {
        $cat = $crit['categoria'];
        if (!isset($evaluacion[$cat])) { $evaluacion[$cat] = ['icono' => $crit['icono'], 'color' => $crit['color'], 'items' => []]; }
        $evaluacion[$cat]['items'][$crit['codigo_examen']] = ['nombre' => $crit['nombre_examen'], 'max' => floatval($crit['puntos_maximos'])];
        $puntos_totales_posibles += floatval($crit['puntos_maximos']);
    }
}

// Cargar Calificaciones
$calificaciones_bd = []; $puntaje_total_acumulado = 0;
if ($ins_activa) {
    $stmt_cal = $pdo->prepare("SELECT tipo_examen, puntaje FROM calificaciones WHERE inscripcion_id = ?");
    $stmt_cal->execute([$ins_activa]);
    while ($row = $stmt_cal->fetch(PDO::FETCH_ASSOC)) {
        $calificaciones_bd[$row['tipo_examen']] = $row['puntaje'];
        if ($row['puntaje'] !== null) $puntaje_total_acumulado += floatval($row['puntaje']);
    }
}
$porcentaje_total = $puntos_totales_posibles > 0 ? ($puntaje_total_acumulado / $puntos_totales_posibles) * 100 : 0;

if (!function_exists('format_score')) {
    function format_score($num) { return floatval($num) == intval($num) ? intval($num) : floatval($num); }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificaciones | E-Pale</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content" style="max-width: 800px;"> 

        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">           
            <?php if($materia_actual): ?>
                <div style="width: 250px;">
                    <?php if($solicitud_pendiente): ?>
                        <button class="btn-pendiente" onclick="abrirModal('modalRetirarBaja')">
                            <i class="fas fa-clock"></i> Solicitud Pendiente
                        </button>
                    <?php else: ?>
                        <button class="btn-baja" onclick="abrirModal('modalSolicitarBaja')">
                            <i class="fas fa-sign-out-alt"></i> Solicitar baja
                        </button>
                    <?php endif; ?>

                    <?php if(count($historial_solicitudes) > 0): ?>
                        <button class="btn-historial" onclick="abrirModal('modalHistorial')">
                            <i class="fas fa-history"></i> Historial de Peticiones
                            <?php if($hay_rechazada_reciente): ?>
                                <i class="fas fa-circle" style="color: #dc3545; font-size: 0.6rem; animation: blink 2s infinite;"></i>
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if($mensaje): ?>
            <div style="padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; background: <?php echo ($tipo_mensaje == 'success') ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo ($tipo_mensaje == 'success') ? '#155724' : '#721c24'; ?>;">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <?php if($materia_actual): ?>
            <div class="page-title-center">
                <h1><i class="fas fa-award"></i> Calificaciones</h1>
                <p><?php echo htmlspecialchars($materia_actual['materia'] . ' ' . $materia_actual['nivel'] . ' - Semestre ' . $materia_actual['ciclo']); ?></p>
                <p style="color: var(--text-dark); font-weight: 500; margin-top: 10px;"><i class="fas fa-chalkboard-teacher" style="color:#aaa;"></i> Profesor: <?php echo htmlspecialchars(trim($materia_actual['prof_nombre'] . ' ' . $materia_actual['prof_ap_pat'])); ?></p>

                <?php if(count($materias_inscritas) > 0): ?>
                    <div style="margin-top: 20px;">
                        <select class="subject-selector" style="font-size: 1rem; padding: 10px 20px; text-align: center; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #ddd;" onchange="window.location.href='calificaciones.php?ins='+this.value">
                            <?php foreach($materias_inscritas as $m): ?>
                                <option value="<?php echo $m['inscripcion_id']; ?>" <?php echo ($m['inscripcion_id'] == $ins_activa) ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['materia'] . ' ' . $m['nivel'] . ' - ' . $m['ciclo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <?php if(empty($evaluacion)): ?>
                <div style="text-align:center; padding:50px; background:white; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.05);"><i class="fas fa-tools" style="font-size: 3rem; color: #ccc; margin-bottom: 15px;"></i><h3 style="color:#666;">Criterios no disponibles</h3></div>
            <?php else: ?>
                <div class="total-score-banner">
                    <h3>Calificación Total Acumulada</h3>
                    <span class="big-score"><?php echo format_score($puntaje_total_acumulado); ?></span>
                    <p>de <?php echo $puntos_totales_posibles; ?> puntos posibles</p>
                    <div class="total-progress-bg"><div class="total-progress-fill" style="width: <?php echo $porcentaje_total; ?>%;"></div></div>
                </div>

                <?php foreach($evaluacion as $categoria => $datos_cat): ?>
                    <div class="grade-category-card" style="border-top-color: <?php echo $datos_cat['color']; ?>;">
                        <h4><i class="fas <?php echo $datos_cat['icono']; ?>"></i> <?php echo $categoria; ?></h4>
                        <?php foreach($datos_cat['items'] as $codigo => $item): 
                            $esta_registrada = array_key_exists($codigo, $calificaciones_bd);
                            $puntaje = $esta_registrada ? $calificaciones_bd[$codigo] : null;
                            $max = $item['max'];
                            if ($puntaje === null) { $html_score = '<span class="badge-pending">Pendiente</span>'; $html_progress = ''; } 
                            else { $porcentaje = ($puntaje / $max) * 100; $color = ($porcentaje >= 60) ? '#28a745' : '#dc3545'; $html_score = '<span class="score-text">' . format_score($puntaje) . ' / ' . $max . '</span>'; $html_progress = '<div class="progress-mini"><div class="progress-bar" style="width: ' . $porcentaje . '%; background-color:' . $color . ';"></div></div>'; }
                        ?>
                            <div class="detailed-grade-item"><div class="dg-info"><strong><?php echo $item['nombre']; ?></strong><span><?php echo ($puntaje !== null) ? 'Calificado' : 'Aún no evaluado'; ?></span></div><div class="dg-score"><?php echo $html_score . $html_progress; ?></div></div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div id="modalSolicitarBaja" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header"><h3 style="margin:0; color:#dc3545;"><i class="fas fa-exclamation-triangle"></i> Solicitar Baja</h3><button style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="cerrarModal('modalSolicitarBaja')">&times;</button></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="solicitar_baja">
                        <input type="hidden" name="inscripcion_id" value="<?php echo $ins_activa; ?>">
                        <div class="modal-body">
                            <p style="font-size:0.9rem; color:#666; margin-bottom:15px;">Estás solicitando la baja de la materia <strong><?php echo htmlspecialchars($materia_actual['materia']); ?></strong>.</p>
                            <label style="font-weight:bold; display:block; margin-bottom:5px;">Motivo Principal</label>
                            <select name="motivo" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; margin-bottom:15px;">
                                <option value="">Selecciona un motivo...</option>
                                <option value="Choque de horario">Choque de horario</option>
                                <option value="Problemas laborales/personales">Problemas laborales o personales</option>
                                <option value="Cambio de carrera/institución">Cambio de carrera/institución</option>
                                <option value="Inscripción por error">Inscripción por error</option>
                                <option value="Otro">Otro</option>
                            </select>
                            
                            <label style="font-weight:bold; display:block; margin-bottom:5px;">Descripción Breve</label>
                            <textarea name="descripcion" id="descBajaInput" rows="4" maxlength="250" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; resize:none;" placeholder="Explica tu situación (Máx. 250 caracteres)..."></textarea>
                            
                            <div style="text-align: right; font-size: 0.8rem; color: #888; margin-top: 5px; font-weight: bold;">
                                <span id="charCount">0</span> / 250
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" style="padding:8px 15px; background:#fff; border:1px solid #ccc; border-radius:6px; cursor:pointer;" onclick="cerrarModal('modalSolicitarBaja')">Cancelar</button>
                            <button type="submit" style="padding:8px 15px; background:#dc3545; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">Enviar Solicitud</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if($solicitud_pendiente): ?>
            <div id="modalRetirarBaja" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header"><h3 style="margin:0; color:#856404;"><i class="fas fa-clock"></i> Solicitud en Proceso</h3><button style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="cerrarModal('modalRetirarBaja')">&times;</button></div>
                    <form method="POST">
                        <input type="hidden" name="action" value="cancelar_baja">
                        <input type="hidden" name="solicitud_id" value="<?php echo $solicitud_pendiente['solicitud_id']; ?>">
                        <div class="modal-body">
                            <p style="margin-top:0;">Tu solicitud fue enviada el <strong><?php echo date('d/m/Y', strtotime($solicitud_pendiente['fecha_solicitud'])); ?></strong>.</p>
                            <div style="background:#f8f9fa; padding:10px; border-radius:6px; border-left:4px solid #ffc107; font-size:0.9rem;">
                                <strong>Motivo:</strong> <?php echo htmlspecialchars($solicitud_pendiente['motivo']); ?><br>
                                <?php if($solicitud_pendiente['descripcion']) echo "<strong>Descripción:</strong> " . htmlspecialchars($solicitud_pendiente['descripcion']); ?>
                            </div>
                            <p style="font-size:0.85rem; color:#888; margin-top:15px;">Si cambiaste de opinión, puedes retirar tu solicitud ahora mismo.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" style="padding:8px 15px; background:#fff; border:1px solid #ccc; border-radius:6px; cursor:pointer;" onclick="cerrarModal('modalRetirarBaja')">Cerrar</button>
                            <button type="submit" style="padding:8px 15px; background:#6c757d; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">Retirar mi solicitud</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if(count($historial_solicitudes) > 0): ?>
            <div id="modalHistorial" class="modal-overlay">
                <div class="modal-content" style="max-width: 600px;">
                    <div class="modal-header">
                        <h3 style="margin:0; color:var(--udg-blue);"><i class="fas fa-history"></i> Historial de Solicitudes</h3>
                        <button style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="cerrarModal('modalHistorial')">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p style="font-size:0.9rem; color:#666; margin-top:0;">Historial de peticiones de baja para <strong><?php echo htmlspecialchars($materia_actual['materia']); ?></strong>.</p>
                        
                        <?php foreach($historial_solicitudes as $h): 
                            $class_card = strtolower($h['estatus']);
                            $icono_estatus = ''; $color_estatus = '';
                            
                            switch($h['estatus']) {
                                case 'PENDIENTE': $icono_estatus = 'fa-clock'; $color_estatus = '#856404'; break;
                                case 'RECHAZADA': $icono_estatus = 'fa-times-circle'; $color_estatus = '#dc3545'; break;
                                case 'CANCELADA': $icono_estatus = 'fa-ban'; $color_estatus = '#6c757d'; break;
                                case 'APROBADA':  $icono_estatus = 'fa-check-circle'; $color_estatus = '#28a745'; break;
                            }
                        ?>
                            <div class="history-card <?php echo $class_card; ?>">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                    <div style="font-weight: bold; color: #333; font-size: 0.95rem;"><?php echo htmlspecialchars($h['motivo']); ?></div>
                                    <div style="font-size: 0.8rem; font-weight: bold; color: <?php echo $color_estatus; ?>; background: white; padding: 3px 8px; border-radius: 12px; border: 1px solid <?php echo $color_estatus; ?>;">
                                        <i class="fas <?php echo $icono_estatus; ?>"></i> <?php echo $h['estatus']; ?>
                                    </div>
                                </div>
                                
                                <div style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">
                                    <i class="far fa-calendar-alt"></i> Solicitada el: <?php echo date('d/m/Y', strtotime($h['fecha_solicitud'])); ?>
                                </div>

                                <?php if(!empty($h['descripcion'])): ?>
                                    <div style="font-size: 0.85rem; color: #555; background: rgba(0,0,0,0.03); padding: 8px; border-radius: 4px; margin-bottom: 10px; font-style: italic;">
                                        "<?php echo htmlspecialchars($h['descripcion']); ?>"
                                    </div>
                                <?php endif; ?>

                                <?php if($h['estatus'] !== 'PENDIENTE' && $h['estatus'] !== 'CANCELADA'): ?>
                                    <div style="border-top: 1px dashed #ddd; padding-top: 10px; margin-top: 10px;">
                                        <div style="font-size: 0.8rem; color: #888; margin-bottom: 4px;">
                                            <i class="fas fa-reply"></i> Respuesta de Administración (<?php echo date('d/m/Y', strtotime($h['fecha_respuesta'])); ?>):
                                        </div>
                                        <div style="font-size: 0.9rem; color: #333; font-weight: 500;">
                                            <?php echo htmlspecialchars($h['respuesta_admin']) ?: 'Sin comentarios adicionales.'; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align:center; padding:50px; color:#888;"><h2>No estás inscrito en ninguna materia.</h2></div>
        <?php endif; ?>
    </main>

    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        function abrirModal(id) { document.getElementById(id).style.display = 'flex'; }
        function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }
        
        const descInput = document.getElementById('descBajaInput');
        const charCount = document.getElementById('charCount');
        if (descInput && charCount) {
            descInput.addEventListener('input', function() {
                charCount.textContent = this.value.length;
                if(this.value.length >= 250) { charCount.style.color = '#dc3545'; } 
                else { charCount.style.color = '#888'; }
            });
        }

        if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href); }
    </script>
</body>
</html>
