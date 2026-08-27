<?php
session_start();
require '../db.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR' || !isset($input['action'])) {
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']); exit;
}

// Validación estricta del Token CSRF
if (empty($_SESSION['csrf_token']) || empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido (CSRF). Por favor, recarga la página.']); exit;
}

$profesor_id = $_SESSION['user_id'];

if ($input['action'] === 'batch') {
    $fecha = date('Y-m-d');
    $pdo->beginTransaction();
    try {
        $stmt_val = $pdo->prepare("SELECT g.estado FROM inscripciones i JOIN grupos g ON i.nrc = g.nrc WHERE i.inscripcion_id = ? AND g.profesor_id = ?");
        $stmt_ins = $pdo->prepare("INSERT INTO asistencias (inscripcion_id, fecha, estatus) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE estatus = VALUES(estatus)");
                                   
        foreach($input['data'] as $row) {
            $stmt_val->execute([$row['inscripcion_id'], $profesor_id]);
            $estado_grupo = $stmt_val->fetchColumn();
            
            if ($estado_grupo === false) { throw new Exception("Vulnerabilidad detectada: Intento de modificar alumno ajeno."); }
            if ($estado_grupo === 'CERRADO') { throw new Exception("Operación rechazada: No se puede tomar asistencia en un grupo finalizado."); }
            
            $stmt_ins->execute([$row['inscripcion_id'], $fecha, $row['estatus']]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
        
    } catch(PDOException $e) { 
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Error PDO en asistencia_api (batch): " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno de base de datos al guardar la lista.']); 
    } catch(Exception $e) { 
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]); 
    }
}

if ($input['action'] === 'single') {
    try {
        $stmt_val = $pdo->prepare("SELECT g.estado FROM inscripciones i JOIN grupos g ON i.nrc = g.nrc WHERE i.inscripcion_id = ? AND g.profesor_id = ?");
        $stmt_val->execute([$input['ins_id'], $profesor_id]);
        $estado_grupo = $stmt_val->fetchColumn();
        
        if ($estado_grupo === false) { echo json_encode(['success' => false, 'error' => 'Acceso denegado: El alumno no pertenece a tus grupos.']); exit; }
        if ($estado_grupo === 'CERRADO') { echo json_encode(['success' => false, 'error' => 'Operación rechazada: Grupo finalizado (CERRADO).']); exit; }

        $stmt = $pdo->prepare("UPDATE asistencias SET estatus = ? WHERE inscripcion_id = ? AND fecha = ?");
        $success = $stmt->execute([$input['estatus'], $input['ins_id'], $input['fecha']]);
        echo json_encode(['success' => $success]);
        
    } catch(PDOException $e) {
        error_log("Error PDO en asistencia_api (single): " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Error interno de base de datos.']);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
