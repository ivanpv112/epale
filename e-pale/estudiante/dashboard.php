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
    <title>Inicio | E-Pale</title>
    
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
            <h2>Página de inicio</h2>
            <h1><?php echo htmlspecialchars($nombre_completo); ?></h1>
        </div>

        <div class="dashboard-grid">
            
            <div class="card">
                <h3><i class="fas fa-chart-line"></i> Evaluación Continua</h3>
                
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
                        <div class="grade-value" style="background:#fff3cd; color:#856404; padding:2px 8px; border-radius:10px; font-size:0.8rem;">Pendiente</div>
                    </div>
                    <div class="progress-mini"><div class="progress-bar" style="width: 0%; background:#e9ecef;"></div></div>

                    <div class="grade-item">
                        <div class="grade-label"><i class="fas fa-laptop-code"></i> Plataforma Moodle</div>
                        <div class="grade-value">20 / 40 pts</div>
                    </div>
                    <div class="progress-mini"><div class="progress-bar" style="width: 50%; background-color:#ffc107;"></div></div>

                    <div class="grade-item">
                        <div class="grade-label"><i class="fas fa-hand-paper"></i> Participación</div>
                        <div class="grade-value">10 / 10 pts</div>
                    </div>
                    <div class="progress-mini"><div class="progress-bar" style="width: 100%"></div></div>
                </div>
            </div>

            <div class="card">
                <h3><i class="far fa-clock"></i> Próximas Clases (Hoy)</h3>
                <ul class="next-classes-list">
                    <li>
                        <span>Inglés IV - AULA 202</span>
                        <span class="grade-value">14:00 - 16:00</span>
                    </li>
                    <li>
                        <span>Laboratorio de Cómputo</span>
                        <span class="grade-value">16:00 - 18:00</span>
                    </li>
                </ul>
                <div style="margin-top: 25px; text-align: center;">
                    <button style="width: auto; padding: 10px 20px; background-color: white; border: 1px solid #ddd; border-radius: 6px; font-weight:bold; cursor:pointer; color: #555; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                        Ver Horario Completo
                    </button>
                </div>
            </div>

             <div class="card">
                <h3><i class="far fa-bell"></i> Avisos</h3>
                <div style="font-size: 0.9rem; color: #555;">
                    <p style="margin-bottom: 15px; padding-bottom:15px; border-bottom:1px solid #eee;">
                        <strong style="color: #002677;">Recordatorio:</strong> La fecha límite para subir el Writing Project es el próximo viernes a las 23:59.
                    </p>
                    <p style="margin-bottom: 15px; padding-bottom:15px; border-bottom:1px solid #eee;">
                        <strong>Aviso:</strong> Mantenimiento de la plataforma Moodle este sábado.
                    </p>
                    <p>
                        <span style="color:#666; font-size:0.85rem;">Información: Las inscripciones para el siguiente semestre abren el 3 de marzo.</span>
                    </p>
                </div>
            </div>
        </div>

    </main>

    <footer class="main-footer">
        <div class="footer-content">
            <div class="footer-section">
                <div style="font-weight:bold; font-size:1.1rem; margin-bottom:5px;">CUCEA PALE</div>
                <i class="fab fa-facebook" style="opacity:0.8;"></i>
            </div>
            <div class="footer-section">
                 <img src="../img/logo-udg.png" alt="Universidad de Guadalajara" class="footer-logo-img">
                 <div style="font-size:0.8rem; margin-top:5px; font-weight:bold;">UNIVERSIDAD DE<br>GUADALAJARA</div>
            </div>
            <div class="footer-section">
                <strong>Contacto</strong><br>
                +52 (33)-3770-3300<br>
                <span style="font-size:0.85rem; opacity:0.9;">plataforma.pale@cucea.udg.mx</span>
            </div>
        </div>
        <div class="address-bar">
            Periférico Norte N° 799, Núcleo Universitario Los Belenes, C.P. 45100, Zapopan, Jalisco, México.<br>
            Copyright © 2026 E-PALE
        </div>
    </footer>

</body>
</html>
