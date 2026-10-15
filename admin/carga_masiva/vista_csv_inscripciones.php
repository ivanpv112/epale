<?php
session_start();
require '../../db.php';
require_once '../../security.php';

validar_csrf_estricto('POST');
requerir_rol('ADMIN');

$resumen = $_SESSION['resumen_inscripciones'] ?? null;
unset($_SESSION['resumen_inscripciones']);

$msg = $_GET['msg'] ?? '';
$tipo_mensaje = '';
$texto_mensaje = '';

if ($msg === 'error_file') {
    $tipo_mensaje = 'error';
    $texto_mensaje = 'Error: No se seleccionó ningún archivo o el formato no es válido (.csv).';
} elseif ($msg === 'error_empty') {
    $tipo_mensaje = 'error';
    $texto_mensaje = 'El archivo CSV está vacío o no contiene filas procesables.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asignación Masiva de Alumnos a Grupos | Admin E-PALE</title>
    <link rel="stylesheet" href="../../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .summary-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
            padding: 24px;
            margin-bottom: 25px;
            border-left: 5px solid var(--udg-blue);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        .stat-box h4 {
            margin: 0;
            font-size: 1.8rem;
            color: var(--udg-blue);
        }
        .stat-box.success h4 { color: #28a745; }
        .stat-box.warning h4 { color: #fd7e14; }
        .stat-box.danger h4 { color: #dc3545; }
        .stat-box p {
            margin: 5px 0 0 0;
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
        }
        .table-omitted {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        .table-omitted th, .table-omitted td {
            padding: 10px 12px;
            border: 1px solid #dee2e6;
            text-align: left;
        }
        .table-omitted th {
            background: #f1f3f5;
            color: #495057;
        }
        .table-omitted tr:nth-child(even) { background: #fafafa; }
        .badge-reason {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-collision { background: #ffeeba; color: #856404; }
        .badge-error { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>

    <?php include '../menu_admin.php'; ?>

    <main class="main-content">
        <a href="../interfaz_carga_masiva" style="display: inline-block; margin-bottom: 20px; color: var(--udg-blue); text-decoration: none; font-weight: bold;">
            <i class="fas fa-arrow-left"></i> Volver a Módulos de Importación
        </a>

        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-users-cog"></i> Carga Masiva de Alumnos a Grupos (Inscripciones)</h1>
            <p>Asigna masivamente alumnos a sus grupos correspondientes mediante archivo CSV.</p>
        </div>

        <?php if (!empty($texto_mensaje)): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>" style="max-width: 1000px; margin: 0 auto 20px auto; padding: 15px; border-radius: 8px; text-align: center; font-size: 1.05rem;">
                <?php echo $texto_mensaje; ?>
            </div>
        <?php endif; ?>

        <?php if ($resumen): ?>
            <div class="summary-card" style="max-width: 1000px; margin: 0 auto 30px auto;">
                <h3 style="margin-top:0; color: var(--udg-blue); display:flex; align-items:center; gap:10px;">
                    <i class="fas fa-clipboard-check"></i> Resultado del Procesamiento
                </h3>
                <p style="color:#555; margin-bottom:10px;">
                    El archivo fue procesado con éxito. Se inscribieron los alumnos válidos y se auto-ajustó el cupo de los grupos cuando fue necesario.
                </p>

                <div class="stats-grid">
                    <div class="stat-box success">
                        <h4><?php echo (int)$resumen['inscritos']; ?></h4>
                        <p>Inscripciones Exitosas</p>
                    </div>
                    <div class="stat-box info">
                        <h4><?php echo (int)($resumen['re_inscritos'] ?? 0); ?></h4>
                        <p>Re-inscritos (Venían de Baja)</p>
                    </div>
                    <div class="stat-box warning">
                        <h4><?php echo (int)($resumen['ya_inscritos'] ?? 0); ?></h4>
                        <p>Ya estaban inscritos</p>
                    </div>
                    <div class="stat-box <?php echo count($resumen['omitidos']) > 0 ? 'danger' : ''; ?>">
                        <h4><?php echo count($resumen['omitidos']); ?></h4>
                        <p>Omitidos / Errores</p>
                    </div>
                </div>

                <?php if (!empty($resumen['cupos_ajustados'])): ?>
                    <div style="background: #e8f4fd; border: 1px solid #b8daff; border-radius: 8px; padding: 12px 16px; margin: 15px 0; color: #004085;">
                        <i class="fas fa-expand-arrows-alt"></i> <strong>Ajuste automático de cupo:</strong>
                        Se aumentó el cupo de <strong><?php echo count($resumen['cupos_ajustados']); ?> grupo(s)</strong> para garantizar lugar a todos los estudiantes asignados sin generar rechazo.
                    </div>
                <?php endif; ?>

                <?php if (!empty($resumen['omitidos'])): ?>
                    <h4 style="color: #dc3545; margin-top: 25px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-exclamation-triangle"></i> Registros Omitidos (Requieren atención)
                    </h4>
                    <p style="font-size:0.9rem; color:#666;">
                        Los siguientes registros no se pudieron inscribir. Puedes revisar el número de línea de tu archivo Excel, corregirlos y volver a cargarlos si es necesario:
                    </p>
                    <div style="overflow-x: auto;">
                        <table class="table-omitted">
                            <thead>
                                <tr>
                                    <th style="width: 70px; text-align:center;">Fila</th>
                                    <th style="width: 120px;">Código Alumno</th>
                                    <th>Nombre del Alumno</th>
                                    <th style="width: 100px;">NRC Solicitado</th>
                                    <th>Materia / Grupo</th>
                                    <th>Causa / Conflicto Detectado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resumen['omitidos'] as $om): ?>
                                    <tr>
                                        <td style="text-align:center; font-weight:bold; color:#d9534f;">#<?php echo (int)$om['fila']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($om['codigo']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($om['nombre_alumno']); ?></td>
                                        <td><span style="font-family: monospace; font-weight:bold;"><?php echo htmlspecialchars($om['nrc']); ?></span></td>
                                        <td><?php echo htmlspecialchars($om['info_grupo']); ?></td>
                                        <td>
                                            <?php if ($om['tipo'] === 'CHOQUE'): ?>
                                                <span class="badge-reason badge-collision"><i class="fas fa-clock"></i> Choque de Horario</span><br>
                                                <small style="color:#856404; font-weight:500;"><?php echo htmlspecialchars($om['detalle']); ?></small>
                                            <?php else: ?>
                                                <span class="badge-reason badge-error"><i class="fas fa-times-circle"></i> Error de Datos</span><br>
                                                <small style="color:#721c24; font-weight:500;"><?php echo htmlspecialchars($om['detalle']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 12px 16px; margin-top: 15px; color: #155724;">
                        <i class="fas fa-check-circle"></i> <strong>¡Excelente!</strong> No se detectó ningún choque de horario ni error en el archivo. Todos los alumnos fueron procesados correctamente.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="import-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0; color: var(--udg-blue);">Estructura del Archivo (2 Columnas)</h3>
                <button type="button" class="btn-cancel-sm" onclick="toggleExcel('excelInscripciones')"><i class="fas fa-file-excel"></i> Ver ejemplo visual</button>
            </div>

            <div class="excel-table-wrapper" id="excelInscripciones" style="display: block;">
                <div class="excel-table-container">
                    <table class="excel-table">
                        <thead>
                            <tr> 
                                <th class="excel-col-header" style="width: 30px;"></th> 
                                <th class="excel-col-header">A</th> 
                                <th class="excel-col-header">B</th> 
                            </tr>
                            <tr> 
                                <th style="background:#e6e6e6; text-align:center; font-weight:bold;">1</th> 
                                <th>CODIGO_ESTUDIANTE</th> 
                                <th>NRC</th> 
                            </tr>
                        </thead>
                        <tbody>
                            <tr> 
                                <td style="background:#e6e6e6; text-align:center; font-weight:bold;">2</td> 
                                <td>218493021</td> 
                                <td>199516</td> 
                            </tr>
                            <tr> 
                                <td style="background:#e6e6e6; text-align:center; font-weight:bold;">3</td> 
                                <td>219503920</td> 
                                <td>199516</td> 
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="excel-info-box">
                    <i class="fas fa-info-circle" style="color:var(--udg-blue);"></i> <strong>Reglas del Módulo:</strong><br>
                    1. <strong>CODIGO_ESTUDIANTE:</strong> El código UDG del alumno registrado en E-PALE.<br>
                    2. <strong>NRC:</strong> El número de referencia del grupo al que se le inscribirá.<br>
                    3. <strong>Auto-Aumento de Cupo:</strong> Si el grupo supera el cupo por defecto (30 alumnos), el sistema aumentará automáticamente la capacidad del grupo para que ningún alumno quede fuera por límite de cupo.<br>
                    4. <strong>Protección contra Choques de Horario:</strong> Si el alumno ya cursa otra materia en ese mismo horario y día dentro del mismo ciclo, el sistema lo <strong>omitirá</strong> y te mostrará el detalle exacto con qué materia choca y en qué fila del archivo para que puedas resolverlo.
                </div>
            </div>

            <form method="POST" action="importar_inscripciones" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="file-input-wrapper">
                    <label for="csv_inscripciones" class="file-input-label" id="label_inscripciones">
                        <i class="fas fa-cloud-upload-alt"></i> Haz clic para seleccionar el CSV de Inscripciones
                    </label>
                    <input type="file" name="archivo_csv" id="csv_inscripciones" class="file-input-hidden" accept=".csv" required onchange="handleFileSelect(this, 'fileNameInscripciones', 'btnInscripciones')">
                    <span id="fileNameInscripciones" class="file-name">Ningún archivo seleccionado</span>
                </div>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; font-weight: 500; color: #444; margin-bottom:15px;">
                    <input type="checkbox" name="ignorar_cabecera" value="1" checked style="width: 18px; height: 18px;"> Ignorar la fila 1 (Encabezados)
                </label>
                <button type="submit" class="btn-save" id="btnInscripciones" disabled style="width: 100%; justify-content: center; background-color: #ccc; cursor: not-allowed;">
                    <i class="fas fa-upload"></i> Procesar Asignación de Alumnos
                </button>
            </form>
        </div>

    </main>

    <?php include '../../main_footer.php'; ?>

    <script>
        function toggleMobileMenu() { 
            document.getElementById('navWrapper').classList.toggle('active'); 
            document.getElementById('menuOverlay').classList.toggle('active'); 
        }
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
