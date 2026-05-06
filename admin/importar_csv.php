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

if ($mensaje === 'ok_alumnos') {
    $tipo_mensaje = 'success';
    $texto_mensaje = "¡Excelente! Se importaron correctamente <strong>$total alumnos</strong>. Contraseñas auto-generadas y cuentas activadas.";
} elseif ($mensaje === 'ok_profesores') {
    $tipo_mensaje = 'success';
    $texto_mensaje = "¡Excelente! Se importaron correctamente <strong>$total profesores</strong>. Contraseñas auto-generadas y cuentas activadas.";
} elseif ($mensaje === 'dup') {
    $tipo_mensaje = 'error';
    $texto_mensaje = "<strong>Error de Duplicado:</strong> El código o correo del usuario <strong>$codigo_error</strong> (Fila $fila_error del Excel) ya existe en el sistema. <br><small>Corrige o elimina esa fila de tu Excel y vuelve a intentarlo. No se guardó ningún registro para evitar errores.</small>";
} elseif ($mensaje === 'error_file') {
    $tipo_mensaje = 'error';
    $texto_mensaje = "Error: No se seleccionó ningún archivo o el formato es incorrecto.";
} elseif ($mensaje === 'error_db') {
    $tipo_mensaje = 'error';
    $texto_mensaje = "Ocurrió un error inesperado al guardar en la base de datos.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carga Masiva | Admin E-PALE</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .import-section { max-width: 900px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .file-input-wrapper { margin: 20px 0; position: relative; }
        .file-input-label { display: block; padding: 40px 20px; background-color: #f8f9fa; border: 2px dashed #ccc; border-radius: 8px; text-align: center; cursor: pointer; font-weight: 500; color: #555; transition: all 0.3s ease; }
        .file-input-label:hover { background-color: #e7f3ff; border-color: var(--udg-blue); color: var(--udg-blue); }
        .file-input-label i { font-size: 3rem; display: block; margin-bottom: 15px; color: var(--udg-light); }
        input[type="file"] { position: absolute; left: -9999px; }
        .file-name { display: block; text-align: center; margin-top: 10px; color: #666; font-size: 0.95rem; font-weight: bold; }
        
        /* Pestañas */
        .tabs { display: flex; border-bottom: 2px solid #eee; margin-bottom: 25px; }
        .tab { padding: 15px 30px; cursor: pointer; font-weight: bold; color: #888; border-bottom: 3px solid transparent; transition: 0.3s; }
        .tab:hover { color: var(--udg-blue); }
        .tab.active { color: var(--udg-blue); border-bottom-color: var(--udg-blue); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Tabla Excel */
        .excel-table-wrapper { display: none; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; }
        .excel-table-container { overflow-x: auto; }
        .excel-table { width: 100%; border-collapse: collapse; font-family: 'Calibri', sans-serif; font-size: 0.85rem; white-space: nowrap; }
        .excel-table th { background-color: #f3f3f3; border: 1px solid #ccc; padding: 6px 12px; text-align: left; font-weight: normal; color: #333; }
        .excel-table td { border: 1px solid #e1e1e1; padding: 6px 12px; color: #000; }
        .excel-col-header { background-color: #e6e6e6 !important; text-align: center !important; font-weight: bold !important; border-bottom: 2px solid #ccc !important; }
        .excel-info-box { padding: 12px 15px; background: #f8f9fa; border-top: 1px solid #ccc; font-size: 0.9rem; color: #555; line-height: 1.5; }
    </style>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <a href="usuarios.php" style="display: inline-block; margin-bottom: 20px; color: var(--udg-blue); text-decoration: none; font-weight: bold;">
            <i class="fas fa-arrow-left"></i> Volver a Usuarios
        </a>

        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-file-csv"></i> Carga Masiva de Usuarios</h1>
            <p>Sube un archivo CSV para registrar cuentas de forma automática.</p>
        </div>

        <?php if (!empty($texto_mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="max-width: 900px; margin: 0 auto 20px auto; padding: 15px; border-radius: 8px; text-align: center; font-size: 1.05rem;">
                <?php echo $texto_mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="import-section">
            
            <div class="tabs">
                <div class="tab active" onclick="switchTab('alumnos')"><i class="fas fa-user-graduate"></i> Importar Alumnos</div>
                <div class="tab" onclick="switchTab('profesores')"><i class="fas fa-chalkboard-teacher"></i> Importar Profesores</div>
            </div>

            <div id="tab-alumnos" class="tab-content active">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: var(--udg-blue);">Formato de Alumnos</h3>
                    <button type="button" class="btn-cancel" onclick="toggleExcel('excelAlumnos')" style="background: #107c41; color: white; border: none; padding: 6px 12px; font-size:0.85rem;">
                        <i class="fas fa-file-excel"></i> Ver ejemplo
                    </button>
                </div>

                <div class="excel-table-wrapper" id="excelAlumnos">
                    <div class="excel-table-container">
                        <table class="excel-table">
                            <thead>
                                <tr>
                                    <th class="excel-col-header" style="width: 30px;"></th> <th class="excel-col-header">A</th> <th class="excel-col-header">B</th> <th class="excel-col-header">C</th> <th class="excel-col-header">D</th> <th class="excel-col-header">E</th> <th class="excel-col-header">F</th> <th class="excel-col-header">G</th> <th class="excel-col-header">H</th>
                                </tr>
                                <tr>
                                    <th style="background:#e6e6e6; text-align:center; font-weight:bold;">1</th> <th>CODIGO</th> <th>APELLIDO_PATERNO</th> <th>APELLIDO_MATERNO</th> <th>NOMBRE</th> <th>CARRERA</th> <th>GENERO</th> <th>CORREO</th> <th>PERIODO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="background:#e6e6e6; text-align:center; font-weight:bold;">2</td> <td>228922367</td> <td>PEREZ</td> <td>DIAZ</td> <td>JAIME ALFREDO</td> <td>LTIN</td> <td>MASCULINO</td> <td style="color:#0563c1; text-decoration:underline;">jaime@ejemplo.com</td> <td>2022B</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="excel-info-box">
                        <i class="fas fa-info-circle" style="color:var(--udg-blue);"></i> <strong>Nota del Sistema:</strong> 
                        Todos los alumnos se crearán con estatus <strong>ACTIVO</strong> por defecto. El sistema asignará automáticamente su contraseña uniendo la sigla de su <strong>CARRERA</strong> y los últimos <strong>6 dígitos de su CÓDIGO</strong>. <br>
                        <em>Ejemplo: Si la carrera es LTIN y el código 228922367, la contraseña será: <strong>LTIN922367</strong>.</em>
                    </div>
                </div>

                <form method="POST" action="importar_alumnos.php" enctype="multipart/form-data">
                    <div class="file-input-wrapper">
                        <label for="csv_alumnos" class="file-input-label" id="label_alumnos">
                            <i class="fas fa-cloud-upload-alt"></i> Haz clic para seleccionar el CSV de Alumnos
                        </label>
                        <input type="file" name="archivo_csv" id="csv_alumnos" accept=".csv" required onchange="handleFileSelect(this, 'fileNameAlumnos', 'btnAlumnos')">
                        <span id="fileNameAlumnos" class="file-name">Ningún archivo seleccionado</span>
                    </div>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 500; color: #444; margin-bottom:15px;">
                        <input type="checkbox" name="ignorar_cabecera" value="1" checked style="width: 18px; height: 18px;">
                        Ignorar la fila 1 (Encabezados)
                    </label>
                    <button type="submit" class="btn-save" id="btnAlumnos" disabled style="width: 100%; justify-content: center; background-color: #ccc; cursor: not-allowed;">
                        <i class="fas fa-upload"></i> Subir Alumnos
                    </button>
                </form>
            </div>

            <div id="tab-profesores" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: var(--udg-blue);">Formato de Profesores</h3>
                    <button type="button" class="btn-cancel" onclick="toggleExcel('excelProfesores')" style="background: #107c41; color: white; border: none; padding: 6px 12px; font-size:0.85rem;">
                        <i class="fas fa-file-excel"></i> Ver ejemplo
                    </button>
                </div>

                <div class="excel-table-wrapper" id="excelProfesores">
                    <div class="excel-table-container">
                        <table class="excel-table">
                            <thead>
                                <tr>
                                    <th class="excel-col-header" style="width: 30px;"></th> <th class="excel-col-header">A</th> <th class="excel-col-header">B</th> <th class="excel-col-header">C</th> <th class="excel-col-header">D</th> <th class="excel-col-header">E</th> <th class="excel-col-header">F</th> <th class="excel-col-header">G</th> <th class="excel-col-header">H</th> <th class="excel-col-header">I</th>
                                </tr>
                                <tr>
                                    <th style="background:#e6e6e6; text-align:center; font-weight:bold;">1</th> <th>CODIGO</th> <th>APELLIDO_PATERNO</th> <th>APELLIDO_MATERNO</th> <th>NOMBRE</th> <th>NACIONALIDAD</th> <th>EXPERIENCIA</th> <th>GENERO</th> <th>CORREO</th> <th>PERIODO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="background:#e6e6e6; text-align:center; font-weight:bold;">2</td> <td>28475638</td> <td>HERNANDEZ</td> <td>LOPEZ</td> <td>MARIA JOSE</td> <td>MEXICANA</td> <td>C1</td> <td>FEMENINO</td> <td style="color:#0563c1; text-decoration:underline;">maria@ejemplo.com</td> <td>2020A</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="excel-info-box">
                        <i class="fas fa-info-circle" style="color:var(--udg-blue);"></i> <strong>Nota del Sistema:</strong> 
                        Todos los profesores se crearán con estatus <strong>ACTIVO</strong> por defecto. El sistema asignará automáticamente su contraseña usando la palabra <strong>PROFE</strong> seguida de los últimos <strong>6 dígitos de su CÓDIGO</strong>. <br>
                        <em>Ejemplo: Si el código es 28475638, la contraseña será: <strong>PROFE475638</strong>.</em>
                    </div>
                </div>

                <form method="POST" action="importar_profesores.php" enctype="multipart/form-data">
                    <div class="file-input-wrapper">
                        <label for="csv_profesores" class="file-input-label" id="label_profesores">
                            <i class="fas fa-cloud-upload-alt"></i> Haz clic para seleccionar el CSV de Profesores
                        </label>
                        <input type="file" name="archivo_csv" id="csv_profesores" accept=".csv" required onchange="handleFileSelect(this, 'fileNameProfesores', 'btnProfesores')">
                        <span id="fileNameProfesores" class="file-name">Ningún archivo seleccionado</span>
                    </div>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 500; color: #444; margin-bottom:15px;">
                        <input type="checkbox" name="ignorar_cabecera" value="1" checked style="width: 18px; height: 18px;">
                        Ignorar la fila 1 (Encabezados)
                    </label>
                    <button type="submit" class="btn-save" id="btnProfesores" disabled style="width: 100%; justify-content: center; background-color: #ccc; cursor: not-allowed;">
                        <i class="fas fa-upload"></i> Subir Profesores
                    </button>
                </form>
            </div>

        </div>
    </main>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if(tab === 'alumnos') {
                document.querySelectorAll('.tab')[0].classList.add('active');
                document.getElementById('tab-alumnos').classList.add('active');
            } else {
                document.querySelectorAll('.tab')[1].classList.add('active');
                document.getElementById('tab-profesores').classList.add('active');
            }
        }

        function toggleExcel(id) {
            const el = document.getElementById(id);
            el.style.display = (el.style.display === 'block') ? 'none' : 'block';
        }

        function handleFileSelect(input, fileNameId, btnId) {
            const fileNameEl = document.getElementById(fileNameId);
            const btnEl = document.getElementById(btnId);
            
            if (input.files && input.files.length > 0) {
                fileNameEl.innerHTML = '<i class="fas fa-file-csv" style="color:#28a745;"></i> ' + input.files[0].name;
                fileNameEl.style.color = '#28a745';
                btnEl.disabled = false;
                btnEl.style.backgroundColor = 'var(--udg-blue)';
                btnEl.style.cursor = 'pointer';
            } else {
                fileNameEl.textContent = 'Ningún archivo seleccionado';
                fileNameEl.style.color = '#666';
                btnEl.disabled = true;
                btnEl.style.backgroundColor = '#ccc';
                btnEl.style.cursor = 'not-allowed';
            }
        }
    </script>
</body>
</html>
