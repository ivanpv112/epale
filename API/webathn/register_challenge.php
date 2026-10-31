<?php
// api/webauthn/register_challenge.php
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
    echo json_encode(['success' => false, 'error' => 'No has iniciado sesión o tu sesión ha expirado. Por favor, recarga la página.']);
    exit;
}

$rootDir = dirname(__DIR__, 2);
require_once $rootDir . '/db.php';

$helperPath = $rootDir . '/lib/WebAuthn/WebAuthnHelper.php';
if (!file_exists($helperPath)) {
    $helperPath = $rootDir . '/lib/webauthn/WebAuthnHelper.php';
}
if (!file_exists($helperPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se encontró lib/WebAuthn/WebAuthnHelper.php en el servidor. Verifica haber subido la carpeta lib/WebAuthn/.']);
    exit;
}
require_once $helperPath;

try {
    $userId = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT usuario_id, correo, nombre, apellido_paterno FROM usuarios WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado en la base de datos.']);
        exit;
    }

    $challenge = WebAuthnHelper::generateChallenge(32);
    $_SESSION['webauthn_reg_challenge'] = $challenge;
    $_SESSION['webauthn_reg_user_id'] = $userId;

    // Obtener credenciales ya registradas para excluirlas
    $excludeCredentials = [];
    try {
        $stmtCreds = $pdo->prepare("SELECT credential_id FROM usuarios_credenciales_biometricas WHERE usuario_id = ?");
        $stmtCreds->execute([$userId]);
        $existing = $stmtCreds->fetchAll(PDO::FETCH_COLUMN);

        foreach ($existing as $credId) {
            $excludeCredentials[] = [
                'type' => 'public-key',
                'id'   => $credId
            ];
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error al consultar usuarios_credenciales_biometricas: ' . $e->getMessage()]);
        exit;
    }

    $options = [
        'challenge' => $challenge,
        'rp' => [
            'name' => WebAuthnHelper::getRpName(),
            'id'   => WebAuthnHelper::getRpId(),
        ],
        'user' => [
            'id'          => WebAuthnHelper::base64url_encode((string)$userId),
            'name'        => $user['correo'],
            'displayName' => trim($user['nombre'] . ' ' . ($user['apellido_paterno'] ?? '')),
        ],
        'pubKeyCredParams' => [
            ['type' => 'public-key', 'alg' => -7],   // ES256
            ['type' => 'public-key', 'alg' => -257], // RS256
        ],
        'authenticatorSelection' => [
            'authenticatorAttachment' => 'platform',
            'userVerification'        => 'preferred',
            'residentKey'             => 'preferred',
        ],
        'timeout'            => 60000,
        'attestation'        => 'none',
        'excludeCredentials' => $excludeCredentials,
    ];

    echo json_encode(['success' => true, 'options' => $options]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error inesperado: ' . $e->getMessage()]);
}

