<?php
require_once 'index_logic.php';
/**
 * Declaración de variables importadas desde index_logic.php
 * para evitar falsos positivos de "Undefined variable" en el IDE.
 * 
 * @var string $nombre_completo
 * @var array $materias_inscritas
 * @var string $fecha_texto_es
 * @var array $clases_hoy
 * @var array $avisos_prof_todas
 * @var array $avisos_prof_dashboard
 * @var array $avisos_dinamicos
 * @var array $avisos_admin
 * @var array $todos_avisos_generales
 */
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio | E-Pale</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content">

        <div class="welcome-banner">
            <h2>Página de inicio</h2>
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
                    ?>

                        <div id="eval-<?php echo $insc_id; ?>" class="eval-container" style="display: <?php echo $display; ?>; margin: auto 0;">
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

                            <div style="text-align: center; margin-top: 15px;">
                                <button onclick="window.location.href='calificaciones?ins=<?php echo $insc_id; ?>'" style="padding: 8px 15px; background: transparent; border: 1px solid var(--udg-blue); color: var(--udg-blue); border-radius: 6px; cursor: pointer; font-weight: bold; transition: 0.2s;">Ver Desglose</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TARJETA: HORARIOS DE HOY -->
            <div class="card dashboard-card-top">
                <div style="flex-shrink: 0;">
                    <h3 style="margin-bottom: 5px;"><i class="far fa-clock"></i> Clases de Hoy</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0; margin-bottom: 15px;">
                        <i class="far fa-calendar-alt"></i> <?php echo $fecha_texto_es; ?>
                    </p>
                </div>

                <div class="card-scrollable-body">
                    <ul class="next-classes-list" style="margin: 0; padding: 0;">
                        <?php if (count($clases_hoy) > 0): ?>
                            <?php foreach ($clases_hoy as $h): ?>
                                <li style="border-left: 4px solid <?php echo ($h['modalidad'] == 'VIRTUAL') ? '#17a2b8' : '#28a745'; ?>; padding-left: 10px;">
                                    <div>
                                        <div style="font-weight: bold; color: var(--text-dark); font-size: 0.95rem;"><?php echo htmlspecialchars($h['nombre'] . ' ' . nivel_a_romano($h['nivel'])); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 3px;">
                                            <i class="fas <?php echo ($h['modalidad'] == 'VIRTUAL') ? 'fa-laptop-house' : 'fa-building'; ?>"></i>
                                            <?php echo htmlspecialchars($h['aula']); ?>
                                        </div>
                                    </div>
                                    <span class="grade-value" style="background: var(--bg-gray); padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; border: 1px solid #eee;">
                                        <?php echo date('H:i', strtotime($h['hora_inicio'])) . ' - ' . date('H:i', strtotime($h['hora_fin'])); ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align:center; padding: 40px 0; color:var(--text-muted); margin: auto;">
                                <i class="fas fa-mug-hot" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                ¡Día libre! No tienes clases programadas para hoy.
                            </div>
                        <?php endif; ?>
                    </ul>
                </div>

                <div style="margin-top: auto; padding-top: 15px; text-align: center; flex-shrink: 0;">
                    <button onclick="window.location.href='horario'" style="width: auto; padding: 10px 20px; background-color: transparent; border: 1px solid var(--text-muted); border-radius: 6px; font-weight:bold; cursor:pointer; color: var(--text-dark); transition: 0.2s;" onmouseover="this.style.background='var(--bg-gray)'" onmouseout="this.style.background='transparent'">Ver Horario Completo</button>
                </div>
            </div>

            <!-- TARJETA: AVISOS Y ASIGNACIONES DEL PROFESOR -->
            <div class="card dashboard-card-top">
                <h3 style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px; flex-shrink: 0;">
                    <span><i class="fas fa-tasks"></i> Anuncios de Clase </span>
                    <?php if (count($avisos_prof_todas) > 0): ?>
                        <button onclick="abrirModalTodasActividades()" style="background: none; border: 1px solid var(--udg-blue); color: var(--udg-blue); padding: 4px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: bold; transition: 0.2s;" onmouseover="this.style.background='var(--udg-blue)'; this.style.color='white';" onmouseout="this.style.background='none'; this.style.color='var(--udg-blue)';">Ver todas (<?php echo count($avisos_prof_todas); ?>)</button>
                    <?php endif; ?>
                </h3>
                <div class="card-scrollable-body" style="font-size: 0.9rem; color: var(--text-muted);">
                    <?php if (count($avisos_prof_dashboard) > 0): ?>
                        <?php foreach ($avisos_prof_dashboard as $a):
                            $badgeClass = '';
                            if ($a['estatus'] === 'PRÓXIMA') $badgeClass = 'background-color: #ffc107; color: #000;';
                            elseif ($a['estatus'] === 'ACTIVA') $badgeClass = 'background-color: #28a745; color: white;';

                            $icono = $a['tipo'] === 'AVISO' ? '📢' : '📝';
                            $borde_izq = $a['tipo'] === 'AVISO' ? '#17a2b8' : '#0056b3';

                            $aviso_prof_data = [
                                'materia' => $a['materia_nombre'],
                                'estatus_html' => '<span style="' . $badgeClass . ' font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; font-weight: bold;">' . $a['estatus'] . '</span>',
                                'titulo' => $icono . ' ' . $a['titulo'],
                                'descripcion' => nl2br(htmlspecialchars($a['descripcion'])),
                                'profesor' => $a['prof_nombre'] . ' ' . $a['prof_ap'],
                                'fecha_inicio' => date('d/m/Y h:i A', strtotime($a['fecha_inicio'])),
                                'fecha_fin' => date('d/m/Y h:i A', strtotime($a['fecha_fin']))
                            ];
                            $json_info = htmlspecialchars(json_encode($aviso_prof_data), ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="aviso-item aviso-clickable texto-seguro" onclick="abrirModalDetalle(this)" data-info="<?php echo $json_info; ?>" style="border-left: 4px solid <?php echo $borde_izq; ?>; padding-left: 10px; margin-bottom: 15px; border-bottom: 1px solid var(--bg-gray); padding-bottom: 10px;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <span class="tag-aviso" style="background: var(--bg-gray); color: var(--text-muted); border: 1px solid var(--text-muted); font-weight:bold; font-size:0.75rem; padding: 3px 8px; border-radius: 12px;"><?php echo htmlspecialchars($a['materia_nombre'] . ' ' . nivel_a_romano($a['materia_nivel'])); ?></span>
                                    <span style="<?php echo $badgeClass; ?> font-size: 0.75rem; padding: 3px 8px; border-radius: 12px; font-weight: bold;"><?php echo $a['estatus']; ?></span>
                                </div>
                                <div style="margin-top: 8px;">
                                    <strong style="color: var(--text-dark); font-size:1.05rem;"><?php echo $icono; ?> <?php echo htmlspecialchars($a['titulo']); ?></strong>
                                </div>
                                <div style="margin-top: 5px; color: var(--text-muted); font-size: 0.9rem;">
                                    <?php echo recortar_texto(htmlspecialchars($a['descripcion']), 60); ?>
                                </div>
                                <div style="margin-top: 8px; font-size: 0.8rem; color: var(--text-muted);">
                                    <i class="far fa-user"></i> Prof. <?php echo htmlspecialchars($a['prof_nombre'] . ' ' . $a['prof_ap']); ?> <br>
                                    <i class="far fa-calendar-alt"></i> Cierre: <strong><?php echo date('d/m/Y h:i A', strtotime($a['fecha_fin'])); ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding: 40px 0; color:var(--text-muted); font-style: italic; margin: auto;">
                            <i class="fas fa-clipboard-check" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                            No hay avisos ni recordatorios activos en tus clases.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TARJETA: AVISOS ADMINISTRATIVOS -->
            <div class="card card-avisos-generales">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 12px; flex-shrink: 0;">
                    <h3 style="margin: 0; padding: 0; border-bottom: none;"><i class="far fa-bell"></i> Avisos Generales</h3>
                    <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                        <?php echo count($todos_avisos_generales); ?> comunicado(s)
                    </span>
                </div>

                <div class="card-scrollable-body" style="max-height: 480px; font-size: 0.9rem;">
                    <?php if (count($todos_avisos_generales) > 0): ?>
                        <div class="avisos-generales-grid">
                            <?php foreach ($todos_avisos_generales as $aviso): ?>
                                <?php if ($aviso['tipo'] === 'SOLICITUD'): ?>
                                    <div class="aviso-card-tile texto-seguro">
                                        <div>
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
                                                <span class="tag-aviso tag-sistema" style="background: #fff3cd; color: #856404; border: 1px solid #ffeeba;">Control Escolar</span>
                                                <?php if ($aviso['estatus'] == 'APROBADA'): ?>
                                                    <span class="tag-aprobada">Aprobada</span>
                                                <?php else: ?>
                                                    <span class="tag-rechazada">Rechazada</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="color: var(--text-dark); margin-bottom: 8px;">
                                                <strong>Solicitud de <?php echo $aviso['tipo_solicitud']; ?>:</strong>
                                                Tu petición para <?php echo $aviso['tipo_solicitud'] === 'BAJA' ? 'abandonar' : 'ingresar a'; ?>
                                                <strong style="color:var(--udg-blue);"><?php echo htmlspecialchars($aviso['nombre'] . ' ' . nivel_a_romano($aviso['nivel'])); ?></strong>
                                                fue <?php echo strtolower($aviso['estatus']); ?>.
                                                <?php if ($aviso['estatus'] !== 'APROBADA'): ?> Revisa el Kárdex.<?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); border-top: 1px dashed rgba(0,0,0,0.08); padding-top: 8px; margin-top: 6px;">
                                            <i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y h:i A', $aviso['timestamp']); ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php
                                    $aviso_admin_data = [
                                        'titulo' => $aviso['titulo'],
                                        'etiqueta' => $aviso['etiqueta'],
                                        'tag_color' => $aviso['tag_color'],
                                        'tag_text' => $aviso['tag_text'],
                                        'cuerpo' => nl2br(htmlspecialchars($aviso['cuerpo'])),
                                        'imagen' => $aviso['imagen'],
                                        'fecha' => date('d/m/Y h:i A', $aviso['timestamp'])
                                    ];
                                    $json_info = htmlspecialchars(json_encode($aviso_admin_data), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <div class="aviso-card-tile aviso-clickable texto-seguro" onclick="abrirModalAvisoAdmin(this)" data-info="<?php echo $json_info; ?>">
                                        <div>
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8px;">
                                                <span class="tag-aviso tag-sistema" style="background: <?php echo $aviso['tag_color']; ?>; color: <?php echo $aviso['tag_text']; ?>; border: 1px solid <?php echo $aviso['tag_text']; ?>;"><?php echo $aviso['etiqueta']; ?></span>
                                                <?php if (!empty($aviso['imagen'])): ?>
                                                    <span style="font-size: 0.75rem; color: var(--udg-blue); font-weight: bold;"><i class="fas fa-image"></i> Cartel</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="margin-bottom: 6px;">
                                                <strong style="color: var(--text-dark); font-size: 0.95rem;"><?php echo htmlspecialchars($aviso['titulo']); ?></strong>
                                            </div>
                                            <div style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 8px;">
                                                <?php echo recortar_texto($aviso['cuerpo'], 85); ?>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); border-top: 1px dashed rgba(0,0,0,0.08); padding-top: 8px; margin-top: 6px; display:flex; justify-content:space-between; align-items:center;">
                                            <span><i class="far fa-calendar-alt"></i> <?php echo date('d/m/Y h:i A', $aviso['timestamp']); ?></span>
                                            <?php if ($aviso['fecha_expiracion']): ?>
                                                <span title="Desaparecerá pronto" style="color: #f59e0b;"><i class="fas fa-stopwatch"></i></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center; padding: 40px 0; color:var(--text-muted); font-style: italic;">
                            <i class="far fa-check-circle" style="font-size: 2.2rem; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                            No tienes notificaciones administrativas nuevas.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

    <?php require_once 'modales_index.php'; ?>
    <?php include '../main_footer.php'; ?>

    <script src="../js/index_estudiante.js?v=<?php echo time(); ?>"></script>

    <script>
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