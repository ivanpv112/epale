<?php
session_start();
// Seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}
$nombre_completo = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : "Estudiante";
if(isset($_SESSION['apellidos'])) { $nombre_completo .= " " . $_SESSION['apellidos']; }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard Estudiante | E-Pale</title>
    
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <header class="main-header">
        
        <div class="logo-container">
            <img src="../img/logo-pale.png" alt="E-PALE" class="logo-img">
            <span>e-PALE</span>
            
            </div>
        
        <nav class="main-nav">
            <ul>
                <li><a href="#"><i class="far fa-calendar-alt"></i> Horario</a></li>
                <li><a href="#"><i class="fas fa-star"></i> Calificaciones</a></li>
                <li><a href="#"><i class="fas fa-book"></i> Materias</a></li>
                <li><a href="#"><i class="fas fa-bullhorn"></i> Oferta</a></li>
            </ul>
        </nav>

        <div class="user-actions">
            <a href="perfil.php" class="profile-btn">
                <i class="fas fa-user-circle"></i>
                <span><?php echo strtok($_SESSION['nombre'], " "); ?></span>
            </a>
            
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Salir
            </a>
        </div>
    </header>

    <main class="main-content">
        
        <div class="welcome-banner">
            <h2>Bienvenido a tu portal</h2>
            <h1><?php echo htmlspecialchars($nombre_completo); ?></h1>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <h3><i class="fas fa-clipboard-check"></i> Evaluación Continua</h3>
                <div class="grades-summary">
                    <div class="grade-item">
                        <div class="grade-label"><i class="fas fa-pen-alt"></i> Quizzes (3)</div>
                        <div class="grade-value">25 / 30 pts</div>
                    </div>
                    <div class="progress-mini"><div class="progress-bar" style="width: 83%"></div></div>
                </div>
            </div>

            <div class="card">
                <h3><i class="far fa-clock"></i> Clases de Hoy</h3>
                <ul class="next-classes-list">
                    <li><span>Inglés IV - AULA 202</span><span class="grade-value">14:00 - 16:00</span></li>
                </ul>
                <div style="margin-top: 25px; text-align: center;">
                    <button style="width: 100%; padding: 12px; border: 1px solid #ddd; background:white; border-radius: 8px;">Ver Horario Completo</button>
                </div>
            </div>
        </div>

    </main>

    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-section">CUCEA PALE</div>
            <div class="footer-section"><img src="../img/logo-udg.png" alt="UDG" class="footer-logo-img"></div>
            <div class="footer-section">plataforma.pale@cucea.udg.mx</div>
        </div>
        <div class="address-bar">
            © 2026 E-PALE
        </div>
    </footer>

</body>
</html>