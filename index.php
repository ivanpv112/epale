<?php
// index.php - Login Actualizado para Nueva BD
session_start();
require 'db.php';

$mensaje = "";

// Respaldo: intenta la limpieza diaria en cada visita (el marcador de archivo
// asegura que la operación de borrado real solo ocurra una vez cada 24h).
limpieza_diaria_respaldo($pdo);

// =========================================================
// CONFIGURACIÓN DE PROTECCIÓN CONTRA FUERZA BRUTA
// =========================================================
const MAX_INTENTOS    = 5;   // intentos fallidos permitidos
const VENTANA_MINUTOS = 15;  // ventana de tiempo que se revisa
const BLOQUEO_MINUTOS = 15;  // tiempo de bloqueo una vez superado el máximo

function obtener_ip_cliente(): string {
    // X-Forwarded-For solo es confiable si tienes un proxy/load balancer que lo
    // sobrescribe; si tu hosting recibe la conexión directa, usa REMOTE_ADDR.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function esta_bloqueado(PDO $pdo, string $identificador, string $ip): bool {
    // Cuenta intentos fallidos recientes por usuario+IP y por IP en general
    // (frena tanto ataques dirigidos a una cuenta como fuerza bruta distribuida
    // de una misma IP contra varias cuentas).
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

function registrar_intento(PDO $pdo, string $identificador, string $ip, bool $exitoso): void {
    $stmt = $pdo->prepare("INSERT INTO login_intentos (identificador, ip, exitoso) VALUES (?, ?, ?)");
    $stmt->execute([$identificador, $ip, $exitoso ? 1 : 0]);
}

function limpiar_intentos(PDO $pdo, string $identificador): void {
    // Al iniciar sesión con éxito borramos su historial de fallos, para que el
    // contador no arrastre intentos viejos hacia el próximo posible bloqueo.
    $stmt = $pdo->prepare("DELETE FROM login_intentos WHERE identificador = ? AND exitoso = 0");
    $stmt->execute([$identificador]);
}

function limpieza_diaria_respaldo(PDO $pdo): void {
    // Respaldo por si el Event Scheduler de MySQL está desactivado en el hosting
    // (común en hosting compartido). Se controla con un archivo marcador para
    // que la limpieza real solo corra una vez cada 24h, sin importar cuántas
    // visitas reciba la página mientras tanto.
    $marcador = __DIR__ . '/storage/.ultima_limpieza_login';
    if (!is_dir(dirname($marcador))) {
        @mkdir(dirname($marcador), 0755, true);
    }

    $ultima = @file_exists($marcador) ? (int)@file_get_contents($marcador) : 0;
    if (time() - $ultima < 86400) {
        return; // ya se limpió hace menos de 24h, no hacer nada
    }

    try {
        $pdo->exec("DELETE FROM login_intentos WHERE fecha_intento < (NOW() - INTERVAL 1 DAY)");
        @file_put_contents($marcador, (string)time());
    } catch (Exception $e) {
        // Si falla (p.ej. la tabla aún no existe), no interrumpe el login.
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = obtener_ip_cliente();

    if ($usuario === '' || $password === '') {
        $mensaje = "Ingresa tu usuario y contraseña.";
    } elseif (esta_bloqueado($pdo, $usuario, $ip)) {
        $mensaje = "Demasiados intentos fallidos. Por seguridad, espera " . BLOQUEO_MINUTOS . " minutos antes de volver a intentar.";
    } else {
        // CAMBIO: Tabla 'usuarios', columna 'usuario_id'
        $sql = "SELECT * FROM usuarios WHERE correo = :user_email OR codigo = :user_code";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'user_email' => $usuario,
            'user_code'  => $usuario
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            // Verificar si está activo
            if ($user['estatus'] !== 'ACTIVO') {
                $mensaje = "Tu cuenta está desactivada. Contacta al administrador.";
                registrar_intento($pdo, $usuario, $ip, false);
            } else {
                limpiar_intentos($pdo, $usuario);
                session_regenerate_id(true); // evita fijación de sesión al autenticar

                // Guardar datos en sesión (Usando los nuevos nombres de columna)
                $_SESSION['user_id'] = $user['usuario_id']; // Mantenemos 'user_id' en la sesión para no romper otros archivos
                $_SESSION['nombre'] = $user['nombre'];
                // Compatible con ambas estructuras: nueva y antigua
                if (isset($user['apellido_paterno'])) {
                    $_SESSION['apellido_paterno'] = $user['apellido_paterno'];
                    $_SESSION['apellido_materno'] = $user['apellido_materno'];
                } else {
                    $_SESSION['apellidos'] = $user['apellidos'];
                }
                $_SESSION['rol'] = $user['rol']; // Ahora será 'ADMIN', 'ALUMNO', etc.

                // Redirección (Ojo con las mayúsculas)
                switch ($user['rol']) {
                    case 'ADMIN':
                        header("Location: admin/usuarios.php");
                        break;
                    case 'PROFESOR':
                        header("Location: profesor/index.php");
                        break;
                    case 'ALUMNO':
                        header("Location: estudiante/index.php");
                        break;
                    default:
                        $mensaje = "Rol no identificado.";
                }
                exit;
            }
        } else {
            registrar_intento($pdo, $usuario, $ip, false);
            $mensaje = "Credenciales incorrectas.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Iniciar Sesión | E-PALE</title>
    <link rel="stylesheet" href="css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="login-header">
        <img src="img/logo-pale.png" alt="Logo E-PALE"> 
        <h1>e-PALE</h1>
        <p>Plataforma de Aprendizaje de Lenguas Extranjeras</p>
    </header>
    <div class="login-card">
        <h2>Iniciar Sesión</h2>
        <?php if(!empty($mensaje)): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo $mensaje; ?></div>
        <?php endif; ?>
        <form method="POST" action="index.php">
            <div class="form-group">
                <label for="usuario">Correo Institucional o Código</label>
                <input type="text" id="usuario" name="usuario" placeholder="usuario@cucea.udg.mx" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required>
                <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
            </div>
            <button type="submit" class="btn-login">Ingresar</button>
        </form>
    </div>
    <footer class="login-footer">© 2026 E-PALE - Universidad de Guadalajara - CUCEA</footer>
    <script>
        function togglePassword() {
            var x = document.getElementById("password");
            var icon = document.querySelector(".toggle-password");
            if (x.type === "password") { x.type = "text"; icon.classList.replace("fa-eye", "fa-eye-slash"); } 
            else { x.type = "password"; icon.classList.replace("fa-eye-slash", "fa-eye"); }
        }
    </script>
</body>

</html>
