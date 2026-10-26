<?php
require_once 'detalle_grupo_logic.php';

/**
 * Declaración de variables importadas desde detalle_grupo_logic.php
 * para evitar falsos positivos de "Undefined variable" en el IDE.
 * 
 * @var array $info_grupo
 * @var array $detalles_grupo
 * @var string $clave_grupo
 * @var int $edicion_total
 * @var bool $grupo_cerrado
 * @var bool $es_ciclo_pasado
 * @var bool $modo_solo_lectura
 * @var array $criterios
 * @var float|int $puntos_maximos_totales
 * @var array $alumnos
 * @var array $calificaciones_actuales
 */

$romano_nivel = nivel_a_romano($info_grupo['nivel']);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificar Grupo | Portal Docente</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profesor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        <!-- Barra Superior: Navegación de Retorno -->
        <div class="dg-top-toolbar">
            <div class="dg-top-nav-left">
                <a href="mis_grupos" class="btn-volver" style="margin-bottom: 0;">
                    <i class="fas fa-arrow-left"></i> Volver a Mis Grupos
                </a>
            </div>
        </div>

        <!-- Fila de Cabecera: Tarjeta de Grupo a la Izquierda y Botones de Acción a la Derecha -->
        <div class="dg-top-row-flex">
            <!-- Tarjeta Acordeón: Resumen Esencial y Detalles Desplegables -->
            <div class="content-card dg-accordion-card" id="cardDetalleGrupo" style="padding: 0; overflow: hidden; border-top: 3px solid var(--udg-blue);">

                <!-- Cabecera Esencial del Acordeón -->
                <div class="dg-accordion-header" onclick="toggleDetallesGrupo()">
                    <!-- Bloque Izquierdo: Materia, Código, Nivel y Ciclo -->
                    <div class="dg-accordion-left">
                        <span class="g-badge" title="Código de Materia">
                            <?php echo htmlspecialchars($info_grupo['clave_materia']); ?>
                        </span>
                        <h1 class="dg-materia-title">
                            <?php echo htmlspecialchars($info_grupo['materia']); ?>
                        </h1>
                        <span class="level-badge">
                            Nivel <?php echo htmlspecialchars($romano_nivel); ?>
                        </span>
                        <span class="dg-cycle-badge">
                            <i class="far fa-calendar-alt"></i> Ciclo <?php echo htmlspecialchars($info_grupo['ciclo']); ?>
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

            <!-- Botones de Acción a la Derecha -->
            <div class="dg-top-actions">
                <?php if ($modo_solo_lectura): ?>
                    <button type="button" class="dg-btn-action dg-btn-equipo" style="opacity: 0.55; cursor: not-allowed; pointer-events: none;" title="No disponible en historial de ciclos pasados">
                        <i class="fas fa-users-slash"></i> Trabajo en Equipo
                    </button>
                <?php else: ?>
                    <a href="trabajo_equipo?clave=<?php echo urlencode($clave_grupo); ?>" class="dg-btn-action dg-btn-equipo">
                        <i class="fas fa-users"></i> Trabajo en Equipo
                    </a>
                <?php endif; ?>

                <a href="asistencia?clave=<?php echo urlencode($clave_grupo) . ($modo_solo_lectura ? '&pasado=1' : ''); ?>" class="dg-btn-action dg-btn-asistencia">
                    <i class="fas fa-calendar-check"></i> Lista de Asistencia
                </a>
            </div>
        </div>

        <?php if ($es_ciclo_pasado): ?>
            <div class="alert-closed" style="background: rgba(100, 116, 139, 0.12); border-color: rgba(100, 116, 139, 0.35); color: #475569;">
                <i class="fas fa-history" style="font-size: 1.8rem; color: #64748b;"></i>
                <div>
                    <strong>Historial de Ciclo Pasado (Solo Lectura)</strong><br>
                    <span style="font-size: 0.9rem;">Este grupo pertenece a un ciclo anterior finalizado. Puedes consultar toda la información y calificaciones de los alumnos, pero no se permiten modificaciones.</span>
                </div>
            </div>
        <?php elseif ($grupo_cerrado): ?>
            <div class="alert-closed">
                <i class="fas fa-archive" style="font-size: 1.8rem;"></i>
                <div><strong>Clase Finalizada</strong><br><span style="font-size: 0.9rem;">Esta clase ha sido cerrada por la administración. Las calificaciones son de solo lectura.</span></div>
            </div>
        <?php elseif ($edicion_total === 0): ?>
            <div class="alert-restricted">
                <i class="fas fa-lock"></i>
                <div><strong>Control Escolar Restringido</strong><br><span style="font-size: 0.9rem;">Solo puedes capturar las evaluaciones de tu competencia (Proyectos, Orales, Participación). Los exámenes principales y plataforma son capturados por administración.</span></div>
            </div>
        <?php endif; ?>

        <?php if (count($criterios) === 0): ?>
            <div class="alert-warning-empty">
                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                <strong>No hay criterios de evaluación definidos para esta materia.</strong>
            </div>
        <?php elseif (count($alumnos) === 0): ?>
            <div class="content-card empty-table-msg">
                <i class="fas fa-ghost"></i>
                <h3>El grupo no tiene alumnos inscritos</h3>
            </div>
        <?php else: ?>

            <div class="excel-header-bar <?php echo $grupo_cerrado ? 'bg-cerrado' : ''; ?>">
                <div class="excel-header-title"><i class="fas fa-edit"></i> Hoja de Calificaciones <?php if ($grupo_cerrado) echo "(Solo Lectura)";
                                                                                                    else echo "En Vivo"; ?></div>
                <?php if (!$grupo_cerrado): ?><div id="saveStatus" class="excel-save-status"><i class="fas fa-cloud-upload-alt"></i> Guardado automático activo</div><?php endif; ?>
            </div>

            <div class="excel-table-wrapper-profe" style="border-top-left-radius: 0; border-top-right-radius: 0; margin-bottom: 30px;">
                <table class="excel-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <?php foreach ($criterios as $c): ?>
                                <th title="<?php echo htmlspecialchars($c['nombre_examen'] ?? ''); ?>">
                                    <i class="excel-crit-icon <?php echo htmlspecialchars($c['icono_fijo']); ?> <?php echo ($c['bloqueado'] || $grupo_cerrado) ? 'class-locked' : $c['color_fijo']; ?>"></i>
                                    <div class="excel-crit-title">
                                        <span class="crit-title-desktop"><?php echo htmlspecialchars($c['nombre_examen'] ?? ''); ?></span>
                                        <span class="crit-title-mobile"><?php echo htmlspecialchars(abreviar_criterio_movil($c['nombre_examen'] ?? '', $c['codigo_examen'] ?? '')); ?></span>
                                        <?php if ($c['bloqueado'] || $grupo_cerrado) echo ' <i class="fas fa-lock lock-icon" title="Manejado por Control Escolar"></i>'; ?>
                                    </div>
                                    <span class="excel-crit-max">Máx: <?php echo floatval($c['puntos_maximos'] ?? 0); ?></span>
                                </th>
                            <?php endforeach; ?>
                            <th class="excel-total-col">
                                <i class="fas fa-calculator excel-crit-icon"></i>
                                <div class="excel-crit-title">TOTAL</div>
                                <span class="excel-crit-max">/ <?php echo $puntos_maximos_totales; ?></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumnos as $a):
                            $insc_id = $a['inscripcion_id'];
                            $suma_alumno = 0;
                            $apellidos = trim(($a['apellido_paterno'] ?? '') . ' ' . ($a['apellido_materno'] ?? ''));
                            $nombres = trim($a['nombre'] ?? '');
                            $foto_url = "../img/avatar-default.png";
                            if (!empty($a['foto_perfil']) && file_exists("../img/perfiles/" . $a['foto_perfil'])) {
                                $foto_url = "../img/perfiles/" . $a['foto_perfil'];
                            }
                        ?>
                            <tr>
                                <td class="excel-student-cell">
                                    <img src="<?php echo htmlspecialchars($foto_url); ?>" class="excel-student-img">
                                    <div>
                                        <div class="excel-student-name">
                                            <?php echo htmlspecialchars($apellidos); ?><br><?php echo htmlspecialchars($nombres); ?>
                                        </div>
                                        <div class="excel-student-code">Código: <?php echo htmlspecialchars($a['codigo'] ?? 'N/A'); ?></div>
                                    </div>
                                </td>
                                <?php foreach ($criterios as $c):
                                    $cod_examen = $c['codigo_examen'];
                                    $max_pts = floatval($c['puntos_maximos'] ?? 0);
                                    $val_actual = '';
                                    if (isset($calificaciones_actuales[$insc_id]) && isset($calificaciones_actuales[$insc_id][$cod_examen])) {
                                        $val_actual = floatval($calificaciones_actuales[$insc_id][$cod_examen]);
                                        $suma_alumno += $val_actual;
                                    }
                                    $bloqueado_total = $c['bloqueado'] || $grupo_cerrado;
                                    $readonly_attr = $bloqueado_total ? 'readonly tabindex="-1"' : '';
                                    $class_attr = $bloqueado_total ? 'grade-input grade-locked js-grade-input' : 'grade-input js-grade-input';
                                ?>
                                    <td><input type="number" step="0.01" min="0" max="<?php echo $max_pts; ?>" value="<?php echo htmlspecialchars((string)$val_actual); ?>" class="<?php echo $class_attr; ?>" data-insc="<?php echo htmlspecialchars((string)$insc_id); ?>" data-examen="<?php echo htmlspecialchars((string)$cod_examen); ?>" <?php echo $readonly_attr; ?> <?php if ($bloqueado_total) echo 'title="Calificación Bloqueada o Cerrada"'; ?>></td>
                                <?php endforeach; ?>
                                <td class="excel-total-cell-bg">
                                    <div class="total-cell js-total-<?php echo htmlspecialchars((string)$insc_id); ?>"><?php echo number_format($suma_alumno, 1); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <?php include '../main_footer.php'; ?>

    <script>
        // VARIABLE GLOBAL DEL CSRF TOKEN GENERADA EN PHP
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.js-grade-input');
            const totalMaximo = <?php echo $puntos_maximos_totales; ?>;
            const puntajeMinimoAprobatorio = 60;
            const saveStatusText = document.getElementById('saveStatus');

            function recalcularTotal(insc_id) {
                const studentInputs = document.querySelectorAll(`.js-grade-input[data-insc="${insc_id}"]`);
                let suma = 0;
                studentInputs.forEach(input => {
                    const val = parseFloat(input.value);
                    if (!isNaN(val)) suma += val;
                });
                const displayCell = document.querySelector(`.js-total-${insc_id}`);
                if (displayCell) {
                    displayCell.innerText = suma.toFixed(1);
                    let porcentaje = (totalMaximo > 0) ? (suma / totalMaximo) * 100 : 0;
                    displayCell.classList.remove('total-aprobado', 'total-reprobado');
                    if (porcentaje >= puntajeMinimoAprobatorio) displayCell.classList.add('total-aprobado');
                    else displayCell.classList.add('total-reprobado');
                }
            }

            inputs.forEach(input => {
                if (!input.hasAttribute('readonly')) {
                    input.addEventListener('input', function() {
                        const maxAllowed = parseFloat(this.getAttribute('max'));
                        if (parseFloat(this.value) > maxAllowed) this.value = maxAllowed;
                        if (parseFloat(this.value) < 0) this.value = 0;
                        recalcularTotal(this.getAttribute('data-insc'));
                    });

                    input.addEventListener('change', function() {
                        const insc_id = this.getAttribute('data-insc');
                        const cod_examen = this.getAttribute('data-examen');
                        const val = this.value;
                        const cell = this;

                        if (saveStatusText) saveStatusText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

                        // SE AÑADE EL TOKEN CSRF AL CUERPO DEL JSON
                        fetch('calificaciones_api', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    action: 'save_single',
                                    inscripcion_id: insc_id,
                                    tipo_examen: cod_examen,
                                    puntaje: val,
                                    csrf_token: csrfToken
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    if (saveStatusText) saveStatusText.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745;"></i> Guardado';
                                    cell.classList.remove('flash-success');
                                    void cell.offsetWidth;
                                    cell.classList.add('flash-success');
                                    setTimeout(() => {
                                        if (saveStatusText) saveStatusText.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Guardado automático activo';
                                    }, 2000);
                                } else {
                                    alert('Error al guardar: ' + data.error);
                                }
                            })
                            .catch(err => {
                                alert('Problema de conexión al guardar.');
                            });
                    });
                }
                recalcularTotal(input.getAttribute('data-insc'));
            });
        });

        // CONTROL DEL ACORDEÓN DE DETALLES DEL GRUPO
        function toggleDetallesGrupo() {
            const drawer = document.getElementById('drawerDetallesGrupo');
            const btn = document.getElementById('btnToggleDetallesGrupo');
            if (!drawer || !btn) return;

            const isHidden = (drawer.style.display === 'none' || drawer.style.display === '');
            if (isHidden) {
                drawer.style.display = 'block';
                btn.classList.add('g-toggle-btn-active');
                btn.innerHTML = '<i class="fas fa-chevron-up"></i>';
                localStorage.setItem('epale_detalle_grupo_drawer', 'open');
            } else {
                drawer.style.display = 'none';
                btn.classList.remove('g-toggle-btn-active');
                btn.innerHTML = '<i class="fas fa-chevron-down"></i>';
                localStorage.setItem('epale_detalle_grupo_drawer', 'closed');
            }
        }

        // Restaurar estado preferido del docente
        document.addEventListener('DOMContentLoaded', function() {
            const estadoGuardado = localStorage.getItem('epale_detalle_grupo_drawer');
            if (estadoGuardado === 'open') {
                const drawer = document.getElementById('drawerDetallesGrupo');
                const btn = document.getElementById('btnToggleDetallesGrupo');
                if (drawer && btn) {
                    drawer.style.display = 'block';
                    btn.classList.add('g-toggle-btn-active');
                    btn.innerHTML = '<i class="fas fa-chevron-up"></i>';
                }
            }
        });
    </script>
</body>

</html>