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

// Diccionario de mensajes
if ($mensaje === 'ok_alumnos') {
    $tipo_mensaje = 'success'; $texto_mensaje = "¡Excelente! Se importaron correctamente <strong>$total alumnos</strong>. Contraseñas auto-generadas.";
} elseif ($mensaje === 'ok_profesores') {
    $tipo_mensaje = 'success'; $texto_mensaje = "¡Excelente! Se importaron correctamente <strong>$total profesores</strong>. Contraseñas auto-generadas.";
} elseif ($mensaje === 'ok_diagnosticos') {
    $tipo_mensaje = 'success'; $texto_mensaje = "¡Excelente! Se importaron correctamente <strong>$total exámenes diagnósticos</strong> al historial.";
} elseif ($mensaje === 'dup') {
    $tipo_mensaje = 'error'; $texto_mensaje = "<strong>Error de Duplicado:</strong> El código o correo <strong>$codigo_error</strong> (Fila $fila_error) ya existe en el sistema. <br><small>No se guardó ningún registro para evitar errores.</small>";
} elseif ($mensaje === 'error_codigo') {
    $tipo_mensaje = 'error'; $texto_mensaje = "<strong>Alumno no encontrado:</strong> En la fila $fila_error, el código <strong>$codigo_error</strong> no existe. Regístralo primero.";
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
    <title>Carga Masiva CSV | Admin E-PALE</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <a href="usuarios.php" style="display: inline-block; margin-bottom: 20px; color: var(--udg-blue); text-decoration: none; font-weight: bold;">
            <i class="fas fa-arrow-left"></i> Volver a Usuarios
        </a>

        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-file-csv"></i> Importación Masiva de Datos</h1>
            <p>Sube archivos CSV estandarizados para registrar usuarios o historial académico al instante.</p>
        </div>

        <?php if (!empty($texto_mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="max-width: 1000px; margin: 0 auto 20px auto; padding: 15px; border-radius: 8px; text-align: center; font-size: 1.05rem;">
                <?php echo $texto_mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="import-section">
            
            <div class="csv-tabs">
                <div class="csv-tab active" onclick="switchTab('alumnos')"><i class="fas fa-user-graduate"></i> Alumnos</div>
                <div class="csv-tab" onclick="switchTab('profesores')"><i class="fas fa-chalkboard-teacher"></i> Profesores</div>
                <div class="csv-tab" onclick="switchTab('diagnosticos')"><i class="fas fa-clipboard-check"></i> Exámenes Diagnósticos</div>
            </div>

            <div id="tab-alumnos" class="csv-tab-content active">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: var(--udg-blue);">Estructura de Alumnos</h3>
                    <button type="button" class="btn-cancel" onclick="toggleExcel('excelAlumnos')" style="background: #107c41; color: white; border: none; padding: 6px 12px; font-size:0.85rem;"><i class="fas fa-file-excel"></i> Ver ejemplo</button>
                </div>

                <div class="excel-table-wrapper" id="excelAlumnos">
                    <div class="excel-table-container">
                        <table class="excel-table">
                            <thead>
                                <tr> <th class="excel-col-header" style="width: 30px;"></th> <th class="excel-col-header">A</th> <th class="excel-col-header">B</th> <th class="excel-col-header">C</th> <th class="excel-col-header">D</th> <th class="excel-col-header">E</th> <th class="excel-col-header">F</th> <th class="excel-col-header">G</th> <th class="excel-col-header">H</th> </tr>
                                <tr> <th style="background:#e6e6e6; text-align:center; font-weight:bold;">1</th> <th>CODIGO</th> <th>APELLIDO_PATERNO</th> <th>APELLIDO_MATERNO</th> <th>NOMBRE</th> <th>CARRERA</th> <th>GENERO</th> <th>CORREO</th> <th>PERIODO</th> </tr>
                            </thead>
                            <tbody>
                                <tr> <td style="background:#e6e6e6; text-align:center; font-weight:bold;">2</td> <td>228922367</td> <td>PEREZ</td> <td>DIAZ</td> <td>JAIME ALFREDO</td> <td>LTIN</td> <td>MASCULINO</td> <td style="color:#0563c1; text-decoration:underline;">jaime@ejemplo.com</td> <td>2022B</td> </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="excel-info-box"><i class="fas fa-info-circle" style="color:var(--udg-blue);"></i> <strong>Nota del Sistema:</strong> Cuentas se activan automáticamente. Contraseña generada uniendo la <strong>CARRERA</strong> y los últimos <strong>6 dígitos del CÓDIGO</strong> (Ej. <strong>LTIN922367</strong>).</div>
                </div>

                <form method="POST" action="importar_alumnos.php" enctype="multipart/form-data">
                    <div class="file-input-wrapper">
                        <label for="csv_alumnos" class="file-input-label" id="label_alumnos">
                            <i class="fas fa-cloud-upload-alt"></i> Haz clic para seleccionar el CSV de Alumnos
                        </label>
                        <input type="file" name="archivo_csv" id="csv_alumnos" class="file-input-hidden" accept=".csv" required onchange="handleFileSelect(this, 'fileNameAlumnos', 'btnAlumnos')">
                        <span id="fileNameAlumnos" class="file-name">Ningún archivo seleccionado</span>
                    </div>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 500; color: #444; margin-bottom:15px;">
                        <input type="checkbox" name="ignorar_cabecera" value="1" checked style="width: 18px; height: 18px;"> Ignorar la fila 1 (Encabezados)
                    </label>
                    <button type="submit" class="btn-save" id="btnAlumnos" disabled style="width: 100%; justify-content: center; background-color: #ccc; cursor: not-allowed;"><i class="fas fa-upload"></i> Procesar Alumnos</button>
                </form>
            </div>

            <div id="tab-profesores" class="csv-tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: var(--udg-blue);">Estructura de Profesores</h3>
                    <button type="button" class="btn-cancel" onclick="toggleExcel('excelProfesores')" style="background: #107c41; color: white; border: none; padding: 6px 12px; font-size:0.85rem;"><i class="fas fa-file-excel"></i> Ver ejemplo</button>
                </div>

                <div class="excel-table-wrapper" id="excelProfesores">
                    <div class="excel-table-container">
                        <table class="excel-table">
                            <thead>
                                <tr> <th class="excel-col-header" style="width: 30px;"></th> <th class="excel-col-header">A</th> <th class="excel-col-header">B</th> <th class="excel-col-header">C</th> <th class="excel-col-header">D</th> <th class="excel-col-header">E</th> <th class="excel-col-header">F</th> <th class="excel-col-header">G</th> <th class="excel-col-header">H</th> <th class="excel-col-header">I</th> </tr>
                                <tr> <th style="background:#e6e6e6; text-align:center; font-weight:bold;">1</th> <th>CODIGO</th> <th>APELLIDO_PATERNO</th> <th>APELLIDO_MATERNO</th> <th>NOMBRE</th> <th>NACIONALIDAD</th> <th>EXPERIENCIA</th> <th>GENERO</th> <th>CORREO</th> <th>PERIODO</th> </tr>
                            </thead>
                            <tbody>
                                <tr> <td style="background:#e6e6e6; text-align:center; font-weight:bold;">2</td> <td>28475638</td> <td>HERNANDEZ</td> <td>LOPEZ</td> <td>MARIA JOSE</td> <td>MEXICANA</td> <td>5 AÑOS</td> <td>FEMENINO</td> <td style="color:#0563c1; text-decoration:underline;">maria@ejemplo.com</td> <td>2020A</td> </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="excel-info-box"><i class="fas fa-info-circle" style="color:var(--udg-blue);"></i> <strong>Nota del Sistema:</strong> Cuentas se activan automáticamente. Contraseña generada usando <strong>PROFE</strong> seguido de los últimos <strong>6 dígitos del CÓDIGO</strong> (Ej. <strong>PROFE475638</strong>).</div>
                </div>

                <form method="POST" action="importar_profesores.php" enctype="multipart/form-data">
                    <div class="file-input-wrapper">
                        <label for="csv_profesores" class="file-input-label" id="label_profesores">
                            <i class="fas fa-cloud-upload-alt"></i> Haz clic para seleccionar el CSV de Profesores
                        </label>
                        <input type="file" name="archivo_csv" id="csv_profesores" class="file-input-hidden" accept=".csv" required onchange="handleFileSelect(this, 'fileNameProfesores', 'btnProfesores')">
                        <span id="fileNameProfesores" class="file-name">Ningún archivo seleccionado</span>
                    </div>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 500; color: #444; margin-bottom:15px;">
                        <input type="checkbox" name="ignorar_cabecera" value="1" checked style="width: 18px; height: 18px;"> Ignorar la fila 1 (Encabezados)
                    </label>
                    <button type="submit" class="btn-save" id="btnProfesores" disabled style="width: 100%; justify-content: center; background-color: #ccc; cursor: not-allowed;"><i class="fas fa-upload"></i> Procesar Profesores</button>
                </form>
            </div>

            <div id="tab-diagnosticos" class="csv-tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0; color: var(--udg-blue);">Estructura de Diagnósticos</h3>
                    <button type="button" class="btn-cancel" onclick="toggleExcel('excelDiagnosticos')" style="background: #107c41; color: white; border: none; padding: 6px 12px; font-size:0.85rem;"><i class="fas fa-file-excel"></i> Ver ejemplo</button>
                </div>

                <div class="excel-table-wrapper" id="excelDiagnosticos">
                    <div class="excel-table-container">
                        <table class="excel-table">
                            <thead>
                                <tr> <th class="excel-col-header" style="width: 30px;"></th> <th class="excel-col-header">A</th> <th class="excel-col-header">B</th> <th class="excel-col-header">C</th> <th class="excel-col-header">D</th> <th class="excel-col-header">E</th> <th class="excel-col-header">F</th> <th class="excel-col-header">G</th> <th class="excel-col-header">H</th> <th class="excel-col-header">I</th> </tr>
                                <tr> <th style="background:#e6e6e6; text-align:center; font-weight:bold;">1</th> <th>Código</th> <th>Candidato</th> <th>Ciclo</th> <th>Examen</th> <th>Idioma</th> <th>Puntaje</th> <th>Puntos_Maximos</th> <th>Nivel_Asignado</th> <th>Fecha</th> </tr>
                            </thead>
                            <tbody>
                                <tr> <td style="background:#e6e6e6; text-align:center; font-weight:bold;">2</td> <td>215286161</td> <td style="color:#888;">Abarca García, Francisco...</td> <td>2016B</td> <td>Examen Diagnóstico V1.0</td> <td>Inglés</td> <td>48</td> <td>100</td> <td>B2</td> <td>17/08/2016</td> </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="excel-info-box"><i class="fas fa-info-circle" style="color:var(--udg-blue);"></i> <strong>Puntos Clave:</strong> 1. El alumno ya debe existir en el sistema. 2. El formato de <strong>Fecha (I)</strong> debe ser <strong>DD/MM/AAAA</strong>.</div>
                </div>

                <form method="POST" action="importar_diagnosticos.php" enctype="multipart/form-data">
                    <div class="file-input-wrapper">
                        <label for="csv_diagnosticos" class="file-input-label" id="label_diagnosticos">
                            <i class="fas fa-cloud-upload-alt"></i> Haz clic para seleccionar el CSV de Diagnósticos
                        </label>
                        <input type="file" name="archivo_csv" id="csv_diagnosticos" class="file-input-hidden" accept=".csv" required onchange="handleFileSelect(this, 'fileNameDiagnosticos', 'btnDiagnosticos')">
                        <span id="fileNameDiagnosticos" class="file-name">Ningún archivo seleccionado</span>
                    </div>
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 500; color: #444; margin-bottom:15px;">
                        <input type="checkbox" name="ignorar_cabecera" value="1" checked style="width: 18px; height: 18px;"> Ignorar la fila 1 (Encabezados)
                    </label>
                    <button type="submit" class="btn-save" id="btnDiagnosticos" disabled style="width: 100%; justify-content: center; background-color: #ccc; cursor: not-allowed;"><i class="fas fa-upload"></i> Procesar Diagnósticos</button>
                </form>
            </div>

        </div>
    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        function switchTab(tab) {
            document.querySelectorAll('.csv-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.csv-tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            document.querySelector(`.csv-tab[onclick="switchTab('${tab}')"]`).classList.add('active');
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