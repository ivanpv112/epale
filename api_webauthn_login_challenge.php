<?php
// api_webauthn_login_challenge.php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/WebAuthn/WebAuthnHelper.php';

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
