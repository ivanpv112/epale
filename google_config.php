<?php
// google_config.php - Credenciales y endpoints de Google OAuth 2.0
// Sigue el mismo patrón que db.php: primero intenta .env local, si no
// existe usa variables de entorno nativas del hosting.

$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $env = parse_ini_file($env_file);
    $google_client_id     = $env['GOOGLE_CLIENT_ID'] ?? '';
    $google_client_secret = $env['GOOGLE_CLIENT_SECRET'] ?? '';
    $google_redirect_uri  = $env['GOOGLE_REDIRECT_URI'] ?? '';
    $curl_ca_bundle       = $env['CURL_CA_BUNDLE'] ?? '';
} else {
    $google_client_id     = getenv('GOOGLE_CLIENT_ID') ?: '';
    $google_client_secret = getenv('GOOGLE_CLIENT_SECRET') ?: '';
    $google_redirect_uri  = getenv('GOOGLE_REDIRECT_URI') ?: '';
    $curl_ca_bundle       = getenv('CURL_CA_BUNDLE') ?: '';
}

define('GOOGLE_CLIENT_ID', $google_client_id);
define('GOOGLE_CLIENT_SECRET', $google_client_secret);
define('GOOGLE_REDIRECT_URI', $google_redirect_uri);

// Bundle de certificados raíz (CA) para que cURL valide el SSL de Google.
// En WAMP/Windows PHP no trae uno configurado y falla con
// "SSL certificate problem: unable to get local issuer certificate".
// En producción normalmente se deja vacío y se usan los CA del sistema.
if (!defined('GOOGLE_CA_BUNDLE')) {
    define('GOOGLE_CA_BUNDLE', is_string($curl_ca_bundle ?? null) ? trim($curl_ca_bundle) : '');
}

// APP_DEBUG=1 en el .env muestra el detalle técnico de errores en el login.
// Déjalo apagado (o sin definir) en producción.
if (!defined('APP_DEBUG')) {
    $app_debug = isset($env) && is_array($env) ? ($env['APP_DEBUG'] ?? '') : getenv('APP_DEBUG');
    define('APP_DEBUG', filter_var($app_debug, FILTER_VALIDATE_BOOLEAN));
}

// Endpoints oficiales de Google (no cambian, no van en .env)
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo');

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '' || GOOGLE_REDIRECT_URI === '') {
    error_log('google_config.php: faltan credenciales de Google en el .env');
}
