<?php
// login_process.php
session_start();
require 'db.php';

// =========================================================
// CONFIGURACIÓN DE PROTECCIÓN CONTRA FUERZA BRUTA
// =========================================================
const MAX_INTENTOS    = 5;   // intentos fallidos permitidos
const VENTANA_MINUTOS = 15;  // ventana de tiempo que se revisa
const BLOQUEO_MINUTOS = 15;  // tiempo de bloqueo una vez superado el máximo

function obtener_ip_cliente(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function esta_bloqueado(PDO $pdo, string $identificador, string $ip): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_intentos
        WHERE exitoso = 0
          AND fecha_intento > (NOW() - INTERVAL :minutos MINUTE)
          AND (identificador = :identificador OR ip = :ip)
    ");
    $stmt->execute([
        'minutos'       => VENTANA_MINUTOS,
        'identificador' => $identificador,
        'ip'            => $ip
    ]);
    return (int)$stmt->fetchColumn() >= MAX_INTENTOS;
}

function registrar_intento(PDO $pdo, string $identificador, string $ip, bool $exitoso): void
{
    $stmt = $pdo->prepare("INSERT INTO login_intentos (identificador, ip, exitoso) VALUES (?, ?, ?)");
    $stmt->execute([$identificador, $ip, $exitoso ? 1 : 0]);
}

function limpiar_intentos(PDO $pdo, string $identificador): void
{
    $stmt = $pdo->prepare("DELETE FROM login_intentos WHERE identificador = ? AND exitoso = 0");
    $stmt->execute([$identificador]);
}

function limpieza_diaria_respaldo(PDO $pdo): void
{
    $marcador = __DIR__ . '/storage/.ultima_limpieza_login';
    if (!is_dir(dirname($marcador))) {
        @mkdir(dirname($marcador), 0755, true);
    }
    $ultima = @file_exists($marcador) ? (int)@file_get_contents($marcador) : 0;
    if (time() - $ultima < 86400) {
        return;
    }
    try {
        $pdo->exec("DELETE FROM login_intentos WHERE fecha_intento < (NOW() - INTERVAL 1 DAY)");
        @file_put_contents($marcador, (string)time());
    } catch (Exception $e) {}
}

limpieza_diaria_respaldo($pdo);

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Método HTTP no permitido.']);
    exit;
}

$inputJSON = file_get_contents("php://input");
$data = json_decode($inputJSON, true);

$usuario = trim($data['usuario'] ?? '');
$password = $data['password'] ?? '';
$ip = obtener_ip_cliente();

$mensaje = "";

if ($usuario === '' || $password === '') {
    $mensaje = "Ingresa tu usuario y contraseña.";
} elseif (esta_bloqueado($pdo, $usuario, $ip)) {
    $mensaje = "Demasiados intentos fallidos. Por seguridad, espera " . BLOQUEO_MINUTOS . " minutos antes de volver a intentar.";
} else {
    $sql = "SELECT * FROM usuarios WHERE correo = :user_email OR codigo = :user_code";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_email' => $usuario, 'user_code'  => $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        if ($user['estatus'] !== 'ACTIVO') {
            $mensaje = "Tu cuenta está desactivada. Contacta al administrador.";
            registrar_intento($pdo, $usuario, $ip, false);
        } else {
            limpiar_intentos($pdo, $usuario);
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['usuario_id'];
            $_SESSION['nombre'] = $user['nombre'];
            if (isset($user['apellido_paterno'])) {
                $_SESSION['apellido_paterno'] = $user['apellido_paterno'];
                $_SESSION['apellido_materno'] = $user['apellido_materno'];
            } else {
                $_SESSION['apellidos'] = $user['apellidos'];
            }
            $_SESSION['rol'] = $user['rol'];

            $redirectUrl = '';
            switch ($user['rol']) {
                case 'ADMIN': $redirectUrl = "admin/usuarios.php"; break;
                case 'PROFESOR': $redirectUrl = "profesor/index.php"; break;
                case 'ALUMNO': $redirectUrl = "estudiante/index.php"; break;
                default: $mensaje = "Rol no identificado."; break;
            }

            if ($redirectUrl !== '') {
                echo json_encode(['success' => true, 'redirect' => $redirectUrl]);
                exit;
            }
        }
    } else {
        registrar_intento($pdo, $usuario, $ip, false);
        $mensaje = "Credenciales incorrectas.";
    }
}

echo json_encode(['success' => false, 'message' => $mensaje]);
exit;
