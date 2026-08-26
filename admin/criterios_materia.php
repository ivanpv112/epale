<?php
session_start();
require '../db.php';

// 1. SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

// 2. VERIFICAR QUE SE HAYA SELECCIONADO UNA MATERIA
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: materias.php"); exit;
}
$materia_id = $_GET['id'];

// 3. OBTENER DATOS DE LA MATERIA
$stmt_mat = $pdo->prepare("SELECT * FROM materias WHERE materia_id = ?");
$stmt_mat->execute([$materia_id]);
$materia = $stmt_mat->fetch(PDO::FETCH_ASSOC);

if (!$materia) {
    header("Location: materias.php"); exit;
}

// 4. PROCESAR GUARDAR (NUEVO Y EDITAR)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_criterio'])) {
    $criterio_id = $_POST['criterio_id'] ?? '';
    $categoria = trim($_POST['categoria']);
    $codigo_examen = strtoupper(trim($_POST['codigo_examen']));
    $nombre_examen = trim($_POST['nombre_examen']);
    $puntos = floatval($_POST['puntos_maximos']);
    $icono = $_POST['icono'];
    $color = $_POST['color'];

    if (empty($criterio_id)) {
        $sql = "INSERT INTO criterios_evaluacion (materia_id, categoria, codigo_examen, nombre_examen, puntos_maximos, icono, color) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$materia_id, $categoria, $codigo_examen, $nombre_examen, $puntos, $icono, $color]);
    } else {
        $sql = "UPDATE criterios_evaluacion 
                SET categoria=?, codigo_examen=?, nombre_examen=?, puntos_maximos=?, icono=?, color=? 
                WHERE criterio_id=?";
        $pdo->prepare($sql)->execute([$categoria, $codigo_examen, $nombre_examen, $puntos, $icono, $color, $criterio_id]);
    }
    
    header("Location: criterios_materia.php?id=" . $materia_id . "&exito=1"); exit;
}

// 4.5. PROCESAR PLANTILLA BASE (AUTO-CRITERIOS INTELIGENTES POR NIVEL)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['auto_criterios'])) {
    
    $es_nivel_4 = ($materia['nivel'] == 4);

    // Criterios base para todos los niveles (Suman 50 pts)
    $criterios_base = [
        ['Exámenes', 'Q1', 'Examen 1', 10, 'fa-book-open', 'var(--udg-light)'],
        ['Exámenes', 'Q2', 'Examen 2', 10, 'fa-book-open', 'var(--udg-light)'],
        ['Exámenes', 'Q3', 'Examen 3', 10, 'fa-book-open', 'var(--udg-light)'],
        ['Exámenes Orales', 'QO1', 'Examen Oral 1', 5, 'fa-comments', '#28a745'],
        ['Exámenes Orales', 'QO2', 'Examen Oral 2', 5, 'fa-comments', '#28a745'],
        ['Proyectos', 'WRITING', 'Proyecto Escrito', 5, 'fa-file-signature', '#ffc107'],
        ['Participación', 'PARTICIPACION', 'Participación en Clase', 5, 'fa-hand-paper', '#17a2b8']
    ];

    // Distribución de los 50 pts restantes dependiendo del nivel
    if ($es_nivel_4) {
        $criterios_base[] = ['Plataforma', 'PLATAFORMA', 'Actividades en Plataforma', 40, 'fa-laptop-code', '#dc3545'];
        $criterios_base[] = ['Certificación', 'CERTIFICACION', 'Examen de Certificación', 10, 'fa-certificate', '#6f42c1'];
    } else {
        // Al no haber certificación, los 10 puntos pasan a la plataforma para cuadrar los 100
        $criterios_base[] = ['Plataforma', 'PLATAFORMA', 'Actividades en Plataforma', 50, 'fa-laptop-code', '#dc3545'];
    }

    $sql = "INSERT INTO criterios_evaluacion (materia_id, categoria, codigo_examen, nombre_examen, puntos_maximos, icono, color) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    
    foreach ($criterios_base as $cb) {
        $stmt->execute([$materia_id, $cb[0], $cb[1], $cb[2], $cb[3], $cb[4], $cb[5]]);
    }
    
    header("Location: criterios_materia.php?id=" . $materia_id . "&exito=auto"); exit;
}

// 5. PROCESAR ELIMINAR CRITERIO
if (isset($_GET['borrar_criterio'])) {
    $criterio_id = $_GET['borrar_criterio'];
    $pdo->prepare("DELETE FROM criterios_evaluacion WHERE criterio_id = ?")->execute([$criterio_id]);
    header("Location: criterios_materia.php?id=" . $materia_id . "&exito=borrado"); exit;
}

// 6. OBTENER LOS CRITERIOS ACTUALES Y SUMAR PUNTOS
$stmt_crit = $pdo->prepare("SELECT * FROM criterios_evaluacion WHERE materia_id = ? ORDER BY categoria ASC, criterio_id ASC");
$stmt_crit->execute([$materia_id]);
$criterios = $stmt_crit->fetchAll(PDO::FETCH_ASSOC);

$total_puntos = 0;
foreach ($criterios as $c) {
    $total_puntos += floatval($c['puntos_maximos']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Configurar Evaluación | Admin</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .smart-dropdown {
            display: none; position: absolute; top: calc(100% + 5px); left: 0; right: 0;
            background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; z-index: 1000;
            max-height: 200px; overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .smart-option {
            padding: 10px 15px; cursor: pointer; color: #333; font-size: 0.95rem; transition: background 0.2s; border-bottom: 1px solid #f8f9fa;
        }
        .smart-option:hover { background: #f0f7ff; color: var(--udg-blue); font-weight: 500; }
        .smart-option:last-child { border-bottom: none; }
    </style>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        
        <a href="materias.php" style="display: inline-block; margin-bottom: 20px; color: var(--udg-blue); text-decoration: none; font-weight: bold;">
            <i class="fas fa-arrow-left"></i> Volver a la página anterior
        </a>

        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-cogs"></i> Configurar Evaluación</h1>
            <p>Define los parámetros, rubros y puntajes para calificar esta materia.</p>
        </div>

        <div class="card" style="background: linear-gradient(135deg, var(--udg-blue) 0%, #001a57 100%); color: white; border: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div>
                    <span style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-family: monospace;"><?php echo htmlspecialchars($materia['clave']); ?></span>
                    <h2 style="margin: 10px 0 0 0; color: white;"><?php echo htmlspecialchars($materia['nombre']); ?> - Nivel <?php echo $materia['nivel']; ?></h2>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.9rem; opacity: 0.8;">Puntos Totales Configurados</div>
                    <div style="font-size: 2.5rem; font-weight: bold; <?php echo ($total_puntos > 105) ? 'color: #ffc107;' : ''; ?>">
                        <?php echo $total_puntos; ?> <span style="font-size: 1rem; font-weight: normal;">pts</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if(isset($_GET['exito'])): ?>
            <div class="alert alert-success" style="margin-top: 20px;">
                <i class="fas fa-check-circle"></i> 
                <?php echo ($_GET['exito'] == 'auto') ? 'Plantilla base generada correctamente según el nivel.' : 'Cambios guardados correctamente.'; ?>
            </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; margin-bottom: 15px; flex-wrap: wrap; gap: 15px;">
            <h3 style="margin: 0; color: var(--udg-blue);"><i class="fas fa-list-ul"></i> Criterios Actuales</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                
                <?php if (count($criterios) === 0): ?>
                    <form method="POST" id="formAutoCriterios" style="margin: 0;">
                        <input type="hidden" name="auto_criterios" value="1">
                        <button type="button" class="btn-auto" onclick="confirmarPlantilla(<?php echo $materia['nivel']; ?>)">
                            <i class="fas fa-magic"></i> Plantilla Base
                        </button>
                    </form>
                <?php endif; ?>

                <button type="button" class="btn-save" onclick="openModal()">
                    <i class="fas fa-plus-circle"></i> Agregar Criterio
                </button>
            </div>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-wrapper" style="overflow-x:auto;">
                <table class="history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Categoría</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Código</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Nombre del Examen/Actividad</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Pts Máximos</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Apariencia</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($criterios) > 0): ?>
                            <?php foreach ($criterios as $c): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 15px; font-weight: bold; color: #555;"><?php echo htmlspecialchars($c['categoria']); ?></td>
                                <td style="padding: 15px;"><span style="background: #eee; padding: 3px 8px; border-radius: 4px; font-family: monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($c['codigo_examen']); ?></span></td>
                                <td style="padding: 15px;"><?php echo htmlspecialchars($c['nombre_examen']); ?></td>
                                <td style="padding: 15px; text-align: center; font-weight: bold; font-size: 1.1rem; color: var(--udg-blue);"><?php echo floatval($c['puntos_maximos']); ?></td>
                                <td style="padding: 15px; text-align: center;">
                                    <i class="fas <?php echo htmlspecialchars($c['icono']); ?>" style="color: <?php echo htmlspecialchars($c['color']); ?>; font-size: 1.2rem;" title="<?php echo htmlspecialchars($c['color']); ?>"></i>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <button class="action-btn" onclick='editCriterio(<?php echo json_encode($c); ?>)' style="background: none; border: none; color: var(--udg-blue); cursor: pointer; font-size: 1.1rem; margin-right: 10px;" title="Editar Criterio">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    
                                    <a href="criterios_materia.php?id=<?php echo $materia_id; ?>&borrar_criterio=<?php echo $c['criterio_id']; ?>" class="action-btn delete" onclick="confirmarBorrado(event, this.href)" style="color: #dc3545; font-size: 1.1rem;" title="Eliminar">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-light);">
                                    <i class="fas fa-clipboard-list" style="font-size: 2.5rem; margin-bottom: 10px; display: block;"></i>
                                    Aún no has agregado ningún criterio para esta materia.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <div id="criterioModal" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width: 600px; position: relative;">
            <div class="modal-header">
                <h2 id="modalTitle" style="margin: 0;"><i class="fas fa-plus-circle"></i> Agregar Criterio</h2>
                <button type="button" class="close-btn" onclick="closeModal()" style="position: absolute; right: 20px; top: 15px;">&times;</button>
            </div>
            
            <form method="POST" id="formCriterio" style="margin: 0;">
                <input type="hidden" name="save_criterio" value="1">
                <input type="hidden" name="criterio_id" id="criterioId">
                
                <div class="modal-body" style="padding-top: 0; overflow-y: visible;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        
                        <div class="form-group" style="grid-column: span 2; position: relative;"> 
                            <label>Categoría (Grupo visual) <span style="color:red;">*</span></label> 
                            <input type="text" name="categoria" id="critCategoria" required placeholder="Ej. Exámenes, Proyectos, Plataforma..." autocomplete="off"> 
                            <div id="dropCategoria" class="smart-dropdown"></div>
                            <small style="color: #888; font-size: 0.8rem; display: block; margin-top: 5px;">Las actividades con la misma categoría se agruparán en la misma tarjeta.</small>
                        </div>

                        <!-- AQUÍ BLOQUEAMOS EL CÓDIGO INTERNO PARA EVITAR ERRORES DE USUARIO -->
                        <div class="form-group"> 
                            <label>Código Interno <span style="color:red;">*</span></label> 
                            <select name="codigo_examen" id="critCodigo" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit;">
                                <option value="">-- Seleccionar Código --</option>
                                <option value="Q1">Q1 (Examen 1)</option>
                                <option value="Q2">Q2 (Examen 2)</option>
                                <option value="Q3">Q3 (Examen 3)</option>
                                <option value="QO1">QO1 (Examen Oral 1)</option>
                                <option value="QO2">QO2 (Examen Oral 2)</option>
                                <option value="WRITING">WRITING (Proyecto Escrito)</option>
                                <option value="PLATAFORMA">PLATAFORMA (Act. Moodle)</option>
                                <option value="PARTICIPACION">PARTICIPACION (En clase)</option>
                                <option value="CERTIFICACION">CERTIFICACION (TOEFL/DELF)</option>
                                <option value="FINAL">FINAL (Examen Extra)</option>
                            </select>
                        </div>

                        <div class="form-group"> 
                            <label>Puntos Máximos <span style="color:red;">*</span></label> 
                            <input type="number" name="puntos_maximos" id="critPuntos" step="0.01" required min="0.1" placeholder="Ej. 10"> 
                        </div>

                        <div class="form-group" style="grid-column: span 2; position: relative;"> 
                            <label>Nombre del Examen/Actividad (Visible para el alumno) <span style="color:red;">*</span></label> 
                            <input type="text" name="nombre_examen" id="critNombre" required placeholder="Ej. Examen 1, Actividades Moodle..." autocomplete="off"> 
                            <div id="dropNombre" class="smart-dropdown"></div>
                        </div>

                        <div class="form-group">
                            <label>Icono</label>
                            <select name="icono" id="critIcono" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit;">
                                <option value="fa-star">★ Estrella (Defecto)</option>
                                <option value="fa-book-open">📖 Libro (Exámenes/Lecturas)</option>
                                <option value="fa-comments">💬 Comentarios (Orales)</option>
                                <option value="fa-file-signature">📝 Papel (Proyectos)</option>
                                <option value="fa-laptop-code">💻 Laptop (Plataforma)</option>
                                <option value="fa-hand-paper">✋ Mano (Participación)</option>
                                <option value="fa-certificate">🎓 Certificado (Idioma/Final)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Color</label>
                            <select name="color" id="critColor" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit;">
                                <option value="var(--udg-light)">Azul (Exámenes)</option>
                                <option value="#28a745">Verde (Orales)</option>
                                <option value="#ffc107">Amarillo (Proyectos)</option>
                                <option value="#dc3545">Rojo (Plataforma)</option>
                                <option value="#17a2b8">Cian (Participación)</option>
                                <option value="#6f42c1">Morado (Certificación)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="margin: 0; border-top: 1px solid #eee; background-color: #fcfcfc;">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save" id="btnSubmit"><i class="fas fa-save"></i> Guardar Criterio</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }

        const modal = document.getElementById('criterioModal');
        const overlayMenu = document.getElementById('menuOverlay');

        function openModal() { 
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle"></i> Agregar Criterio';
            document.getElementById('criterioId').value = '';
            document.getElementById('formCriterio').reset(); 
            document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-plus"></i> Agregar Criterio';
            modal.style.display = 'flex'; 
        }

        function editCriterio(crit) {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Editar Criterio';
            document.getElementById('criterioId').value = crit.criterio_id;
            
            document.getElementById('critCategoria').value = crit.categoria;
            document.getElementById('critCodigo').value = crit.codigo_examen; // Ahora selecciona del dropdown
            document.getElementById('critPuntos').value = crit.puntos_maximos;
            document.getElementById('critNombre').value = crit.nombre_examen;
            document.getElementById('critIcono').value = crit.icono;
            document.getElementById('critColor').value = crit.color;

            document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
            modal.style.display = 'flex';
        }

        function closeModal() { modal.style.display = 'none'; }

        window.onclick = function(e) { 
            if(e.target == modal) closeModal(); 
            if(e.target == overlayMenu) toggleMobileMenu();
        };

        // =====================================================================
        // SWEETALERT2 PARA ALERTAS NATIVAS
        // =====================================================================
        function confirmarPlantilla(nivel) {
            let texto = nivel == 4 
                ? "Se cargarán 9 criterios de evaluación (incluyendo Certificación) sumando 100 puntos totales." 
                : "Se cargarán 8 criterios de evaluación sumando 100 puntos totales (La Plataforma valdrá 50 pts al no haber Certificación).";
            
            Swal.fire({
                title: '¿Generar plantilla base?',
                text: texto,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#6f42c1',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, generar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('formAutoCriterios').submit();
                }
            });
        }

        function confirmarBorrado(e, url) {
            e.preventDefault();
            Swal.fire({
                title: '¿Borrar este criterio?',
                text: "Esto podría afectar las calificaciones de los alumnos si ya fueron evaluados en este rubro.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, borrar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        // =====================================================================
        // LÓGICA DEL MENÚ DESPLEGABLE INTELIGENTE (AUTOCOMPLETE ACTUALIZADO)
        // =====================================================================
        const dataCategoria = ['Exámenes', 'Exámenes Orales', 'Proyectos', 'Plataforma', 'Participación', 'Certificación', 'Examen Final'];
        const dataNombre = ['Examen 1', 'Examen 2', 'Examen 3', 'Examen Oral 1', 'Examen Oral 2', 'Proyecto Escrito', 'Actividades en Plataforma', 'Participación en Clase', 'Examen de Certificación', 'Examen Final'];

        function setupAutocomplete(inputId, dropId, list) {
            const input = document.getElementById(inputId);
            const drop = document.getElementById(dropId);

            function renderOptions(filter) {
                drop.innerHTML = '';
                const lowerFilter = filter.toLowerCase();
                const filtered = list.filter(item => item.toLowerCase().includes(lowerFilter));
                
                if(filtered.length === 0) { drop.style.display = 'none'; return; }

                filtered.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'smart-option';
                    div.textContent = item;
                    div.onmousedown = function(e) { e.preventDefault(); }
                    div.onclick = function() {
                        input.value = item;
                        drop.style.display = 'none';
                    };
                    drop.appendChild(div);
                });
                drop.style.display = 'block';
            }

            input.addEventListener('focus', () => renderOptions(input.value));
            input.addEventListener('input', (e) => renderOptions(e.target.value));
            input.addEventListener('blur', () => { drop.style.display = 'none'; });
        }

        setupAutocomplete('critCategoria', 'dropCategoria', dataCategoria);
        setupAutocomplete('critNombre', 'dropNombre', dataNombre);

    </script>
</body>
</html>
