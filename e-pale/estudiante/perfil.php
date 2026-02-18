<?php
session_start();
require '../db.php';

// 1. Seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../index.php"); exit;
}

// 2. Obtener datos frescos de la BD
$stmt = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$nombre_completo = $user['nombre'] . " " . $user['apellidos'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Perfil | E-Pale</title>
    <base href="http://localhost/e-pale/">
    <link rel="stylesheet" href="css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <header class="main-header">
        <div class="logo-container">
            <div style="display:flex; align-items:center; gap:10px;">
                <img src="img/logo-pale.png" alt="E-PALE" class="logo-img">
                <span>e-PALE</span>
            </div>
            
            <div class="user-profile-header" onclick="window.location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Salir
            </div>
        </div>
        
        <nav class="main-nav">
            <ul>
                <li><a href="estudiante/dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
                <li><a href="#"><i class="fas fa-star"></i> Calificaciones</a></li>
                <li><a href="#"><i class="fas fa-book"></i> Materias</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main class="main-content">
        
        <div class="profile-header-card">
            <div class="profile-avatar-container">
                <i class="fas fa-user profile-avatar-icon"></i>
            </div>
        </div>

        <div class="profile-basic-info">
            <h2 style="margin:0; color:var(--udg-blue);"><?php echo htmlspecialchars($nombre_completo); ?></h2>
            <span class="profile-role-badge"><i class="fas fa-graduation-cap"></i> Alumno</span>
            <p style="color:#666; margin-top:5px; font-size:0.9rem;"><?php echo $user['email']; ?></p>
        </div>

        <div class="profile-grid">
            
            <div class="card">
                <h3><i class="far fa-user"></i> Información Personal <i class="fas fa-pen" style="margin-left:auto; font-size:0.8rem; cursor:pointer; color:#ccc;"></i></h3>
                
                <div class="info-section">
                    <span class="info-label">Nombre Completo</span>
                    <span class="info-value"><?php echo htmlspecialchars($nombre_completo); ?></span>
                </div>
                
                <div class="info-section">
                    <span class="info-label">Correo Electrónico</span>
                    <span class="info-value"><?php echo $user['email']; ?></span>
                </div>

                <div class="info-section">
                    <span class="info-label">Teléfono</span>
                    <span class="info-value"><?php echo $user['telefono'] ? $user['telefono'] : 'No registrado'; ?></span>
                </div>

                <div class="info-section">
                    <span class="info-label">Dirección</span>
                    <span class="info-value">Zapopan, Jalisco</span> </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-graduation-cap"></i> Información Académica</h3>
                
                <div class="info-section">
                    <span class="info-label">Código de Estudiante</span>
                    <span class="info-value"><?php echo $user['codigo'] ? $user['codigo'] : '---'; ?></span>
                </div>

                <div class="info-section">
                    <span class="info-label">Carrera / Programa</span>
                    <span class="info-value"><?php echo $user['carrera'] ? $user['carrera'] : 'Lic. en Tecnologías de la Info.'; ?></span>
                </div>

                <div class="info-section">
                    <span class="info-label">Nivel Actual</span>
                    <span class="info-value">Inglés IV</span>
                </div>

                <div class="info-section">
                    <span class="info-label">Ciclo Escolar</span>
                    <span class="info-value"><?php echo $user['ciclo_escolar'] ? $user['ciclo_escolar'] : '2026-A'; ?></span>
                </div>
            </div>
        </div>

        <div class="card">
            <h3><i class="fas fa-history"></i> Historial de Calificaciones</h3>
            <div style="overflow-x:auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Materia</th>
                            <th>Ciclo</th>
                            <th>Calificación</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Inglés III</td>
                            <td>2025-B</td>
                            <td><strong>92</strong></td>
                            <td><span class="tag-aprobado">Aprobado</span></td>
                        </tr>
                        <tr>
                            <td>Inglés II</td>
                            <td>2025-A</td>
                            <td><strong>88</strong></td>
                            <td><span class="tag-aprobado">Aprobado</span></td>
                        </tr>
                        <tr>
                            <td>Inglés I</td>
                            <td>2024-B</td>
                            <td><strong>95</strong></td>
                            <td><span class="tag-aprobado">Aprobado</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <footer class="main-footer">
        <div class="address-bar">
            Periférico Norte N° 799, Núcleo Universitario Los Belenes, C.P. 45100, Zapopan, Jalisco, México.<br>
            © 2026 E-PALE
        </div>
    </footer>

</body>
</html>