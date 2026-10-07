<?php
session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Error de Seguridad Crítico: Token CSRF inválido o ausente. Petición bloqueada.");
    }
}

$config_file = '../config.json';
$config = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : [];
$altas_habilitadas = isset($config['altas_habilitadas']) ? (bool)$config['altas_habilitadas'] : false;


$mensaje = '';
$tipo_mensaje = '';

// =======================================================
// PROCESAR APROBACIÓN / RECHAZO
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $solicitud_id = intval($_POST['solicitud_id']);
    $inscripcion_id = intval($_POST['inscripcion_id']);
    $respuesta = strip_tags(trim($_POST['respuesta_admin']));
    
    if (empty($respuesta)) {
        $respuesta = ($_POST['action'] === 'aprobar') ? 'Su solicitud de alta ha sido aprobada.' : 'Su solicitud de alta ha sido rechazada.';
    }
    
    // Si la acción es aprobar, verificar nuevamente que haya lugares (cupo)
    // También verificar que el grupo sigue activo

    try {
        $pdo->beginTransaction();

        $stmtInfo = $pdo->prepare("
            SELECT u.nombre, u.apellido_paterno, u.codigo, m.nombre AS materia, g.nrc, g.cupo,
                   (SELECT COUNT(*) FROM inscripciones i2 WHERE i2.nrc = g.nrc AND (i2.estatus = 'INSCRITO' OR i2.estatus = 'BAJA')) as inscritos
            FROM solicitudes_altas sa
            JOIN inscripciones i ON sa.inscripcion_id = i.inscripcion_id 
            JOIN alumnos a ON i.alumno_id = a.alumno_id 
            JOIN usuarios u ON a.usuario_id = u.usuario_id 
            JOIN grupos g ON i.nrc = g.nrc 
            JOIN materias m ON g.materia_id = m.materia_id 
            WHERE sa.solicitud_id = ?
        ");
        $stmtInfo->execute([$solicitud_id]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        $afectado = $info ? $info['nombre'] . ' ' . $info['apellido_paterno'] . ' (' . $info['codigo'] . ')' : 'Desconocido';
        $clase_info = $info ? $info['materia'] . " (NRC: " . $info['nrc'] . ")" : 'Clase Desconocida';

        if ($_POST['action'] === 'aprobar') {
            if ($info && $info['inscritos'] >= $info['cupo']) {
                throw new Exception("El grupo ya alcanzó el cupo máximo ({$info['cupo']}).");
            }

            $pdo->prepare("UPDATE solicitudes_altas SET estatus = 'APROBADA', respuesta_admin = ?, fecha_respuesta = NOW() WHERE solicitud_id = ?")->execute([$respuesta, $solicitud_id]);
            $pdo->prepare("UPDATE inscripciones SET estatus = 'INSCRITO' WHERE inscripcion_id = ?")->execute([$inscripcion_id]);

            registrar_historial($pdo, $_SESSION['user_id'], 'Estado', 'Altas', 'Aprobación de Alta', $afectado, "Clase: $clase_info • Estado: Pendiente → Aprobada");

            $mensaje = "Solicitud aprobada: El alumno ha sido inscrito a la materia.";
            $tipo_mensaje = "success";
        } elseif ($_POST['action'] === 'rechazar') {
            $pdo->prepare("UPDATE solicitudes_altas SET estatus = 'RECHAZADA', respuesta_admin = ?, fecha_respuesta = NOW() WHERE solicitud_id = ?")->execute([$respuesta, $solicitud_id]);
            // Marcamos como cancelada o baja la inscripcion para liberar su horario
            $pdo->prepare("UPDATE inscripciones SET estatus = 'BAJA' WHERE inscripcion_id = ?")->execute([$inscripcion_id]);
            
            registrar_historial($pdo, $_SESSION['user_id'], 'Estado', 'Altas', 'Rechazo de Alta', $afectado, "Clase: $clase_info • Estado: Pendiente → Rechazada");

            $mensaje = "Solicitud rechazada. Se ha liberado el horario del alumno.";
            $tipo_mensaje = "success";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error al procesar: " . $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// =======================================================
// LÓGICA DE PESTAÑAS Y FILTROS
// =======================================================
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'pendientes';
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

$condiciones = [];
$params = [];

if ($vista === 'historial') {
    $condiciones[] = "sa.estatus != 'PENDIENTE'";
    
    if (!empty($_GET['fecha'])) {
        $condiciones[] = "DATE(sa.fecha_solicitud) = ?";
        $params[] = preg_replace('/[^0-9\-]/', '', $_GET['fecha']);
    }
    
    if (!empty($busqueda)) {
        $condiciones[] = "(u.codigo LIKE ? OR u.nombre LIKE ? OR u.apellido_paterno LIKE ?)";
        $q_like = "%$busqueda%";
        $params[] = $q_like;
        $params[] = $q_like;
        $params[] = $q_like;
    }
    $orden = "sa.fecha_solicitud DESC";
} else {
    $condiciones[] = "sa.estatus = 'PENDIENTE'";
    $orden = "sa.fecha_solicitud ASC";
}

$where_clause = implode(' AND ', $condiciones);

$sql = "SELECT sa.*, u.nombre, u.apellido_paterno, u.codigo, u.correo, 
               m.materia_id, m.clave AS clave_materia, m.nombre AS materia, m.nivel, c.nombre AS ciclo, g.nrc, g.clave_grupo,
               up.nombre AS prof_nombre, up.apellido_paterno AS prof_ap
        FROM solicitudes_altas sa
        JOIN inscripciones i ON sa.inscripcion_id = i.inscripcion_id
        JOIN alumnos a ON i.alumno_id = a.alumno_id
        JOIN usuarios u ON a.usuario_id = u.usuario_id
        JOIN grupos g ON i.nrc = g.nrc
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        LEFT JOIN usuarios up ON g.profesor_id = up.usuario_id
        WHERE $where_clause
        ORDER BY $orden";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getRomanNumeralAlt(int $num): string {
    $map = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X'];
    return $map[$num] ?? (string)$num;
}

function getDiasCompletosAlt(string $dias_patron): string {
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

foreach($solicitudes as &$s) {
    $stmtG = $pdo->prepare("SELECT g.nrc, h.modalidad, h.hora_inicio, h.hora_fin, h.dias_patron, h.aula 
                            FROM grupos g LEFT JOIN horarios h ON g.nrc = h.nrc
                            WHERE g.materia_id = ? AND g.clave_grupo = ?");
    $stmtG->execute([$s['materia_id'], $s['clave_grupo']]);
    $grupos_clase = $stmtG->fetchAll(PDO::FETCH_ASSOC);

    $nrc_presencial = 'NA';
    $nrc_virtual = 'NA';
    $horas_txt = '';

    $hp = null; $hv = null;
    foreach($grupos_clase as $gc) {
        $mod = strtoupper($gc['modalidad']??'');
        if($mod == 'PRESENCIAL') {
            $nrc_presencial = $gc['nrc'];
            $hp = [
                'inicio' => substr($gc['hora_inicio']??'', 0, 5),
                'fin' => substr($gc['hora_fin']??'', 0, 5),
                'dias' => getDiasCompletosAlt($gc['dias_patron']??''),
                'aula' => $gc['aula']??''
            ];
        } elseif($mod == 'VIRTUAL') {
            $nrc_virtual = $gc['nrc'];
            $hv = [
                'inicio' => substr($gc['hora_inicio']??'', 0, 5),
                'fin' => substr($gc['hora_fin']??'', 0, 5),
                'dias' => getDiasCompletosAlt($gc['dias_patron']??''),
                'aula' => $gc['aula']??''
            ];
        } else {
            if($nrc_presencial == 'NA') $nrc_presencial = $gc['nrc'];
        }
    }

    if ($hp) {
        $horas_txt .= "<p style='margin-bottom:8px; font-size:0.85rem;'><i class=\"fas fa-building\" style=\"color:#ffc107; width:20px;\"></i> {$hp['dias']} • {$hp['inicio']} - {$hp['fin']} <br><i class=\"fas fa-map-marker-alt\" style=\"color:#ffc107; width:20px;\"></i> {$hp['aula']}</p>";
    } else {
        $horas_txt .= "<p style='margin-bottom:8px; color:#888; font-size:0.85rem;'><i class=\"fas fa-building\" style=\"color:#ffc107; width:20px;\"></i> NA</p>";
    }
    if ($hv) {
        $horas_txt .= "<p style='font-size:0.85rem;'><i class=\"fas fa-laptop\" style=\"color:#17a2b8; width:20px;\"></i> {$hv['dias']} • {$hv['inicio']} - {$hv['fin']} <br><i class=\"fas fa-video\" style=\"color:#17a2b8; width:20px;\"></i> {$hv['aula']}</p>";
    } else {
        $horas_txt .= "<p style='color:#888; font-size:0.85rem;'><i class=\"fas fa-laptop\" style=\"color:#17a2b8; width:20px;\"></i> NA <br><i class=\"fas fa-video\" style=\"color:#17a2b8; width:20px;\"></i> NA</p>";
    }

    $romano = getRomanNumeralAlt($s['nivel']);
    $materia_html = "
        <div style=\"font-weight: bold; color: #333;\">{$s['materia']} {$romano}</div>
        <div style=\"font-size: 0.85rem; color: #666; margin-top:2px;\">Clave: {$s['clave_materia']} | Ciclo: {$s['ciclo']}</div>
        <div style=\"font-size: 0.85rem; color: #555; margin-top:2px; font-weight:bold;\">
            <span style=\"color:#ffc107;\"><i class=\"fas fa-building\"></i> {$nrc_presencial}</span> - 
            <span style=\"color:#17a2b8;\"><i class=\"fas fa-laptop\"></i> {$nrc_virtual}</span>
        </div>
        <div style=\"font-size: 0.85rem; color: #555; margin-top:6px; font-weight:bold;\"><i class=\"fas fa-chalkboard-teacher\"></i> Prof. {$s['prof_nombre']} {$s['prof_ap']}</div>
        <div style=\"margin-top:6px;\">{$horas_txt}</div>
    ";
    
    $s['materia_html'] = $materia_html;
    $s['nrc_presencial'] = $nrc_presencial;
    $s['nrc_virtual'] = $nrc_virtual;
    $s['nivel_romano'] = $romano;
}

$total_pendientes = $pdo->query("SELECT COUNT(*) FROM solicitudes_altas WHERE estatus = 'PENDIENTE'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Alta | Admin</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal2-container {
            z-index: 999999 !important;
        }
    </style>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

        <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 20px; position: relative;">
            <h1><i class="fas fa-user-check"></i> Solicitudes de Alta</h1>
            <p>Administra las peticiones de los alumnos para ingresar a nuevas materias o consulta el archivo histórico.</p>

            <div style="position: absolute; right: 0; top: 0; background: #fff; padding: 10px 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 10px; border: 1px solid #eee;">
                <span style="font-weight: bold; color: var(--udg-blue); font-size: 0.9rem;">Habilitar Altas</span>
                <label class="switch switch-on-off">
                    <input type="checkbox" id="toggleAltas" onclick="confirmarToggleAltas(event, this)" <?php echo $altas_habilitadas ? 'checked' : ''; ?>>
                    <span class="slider round slider-on-off"></span>
                </label>
            </div>
        </div>

        <div class="tabs-container">
            <a href="?vista=pendientes" class="btn-tab <?php echo $vista == 'pendientes' ? 'active' : ''; ?>">
                <i class="fas fa-inbox"></i> Bandeja Pendientes
                <?php if ($total_pendientes > 0): ?><span class="badge-tab"><?php echo $total_pendientes; ?></span><?php endif; ?>
            </a>
            <a href="?vista=historial" class="btn-tab <?php echo $vista == 'historial' ? 'active' : ''; ?>">
                <i class="fas fa-archive"></i> Historial Completo
            </a>
        </div>

        <?php if ($vista == 'historial'): ?>
            <form method="GET" action="" class="toolbar" style="margin-bottom: 20px; justify-content: flex-end;">
                <input type="hidden" name="vista" value="historial">
                
                <i class="fas fa-search icon-muted" style="align-self:center;"></i>
                <input type="text" name="q" class="search-input" placeholder="Buscar código o nombre..." value="<?php echo htmlspecialchars($busqueda); ?>" style="width: 250px;">
                
                <input type="date" id="fecha" name="fecha" value="<?php echo htmlspecialchars($_GET['fecha'] ?? ''); ?>" class="filter-select" style="max-width: 150px; cursor: pointer;" title="Filtrar por fecha" onchange="this.form.submit()">
                
                <button type="submit" style="background: var(--udg-blue); color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 0.95rem; transition: 0.2s;">
                    Buscar
                </button>

                <?php if(!empty($busqueda) || !empty($_GET['fecha'])): ?>
                    <a href="?vista=historial" style="padding: 8px 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 0.95rem; transition: 0.2s;" title="Limpiar Filtros">
                        Limpiar
                    </a>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-wrapper" style="overflow-x:auto;">
                <table class="history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Fecha Solicitud</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Estudiante</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Materia</th>

                            <?php if ($vista == 'historial'): ?>
                                <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Fecha Resolución</th>
                                <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Resuelto Por</th>
                                <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Estatus Final</th>
                            <?php endif; ?>
                            
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
                                        <div style="font-size: 0.8rem; color: #888;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($s['correo'] ?? ''); ?></div>
                                    </td>
                                    <td style="padding: 15px;">
                                        <div style="font-weight: bold; color: #333;"><?php echo htmlspecialchars($s['materia']) . ' ' . $s['nivel_romano']; ?></div>
                                        <div style="font-size: 0.8rem; color: #888; margin-top:2px;">Clave: <?php echo htmlspecialchars($s['clave_materia'] ?? $s['clave_grupo']); ?> | <?php echo htmlspecialchars($s['ciclo']); ?></div>
                                        <div style="font-size: 0.8rem; color: #555; margin-top:2px; font-weight:bold;">
                                            <span style="color:#ffc107;"><i class="fas fa-building"></i> <?php echo $s['nrc_presencial']; ?></span> - 
                                            <span style="color:#17a2b8;"><i class="fas fa-laptop"></i> <?php echo $s['nrc_virtual']; ?></span>
                                        </div>
                                    </td>

                                    <?php if ($vista == 'historial'): ?>
                                        <td style="padding: 15px; color: #666; font-size: 0.9rem;">
                                            <?php if (!empty($s['fecha_respuesta'])): ?>
                                                <?php echo date('d/m/Y', strtotime($s['fecha_respuesta'])); ?><br>
                                                <small><?php echo date('H:i', strtotime($s['fecha_respuesta'])); ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 15px; font-size: 0.85rem; font-weight: bold;">
                                            <?php if (strpos($s['estatus'], 'CANCELADA') !== false): ?>
                                                <span style="color: #6c757d;"><i class="fas fa-user-graduate"></i> Estudiante</span>
                                            <?php else: ?>
                                                <span style="color: var(--udg-blue);"><i class="fas fa-user-shield"></i> Administrador</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

                                    <?php if ($vista == 'historial'): ?>
                                    <td style="padding: 15px; text-align: center;">
                                            <?php
                                            if ($s['estatus'] == 'APROBADA') echo '<span class="tag-aprobada" style="background:#d4edda; color:#155724; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;"><i class="fas fa-check-circle"></i> Aprobada</span>';
                                            elseif ($s['estatus'] == 'RECHAZADA') echo '<span class="tag-rechazada" style="background:#f8d7da; color:#721c24; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;"><i class="fas fa-times-circle"></i> Rechazada</span>';
                                            elseif (strpos($s['estatus'], 'CANCELADA') !== false) echo '<span style="background:#e2e3e5; color:#383d41; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;"><i class="fas fa-ban"></i> Cancelada</span>';
                                            ?>
                                    </td>
                                    <?php endif; ?>

                                    <td style="padding: 15px; text-align: center;">
                                        <button onclick='abrirModal(<?php echo json_encode([
                                            "id" => $s["solicitud_id"],
                                            "insc" => $s["inscripcion_id"],
                                            "nombre" => $s["nombre"] . " " . $s["apellido_paterno"],
                                            "codigo" => $s["codigo"],
                                            "correo" => $s["correo"],
                                            "materia" => $s["materia_html"],
                                            "motivo" => $s["motivo"],
                                            "img" => "../img/horarios/" . $s["horario_externo"],
                                            "estatus" => $s["estatus"],
                                            "respuesta" => $s["respuesta_admin"],
                                            "fecha_solicitud" => date('d/m/Y \a \l\a\s H:i', strtotime($s["fecha_solicitud"])),
                                            "fecha_respuesta" => $s["fecha_respuesta"] ? date('d/m/Y \a \l\a\s H:i', strtotime($s["fecha_respuesta"])) : ''
                                        ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)' style="background: <?php echo ($vista == 'pendientes') ? 'var(--udg-blue)' : '#f1f3f5'; ?>; color: <?php echo ($vista == 'pendientes') ? 'white' : '#555'; ?>; border: <?php echo ($vista == 'pendientes') ? 'none' : '1px solid #ccc'; ?>; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold;">
                                            <?php if ($vista == 'pendientes'): ?>
                                                <i class="fas fa-edit"></i> Evaluar
                                            <?php else: ?>
                                                <i class="fas fa-eye"></i> Detalles
                                            <?php endif; ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo ($vista == 'historial') ? '7' : '4'; ?>" style="text-align: center; padding: 50px 20px; color: #888;">
                                    <?php if ($vista == 'pendientes'): ?>
                                        <i class="fas fa-check-double" style="font-size: 3rem; color: #ddd; margin-bottom: 15px; display: block;"></i>
                                        No hay solicitudes de alta pendientes.
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

    <!-- Modal Revisión Sofisticado -->
    <div id="modalReview" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0; color:var(--udg-blue);" id="modalTitle"><i class="fas fa-clipboard-check"></i> Detalles de la Solicitud de Alta</h3>
                <button style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="cerrarReview()">&times;</button>
            </div>

            <form method="POST" id="formResolucion" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" id="mAction" value="">
                <input type="hidden" name="solicitud_id" id="mSolicitudId">
                <input type="hidden" name="inscripcion_id" id="mInscripcionId">

                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="sol-box" style="border-left: 4px solid var(--udg-blue);">
                            <h4>Estudiante</h4>
                            <div style="font-size:1rem; color:var(--udg-blue); font-weight:bold;" id="mNombre"></div>
                        </div>
                        <div class="sol-box" style="border-left: 4px solid var(--udg-blue);">
                            <h4>Materia Solicitada</h4>
                            <div id="mMateria" style="color:#333; font-size: 0.95rem;"></div>
                        </div>
                    </div>

                    <div class="sol-box" style="background: #f8f9fa; border-color: #ddd; border-left: 4px solid #6f42c1; margin-bottom: 15px;">
                        <h4>Motivo expresado por el estudiante</h4>
                        <div id="mMotivo" style="color:#555; font-size:0.9rem;"></div>
                    </div>

                    <div class="sol-box" style="background: #fff9e6; border-color: #ffeeba; border-left: 4px solid #ffc107; margin-bottom: 15px;">
                        <h4>Horario Externo (Comprobante)</h4>
                        <img id="mImg" onclick="ampliarImagen(this.src)" style="max-width: 100%; max-height: 200px; border-radius: 6px; border: 1px solid #ddd; display: block; margin-top: 5px; cursor: pointer; transition: 0.2s;" src="" alt="Horario externo" onmouseover="this.style.opacity=0.8" onmouseout="this.style.opacity=1">
                        <p style="font-size:0.75rem; color:#888; margin-top: 5px;"><i class="fas fa-search-plus"></i> Clic en la imagen para abrir en tamaño completo.</p>
                    </div>

                    <div id="admin_input_area" class="sol-box" style="background: #e2f0d9; border-color: #c3e6cb; border-left: 4px solid #28a745; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <h4 style="margin: 0;">Comentario (Opcional)</h4>
                            <button type="button" onclick="togglePanelRespuestas()" style="background:#f3e8ff; color:#6f42c1; border:1px solid #6f42c1; padding:4px 8px; border-radius:4px; font-size:0.8rem; cursor:pointer; font-weight:bold; transition:0.2s;" onmouseover="this.style.background='#e2d6f8'" onmouseout="this.style.background='#f3e8ff'"><i class="fas fa-bolt"></i> Automatizado</button>
                        </div>
                        <textarea name="respuesta_admin" id="mComentarios" rows="2" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box;" placeholder="Si no agregas un comentario, se usará uno predeterminado."></textarea>
                        
                        <div id="panelAutomatizado" style="display: none; background: #fff; border: 1px solid #ccc; border-radius: 6px; padding: 10px; margin-top: 10px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span style="font-weight: bold; font-size: 0.85rem; color: #555;"><i class="fas fa-list-ul"></i> Respuestas Guardadas</span>
                                <button type="button" onclick="mostrarFormNuevaRespuesta()" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; cursor: pointer; transition: 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'"><i class="fas fa-plus"></i> Nueva</button>
                            </div>
                            
                            <div id="formNuevaRespuesta" style="display: none; margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px; border: 1px dashed #ccc;">
                                <input type="text" id="nuevaRespTitulo" placeholder="Título (ej. Falta de cupo)" style="width: 100%; padding: 6px; margin-bottom: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.85rem; box-sizing:border-box;">
                                <textarea id="nuevaRespCuerpo" rows="2" placeholder="Cuerpo de la respuesta..." style="width: 100%; padding: 6px; margin-bottom: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.85rem; box-sizing:border-box;"></textarea>
                                <div style="text-align: right;">
                                    <button type="button" onclick="ocultarFormNuevaRespuesta()" style="background: none; border: none; color: #888; cursor: pointer; font-size: 0.8rem; margin-right: 10px; padding: 4px;">Cancelar</button>
                                    <button type="button" onclick="guardarNuevaRespuesta()" style="background: #007bff; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 0.8rem; cursor: pointer;"><i class="fas fa-save"></i> Guardar</button>
                                </div>
                            </div>

                            <div id="listaRespuestas" style="max-height: 150px; overflow-y: auto; display: flex; flex-direction: column; gap: 5px;">
                            </div>
                        </div>
                    </div>

                    <div id="admin_response_area" style="display:none; border-top: 2px dashed #ddd; padding-top: 15px; margin-top: 15px;">
                        <h4 id="resp_title" style="margin: 0 0 10px 0; color: #333; text-transform: uppercase; font-size: 0.9rem;">RESOLUCIÓN</h4>
                        <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 10px;">
                            <div id="resp_estatus"></div>
                            <div id="resp_fecha_solicitud" style="font-size:0.85rem; color:#888;"></div>
                        </div>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; border-left: 4px solid #ccc; color: #555; font-size: 0.95rem; margin-bottom: 10px;" id="mRespuesta"></div>
                        <div id="resp_fecha_resolucion" style="font-size:0.85rem; color:#333; font-weight:bold;"></div>
                    </div>
                </div>

                <div class="modal-footer" id="action_footer" style="justify-content: space-between;">
                    <button type="button" style="padding:10px 15px; background:#fff; border:1px solid #dc3545; color:#dc3545; border-radius:6px; cursor:pointer; font-weight:bold;" onclick="submitResolucion('rechazar')"><i class="fas fa-times"></i> Rechazar Petición</button>
                    <button type="button" style="padding:10px 15px; background:#28a745; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;" onclick="submitResolucion('aprobar')"><i class="fas fa-check"></i> Aprobar Alta</button>
                </div>

                <div class="modal-footer" id="close_footer" style="display:none; justify-content: flex-end;">
                    <button type="button" style="padding:8px 15px; background:#dc3545; color:white; border:none; border-radius:6px; cursor:pointer;" onclick="cerrarReview()">Cerrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Image Lightbox Modal -->
    <div id="imageLightbox" onclick="this.style.display='none'" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:99999; justify-content:center; align-items:center; cursor:pointer;">
        <img id="lightboxImg" style="max-width:90%; max-height:90%; border-radius:8px; border:2px solid white;" src="">
    </div>

    <script>
        <?php if ($mensaje): ?>
            Swal.fire({
                icon: '<?php echo $tipo_mensaje; ?>',
                title: 'Atención',
                text: '<?php echo addslashes($mensaje); ?>'
            });
        <?php endif; ?>

        function abrirModal(data) {
            document.getElementById('mSolicitudId').value = data.id;
            document.getElementById('mInscripcionId').value = data.insc;
            document.getElementById('mNombre').innerHTML = data.nombre + '<br><span style="font-size:0.85rem; color:#666; font-family:monospace; font-weight:normal;">Código: ' + data.codigo + '</span><br><span style="font-size:0.85rem; color:#666; font-weight:normal;"><i class="fas fa-envelope"></i> ' + data.correo + '</span>';
            document.getElementById('mMateria').innerHTML = data.materia;
            document.getElementById('mMotivo').innerText = data.motivo;
            document.getElementById('mImg').src = data.img;

            if (data.estatus !== 'PENDIENTE') {
                document.getElementById('admin_input_area').style.display = 'none';
                document.getElementById('action_footer').style.display = 'none';
                document.getElementById('admin_response_area').style.display = 'block';
                document.getElementById('close_footer').style.display = 'flex';
                
                let badge = '';
                if(data.estatus === 'APROBADA') {
                    badge = '<span style="background:#d4edda; color:#155724; padding:4px 10px; border-radius:12px; font-weight:bold; font-size:0.8rem;"><i class="fas fa-check-circle"></i> Aprobada</span>';
                    document.getElementById('resp_title').innerText = 'RESOLUCIÓN DEL ADMINISTRADOR';
                    document.getElementById('mRespuesta').innerText = data.respuesta || 'Sin comentarios adicionales.';
                } else if(data.estatus === 'RECHAZADA') {
                    badge = '<span style="background:#f8d7da; color:#721c24; padding:4px 10px; border-radius:12px; font-weight:bold; font-size:0.8rem;"><i class="fas fa-times-circle"></i> Rechazada</span>';
                    document.getElementById('resp_title').innerText = 'RESOLUCIÓN DEL ADMINISTRADOR';
                    document.getElementById('mRespuesta').innerText = data.respuesta || 'Sin comentarios adicionales.';
                } else {
                    badge = '<span style="background:#e2e3e5; color:#383d41; padding:4px 10px; border-radius:12px; font-weight:bold; font-size:0.8rem;"><i class="fas fa-ban"></i> Cancelada</span>';
                    document.getElementById('resp_title').innerText = 'RESOLUCIÓN DEL ESTUDIANTE';
                    document.getElementById('mRespuesta').innerText = 'El estudiante canceló la solicitud.';
                }
                
                document.getElementById('resp_estatus').innerHTML = badge;
                document.getElementById('resp_fecha_solicitud').innerHTML = '<i class="far fa-calendar-alt"></i> Fecha Solicitud: ' + data.fecha_solicitud;
                if (data.fecha_respuesta) {
                    document.getElementById('resp_fecha_resolucion').innerHTML = '<i class="far fa-clock"></i> Fecha de Resolución: ' + data.fecha_respuesta;
                } else {
                    document.getElementById('resp_fecha_resolucion').innerHTML = '';
                }
            } else {
                document.getElementById('admin_input_area').style.display = 'block';
                document.getElementById('action_footer').style.display = 'flex';
                document.getElementById('admin_response_area').style.display = 'none';
                document.getElementById('close_footer').style.display = 'none';
                document.getElementById('mComentarios').value = '';
            }

            document.getElementById('modalReview').style.display = 'flex';
        }

        function cerrarReview() {
            document.getElementById('modalReview').style.display = 'none';
            document.getElementById('panelAutomatizado').style.display = 'none';
            ocultarFormNuevaRespuesta();
        }

        function ampliarImagen(src) {
            document.getElementById('lightboxImg').src = src;
            document.getElementById('imageLightbox').style.display = 'flex';
        }

        // ==========================
        // Respuestas Automatizadas
        // ==========================
        function togglePanelRespuestas() {
            const panel = document.getElementById('panelAutomatizado');
            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                cargarRespuestas();
            } else {
                panel.style.display = 'none';
                ocultarFormNuevaRespuesta();
            }
        }

        function mostrarFormNuevaRespuesta() {
            document.getElementById('formNuevaRespuesta').style.display = 'block';
        }

        function ocultarFormNuevaRespuesta() {
            document.getElementById('formNuevaRespuesta').style.display = 'none';
            document.getElementById('nuevaRespTitulo').value = '';
            document.getElementById('nuevaRespCuerpo').value = '';
        }

        function cargarRespuestas() {
            fetch('api_respuestas.php')
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const container = document.getElementById('listaRespuestas');
                    container.innerHTML = '';
                    if(data.data.length === 0) {
                        container.innerHTML = '<span style="font-size:0.8rem; color:#888;">No hay respuestas guardadas.</span>';
                        return;
                    }
                    data.data.forEach(r => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.style.textAlign = 'left';
                        btn.style.background = '#f8f9fa';
                        btn.style.border = '1px solid #ddd';
                        btn.style.padding = '8px 10px';
                        btn.style.borderRadius = '4px';
                        btn.style.cursor = 'pointer';
                        btn.style.fontSize = '0.85rem';
                        btn.style.color = '#333';
                        btn.style.transition = '0.2s';
                        
                        btn.onmouseover = () => btn.style.background = '#e9ecef';
                        btn.onmouseout = () => btn.style.background = '#f8f9fa';
                        
                        btn.innerHTML = `<i class="fas fa-comment-dots" style="color:#007bff;"></i> <strong style="margin-left:5px;">${r.titulo}</strong>`;
                        btn.onclick = () => {
                            document.getElementById('mComentarios').value = r.cuerpo;
                            document.getElementById('panelAutomatizado').style.display = 'none';
                        };
                        container.appendChild(btn);
                    });
                }
            })
            .catch(err => console.error(err));
        }

        function guardarNuevaRespuesta() {
            const titulo = document.getElementById('nuevaRespTitulo').value.trim();
            const cuerpo = document.getElementById('nuevaRespCuerpo').value.trim();
            if (!titulo || !cuerpo) {
                Swal.fire('Atención', 'Debes ingresar un título y un cuerpo para la respuesta.', 'warning');
                return;
            }
            const formData = new FormData();
            formData.append('action', 'save');
            formData.append('titulo', titulo);
            formData.append('cuerpo', cuerpo);

            fetch('api_respuestas.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    ocultarFormNuevaRespuesta();
                    cargarRespuestas();
                } else {
                    Swal.fire('Error', data.error || 'Ocurrió un error al guardar.', 'error');
                }
            })
            .catch(err => console.error(err));
        }

        function submitResolucion(action) {
            const btnText = action === 'aprobar' ? 'Aprobar Alta' : 'Rechazar Alta';
            const color = action === 'aprobar' ? '#28a745' : '#dc3545';
            
            Swal.fire({
                title: '¿Confirmar Resolución?',
                text: "Estás a punto de " + (action === 'aprobar' ? "APROBAR" : "RECHAZAR") + " esta solicitud. Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: color,
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, ' + btnText
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('mAction').value = action;
                    document.getElementById('formResolucion').submit();
                }
            });
        }
        function confirmarToggleAltas(event, checkbox) {
            const proposedState = checkbox.checked;
            event.preventDefault();

            const actionText = proposedState ? 'habilitar' : 'deshabilitar';

            Swal.fire({
                title: `¿Confirmas ${actionText} las altas?`,
                text: proposedState ? "Los alumnos podrán solicitar altas de materias desde su panel." : "Se deshabilitará la opción de solicitar altas para todos los alumnos.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: proposedState ? '#28a745' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Sí, ${actionText}`,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    checkbox.checked = proposedState;
                    ejecutarToggleAltas(proposedState);
                }
            });
        }

        function ejecutarToggleAltas(habilitadas) {
            fetch('toggle_altas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        habilitadas: habilitadas
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: habilitadas ? 'Altas habilitadas' : 'Altas deshabilitadas',
                            text: 'La configuración ha sido actualizada correctamente.',
                            timer: 2500,
                            showConfirmButton: false
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'No se pudo actualizar la configuración.', 'error');
                });
        }
    </script>
</body>
</html>
