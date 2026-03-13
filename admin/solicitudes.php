<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

$mensaje = ''; $tipo_mensaje = '';

// =======================================================
// PROCESAR APROBACIÓN / RECHAZO
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $solicitud_id = $_POST['solicitud_id'];
    $inscripcion_id = $_POST['inscripcion_id'];
    $respuesta = strip_tags(trim($_POST['respuesta_admin']));
    
    try {
        $pdo->beginTransaction();
        
        if ($_POST['action'] === 'aprobar') {
            $pdo->prepare("UPDATE solicitudes_bajas SET estatus = 'APROBADA', respuesta_admin = ?, fecha_respuesta = NOW() WHERE solicitud_id = ?")->execute([$respuesta, $solicitud_id]);
            $pdo->prepare("UPDATE inscripciones SET estatus = 'BAJA' WHERE inscripcion_id = ?")->execute([$inscripcion_id]);
            $pdo->prepare("DELETE FROM calificaciones WHERE inscripcion_id = ?")->execute([$inscripcion_id]);
            $mensaje = "Solicitud aprobada: El alumno ha sido dado de baja y sus calificaciones fueron eliminadas."; $tipo_mensaje = "success";
            
        } elseif ($_POST['action'] === 'rechazar') {
            $pdo->prepare("UPDATE solicitudes_bajas SET estatus = 'RECHAZADA', respuesta_admin = ?, fecha_respuesta = NOW() WHERE solicitud_id = ?")->execute([$respuesta, $solicitud_id]);
            $mensaje = "Solicitud rechazada. El alumno permanece en la clase."; $tipo_mensaje = "success";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error al procesar: " . $e->getMessage(); $tipo_mensaje = "error";
    }
}

// =======================================================
// LÓGICA DE PESTAÑAS (PENDIENTES VS HISTORIAL)
// =======================================================
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'pendientes';
$filtro_estatus = ($vista === 'historial') ? "sb.estatus != 'PENDIENTE'" : "sb.estatus = 'PENDIENTE'";
$orden = ($vista === 'historial') ? "sb.fecha_solicitud DESC" : "sb.fecha_solicitud ASC";

$sql = "SELECT sb.*, u.nombre, u.apellido_paterno, u.codigo, 
               m.nombre AS materia, m.nivel, c.nombre AS ciclo, g.nrc
        FROM solicitudes_bajas sb
        JOIN inscripciones i ON sb.inscripcion_id = i.inscripcion_id
        JOIN alumnos a ON i.alumno_id = a.alumno_id
        JOIN usuarios u ON a.usuario_id = u.usuario_id
        JOIN grupos g ON i.nrc = g.nrc
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        WHERE $filtro_estatus
        ORDER BY $orden";
$solicitudes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Contar pendientes para la pestaña
$total_pendientes = $pdo->query("SELECT COUNT(*) FROM solicitudes_bajas WHERE estatus = 'PENDIENTE'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Baja | Admin</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 20px;">
            <h1><i class="fas fa-envelope-open-text"></i> Solicitudes de Baja</h1>
            <p>Administra las peticiones de los alumnos o consulta el archivo histórico.</p>
        </div>

        <?php if($mensaje): ?>
            <div style="padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; background: <?php echo ($tipo_mensaje == 'success') ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo ($tipo_mensaje == 'success') ? '#155724' : '#721c24'; ?>;">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <div class="tabs-container">
            <a href="?vista=pendientes" class="btn-tab <?php echo $vista == 'pendientes' ? 'active' : ''; ?>">
                <i class="fas fa-inbox"></i> Bandeja Pendientes
                <?php if($total_pendientes > 0): ?><span class="badge-tab"><?php echo $total_pendientes; ?></span><?php endif; ?>
            </a>
            <a href="?vista=historial" class="btn-tab <?php echo $vista == 'historial' ? 'active' : ''; ?>">
                <i class="fas fa-archive"></i> Historial Completo
            </a>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-wrapper" style="overflow-x:auto;">
                <table class="history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Fecha</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Alumno</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Materia</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">
                                <?php echo ($vista == 'pendientes') ? 'Motivo' : 'Estatus Final'; ?>
                            </th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($solicitudes) > 0): ?>
                            <?php foreach ($solicitudes as $s): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 15px; color: #666; font-size: 0.9rem;">
                                        <?php echo date('d/m/Y', strtotime($s['fecha_solicitud'])); ?><br>
                                        <small><?php echo date('H:i', strtotime($s['fecha_solicitud'])); ?></small>
                                    </td>
                                    <td style="padding: 15px;">
                                        <div style="font-weight: bold; color: var(--udg-blue);"><?php echo htmlspecialchars($s['nombre'] . ' ' . $s['apellido_paterno']); ?></div>
                                        <div style="font-size: 0.8rem; color: #888; font-family: monospace;">Código: <?php echo htmlspecialchars($s['codigo']); ?></div>
                                    </td>
                                    <td style="padding: 15px;">
                                        <div style="font-weight: bold; color: #333;"><?php echo htmlspecialchars($s['materia'] . ' ' . $s['nivel']); ?></div>
                                        <div style="font-size: 0.8rem; color: #888;">NRC: <?php echo htmlspecialchars($s['nrc']); ?> | <?php echo htmlspecialchars($s['ciclo']); ?></div>
                                    </td>
                                    
                                    <td style="padding: 15px; text-align: center;">
                                        <?php if($vista == 'pendientes'): ?>
                                            <span style="background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;"><?php echo htmlspecialchars($s['motivo']); ?></span>
                                        <?php else: ?>
                                            <?php 
                                                if($s['estatus'] == 'APROBADA') echo '<span class="tag-aprobada"><i class="fas fa-check-circle"></i> Aprobada</span>';
                                                elseif($s['estatus'] == 'RECHAZADA') echo '<span class="tag-rechazada"><i class="fas fa-times-circle"></i> Rechazada</span>';
                                                elseif($s['estatus'] == 'CANCELADA') echo '<span class="tag-cancelada"><i class="fas fa-ban"></i> Cancelada</span>';
                                            ?>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td style="padding: 15px; text-align: center;">
                                        <?php if($vista == 'pendientes'): ?>
                                            <button onclick="abrirReview(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8'); ?>)" style="background: var(--udg-blue); color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold;">
                                                <i class="fas fa-edit"></i> Evaluar
                                            </button>
                                        <?php else: ?>
                                            <button onclick="abrirReview(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8'); ?>)" style="background: #f1f3f5; color: #555; border: 1px solid #ccc; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold;">
                                                <i class="fas fa-eye"></i> Detalles
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 50px 20px; color: #888;">
                                    <?php if($vista == 'pendientes'): ?>
                                        <i class="fas fa-check-double" style="font-size: 3rem; color: #ddd; margin-bottom: 15px; display: block;"></i>
                                        No hay solicitudes de baja pendientes.
                                    <?php else: ?>
                                        <i class="fas fa-archive" style="font-size: 3rem; color: #ddd; margin-bottom: 15px; display: block;"></i>
                                        El historial está vacío.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <div id="modalReview" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0; color:var(--udg-blue);" id="modalTitle"><i class="fas fa-clipboard-list"></i> Detalles de la Solicitud</h3>
                <button style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="cerrarReview()">&times;</button>
            </div>
            
            <form method="POST" id="formReview" style="margin:0;">
                <input type="hidden" name="action" id="actionType" value="">
                <input type="hidden" name="solicitud_id" id="sol_id">
                <input type="hidden" name="inscripcion_id" id="insc_id">
                
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="sol-box">
                            <h4>Alumno</h4>
                            <div style="font-size:1rem; color:var(--udg-blue); font-weight:bold;" id="txt_alumno"></div>
                        </div>
                        <div class="sol-box" style="border-left: 4px solid var(--udg-light);">
                            <h4>Clase afectada</h4>
                            <div id="txt_clase" style="font-weight:bold; color:#333; font-size: 0.95rem;"></div>
                        </div>
                    </div>

                    <div class="sol-box" style="background: #fff8f8; border-color: #f5c6cb; border-left: 4px solid #dc3545;">
                        <h4>Motivo expresado por el alumno</h4>
                        <div id="txt_motivo" style="font-weight:bold; color:#dc3545; margin-bottom: 5px;"></div>
                        <div id="txt_desc"></div>
                    </div>

                    <div id="admin_input_area">
                        <label style="font-weight:bold; display:block; margin-bottom:5px;">Nota / Respuesta (Opcional)</label>
                        <textarea name="respuesta_admin" rows="2" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box;" placeholder="Mensaje visible para el alumno..."></textarea>
                        
                        <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 6px; font-size: 0.85rem; margin-top: 15px;">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Atención:</strong> Si apruebas esta solicitud, el alumno será expulsado de la clase y todas sus calificaciones registradas se borrarán permanentemente.
                        </div>
                    </div>

                    <div id="admin_response_area" style="display:none; border-top: 2px dashed #ddd; padding-top: 15px; margin-top: 15px;">
                        <h4 style="margin: 0 0 10px 0; color: #333; text-transform: uppercase; font-size: 0.9rem;">Resolución del Administrador</h4>
                        <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 10px;">
                            <div id="resp_estatus"></div>
                            <div style="font-size: 0.85rem; color: #888;"><i class="far fa-calendar-alt"></i> Fecha: <span id="resp_fecha"></span></div>
                        </div>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; border-left: 4px solid #ccc; color: #555; font-size: 0.95rem;" id="resp_texto"></div>
                    </div>
                </div>

                <div class="modal-footer" id="action_footer" style="justify-content: space-between;">
                    <button type="button" style="padding:10px 15px; background:#fff; border:1px solid #dc3545; color:#dc3545; border-radius:6px; cursor:pointer; font-weight:bold;" onclick="procesar('rechazar')"><i class="fas fa-times"></i> Rechazar Petición</button>
                    <button type="button" style="padding:10px 15px; background:#28a745; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;" onclick="procesar('aprobar')"><i class="fas fa-check"></i> Aprobar Baja</button>
                </div>

                <div class="modal-footer" id="close_footer" style="display:none;">
                    <button type="button" style="padding:10px 25px; background:#6c757d; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;" onclick="cerrarReview()">Cerrar Detalles</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        
        function abrirReview(solicitud) {
            document.getElementById('sol_id').value = solicitud.solicitud_id;
            document.getElementById('insc_id').value = solicitud.inscripcion_id;
            
            document.getElementById('txt_alumno').innerText = solicitud.nombre + ' ' + solicitud.apellido_paterno + ' (Código: ' + solicitud.codigo + ')';
            document.getElementById('txt_clase').innerText = solicitud.materia + ' ' + solicitud.nivel + ' (NRC: ' + solicitud.nrc + ')';
            document.getElementById('txt_motivo').innerText = solicitud.motivo;
            document.getElementById('txt_desc').innerText = solicitud.descripcion ? '"' + solicitud.descripcion + '"' : 'Sin descripción adicional.';
            
            const adminInputArea = document.getElementById('admin_input_area');
            const adminResponseArea = document.getElementById('admin_response_area');
            const actionFooter = document.getElementById('action_footer');
            const closeFooter = document.getElementById('close_footer');
            
            if (solicitud.estatus === 'PENDIENTE') {
                // Modo Evaluar
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-clipboard-list"></i> Evaluar Solicitud';
                adminInputArea.style.display = 'block';
                adminResponseArea.style.display = 'none';
                actionFooter.style.display = 'flex';
                closeFooter.style.display = 'none';
            } else {
                // Modo Lectura Histórica
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-archive"></i> Archivo Histórico';
                adminInputArea.style.display = 'none';
                adminResponseArea.style.display = 'block';
                actionFooter.style.display = 'none';
                closeFooter.style.display = 'flex';
                
                // Formatear Estatus
                let tagHtml = '';
                if(solicitud.estatus === 'APROBADA') tagHtml = '<span class="tag-aprobada"><i class="fas fa-check-circle"></i> Aprobada</span>';
                else if(solicitud.estatus === 'RECHAZADA') tagHtml = '<span class="tag-rechazada"><i class="fas fa-times-circle"></i> Rechazada</span>';
                else if(solicitud.estatus === 'CANCELADA') tagHtml = '<span class="tag-cancelada"><i class="fas fa-ban"></i> Cancelada</span>';
                
                document.getElementById('resp_estatus').innerHTML = tagHtml;
                
                // Formatear Fecha
                let fechaResp = solicitud.fecha_respuesta;
                if(fechaResp) {
                    let d = new Date(fechaResp);
                    document.getElementById('resp_fecha').innerText = d.toLocaleDateString('es-ES') + ' a las ' + d.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'});
                } else {
                    document.getElementById('resp_fecha').innerText = 'No registrada';
                }
                
                document.getElementById('resp_texto').innerText = solicitud.respuesta_admin ? '"' + solicitud.respuesta_admin + '"' : 'El administrador no dejó comentarios.';
            }

            document.getElementById('modalReview').style.display = 'flex';
        }
        
        function cerrarReview() { document.getElementById('modalReview').style.display = 'none'; }
        
        function procesar(accion) {
            document.getElementById('actionType').value = accion;
            document.getElementById('formReview').submit();
        }

        if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href); }
    </script>
</body>
</html>