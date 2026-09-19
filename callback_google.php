<?php
// callback_google.php - Punto de regreso de Google tras el login.
//
// A diferencia de un login social "abierto", aquí NUNCA se crea un usuario
// nuevo: E-PALE requiere que el Admin haya dado de alta la cuenta primero
// (con su correo institucional). Este archivo solo VINCULA esa cuenta ya
// existente con su Google ID, para futuros logins más rápidos.

session_start();
require 'db.php';
require 'google_config.php';

function volver_con_error(string $mensaje): void
{
    $_SESSION['login_error'] = $mensaje;
    header('Location: index.php');
    exit;
}

// 1) Errores directos de Google (usuario canceló, etc.)
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'access_denied') {
        volver_con_error('Cancelaste el inicio de sesión con Google.');
    }
    volver_con_error('Ocurrió un problema con Google. Intenta de nuevo.');
}

// 2) Validar que vengan code y state
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    volver_con_error('No se recibió la información necesaria desde Google.');
}

// 3) Validar el "state" contra el que guardamos al iniciar el flujo (anti-CSRF)
if (empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $_GET['state'])) {
    unset($_SESSION['google_oauth_state']);
    volver_con_error('La sesión de autenticación con Google no es válida. Intenta de nuevo.');
}
unset($_SESSION['google_oauth_state']);

// 4) Intercambiar el "code" por un access_token
$ch = curl_init(GOOGLE_TOKEN_URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$response = curl_exec($ch);
$curl_err = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_log('Google OAuth (token): ' . $curl_err);
    volver_con_error('No se pudo conectar con Google. Intenta más tarde.');
}

$token_info = json_decode($response, true);
if (empty($token_info['access_token'])) {
    error_log('Google OAuth (token): respuesta sin access_token: ' . $response);
    volver_con_error('Google no devolvió un token válido. Intenta de nuevo.');
}

// 5) Obtener datos del usuario con el access_token
$ch = curl_init(GOOGLE_USERINFO_URL);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token_info['access_token']],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$user_response = curl_exec($ch);
$curl_err = curl_error($ch);
curl_close($ch);

if ($user_response === false) {
    error_log('Google OAuth (userinfo): ' . $curl_err);
    volver_con_error('No se pudo obtener tu información de Google. Intenta de nuevo.');
}

$google_user = json_decode($user_response, true);

if (empty($google_user['id']) || empty($google_user['email'])) {
    volver_con_error('Google no envió la información necesaria. Intenta de nuevo.');
}

// Exigimos que el correo esté verificado por Google: nos vamos a identificar
// a alguien en base a este correo, así que debe ser confiable.
if (empty($google_user['verified_email'])) {
    volver_con_error('Tu correo de Google no está verificado. Usa una cuenta verificada.');
}

$google_id = $google_user['id'];
$correo_google = strtolower(trim($google_user['email']));

// 6) Buscar la cuenta YA EXISTENTE en usuarios por correo institucional.
//    Si no existe, no se crea nada: E-PALE es de alta controlada por Admin.
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

// 7) Vincular google_id la primera vez (o si cambió, poco probable pero por
//    si Admin recreó la cuenta de Google del usuario).
if ($user['google_id'] !== $google_id) {
    $upd = $pdo->prepare("UPDATE usuarios SET google_id = ? WHERE usuario_id = ?");
    $upd->execute([$google_id, $user['usuario_id']]);
}

// 8) Iniciar sesión igual que el login normal (mismas variables que index.php)
session_regenerate_id(true);

$_SESSION['user_id'] = $user['usuario_id'];
$_SESSION['nombre'] = $user['nombre'];
$_SESSION['apellido_paterno'] = $user['apellido_paterno'];
$_SESSION['apellido_materno'] = $user['apellido_materno'];
$_SESSION['rol'] = $user['rol'];
$_SESSION['ultima_actividad'] = time();

switch ($user['rol']) {
    case 'ADMIN':
        header('Location: admin/usuarios.php');
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
