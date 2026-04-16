<?php
session_start();
require '../db.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($_SESSION['user_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false]); exit;
}

if ($input['action'] === 'batch') {
    $fecha = date('Y-m-d');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO asistencias (inscripcion_id, fecha, estatus) VALUES (?, ?, ?) 
                               ON DUPLICATE KEY UPDATE estatus = VALUES(estatus)");
        foreach($input['data'] as $row) {
            $stmt->execute([$row['inscripcion_id'], $fecha, $row['estatus']]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch(Exception $e) { $pdo->rollBack(); echo json_encode(['success' => false]); }
}

if ($input['action'] === 'single') {
    $stmt = $pdo->prepare("UPDATE asistencias SET estatus = ? WHERE inscripcion_id = ? AND fecha = ?");
    $success = $stmt->execute([$input['estatus'], $input['ins_id'], $input['fecha']]);
    echo json_encode(['success' => $success]);
}