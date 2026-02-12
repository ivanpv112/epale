<?php
// estudiante/dashboard.php
session_start();

// Verificar sesión
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../index.php");
    exit;
}

$nombre_completo = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : "Estudiante";
if(isset($_SESSION['apellidos'])) {
    $nombre_completo .= " " . $_SESSION['apellidos'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Estudiante | E-Pale</title>
    
    <link rel="stylesheet" href="/e-pale/css/estudiante.css">

    <link rel="stylesheet" href="../css/estudiante.css">
</head>
<body>

    <header class="main-header">
        <div class="logo-container">
            <img src="../img/logo-pale.png" alt="E-PALE" class="logo-img">
        </div>
        
        <nav class="main-nav">
            <ul>
                <li><a href="#">Horario</a></li>
                <li><a href="#">Calificaciones</a></li>
                <li><a href="#">Alta de Materias</a></li>
                <li><a href="#">Cita UAAL</a></li>
                <li><a href="#">Oferta</a></li>
            </ul>
        </nav>

        <div class="user-profile-header">
            <i class="fas fa-user-circle"></i>
            <span>PERFIL</span>
        </div>
    </header>

    <main class="main-content">
        
        <div class="welcome-banner">
            <h2>Pagina de inicio</h2>
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

                    <div class="grade-item">
                        <div class="grade-label"><i class="fas fa-comment-dots"></i> Quizzes Orales (2)</div>
                        <div class="grade-value">18 / 20 pts</div>
                    </div>
                    <div class="progress-mini"><div class="progress-bar" style="width: 90%"></div></div>

                    <div class="grade-item">
                        <div class="grade-label"><i class="fas fa-file-alt"></i> Writing Project</div>
                        <div class="grade-value">Pendiente</div>
                    </div>
                     <div class="progress-mini"><div class="progress-bar" style="width: 0%; background: #ddd;"></div></div>

                    <div class="grade-item">
                        <div class="grade-label"><i class="fas fa-laptop-code"></i> Plataforma Moodle</div>
                        <div class="grade-value">20 / 40pts</div>
                    </div>
                     <div class="progress-mini"><div class="progress-bar" style="width: 50%"></div></div>

                    <div class="grade-item">
                        <div class="grade-label"><i class="fas fa-hand-paper"></i> Participación</div>
                        <div class="grade-value">10 / 10 pts</div>
                    </div>
                     <div class="progress-mini"><div class="progress-bar" style="width: 100%"></div></div>
                </div>
            </div>

            <div class="card">
                <h3><i class="far fa-calendar-alt"></i> Próximas Clases (Hoy)</h3>
                <ul class="next-classes-list">
                    <li>
                        <span>Inglés IV - AULA 202</span>
                        <span class="class-time">14:00 - 16:00</span>
                    </li>
                    <li>
                        <span>Laboratorio de Cómputo</span>
                        <span class="class-time">16:00 - 18:00</span>
                    </li>
                </ul>
                <div style="margin-top: 20px; text-align: center;">
                    <button style="width: auto; padding: 8px 20px;">Ver Horario Completo</button>
                </div>
            </div>

             <div class="card">
                <h3><i class="fas fa-bullhorn"></i> Avisos</h3>
                <p style="color: #666; font-size: 0.9rem;">
                    <strong>Recordatorio:</strong> La fecha límite para subir el Writing Project es el próximo viernes a las 23:59.
                </p>
                <hr style="border: 0; border-top: 1px solid #eee; margin: 10px 0;">
                <p style="color: #666; font-size: 0.9rem;">
                    <strong>Aviso:</strong> Mantenimiento de la plataforma Moodle este sábado.
                </p>
            </div>
        </div>

    </main>

    <footer class="main-footer">
        <div class="footer-content">
            
            <div class="footer-section">
                <div class="footer-logo-placeholder">CUCEA PALE</div>
                <div style="margin-top: 15px;">
                    <a href="https://facebook.com" target="_blank">
                        <img src="../img/facebook.png" alt="Facebook" class="social-icon-img">
                    </a>
                </div>
            </div>

            <div class="footer-section">
                <img src="../img/logo-udg.png" alt="Universidad de Guadalajara" class="footer-logo-img">
            </div>

            <div class="footer-section">
                <strong>Contacto</strong><br>
                +52 (33)-3770-3300<br>
                plataforma.pale@cucea.udg.mx
            </div>
        </div>
        
        <div class="address-bar">
            Periférico Norte N° 799, Núcleo Universitario Los Belenes, C.P. 45100, Zapopan, Jalisco, México.<br>
            Copyright © 2026 E-PALE
        </div>
    </footer>

</body>
</html>