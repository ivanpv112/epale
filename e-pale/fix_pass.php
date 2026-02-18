<?php
require 'db.php';

// Generar el hash correcto para "12345"
$password_correcta = password_hash("12345", PASSWORD_DEFAULT);

try {
    // Actualizar TODOS los usuarios con esta contraseña
    $sql = "UPDATE usuarios SET password = :p";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['p' => $password_correcta]);
    
    echo "<h1>¡Listo!</h1>";
    echo "<p>Todas las contraseñas se han restablecido a: <strong>12345</strong></p>";
    echo "<p>Ahora borra este archivo y <a href='index.php'>intenta iniciar sesión</a>.</p>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>