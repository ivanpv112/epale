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
    <title>Descarga Masiva | Admin E-PALE</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-file-export"></i> Módulos de Exportación Masiva</h1>
            <p>Selecciona la tabla que deseas descargar desde el sistema en formato CSV.</p>
        </div>

        <style>
            .module-card.disabled {
                opacity: 0.6;
                pointer-events: none;
                filter: grayscale(100%);
                box-shadow: none;
                border-color: #ddd;
            }
        </style>
        
<?php
    $stmt_carr = $pdo->query("SELECT DISTINCT carrera FROM alumnos WHERE carrera IS NOT NULL AND carrera != '' ORDER BY carrera");
    $carreras = $stmt_carr->fetchAll(PDO::FETCH_COLUMN);
?>
        <div class="module-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <a href="#" onclick="solicitarExportacion(event, 'GLOBAL')" class="module-card" id="card-GLOBAL" style="text-decoration: none;">
                <i class="fas fa-globe"></i>
                <h3>Descarga Global</h3>
                <p>Exporta toda la información del sitio en un archivo ZIP organizado por CSVs.</p>
                
                <div class="progress-container" id="progreso-container-GLOBAL" style="display: none; margin-top: 15px;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 5px; color: var(--udg-blue); font-weight: bold;">
                        <span id="progreso-label-GLOBAL">Procesando...</span>
                        <span id="progreso-text-GLOBAL">0%</span>
                    </div>
                    <div class="progress-bar-bg" style="background:#eee; height:10px; border-radius:5px; overflow:hidden;">
                        <div class="progress-bar-fill" id="progreso-fill-GLOBAL" style="width: 0%; height:100%; background: #00d27a; transition: width 0.5s ease;"></div>
                    </div>
                </div>
            </a>

            <a href="#" onclick="solicitarExportacionCarrera(event)" class="module-card" id="card-CARRERA" style="text-decoration: none;">
                <i class="fas fa-filter"></i>
                <h3>Descarga por Carrera</h3>
                <p>Exporta datos excluyendo Profesores y Grupos, filtrados solo para la carrera seleccionada.</p>
                
                <div class="progress-container" id="progreso-container-CARRERA" style="display: none; margin-top: 15px;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 5px; color: var(--udg-blue); font-weight: bold;">
                        <span id="progreso-label-CARRERA">Procesando...</span>
                        <span id="progreso-text-CARRERA">0%</span>
                    </div>
                    <div class="progress-bar-bg" style="background:#eee; height:10px; border-radius:5px; overflow:hidden;">
                        <div class="progress-bar-fill" id="progreso-fill-CARRERA" style="width: 0%; height:100%; background: #00d27a; transition: width 0.5s ease;"></div>
                    </div>
                </div>
            </a>
        </div>

        <!-- HISTORIAL DE DESCARGAS -->
        <div style="margin-top: 50px;">
            <h3 style="color: var(--udg-blue);"><i class="fas fa-history"></i> Historial de Descargas</h3>
            <p style="color: #666; font-size: 0.9rem;">Aquí aparecerán los archivos generados. Puedes seguir navegando mientras se procesan.</p>
            
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead class="table-header-clean">
                        <tr>
                            <th>Folio</th>
                            <th>Tipo</th>
                            <th>Fecha de Solicitud</th>
                            <th>Estatus</th>
                            <th>Progreso</th>
                            <th style="text-align:center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-exportaciones">
                        <tr><td colspan="6" class="empty-table-msg">Cargando descargas...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <?php include '../main_footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }

        // Función para consultar la BD periódicamente
        function cargarHistorial() {
            $.ajax({
                url: 'descarga_masiva/ajax_obtener_exportaciones.php',
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if(res.success) {
                        let html = '';
                        let modulosActivos = {}; // Para bloquear tarjetas

                        if(res.data.length === 0) {
                            html = '<tr><td colspan="6" class="empty-table-msg">No hay descargas recientes.</td></tr>';
                        } else {
                            res.data.forEach(function(item) {
                                let badge = '';
                                let accion = '';
                                let progreso_visual = item.progreso ? item.progreso : 0;
                                
                                if(item.estado === 'PENDIENTE' || item.estado === 'PROCESANDO') {
                                    // Si está activo, lo marcamos para bloquear la tarjeta
                                    if(!modulosActivos[item.tipo]) {
                                        modulosActivos[item.tipo] = progreso_visual;
                                    }
                                }

                                if(item.estado === 'COMPLETADO') {
                                    badge = '<span class="tag-active-status">Completado</span>';
                                    accion = `<a href="${item.archivo_ruta}" download class="btn-auto" style="padding: 6px 12px; font-size:0.85rem;"><i class="fas fa-download"></i> Descargar ZIP</a>`;
                                } else if(item.estado === 'PENDIENTE') {
                                    badge = '<span class="tag-inactive-status" style="background:#fff3cd; color:#856404;">En Cola</span>';
                                    accion = '<span style="color:#aaa; font-size:0.85rem;"><i class="fas fa-hourglass-half"></i> Esperando...</span>';
                                } else if(item.estado === 'PROCESANDO') {
                                    badge = '<span class="tag-inactive-status" style="background:#cff4fc; color:#055160;">Procesando</span>';
                                    accion = '<span style="color:#0d6efd; font-size:0.85rem;"><i class="fas fa-spinner fa-spin"></i> Empaquetando...</span>';
                                } else {
                                    badge = '<span class="tag-inactive-status">Error</span>';
                                    progreso_visual = 0;
                                    accion = '-';
                                }

                                html += `<tr class="group-row">
                                    <td><strong>#${item.id}</strong></td>
                                    <td><i class="fas fa-file-archive" style="color:#6c757d; margin-right:5px;"></i> ${item.tipo}</td>
                                    <td style="color:#666; font-size:0.9rem;">${item.fecha_solicitud}</td>
                                    <td>${badge}</td>
                                    <td>
                                        <div style="background:#eee; height:6px; border-radius:3px; width:100%; margin-top:5px;">
                                            <div style="background:#00d27a; height:100%; width:${progreso_visual}%; border-radius:3px; transition: 0.5s;"></div>
                                        </div>
                                        <small style="color:#888;">${progreso_visual}%</small>
                                    </td>
                                    <td style="text-align:center;">${accion}</td>
                                </tr>`;
                            });
                        }
                        $('#tabla-exportaciones').html(html);

                        // Actualizar UI de las tarjetas bloqueadas
                        actualizarTarjetasUI(modulosActivos);
                    }
                }
            });
        }

        // Bloquea o desbloquea tarjetas y actualiza sus barras
        function actualizarTarjetasUI(modulosActivos) {
            // Ejemplo para GLOBAL, si en el futuro hay más módulos, iterar sobre la lista de módulos
            let modulo = 'GLOBAL';
            let card = $('#card-' + modulo);
            let container = $('#progreso-container-' + modulo);
            let fill = $('#progreso-fill-' + modulo);
            let text = $('#progreso-text-' + modulo);

            if (modulosActivos[modulo] !== undefined) {
                // Está procesando
                card.addClass('disabled');
                container.show();
                let pct = modulosActivos[modulo];
                fill.css('width', pct + '%');
                text.text(pct + '%');
            } else {
                // Está libre
                card.removeClass('disabled');
                container.hide();
                fill.css('width', '0%');
                text.text('0%');
            }
        }

        // Ejecutar solicitud asíncrona de descarga
        function solicitarExportacion(e, tipoModulo) {
            e.preventDefault();
            Swal.fire({
                title: '¿Generar Exportación?',
                text: "El reporte se procesará en segundo plano.",
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#0d2366',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, generar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarAjaxExportacion(tipoModulo);
                }
            });
        }

        // Lista de carreras desde PHP a JS
        const opcionesCarreras = {
            <?php foreach($carreras as $c): ?>
            "<?php echo htmlspecialchars($c, ENT_QUOTES); ?>": "<?php echo htmlspecialchars($c, ENT_QUOTES); ?>",
            <?php endforeach; ?>
        };

        function solicitarExportacionCarrera(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Selecciona una Carrera',
                text: 'Elige de la lista la carrera para filtrar el reporte.',
                icon: 'info',
                input: 'select',
                inputOptions: opcionesCarreras,
                inputPlaceholder: 'Elige una carrera...',
                showCancelButton: true,
                confirmButtonColor: '#0d2366',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Siguiente',
                cancelButtonText: 'Cancelar',
                inputValidator: (value) => {
                    return new Promise((resolve) => {
                        if (value !== '') {
                            resolve();
                        } else {
                            resolve('Debes seleccionar una carrera');
                        }
                    });
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    let carreraSeleccionada = result.value;
                    // Segunda confirmación idéntica a Global
                    Swal.fire({
                        title: `¿Exportar datos para ${carreraSeleccionada}?`,
                        text: "El reporte se procesará en segundo plano.",
                        icon: 'info',
                        showCancelButton: true,
                        confirmButtonColor: '#0d2366',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, generar',
                        cancelButtonText: 'Cancelar'
                    }).then((confirmacion) => {
                        if (confirmacion.isConfirmed) {
                            ejecutarAjaxExportacion('CARRERA_' + carreraSeleccionada);
                        }
                    });
                }
            });
        }

        function ejecutarAjaxExportacion(tipoModulo) {
            $.ajax({
                url: 'descarga_masiva/ajax_solicitar_exportacion.php',
                type: 'POST',
                dataType: 'json',
                data: { tipo: tipoModulo },
                success: function(res) {
                    if(res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡En cola!',
                            text: res.message,
                            timer: 3000,
                            showConfirmButton: false
                        });
                        cargarHistorial(); // Refrescar tabla de inmediato
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
                }
            });
        }

        // Iniciar el polling (consulta) cuando cargue la página
        $(document).ready(function() {
            cargarHistorial();
            // Actualizar la tabla y barras cada 3 segundos
            setInterval(cargarHistorial, 3000);
        });
    </script>
</body>
</html>