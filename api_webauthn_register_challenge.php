<?php
// api_webauthn_register_challenge.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No has iniciado sesión.']);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/WebAuthn/WebAuthnHelper.php';

try {
    $userId = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT usuario_id, correo, nombre, apellido_paterno FROM usuarios WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado.']);
        exit;
    }

    $challenge = WebAuthnHelper::generateChallenge(32);
    $_SESSION['webauthn_reg_challenge'] = $challenge;
    $_SESSION['webauthn_reg_user_id'] = $userId;

    // Obtener credenciales ya registradas para excluirlas
    $stmtCreds = $pdo->prepare("SELECT credential_id FROM usuarios_credenciales_biometricas WHERE usuario_id = ?");
    $stmtCreds->execute([$userId]);
    $existing = $stmtCreds->fetchAll(PDO::FETCH_COLUMN);

    $excludeCredentials = [];
    foreach ($existing as $credId) {
        $excludeCredentials[] = [
            'type' => 'public-key',
            'id'   => $credId
        ];
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
            'authenticatorAttachment' => 'platform', // Obliga al uso de sensor biométrico del dispositivo (Touch ID, Windows Hello, Huella Android)
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
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
