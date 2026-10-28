<?php
// api_webauthn_credentials.php
// Permite listar y eliminar credenciales biométricas asociadas a la cuenta en sesión.
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado o sesión expirada.']);
    exit;
}

require_once __DIR__ . '/db.php';
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT id, nombre_dispositivo, fecha_registro, ultimo_uso
            FROM usuarios_credenciales_biometricas
            WHERE usuario_id = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'credentials' => $items]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al listar credenciales: ' . $e->getMessage()]);
    }
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $credId = (int)($input['id'] ?? 0);

    if ($action === 'delete' && $credId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM usuarios_credenciales_biometricas WHERE id = ? AND usuario_id = ?");
            $stmt->execute([$credId, $userId]);

            echo json_encode(['success' => true, 'message' => 'Credencial biométrica eliminada con éxito.']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al eliminar credencial: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no soportado.']);
