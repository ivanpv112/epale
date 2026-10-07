<?php
session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index");
    exit;
}

// 2. ESCUDO CSRF: Bloquear peticiones de origen cruzado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Error de Seguridad Crítico: Token CSRF inválido o ausente. Petición bloqueada.");
    }
}

$mensaje = '';
$tipo_mensaje = '';

// =======================================================
// PROCESAR APROBACIÓN / RECHAZO
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $solicitud_id = $_POST['solicitud_id'];
    $inscripcion_id = $_POST['inscripcion_id'];
    $respuesta = strip_tags(trim($_POST['respuesta_admin']));
    if (empty($respuesta)) {
        $respuesta = ($_POST['action'] === 'aprobar') ? 'Su solicitud de baja ha sido aprobada.' : 'Su solicitud de baja ha sido rechazada.';
    }

    try {
        $pdo->beginTransaction();

        // Obtener datos para el historial
        $stmtInfo = $pdo->prepare("
            SELECT u.nombre, u.apellido_paterno, u.codigo, m.nombre AS materia, g.nrc 
            FROM solicitudes_bajas sb 
            JOIN inscripciones i ON sb.inscripcion_id = i.inscripcion_id 
            JOIN alumnos a ON i.alumno_id = a.alumno_id 
            JOIN usuarios u ON a.usuario_id = u.usuario_id 
            JOIN grupos g ON i.nrc = g.nrc 
            JOIN materias m ON g.materia_id = m.materia_id 
            WHERE sb.solicitud_id = ?
        ");
        $stmtInfo->execute([$solicitud_id]);
        $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);
        $afectado = $info ? $info['nombre'] . ' ' . $info['apellido_paterno'] . ' (' . $info['codigo'] . ')' : 'Desconocido';
        $clase_info = $info ? $info['materia'] . " (NRC: " . $info['nrc'] . ")" : 'Clase Desconocida';

        if ($_POST['action'] === 'aprobar') {
            $pdo->prepare("UPDATE solicitudes_bajas SET estatus = 'APROBADA', respuesta_admin = ?, fecha_respuesta = NOW() WHERE solicitud_id = ?")->execute([$respuesta, $solicitud_id]);
            // Solo cambiamos el estatus a BAJA, ya NO borramos las calificaciones del Kárdex
            $pdo->prepare("UPDATE inscripciones SET estatus = 'BAJA' WHERE inscripcion_id = ?")->execute([$inscripcion_id]);

            registrar_historial($pdo, $_SESSION['user_id'], 'Estado', 'Solicitudes', 'Aprobación de Baja', $afectado, "Clase: $clase_info • Estado: Pendiente → Aprobada");

            $mensaje = "Solicitud aprobada: El alumno ha sido dado de baja, pero sus calificaciones se conservan en el Kárdex.";
            $tipo_mensaje = "success";
        } elseif ($_POST['action'] === 'rechazar') {
            $pdo->prepare("UPDATE solicitudes_bajas SET estatus = 'RECHAZADA', respuesta_admin = ?, fecha_respuesta = NOW() WHERE solicitud_id = ?")->execute([$respuesta, $solicitud_id]);

            registrar_historial($pdo, $_SESSION['user_id'], 'Estado', 'Solicitudes', 'Rechazo de Baja', $afectado, "Clase: $clase_info • Estado: Pendiente → Rechazada");

            $mensaje = "Solicitud rechazada. El alumno permanece en la clase.";
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
// LÓGICA DE PESTAÑAS Y FILTROS (PENDIENTES VS HISTORIAL)
// =======================================================
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'pendientes';
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

$condiciones = [];
$params = [];

if ($vista === 'historial') {
    $condiciones[] = "sb.estatus != 'PENDIENTE'";

    if (!empty($_GET['fecha'])) {
        $condiciones[] = "DATE(sb.fecha_solicitud) = ?";
        $params[] = preg_replace('/[^0-9\-]/', '', $_GET['fecha']);
    }

    if (!empty($busqueda)) {
        $condiciones[] = "(u.codigo LIKE ? OR u.nombre LIKE ? OR u.apellido_paterno LIKE ?)";
        $q_like = "%$busqueda%";
        $params[] = $q_like;
        $params[] = $q_like;
        $params[] = $q_like;
    }
    $orden = "sb.fecha_solicitud DESC";
} else {
    $condiciones[] = "sb.estatus = 'PENDIENTE'";
    $orden = "sb.fecha_solicitud ASC";
}

$where_clause = implode(' AND ', $condiciones);

$sql = "SELECT sb.*, u.nombre, u.apellido_paterno, u.codigo, u.correo, 
               m.materia_id, m.nombre AS materia, m.nivel, m.clave AS clave_materia, c.nombre AS ciclo, g.nrc, g.clave_grupo
        FROM solicitudes_bajas sb
        JOIN inscripciones i ON sb.inscripcion_id = i.inscripcion_id
        JOIN alumnos a ON i.alumno_id = a.alumno_id
        JOIN usuarios u ON a.usuario_id = u.usuario_id
        JOIN grupos g ON i.nrc = g.nrc
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        WHERE $where_clause
        ORDER BY $orden";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getRomanNumeralBajas(int $num): string
{
    $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];
    return $map[$num] ?? (string)$num;
}

foreach ($solicitudes as &$s) {
    $stmtG = $pdo->prepare("SELECT g.nrc, h.modalidad 
                            FROM grupos g LEFT JOIN horarios h ON g.nrc = h.nrc
                            WHERE g.materia_id = ? AND g.clave_grupo = ?");
    $stmtG->execute([$s['materia_id'], $s['clave_grupo']]);
    $grupos_clase = $stmtG->fetchAll(PDO::FETCH_ASSOC);

    $nrc_presencial = 'NA';
    $nrc_virtual = 'NA';

    foreach ($grupos_clase as $gc) {
        $mod = strtoupper($gc['modalidad'] ?? '');
        if ($mod == 'PRESENCIAL') {
            $nrc_presencial = $gc['nrc'];
        } elseif ($mod == 'VIRTUAL') {
            $nrc_virtual = $gc['nrc'];
        } else {
            if ($nrc_presencial == 'NA') $nrc_presencial = $gc['nrc'];
        }
    }

    $s['nrc_presencial'] = $nrc_presencial;
    $s['nrc_virtual'] = $nrc_virtual;
    $s['nivel_romano'] = getRomanNumeralBajas($s['nivel']);
}

// Contar pendientes para la pestaña
$total_pendientes = $pdo->query("SELECT COUNT(*) FROM solicitudes_bajas WHERE estatus = 'PENDIENTE'")->fetchColumn();

// Leer configuración de bajas
$config_file = '../config.json';
$config = file_exists($config_file) ? json_decode(file_get_contents($config_file), true) : ['bajas_habilitadas' => true];
$bajas_habilitadas = $config['bajas_habilitadas'] ?? true;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Baja | Admin</title>
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
            <h1><i class="fas fa-user-xmark"></i> Solicitudes de Baja</h1>
            <p>Administra las peticiones de los alumnos o consulta el archivo histórico.</p>

            <div style="position: absolute; right: 0; top: 0; background: #fff; padding: 10px 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 10px; border: 1px solid #eee;">
                <span style="font-weight: bold; color: var(--udg-blue); font-size: 0.9rem;">Habilitar Bajas</span>
                <label class="switch switch-on-off">
                    <input type="checkbox" id="toggleBajas" onclick="confirmarToggleBajas(event, this)" <?php echo $bajas_habilitadas ? 'checked' : ''; ?>>
                    <span class="slider round slider-on-off"></span>
                </label>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div style="padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; background: <?php echo ($tipo_mensaje == 'success') ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo ($tipo_mensaje == 'success') ? '#155724' : '#721c24'; ?>;">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

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

                <?php if (!empty($busqueda) || !empty($_GET['fecha'])): ?>
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
                            <?php endif; ?>

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
                                        <div style="font-size: 0.8rem; color: #888;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($s['correo']); ?></div>
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
                                            <?php
                                            if ($s['estatus'] == 'CANCELADA') {
                                                echo '<span style="color: #6c757d;"><i class="fas fa-user-graduate"></i> Estudiante</span>';
                                            } else {
                                                echo '<span style="color: var(--udg-blue);"><i class="fas fa-user-shield"></i> Administrador</span>';
                                            }
                                            ?>
                                        </td>
                                    <?php endif; ?>

                                    <td style="padding: 15px; text-align: center;">
                                        <?php if ($vista == 'pendientes'): ?>
                                            <span style="background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;"><?php echo htmlspecialchars($s['motivo']); ?></span>
                                        <?php else: ?>
                                            <?php
                                            if ($s['estatus'] == 'APROBADA') echo '<span class="tag-aprobada"><i class="fas fa-check-circle"></i> Aprobada</span>';
                                            elseif ($s['estatus'] == 'RECHAZADA') echo '<span class="tag-rechazada"><i class="fas fa-times-circle"></i> Rechazada</span>';
                                            elseif ($s['estatus'] == 'CANCELADA') echo '<span class="tag-cancelada"><i class="fas fa-ban"></i> Cancelada</span>';
                                            ?>
                                        <?php endif; ?>
                                    </td>

                                    <td style="padding: 15px; text-align: center;">
                                        <?php if ($vista == 'pendientes'): ?>
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
                                <td colspan="<?php echo ($vista == 'historial') ? '7' : '5'; ?>" style="text-align: center; padding: 50px 20px; color: #888;">
                                    <?php if ($vista == 'pendientes'): ?>
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

    <div id="modalReview" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0; color:var(--udg-blue);" id="modalTitle"><i class="fas fa-clipboard-list"></i> Detalles de la Solicitud</h3>
                <button style="background:none; border:none; font-size:1.5rem; cursor:pointer;" onclick="cerrarReview()">&times;</button>
            </div>

            <form method="POST" id="formReview" style="margin:0;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" id="actionType" value="">
                <input type="hidden" name="solicitud_id" id="sol_id">
                <input type="hidden" name="inscripcion_id" id="insc_id">

                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="sol-box" style="border-left: 4px solid var(--udg-blue);">
                            <h4>Estudiante</h4>
                            <div style="font-size:1rem; color:var(--udg-blue); font-weight:bold;" id="txt_alumno"></div>
                        </div>
                        <div class="sol-box" style="border-left: 4px solid var(--udg-blue);">
                            <h4>Clase afectada</h4>
                            <div id="txt_clase" style="font-weight:bold; color:#333; font-size: 0.95rem;"></div>
                        </div>
                    </div>

                    <div class="sol-box" style="background: #fff8f8; border-color: #f5c6cb; border-left: 4px solid #dc3545;">
                        <h4>Motivo expresado por el estudiante</h4>
                        <div id="txt_motivo" style="font-weight:bold; color:#dc3545; margin-bottom: 5px;"></div>
                        <div id="txt_desc"></div>
                    </div>

                    <div id="admin_input_area" class="sol-box" style="background: #e2f0d9; border-color: #c3e6cb; border-left: 4px solid #28a745; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <h4 style="margin: 0;">Nota / Respuesta (Opcional)</h4>
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

                        <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 6px; font-size: 0.85rem; margin-top: 15px;">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Atención:</strong> Si apruebas esta solicitud, el alumno será dado de baja de la clase, pero sus calificaciones actuales se conservarán en su Kárdex para temas de auditoría.
                        </div>
                    </div>

                    <div id="admin_response_area" style="display:none; border-top: 2px dashed #ddd; padding-top: 15px; margin-top: 15px;">
                        <h4 id="resp_title" style="margin: 0 0 10px 0; color: #333; text-transform: uppercase; font-size: 0.9rem;">Resolución</h4>
                        <div style="display: flex; gap: 20px; align-items: center; margin-bottom: 10px;">
                            <div id="resp_estatus"></div>
                            <div style="font-size: 0.85rem; color: #888;"><i class="far fa-calendar-alt"></i> Fecha Solicitud: <span id="solicitud_fecha"></span></div>
                        </div>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; border-left: 4px solid #ccc; color: #555; font-size: 0.95rem; margin-bottom: 10px;" id="resp_texto"></div>
                        <div style="font-size: 0.85rem; color: #555; font-weight: bold;"><i class="far fa-clock"></i> Fecha de Resolución: <span id="resp_fecha"></span></div>
                    </div>
                </div>

                <div class="modal-footer" id="action_footer" style="justify-content: space-between;">
                    <button type="button" style="padding:10px 15px; background:#fff; border:1px solid #dc3545; color:#dc3545; border-radius:6px; cursor:pointer; font-weight:bold;" onclick="procesar('rechazar')"><i class="fas fa-times"></i> Rechazar Petición</button>
                    <button type="button" style="padding:10px 15px; background:#28a745; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;" onclick="procesar('aprobar')"><i class="fas fa-check"></i> Aprobar Baja</button>
                </div>

                <div class="modal-footer" id="close_footer" style="display:none;">
                    <button type="button" style="padding:10px 25px; background:#dc3545; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;" onclick="cerrarReview()">Cerrar</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }

        function confirmarToggleBajas(event, checkbox) {
            // Guardamos el estado que el usuario intentó poner
            const proposedState = checkbox.checked;

            // Prevenimos el cambio visual inmediato
            event.preventDefault();

            const actionText = proposedState ? 'habilitar' : 'deshabilitar';

            Swal.fire({
                title: `¿Confirmas ${actionText} las bajas?`,
                text: proposedState ? "Los alumnos podrán solicitar bajas de materias desde su panel." : "Se deshabilitará la opción de solicitar bajas para todos los alumnos.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: proposedState ? '#28a745' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Sí, ${actionText}`,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    checkbox.checked = proposedState; // Aplicamos el cambio visual
                    ejecutarToggleBajas(proposedState);
                }
            });
        }

        function ejecutarToggleBajas(habilitadas) {
            fetch('toggle_bajas.php', {
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
                            title: habilitadas ? 'Bajas habilitadas' : 'Bajas deshabilitadas',
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


        function abrirReview(solicitud) {
            document.getElementById('sol_id').value = solicitud.solicitud_id;
            document.getElementById('insc_id').value = solicitud.inscripcion_id;

            document.getElementById('txt_alumno').innerHTML = solicitud.nombre + ' ' + solicitud.apellido_paterno + '<br><span style="font-size:0.85rem; color:#666; font-family:monospace;">Código: ' + solicitud.codigo + '</span><br><span style="font-size:0.85rem; color:#666;"><i class="fas fa-envelope"></i> ' + solicitud.correo + '</span>';
            document.getElementById('txt_clase').innerHTML = `
                <div style="font-weight: bold; color: #333;">${solicitud.materia} ${solicitud.nivel_romano}</div>
                <div style="font-size: 0.85rem; color: #666; margin-top:2px;">Clave: ${solicitud.clave_materia || solicitud.clave_grupo || solicitud.clave} | Ciclo: ${solicitud.ciclo}</div>
                <div style="font-size: 0.85rem; color: #555; margin-top:2px; font-weight:bold;">
                    <span style="color:#ffc107;"><i class="fas fa-building"></i> ${solicitud.nrc_presencial}</span> - 
                    <span style="color:#17a2b8;"><i class="fas fa-laptop"></i> ${solicitud.nrc_virtual}</span>
                </div>
            `;
            document.getElementById('txt_motivo').innerText = solicitud.motivo;
            document.getElementById('txt_desc').innerText = solicitud.descripcion ? '"' + solicitud.descripcion + '"' : 'Sin descripción adicional.';

            const adminInputArea = document.getElementById('admin_input_area');
            const adminResponseArea = document.getElementById('admin_response_area');
            const actionFooter = document.getElementById('action_footer');
            const closeFooter = document.getElementById('close_footer');

            if (solicitud.estatus === 'PENDIENTE') {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-clipboard-list"></i> Evaluar Solicitud';
                adminInputArea.style.display = 'block';
                adminResponseArea.style.display = 'none';
                actionFooter.style.display = 'flex';
                closeFooter.style.display = 'none';
                document.getElementById('mComentarios').value = '';
            } else {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-archive"></i> Archivo Histórico';
                adminInputArea.style.display = 'none';
                adminResponseArea.style.display = 'block';
                actionFooter.style.display = 'none';
                closeFooter.style.display = 'flex';

                let tagHtml = '';
                if (solicitud.estatus === 'APROBADA') tagHtml = '<span class="tag-aprobada" style="background:#d4edda; color:#155724; padding:4px 10px; border-radius:12px; font-size:0.8rem; font-weight:bold;"><i class="fas fa-check-circle"></i> Aprobada</span>';
                else if (solicitud.estatus === 'RECHAZADA') tagHtml = '<span class="tag-rechazada" style="background:#f8d7da; color:#721c24; padding:4px 10px; border-radius:12px; font-size:0.8rem; font-weight:bold;"><i class="fas fa-times-circle"></i> Rechazada</span>';
                else if (solicitud.estatus === 'CANCELADA') tagHtml = '<span class="tag-cancelada" style="background:#e2e3e5; color:#383d41; padding:4px 10px; border-radius:12px; font-size:0.8rem; font-weight:bold;"><i class="fas fa-ban"></i> Cancelada</span>';

                document.getElementById('resp_estatus').innerHTML = tagHtml;

                let fechaSol = solicitud.fecha_solicitud;
                if (fechaSol) {
                    let d = new Date(fechaSol.replace(/-/g, '/'));
                    document.getElementById('solicitud_fecha').innerText = d.toLocaleDateString('es-ES') + ' a las ' + d.toLocaleTimeString('es-ES', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }

                if (solicitud.estatus === 'CANCELADA') {
                    document.getElementById('resp_title').innerText = 'Resolución del Estudiante';
                    document.getElementById('resp_texto').innerText = 'El estudiante canceló la solicitud.';
                } else {
                    document.getElementById('resp_title').innerText = 'Resolución del Administrador';
                    document.getElementById('resp_texto').innerText = solicitud.respuesta_admin ? '"' + solicitud.respuesta_admin + '"' : 'El administrador no dejó comentarios.';
                }

                let fechaResp = solicitud.fecha_respuesta;
                if (fechaResp) {
                    let d = new Date(fechaResp.replace(/-/g, '/'));
                    document.getElementById('resp_fecha').innerText = d.toLocaleDateString('es-ES') + ' a las ' + d.toLocaleTimeString('es-ES', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } else {
                    document.getElementById('resp_fecha').innerText = 'No registrada';
                }
            }

            document.getElementById('modalReview').style.display = 'flex';
        }

        function cerrarReview() {
            document.getElementById('modalReview').style.display = 'none';
            document.getElementById('panelAutomatizado').style.display = 'none';
            ocultarFormNuevaRespuesta();
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
                    if (data.success) {
                        const container = document.getElementById('listaRespuestas');
                        container.innerHTML = '';
                        if (data.data.length === 0) {
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

                            btn.innerHTML = `<i class="fas fa-comment-dots" style="color:#6f42c1;"></i> <strong style="margin-left:5px;">${r.titulo}</strong>`;
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

            fetch('api_respuestas.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        ocultarFormNuevaRespuesta();
                        cargarRespuestas();
                    } else {
                        Swal.fire('Error', data.error || 'Ocurrió un error al guardar.', 'error');
                    }
                })
                .catch(err => console.error(err));
        }

        function procesar(accion) {
            document.getElementById('actionType').value = accion;
            document.getElementById('formReview').submit();
        }

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>

</html>