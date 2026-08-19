<?php
session_start();
require '../db.php';
date_default_timezone_set('America/Mexico_City'); 

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']); exit;
}

$profesor_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$ahora = date('Y-m-d H:i:s'); 

function calcularEstatus($fecha_inicio, $fecha_fin) {
    $hoy = new DateTime();
    $inicio = new DateTime($fecha_inicio);
    $fin = new DateTime($fecha_fin);

    if ($hoy > $fin) return 'FINALIZADA';
    if ($hoy >= $inicio && $hoy <= $fin) return 'ACTIVA';

    $diff = $hoy->diff($inicio);
    $dias_faltantes = $diff->days;
    $invert = $diff->invert;

    if ($invert == 0 && $dias_faltantes <= 3) return 'PRÓXIMA';
    return 'PENDIENTE';
}

if ($action == 'get_tareas') {
    // FILTRO INTELIGENTE: JOIN con ciclos para traer solo lo del periodo activo
    $stmt = $pdo->prepare("
        SELECT t.*, g.clave_grupo, m.nombre as materia_nombre 
        FROM tareas_profesor t 
        JOIN grupos g ON t.nrc = g.nrc 
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        WHERE t.profesor_id = ? AND c.activo = 1
        ORDER BY t.fecha_inicio DESC
    ");
    $stmt->execute([$profesor_id]);
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tareas as &$t) {
        $t['estatus'] = calcularEstatus($t['fecha_inicio'], $t['fecha_fin']);
        $t['fecha_inicio_input'] = date('Y-m-d\TH:i', strtotime($t['fecha_inicio']));
        $t['fecha_fin_input'] = date('Y-m-d\TH:i', strtotime($t['fecha_fin']));
    }
    echo json_encode($tareas); exit;
}

elseif ($action == 'save_tarea') {
    $tarea_id = $_POST['tarea_id'] ?? '';
    $nrc = $_POST['nrc'];
    $tipo = $_POST['tipo'];
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];

    if (empty($tarea_id)) {
        $stmt = $pdo->prepare("INSERT INTO tareas_profesor (profesor_id, nrc, tipo, titulo, descripcion, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$profesor_id, $nrc, $tipo, $titulo, $descripcion, $fecha_inicio, $fecha_fin]);
    } else {
        $stmt = $pdo->prepare("UPDATE tareas_profesor SET nrc=?, tipo=?, titulo=?, descripcion=?, fecha_inicio=?, fecha_fin=? WHERE tarea_id=? AND profesor_id=?");
        $stmt->execute([$nrc, $tipo, $titulo, $descripcion, $fecha_inicio, $fecha_fin, $tarea_id, $profesor_id]);
    }
    echo json_encode(['status' => 'success']); exit;
}

elseif ($action == 'publicar_manual') {
    $tarea_id = $_POST['tarea_id'];
    $stmt = $pdo->prepare("UPDATE tareas_profesor SET fecha_inicio = ? WHERE tarea_id=? AND profesor_id=?");
    $stmt->execute([$ahora, $tarea_id, $profesor_id]);
    echo json_encode(['status' => 'success']); exit;
}

elseif ($action == 'finalizar_manual') {
    $tarea_id = $_POST['tarea_id'];
    $stmt = $pdo->prepare("UPDATE tareas_profesor SET fecha_fin = ? WHERE tarea_id=? AND profesor_id=?");
    $stmt->execute([$ahora, $tarea_id, $profesor_id]);
    echo json_encode(['status' => 'success']); exit;
}

elseif ($action == 'delete_tarea') {
    $tarea_id = $_POST['tarea_id'];
    $stmt = $pdo->prepare("DELETE FROM tareas_profesor WHERE tarea_id=? AND profesor_id=?");
    $stmt->execute([$tarea_id, $profesor_id]);
    echo json_encode(['status' => 'success']); exit;
}

elseif ($action == 'get_grupos_activos') {
    // FILTRO INTELIGENTE: Solo los grupos del ciclo activo actual
    $stmt = $pdo->prepare("
        SELECT g.nrc, g.clave_grupo, m.nombre 
        FROM grupos g 
        JOIN materias m ON g.materia_id = m.materia_id 
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        WHERE g.profesor_id = ? AND g.estado = 'ACTIVO' AND c.activo = 1
    ");
    $stmt->execute([$profesor_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}
?>