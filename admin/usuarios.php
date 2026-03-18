<?php
session_start();
require '../db.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

$mensaje = ''; 
$tipo_mensaje = '';

// ELIMINAR
if (isset($_GET['borrar'])) {
    $id = $_GET['borrar'];
    if ($id != $_SESSION['user_id']) {
        try {
            // Al borrar el usuario, la BD borra automáticamente al alumno por el "ON DELETE CASCADE"
            $pdo->prepare("DELETE FROM usuarios WHERE usuario_id = ?")->execute([$id]);
            $mensaje = "El usuario ha sido eliminado con éxito.";
            $tipo_mensaje = "success";
        } catch(PDOException $e) {
            $mensaje = "No se pudo eliminar el usuario. Es posible que tenga registros dependientes en otras tablas.";
            $tipo_mensaje = "error";
        }
    } else {
        $mensaje = "No puedes eliminar tu propio usuario.";
        $tipo_mensaje = "error";
    }
}

// Capturar mensajes que vengan por GET (ej. de guardar_usuario.php)
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'ok') {
        $mensaje = "¡Usuario guardado correctamente!";
        $tipo_mensaje = "success";
    } elseif ($_GET['msg'] === 'error') {
        $mensaje = "Hubo un error al intentar guardar el usuario.";
        $tipo_mensaje = "error";
    } elseif ($_GET['msg'] === 'dup') {
        $mensaje = "El correo o el código ya están registrados.";
        $tipo_mensaje = "error";
    }
}

// CONSULTAS Y FILTROS
$where = "1=1";
$params = [];

if (isset($_GET['q']) && !empty($_GET['q'])) {
    $where .= " AND (nombre LIKE :q1 OR apellido_paterno LIKE :q2 OR apellido_materno LIKE :q3 OR correo LIKE :q4 OR codigo LIKE :q5)";
    $termino = "%" . $_GET['q'] . "%";
    $params[':q1'] = $termino;
    $params[':q2'] = $termino;
    $params[':q3'] = $termino;
    $params[':q4'] = $termino;
    $params[':q5'] = $termino;
}
if (isset($_GET['rol']) && !empty($_GET['rol'])) {
    $where .= " AND rol = :rol";
    $params[':rol'] = $_GET['rol'];
}

// NUEVA CONSULTA: Unimos usuarios con alumnos para sacar la carrera
$sql = "SELECT u.*, a.carrera 
        FROM usuarios u 
        LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id 
        WHERE $where 
        ORDER BY u.usuario_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ESTADÍSTICAS
$total_users = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$total_students = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='ALUMNO'")->fetchColumn();
$total_teachers = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='PROFESOR'")->fetchColumn();
$total_admins = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='ADMIN'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gestión de Usuarios | Admin</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-users"></i> Gestión de Usuarios</h1>
            <p>Administra a los alumnos, profesores y personal del sistema.</p>
        </div>

        <?php if($mensaje): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: '<?php echo ($tipo_mensaje == "success") ? "¡Éxito!" : "Error"; ?>',
                        text: '<?php echo addslashes($mensaje); ?>',
                        icon: '<?php echo $tipo_mensaje; ?>',
                        confirmButtonColor: 'var(--udg-blue)'
                    });
                    
                    // Limpiar la URL para evitar que el mensaje reaparezca al recargar
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.delete('borrar');
                    currentUrl.searchParams.delete('msg');
                    window.history.replaceState({}, document.title, currentUrl.pathname + currentUrl.search);
                });
            </script>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"> <span class="stat-number"><?php echo $total_users; ?></span> <span class="stat-label">Total Usuarios</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_students; ?></span> <span class="stat-label">Alumnos</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_teachers; ?></span> <span class="stat-label">Profesores</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_admins; ?></span> <span class="stat-label">Admins</span> </div>
        </div>

        <form class="toolbar" method="GET" action="usuarios.php" style="margin-top: 20px;">
            <i class="fas fa-search" style="color:#aaa; align-self:center;"></i>
            <input type="text" name="q" class="search-input" placeholder="Buscar por nombre, correo o código..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
            <select name="rol" class="filter-select" onchange="this.form.submit()">
                <option value="">Todos los roles</option>
                <option value="ALUMNO" <?php if(isset($_GET['rol']) && $_GET['rol']=='ALUMNO') echo 'selected'; ?>>Alumnos</option>
                <option value="PROFESOR" <?php if(isset($_GET['rol']) && $_GET['rol']=='PROFESOR') echo 'selected'; ?>>Profesores</option>
                <option value="ADMIN" <?php if(isset($_GET['rol']) && $_GET['rol']=='ADMIN') echo 'selected'; ?>>Admins</option>
            </select>
            <button type="button" class="btn-save" onclick="openModal()" style="margin-left: auto;">
                <i class="fas fa-user-plus"></i> Nuevo Usuario
            </button>
        </form>

        <div class="card" style="padding: 0; overflow: hidden; margin-top: 20px;">
            <div class="table-wrapper" style="overflow-x:auto;">
                <table class="history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Usuario</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Código</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Teléfono</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Rol</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Estado</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td class="user-cell" style="padding: 15px;">
                                <h4 style="margin: 0; color: var(--udg-blue);"><?php 
                                    if (isset($u['apellido_paterno'])) {
                                        echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido_paterno'] . (isset($u['apellido_materno']) && $u['apellido_materno'] ? ' ' . $u['apellido_materno'] : ''));
                                    } else {
                                        echo htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']);
                                    }
                                ?></h4>
                                <span style="font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($u['correo']); ?></span>
                            </td>
                            <td style="padding: 15px;"><?php echo $u['codigo'] ? htmlspecialchars($u['codigo']) : '-'; ?></td>
                            
                            <td style="padding: 15px; color: #555;">
                                <?php echo $u['telefono'] ? htmlspecialchars($u['telefono']) : '<span style="color:#aaa; font-style:italic;">No registrado</span>'; ?>
                            </td>

                            <td style="padding: 15px;">
                                <?php 
                                    $ico = ($u['rol']=='ALUMNO') ? 'user-graduate' : (($u['rol']=='PROFESOR') ? 'chalkboard-teacher' : 'user-shield'); 
                                    echo "<i class='fas fa-$ico' style='color:#888;'></i> " . ucfirst(strtolower($u['rol']));
                                ?>
                            </td>
                            <td style="padding: 15px;">
                                <?php if($u['estatus'] == 'ACTIVO'): ?>
                                    <span class="tag-aprobado" style="background-color: #d4edda; color: #155724; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;">Activo</span>
                                <?php else: ?>
                                    <span class="tag-aprobado" style="background-color: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 15px; text-align: center;">
                                <button class="action-btn"
                                        data-id="<?php echo $u['usuario_id']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($u['nombre']); ?>"
                                        data-ap="<?php echo htmlspecialchars($u['apellido_paterno'] ?? ''); ?>"
                                        data-am="<?php echo htmlspecialchars($u['apellido_materno'] ?? ''); ?>"
                                        data-apellidos="<?php echo htmlspecialchars($u['apellidos'] ?? ''); ?>"
                                        data-correo="<?php echo htmlspecialchars($u['correo']); ?>"
                                        data-tel="<?php echo htmlspecialchars($u['telefono'] ?? ''); ?>"
                                        data-rol="<?php echo $u['rol']; ?>"
                                        data-estatus="<?php echo $u['estatus']; ?>"
                                        data-codigo="<?php echo htmlspecialchars($u['codigo'] ?? ''); ?>"
                                        data-carrera="<?php echo htmlspecialchars($u['carrera'] ?? ''); ?>"
                                        onclick="editUser(this)" 
                                        style="background: none; border: none; color: var(--udg-blue); cursor: pointer; font-size: 1.1rem; margin-right: 10px;" 
                                        title="Editar">
                                    <i class="fas fa-pen"></i>
                                </button>
                                
                                <?php if($u['usuario_id'] != $_SESSION['user_id']): ?>
                                    <?php 
                                        // Preparar nombre para alerta
                                        $nombre_completo_u = htmlspecialchars($u['nombre'] . ' ' . ($u['apellido_paterno'] ?? ''));
                                    ?>
                                    <a href="#" onclick="confirmarBorradoUsuario('usuarios.php?borrar=<?php echo $u['usuario_id']; ?>', '<?php echo addslashes($nombre_completo_u); ?>'); return false;" style="color: #dc3545; font-size: 1.1rem;" title="Eliminar">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if(count($usuarios) == 0): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #888;">No se encontraron usuarios.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <div id="userModal" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="padding: 0;">
            
            <div class="modal-header" style="padding: 20px 30px; margin: 0; border-bottom: 1px solid #eee;">
                <h2 id="modalTitle" style="margin: 0;">Nuevo Usuario</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form action="guardar_usuario.php" method="POST" style="margin: 0;">
                <input type="hidden" name="usuario_id" id="userId">
                
                <div style="padding: 20px 30px; max-height: 60vh; overflow-y: auto;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group" style="grid-column: span 2;"> 
                            <label>Nombre(s)</label> <input type="text" name="nombre" id="userName" required> 
                        </div>
                        <div class="form-group"> 
                            <label>Apellido Paterno</label> <input type="text" name="apellido_paterno" id="userLastnameP" required> 
                        </div>
                        <div class="form-group"> 
                            <label>Apellido Materno</label> <input type="text" name="apellido_materno" id="userLastnameM"> 
                        </div>
                        <div class="form-group" style="grid-column: span 2;"> 
                            <label>Correo Electrónico</label> <input type="email" name="correo" id="userEmail" required> 
                        </div>
                        <div class="form-group">
                            <label>Rol</label>
                            <select name="rol" id="userRole" onchange="toggleFields()">
                                <option value="ALUMNO">Alumno</option>
                                <option value="PROFESOR">Profesor</option>
                                <option value="ADMIN">Administrador</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Estado</label>
                            <select name="estatus" id="userStatus">
                                <option value="ACTIVO">Activo</option>
                                <option value="INACTIVO">Inactivo</option>
                            </select>
                        </div>
                        <div class="form-group code-field"> 
                            <label>Código</label> <input type="text" name="codigo" id="userCode"> 
                        </div>
                        <div class="form-group student-field"> 
                            <label>Carrera</label> <input type="text" name="carrera" id="userCareer" placeholder="Ej. LIME"> 
                        </div>
                        <div class="form-group"> 
                            <label>Teléfono</label> <input type="text" name="telefono" id="userPhone"> 
                        </div>
                        <div class="form-group" style="grid-column: span 2;"> 
                            <label>Contraseña</label> <input type="password" name="password" placeholder="(Dejar vacía para no cambiar)"> 
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="padding: 20px 30px; margin: 0; border-top: 1px solid #eee; background-color: #fcfcfc; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('userModal');
        const overlayMenu = document.getElementById('menuOverlay');

        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }

        function openModal() {
            document.getElementById('userId').value = '';
            document.getElementById('modalTitle').innerText = 'Nuevo Usuario';
            document.getElementById('userName').value = '';
            document.getElementById('userLastnameP').value = '';
            document.getElementById('userLastnameM').value = '';
            document.getElementById('userEmail').value = '';
            document.getElementById('userCode').value = '';
            document.getElementById('userCareer').value = '';
            document.getElementById('userPhone').value = '';
            document.getElementById('userRole').value = 'ALUMNO';
            document.getElementById('userStatus').value = 'ACTIVO';
            toggleFields();
            modal.style.display = 'flex';
        }

        // Nueva función editUser leyendo atributos data
        function editUser(btn) {
            document.getElementById('userId').value = btn.getAttribute('data-id');
            document.getElementById('modalTitle').innerText = 'Editar Usuario';
            document.getElementById('userName').value = btn.getAttribute('data-nombre');
            
            const ap = btn.getAttribute('data-ap');
            const am = btn.getAttribute('data-am');
            const apellidos = btn.getAttribute('data-apellidos');
            
            if (ap || am) {
                document.getElementById('userLastnameP').value = ap;
                document.getElementById('userLastnameM').value = am;
            } else if (apellidos) {
                const partes = apellidos.split(' ');
                document.getElementById('userLastnameP').value = partes[0];
                document.getElementById('userLastnameM').value = partes.slice(1).join(' ') || '';
            } else {
                 document.getElementById('userLastnameP').value = '';
                 document.getElementById('userLastnameM').value = '';
            }
            
            document.getElementById('userEmail').value = btn.getAttribute('data-correo');
            document.getElementById('userPhone').value = btn.getAttribute('data-tel');
            document.getElementById('userRole').value = btn.getAttribute('data-rol');
            document.getElementById('userStatus').value = btn.getAttribute('data-estatus');
            document.getElementById('userCode').value = btn.getAttribute('data-codigo');
            document.getElementById('userCareer').value = btn.getAttribute('data-carrera');
            toggleFields();
            modal.style.display = 'flex';
        }

        function closeModal() { modal.style.display = 'none'; }

        function toggleFields() {
            const role = document.getElementById('userRole').value;
            const studentFields = document.querySelectorAll('.student-field');
            studentFields.forEach(f => f.style.display = (role === 'ALUMNO') ? 'block' : 'none');
            
            const codeGroup = document.querySelector('.code-field');
            if (codeGroup) {
                codeGroup.style.display = (role === 'ADMIN') ? 'none' : 'block';
            }
        }

        // SWEETALERT PARA BORRAR USUARIO
        function confirmarBorradoUsuario(url, nombre) {
            Swal.fire({
                title: '¿Eliminar Usuario?',
                html: `Estás a punto de borrar a <b>${nombre}</b> del sistema.<br><br><small style="color:#dc3545;">⚠️ Esta acción es irreversible y eliminará toda su información.</small>`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash-alt"></i> Sí, eliminar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        window.onclick = function(e) { 
            if(e.target == modal) closeModal(); 
            if(e.target == overlayMenu) toggleMobileMenu(); 
        };
    </script>

</body>
</html>
