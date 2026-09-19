<?php
// google_auth.php - Inicia el flujo OAuth 2.0 con Google.
// Se llega aquí desde el botón "Continuar con Google" de index.php.

session_start();
require 'google_config.php';

// "state" aleatorio de un solo uso: Google nos lo regresa tal cual en el
// callback, y ahí lo comparamos para confirmar que la respuesta corresponde
// a una petición que nosotros iniciamos (evita CSRF sobre el login social).
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;

$params = [
    'response_type' => 'code',
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'scope'         => 'email profile',
    'state'         => $state,
    // No pedimos access_type=offline ni prompt=consent: solo necesitamos
    // identificar al usuario una vez, no acceso continuo a su cuenta de
    // Google. Esto además hace la pantalla de permisos menos intimidante.
];

header('Location: ' . GOOGLE_AUTH_URL . '?' . http_build_query($params));
exit;
