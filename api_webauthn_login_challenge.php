<?php
// api_webauthn_login_challenge.php
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

try {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, true) ?: [];
    $usuario = trim($input['usuario'] ?? '');

    $challenge = WebAuthnHelper::generateChallenge(32);
    $_SESSION['webauthn_auth_challenge'] = $challenge;

    $allowCredentials = [];

    // Si el usuario escribió su correo o código, buscamos sus credenciales específicas
    if ($usuario !== '') {
        $stmtUser = $pdo->prepare("SELECT usuario_id FROM usuarios WHERE (correo = ? OR codigo = ?) AND estatus = 'ACTIVO'");
        $stmtUser->execute([$usuario, $usuario]);
        $u = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($u) {
            $stmtCreds = $pdo->prepare("SELECT credential_id FROM usuarios_credenciales_biometricas WHERE usuario_id = ?");
            $stmtCreds->execute([$u['usuario_id']]);
            $creds = $stmtCreds->fetchAll(PDO::FETCH_COLUMN);

            foreach ($creds as $cid) {
                $allowCredentials[] = [
                    'type' => 'public-key',
                    'id'   => $cid
                ];
            }
        }
    }

    $options = [
        'challenge'        => $challenge,
        'timeout'          => 60000,
        'rpId'             => WebAuthnHelper::getRpId(),
        'userVerification' => 'preferred',
    ];

    if (!empty($allowCredentials)) {
        $options['allowCredentials'] = $allowCredentials;
    }

    echo json_encode([
        'success' => true,
        'options' => $options
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
