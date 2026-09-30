<?php
session_start();
require '../db.php';
require '../security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index"); 
    exit; 
}

// Obtener rol y género del administrador para saludo personalizado
$stmt_admin = $pdo->prepare("SELECT rol, genero FROM usuarios WHERE usuario_id = ?");
$stmt_admin->execute([$_SESSION['user_id']]);
$admin_info = $stmt_admin->fetch(PDO::FETCH_ASSOC);

$rol_admin = $admin_info['rol'] ?? 'ADMIN';
$genero_admin = $admin_info['genero'] ?? '';

// Mapeo temporal del rol para visualización
$rol_mostrar = $rol_admin;
if ($rol_admin === 'ADMIN') {
    $rol_mostrar = 'Administrador General';
}

$saludo = "Bienvenid@";
if ($genero_admin === 'MASCULINO') {
    $saludo = "Bienvenido";
} elseif ($genero_admin === 'FEMENINO') {
    $saludo = "Bienvenida";
}

// Extraer el primer nombre y apellido
$nombres = explode(' ', $_SESSION['nombre'] ?? '');
$primer_nombre = $nombres[0];
$apellido_paterno = $_SESSION['apellido_paterno'] ?? '';
$nombre_mostrar = trim($primer_nombre . ' ' . $apellido_paterno);
if (empty($nombre_mostrar)) {
    $nombre_mostrar = 'Admin';
}

// Obtener el total de inicios de sesión de HOY por rol (contando múltiples logins de un mismo usuario)
$sql = "SELECT u.rol, COUNT(l.intento_id) as total 
        FROM login_intentos l 
        JOIN usuarios u ON (l.identificador = u.correo OR l.identificador = u.codigo)
        WHERE DATE(l.fecha_intento) = CURDATE() AND l.exitoso = 1
        GROUP BY u.rol";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$logins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logins_hoy = [
    'ALUMNO' => 0,
    'PROFESOR' => 0,
    'ADMIN' => 0
];
$total_activos_hoy = 0;

foreach ($logins as $row) {
    $rol = $row['rol'];
    $count = (int)$row['total'];
    if (isset($logins_hoy[$rol])) {
        $logins_hoy[$rol] = $count;
        $total_activos_hoy += $count;
    }
}

// Formateo de fecha
date_default_timezone_set('America/Mexico_City');
$fecha_formateada = date('d/m/Y');
if (class_exists('IntlDateFormatter')) {
    $fmt = new IntlDateFormatter('es_MX', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'America/Mexico_City', IntlDateFormatter::GREGORIAN);
    $fecha_formateada = ucfirst($fmt->format(new DateTime()));
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Panel de Control | Admin</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div class="dashboard-banner">
            <div class="badge" style="float: right; margin-top: 5px;"><?php echo htmlspecialchars($rol_mostrar); ?></div>
            <div class="badge">E-PALE · CUCEA</div>
            <h1><?php echo $saludo; ?>, <?php echo htmlspecialchars($nombre_mostrar); ?></h1>
            <p><?php echo $fecha_formateada; ?><br><small style="opacity: 0.8; font-size: 0.9em; margin-top: 5px; display: inline-block;"><i class="far fa-clock"></i> <span id="reloj-tiempo-real"></span></small></p>
        </div>

        <h3 class="section-title-border" style="margin-bottom: 20px;">Hoy se realizaron:</h3>

        <div class="dashboard-cards">
            <!-- Logins de Alumnos Hoy -->
            <div class="dash-card card-alumnos">
                <div class="card-icon-wrap"><i class="fas fa-user-graduate"></i></div>
                <h3><?php echo $logins_hoy['ALUMNO']; ?></h3>
                <p>Logins de Alumnos</p>
            </div>

            <!-- Logins de Profesores Hoy -->
            <div class="dash-card card-profesores">
                <div class="card-icon-wrap"><i class="fas fa-chalkboard-teacher"></i></div>
                <h3><?php echo $logins_hoy['PROFESOR']; ?></h3>
                <p>Logins de Profesores</p>
            </div>

            <!-- Logins de Administradores Hoy -->
            <div class="dash-card card-admins">
                <div class="card-icon-wrap"><i class="fas fa-user-shield"></i></div>
                <h3><?php echo $logins_hoy['ADMIN']; ?></h3>
                <p>Logins de Administradores</p>
            </div>
            
            <!-- Total de Inicios de Sesión Hoy -->
            <div class="dash-card card-total">
                <div class="card-icon-wrap"><i class="fas fa-sign-in-alt"></i></div>
                <h3><?php echo $total_activos_hoy; ?></h3>
                <p>Total Logins Hoy</p>
            </div>
        </div>
    </main>

    <script>
        function actualizarReloj() {
            const ahora = new Date();
            let horas = ahora.getHours();
            let minutos = ahora.getMinutes();
            let segundos = ahora.getSeconds();
            const ampm = horas >= 12 ? 'PM' : 'AM';
            
            horas = horas % 12;
            horas = horas ? horas : 12;
            minutos = minutos < 10 ? '0' + minutos : minutos;
            segundos = segundos < 10 ? '0' + segundos : segundos;
            
            const horaTexto = horas + ':' + minutos + ':' + segundos + ' ' + ampm;
            const reloj = document.getElementById('reloj-tiempo-real');
            if (reloj) reloj.textContent = horaTexto;
        }
        setInterval(actualizarReloj, 1000);
        actualizarReloj();
    </script>
</body>
</html>
