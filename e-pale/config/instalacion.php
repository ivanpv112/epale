<?php
/*
* config/instalacion.php
* Archivo de instalación CORREGIDO para E-Pale.
*/

$host = 'localhost';
$user = 'root';
$pass = 'Mikasa09'; // Tu contraseña

try {
    // 1. Conectamos SOLO al servidor MySQL
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h3>🚀 Iniciando instalación de E-Pale...</h3>";

    // 2. Crear la Base de Datos 'epale_db'
    $sqlDB = "CREATE DATABASE IF NOT EXISTS epale_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $pdo->exec($sqlDB);
    echo "✔ Base de datos 'epale_db' verificada.<br>";

    // 3. Seleccionar la Base de Datos
    $pdo->exec("USE epale_db");

    // 4. Crear Tablas

    // --- Tabla USUARIOS ---
    $sqlUsers = "CREATE TABLE IF NOT EXISTS users (
        id_user INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(20) UNIQUE NULL, 
        nombre VARCHAR(100) NOT NULL,
        apellidos VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        rol ENUM('admin', 'profesor', 'estudiante') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sqlUsers);
    echo "✔ Tabla 'users' verificada.<br>";

    // --- Tabla MATERIAS ---
    $sqlMaterias = "CREATE TABLE IF NOT EXISTS materias (
        id_materia INT AUTO_INCREMENT PRIMARY KEY,
        nombre_materia VARCHAR(100) NOT NULL,
        id_profesor INT,
        FOREIGN KEY (id_profesor) REFERENCES users(id_user) ON DELETE SET NULL
    )";
    $pdo->exec($sqlMaterias);
    echo "✔ Tabla 'materias' verificada.<br>";

    // --- Tabla CALIFICACIONES (CORREGIDA) ---
    // Cambiamos DATE por TIMESTAMP para evitar el error de sintaxis
    $sqlCalificaciones = "CREATE TABLE IF NOT EXISTS calificaciones (
        id_calificacion INT AUTO_INCREMENT PRIMARY KEY,
        id_estudiante INT NOT NULL,
        id_materia INT NOT NULL,
        calificacion DECIMAL(5, 2) NOT NULL CHECK (calificacion >= 0 AND calificacion <= 100),
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_estudiante) REFERENCES users(id_user) ON DELETE CASCADE,
        FOREIGN KEY (id_materia) REFERENCES materias(id_materia) ON DELETE CASCADE
    )";
    $pdo->exec($sqlCalificaciones);
    echo "✔ Tabla 'calificaciones' creada correctamente.<br>";


    // 5. Insertar Datos de Prueba
    $passHash = password_hash('12345', PASSWORD_DEFAULT);

    // Insertar Admin
    $sqlAdmin = "INSERT IGNORE INTO users (nombre, apellidos, email, password, rol) 
                 VALUES ('Admin', 'Principal', 'admin@epale.com', '$passHash', 'admin')";
    $pdo->exec($sqlAdmin);

    // Insertar Profesor
    $sqlProfe = "INSERT IGNORE INTO users (nombre, apellidos, email, password, rol) 
                 VALUES ('Juan', 'Perez', 'profe@epale.com', '$passHash', 'profesor')";
    $pdo->exec($sqlProfe);

    // Insertar Alumno
    $sqlAlumno = "INSERT IGNORE INTO users (codigo, nombre, apellidos, email, password, rol) 
                  VALUES ('2026A', 'Luis', 'Lopez', 'alumno@epale.com', '$passHash', 'estudiante')";
    $pdo->exec($sqlAlumno);

    echo "✔ Usuarios de prueba creados (Pass: 12345).<br>";
    echo "<hr><h3>✅ ¡Instalación de E-Pale Finalizada con Éxito!</h3>";
    echo "<p>Ahora ve a <a href='../index.php'>Inicio</a> para crear el Login.</p>";

} catch (PDOException $e) {
    die("❌ Error Fatal: " . $e->getMessage());
}
?>