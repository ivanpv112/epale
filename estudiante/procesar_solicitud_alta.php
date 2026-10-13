<?php
session_start();
require '../db.php';
require_once '../security.php';

// Validar solicitud AJAX
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido.']);
    exit;
}

requerir_rol('ALUMNO');

if (!config_get('altas_habilitadas', false)) {
    echo json_encode(['status' => 'error', 'message' => 'El periodo de altas se encuentra cerrado actualmente.']);
    exit;
}

$usuario_id = $_SESSION['user_id'];
$nrc = intval($_POST['nrc'] ?? 0);
$action = $_POST['action'] ?? 'solicitar';

if ($nrc <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos (NRC).']);
    exit;
}

// Obtener alumno_id
$stmt_al = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
$stmt_al->execute([$usuario_id]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);
if (!$alumno) {
    echo json_encode(['status' => 'error', 'message' => 'Alumno no encontrado.']);
    exit;
}
$alumno_id = $alumno['alumno_id'];

if ($action === 'cancelar') {
    try {
        $pdo->beginTransaction();
        
        $stmt_ins = $pdo->prepare("SELECT inscripcion_id FROM inscripciones WHERE alumno_id = ? AND nrc = ? AND estatus = 'SOLICITUD_ALTA'");
        $stmt_ins->execute([$alumno_id, $nrc]);
        $inscripcion = $stmt_ins->fetch(PDO::FETCH_ASSOC);

        if ($inscripcion) {
            $inscripcion_id = $inscripcion['inscripcion_id'];
            
            $stmt_del_sol = $pdo->prepare("UPDATE solicitudes_altas SET estatus = 'CANCELADA', fecha_respuesta = NOW() WHERE inscripcion_id = ? AND estatus = 'PENDIENTE'");
            $stmt_del_sol->execute([$inscripcion_id]);
            
            $stmt_del_ins = $pdo->prepare("UPDATE inscripciones SET estatus = 'CANCELADA' WHERE inscripcion_id = ? AND estatus = 'SOLICITUD_ALTA'");
            $stmt_del_ins->execute([$inscripcion_id]);
            
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'Tu solicitud de alta ha sido retirada con éxito.']);
        } else {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'No se encontró una solicitud pendiente para esta materia.']);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Error del servidor: ' . mensaje_error_amigable($e)]);
    }
    exit;
}

$motivo = trim($_POST['motivo'] ?? '');

if (empty($motivo) || !isset($_FILES['horario_img'])) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos para solicitar.']);
    exit;
}

// Obtener alumno_id
$stmt_al = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
$stmt_al->execute([$usuario_id]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);
if (!$alumno) {
    echo json_encode(['status' => 'error', 'message' => 'Alumno no encontrado.']);
    exit;
}
$alumno_id = $alumno['alumno_id'];

// Verificar si ya está inscrito
$stmt_ya_inscrito = $pdo->prepare("SELECT inscripcion_id FROM inscripciones WHERE alumno_id = ? AND nrc = ? AND estatus = 'INSCRITO'");
$stmt_ya_inscrito->execute([$alumno_id, $nrc]);
if ($stmt_ya_inscrito->fetch()) {
    echo json_encode(['status' => 'error', 'message' => 'Ya estás inscrito en este NRC.']);
    exit;
}

// 1. Lógica de validación de horarios (Cruces)
// Obtener horarios de la materia solicitada
$stmt_req_hor = $pdo->prepare("SELECT hora_inicio, hora_fin, dias_patron FROM horarios WHERE nrc = ?");
$stmt_req_hor->execute([$nrc]);
$horarios_req = $stmt_req_hor->fetchAll(PDO::FETCH_ASSOC);

// Obtener horarios actuales del alumno (INSCRITO o SOLICITUD_ALTA)
$stmt_mis_hor = $pdo->prepare("
    SELECT h.hora_inicio, h.hora_fin, h.dias_patron, m.nombre as materia_nombre
    FROM horarios h
    JOIN inscripciones i ON h.nrc = i.nrc
    JOIN grupos g ON i.nrc = g.nrc
    JOIN materias m ON g.materia_id = m.materia_id
    JOIN ciclos c ON g.ciclo_id = c.ciclo_id
    WHERE i.alumno_id = ? 
      AND (i.estatus = 'INSCRITO' OR i.estatus = 'SOLICITUD_ALTA')
      AND c.activo = 1
");
$stmt_mis_hor->execute([$alumno_id]);
$mis_horarios = $stmt_mis_hor->fetchAll(PDO::FETCH_ASSOC);

// Detectar cruces
foreach ($horarios_req as $req) {
    $req_inicio = strtotime($req['hora_inicio']);
    $req_fin = strtotime($req['hora_fin']);
    $req_dias = str_split(preg_replace('/[^A-Za-z]/', '', strtoupper((string)$req['dias_patron'])));

    foreach ($mis_horarios as $mio) {
        $mio_inicio = strtotime($mio['hora_inicio']);
        $mio_fin = strtotime($mio['hora_fin']);
        $mio_dias = str_split(preg_replace('/[^A-Za-z]/', '', strtoupper((string)$mio['dias_patron'])));

        // Verificar si coinciden en algún día
        $cruzan_dias = array_intersect($req_dias, $mio_dias);
        
        if (!empty($cruzan_dias)) {
            // Si $req_inicio < $mio_fin Y $req_fin > $mio_inicio hay cruce
            if ($req_inicio < $mio_fin && $req_fin > $mio_inicio) {
                // Hay cruce!
                $dias_str = implode(', ', $cruzan_dias);
                echo json_encode([
                    'status' => 'error', 
                    'message' => 'No puedes agendar esta materia porque choca con tu horario actual.',
                    'horario' => htmlspecialchars($mio['materia_nombre']) . " (Días: $dias_str de " . substr($mio['hora_inicio'], 0, 5) . " a " . substr($mio['hora_fin'], 0, 5) . ")"
                ]);
                exit;
            }
        }
    }
}

// 2. Procesamiento de la Imagen (Horario Externo)
$file = $_FILES['horario_img'];
$target_dir = "../img/horarios/";
$new_file_name = secure_image_upload($file, $target_dir);

if (!$new_file_name) {
    echo json_encode(['status' => 'error', 'message' => 'Error al subir o procesar la imagen (Solo JPG/PNG permitidos).']);
    exit;
}

// 3. Guardar en Base de Datos
try {
    $pdo->beginTransaction();

    // Validar si ya la tiene inscrita o en trámite
    $stmt_check = $pdo->prepare("SELECT estatus FROM inscripciones WHERE alumno_id = ? AND nrc = ?");
    $stmt_check->execute([$alumno_id, $nrc]);
    if ($stmt_check->rowCount() > 0) {
        $estado_actual = $stmt_check->fetchColumn();
        if ($estado_actual == 'INSCRITO' || $estado_actual == 'SOLICITUD_ALTA') {
            throw new Exception("Ya estás inscrito o tienes una solicitud pendiente para esta materia.");
        } else {
            // Actualizar la existente (ej: estaba en BAJA)
            $stmt_upd = $pdo->prepare("UPDATE inscripciones SET estatus = 'SOLICITUD_ALTA' WHERE alumno_id = ? AND nrc = ?");
            $stmt_upd->execute([$alumno_id, $nrc]);
            // Obtenemos id
            $stmt_get = $pdo->prepare("SELECT inscripcion_id FROM inscripciones WHERE alumno_id = ? AND nrc = ?");
            $stmt_get->execute([$alumno_id, $nrc]);
            $inscripcion_id = $stmt_get->fetchColumn();
        }
    } else {
        // Nueva inscripción
        $stmt_ins = $pdo->prepare("INSERT INTO inscripciones (alumno_id, nrc, estatus, calificacion_final) VALUES (?, ?, 'SOLICITUD_ALTA', 0.00)");
        $stmt_ins->execute([$alumno_id, $nrc]);
        $inscripcion_id = $pdo->lastInsertId();
    }

    // Insertar en solicitudes_altas
    $stmt_alta = $pdo->prepare("INSERT INTO solicitudes_altas (inscripcion_id, motivo, horario_externo, estatus) VALUES (?, ?, ?, 'PENDIENTE')");
    $stmt_alta->execute([$inscripcion_id, htmlspecialchars(strip_tags($motivo)), $new_file_name]);

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Solicitud de alta enviada correctamente.']);

} catch (Exception $e) {
    $pdo->rollBack();
    // Borrar imagen si falló BD
    if (file_exists($target_dir . $new_file_name)) { unlink($target_dir . $new_file_name); }
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar: ' . mensaje_error_amigable($e)]);
}
?>
