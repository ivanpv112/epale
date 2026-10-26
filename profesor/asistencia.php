<?php
require_once 'asistencia_logic.php';

/**
 * Declaración de variables importadas desde asistencia_logic.php
 * para evitar falsos positivos de "Undefined variable" en el IDE.
 * 
 * @var string $clave
 * @var array $grupo
 * @var array $alumnos
 * @var array $fechas_clase
 * @var int $total_sesiones
 * @var array $asistencias_log
 * @var string $hoy
 * @var bool $asistencia_hoy_completada
 * @var bool $es_ciclo_pasado
 * @var bool $modo_solo_lectura
 * @var array $detalles_grupo
 */
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Asistencia | e-PALE</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profesor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        <div style="margin-bottom: 15px;">
            <a href="detalle_grupo?clave=<?php echo urlencode($clave) . ($modo_solo_lectura ? '&pasado=1' : ''); ?>" class="btn-volver" style="margin-bottom: 0;">
                <i class="fas fa-arrow-left"></i> Volver a Detalles del Grupo
            </a>
        </div>

        <?php if ($es_ciclo_pasado): ?>
            <div class="alert-closed" style="background: rgba(100, 116, 139, 0.12); border-color: rgba(100, 116, 139, 0.35); color: #475569; margin-bottom: 20px;">
                <i class="fas fa-history" style="font-size: 1.8rem; color: #64748b;"></i>
                <div>
                    <strong>Historial de Asistencias (Solo Lectura)</strong><br>
                    <span style="font-size: 0.9rem;">Este grupo pertenece a un ciclo escolar finalizado. La lista de asistencia se encuentra en modo de solo lectura para consulta histórica.</span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Fila de Cabecera: Tarjeta de Grupo a la Izquierda y Botón de Acción a la Derecha -->
        <div class="dg-top-row-flex" style="margin-bottom: 25px;">
            <!-- Tarjeta Acordeón: Resumen Esencial y Detalles Desplegables -->
            <div class="content-card dg-accordion-card" id="cardDetalleGrupo" style="padding: 0; overflow: hidden; border-top: 3px solid var(--udg-blue);">
                <!-- Cabecera Esencial del Acordeón -->
                <div class="dg-accordion-header" onclick="toggleDetallesGrupo()">
                    <!-- Bloque Izquierdo: Materia, Código, Nivel y Ciclo -->
                    <div class="dg-accordion-left">
                        <span class="g-badge" title="Código de Materia">
                            <?php echo htmlspecialchars($grupo['clave_materia'] ?? 'PROFE'); ?>
                        </span>
                        <h1 class="dg-materia-title">
                            <?php echo htmlspecialchars($grupo['materia']); ?>
                        </h1>
                        <span class="level-badge">
                            Nivel <?php echo htmlspecialchars(nivel_a_romano($grupo['nivel'])); ?>
                        </span>
                        <span class="dg-cycle-badge">
                            <i class="far fa-calendar-alt"></i> Ciclo <?php echo htmlspecialchars($grupo['ciclo']); ?>
                        </span>
                    </div>

                    <!-- Bloque Derecho: Botón Toggle Desplegable -->
                    <div class="dg-accordion-right" onclick="event.stopPropagation();">
                        <span class="dg-toggle-label">Detalles de horarios</span>
                        <button type="button" class="g-toggle-btn" id="btnToggleDetallesGrupo" onclick="event.stopPropagation(); toggleDetallesGrupo();" title="Desplegar detalles y horarios">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                </div>

                <!-- Cuerpo Desplegable del Acordeón: Sesiones, Modalidades y Aulas -->
                <div id="drawerDetallesGrupo" class="dg-accordion-drawer" style="display: none;">
                    <div class="dg-drawer-title">
                        <i class="fas fa-info-circle"></i> Sesiones, Horarios y Modalidades del Grupo
                    </div>

                    <div class="g-expanded-sessions-list">
                        <!-- LÍNEA PRESENCIAL -->
                        <div class="g-inline-session session-presencial">
                            <span class="session-modality-badge presencial">
                                <i class="fas fa-building"></i> Presencial
                            </span>
                            <span class="g-session-pill col-nrc">
                                <i class="fas fa-building" style="color: #ffc107;" title="NRC Presencial"></i>
                                <span><?php echo !empty($detalles_grupo['nrc_p']) ? htmlspecialchars($detalles_grupo['nrc_p']) : 'NA'; ?></span>
                            </span>
                            <span class="g-meta-sep">|</span>
                            <span class="g-session-pill col-aula">
                                <i class="fas fa-map-marker-alt" style="color: #ffc107;" title="Aula Presencial"></i>
                                <span><?php echo !empty($detalles_grupo['aula_p']) ? htmlspecialchars($detalles_grupo['aula_p']) : 'NA'; ?></span>
                            </span>
                            <span class="g-meta-sep">|</span>
                            <span class="g-session-pill col-dias">
                                <i class="far fa-calendar-alt" style="color: #ffc107;" title="Días Presenciales"></i>
                                <span><?php echo !empty($detalles_grupo['dias_p']) ? htmlspecialchars(formatear_dias_es($detalles_grupo['dias_p'])) : 'NA'; ?></span>
                            </span>
                            <span class="g-meta-sep">|</span>
                            <span class="g-session-pill col-hora">
                                <i class="far fa-clock" style="color: #ffc107;" title="Horario Presencial"></i>
                                <span>
                                    <?php echo (!empty($detalles_grupo['inicio_p']) && !empty($detalles_grupo['fin_p'])) ? date('H:i', strtotime($detalles_grupo['inicio_p'])) . ' - ' . date('H:i', strtotime($detalles_grupo['fin_p'])) : 'NA'; ?>
                                </span>
                            </span>
                        </div>

                        <!-- LÍNEA VIRTUAL -->
                        <div class="g-inline-session session-virtual">
                            <span class="session-modality-badge virtual">
                                <i class="fas fa-laptop"></i> Virtual
                            </span>
                            <span class="g-session-pill col-nrc">
                                <i class="fas fa-laptop" style="color: #00bcd4;" title="NRC Virtual"></i>
                                <span><?php echo !empty($detalles_grupo['nrc_v']) ? htmlspecialchars($detalles_grupo['nrc_v']) : 'NA'; ?></span>
                            </span>
                            <span class="g-meta-sep">|</span>
                            <span class="g-session-pill col-aula">
                                <i class="fas fa-video" style="color: #00bcd4;" title="Aula Virtual"></i>
                                <span><?php echo !empty($detalles_grupo['aula_v']) ? htmlspecialchars($detalles_grupo['aula_v']) : (!empty($detalles_grupo['nrc_v']) ? 'Zoom' : 'NA'); ?></span>
                            </span>
                            <span class="g-meta-sep">|</span>
                            <span class="g-session-pill col-dias">
                                <i class="far fa-calendar-alt" style="color: #00bcd4;" title="Días Virtuales"></i>
                                <span><?php echo !empty($detalles_grupo['dias_v']) ? htmlspecialchars(formatear_dias_es($detalles_grupo['dias_v'])) : 'NA'; ?></span>
                            </span>
                            <span class="g-meta-sep">|</span>
                            <span class="g-session-pill col-hora">
                                <i class="far fa-clock" style="color: #00bcd4;" title="Horario Virtual"></i>
                                <span>
                                    <?php echo (!empty($detalles_grupo['inicio_v']) && !empty($detalles_grupo['fin_v'])) ? date('H:i', strtotime($detalles_grupo['inicio_v'])) . ' - ' . date('H:i', strtotime($detalles_grupo['fin_v'])) : 'NA'; ?>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de Acción / Estadísticas a la Derecha -->
            <div class="dg-top-actions">
                <?php if (!$modo_solo_lectura): ?>
                    <button id="btnTomarAsist" class="dg-btn-action dg-btn-asistencia" 
                            <?php echo $asistencia_hoy_completada ? 'disabled style="opacity: 0.65; cursor: not-allowed;"' : ''; ?>
                            onclick="iniciarTomaAsistencia()">
                        <i class="fas fa-user-check"></i> <?php echo $asistencia_hoy_completada ? 'Asistencia de Hoy Completada' : 'Pasar Lista de Asistencia'; ?>
                    </button>
                <?php else: ?>
                    <div class="dg-btn-action" style="opacity: 0.8; background: #e2e8f0; color: #475569; border-color: #cbd5e1; cursor: default;">
                        <i class="fas fa-eye"></i> Modo Solo Lectura
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="excel-header-bar" style="margin-bottom: 0;">
            <div class="excel-header-title">
                <i class="fas fa-calendar-check"></i> Registro de Asistencia 
                <?php if ($modo_solo_lectura) echo "(Solo Lectura)"; else echo "En Vivo"; ?>
            </div>
            <div class="excel-save-status">
                <i class="fas fa-users"></i> <?php echo count($alumnos); ?> Alumnos &nbsp;|&nbsp; 
                <i class="fas fa-chalkboard"></i> Clases impartidas: <strong><?php echo $total_sesiones; ?></strong>
            </div>
        </div>

        <div class="excel-table-wrapper-profe table-card-asist" style="border-top-left-radius: 0; border-top-right-radius: 0; border-top: none; margin-bottom: 30px;">
            <table class="table-asist-responsive">
                <thead>
                    <tr>
                        <th class="th-alumno">Estudiante</th>
                        <?php foreach($fechas_clase as $f): ?>
                            <th class="th-fecha"><?php echo date('d/m', strtotime($f)); ?></th>
                        <?php endforeach; ?>
                        <th class="th-total th-total-num" title="Total de Asistencias">
                            <span class="crit-title-desktop">Total Asist.</span>
                            <span class="crit-title-mobile">=</span>
                        </th>
                        <th class="th-total th-porcentaje" title="Porcentaje de Asistencia">
                            <span class="crit-title-desktop">% Asistencia</span>
                            <span class="crit-title-mobile">%</span>
                        </th>
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
                        if ($total_sesiones > 0) {
                            if ($porcentaje_asist < 80) { // Menos de 80% pierde derecho
                                $row_class = 'status-row-fail';
                            } elseif ($porcentaje_asist < 86) { // Menos de 86% está en riesgo
                                $row_class = 'status-row-risk';
                            }
                        }
                    ?>
                    <?php 
                        $apellidos = trim(($a['apellido_paterno'] ?? '') . ' ' . ($a['apellido_materno'] ?? ''));
                        $nombres = trim($a['nombre'] ?? '');
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td class="td-alumno">
                            <img src="<?php echo $a['foto_url']; ?>" class="td-foto">
                            <div>
                                <div class="td-nombre">
                                    <?php echo htmlspecialchars($apellidos); ?><br><?php echo htmlspecialchars($nombres); ?>
                                </div>
                                <div class="td-codigo">Código: <?php echo htmlspecialchars($a['codigo']); ?></div>
                            </div>
                        </td>
                        <?php foreach($fechas_clase as $f): 
                            $st = $asistencias_log[$id_ins][$f] ?? 'DEFAULT';
                            $disabled_select = $modo_solo_lectura ? 'disabled style="cursor: default; opacity: 0.9;"' : '';
                        ?>
                            <td class="td-center">
                                <select class="select-asist sel-<?php echo $st; ?>" <?php echo $disabled_select; ?> onchange="cambiarAsistencia(this, <?php echo $id_ins; ?>, '<?php echo $f; ?>')" title="<?php echo $modo_solo_lectura ? 'Solo lectura' : 'Cambiar estado'; ?>">
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
                        <td class="td-porcentaje">
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
        // VARIABLE GLOBAL DEL CSRF TOKEN GENERADA EN PHP
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

        const modoSoloLectura = <?php echo $modo_solo_lectura ? 'true' : 'false'; ?>;

        function cambiarAsistencia(selectObj, ins_id, fecha) {
            if (modoSoloLectura) {
                return;
            }
            const nuevoEstatus = selectObj.value;
            selectObj.className = 'select-asist sel-' + nuevoEstatus;
            
            // SE INYECTA EL TOKEN EN EL CUERPO DE LA PETICIÓN
            fetch('asistencia_api', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'single', ins_id: ins_id, fecha: fecha, estatus: nuevoEstatus, csrf_token: csrfToken })
            })
            .then(res => res.json())
            .then(res => { 
                if(res.success) { location.reload(); } 
                else { Swal.fire('Error', res.error || 'Hubo un problema al actualizar la asistencia.', 'error'); }
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
            if(indexActual < alumnos.length) { mostrarAlumno(); } else { finalizarTomaAsistencia(); }
        }

        function finalizarTomaAsistencia() {
            document.getElementById('modalAsistencia').style.display = 'none';
            Swal.fire({ title: 'Procesando lista...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
            
            // SE INYECTA EL TOKEN EN LA TOMA DE LISTA COMPLETA
            fetch('asistencia_api', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'batch', data: resultados, csrf_token: csrfToken })
            })
            .then(res => res.json())
            .then(res => {
                if(res.success) { Swal.fire('¡Lista Guardada!', 'La asistencia de hoy ha sido registrada exitosamente.', 'success').then(() => location.reload()); } 
                else { Swal.fire('Error', res.error || 'Hubo un problema al guardar la asistencia.', 'error'); }
            });
        }

        function toggleDetallesGrupo() {
            const drawer = document.getElementById('drawerDetallesGrupo');
            const btn = document.getElementById('btnToggleDetallesGrupo');
            if (!drawer || !btn) return;

            if (drawer.style.display === 'none' || drawer.style.display === '') {
                drawer.style.display = 'block';
                btn.classList.add('rotated');
            } else {
                drawer.style.display = 'none';
                btn.classList.remove('rotated');
            }
        }

        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
    </script>

    <?php include '../main_footer.php'; ?>

</body>
</html>