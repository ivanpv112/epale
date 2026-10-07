<?php
session_start();
require '../db.php';
require_once '../security.php';

// Validar que sea alumno
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index");
    exit;
}

$alumno_id_usuario = $_SESSION['user_id'];
$stmt_al = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
$stmt_al->execute([$alumno_id_usuario]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);
$alumno_id = $alumno['alumno_id'];

// Obtener nombre del ciclo activo
$stmt_ciclo = $pdo->query("SELECT nombre FROM ciclos WHERE activo = 1 LIMIT 1");
$nombre_ciclo = $stmt_ciclo->fetchColumn() ?: 'Actual';

$config_file = '../config.json';
$config = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : [];
$altas_habilitadas = isset($config['altas_habilitadas']) ? (bool)$config['altas_habilitadas'] : false;

// Obtener los grupos activos (Agrupados por clave_grupo)
$sql_grupos = "
    SELECT 
        g.clave_grupo, g.nrc, g.cupo, 
        m.materia_id, m.clave as clave_materia, m.nombre as idioma, m.nivel, 
        u.nombre as prof_nombre, u.apellido_paterno as prof_ap,
        (SELECT COUNT(*) FROM inscripciones i WHERE i.nrc = g.nrc AND (i.estatus = 'INSCRITO' OR i.estatus = 'BAJA')) as inscritos,
        (SELECT COUNT(*) FROM solicitudes_altas sa JOIN inscripciones i ON sa.inscripcion_id = i.inscripcion_id WHERE i.nrc = g.nrc AND sa.estatus = 'PENDIENTE') as demanda,
        h.hora_inicio, h.hora_fin, h.dias_patron, h.aula, h.modalidad
    FROM grupos g
    JOIN materias m ON g.materia_id = m.materia_id
    JOIN usuarios u ON g.profesor_id = u.usuario_id
    JOIN ciclos c ON g.ciclo_id = c.ciclo_id
    LEFT JOIN horarios h ON g.nrc = h.nrc
    WHERE c.activo = 1 AND g.estado = 'ACTIVO'
    ORDER BY m.nombre ASC, m.nivel ASC, g.clave_grupo ASC
";
$raw_grupos = $pdo->query($sql_grupos)->fetchAll(PDO::FETCH_ASSOC);

// Obtener materias en las que el alumno ya está inscrito
$stmt_inscritos = $pdo->prepare("
    SELECT m.materia_id, i.nrc 
    FROM inscripciones i 
    JOIN grupos g ON i.nrc = g.nrc 
    JOIN materias m ON g.materia_id = m.materia_id 
    WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'
");
$stmt_inscritos->execute([$alumno_id]);
$inscritos_data = $stmt_inscritos->fetchAll(PDO::FETCH_ASSOC);
$mis_nrcs = array_column($inscritos_data, 'nrc');
$mis_materias_inscritas = array_column($inscritos_data, 'materia_id');

$stmt_solicitados = $pdo->prepare("
    SELECT m.materia_id, i.nrc 
    FROM inscripciones i 
    JOIN grupos g ON i.nrc = g.nrc 
    JOIN materias m ON g.materia_id = m.materia_id 
    WHERE i.alumno_id = ? AND i.estatus = 'SOLICITUD_ALTA'
");
$stmt_solicitados->execute([$alumno_id]);
$solicitados_data = $stmt_solicitados->fetchAll(PDO::FETCH_ASSOC);
$mis_solicitudes = array_column($solicitados_data, 'nrc');
$mis_materias_solicitadas = array_column($solicitados_data, 'materia_id');

// Obtener materias que el alumno ya acreditó (calificación final >= 80)
$stmt_acreditadas = $pdo->prepare("
    SELECT CONCAT(UPPER(TRIM(m.nombre)), '_', m.nivel) as materia_key
    FROM inscripciones i
    JOIN grupos g ON i.nrc = g.nrc
    JOIN materias m ON g.materia_id = m.materia_id
    LEFT JOIN calificaciones c ON c.inscripcion_id = i.inscripcion_id
    WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'
    GROUP BY m.nombre, m.nivel, i.inscripcion_id
    HAVING SUM(COALESCE(c.puntaje, 0)) >= 80
");
$stmt_acreditadas->execute([$alumno_id]);
$mis_materias_acreditadas = $stmt_acreditadas->fetchAll(PDO::FETCH_COLUMN);

$grupos_agrupados = [];
foreach ($raw_grupos as $row) {
    $clave = $row['clave_grupo'] ?: $row['nrc']; // Usar nrc como clave si clave_grupo es nulo
    if (!isset($grupos_agrupados[$clave])) {
        $grupos_agrupados[$clave] = [
            'clave_grupo' => $row['clave_grupo'],
            'materia_id' => $row['materia_id'],
            'clave_materia' => $row['clave_materia'],
            'idioma' => $row['idioma'],
            'nivel' => $row['nivel'],
            'prof_nombre' => $row['prof_nombre'],
            'prof_ap' => $row['prof_ap'],
            'cupo' => $row['cupo'],
            'inscritos' => $row['inscritos'],
            'demanda' => $row['demanda'],
            'nrc_presencial' => 'NA',
            'nrc_virtual' => 'NA',
            'horario_presencial' => null,
            'horario_virtual' => null
        ];
    }

    // Maximizar inscritos y demanda
    $grupos_agrupados[$clave]['inscritos'] = max($grupos_agrupados[$clave]['inscritos'], $row['inscritos']);
    $grupos_agrupados[$clave]['demanda'] = max($grupos_agrupados[$clave]['demanda'], $row['demanda']);

    if ($row['modalidad'] == 'PRESENCIAL') {
        $grupos_agrupados[$clave]['nrc_presencial'] = $row['nrc'];
        $grupos_agrupados[$clave]['horario_presencial'] = [
            'inicio' => substr($row['hora_inicio'], 0, 5),
            'fin' => substr($row['hora_fin'], 0, 5),
            'dias' => getDiasCompletos($row['dias_patron']),
            'aula' => $row['aula']
        ];
    } elseif ($row['modalidad'] == 'VIRTUAL') {
        $grupos_agrupados[$clave]['nrc_virtual'] = $row['nrc'];
        $grupos_agrupados[$clave]['horario_virtual'] = [
            'inicio' => substr($row['hora_inicio'], 0, 5),
            'fin' => substr($row['hora_fin'], 0, 5),
            'dias' => getDiasCompletos($row['dias_patron']),
            'aula' => $row['aula']
        ];
    } else {
        // Sin modalidad asignada, se pone como presencial por defecto
        if ($grupos_agrupados[$clave]['nrc_presencial'] == 'NA') {
            $grupos_agrupados[$clave]['nrc_presencial'] = $row['nrc'];
        }
    }
}
$grupos = array_values($grupos_agrupados);

// Obtener historial de solicitudes de alta
$stmt_hist_altas = $pdo->prepare("
    SELECT sa.*, m.nombre AS materia, m.nivel, m.clave AS clave_materia, g.clave_grupo 
    FROM solicitudes_altas sa
    JOIN inscripciones i ON sa.inscripcion_id = i.inscripcion_id
    JOIN grupos g ON i.nrc = g.nrc
    JOIN materias m ON g.materia_id = m.materia_id
    WHERE i.alumno_id = ?
    ORDER BY sa.fecha_solicitud DESC
");
$stmt_hist_altas->execute([$alumno_id]);
$historial_altas = $stmt_hist_altas->fetchAll(PDO::FETCH_ASSOC);

$hay_actualizacion_reciente_alta = false;
if (count($historial_altas) > 0) {
    $ultima_alta = $historial_altas[0];
    if ($ultima_alta['estatus'] === 'RECHAZADA' || $ultima_alta['estatus'] === 'APROBADA') {
        $hay_actualizacion_reciente_alta = true;
    }
}

// Convertir nivel entero a romano
function getRomanNumeral(int $num): string
{
    $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];
    return (string)($map[$num] ?? $num);
}

// Convertir días a nombre completo
function getDiasCompletos(string $dias_patron): string
{
    if (empty($dias_patron)) return 'Sin asignar';
    $map = ['L' => 'Lunes', 'M' => 'Martes', 'I' => 'Miércoles', 'J' => 'Jueves', 'V' => 'Viernes', 'S' => 'Sábado', 'D' => 'Domingo'];
    $letras = explode('-', $dias_patron);
    $nombres = [];
    foreach ($letras as $l) {
        $l = trim($l);
        if (isset($map[$l])) $nombres[] = $map[$l];
    }
    return !empty($nombres) ? implode('-', $nombres) : $dias_patron;
}

// Generar token para modal
$csrf_token = $_SESSION['csrf_token'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oferta Académica | E-Pale</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content">
        <div class="oferta-header">
            <h1><i class="fas fa-globe"></i> Oferta Académica</h1>
            <p>Ciclo <?php echo htmlspecialchars($nombre_ciclo); ?></p>
        </div>

        <?php if (count($historial_altas) > 0): ?>
            <div style="display: flex; justify-content: flex-start; margin-bottom: 20px;">
                <button class="btn-historial" style="width: max-content;" onclick="document.getElementById('modalHistorialAltas').style.display='flex'">
                    <i class="fas fa-history"></i> Historial de Solicitudes
                    <?php if ($hay_actualizacion_reciente_alta): ?>
                        <i class="fas fa-circle" style="color: #dc3545; font-size: 0.6rem; animation: blink 2s infinite;"></i>
                    <?php endif; ?>
                </button>
                <style>
                    @keyframes blink {
                        0% {
                            opacity: 1;
                        }

                        50% {
                            opacity: 0;
                        }

                        100% {
                            opacity: 1;
                        }
                    }
                </style>
            </div>
        <?php endif; ?>

        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Buscar por idioma, nivel, profesor o NRC..." onkeyup="filterCards()">
            <select id="langFilter" onchange="filterCards()">
                <option value="">Todos los idiomas</option>
                <?php
                $idiomas_unicos = [];
                foreach ($grupos as $g) {
                    $idiomas_unicos[$g['idioma']] = $g['idioma'];
                }
                foreach ($idiomas_unicos as $i) {
                    echo "<option value=\"" . htmlspecialchars($i) . "\">" . htmlspecialchars($i) . "</option>";
                }
                ?>
            </select>
        </div>

        <div class="grid-oferta" id="cardsGrid">
            <?php if (!$altas_habilitadas): ?>
                <div style="grid-column: 1 / -1; background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold;">
                    <i class="fas fa-lock"></i> El periodo de altas está cerrado temporalmente. Las opciones de solicitar materia han sido deshabilitadas.
                </div>
            <?php endif; ?>
            <?php foreach ($grupos as $g): ?>
                <?php
                $lugares_disp = $g['cupo'] - $g['inscritos'];
                if ($lugares_disp < 0) $lugares_disp = 0;

                $porcentaje_disp = $g['cupo'] > 0 ? ($lugares_disp / $g['cupo']) * 100 : 0;

                $badge_class = 'badge-verde';
                $badge_text = "$lugares_disp lugares";
                if ($porcentaje_disp <= 0) {
                    $badge_class = 'badge-rojo';
                    $badge_text = "Lleno";
                } elseif ($porcentaje_disp < 20) {
                    $badge_class = 'badge-rojo';
                } elseif ($porcentaje_disp < 40) {
                    $badge_class = 'badge-amarillo';
                }

                $horas_txt = "";

                // Texto horario Presencial
                if ($g['horario_presencial']) {
                    $hp = $g['horario_presencial'];
                    $horas_txt .= "<p style='margin-bottom:8px;'><i class=\"fas fa-building\" style=\"color:#ffc107; width:20px;\"></i> {$hp['dias']} • {$hp['inicio']} - {$hp['fin']} <br><i class=\"fas fa-map-marker-alt\" style=\"color:#ffc107; width:20px;\"></i> {$hp['aula']}</p>";
                } else {
                    $horas_txt .= "<p style='margin-bottom:8px; color:#888;'><i class=\"fas fa-building\" style=\"color:#ffc107; width:20px;\"></i> NA</p>";
                }

                // Texto horario Virtual
                if ($g['horario_virtual']) {
                    $hv = $g['horario_virtual'];
                    $horas_txt .= "<p><i class=\"fas fa-laptop\" style=\"color:#17a2b8; width:20px;\"></i> {$hv['dias']} • {$hv['inicio']} - {$hv['fin']} <br><i class=\"fas fa-video\" style=\"color:#17a2b8; width:20px;\"></i> {$hv['aula']}</p>";
                } else {
                    $horas_txt .= "<p style='color:#888;'><i class=\"fas fa-laptop\" style=\"color:#17a2b8; width:20px;\"></i> NA <br><i class=\"fas fa-video\" style=\"color:#17a2b8; width:20px;\"></i> NA</p>";
                }

                $nrc_para_solicitud = $g['nrc_presencial'] !== 'NA' ? $g['nrc_presencial'] : $g['nrc_virtual'];
                ?>
                <div class="card-oferta" data-lang="<?php echo htmlspecialchars($g['idioma']); ?>" data-text="<?php echo strtolower(htmlspecialchars($g['clave_materia'] . ' ' . $g['idioma'] . ' ' . getRomanNumeral($g['nivel']) . ' ' . $g['prof_nombre'] . ' ' . $g['prof_ap'] . ' ' . $g['clave_grupo'])); ?>">
                    <div class="card-header">
                        <div class="card-title">
                            <h3><span style="font-size:0.9rem; color:#888; margin-right:5px;"><?php echo htmlspecialchars($g['clave_materia']); ?></span> <?php echo htmlspecialchars($g['idioma']); ?> <?php echo getRomanNumeral($g['nivel']); ?></h3>
                            <p style="margin-bottom:5px;">Grupo <?php echo htmlspecialchars($g['clave_grupo'] ?? 'S/N'); ?></p>
                            <p style="margin-top: 5px; font-weight: 600;">NRC:<br>
                                <span style="color: #ffc107;"><i class="fas fa-building"></i> <?php echo $g['nrc_presencial']; ?></span> -
                                <span style="color: #17a2b8;"><i class="fas fa-laptop"></i> <?php echo $g['nrc_virtual']; ?></span>
                            </p>
                        </div>
                        <div class="badges-container">
                            <span class="badge-cupo <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                            <?php if ($g['demanda'] > 0): ?>
                                <span class="badge-demanda"><i class="fas fa-fire"></i> <?php echo $g['demanda']; ?> peticiones</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-info">
                        <p><i class="fas fa-chalkboard-teacher"></i> Prof. <?php echo htmlspecialchars(trim($g['prof_nombre'] . ' ' . $g['prof_ap'])); ?></p>
                        <?php echo $horas_txt; ?>
                    </div>

                    <div class="card-progress">
                        <div class="progress-text">
                            <span>Inscritos</span>
                            <span><?php echo $g['inscritos']; ?>/<?php echo $g['cupo']; ?></span>
                        </div>
                        <div class="progress-bar-container">
                            <?php
                            $fill_perc = $g['cupo'] > 0 ? ($g['inscritos'] / $g['cupo']) * 100 : 100;
                            if ($fill_perc > 100) $fill_perc = 100;
                            ?>
                            <div class="progress-bar-fill <?php echo $fill_perc >= 100 ? 'danger' : ''; ?>" style="width: <?php echo $fill_perc; ?>%"></div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <?php
                        $ya_inscrito_nrc = in_array($g['nrc_presencial'], $mis_nrcs) || in_array($g['nrc_virtual'], $mis_nrcs);
                        $ya_solicitado_nrc = in_array($g['nrc_presencial'], $mis_solicitudes) || in_array($g['nrc_virtual'], $mis_solicitudes);
                        
                        $ya_cursando_materia = in_array($g['materia_id'], $mis_materias_inscritas);
                        $ya_solicitada_materia = in_array($g['materia_id'], $mis_materias_solicitadas);
                        
                        $materia_key = mb_strtoupper(trim($g['idioma']), 'UTF-8') . '_' . $g['nivel'];
                        $ya_acreditada_materia = in_array($materia_key, $mis_materias_acreditadas);
                        
                        if ($ya_inscrito_nrc): ?>
                            <button class="btn-solicitar" style="background:#28a745; cursor:not-allowed;" disabled>
                                Inscrito <i class="fas fa-check-circle"></i>
                            </button>
                        <?php elseif ($ya_acreditada_materia): ?>
                            <div style="display: flex; align-items: center;">
                                <span style="font-size: 0.8rem; color: #0d6efd; opacity: 0.8; margin-right: 15px; font-style: italic;">
                                    Ya has acreditado este nivel de idioma.
                                </span>
                                <button class="btn-solicitar" style="background:#0d6efd; cursor:not-allowed; white-space: nowrap;" disabled>
                                    Acreditado <i class="fas fa-check-double"></i>
                                </button>
                            </div>
                        <?php elseif ($ya_solicitado_nrc): ?>
                            <div style="display: flex; align-items: center;">
                                <span style="font-size: 0.8rem; color: #856404; margin-right: 15px; font-style: italic;">
                                    En espera a la confirmación de un administrador.
                                </span>
                                <button class="btn-solicitar" style="background:#ffc107; color:#333; cursor:pointer;" onclick="cancelarSolicitud(<?php echo $nrc_para_solicitud; ?>)">
                                    Solicitada <i class="fas fa-clock"></i>
                                </button>
                            </div>
                        <?php elseif ($ya_cursando_materia): ?>
                            <div style="display: flex; align-items: center;">
                                <span style="font-size: 0.8rem; color: #6f42c1; opacity: 0.8; margin-right: 15px; font-style: italic;">
                                    Ya estás cursando este nivel de idioma.
                                </span>
                                <button class="btn-solicitar" style="background:#6f42c1; cursor:not-allowed; white-space: nowrap;" disabled>
                                    En Curso <i class="fas fa-ban"></i>
                                </button>
                            </div>
                        <?php elseif ($ya_solicitada_materia): ?>
                            <div style="display: flex; align-items: center;">
                                <span style="font-size: 0.8rem; color: #fd7e14; opacity: 0.8; margin-right: 15px; font-style: italic;">
                                    Ya tienes una solicitud pendiente para este nivel.
                                </span>
                                <button class="btn-solicitar" style="background:#fd7e14; cursor:not-allowed; white-space: nowrap;" disabled>
                                    Solicitado <i class="fas fa-ban"></i>
                                </button>
                            </div>
                        <?php elseif ($altas_habilitadas): ?>
                            <button class="btn-solicitar" onclick="openModal(<?php echo $nrc_para_solicitud; ?>, '<?php echo addslashes(htmlspecialchars($g['clave_materia'])); ?>', '<?php echo addslashes(htmlspecialchars($g['idioma'] . ' ' . getRomanNumeral($g['nivel']))); ?>', '<?php echo htmlspecialchars($g['nrc_presencial']); ?>', '<?php echo htmlspecialchars($g['nrc_virtual']); ?>')">
                                Solicitar <i class="fas fa-paper-plane"></i>
                            </button>
                        <?php else: ?>
                            <button class="btn-solicitar" style="background:#ccc; cursor:not-allowed;" disabled>
                                No Disponible <i class="fas fa-lock"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </main>

    <!-- Modal para Solicitar -->
    <div class="modal" id="modalSolicitar">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h3 style="margin-top:0; color:var(--udg-blue); border-bottom:1px solid #eee; padding-bottom:10px;">Solicitar Alta de Materia</h3>
            <p id="materiaSolicitadaTxt" style="font-weight:bold; margin-bottom: 20px;"></p>

            <div class="alert" style="background:#fff3cd; color:#856404; padding:10px; border-radius:5px; margin-bottom:20px; font-size:0.9rem;">
                <i class="fas fa-exclamation-triangle"></i> Si la materia que solicitas choca con tu horario externo adjunto, será rechazada al instante. Se validarán cruces automáticamente.
            </div>

            <form id="formSolicitar" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="nrc" id="inputNrc" value="">

                <div class="form-group">
                    <label>Motivo de la solicitud:</label>
                    <textarea name="motivo" rows="3" required maxlength="250" placeholder="Explica por qué necesitas dar de alta esta materia..."></textarea>
                    <small style="color:#666; display:block; margin-top:5px; text-align:right;">Máximo 250 caracteres.</small>
                </div>

                <div class="form-group">
                    <label>Captura de tu horario escolar (Obligatorio):</label>
                    <input type="file" name="horario_img" accept="image/png, image/jpeg, image/jpg" required>
                    <small style="color:#666; display:block; margin-top:5px;">Sube una imagen clara de tu horario (se optimizará al subir).</small>
                </div>

                <button type="submit" class="btn-solicitar" style="width:100%; justify-content:center; margin-top:10px;" id="btnSubmit">
                    Enviar Solicitud <i class="fas fa-check"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Modal de Historial de Altas -->
    <?php if (count($historial_altas) > 0): ?>
        <div id="modalHistorialAltas" class="modal-overlay" style="display:none;">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3 style="margin:0; color:var(--udg-blue);"><i class="fas fa-history"></i> Historial de Solicitudes de Alta</h3>
                    <button class="close-btn" onclick="document.getElementById('modalHistorialAltas').style.display='none'">&times;</button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <p style="font-size:0.9rem; color:var(--text-muted); margin-top:0;">Historial de peticiones de alta para el semestre actual.</p>

                    <?php foreach ($historial_altas as $h):
                        $class_card = strtolower($h['estatus']);
                        $icono_estatus = '';
                        $color_estatus = '';

                        switch ($h['estatus']) {
                            case 'PENDIENTE':
                                $icono_estatus = 'fa-clock';
                                $color_estatus = '#856404';
                                break;
                            case 'RECHAZADA':
                                $icono_estatus = 'fa-times-circle';
                                $color_estatus = '#dc3545';
                                break;
                            case 'CANCELADA':
                                $icono_estatus = 'fa-ban';
                                $color_estatus = '#6c757d';
                                break;
                            case 'APROBADA':
                                $icono_estatus = 'fa-check-circle';
                                $color_estatus = '#28a745';
                                break;
                        }
                    ?>
                        <div class="history-card <?php echo $class_card; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div style="font-weight: bold; color: var(--text-dark); font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($h['materia'] . ' ' . getRomanNumeral((int)$h['nivel'])); ?>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: normal; margin-top: 2px;">Grupo <?php echo htmlspecialchars($h['clave_grupo']); ?> | Clave: <?php echo htmlspecialchars($h['clave_materia']); ?></div>
                                </div>
                                <div style="font-size: 0.8rem; font-weight: bold; color: <?php echo $color_estatus; ?>; padding: 3px 8px; border-radius: 12px; border: 1px solid <?php echo $color_estatus; ?>;">
                                    <i class="fas <?php echo $icono_estatus; ?>"></i> <?php echo $h['estatus']; ?>
                                </div>
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 10px;"><i class="far fa-calendar-alt"></i> Solicitada el: <?php echo date('d/m/Y', strtotime($h['fecha_solicitud'])); ?></div>

                            <div style="font-size: 0.85rem; color: var(--text-dark); margin-bottom: 10px;">
                                <strong>Motivo:</strong> <?php echo htmlspecialchars($h['motivo']); ?>
                            </div>

                            <?php if ($h['estatus'] !== 'PENDIENTE' && $h['estatus'] !== 'CANCELADA'): ?>
                                <div style="border-top: 1px dashed var(--text-muted); padding-top: 10px; margin-top: 10px;">
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px;"><i class="fas fa-reply"></i> Respuesta de Administración (<?php echo date('d/m/Y', strtotime($h['fecha_respuesta'])); ?>):</div>
                                    <div style="font-size: 0.9rem; color: var(--text-dark); font-weight: 500;"><?php echo htmlspecialchars($h['respuesta_admin']) ?: 'Sin comentarios adicionales.'; ?></div>
                                </div>
                            <?php elseif ($h['estatus'] === 'CANCELADA'): ?>
                                <div style="border-top: 1px dashed var(--text-muted); padding-top: 10px; margin-top: 10px;">
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px;"><i class="fas fa-reply"></i> Resolución del Estudiante (<?php echo date('d/m/Y', strtotime($h['fecha_respuesta'])); ?>):</div>
                                    <div style="font-size: 0.9rem; color: var(--text-dark); font-weight: 500;">El estudiante canceló la solicitud.</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function filterCards() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const lang = document.getElementById('langFilter').value;
            const cards = document.querySelectorAll('.card-oferta');

            cards.forEach(card => {
                const text = card.getAttribute('data-text');
                const cardLang = card.getAttribute('data-lang');

                let matchesSearch = text.includes(input);
                let matchesLang = lang === '' || cardLang === lang;

                if (matchesSearch && matchesLang) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        const modal = document.getElementById('modalSolicitar');

        function openModal(nrc_solicitar, clave, nombre, nrc_p, nrc_v) {
            document.getElementById('inputNrc').value = nrc_solicitar;
            document.getElementById('materiaSolicitadaTxt').innerHTML =
                clave + ": " + nombre + " - NRC: <span style='color:#ffc107;'><i class='fas fa-building'></i> " + nrc_p + "</span> | <span style='color:#17a2b8;'><i class='fas fa-laptop'></i> " + nrc_v + "</span>";
            modal.classList.add('active');
        }

        function closeModal() {
            modal.classList.remove('active');
            document.getElementById('formSolicitar').reset();
        }

        document.getElementById('formSolicitar').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.innerHTML = 'Enviando... <i class="fas fa-spinner fa-spin"></i>';

            let formData = new FormData(this);

            fetch('procesar_solicitud_alta.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = 'Enviar Solicitud <i class="fas fa-check"></i>';

                    if (data.status === 'success') {
                        closeModal();
                        Swal.fire({
                            icon: 'success',
                            title: 'Solicitud Enviada',
                            text: data.message,
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        if (data.horario) {
                            // Conflicto de horario detectado
                            Swal.fire({
                                icon: 'error',
                                title: 'Cruce de Horario Detectado',
                                html: data.message + '<br><br><b>Tienes clase de:</b> ' + data.horario,
                                confirmButtonColor: '#dc3545'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message,
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = 'Enviar Solicitud <i class="fas fa-check"></i>';
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de red',
                        text: 'No se pudo conectar con el servidor.'
                    });
                });
        });

        function cancelarSolicitud(nrc) {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Estás a punto de retirar tu solicitud para esta materia.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, retirar solicitud',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    let formData = new FormData();
                    formData.append('action', 'cancelar');
                    formData.append('nrc', nrc);
                    formData.append('csrf_token', '<?php echo htmlspecialchars($csrf_token); ?>');

                    fetch('procesar_solicitud_alta.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Retirada',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        })
                        .catch(err => {
                            Swal.fire('Error', 'Ocurrió un problema de conexión', 'error');
                        });
                }
            });
        }
    </script>
</body>

</html>