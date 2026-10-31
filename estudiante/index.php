<?php
require_once 'index_logic.php';
/**
 * Declaración de variables importadas desde index_logic.php
 * para evitar falsos positivos de "Undefined variable" en el IDE.
 * 
 * @var string $nombre_completo
 * @var string $primer_nombre
 * @var string $saludo_tiempo
 * @var string $icono_saludo
 * @var string $color_icono_saludo
 * @var array $materias_inscritas
 * @var string $fecha_texto_es
 * @var array $clases_hoy
 * @var array $avisos_prof_todas
 * @var array $avisos_prof_dashboard
 * @var array $avisos_dinamicos
 * @var array $avisos_admin
 * @var array $todos_avisos_generales
 * @var array $asistencias_resumen
 */
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio | E-Pale</title>
    <link rel="icon" type="image/png" href="../img/isotipo-pale.png">
    <link rel="apple-touch-icon" href="../img/isotipo-pale.png">
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content">

        <div class="welcome-banner">
            <h2><i class="fas <?php echo $icono_saludo; ?>" style="color: <?php echo $color_icono_saludo; ?>; margin-right: 6px;"></i> <?php echo $saludo_tiempo; ?>!</h2>
            <h1><?php echo htmlspecialchars($nombre_completo); ?></h1>
        </div>

        <div class="dashboard-grid">

            <!-- TARJETA: PROGRESO -->
            <div class="card dashboard-card-top">
                <h3 style="display:flex; justify-content:space-between; align-items:center; flex-shrink: 0;">
                    <span><i class="fas fa-chart-pie"></i> Progreso General</span>

                    <?php if (count($materias_inscritas) > 0): ?>
                        <select class="subject-selector" onchange="cambiarMateriaDash(this.value)">
                            <?php foreach ($materias_inscritas as $index => $mat): ?>
                                <option value="eval-<?php echo $mat['inscripcion_id']; ?>">
                                    <?php echo htmlspecialchars($mat['nombre'] . ' ' . nivel_a_romano($mat['nivel'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <span style="font-size:0.8rem; color:var(--text-muted);">Sin materias activas</span>
                    <?php endif; ?>
                </h3>

                <div class="card-scrollable-body" style="display:flex; flex-direction:column; justify-content:center;">
                    <?php
                    if (count($materias_inscritas) == 0): ?>
                        <div style="text-align:center; padding:40px 20px; color:var(--text-muted); margin: auto;">
                            <i class="fas fa-bed" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 15px; display: block; opacity: 0.5;"></i>
                            No estás inscrito en ninguna materia actualmente.
                        </div>
                    <?php endif; ?>

                    <?php foreach ($materias_inscritas as $index => $mat):
                        $insc_id = $mat['inscripcion_id'];
                        $materia_id = $mat['materia_id'];
                        $display = ($index === 0) ? 'block' : 'none';

                        $stmt_max = $pdo->prepare("SELECT SUM(puntos_maximos) FROM criterios_evaluacion WHERE materia_id = ?");
                        $stmt_max->execute([$materia_id]);
                        $max_puntos = $stmt_max->fetchColumn() ?: 0;

                        $stmt_cal = $pdo->prepare("SELECT puntaje FROM calificaciones WHERE inscripcion_id = ?");
                        $stmt_cal->execute([$insc_id]);
                        $suma_puntos = 0;
                        while ($row = $stmt_cal->fetch(PDO::FETCH_ASSOC)) {
                            if ($row['puntaje'] !== null) $suma_puntos += floatval($row['puntaje']);
                        }

                        $porcentaje = ($max_puntos > 0) ? ($suma_puntos / $max_puntos) * 100 : 0;
                        if ($porcentaje > 100) $porcentaje = 100;

                        if ($porcentaje < 60) {
                            $color = '#dc3545';
                            $mensaje = 'En riesgo';
                            $bg_msg = '#f8d7da';
                            $col_msg = '#721c24';
                        } elseif ($porcentaje < 80) {
                            $color = '#ffc107';
                            $mensaje = 'Regular';
                            $bg_msg = '#fff3cd';
                            $col_msg = '#856404';
                        } elseif ($porcentaje < 95) {
                            $color = '#28a745';
                            $mensaje = 'Buen desempeño';
                            $bg_msg = '#d4edda';
                            $col_msg = '#155724';
                        } else {
                            $color = 'var(--udg-blue)';
                            $mensaje = '¡Excelente!';
                            $bg_msg = '#cce5ff';
                            $col_msg = '#004085';
                        }
                    ?> <div id="eval-<?php echo $insc_id; ?>" class="eval-container" style="display: <?php echo $display; ?>; margin: auto 0;">
                            <div class="chart-wrapper">

                                <?php if ($max_puntos == 0): ?>
                                    <div style="padding:30px 0; color:var(--text-muted); text-align:center;">
                                        <i class="fas fa-hourglass-half" style="font-size:2rem; margin-bottom:10px; opacity: 0.5;"></i><br>
                                        Criterios sin configurar
                                    </div>
                                <?php else: ?>
                                    <svg viewBox="0 0 36 36" class="circular-chart">
                                        <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                        <path class="circle"
                                            stroke-dasharray="<?php echo round($porcentaje); ?>, 100"
                                            style="stroke: <?php echo $color; ?>;"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                                        <text x="18" y="20.35" class="percentage"><?php echo round($porcentaje); ?>%</text>
                                    </svg>

                                    <div class="chart-subtitle">
                                        <?php echo format_score($suma_puntos); ?> / <?php echo $max_puntos; ?> puntos
                                    </div>
                                    <div class="chart-status" style="background-color: <?php echo $bg_msg; ?>; color: <?php echo $col_msg; ?>;">
                                        <?php echo $mensaje; ?>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Footer unificado y alineado con los otros widgets -->
                <div class="card-footer-action">
                    <button id="btnVerDesglose" class="btn-dash-action" onclick="irADesgloseActual()">
                        <i class="fas fa-chart-line"></i> Ver Desglose
                    </button>
                </div>
            </div>

            <!-- TARJETA: HORARIOS DE HOY -->
            <div class="card dashboard-card-top card-hoy-widget">
                <div style="flex-shrink: 0;">
                    <h3 style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
                        <span><i class="far fa-clock" style="color: #06b6d4;"></i> Clases de Hoy</span>
                        <?php if (count($clases_hoy) > 0): ?>
                            <span class="dash-badge-pill"><?php echo count($clases_hoy); ?> sesión(es)</span>
                        <?php endif; ?>
                    </h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0; margin-bottom: 15px;">
                        <i class="far fa-calendar-alt"></i> <?php echo $fecha_texto_es; ?>
                    </p>
                </div>

                <div class="card-scrollable-body" style="padding-right: 4px;">
                    <?php if (count($clases_hoy) > 0):
                        $primer_eval_id = !empty($materias_inscritas[0]['inscripcion_id']) ? ('eval-' . $materias_inscritas[0]['inscripcion_id']) : '';
                    ?>
                        <div class="hoy-classes-list">
                            <?php foreach ($clases_hoy as $h):
                                $es_virtual = ($h['modalidad'] === 'VIRTUAL');
                                $mod_color = $es_virtual ? '#06b6d4' : '#10b981';
                                $mod_icon = $es_virtual ? 'fa-laptop-house' : 'fa-building';
                                $eval_id_h = !empty($h['inscripcion_id']) ? ('eval-' . $h['inscripcion_id']) : '';
                                $is_active_h = ($eval_id_h !== '' && $eval_id_h === $primer_eval_id) ? ' hoy-class-active' : '';
                            ?>
                                <div class="hoy-class-card<?php echo $is_active_h; ?>"
                                    <?php if ($eval_id_h): ?>
                                    data-eval-id="<?php echo $eval_id_h; ?>"
                                    onclick="cambiarMateriaDash('<?php echo $eval_id_h; ?>')"
                                    title="Clic para ver el progreso de esta clase"
                                    role="button"
                                    tabindex="0"
                                    onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault(); cambiarMateriaDash('<?php echo $eval_id_h; ?>');}"
                                    <?php endif; ?>>
                                    <div class="hoy-class-strip" style="background: <?php echo $mod_color; ?>;"></div>
                                    <div class="hoy-class-content">
                                        <div class="hoy-class-main">
                                            <div class="hoy-class-title">
                                                <?php echo htmlspecialchars($h['nombre'] . ' ' . nivel_a_romano((int)$h['nivel'])); ?>
                                            </div>
                                            <div class="hoy-class-meta">
                                                <span class="hoy-aula-tag">
                                                    <i class="fas <?php echo $mod_icon; ?>" style="color: <?php echo $mod_color; ?>;"></i>
                                                    <?php echo htmlspecialchars($h['aula']); ?>
                                                </span>
                                                <span class="hoy-mod-pill" style="color: <?php echo $mod_color; ?>; background: <?php echo $es_virtual ? 'rgba(6, 182, 212, 0.12)' : 'rgba(16, 185, 129, 0.12)'; ?>;">
                                                    <?php echo htmlspecialchars($h['modalidad']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="hoy-time-badge">
                                            <i class="far fa-clock" style="font-size: 0.75rem; opacity: 0.7;"></i>
                                            <span><?php echo date('H:i', strtotime($h['hora_inicio'])) . ' - ' . date('H:i', strtotime($h['hora_fin'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding: 40px 20px; color:var(--text-muted); margin: auto;">
                            <i class="fas fa-mug-hot" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                            ¡Día libre! No tienes clases programadas para hoy.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer unificado y alineado con los otros widgets -->
                <div class="card-footer-action">
                    <button class="btn-dash-action" onclick="window.location.href='horario'">
                        <i class="far fa-calendar-check"></i> Ver Horario Completo
                    </button>
                </div>
            </div>

            <!-- TARJETA: ASISTENCIA DE CLASES ACTIVAS -->
            <div class="card dashboard-card-top card-asistencias-widget">
                <div style="flex-shrink: 0;">
                    <h3 style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 5px;">
                        <span><i class="fas fa-user-check" style="color: #06b6d4;"></i> Asistencia</span>
                        <?php if (count($asistencias_resumen) > 0): ?>
                            <span class="dash-badge-pill"><?php echo count($asistencias_resumen); ?> materia(s)</span>
                        <?php endif; ?>
                    </h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0; margin-bottom: 15px;">
                        Porcentaje de asistencia en tus clases activas
                    </p>
                </div>

                <div class="card-scrollable-body" style="padding-right: 4px;">
                    <?php if (count($asistencias_resumen) > 0): ?>
                        <div class="asistencias-list">
                            <?php foreach ($asistencias_resumen as $idx => $asist):
                                $eval_id = 'eval-' . $asist['inscripcion_id'];
                                $is_first = ($idx === 0) ? ' asist-item-active' : '';
                            ?>
                                <div class="asist-item<?php echo $is_first; ?>"
                                    data-eval-id="<?php echo $eval_id; ?>"
                                    onclick="cambiarMateriaDash('<?php echo $eval_id; ?>')"
                                    title="Clic para ver el progreso general de esta materia"
                                    role="button"
                                    tabindex="0"
                                    onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault(); cambiarMateriaDash('<?php echo $eval_id; ?>');}">
                                    <div class="asist-header-row">
                                        <div class="asist-title-col">
                                            <span class="asist-materia-nombre">
                                                <?php echo htmlspecialchars($asist['nombre'] . ' ' . nivel_a_romano((int)$asist['nivel'])); ?>
                                            </span>
                                            
                                        </div>
                                        <div class="asist-meta-col">
                                            <span class="asist-status-pill asist-pill-<?php echo $asist['categoria']; ?>">
                                                <?php echo $asist['estado_texto']; ?>
                                            </span>
                                            <span class="asist-percentage-val" style="color: <?php echo $asist['color']; ?>;">
                                                <?php echo $asist['porcentaje']; ?>%
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Barra de Progreso Horizontal -->
                                    <div class="asist-progress-track" title="<?php echo $asist['asistidas']; ?> de <?php echo $asist['total']; ?> sesiones">
                                        <div class="asist-progress-fill asist-fill-<?php echo $asist['categoria']; ?>"
                                            style="width: <?php echo $asist['porcentaje']; ?>%; background-color: <?php echo $asist['color']; ?>;">
                                        </div>
                                    </div>

                                    <div class="asist-footer-info">
                                        <?php if ($asist['total'] > 0): ?>
                                            <span><i class="far fa-check-circle" style="color:#10b981;"></i> <?php echo $asist['asistidas']; ?> asistencias</span>
                                            <?php if ($asist['retardos'] > 0): ?>
                                                <span><i class="far fa-clock" style="color:#f59e0b;"></i> <?php echo $asist['retardos']; ?> retardos</span>
                                            <?php endif; ?>
                                            <span><i class="far fa-times-circle" style="color:#ef4444;"></i> <?php echo $asist['faltas']; ?> faltas</span>
                                        <?php else: ?>
                                            <span style="font-style: italic; color: var(--text-muted);"><i class="fas fa-info-circle"></i> Sin sesiones pasadas de lista</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding: 40px 0; color:var(--text-muted); margin: auto;">
                            <i class="fas fa-calendar-times" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                            No tienes materias inscritas actualmente.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer de la tarjeta con leyenda de umbrales estilizada y alineada con los botones -->
                <div class="card-footer-action card-footer-legend">
                    <div class="asist-legend-bar">
                        <span class="asist-legend-item"><span class="asist-legend-dot" style="background:#10b981;"></span> ≥80% Regular</span>
                        <span class="asist-legend-item"><span class="asist-legend-dot" style="background:#f59e0b;"></span> 70-79% Riesgo</span>
                        <span class="asist-legend-item"><span class="asist-legend-dot" style="background:#ef4444;"></span> &lt;70% Reprobado</span>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <?php require_once 'modales_index.php'; ?>
    <?php include '../main_footer.php'; ?>

    <script src="../js/index_estudiante.js?v=<?php echo time(); ?>"></script>

    <script>
        // Función para cambiar de pestaña en el Centro de Avisos Unificado
        function cambiarTabAvisos(tab) {
            const btnProf = document.getElementById('btnTabProfesores');
            const btnGen = document.getElementById('btnTabGenerales');
            const contProf = document.getElementById('tabContenidoProfesores');
            const contGen = document.getElementById('tabContenidoGenerales');
            const btnHist = document.getElementById('btnVerTodasActividades');

            if (tab === 'profesores') {
                btnProf.classList.add('active');
                btnGen.classList.remove('active');
                contProf.style.display = 'block';
                contGen.style.display = 'none';
                if (btnHist) btnHist.style.display = 'inline-block';
            } else {
                btnGen.classList.add('active');
                btnProf.classList.remove('active');
                contGen.style.display = 'block';
                contProf.style.display = 'none';
                if (btnHist) btnHist.style.display = 'none';
            }
        }

        // Funciones para el Modal de Historial Completo
        function abrirModalTodasActividades() {
            document.getElementById('modalTodasActividades').style.display = 'flex';
        }

        function cerrarModalTodasActividades() {
            document.getElementById('modalTodasActividades').style.display = 'none';
        }

        // Funciones para el Modal de Detalle de Actividad Individual
        function abrirModalDetalle(elemento) {
            try {
                // Leemos los datos inyectados en la tarjeta
                const info = JSON.parse(elemento.getAttribute('data-info'));

                // Rellenamos el modal emergente
                document.getElementById('modMateria').innerHTML = info.materia;
                document.getElementById('modBadge').innerHTML = info.estatus_html;
                document.getElementById('modTitulo').innerHTML = info.titulo;
                document.getElementById('modDesc').innerHTML = info.descripcion;
                document.getElementById('modProf').innerHTML = info.profesor;
                document.getElementById('modInicio').innerHTML = info.fecha_inicio;
                document.getElementById('modFin').innerHTML = info.fecha_fin;

                // Mostramos el modal
                document.getElementById('modalDetalleActividad').style.display = 'flex';
            } catch (e) {
                console.error("Error al procesar la información de la actividad", e);
            }
        }

        function cerrarModalDetalle() {
            document.getElementById('modalDetalleActividad').style.display = 'none';
        }

        // Funciones para el Modal de Aviso Administrativo
        function abrirModalAvisoAdmin(elemento) {
            try {
                const info = JSON.parse(elemento.getAttribute('data-info'));

                const tag = document.getElementById('modAdminTag');
                tag.innerHTML = info.etiqueta;
                tag.style.background = info.tag_color;
                tag.style.color = info.tag_text;
                tag.style.border = '1px solid ' + info.tag_text;

                document.getElementById('modAdminTitulo').innerHTML = info.titulo;
                document.getElementById('modAdminDesc').innerHTML = info.cuerpo;
                document.getElementById('modAdminFecha').innerHTML = info.fecha;

                const autorElem = document.getElementById('modAdminAutor');
                if (autorElem) {
                    autorElem.innerHTML = info.profesor ? ('Prof. ' + info.profesor) : 'Administración E-Pale';
                }

                const expRow = document.getElementById('modAdminExpiraRow');
                const expSpan = document.getElementById('modAdminExpira');
                if (expRow && expSpan) {
                    if (info.fecha_expiracion) {
                        expSpan.innerHTML = info.fecha_expiracion;
                        expRow.style.display = 'inline';
                    } else {
                        expRow.style.display = 'none';
                    }
                }

                const imgContainer = document.getElementById('modAdminImgContainer');
                const img = document.getElementById('modAdminImg');

                if (info.imagen) {
                    img.src = info.imagen;
                    imgContainer.style.display = 'block';
                } else {
                    imgContainer.style.display = 'none';
                    img.src = '';
                }

                document.getElementById('modalDetalleAvisoAdmin').style.display = 'flex';
            } catch (e) {
                console.error("Error al procesar el aviso administrativo", e);
            }
        }

        function cerrarModalAvisoAdmin() {
            document.getElementById('modalDetalleAvisoAdmin').style.display = 'none';
        }

        function ampliarImagenAviso(src) {
            if (!src) return;
            const lightbox = document.getElementById('lightboxAvisoAdmin');
            const img = document.getElementById('lightboxImgAviso');
            img.src = src;
            lightbox.style.display = 'flex';
        }

        function cerrarLightboxAviso() {
            const lightbox = document.getElementById('lightboxAvisoAdmin');
            if (lightbox) lightbox.style.display = 'none';
        }

        // Tecla Escape para cerrar modales y lightbox
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarLightboxAviso();
                cerrarModalAvisoAdmin();
                cerrarModalTodasActividades();
                cerrarModalDetalle();
            }
        });

        // Cerrar modales si se hace clic afuera del cuadro blanco
        window.onclick = function(e) {
            if (e.target == document.getElementById('modalTodasActividades')) cerrarModalTodasActividades();
            if (e.target == document.getElementById('modalDetalleActividad')) cerrarModalDetalle();
            if (e.target == document.getElementById('modalDetalleAvisoAdmin')) cerrarModalAvisoAdmin();
            if (e.target == document.getElementById('lightboxAvisoAdmin')) cerrarLightboxAviso();
        }
    </script>

</body>

</html>