<?php
session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

// Validar seguridad: Solo Alumnos
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}

$usuario_id = $_SESSION['user_id'];

// 1. Obtener el código del alumno desde la tabla usuarios
$stmt_codigo = $pdo->prepare("SELECT codigo FROM usuarios WHERE usuario_id = ?");
$stmt_codigo->execute([$usuario_id]);
$codigo_alumno = $stmt_codigo->fetchColumn();

// 2. Buscar sus acreditaciones en la base de datos
$stmt_dictamenes = $pdo->prepare("
    SELECT * FROM dictamenes_estudiantes 
    WHERE codigo_alumno = ? 
    ORDER BY ciclo_acreditacion DESC, nombre_materia ASC
");
$stmt_dictamenes->execute([$codigo_alumno]);
$dictamenes = $stmt_dictamenes->fetchAll(PDO::FETCH_ASSOC);

// 3. Leer los archivos físicos de la carpeta local
$upload_dir = '../uploads/dictamenes/';
$archivos_fisicos = [];
if (is_dir($upload_dir)) {
    $archivos_fisicos = array_diff(scandir($upload_dir), array('.', '..'));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Dictámenes | Portal Estudiantil</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 40px;">
            <h1><i class="fas fa-file-signature"></i> Mis Dictámenes Oficiales</h1>
            <p>Acreditaciones por competencia y documentos oficiales vinculados a tu código (<strong><?php echo htmlspecialchars($codigo_alumno); ?></strong>).</p>
        </div>

        <?php if (count($dictamenes) === 0): ?>
            <div class="empty-dictamenes">
                <i class="fas fa-folder-open"></i>
                <h2>No tienes dictámenes registrados</h2>
                <p>Si aprobaste un nivel mediante examen de ubicación o competencias, aparecerá aquí cuando Control Escolar suba el documento oficial.</p>
            </div>
        <?php else: ?>
            <div class="dictamen-grid">
                <?php foreach ($dictamenes as $d): 
                    $pdf_url = null;
                    $llave_busqueda = str_replace('/', '.', $d['num_dictamen']);

                    foreach ($archivos_fisicos as $archivo) {
                        if (stripos($archivo, $llave_busqueda) !== false) {
                            $pdf_url = '../uploads/dictamenes/' . rawurlencode($archivo);
                            break;
                        }
                    }
                ?>
                    <div class="dictamen-card">
                        <div class="dictamen-header">
                            <h3><i class="fas fa-award"></i> <?php echo htmlspecialchars($d['nombre_materia']); ?></h3>
                            <div style="color: #a0d8ff; font-size: 0.85rem; margin-top: 5px;">Clave: <?php echo htmlspecialchars($d['clave_materia']); ?></div>
                        </div>
                        <div class="dictamen-body">
                            <div class="dictamen-info-row">
                                <i class="fas fa-hashtag"></i>
                                <div>
                                    <strong>No. de Dictamen:</strong><br>
                                    <span class="dictamen-badge"><?php echo htmlspecialchars($d['num_dictamen']); ?></span>
                                </div>
                            </div>
                            <div class="dictamen-info-row" style="margin-top: 5px;">
                                <i class="far fa-calendar-check"></i>
                                <div>
                                    <strong>Ciclo de Acreditación:</strong><br>
                                    <?php echo htmlspecialchars($d['ciclo_acreditacion']); ?>
                                </div>
                            </div>
                            
                            <hr style="border: 0; border-top: 1px solid #eee; margin: 15px 0; width: 100%;">
                            
                            <?php if ($pdf_url): ?>
                                <a href="<?php echo $pdf_url; ?>" target="_blank" class="btn-download" title="Abre el PDF en una nueva pestaña">
                                    <i class="fas fa-file-pdf"></i> Ver / Descargar Documento
                                </a>
                            <?php else: ?>
                                <button class="btn-download btn-disabled" disabled title="Control Escolar aún no sube el archivo">
                                    <i class="fas fa-hourglass-half"></i> En proceso de firma
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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