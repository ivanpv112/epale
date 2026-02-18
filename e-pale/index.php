<?php
// index.php - Login Actualizado para Nueva BD
session_start();
require 'db.php';

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario']; 
    $password = $_POST['password'];

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
        if($user['estatus'] !== 'ACTIVO'){
             $mensaje = "Tu cuenta está desactivada. Contacta al administrador.";
        } else {
            // Guardar datos en sesión (Usando los nuevos nombres de columna)
            $_SESSION['user_id'] = $user['usuario_id']; // Mantenemos 'user_id' en la sesión para no romper otros archivos
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['apellidos'] = $user['apellidos'];
            $_SESSION['rol'] = $user['rol']; // Ahora será 'ADMIN', 'ALUMNO', etc.

            // Redirección (Ojo con las mayúsculas)
            switch ($user['rol']) {
                case 'ADMIN':
                    header("Location: admin/usuarios.php");
                    break;
                case 'PROFESOR':
                    header("Location: profesor/dashboard.php");
                    break;
                case 'ALUMNO':
                    header("Location: estudiante/dashboard.php");
                    break;
                default:
                    $mensaje = "Rol no identificado.";
            }
            exit;
        }
    } else {
        $mensaje = "Credenciales incorrectas.";
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
        <div class="demo-box">
            <span class="demo-title">Credenciales de demostración:</span>
            <div><strong>Alumno:</strong> alumno@epale.com / 12345</div>
            <div><strong>Profesor:</strong> profe@epale.com / 12345</div>
            <div><strong>Admin:</strong> admin@epale.com / 12345</div>
        </div>
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