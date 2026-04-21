<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

$mensaje = ''; 
$tipo_mensaje = '';

// ===============================================
// ELIMINACIÓN DE USUARIO
// ===============================================
if (isset($_GET['borrar'])) {
    $id = $_GET['borrar'];
    $root_admin_id = 1; 

    if ($id == $root_admin_id) {
        $mensaje = "Acceso denegado: No puedes eliminar al Administrador Principal."; $tipo_mensaje = "error";
    } elseif ($id == $_SESSION['user_id']) {
        $mensaje = "No puedes eliminar tu propia cuenta."; $tipo_mensaje = "error";
    } else {
        try {
            $pdo->prepare("DELETE FROM usuarios WHERE usuario_id = ?")->execute([$id]);
            $mensaje = "El usuario ha sido eliminado con éxito."; $tipo_mensaje = "success";
        } catch(PDOException $e) {
            $mensaje = "No se pudo eliminar el usuario. Verifica registros dependientes."; $tipo_mensaje = "error";
        }
    }
}

// MENSAJES DE RESPUESTA DE GUARDADO
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'ok') { $mensaje = "¡Usuario guardado correctamente!"; $tipo_mensaje = "success"; } 
    elseif ($_GET['msg'] === 'error') { $mensaje = "Hubo un error al intentar guardar el usuario."; $tipo_mensaje = "error"; } 
    elseif ($_GET['msg'] === 'dup') { $mensaje = "El correo o el código ya están registrados."; $tipo_mensaje = "error"; }
}

// CARGAMOS TODOS LOS USUARIOS (El buscador JS filtrará en tiempo real sin recargar)
$sql = "SELECT u.*, a.carrera FROM usuarios u LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id ORDER BY u.usuario_id DESC";
$stmt = $pdo->prepare($sql); $stmt->execute(); $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        
        <div class="page-title-center mb-30">
            <h1><i class="fas fa-users"></i> Gestión de Usuarios</h1>
            <p>Administra a los alumnos, profesores y personal del sistema.</p>
        </div>

        <?php if($mensaje): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({ title: '<?php echo ($tipo_mensaje == "success") ? "¡Éxito!" : "Error"; ?>', text: '<?php echo addslashes($mensaje); ?>', icon: '<?php echo $tipo_mensaje; ?>', confirmButtonColor: 'var(--udg-blue)' });
                    const currentUrl = new URL(window.location.href); currentUrl.searchParams.delete('borrar'); currentUrl.searchParams.delete('msg'); window.history.replaceState({}, document.title, currentUrl.pathname + currentUrl.search);
                });
            </script>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card"> <span class="stat-number"><?php echo $total_users; ?></span> <span class="stat-label">Total Usuarios</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_students; ?></span> <span class="stat-label">Alumnos</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_teachers; ?></span> <span class="stat-label">Profesores</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_admins; ?></span> <span class="stat-label">Admins</span> </div>
        </div>

        <form class="toolbar mt-20" onsubmit="event.preventDefault();">
            <i class="fas fa-search icon-muted" style="align-self:center;"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="Buscar por nombre, correo o código...">
            <select id="rolSelect" class="filter-select">
                <option value="">Todos los roles</option>
                <option value="ALUMNO">Alumnos</option>
                <option value="PROFESOR">Profesores</option>
                <option value="ADMIN">Admins</option>
            </select>
            <button type="button" class="btn-save" onclick="openModal()" style="margin-left: auto;">
                <i class="fas fa-user-plus"></i> Nuevo Usuario
            </button>
        </form>

        <div class="card mt-20" style="padding: 0; overflow: hidden;">
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Código</th>
                            <th>Teléfono</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th style="text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php foreach ($usuarios as $u): ?>
                        <tr class="group-row" 
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
                            onclick="editUser(this)" title="Haz clic para editar">
                            
                            <td class="user-cell">
                                <h4 class="user-name"><?php 
                                    if (isset($u['apellido_paterno'])) { echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido_paterno'] . (isset($u['apellido_materno']) && $u['apellido_materno'] ? ' ' . $u['apellido_materno'] : '')); } 
                                    else { echo htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']); }
                                ?></h4>
                                <span class="user-email"><?php echo htmlspecialchars($u['correo']); ?></span>
                            </td>
                            <td><?php echo $u['codigo'] ? htmlspecialchars($u['codigo']) : '-'; ?></td>
                            
                            <td>
                                <?php echo $u['telefono'] ? htmlspecialchars($u['telefono']) : '<span class="text-muted-italic">No registrado</span>'; ?>
                            </td>

                            <td>
                                <?php 
                                    $ico = ($u['rol']=='ALUMNO') ? 'user-graduate' : (($u['rol']=='PROFESOR') ? 'chalkboard-teacher' : 'user-shield'); 
                                    echo "<i class='fas fa-$ico icon-muted'></i> " . ucfirst(strtolower($u['rol']));
                                ?>
                            </td>
                            <td>
                                <?php if($u['estatus'] == 'ACTIVO'): ?>
                                    <span class="tag-aprobado">Activo</span>
                                <?php else: ?>
                                    <span class="tag-inactivo">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if($u['usuario_id'] != $_SESSION['user_id'] && $u['usuario_id'] != 1): ?>
                                    <?php $nombre_completo_u = htmlspecialchars($u['nombre'] . ' ' . ($u['apellido_paterno'] ?? '')); ?>
                                    <a href="#" class="action-btn delete" onclick="event.stopPropagation(); confirmarBorradoUsuario('usuarios.php?borrar=<?php echo $u['usuario_id']; ?>', '<?php echo addslashes($nombre_completo_u); ?>'); return false;" title="Eliminar">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <tr id="noResultsRow" style="display: none;">
                            <td colspan="6" style="text-align:center; padding: 40px; color: #888; font-size: 1.1rem;">
                                <i class="fas fa-search" style="font-size: 2.5rem; margin-bottom: 15px; display: block; color: #eee;"></i>
                                No se encontraron usuarios con esa búsqueda.
                            </td>
                        </tr>
                        
                        <?php if(count($usuarios) == 0): ?>
                            <tr><td colspan="6" class="empty-table-msg">No hay usuarios registrados en el sistema.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <div id="userModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle" style="margin: 0;">Nuevo Usuario</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form action="guardar_usuario.php" method="POST" style="margin: 0;">
                <input type="hidden" name="usuario_id" id="userId">
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group full-width"> <label>Nombre(s)</label> <input type="text" name="nombre" id="userName" required> </div>
                        <div class="form-group"> <label>Apellido Paterno</label> <input type="text" name="apellido_paterno" id="userLastnameP" required> </div>
                        <div class="form-group"> <label>Apellido Materno</label> <input type="text" name="apellido_materno" id="userLastnameM"> </div>
                        <div class="form-group full-width"> <label>Correo Electrónico</label> <input type="email" name="correo" id="userEmail" required> </div>
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
                        <div class="form-group code-field"> <label>Código</label> <input type="text" name="codigo" id="userCode"> </div>
                        <div class="form-group student-field"> <label>Carrera</label> <input type="text" name="carrera" id="userCareer" placeholder="Ej. LIME"> </div>
                        <div class="form-group"> <label>Teléfono</label> <input type="text" name="telefono" id="userPhone"> </div>
                        <div class="form-group full-width"> <label>Contraseña</label> <input type="password" name="password" placeholder="(Dejar vacía para no cambiar)"> </div>
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
        const modal = document.getElementById('userModal');
        const overlayMenu = document.getElementById('menuOverlay');

        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }

        function openModal() {
            document.getElementById('userId').value = ''; document.getElementById('modalTitle').innerText = 'Nuevo Usuario'; document.getElementById('userName').value = ''; document.getElementById('userLastnameP').value = ''; document.getElementById('userLastnameM').value = ''; document.getElementById('userEmail').value = ''; document.getElementById('userCode').value = ''; document.getElementById('userCareer').value = ''; document.getElementById('userPhone').value = ''; document.getElementById('userRole').value = 'ALUMNO'; document.getElementById('userStatus').value = 'ACTIVO'; toggleFields(); modal.style.display = 'flex';
        }

        function editUser(btn) {
            document.getElementById('userId').value = btn.getAttribute('data-id'); document.getElementById('modalTitle').innerText = 'Editar Usuario'; document.getElementById('userName').value = btn.getAttribute('data-nombre');
            const ap = btn.getAttribute('data-ap'); const am = btn.getAttribute('data-am'); const apellidos = btn.getAttribute('data-apellidos');
            if (ap || am) { document.getElementById('userLastnameP').value = ap; document.getElementById('userLastnameM').value = am; } else if (apellidos) { const partes = apellidos.split(' '); document.getElementById('userLastnameP').value = partes[0]; document.getElementById('userLastnameM').value = partes.slice(1).join(' ') || ''; } else { document.getElementById('userLastnameP').value = ''; document.getElementById('userLastnameM').value = ''; }
            document.getElementById('userEmail').value = btn.getAttribute('data-correo'); document.getElementById('userPhone').value = btn.getAttribute('data-tel'); document.getElementById('userRole').value = btn.getAttribute('data-rol'); document.getElementById('userStatus').value = btn.getAttribute('data-estatus'); document.getElementById('userCode').value = btn.getAttribute('data-codigo'); document.getElementById('userCareer').value = btn.getAttribute('data-carrera'); toggleFields(); modal.style.display = 'flex';
        }

        function closeModal() { modal.style.display = 'none'; }
        
        function toggleFields() { 
            const role = document.getElementById('userRole').value; 
            const studentFields = document.querySelectorAll('.student-field'); 
            studentFields.forEach(f => f.style.display = (role === 'ALUMNO') ? 'block' : 'none'); 
            const codeGroup = document.querySelector('.code-field'); 
            if (codeGroup) { codeGroup.style.display = (role === 'ADMIN') ? 'none' : 'block'; } 
        }

        function confirmarBorradoUsuario(url, nombre) { Swal.fire({ title: '¿Eliminar Usuario?', html: `Estás a punto de borrar a <b>${nombre}</b> del sistema.<br><br><small style="color:#dc3545;">⚠️ Esta acción es irreversible y eliminará toda su información.</small>`, icon: 'error', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', confirmButtonText: '<i class="fas fa-trash-alt"></i> Sí, eliminar', cancelButtonText: 'Cancelar', reverseButtons: true }).then((result) => { if (result.isConfirmed) { window.location.href = url; } }); }

        window.onclick = function(e) { if(e.target == modal) closeModal(); if(e.target == overlayMenu) toggleMobileMenu(); };

        // ==========================================
        // LÓGICA DE BUSCADOR JS EN TIEMPO REAL
        // ==========================================
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const rolSelect = document.getElementById('rolSelect');
            const rows = document.querySelectorAll('.group-row');
            const noResultsRow = document.getElementById('noResultsRow');

            function filterTable() {
                const term = searchInput.value.toLowerCase().trim();
                const role = rolSelect.value.toUpperCase();
                let hasVisibleRows = false;

                rows.forEach(row => {
                    const rowText = row.innerText.toLowerCase();
                    const rowRole = row.getAttribute('data-rol').toUpperCase();
                    
                    const matchesText = rowText.includes(term);
                    const matchesRole = role === '' || rowRole === role;

                    if (matchesText && matchesRole) {
                        row.style.display = '';
                        hasVisibleRows = true;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if(noResultsRow) {
                    noResultsRow.style.display = hasVisibleRows || rows.length === 0 ? 'none' : '';
                }
            }

            if (searchInput) searchInput.addEventListener('input', filterTable);
            if (rolSelect) rolSelect.addEventListener('change', filterTable);
        });
    </script>
</body>
</html>
