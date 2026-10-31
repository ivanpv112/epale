<?php
// api/webauthn/login_verify.php
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

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/db.php';

$helperPath = $rootDir . '/lib/WebAuthn/WebAuthnHelper.php';
if (!file_exists($helperPath)) {
    $helperPath = $rootDir . '/lib/webauthn/WebAuthnHelper.php';
}
if (!file_exists($helperPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se encontró lib/WebAuthn/WebAuthnHelper.php en el servidor.']);
    exit;
}
require_once $helperPath;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cuerpo JSON inválido.']);
    exit;
}

$credentialId = $input['id'] ?? '';
$clientDataJSON = $input['clientDataJSON'] ?? '';
$authenticatorData = $input['authenticatorData'] ?? '';
$signature = $input['signature'] ?? '';

$expectedChallenge = $_SESSION['webauthn_auth_challenge'] ?? '';
if (empty($expectedChallenge)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Sesión de autenticación expirada. Por favor, intenta de nuevo.']);
    exit;
}

try {
    // 1. Buscar la credencial registrada
    $stmt = $pdo->prepare("
        SELECT b.id, b.usuario_id, b.public_key, b.sign_count,
               u.usuario_id, u.nombre, u.apellido_paterno, u.apellido_materno, u.rol, u.estatus
        FROM usuarios_credenciales_biometricas b
        INNER JOIN usuarios u ON b.usuario_id = u.usuario_id
        WHERE b.credential_id = ?
        LIMIT 1
    ");
    $stmt->execute([$credentialId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception("Dispositivo biométrico no reconocido en el sistema. Asegúrate de haberlo vinculado en tu perfil.");
    }

    if ($row['estatus'] !== 'ACTIVO') {
        throw new Exception("Tu cuenta se encuentra desactivada. Contacta al administrador.");
    }

    // 2. Verificar la firma criptográfica
    $result = WebAuthnHelper::verifyAssertion(
        $clientDataJSON,
        $authenticatorData,
        $signature,
        $row['public_key'],
        $expectedChallenge,
        (int)$row['sign_count']
    );

    // 3. Actualizar contador de firma y fecha de último uso
    $upd = $pdo->prepare("UPDATE usuarios_credenciales_biometricas SET sign_count = ?, ultimo_uso = NOW() WHERE id = ?");
    $upd->execute([$result['signCount'], $row['id']]);

    // Limpiar reto de sesión
    unset($_SESSION['webauthn_auth_challenge']);

    // 4. Iniciar sesión formalmente (alineado con login_process.php y security.php)
    session_regenerate_id(true);

    $_SESSION['user_id'] = $row['usuario_id'];
    $_SESSION['nombre'] = $row['nombre'];
    if (isset($row['apellido_paterno'])) {
        $_SESSION['apellido_paterno'] = $row['apellido_paterno'];
        $_SESSION['apellido_materno'] = $row['apellido_materno'] ?? '';
    }
    $_SESSION['rol'] = $row['rol'];
    $_SESSION['ultima_actividad'] = time();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $redirectUrl = '';
    switch ($row['rol']) {
        case 'ADMIN':
            $redirectUrl = "admin/index";
            break;
        case 'PROFESOR':
            $redirectUrl = "profesor/index";
            break;
        case 'ALUMNO':
            $redirectUrl = "estudiante/index";
            break;
        default:
            throw new Exception("Rol de usuario no identificado.");
    }

    echo json_encode([
        'success'   => true,
        'redirect'  => $redirectUrl,
        'user_name' => $row['nombre']
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

