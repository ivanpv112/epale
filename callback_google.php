<?php
// callback_google.php - Punto de regreso de Google tras el login.

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

require_once 'db.php';
require_once 'google_config.php';

function volver_con_error(string $mensaje): void
{
    $_SESSION['login_error'] = $mensaje;
    header('Location: index.php');
    exit;
}

// Captura de errores imprevistos (evita que termine en pantalla 500 en blanco)
function error_callback_google(string $detalle): void
{
    error_log('callback_google.php: ' . $detalle);
    $msg = 'Ocurrió un error al iniciar sesión con Google. Intenta de nuevo.';
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $msg .= ' [DEBUG: ' . htmlspecialchars($detalle) . ']';
    }
    if (!headers_sent()) {
        volver_con_error($msg);
    }
    echo htmlspecialchars($msg);
    exit;
}
set_exception_handler(function (Throwable $e) {
    error_callback_google(get_class($e) . ': ' . $e->getMessage()
        . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
});
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_callback_google($e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')');
    }
});

// Opciones SSL comunes para las peticiones a Google
function opciones_ssl_google(): array
{
    $opts = [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];
    $ca = defined('GOOGLE_CA_BUNDLE') ? (string) GOOGLE_CA_BUNDLE : '';
    if ($ca !== '') {
        if (@is_file($ca) && @is_readable($ca)) {
            $opts[CURLOPT_CAINFO] = $ca;
            return $opts;
        } else {
            error_log('callback_google.php: CURL_CA_BUNDLE no existe o no es legible: ' . $ca);
        }
    }
    // En hostings Linux donde default_cert_file no existe,
    // buscamos bundles estándar del sistema para asegurar la validación SSL
    $posibles_ca = [
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/ca-bundle.pem',
        '/etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem',
    ];
    foreach ($posibles_ca as $bundle) {
        if (@is_file($bundle) && @is_readable($bundle)) {
            $opts[CURLOPT_CAINFO] = $bundle;
            break;
        }
    }
    return $opts;
}

// 1) Errores directos devueltos por Google en la URL
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'access_denied') {
        volver_con_error('Cancelaste el inicio de sesión con Google.');
    }
    $err_code = $_GET['error'];
    $err_desc = $_GET['error_description'] ?? '';
    $detalle = $err_desc !== '' ? "$err_code: $err_desc" : $err_code;
    volver_con_error('Google reportó un error (' . htmlspecialchars($detalle) . '). Intenta de nuevo.');
}

// 2) Validar que vengan code y state
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    volver_con_error('No se recibió la información necesaria desde Google.');
}

// 3) Validar el "state" contra el que guardamos al iniciar el flujo (anti-CSRF)
if (empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $_GET['state'])) {
    unset($_SESSION['google_oauth_state']);
    volver_con_error('La sesión de autenticación con Google no es válida o expiró. Intenta de nuevo.');
}
unset($_SESSION['google_oauth_state']);

// 4) Intercambiar el "code" por un access_token
$ch = curl_init(GOOGLE_TOKEN_URL);
$curl_post_opts = opciones_ssl_google() + [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    CURLOPT_USERAGENT      => 'E-PALE-OAuth/1.0',
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
    ],
];
curl_setopt_array($ch, $curl_post_opts);
$response = curl_exec($ch);
$curl_err = curl_error($ch);
$curl_errno = curl_errno($ch);
curl_close($ch);

if ($response === false) {
    error_log("Google OAuth (token) errno $curl_errno: $curl_err");
    volver_con_error("No se pudo conectar con Google (cURL $curl_errno: $curl_err). Intenta más tarde.");
}

$token_info = json_decode($response, true);
if (empty($token_info['access_token'])) {
    $err_desc = $token_info['error_description'] ?? ($token_info['error'] ?? 'Respuesta sin access_token');
    error_log('Google OAuth (token): respuesta inválida: ' . $response);
    volver_con_error('Google rechazó la autenticación (' . htmlspecialchars($err_desc) . '). Intenta de nuevo.');
}

// 5) Obtener datos del usuario con el access_token
$ch = curl_init(GOOGLE_USERINFO_URL);
$curl_userinfo_opts = opciones_ssl_google() + [
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token_info['access_token'],
        'Accept: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
    CURLOPT_USERAGENT      => 'E-PALE-OAuth/1.0',
];
curl_setopt_array($ch, $curl_userinfo_opts);
$user_response = curl_exec($ch);
$curl_err = curl_error($ch);
$curl_errno = curl_errno($ch);
curl_close($ch);

if ($user_response === false) {
    error_log("Google OAuth (userinfo) errno $curl_errno: $curl_err");
    volver_con_error("No se pudo obtener tu información de Google (cURL $curl_errno: $curl_err). Intenta de nuevo.");
}

$google_user = json_decode($user_response, true);

if (empty($google_user['id']) || empty($google_user['email'])) {
    volver_con_error('Google no envió la información necesaria del usuario. Intenta de nuevo.');
}

// Exigimos que el correo esté verificado por Google
if (empty($google_user['verified_email'])) {
    volver_con_error('Tu correo de Google no está verificado. Usa una cuenta verificada.');
}

$google_id = $google_user['id'];
$correo_google = strtolower(trim($google_user['email']));

// 6) Buscar la cuenta YA EXISTENTE en usuarios por correo institucional.
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE correo = ?");
$stmt->execute([$correo_google]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    volver_con_error(
        'Tu correo de Google (' . htmlspecialchars($correo_google) . ') no está registrado en E-PALE. '
            . 'Contacta a administración para que den de alta tu cuenta.'
    );
}

if ($user['estatus'] !== 'ACTIVO') {
    volver_con_error('Tu cuenta está desactivada. Contacta al administrador.');
}

// 7) Vincular google_id la primera vez (o si cambió)
if (($user['google_id'] ?? null) !== $google_id) {
    $upd = $pdo->prepare("UPDATE usuarios SET google_id = ? WHERE usuario_id = ?");
    $upd->execute([$google_id, $user['usuario_id']]);
}

// 8) Iniciar sesión igual que el login normal (alineado con login_process.php y security.php)
session_regenerate_id(true);

$_SESSION['user_id'] = $user['usuario_id'];
$_SESSION['nombre'] = $user['nombre'];
if (array_key_exists('apellido_paterno', $user)) {
    $_SESSION['apellido_paterno'] = $user['apellido_paterno'];
    $_SESSION['apellido_materno'] = $user['apellido_materno'] ?? '';
} else {
    $_SESSION['apellidos'] = $user['apellidos'] ?? '';
}
$_SESSION['rol'] = $user['rol'];
$_SESSION['ultima_actividad'] = time();

// Asegurar que el token CSRF exista para los formularios de las secciones internas
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

switch ($user['rol']) {
    case 'ADMIN':
        header('Location: admin/index.php');
        break;
    case 'PROFESOR':
        header('Location: profesor/index.php');
        break;
    case 'ALUMNO':
        header('Location: estudiante/index.php');
        break;
    default:
        volver_con_error('Rol no identificado.');
}
exit;
