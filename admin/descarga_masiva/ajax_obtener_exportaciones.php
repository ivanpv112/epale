<?php
session_start();
require '../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'data' => []]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT id, tipo, estado, progreso, archivo_ruta, fecha_solicitud FROM exportaciones WHERE admin_id = ? ORDER BY id DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'data' => []]);
}
?>
