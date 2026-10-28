<?php
// api_webauthn_credentials.php
// Permite listar y eliminar credenciales biométricas asociadas a la cuenta en sesión.
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado.']);
    exit;
}

require_once __DIR__ . '/db.php';
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Listar credenciales del usuario
    $stmt = $pdo->prepare("
        SELECT id, nombre_dispositivo, fecha_registro, ultimo_uso
        FROM usuarios_credenciales_biometricas
        WHERE usuario_id = ?
        ORDER BY id DESC
    ");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'credentials' => $items]);
    exit;
}

if ($method === 'POST') {
    // Eliminar credencial
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $credId = (int)($input['id'] ?? 0);

    if ($action === 'delete' && $credId > 0) {
        $stmt = $pdo->prepare("DELETE FROM usuarios_credenciales_biometricas WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$credId, $userId]);

        echo json_encode(['success' => true, 'message' => 'Credencial biométrica eliminada con éxito.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no soportado.']);
