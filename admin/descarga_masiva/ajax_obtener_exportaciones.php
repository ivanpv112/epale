<?php
session_start();
require '../../db.php';
require_once '../../security.php';

header('Content-Type: application/json');

requerir_rol('ADMIN');

$user_id = $_SESSION['user_id'] ?? 0;
$fecha = $_GET['fecha'] ?? '';
$tipo = $_GET['tipo'] ?? 'Todos';

try {
    $sql = "SELECT id, tipo, estado, progreso, archivo_ruta, fecha_solicitud FROM exportaciones WHERE admin_id = ?";
    $params = [$user_id];

    if (!empty($fecha)) {
        $sql .= " AND DATE(fecha_solicitud) = ?";
        $params[] = $fecha;
    }

    if ($tipo === 'GLOBAL') {
        $sql .= " AND tipo = 'GLOBAL'";
    } elseif ($tipo === 'CARRERA') {
        $sql .= " AND tipo LIKE 'CARRERA_%'";
    }

    $sql .= " ORDER BY id DESC LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'data' => []]);
}
?>
