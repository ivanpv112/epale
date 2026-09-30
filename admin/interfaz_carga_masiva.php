<?php
session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

// Validar seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga Masiva | Admin E-PALE</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-database"></i> Módulos de Importación Masiva</h1>
            <p>Selecciona el tipo de datos que deseas cargar al sistema mediante archivo CSV o PDF.</p>
        </div>

        <div class="module-grid">
            <a href="carga_masiva/vista_csv_alumnos" class="module-card">
                <i class="fas fa-user-graduate"></i>
                <h3>Importar Alumnos</h3>
                <p>Carga cuentas de estudiantes con auto-generación de contraseñas y asignación de carrera.</p>
            </a>

            <a href="carga_masiva/vista_csv_profesores" class="module-card">
                <i class="fas fa-chalkboard-teacher"></i>
                <h3>Importar Profesores</h3>
                <p>Carga perfiles de docentes incluyendo nacionalidad, experiencia y generación de accesos.</p>
            </a>

            <a href="carga_masiva/vista_csv_diagnosticos" class="module-card">
                <i class="fas fa-clipboard-check"></i>
                <h3>Exámenes Diagnósticos</h3>
                <p>Alimenta el historial académico con los resultados de ubicación inicial de los alumnos.</p>
            </a>

            <a href="carga_masiva/vista_csv_certificaciones" class="module-card">
                <i class="fas fa-certificate"></i>
                <h3>Certificaciones</h3>
                <p>Carga los niveles oficiales (TOEFL, Cambridge, etc.) obtenidos por los alumnos en cada idioma.</p>
            </a>

            <a href="carga_masiva/vista_csv_grupos" class="module-card">
                <i class="fas fa-chalkboard"></i>
                <h3>Grupos y Horarios</h3>
                <p>Registra la oferta académica, asignando el NRC, materia, profesor y horarios correspondientes.</p>
            </a>

            <!-- NUEVO MÓDULO DE DICTÁMENES -->
            <a href="carga_masiva/vista_csv_dictamenes" class="module-card">
                <i class="fas fa-file-signature"></i>
                <h3>Dictámenes (PDF)</h3>
                <p>Registra acreditaciones por competencias y vincula los archivos PDF oficiales para los alumnos.</p>
            </a>
        </div>

    </main>

    <?php include '../main_footer.php'; ?>

    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }
    </script>
</body>

</html>