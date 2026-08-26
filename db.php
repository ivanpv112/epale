<?php
// db.php - Conexión oficial al proyecto E-Pale

// 1. Intentar cargar variables desde un archivo .env local (Modo Desarrollo)
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $env = parse_ini_file($env_file);
    $host = $env['DB_HOST'] ?? 'localhost';
    $db   = $env['DB_NAME'] ?? '';
    $user = $env['DB_USER'] ?? '';
    $pass = $env['DB_PASS'] ?? '';
    $charset = $env['DB_CHARSET'] ?? 'utf8mb4';
} else {
    // 2. Si no hay .env, usar variables de entorno nativas del servidor (Modo Producción)
    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // REGLA DE SEGURIDAD: Jamás mostrar el error real en pantalla.
    // Lo guardamos en el log interno del servidor para que tú lo veas, 
    // pero al usuario le mostramos un mensaje genérico.
    error_log("Error de conexión PDO: " . $e->getMessage()); 
    die("Error crítico: No se pudo establecer conexión con la base de datos.");
}
?>
