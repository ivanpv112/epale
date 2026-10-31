<?php
// index.php - Login
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
require 'db.php';

$mensaje = "";
$mensaje_tipo = "error";

// Si venimos de un intento fallido de login con Google, mostramos ese mensaje.
if (!empty($_SESSION['login_error'])) {
    $mensaje = $_SESSION['login_error'];
    $mensaje_tipo = "error";
    unset($_SESSION['login_error']);
} elseif (!empty($_SESSION['login_success'])) {
    $mensaje = $_SESSION['login_success'];
    $mensaje_tipo = "success";
    unset($_SESSION['login_success']);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | E-PALE</title>
    <!-- Identidad visual e íconos para móviles y biometría / WebAuthn -->
    <link rel="icon" type="image/png" href="img/isotipo-pale.png">
    <link rel="apple-touch-icon" href="img/isotipo-pale.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#001a57">

    <link rel="stylesheet" href="css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="login-body">

    <header class="login-header">
        <img src="img/imagotipo-pale-login.png" alt="Logo E-PALE">
    </header>
    <div class="login-card">
        <h2>Iniciar Sesión</h2>
        <?php if (!empty($mensaje) && $mensaje_tipo === 'success'): ?>
            <div id="successMessage" class="success-msg">
                <i class="fas fa-check-circle"></i> <span><?php echo htmlspecialchars($mensaje); ?></span>
            </div>
        <?php endif; ?>
        <div id="errorMessage" class="error-msg <?php echo (empty($mensaje) || $mensaje_tipo !== 'error') ? 'hidden' : ''; ?>">
            <i class="fas fa-exclamation-circle"></i> <span><?php echo ($mensaje_tipo === 'error') ? htmlspecialchars($mensaje) : ''; ?></span>
        </div>
        <form id="loginForm">
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
        <div class="login-divider"><span>o</span></div>
        <div class="social-login-pills">
            <button type="button" id="btnBiometricLogin" class="login-pill btn-biometric hidden">
                <i class="fas fa-fingerprint"></i>
                <span>Passkey</span>
            </button>
            <a href="google_auth.php" class="login-pill btn-google">
                <i class="fab fa-google"></i>
                <span>Continuar con Google</span>
            </a>
        </div>
        <div class="forgot-password-box">
            <a href="recuperar_password" class="forgot-password-link">
                ¿Olvidaste tu contraseña?
            </a>
        </div>
    </div>
    <footer class="login-footer">© 2026 E-PALE - Universidad de Guadalajara - CUCEA</footer>
    <script>
        function togglePassword() {
            var x = document.getElementById("password");
            var icon = document.querySelector(".toggle-password");
            if (x.type === "password") {
                x.type = "text";
                icon.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                x.type = "password";
                icon.classList.replace("fa-eye-slash", "fa-eye");
            }
        }
    </script>
    <script src="js/webauthn.js"></script>
    <script src="js/login_animation.js"></script>
</body>

</html>