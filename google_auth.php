<?php
// google_auth.php - Inicia el flujo OAuth 2.0 con Google.
// Se llega aquí desde el botón "Continuar con Google" de index.php.

// Asegurar compatibilidad de cookies SameSite para OAuth cross-site en HTTPS/HTTP
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

require 'google_config.php';

// "state" aleatorio de un solo uso
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;

$params = [
    'response_type' => 'code',
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'scope'         => 'email profile',
    'state'         => $state,
];

header('Location: ' . GOOGLE_AUTH_URL . '?' . http_build_query($params));
exit;
