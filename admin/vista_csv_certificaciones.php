<?php
session_start();
require '../db.php';

// Validar seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php"); exit;
}

$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';
$total = isset($_GET['total']) ? (int)$_GET['total'] : 0;
$codigo_error = isset($_GET['codigo']) ? htmlspecialchars($_GET['codigo']) : '';
$fila_error = isset($_GET['fila']) ? (int)$_GET['fila'] : 0;

$tipo_mensaje = '';
$texto_mensaje = '';

if ($mensaje === 'ok_certificaciones') {
    $tipo_mensaje = 'success'; $texto_mensaje = "¡Excelente! Se importaron correctamente <strong>$total certificaciones</strong> al historial.";
} elseif ($mensaje === 'error_codigo') {
    $tipo_mensaje = 'error'; $texto_mensaje = "<strong>Alumno no encontrado:</strong> En la fila $fila_error, el código <strong>$codigo_error</strong> no está registrado como alumno. Regístralo primero.";
} elseif ($mensaje === 'error_file') {
    $tipo_mensaje = 'error'; $texto_mensaje = "Error: No se seleccionó ningún archivo o el formato es incorrecto.";
} elseif ($mensaje === 'error_db') {
    $tipo_mensaje = 'error'; $texto_mensaje = "Ocurrió un error en la base de datos en la fila $fila_error. Verifica el formato de tus datos.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Certificaciones | Admin E-PALE</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <a href="interfaz_csv.php" style="display: inline-block; margin-bottom: 20px; color: var(--udg-blue); text-decoration: none; font-weight: bold;">
            <i class="fas fa-arrow-left"></i> Volver a Módulos de Importación
        </a>

        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-certificate"></i> Carga Masiva de Certificaciones</h1>
            <p>Sube el archivo CSV para registrar los niveles oficiales de idiomas obtenidos por los alumnos.</p>
        </div>

        <?php if (!empty($texto_mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="max-width: 1000px; margin: 0 auto 20px auto; padding: 15px; border-radius: 8px; text-align: center; font-size: 1.05rem;">
                <?php echo $texto_mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="import-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; color: var(--udg-blue);">Estructura de Certificaciones</h3>
                <button type="button" class="btn-cancel" onclick="toggleExcel('excelCertificaciones')" style="background: #107c41; color: white; border: none; padding: 6px 12px; font-size:0.85rem;"><i class="fas fa-file-excel"></i> Ver ejemplo visual</button>
            </div>

            <div class="excel-table-wrapper" id="excelCertificaciones" style="display: block;">
                <div class="excel-table-container">
                    <table class="excel-table">
                        <thead>
                            <tr> 
                                <th class="excel-col-header" style="width: 30px;"></th> 
                                <th class="excel-col-header">A</th> 
                                <th class="excel-col-header">B</th> 
                                <th class="excel-col-header">C</th> 
                                <th class="excel-col-header">D</th> 
                                <th class="excel-col-header">E</th> 
                                <th class="excel-col-header">F</th> 
                            </tr>
                            <tr> 
                                <th style="background:#e6e6e6; text-align:center; font-weight:bold;">1</th> 
                                <th>CODIGO</th> 
                                <th>PERIODO</th> 
                                <th>TIPO</th> 
                                <th>PUNTAJE</th> 
                                <th>EQUIVALENCIA</th> 
                                <th>FECHA_DE_APLICACION</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <tr> 
                                <td style="background:#e6e6e6; text-align:center; font-weight:bold;">2</td> 
                                <td>215286161</td> 
                                <td>2016B</td> 
                                <td>INGLÉS</td> 
                                <td>550</td> 
                                <td>B2</td> 
                                <td>15/09/2016</td> 
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="excel-info-box">
                    <i class="fas fa-info-circle" style="color:var(--udg-blue);"></i> <strong>Puntos Clave:</strong><br>
                    1. <strong>TIPO</strong> se refiere al Idioma evaluado.<br>
                    2. <strong>EQUIVALENCIA</strong> es el Nivel Obtenido final (Ej. B1, B2).<br>
                    3. La fecha de aplicación debe venir en formato <strong>DD/MM/AAAA</strong>.
                </div>
            </div>

            <form method="POST" action="importar_certificaciones.php" enctype="multipart/form-data">
                <div class="file-input-wrapper">
                    <label for="csv_certificaciones" class="file-input-label" id="label_certificaciones">
                        <i class="fas fa-cloud-upload-alt"></i> Haz clic para seleccionar el CSV de Certificaciones
                    </label>
                    <input type="file" name="archivo_csv" id="csv_certificaciones" class="file-input-hidden" accept=".csv" required onchange="handleFileSelect(this, 'fileNameCert', 'btnCert')">
                    <span id="fileNameCert" class="file-name">Ningún archivo seleccionado</span>
                </div>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 500; color: #444; margin-bottom:15px;">
                    <input type="checkbox" name="ignorar_cabecera" value="1" checked style="width: 18px; height: 18px;"> Ignorar la fila 1 (Encabezados)
                </label>
                <button type="submit" class="btn-save" id="btnCert" disabled style="width: 100%; justify-content: center; background-color: #ccc; cursor: not-allowed;"><i class="fas fa-upload"></i> Procesar Archivo de Certificaciones</button>
            </form>
        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        function toggleExcel(id) {
            const el = document.getElementById(id);
            el.style.display = (el.style.display === 'none') ? 'block' : 'none';
        }
        function handleFileSelect(input, fileNameId, btnId) {
            const fileNameEl = document.getElementById(fileNameId);
            const btnEl = document.getElementById(btnId);
            if (input.files && input.files.length > 0) {
                fileNameEl.innerHTML = '<i class="fas fa-file-csv" style="color:#28a745;"></i> ' + input.files[0].name;
                fileNameEl.style.color = '#28a745';
                btnEl.disabled = false; btnEl.style.backgroundColor = 'var(--udg-blue)'; btnEl.style.cursor = 'pointer';
            } else {
                fileNameEl.textContent = 'Ningún archivo seleccionado';
                fileNameEl.style.color = '#666';
                btnEl.disabled = true; btnEl.style.backgroundColor = '#ccc'; btnEl.style.cursor = 'not-allowed';
            }
        }
    </script>
</body>
</html>