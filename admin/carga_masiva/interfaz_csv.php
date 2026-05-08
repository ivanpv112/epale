<?php
session_start();
require '../db.php';

// Validar seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php"); exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga Masiva CSV | Admin E-PALE</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .module-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; max-width: 1000px; margin: 0 auto; }
        .module-card { background: white; border-radius: 12px; padding: 30px 20px; text-align: center; border: 1px solid #eee; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-decoration: none; color: #333; transition: all 0.3s; display: block; }
        .module-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); border-color: var(--udg-blue); }
        .module-card i { font-size: 3.5rem; color: var(--udg-light); margin-bottom: 15px; transition: color 0.3s; }
        .module-card:hover i { color: var(--udg-blue); }
        .module-card h3 { margin: 0 0 10px 0; color: var(--udg-blue); font-size: 1.3rem; }
        .module-card p { margin: 0; color: #666; font-size: 0.9rem; line-height: 1.5; }
    </style>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <a href="usuarios.php" style="display: inline-block; margin-bottom: 20px; color: var(--udg-blue); text-decoration: none; font-weight: bold;">
            <i class="fas fa-arrow-left"></i> Volver a Usuarios
        </a>

        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-database"></i> Módulos de Importación Masiva</h1>
            <p>Selecciona el tipo de datos que deseas cargar al sistema mediante archivo CSV.</p>
        </div>

        <div class="module-grid">
            <a href="vista_csv_alumnos.php" class="module-card">
                <i class="fas fa-user-graduate"></i>
                <h3>Importar Alumnos</h3>
                <p>Carga cuentas de estudiantes con auto-generación de contraseñas y asignación de carrera.</p>
            </a>

            <a href="vista_csv_profesores.php" class="module-card">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>Importar Profesores</h3>
                <p>Carga perfiles de docentes incluyendo nacionalidad, experiencia y generación de accesos.</p>
            </a>

            <a href="vista_csv_diagnosticos.php" class="module-card">
                <i class="fas fa-clipboard-check"></i>
                <h3>Exámenes Diagnósticos</h3>
                <p>Alimenta el historial académico de los alumnos con sus resultados de ubicación inicial.</p>
            </a>
            
            </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
    </script>
</body>
</html>