<?php
require_once 'calificaciones_logic.php';

/**
 * Declaración de variables importadas desde calificaciones_logic.php
 * para evitar falsos positivos de "Undefined variable" en el IDE.
 * 
 * @var array|null $materia_actual
 * @var array $materias_activas
 * @var int|string|null $ins_activa
 * @var array $evaluacion
 * @var array $calificaciones_bd
 * @var float|int $puntaje_total_acumulado
 * @var float|int $puntos_totales_posibles
 * @var float|int $porcentaje_total
 * @var array $historial_solicitudes
 * @var array|null $solicitud_pendiente
 * @var bool $hay_rechazada_reciente
 * @var bool $bajas_habilitadas
 * @var string $mensaje
 * @var string $tipo_mensaje
 */
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificaciones | E-Pale</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content" style="max-width: 800px;"> 

        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">           
            <?php if($materia_actual): ?>
                
                <?php if($materia_actual['grupo_estado'] == 'CERRADO'): ?>
                    <div>
                        <a href="perfil" class="btn-cancel" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-arrow-left"></i> Volver al Kárdex
                        </a>
                    </div>
                    <div>
                        <span style="background: rgba(108,117,125,0.1); color: #6c757d; border: 1px solid rgba(108,117,125,0.2); padding: 6px 12px; border-radius: 12px; font-size: 0.85rem; font-weight: bold; display: flex; align-items: center; gap: 6px;">
                            <i class="fas fa-archive"></i> Clase Finalizada
                        </span>
                    </div>

                <?php else: ?>
                    <div style="display: flex; flex-direction: column; width: max-content; gap: 8px;">
                        <?php if($solicitud_pendiente): ?>
                            <button class="btn-pendiente" onclick="abrirModal('modalRetirarBaja')">
                                <i class="fas fa-clock"></i> Solicitud Pendiente
                            </button>
                        <?php else: ?>
                            <?php if ($bajas_habilitadas): ?>
                                <button class="btn-baja" onclick="abrirModal('modalSolicitarBaja')">
                                    <i class="fas fa-sign-out-alt"></i> Solicitar baja
                                </button>
                            <?php else: ?>
                                <button class="btn-baja" style="opacity: 0.5; pointer-events: none;" disabled title="El periodo para solicitar la baja ha terminado">
                                    <i class="fas fa-sign-out-alt"></i> Solicitar baja
                                </button>
                            <?php endif; ?>
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

            <?php endif; ?>
        </div>

        <?php if($mensaje): ?>
            <div style="padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; background: <?php echo ($tipo_mensaje == 'success') ? 'rgba(40,167,69,0.1)' : 'rgba(220,53,69,0.1)'; ?>; color: <?php echo ($tipo_mensaje == 'success') ? '#28a745' : '#dc3545'; ?>; border: 1px solid <?php echo ($tipo_mensaje == 'success') ? 'rgba(40,167,69,0.2)' : 'rgba(220,53,69,0.2)'; ?>;">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>
        
        <?php if($materia_actual): ?>
            <div class="page-title-center">
                <h1><i class="fas fa-award"></i> Calificaciones</h1>
                <p style="font-size: 1.1rem; color: var(--text-muted); margin-bottom: 5px;">
                    <?php echo htmlspecialchars($materia_actual['materia'] . ' ' . nivel_a_romano($materia_actual['nivel']) . ' - NRC: ' . $materia_actual['nrc'] . ' - ' . $materia_actual['ciclo']); ?>
                </p>
                <p style="color: var(--text-dark); font-weight: 500; margin-top: 10px;"><i class="fas fa-chalkboard-teacher" style="color:var(--text-muted);"></i> Profesor: <?php echo htmlspecialchars(trim($materia_actual['prof_nombre'] . ' ' . $materia_actual['prof_ap_pat'])); ?></p>

                <?php if($materia_actual['grupo_estado'] == 'ACTIVO' && count($materias_activas) > 0): ?>
                    <div style="margin-top: 20px;">
                        <select class="subject-selector" style="font-size: 1rem; padding: 10px 20px; text-align: center; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border: 1px solid #ddd;" onchange="window.location.href='calificaciones?ins='+this.value">
                            <?php foreach($materias_activas as $m): ?>
                                <option value="<?php echo $m['inscripcion_id']; ?>" <?php echo ($m['inscripcion_id'] == $ins_activa) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($m['materia'] . ' ' . nivel_a_romano($m['nivel'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <?php if(empty($evaluacion)): ?>
                <div style="text-align:center; padding:50px; background:var(--white); border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.05); border: 1px solid var(--text-muted);"><i class="fas fa-tools" style="font-size: 3rem; color: var(--text-muted); opacity:0.5; margin-bottom: 15px;"></i><h3 style="color:var(--text-muted);">Criterios no disponibles</h3></div>
            <?php else: ?>
                <div class="total-score-banner" style="<?php if($materia_actual['grupo_estado'] == 'CERRADO') echo 'background: #6c757d;'; ?>">
                    <h3>Calificación Total Obtenida</h3>
                    <span class="big-score"><?php echo format_score($puntaje_total_acumulado); ?></span>
                    <p>de <?php echo $puntos_totales_posibles; ?> puntos posibles</p>
                    <div class="total-progress-bg"><div class="total-progress-fill" style="width: <?php echo $porcentaje_total; ?>%;"></div></div>
                </div>

                <?php foreach($evaluacion as $categoria => $datos_cat): ?>
                    <div class="grade-category-card" style="border-top-color: <?php echo $datos_cat['color']; ?>;">
                        <h4><i class="fas <?php echo $datos_cat['icono']; ?>"></i> <?php echo htmlspecialchars($categoria); ?></h4>
                        <?php foreach($datos_cat['items'] as $codigo => $item): 
                            $esta_registrada = array_key_exists($codigo, $calificaciones_bd);
                            $puntaje = $esta_registrada ? $calificaciones_bd[$codigo] : null;
                            $max = $item['max'];
                            if ($puntaje === null) { 
                                $html_score = '<span class="badge-pending" style="color:var(--text-muted); font-style:italic;">Sin registro</span>'; 
                                $html_progress = ''; 
                            } else { 
                                $porcentaje = ($puntaje / $max) * 100; 
                                // CORRECCIÓN REGLA MÍNIMA 80 PTS
                                $color = ($porcentaje >= 80) ? '#28a745' : '#dc3545'; 
                                $html_score = '<span class="score-text" style="color:var(--text-dark);">' . format_score($puntaje) . ' / ' . $max . '</span>'; 
                                $html_progress = '<div class="progress-mini"><div class="progress-bar" style="width: ' . $porcentaje . '%; background-color:' . $color . ';"></div></div>'; 
                            }
                        ?>
                            <div class="detailed-grade-item">
                                <div class="dg-info">
                                    <strong><?php echo htmlspecialchars($item['nombre']); ?></strong>
                                    <span><?php echo ($puntaje !== null) ? 'Evaluado' : '---'; ?></span>
                                </div>
                                <div class="dg-score">
                                    <?php echo $html_score . $html_progress; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if($materia_actual['grupo_estado'] == 'ACTIVO'): ?>
                <div id="modalSolicitarBaja" class="modal-overlay">
                    <div class="modal-content">
                        <div class="modal-header"><h3 style="margin:0; color:#dc3545;"><i class="fas fa-exclamation-triangle"></i> Solicitar Baja</h3><button class="close-btn" onclick="cerrarModal('modalSolicitarBaja')">&times;</button></div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="solicitar_baja">
                            <input type="hidden" name="inscripcion_id" value="<?php echo $ins_activa; ?>">
                            
                            <div class="modal-body">
                                <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom:15px;">Estás solicitando la baja de la materia <strong><?php echo htmlspecialchars($materia_actual['materia']); ?></strong>.</p>
                                <div class="form-group">
                                    <label>Motivo Principal</label>
                                    <select name="motivo" required>
                                        <option value="">Selecciona un motivo...</option>
                                        <option value="Choque de horario">Choque de horario</option>
                                        <option value="Problemas laborales/personales">Problemas laborales o personales</option>
                                        <option value="Cambio de carrera/institución">Cambio de carrera/institución</option>
                                        <option value="Inscripción por error">Inscripción por error</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Descripción Breve</label>
                                    <textarea name="descripcion" id="descBajaInput" rows="4" maxlength="250" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; box-sizing:border-box; resize:none;" placeholder="Explica tu situación (Máx. 250 caracteres)..."></textarea>
                                    <div style="text-align: right; font-size: 0.8rem; color: var(--text-muted); margin-top: 5px; font-weight: bold;">
                                        <span id="charCount">0</span> / 250
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-cancel" onclick="cerrarModal('modalSolicitarBaja')">Cancelar</button>
                                <button type="submit" style="padding:10px 20px; background:#dc3545; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">Enviar Solicitud</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if($solicitud_pendiente): ?>
                <div id="modalRetirarBaja" class="modal-overlay">
                    <div class="modal-content">
                        <div class="modal-header"><h3 style="margin:0; color:#856404;"><i class="fas fa-clock"></i> Solicitud en Proceso</h3><button class="close-btn" onclick="cerrarModal('modalRetirarBaja')">&times;</button></div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="cancelar_baja">
                            <input type="hidden" name="solicitud_id" value="<?php echo $solicitud_pendiente['solicitud_id']; ?>">
                            
                            <div class="modal-body">
                                <p style="margin-top:0; color:var(--text-dark);">Tu solicitud fue enviada el <strong><?php echo date('d/m/Y', strtotime($solicitud_pendiente['fecha_solicitud'])); ?></strong>.</p>
                                <div style="background:rgba(255,193,7,0.1); padding:15px; border-radius:6px; border-left:4px solid #ffc107; font-size:0.9rem; color:var(--text-dark);">
                                    <strong>Motivo:</strong> <?php echo htmlspecialchars($solicitud_pendiente['motivo']); ?><br>
                                    <?php if($solicitud_pendiente['descripcion']) echo "<strong>Descripción:</strong> " . htmlspecialchars($solicitud_pendiente['descripcion']); ?>
                                </div>
                                <p style="font-size:0.85rem; color:var(--text-muted); margin-top:15px;">Si cambiaste de opinión, puedes retirar tu solicitud ahora mismo.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn-cancel" onclick="cerrarModal('modalRetirarBaja')">Mantener Solicitud</button>
                                <button type="submit" style="padding:10px 20px; background:#dc3545; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold; transition:0.2s;" onmouseover="this.style.background='#c82333'" onmouseout="this.style.background='#dc3545'">
                                    <i class="fas fa-trash-alt"></i> Retirar mi solicitud
                                </button>
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
                            <button class="close-btn" onclick="cerrarModal('modalHistorial')">&times;</button>
                        </div>
                        <div class="modal-body">
                            <?php 
                                $nombre_materia_completo = htmlspecialchars($materia_actual['materia'] . ' ' . nivel_a_romano((int)($materia_actual['nivel'] ?? 1)));
                            ?>
                            <p style="font-size:0.9rem; color:var(--text-muted); margin-top:0;">Historial de peticiones de baja para <strong><?php echo $nombre_materia_completo; ?></strong>.</p>
                            
                            <?php foreach($historial_solicitudes as $h): 
                                $estatus_raw = strtoupper($h['estatus']);
                                $class_card = strtolower($estatus_raw);
                                $icono_estatus = 'fa-clock';
                                $neon_badge_class = 'neon-badge-pendiente';
                                
                                switch($estatus_raw) {
                                    case 'APROBADA': 
                                        $icono_estatus = 'fa-check-circle'; 
                                        $neon_badge_class = 'neon-badge-aprobada'; 
                                        break;
                                    case 'RECHAZADA': 
                                        $icono_estatus = 'fa-times-circle'; 
                                        $neon_badge_class = 'neon-badge-rechazada'; 
                                        break;
                                    case 'CANCELADA': 
                                        $icono_estatus = 'fa-ban'; 
                                        $neon_badge_class = 'neon-badge-cancelada'; 
                                        break;
                                    case 'PENDIENTE': 
                                    default:
                                        $icono_estatus = 'fa-clock'; 
                                        $neon_badge_class = 'neon-badge-pendiente'; 
                                        break;
                                }
                            ?>
                                <div class="history-card <?php echo $class_card; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; margin-bottom: 8px;">
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                                <span class="notif-sub-badge neon-sub-tramite">
                                                    <i class="fas fa-file-signature"></i> Solicitud de Baja
                                                </span>
                                            </div>
                                            <div style="font-weight: 700; color: var(--text-dark); font-size: 0.98rem;">
                                                <?php echo htmlspecialchars($h['motivo']); ?>
                                            </div>
                                            <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                                <?php echo $nombre_materia_completo; ?>
                                            </div>
                                        </div>
                                        <span class="neon-badge <?php echo $neon_badge_class; ?>">
                                            <i class="fas <?php echo $icono_estatus; ?>"></i> <?php echo $estatus_raw; ?>
                                        </span>
                                    </div>

                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
                                        <i class="far fa-calendar-alt"></i> Solicitada el <?php echo date('d/m/Y', strtotime($h['fecha_solicitud'])); ?>
                                    </div>

                                    <?php if(!empty($h['descripcion'])): ?>
                                        <div style="font-size: 0.86rem; color: var(--text-dark); line-height: 1.45; background: rgba(128, 128, 128, 0.05); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(128, 128, 128, 0.1); margin-bottom: 10px;">
                                            <strong style="color: var(--text-muted); font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.4px; display: block; margin-bottom: 2px;">Detalle / Justificación:</strong>
                                            <?php echo htmlspecialchars($h['descripcion']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if($estatus_raw !== 'PENDIENTE' && $estatus_raw !== 'CANCELADA'): ?>
                                        <div class="history-resp-callout <?php echo $estatus_raw === 'APROBADA' ? 'history-resp-aprobada' : 'history-resp-rechazada'; ?>">
                                            <div class="history-resp-header">
                                                <i class="fas fa-reply"></i> Respuesta de Administración • <?php echo date('d/m/Y', strtotime($h['fecha_respuesta'])); ?>
                                            </div>
                                            <div class="history-resp-text">
                                                <?php echo htmlspecialchars($h['respuesta_admin']) ?: 'Sin comentarios adicionales.'; ?>
                                            </div>
                                        </div>
                                    <?php elseif($estatus_raw === 'CANCELADA'): ?>
                                        <div class="history-resp-callout history-resp-cancelada">
                                            <div class="history-resp-header">
                                                <i class="fas fa-ban"></i> Resolución del Estudiante • <?php echo date('d/m/Y', strtotime($h['fecha_respuesta'])); ?>
                                            </div>
                                            <div class="history-resp-text">
                                                El estudiante canceló la solicitud.
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align:center; padding:50px; color:var(--text-muted);">
                <i class="fas fa-search" style="font-size: 3rem; color: var(--text-muted); opacity: 0.5; margin-bottom: 15px; display: block;"></i>
                <h2 style="color:var(--text-dark);">Información no encontrada</h2>
                <p>No estás inscrito en esta materia o no existe.</p>
                <a href="perfil" class="btn-cancel" style="text-decoration:none; margin-top:15px; display:inline-block;"><i class="fas fa-arrow-left"></i> Volver al Kárdex</a>
            </div>
        <?php endif; ?>
    </main>

    <?php include '../main_footer.php'; ?>

    <script>
        function abrirModal(id) { document.getElementById(id).style.display = 'flex'; }
        function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }
        
        const descInput = document.getElementById('descBajaInput');
        const charCount = document.getElementById('charCount');
        if (descInput && charCount) {
            descInput.addEventListener('input', function() {
                charCount.textContent = this.value.length;
                if(this.value.length >= 250) { charCount.style.color = '#dc3545'; } else { charCount.style.color = 'var(--text-muted)'; }
            });
        }
        if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href); }
    </script>
</body>
</html>