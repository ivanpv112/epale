<?php
// index.php - Login del Sistema E-Pale
session_start();
require 'db.php';

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $usuario = $_POST['usuario']; // Puede ser email o código
    $password = $_POST['password'];

    // CORRECCIÓN: Usamos dos marcadores diferentes (:user_email y :user_code)
    // para evitar el error de "Invalid parameter number".
    $sql = "SELECT * FROM users WHERE email = :user_email OR codigo = :user_code";
    
    $stmt = $pdo->prepare($sql);
    
    // Enviamos el mismo dato ($usuario) a ambos marcadores
    $stmt->execute([
        'user_email' => $usuario,
        'user_code'  => $usuario
    ]);
    
    $user = $stmt->fetch();

    // 2. Verificamos contraseña
    if ($user && password_verify($password, $user['password'])) {
        // Login Exitoso
        $_SESSION['user_id'] = $user['id_user'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['rol'] = $user['rol'];
        
    if ($user && password_verify($password, $user['password'])) {
        // Login Exitoso
        $_SESSION['user_id'] = $user['id_user'];
        $_SESSION['nombre'] = $user['nombre'];
        
        // --- AGREGA ESTA LÍNEA ---
        $_SESSION['apellidos'] = $user['apellidos']; 
        // -------------------------

        $_SESSION['rol'] = $user['rol'];

        // ... resto del código ...

        // 3. Redirección según el ROL
        switch ($user['rol']) {
            case 'admin':
                header("Location: admin/dashboard.php");
                break;
            case 'profesor':
                header("Location: profesor/dashboard.php");
                break;
            case 'estudiante':
                header("Location: estudiante/dashboard.php");
                break;
            default:
                $mensaje = "Rol no identificado.";
        }
        exit;
    } else {
        $mensaje = "Usuario o contraseña incorrectos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | E-Pale</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

    <div class="login-container">
        <h2>Bienvenido a E-Pale</h2>
        
        <?php if(!empty($mensaje)): ?>
            <div class="error"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <input type="text" name="usuario" placeholder="Correo o Código de Estudiante" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Iniciar Sesión</button>
        </form>
        <p style="font-size: 12px; color: #666; margin-top: 20px;">
            Sistema de Gestión Escolar v1.0
        </p>
    </div>

</body>
</html>