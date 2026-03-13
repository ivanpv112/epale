<?php
session_start();
require '../db.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

$mensaje = ''; 
$tipo_mensaje = '';

// =======================================================
// PROCESAR FORMULARIO DE GUARDAR/EDITAR MATERIA
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_materia') {
    $materia_id = $_POST['materia_id'] ?? null;
    $nombre = trim($_POST['nombre']);
    $clave = strtoupper(trim($_POST['clave']));
    $nivel = intval($_POST['nivel']);

    try {
        // Validación 1: Campos vacíos
        if(empty($nombre) || empty($clave) || empty($nivel)) {
            throw new Exception("Todos los campos son obligatorios.");
        }

        // Validación 2: Clave duplicada
        if ($materia_id) {
            $check = $pdo->prepare("SELECT COUNT(*) FROM materias WHERE clave = ? AND materia_id != ?");
            $check->execute([$clave, $materia_id]);
            if ($check->fetchColumn() > 0) throw new Exception("Error: La clave '$clave' ya está asignada a otra materia.");
            
            $pdo->prepare("UPDATE materias SET clave = ?, nombre = ?, nivel = ? WHERE materia_id = ?")
                ->execute([$clave, $nombre, $nivel, $materia_id]);
            $mensaje = "Materia actualizada correctamente.";
            $tipo_mensaje = "success";
        } else {
            $check = $pdo->prepare("SELECT COUNT(*) FROM materias WHERE clave = ?");
            $check->execute([$clave]);
            if ($check->fetchColumn() > 0) throw new Exception("Error: Ya existe una materia con la clave '$clave'.");
            
            $pdo->prepare("INSERT INTO materias (clave, nombre, nivel) VALUES (?, ?, ?)")
                ->execute([$clave, $nombre, $nivel]);
            $mensaje = "Materia creada exitosamente.";
            $tipo_mensaje = "success";
        }
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// =======================================================
// ELIMINAR MATERIA
// =======================================================
if (isset($_GET['borrar'])) {
    $id = $_GET['borrar'];
    try {
        $pdo->prepare("DELETE FROM materias WHERE materia_id = ?")->execute([$id]);
        $mensaje = "Materia eliminada correctamente.";
        $tipo_mensaje = "success";
    } catch(PDOException $e) {
        $mensaje = "No se puede eliminar la materia porque ya tiene grupos o calificaciones asignadas.";
        $tipo_mensaje = "error";
    }
}

// CONSULTAS Y FILTROS
$where = "1=1";
$params = [];

if (isset($_GET['q']) && !empty($_GET['q'])) {
    $where .= " AND (nombre LIKE :q OR clave LIKE :q)";
    $params[':q'] = "%".$_GET['q']."%";
}

$idiomas = ['Inglés','Francés','Italiano','Español','B-learning'];
$idiomas_present = [];
foreach ($idiomas as $idioma) {
    $count = $pdo->prepare("SELECT COUNT(*) FROM materias WHERE nombre LIKE ?");
    $count->execute(["%".$idioma."%"]);
    if ($count->fetchColumn() > 0) {
        $idiomas_present[] = $idioma;
    }
}

if (isset($_GET['idioma']) && !empty($_GET['idioma'])) {
    $where .= " AND nombre LIKE :idioma";
    $params[':idioma'] = "%".$_GET['idioma']."%";
}

$sql = "SELECT * FROM materias WHERE $where ORDER BY nombre ASC, nivel ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_materias = $pdo->query("SELECT COUNT(*) FROM materias")->fetchColumn();
$total_grupos = $pdo->query("SELECT COUNT(*) FROM grupos")->fetchColumn();
$total_idiomas = count($idiomas_present);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gestión de Materias | Admin</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-book"></i> Materias y Criterios</h1>
            <p>Administra el catálogo de idiomas y configura cómo se evalúa cada nivel.</p>
        </div>

        <?php if($mensaje): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: '<?php echo ($tipo_mensaje == "success") ? "¡Éxito!" : "Error"; ?>',
                        text: '<?php echo htmlspecialchars($mensaje); ?>',
                        icon: '<?php echo $tipo_mensaje; ?>',
                        confirmButtonColor: 'var(--udg-blue)'
                    });
                    if(window.location.search.includes('borrar=')){
                        window.history.replaceState({}, document.title, window.location.pathname);
                    }
                });
            </script>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"> <span class="stat-number"><?php echo $total_materias; ?></span> <span class="stat-label">Total Materias</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_grupos; ?></span> <span class="stat-label">Grupos Activos</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_idiomas; ?></span> <span class="stat-label">Idiomas</span> </div>
        </div>

        <form class="toolbar" method="GET" action="materias.php" style="margin-top: 20px;">
            <i class="fas fa-search" style="color:#aaa; align-self:center;"></i>
            <input type="text" name="q" class="search-input" placeholder="Buscar por nombre o clave..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
            <select name="idioma" class="filter-select" onchange="this.form.submit()">
                <option value="">Todos los idiomas</option>
                <?php foreach ($idiomas_present as $idioma): ?>
                    <option value="<?php echo htmlspecialchars($idioma); ?>" <?php if(isset($_GET['idioma']) && $_GET['idioma']==$idioma) echo 'selected'; ?>><?php echo htmlspecialchars($idioma); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn-save" onclick="openModal()" style="margin-left: auto;">
                <i class="fas fa-book-medical"></i> Nueva Materia
            </button>
        </form>

        <div class="card" style="padding: 0; overflow: hidden; margin-top: 20px;">
            <div class="table-wrapper" style="overflow-x:auto;">
                <table class="history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Clave</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Nombre y Nivel</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Grupos</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Evaluación</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($materias) > 0): ?>
                            <?php foreach ($materias as $m): 
                                $count_grupos = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE materia_id = ?");
                                $count_grupos->execute([$m['materia_id']]);
                                $num_grupos = $count_grupos->fetchColumn();

                                $stmt_crit = $pdo->prepare("SELECT SUM(puntos_maximos) as total_puntos, COUNT(*) as qty FROM criterios_evaluacion WHERE materia_id = ?");
                                $stmt_crit->execute([$m['materia_id']]);
                                $criterios = $stmt_crit->fetch(PDO::FETCH_ASSOC);
                                $total_puntos = $criterios['total_puntos'] ? floatval($criterios['total_puntos']) : 0;
                                $num_criterios = $criterios['qty'];
                            ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 15px;">
                                    <span style="font-weight: bold; color: #666; font-family: monospace; font-size: 1.1rem;"><?php echo htmlspecialchars($m['clave']); ?></span>
                                </td>
                                <td style="padding: 15px;">
                                    <h4 style="margin: 0; color: var(--udg-blue); font-size: 1.1rem;"><?php echo htmlspecialchars($m['nombre']); ?></h4>
                                    <span class="tag-aprobado" style="background-color: #e7f3ff; color: var(--udg-blue); padding: 2px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; display: inline-block; margin-top: 5px;">Nivel <?php echo $m['nivel']; ?></span>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <span style="background: #f1f3f5; padding: 5px 12px; border-radius: 20px; font-weight: bold; color: #555;">
                                        <i class="fas fa-users" style="color: #888;"></i> <?php echo $num_grupos; ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <?php if($num_criterios > 0): ?>
                                        <div style="font-size: 0.85rem; color: #28a745; font-weight: bold;"><i class="fas fa-check-circle"></i> Configurado</div>
                                        <div style="font-size: 0.8rem; color: #666;"><?php echo $total_puntos; ?> pts / <?php echo $num_criterios; ?> rubros</div>
                                    <?php else: ?>
                                        <span style="background: #f8d7da; color: #dc3545; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;"><i class="fas fa-exclamation-triangle"></i> Sin configurar</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <a href="criterios_materia.php?id=<?php echo $m['materia_id']; ?>" class="action-btn" style="color: #17a2b8; font-size: 1.2rem; margin-right: 12px;" title="Configurar Criterios de Evaluación">
                                        <i class="fas fa-cogs"></i>
                                    </a>
                                    
                                    <button class="action-btn" 
                                            data-id="<?php echo $m['materia_id']; ?>"
                                            data-clave="<?php echo htmlspecialchars($m['clave']); ?>"
                                            data-nombre="<?php echo htmlspecialchars($m['nombre']); ?>"
                                            data-nivel="<?php echo $m['nivel']; ?>"
                                            onclick="editMateria(this)" 
                                            style="background: none; border: none; color: var(--udg-blue); cursor: pointer; font-size: 1.1rem; margin-right: 10px;" 
                                            title="Editar Info Básica">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    
                                    <button class="action-btn delete" onclick="confirmDelete(<?php echo $m['materia_id']; ?>)" style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 1.1rem;" title="Eliminar Materia">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 50px; color: var(--text-light);">
                                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; display: block; color: #ddd;"></i>
                                    <h3 style="color: #888;">No hay materias registradas</h3>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <div id="materiaModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nueva Materia</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form action="materias.php" method="POST">
                <input type="hidden" name="action" value="save_materia">
                <input type="hidden" name="materia_id" id="materiaId">
                <div class="modal-body">
                    <div class="form-grid-materias">
                        <div class="form-group full-width"> 
                            <label>Nombre de la Materia</label> 
                            <input type="text" name="nombre" id="materiaNombre" required placeholder="Ej. Inglés, Francés, etc."> 
                        </div>
                        <div class="form-group"> 
                            <label>Clave</label> 
                            <input type="text" name="clave" id="materiaclave" required placeholder="Ej. ING101"> 
                        </div>
                        <div class="form-group"> 
                            <label>Nivel</label> 
                            <input type="number" name="nivel" id="materiaLevel" required min="1" max="10" placeholder="Ej. 1"> 
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }

        const modal = document.getElementById('materiaModal');
        const overlayMenu = document.getElementById('menuOverlay');
        
        function openModal() {
            document.getElementById('materiaId').value = '';
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-book-medical"></i> Nueva Materia';
            document.getElementById('materiaclave').value = '';
            document.getElementById('materiaNombre').value = '';
            document.getElementById('materiaLevel').value = '';
            modal.style.display = 'flex';
        }
        
        // Función limpia que atrapa los datos uno por uno
        function editMateria(btn) {
            document.getElementById('materiaId').value = btn.getAttribute('data-id');
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-pen"></i> Editar Materia';
            document.getElementById('materiaclave').value = btn.getAttribute('data-clave');
            document.getElementById('materiaNombre').value = btn.getAttribute('data-nombre');
            document.getElementById('materiaLevel').value = btn.getAttribute('data-nivel');
            modal.style.display = 'flex';
        }
        
        function closeModal() { 
            modal.style.display = 'none'; 
        }

        function confirmDelete(id) {
            Swal.fire({
                title: '¿Eliminar materia?',
                text: "Esta acción no se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `materias.php?borrar=${id}`;
                }
            })
        }
        
        window.onclick = function(e) { 
            if(e.target == modal) closeModal(); 
            if(e.target == overlayMenu) toggleMobileMenu();
        };
    </script>

</body>
</html>
