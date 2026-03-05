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
        // ES UN CRITERIO NUEVO
        $sql = "INSERT INTO criterios_evaluacion (materia_id, categoria, codigo_examen, nombre_examen, puntos_maximos, icono, color) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$materia_id, $categoria, $codigo_examen, $nombre_examen, $puntos, $icono, $color]);
    } else {
        // SE ESTÁ EDITANDO UN CRITERIO EXISTENTE
        $sql = "UPDATE criterios_evaluacion 
                SET categoria=?, codigo_examen=?, nombre_examen=?, puntos_maximos=?, icono=?, color=? 
                WHERE criterio_id=?";
        $pdo->prepare($sql)->execute([$categoria, $codigo_examen, $nombre_examen, $puntos, $icono, $color, $criterio_id]);
    }
    
    header("Location: criterios_materia.php?id=" . $materia_id . "&exito=1"); exit;
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

// FOTO DEL ADMIN (Para el header)
$stmt_foto = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE usuario_id = ?");
$stmt_foto->execute([$_SESSION['user_id']]);
$user_foto = $stmt_foto->fetchColumn();
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
</head>
<body>

    <header class="main-header" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; height: 65px;">
        <div class="logo-container" style="display: flex; align-items: center; width: auto; margin: 0;">
            <a href="dashboard.php" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: white;">
                <img src="../img/logo-pale.png" alt="E-PALE" class="logo-img">
                <span style="font-size: 1.2rem; font-weight: bold;">e-PALE</span>
            </a>
        </div>

        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="perfil.php" style="text-decoration: none; color: white; display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); padding: 5px 15px 5px 5px; border-radius: 20px;">
                <?php if($user_foto && file_exists("../img/perfiles/" . $user_foto)): ?>
                    <img src="../img/perfiles/<?php echo $user_foto; ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid white; background:white;">
                <?php else: ?>
                    <i class="fas fa-user-circle" style="font-size: 1.8rem;"></i>
                <?php endif; ?>
                <span class="profile-name" style="font-weight: 500;"><?php echo strtok($_SESSION['nombre'], " "); ?></span>
            </a>
            <button onclick="toggleMobileMenu()" style="background: transparent; border: none; color: white; font-size: 1.8rem; cursor: pointer; padding: 0;">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </header>

    <div class="menu-overlay" id="menuOverlay" onclick="toggleMobileMenu()"></div>

    <aside class="yt-sidebar" id="navWrapper">
        <div class="yt-sidebar-header">
            <span style="color: white; font-size: 1.1rem; font-weight: bold;">Panel Admin</span>
            <button onclick="toggleMobileMenu()" style="background: transparent; border: none; color: #aaa; font-size: 2rem; cursor: pointer; line-height: 1;">&times;</button>
        </div>

        <ul class="yt-sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="usuarios.php"><i class="fas fa-users"></i> Gestión de Usuarios</a></li>
            <li><a href="materias.php" class="active"><i class="fas fa-book"></i> Materias y Criterios</a></li>
            <li><a href="reportes.php"><i class="fas fa-chart-line"></i> Reportes Generales</a></li>
        </ul>

        <div class="sidebar-divider"></div>

        <ul class="yt-sidebar-menu">
            <li><a href="perfil.php"><i class="far fa-user-circle"></i> Mi Perfil</a></li>
            <li><a href="../logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt" style="color: #ff6b6b;"></i> Cerrar Sesión</a></li>
        </ul>
    </aside>

    <main class="main-content">
        
        <a href="materias.php" style="display: inline-block; margin-bottom: 20px; color: var(--udg-blue); text-decoration: none; font-weight: bold;">
            <i class="fas fa-arrow-left"></i> Volver a Materias
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
            <div class="alert alert-success" style="margin-top: 20px;"><i class="fas fa-check-circle"></i> Cambios guardados correctamente.</div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; margin-bottom: 15px;">
            <h3 style="margin: 0; color: var(--udg-blue);"><i class="fas fa-list-ul"></i> Criterios Actuales</h3>
            <button type="button" class="btn-save" onclick="openModal()">
                <i class="fas fa-plus-circle"></i> Agregar Criterio
            </button>
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
                                    
                                    <a href="criterios_materia.php?id=<?php echo $materia_id; ?>&borrar_criterio=<?php echo $c['criterio_id']; ?>" class="action-btn delete" onclick="return confirm('¿Borrar este criterio? Esto podría afectar las calificaciones de los alumnos si ya fueron evaluados en este rubro.');" style="color: #dc3545; font-size: 1.1rem;" title="Eliminar">
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
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 id="modalTitle">Agregar Criterio de Evaluación</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" id="formCriterio">
                <input type="hidden" name="save_criterio" value="1">
                <input type="hidden" name="criterio_id" id="criterioId">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group" style="grid-column: span 2;"> 
                        <label>Categoría (Grupo visual)</label> 
                        <input type="text" name="categoria" id="critCategoria" required placeholder="Ej. Quizzes, Proyectos, Plataforma, Examen Final..."> 
                        <small style="color: #888; font-size: 0.8rem;">Las actividades con la misma categoría se agruparán en la misma tarjeta.</small>
                    </div>

                    <div class="form-group"> 
                        <label>Código Interno</label> 
                        <input type="text" name="codigo_examen" id="critCodigo" required placeholder="Ej. Q1, WRITING, FINAL" style="text-transform: uppercase;"> 
                    </div>

                    <div class="form-group"> 
                        <label>Puntos Máximos</label> 
                        <input type="number" name="puntos_maximos" id="critPuntos" step="0.01" required min="0.1" placeholder="Ej. 10"> 
                    </div>

                    <div class="form-group" style="grid-column: span 2;"> 
                        <label>Nombre del Examen/Actividad (Visible para el alumno)</label> 
                        <input type="text" name="nombre_examen" id="critNombre" required placeholder="Ej. Quiz 1, Examen TOEFL, Actividades Moodle..."> 
                    </div>

                    <div class="form-group">
                        <label>Icono</label>
                        <select name="icono" id="critIcono">
                            <option value="fa-star">★ Estrella (Defecto)</option>
                            <option value="fa-book-open">📖 Libro (Quizzes/Lecturas)</option>
                            <option value="fa-comments">💬 Comentarios (Orales)</option>
                            <option value="fa-file-signature">📝 Papel (Proyectos/Writing)</option>
                            <option value="fa-laptop-code">💻 Laptop (Plataforma)</option>
                            <option value="fa-hand-paper">✋ Mano (Participación)</option>
                            <option value="fa-certificate">🎓 Certificado (TOEFL/Final)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Color</label>
                        <select name="color" id="critColor">
                            <option value="var(--udg-light)">Azul UdeG</option>
                            <option value="#28a745">Verde (Éxito)</option>
                            <option value="#ffc107">Amarillo (Advertencia)</option>
                            <option value="#dc3545">Rojo (Peligro/Importante)</option>
                            <option value="#17a2b8">Cian (Info)</option>
                            <option value="#6f42c1">Morado (Especial)</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer" style="margin-top: 20px;">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save" id="btnSubmit"><i class="fas fa-save"></i> Guardar</button>
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
            document.getElementById('modalTitle').innerText = 'Agregar Criterio de Evaluación';
            document.getElementById('criterioId').value = '';
            document.getElementById('formCriterio').reset(); 
            document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-plus"></i> Agregar';
            modal.style.display = 'flex'; 
        }

        function editCriterio(crit) {
            document.getElementById('modalTitle').innerText = 'Editar Criterio de Evaluación';
            document.getElementById('criterioId').value = crit.criterio_id;
            
            document.getElementById('critCategoria').value = crit.categoria;
            document.getElementById('critCodigo').value = crit.codigo_examen;
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
    </script>
</body>
</html>