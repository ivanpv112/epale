<?php
session_start();
require '../db.php';

// Validar seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php"); exit;
}

$mensaje = isset($_GET['msg']) ? $_GET['msg'] : '';
$total = isset($_GET['total']) ? (int)$_GET['total'] : 0;
$detalle_error = isset($_GET['detalle']) ? htmlspecialchars($_GET['detalle']) : '';

$tipo_mensaje = '';
$texto_mensaje = '';

if ($mensaje === 'ok_grupos') {
    $tipo_mensaje = 'success'; $texto_mensaje = "¡Excelente! Se importaron correctamente <strong>$total grupos</strong> al sistema con sus respectivos horarios y materias.";
} elseif ($mensaje === 'error_datos') {
    $tipo_mensaje = 'error'; $texto_mensaje = "<strong>Error detectado en el archivo:</strong><br>" . $detalle_error . "<br><small>Por favor, corrige este dato en tu archivo Excel y vuelve a subirlo.</small>";
} elseif ($mensaje === 'error_file') {
    $tipo_mensaje = 'error'; $texto_mensaje = "Error: No se seleccionó ningún archivo o el formato es incorrecto.";
} elseif ($mensaje === 'error_db') {
    $tipo_mensaje = 'error'; $texto_mensaje = "Ocurrió un error grave de base de datos. Verifica duplicados o datos inválidos.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Grupos | Admin E-PALE</title>
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
            <h1><i class="fas fa-chalkboard"></i> Carga Masiva de Grupos y Horarios</h1>
            <p>Sube el archivo CSV con la oferta académica. El sistema creará automáticamente las materias faltantes.</p>
        </div>

        <?php if (!empty($texto_mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="max-width: 1000px; margin: 0 auto 20px auto; padding: 15px; border-radius: 8px; text-align: center; font-size: 1.05rem;">
                <?php echo $texto_mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="import-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; color: var(--udg-blue);">Estructura del Archivo (12 Columnas)</h3>
                <button type="button" class="btn-cancel-sm" onclick="toggleExcel('excelGrupos')"><i class="fas fa-file-excel"></i> Ver ejemplo visual</button>
            </div>

            <div class="excel-table-wrapper" id="excelGrupos" style="display: block;">
                <div class="excel-table-container">
                    <table class="excel-table">
                        <thead>
                            <tr> 
                                <th class="excel-col-header" style="width: 30px;"></th> 
                                <th class="excel-col-header">A</th> <th class="excel-col-header">B</th> 
                                <th class="excel-col-header">C</th> <th class="excel-col-header">D</th> 
                                <th class="excel-col-header">E</th> <th class="excel-col-header">F</th> 
                                <th class="excel-col-header">G</th> <th class="excel-col-header">H</th> 
                                <th class="excel-col-header">I</th> <th class="excel-col-header">J</th> 
                                <th class="excel-col-header">K</th> <th class="excel-col-header">L</th>
                            </tr>
                            <tr> 
                                <th style="background:#e6e6e6; text-align:center; font-weight:bold;">1</th> 
                                <th>NRC</th> <th>CLAVE_SIIAU</th> <th>IDIOMA</th> <th>NIVEL</th> 
                                <th>NOM_GRUPO</th> <th>COD_PROFE</th> <th>PERIODO</th> <th>AULA</th> 
                                <th>HR_INICIO</th> <th>HR_FINAL</th> <th>PRESENCIAL</th> <th>VIRTUAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr> 
                                <td style="background:#e6e6e6; text-align:center; font-weight:bold;">2</td> 
                                <td>199516</td> <td>CU182</td> <td>CROATA NEGOCIOS</td> <td style="text-align:center;">4</td> 
                                <td>E.13.1.B</td> <td>29298377</td> <td>2026A</td> 
                                <td>N301</td> <td>900</td> <td>1255</td> <td style="color:#0056b3; font-weight:bold;">L-J</td> <td style="color:#17a2b8; font-weight:bold;">V</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="excel-info-box">
                    <i class="fas fa-info-circle" style="color:var(--udg-blue);"></i> <strong>Lógica Inteligente:</strong><br>
                    1. <strong>IDIOMA y NIVEL:</strong> Si la materia no existe en el sistema, E-PALE la creará automáticamente generando una clave interna (Ej. Croata Negocios 4 = CN4).<br>
                    2. <strong>CLAVE_SIIAU:</strong> Se guardará internamente para identificar el bloque de la materia en SIIAU.<br>
                    3. Si no hay clases de una modalidad, escribe <strong>NA</strong> en las columnas PRESENCIAL o VIRTUAL.
                </div>
            </div>

            <form method="POST" action="importar_grupos.php" enctype="multipart/form-data">
                 <!-- ESCUDO CSRF INYECTADO -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="file-input-wrapper">
                    <label for="csv_grupos" class="file-input-label" id="label_grupos">
                        <i class="fas fa-cloud-upload-alt"></i> Haz clic para seleccionar el CSV de Grupos
                    </label>
                    <input type="file" name="archivo_csv" id="csv_grupos" class="file-input-hidden" accept=".csv" required onchange="handleFileSelect(this, 'fileNameGrupos', 'btnGrupos')">
                    <span id="fileNameGrupos" class="file-name">Ningún archivo seleccionado</span>
                </div>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 500; color: #444; margin-bottom:15px;">
                    <input type="checkbox" name="ignorar_cabecera" value="1" checked style="width: 18px; height: 18px;"> Ignorar la fila 1 (Encabezados)
                </label>
                <button type="submit" class="btn-save" id="btnGrupos" disabled style="width: 100%; justify-content: center; background-color: #ccc; cursor: not-allowed;"><i class="fas fa-upload"></i> Procesar Archivo de Grupos</button>
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
