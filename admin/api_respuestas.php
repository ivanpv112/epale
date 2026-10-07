<?php
session_start();
require '../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT id, titulo, cuerpo FROM respuestas_predefinidas WHERE usuario_id = ? ORDER BY id DESC");
    $stmt->execute([$admin_id]);
    $respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $respuestas]);
    exit;
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $titulo = trim($_POST['titulo'] ?? '');
        $cuerpo = trim($_POST['cuerpo'] ?? '');
        
        if (empty($titulo) || empty($cuerpo)) {
            echo json_encode(['success' => false, 'error' => 'El título y el cuerpo son obligatorios.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO respuestas_predefinidas (usuario_id, titulo, cuerpo) VALUES (?, ?, ?)");
        $stmt->execute([$admin_id, $titulo, $cuerpo]);
        
        $id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'id' => $id, 'titulo' => $titulo, 'cuerpo' => $cuerpo]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Acción no válida']);
