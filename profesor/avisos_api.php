<?php
session_start();
require '../db.php';
require '../security.php';

date_default_timezone_set('America/Mexico_City'); 

// CAPA EXTRA DE SEGURIDAD: Rechazar cualquier petición que no sea por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']); 
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']); 
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (is_array($input)) { 
    $_POST = array_merge($_POST, $input); 
}

// Validación estricta del Token CSRF (Centralizada)
validar_csrf_estricto();

$profesor_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
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

if ($action == 'get_avisos') {
    $stmt = $pdo->prepare("
        SELECT a.aviso_id, a.profesor_id, a.nrc, a.tipo, a.titulo, a.descripcion, 
               a.fecha_inicio, a.fecha_fin, a.fecha_creacion, g.clave_grupo, m.nombre AS materia_nombre 
        FROM avisos_profesor a 
        JOIN grupos g ON a.nrc = g.nrc 
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        WHERE a.profesor_id = ? AND c.activo = 1
        ORDER BY a.fecha_inicio DESC
    ");
    $stmt->execute([$profesor_id]);
    $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($avisos as &$a) {
        $a['estatus'] = calcularEstatus($a['fecha_inicio'], $a['fecha_fin']);
        $a['fecha_inicio_input'] = date('Y-m-d\TH:i', strtotime($a['fecha_inicio']));
        $a['fecha_fin_input'] = date('Y-m-d\TH:i', strtotime($a['fecha_fin']));
    }
    echo json_encode($avisos); 
    exit;
}

elseif ($action == 'save_aviso') {
    $aviso_id = $_POST['aviso_id'] ?? '';
    $nrc = $_POST['nrc'];
    $tipo = $_POST['tipo'];
    $titulo = $_POST['titulo'];
    $descripcion = $_POST['descripcion'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_fin = $_POST['fecha_fin'];

    if (empty($aviso_id)) {
        $stmt = $pdo->prepare("INSERT INTO avisos_profesor (profesor_id, nrc, tipo, titulo, descripcion, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$profesor_id, $nrc, $tipo, $titulo, $descripcion, $fecha_inicio, $fecha_fin]);
    } else {
        $stmt = $pdo->prepare("UPDATE avisos_profesor SET nrc=?, tipo=?, titulo=?, descripcion=?, fecha_inicio=?, fecha_fin=? WHERE aviso_id=? AND profesor_id=?");
        $stmt->execute([$nrc, $tipo, $titulo, $descripcion, $fecha_inicio, $fecha_fin, $aviso_id, $profesor_id]);
    }
    echo json_encode(['status' => 'success']); 
    exit;
}

elseif ($action == 'publicar_manual') {
    $aviso_id = $_POST['aviso_id'];
    $stmt = $pdo->prepare("UPDATE avisos_profesor SET fecha_inicio = ? WHERE aviso_id=? AND profesor_id=?");
    $stmt->execute([$ahora, $aviso_id, $profesor_id]);
    echo json_encode(['status' => 'success']); 
    exit;
}

elseif ($action == 'finalizar_manual') {
    $aviso_id = $_POST['aviso_id'];
    $stmt = $pdo->prepare("UPDATE avisos_profesor SET fecha_fin = ? WHERE aviso_id=? AND profesor_id=?");
    $stmt->execute([$ahora, $aviso_id, $profesor_id]);
    echo json_encode(['status' => 'success']); 
    exit;
}

elseif ($action == 'delete_aviso') {
    $aviso_id = $_POST['aviso_id'];
    $stmt = $pdo->prepare("DELETE FROM avisos_profesor WHERE aviso_id=? AND profesor_id=?");
    $stmt->execute([$aviso_id, $profesor_id]);
    echo json_encode(['status' => 'success']); 
    exit;
}

elseif ($action == 'get_grupos_activos') {
    $stmt = $pdo->prepare("
        SELECT g.nrc, g.clave_grupo, m.nombre 
        FROM grupos g 
        JOIN materias m ON g.materia_id = m.materia_id 
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        WHERE g.profesor_id = ? AND g.estado = 'ACTIVO' AND c.activo = 1
    ");
    $stmt->execute([$profesor_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); 
    exit;
}
?>