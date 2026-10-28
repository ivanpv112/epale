<?php
// api_webauthn_register_verify.php
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
    echo json_encode(['success' => false, 'error' => 'No has iniciado sesión o tu sesión ha expirado.']);
    exit;
}

require_once __DIR__ . '/db.php';

$helperPath = __DIR__ . '/lib/WebAuthn/WebAuthnHelper.php';
if (!file_exists($helperPath)) {
    $helperPath = __DIR__ . '/lib/webauthn/WebAuthnHelper.php';
}
if (!file_exists($helperPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se encontró lib/WebAuthn/WebAuthnHelper.php en el servidor.']);
    exit;
}
require_once $helperPath;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cuerpo JSON inválido.']);
    exit;
}

$clientDataJSON = $input['clientDataJSON'] ?? '';
$attestationObject = $input['attestationObject'] ?? '';
$nombreDispositivo = trim($input['nombre_dispositivo'] ?? '');
if ($nombreDispositivo === '') {
    $nombreDispositivo = 'Dispositivo Biométrico';
}

$expectedChallenge = $_SESSION['webauthn_reg_challenge'] ?? '';
$expectedUserId = $_SESSION['webauthn_reg_user_id'] ?? 0;

if (empty($expectedChallenge) || $expectedUserId !== (int)$_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sesión de registro expirada. Inténtalo nuevamente.']);
    exit;
}

try {
    $parsed = WebAuthnHelper::parseRegistrationResponse($clientDataJSON, $attestationObject, $expectedChallenge);

    // Guardar credencial en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO usuarios_credenciales_biometricas 
        (usuario_id, credential_id, public_key, sign_count, nombre_dispositivo, fecha_registro)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $parsed['credentialId'],
        $parsed['publicKeyPem'],
        $parsed['signCount'],
        $nombreDispositivo
    ]);

    // Limpiar variables de registro
    unset($_SESSION['webauthn_reg_challenge'], $_SESSION['webauthn_reg_user_id']);

    echo json_encode([
        'success' => true,
        'message' => '¡Dispositivo y huella dactilar vinculados con éxito!'
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
