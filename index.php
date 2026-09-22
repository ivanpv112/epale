<?php
// index.php - Login
session_start();
require 'db.php';

$mensaje = "";

// Si venimos de un intento fallido de login con Google, mostramos ese mensaje.
if (!empty($_SESSION['login_error'])) {
    $mensaje = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
// Ya no procesamos el form aquí ni chequeamos BD, todo está en login_process.php
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

<body class="login-body">


    <header class="login-header">
        <img src="img/imagotipo-pale-login.png" alt="Logo E-PALE">
    </header>
    <div class="login-card">
        <h2>Iniciar Sesión</h2>
        <div id="errorMessage" class="error-msg <?php echo empty($mensaje) ? 'hidden' : ''; ?>">
            <i class="fas fa-exclamation-circle"></i> <span><?php echo $mensaje; ?></span>
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
        <a href="#" class="btn-google" style="pointer-events: none; opacity: 0.5;" title="Próximamente">
            <i class="fab fa-google"></i>
            Continuar con Google
        </a>
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
    <script src="js/login_animation.js"></script>
</body>

</html>