<?php
session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

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
    <title>Importar Dictámenes | Admin E-PALE</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <a href="interfaz_csv.php" style="display: inline-block; margin-bottom: 20px; color: var(--udg-blue); text-decoration: none; font-weight: bold;">
            <i class="fas fa-arrow-left"></i> Volver a Módulos de Importación
        </a>

        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-file-signature"></i> Carga Masiva de Dictámenes</h1>
            <p>Sube el archivo CSV con los datos de los alumnos y vincula los archivos PDF oficiales de los dictámenes.</p>
        </div>

        <!-- SECCIÓN 1: ESTRUCTURA Y CSV -->
        <div class="import-section" style="margin-bottom: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; color: var(--udg-blue);">1. Subir Base de Datos (CSV)</h3>
                <button type="button" class="btn-cancel-sm" onclick="toggleExcel('excelDictamenes')" style="background: #107c41; color: white; border: none; font-weight: bold;"><i class="fas fa-file-excel"></i> Ver formato esperado</button>
            </div>

            <!-- Tabla de ejemplo oculta -->
            <div class="excel-table-wrapper" id="excelDictamenes" style="display: none;">
                <div class="excel-table-container">
                    <table class="excel-table">
                        <thead>
                            <tr> 
                                <th class="excel-col-header" style="width: 30px;"></th> 
                                <th class="excel-col-header">A</th> <th class="excel-col-header">B</th> <th class="excel-col-header">C</th> 
                                <th class="excel-col-header">D</th> <th class="excel-col-header">E</th> <th class="excel-col-header">F</th> 
                                <th class="excel-col-header">G</th> 
                            </tr>
                            <tr> 
                                <th style="background:#e6e6e6; text-align:center; font-weight:bold;">1</th> 
                                <th>CODIGO</th> <th>NOMBRE_ALUMNO</th> <th>CARRERA</th> 
                                <th>CICLO</th> <th>CLAVE_MATERIA</th> <th>NOMBRE_MATERIA</th> 
                                <th>NUM_DICTAMEN</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <tr> 
                                <td style="background:#e6e6e6; text-align:center; font-weight:bold;">2</td> 
                                <td>2367565</td> <td>Juan Perez Lopez</td> <td>LAFI</td> 
                                <td>2026A</td> <td>I5134</td> <td>Ingles I</td> 
                                <td>III/547/2026</td> 
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="excel-info-box">
                    <i class="fas fa-info-circle" style="color:var(--udg-blue);"></i> <strong>Nota Importante:</strong><br>
                    El <strong>NUM_DICTAMEN</strong> será utilizado por el sistema para buscar automáticamente el archivo PDF correspondiente en la segunda fase.
                </div>
            </div>

            <!-- Input CSV -->
            <form id="formCSV" onsubmit="procesarCSV(event)">
                <div class="file-input-wrapper">
                    <label for="csv_input" class="file-input-label" id="label_csv">
                        <i class="fas fa-cloud-upload-alt"></i> Haz clic para seleccionar el CSV de Alumnos
                    </label>
                    <input type="file" id="csv_input" accept=".csv" class="file-input-hidden" required onchange="handleFileSelect(this, 'fileNameCsv', 'btnCsv')">
                    <span id="fileNameCsv" class="file-name">Ningún archivo seleccionado</span>
                </div>
                
                <!-- CHECKBOX AGREGADO -->
                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 500; color: #444; margin-bottom:15px;">
                    <input type="checkbox" id="ignorar_cabecera" value="1" checked style="width: 18px; height: 18px;"> Ignorar la fila 1 (Encabezados)
                </label>

                <button type="submit" class="btn-save" id="btnCsv" disabled style="width: 100%; justify-content: center; background-color: #ccc; cursor: not-allowed;">
                    <i class="fas fa-database"></i> Procesar Archivo de Grupos
                </button>
            </form>
        </div>

        <!-- SECCIÓN 2: CARGA MÚLTIPLE DE PDFs -->
        <div class="import-section" style="margin-bottom: 30px;">
            <h3 style="margin: 0 0 15px 0; color: var(--udg-blue);">2. Subir Archivos Físicos (PDF)</h3>
            <p style="color: #666; font-size: 0.9rem; margin-bottom: 20px;">
                Selecciona todos los dictámenes en PDF que deseas subir. El sistema los vinculará con la base de datos automáticamente.
            </p>

            <form id="formPDF" onsubmit="procesarPDFs(event)">
                <div class="file-input-wrapper">
                    <label for="pdf_input" class="file-input-label" id="label_pdf" style="border-color: #f5c6cb; background: #fff8f8;">
                        <i class="fas fa-file-pdf" style="color: #dc3545;"></i> Haz clic para seleccionar Múltiples PDFs
                    </label>
                    <!-- Agregamos "multiple" para poder subir decenas de golpe -->
                    <input type="file" id="pdf_input" accept=".pdf" multiple class="file-input-hidden" required onchange="handleMultipleFileSelect(this, 'fileNamePdf', 'btnPdf')">
                    <span id="fileNamePdf" class="file-name">Ningún archivo seleccionado</span>
                </div>
                <button type="submit" class="btn-save" id="btnPdf" disabled style="width: 100%; justify-content: center; background-color: #ccc; cursor: not-allowed;">
                    <i class="fas fa-upload"></i> Subir Documentos PDF
                </button>
            </form>
        </div>

        <!-- SECCIÓN 3: TABLA DE DIAGNÓSTICO EN TIEMPO REAL -->
        <div class="import-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: var(--udg-blue);"><i class="fas fa-traffic-light"></i> Diagnóstico en Tiempo Real</h3>
                <button type="button" class="btn-cancel-sm" onclick="cargarDiagnostico()"><i class="fas fa-sync-alt"></i> Actualizar</button>
            </div>
            
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Número de Dictamen</th>
                            <th>Materia (Nivel)</th>
                            <th class="td-center">Alumnos</th>
                            <th class="td-center">Estatus PDF</th>
                        </tr>
                    </thead>
                    <tbody id="tablaDiagnostico">
                        <tr><td colspan="4" class="empty-table-msg">Cargando diagnóstico...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <?php include '../main_footer.php'; ?>

    <script>
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

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

        function handleMultipleFileSelect(input, fileNameId, btnId) {
            const fileNameEl = document.getElementById(fileNameId);
            const btnEl = document.getElementById(btnId);
            if (input.files && input.files.length > 0) {
                const count = input.files.length;
                fileNameEl.innerHTML = `<i class="fas fa-copy" style="color:#dc3545;"></i> ${count} archivo(s) seleccionado(s)`;
                fileNameEl.style.color = '#dc3545';
                btnEl.disabled = false; btnEl.style.backgroundColor = '#dc3545'; btnEl.style.cursor = 'pointer';
            } else {
                fileNameEl.textContent = 'Ningún archivo seleccionado';
                fileNameEl.style.color = '#666';
                btnEl.disabled = true; btnEl.style.backgroundColor = '#ccc'; btnEl.style.cursor = 'not-allowed';
            }
        }

        // Petición AJAX para subir el CSV
        function procesarCSV(e) {
            e.preventDefault();
            const input = document.getElementById('csv_input');
            const ignorar = document.getElementById('ignorar_cabecera').checked ? '1' : '0';

            if (!input.files[0]) return;

            let formData = new FormData();
            formData.append('archivo_csv', input.files[0]);
            formData.append('ignorar_cabecera', ignorar); // Enviamos el valor del checkbox
            formData.append('action', 'upload_csv');
            formData.append('csrf_token', csrfToken);

            Swal.fire({ title: 'Procesando CSV...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch('api_dictamenes.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('¡Éxito!', data.message, 'success');
                    cargarDiagnostico(); 
                    document.getElementById('formCSV').reset();
                    handleFileSelect(input, 'fileNameCsv', 'btnCsv');
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            }).catch(err => Swal.fire('Error', 'Problema de red al subir CSV.', 'error'));
        }

        // Petición AJAX para subir los PDFs
        function procesarPDFs(e) {
            e.preventDefault();
            const input = document.getElementById('pdf_input');
            if (input.files.length === 0) return;

            let formData = new FormData();
            formData.append('action', 'upload_pdfs');
            formData.append('csrf_token', csrfToken);
            
            for (let i = 0; i < input.files.length; i++) {
                formData.append('pdfs[]', input.files[i]);
            }

            Swal.fire({ title: 'Subiendo PDFs...', text: `Procesando ${input.files.length} archivos. Puede tardar unos segundos.`, allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch('api_dictamenes.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('¡Éxito!', data.message, 'success');
                    cargarDiagnostico(); 
                    document.getElementById('formPDF').reset();
                    handleMultipleFileSelect(input, 'fileNamePdf', 'btnPdf');
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            }).catch(err => Swal.fire('Error', 'Problema de red al subir PDFs.', 'error'));
        }

        // Llenar tabla en tiempo real
        function cargarDiagnostico() {
            let formData = new URLSearchParams();
            formData.append('action', 'get_status');
            formData.append('csrf_token', csrfToken);

            fetch('api_dictamenes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                const tbody = document.getElementById('tablaDiagnostico');
                tbody.innerHTML = '';

                if (data.error) {
                    tbody.innerHTML = `<tr><td colspan="4" class="empty-table-msg">${data.error}</td></tr>`;
                    return;
                }

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="empty-table-msg"><i class="fas fa-folder-open"></i> No hay dictámenes registrados en el sistema. Sube el CSV primero.</td></tr>';
                    return;
                }
                
                data.forEach(d => {
                    let badge = d.pdf_existe 
                        ? `<span class="tag-active-status"><i class="fas fa-check-circle"></i> Vinculado</span>` 
                        : `<span class="tag-inactive-status"><i class="fas fa-exclamation-triangle"></i> Faltante</span>`;
                    
                    // Diseño más limpio para la materia (como sugeriste)
                    tbody.innerHTML += `
                        <tr class="clickable-row">
                            <td style="font-weight:bold; color: var(--udg-blue);">${d.num_dictamen}</td>
                            <td>
                                <strong>${d.materia}</strong><br>
                                <span style="font-size:0.8rem; color:#888;">Clave: ${d.clave_materia}</span>
                            </td>
                            <td class="td-center"><i class="fas fa-users" style="color:#aaa;"></i> <strong style="font-size:1.1rem;">${d.total_alumnos}</strong></td>
                            <td class="td-center">${badge}</td>
                        </tr>
                    `;
                });
            }).catch(err => console.error('Error cargando diagnóstico'));
        }

        document.addEventListener('DOMContentLoaded', cargarDiagnostico);
    </script>
</body>
</html>