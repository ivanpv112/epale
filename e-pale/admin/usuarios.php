<?php
session_start();
require '../db.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

// ELIMINAR
if (isset($_GET['borrar'])) {
    $id = $_GET['borrar'];
    if ($id != $_SESSION['user_id']) {
        // Al borrar el usuario, la BD borra automáticamente al alumno por el "ON DELETE CASCADE"
        $pdo->prepare("DELETE FROM usuarios WHERE usuario_id = ?")->execute([$id]);
        header("Location: usuarios.php"); exit;
    }
}

// CONSULTAS Y FILTROS
$where = "1=1";
$params = [];

if (isset($_GET['q']) && !empty($_GET['q'])) {
    $where .= " AND (nombre LIKE :q OR apellidos LIKE :q OR correo LIKE :q OR codigo LIKE :q)";
    $params[':q'] = "%".$_GET['q']."%";
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
    <title>Gestión de Usuarios | Admin</title>
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand">
            <img src="../img/logo-pale.png" alt="Logo" style="height: 35px;"> e-PALE
        </div>
        <div class="navbar-menu">
            <a href="usuarios.php" class="active">USUARIOS</a>
            <a href="materias.php">MATERIAS</a>
            <a href="#">REPORTES</a>
        </div>
        <div class="user-profile">
            <i class="fas fa-user-circle fa-lg"></i> PERFIL <i class="fas fa-sign-out-alt" onclick="window.location.href='../logout.php'" title="Salir" style="margin-left:10px; cursor:pointer;"></i>
        </div>
    </nav>

    <div class="main-container">
        
        <div class="stats-grid">
            <div class="stat-card"> <span class="stat-number"><?php echo $total_users; ?></span> <span class="stat-label">Total Usuarios</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_students; ?></span> <span class="stat-label">Alumnos</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_teachers; ?></span> <span class="stat-label">Profesores</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_admins; ?></span> <span class="stat-label">Admins</span> </div>
        </div>

        <form class="toolbar" method="GET" action="usuarios.php">
            <i class="fas fa-search" style="color:#aaa; align-self:center;"></i>
            <input type="text" name="q" class="search-input" placeholder="Buscar..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
            <select name="rol" class="filter-select" onchange="this.form.submit()">
                <option value="">Todos los roles</option>
                <option value="ALUMNO" <?php if(isset($_GET['rol']) && $_GET['rol']=='ALUMNO') echo 'selected'; ?>>Alumnos</option>
                <option value="PROFESOR" <?php if(isset($_GET['rol']) && $_GET['rol']=='PROFESOR') echo 'selected'; ?>>Profesores</option>
                <option value="ADMIN" <?php if(isset($_GET['rol']) && $_GET['rol']=='ADMIN') echo 'selected'; ?>>Admins</option>
            </select>
            <button type="button" class="btn-primary" onclick="openModal()">
                <i class="fas fa-user-plus"></i> Nuevo Usuario
            </button>
        </form>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Código</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td class="user-cell">
                            <h4><?php echo $u['nombre'] . ' ' . $u['apellidos']; ?></h4>
                            <span><?php echo $u['correo']; ?></span>
                        </td>
                        <td><?php echo $u['codigo'] ? $u['codigo'] : '-'; ?></td>
                        <td>
                            <?php 
                                $ico = ($u['rol']=='ALUMNO') ? 'user-graduate' : (($u['rol']=='PROFESOR') ? 'chalkboard-teacher' : 'user-shield'); 
                                echo "<i class='fas fa-$ico'></i> " . ucfirst(strtolower($u['rol']));
                            ?>
                        </td>
                        <td>
                            <span class="status-badge <?php echo ($u['estatus']=='ACTIVO')?'status-active':'status-inactive'; ?>">
                                <?php echo $u['estatus']; ?>
                            </span>
                        </td>
                        <td>
                            <button class="action-btn" onclick='editUser(<?php echo json_encode($u); ?>)'>
                                <i class="fas fa-pen"></i>
                            </button>
                            
                            <?php if($u['usuario_id'] != $_SESSION['user_id']): ?>
                                <a href="usuarios.php?borrar=<?php echo $u['usuario_id']; ?>" class="action-btn delete" onclick="return confirm('¿Borrar?');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="userModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nuevo Usuario</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form action="guardar_usuario.php" method="POST">
                <input type="hidden" name="usuario_id" id="userId">

                <div class="form-grid">
                    <div class="form-group"> <label>Nombre(s)</label> <input type="text" name="nombre" id="userName" required> </div>
                    <div class="form-group"> <label>Apellidos</label> <input type="text" name="apellidos" id="userLastname" required> </div>
                    
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

                    <div class="form-group student-field"> <label>Código</label> <input type="text" name="codigo" id="userCode"> </div>
                    <div class="form-group student-field"> <label>Carrera</label> <input type="text" name="carrera" id="userCareer" placeholder="Ej. LIME"> </div>
                    
                    <div class="form-group"> <label>Teléfono</label> <input type="text" name="telefono" id="userPhone"> </div>
                    <div class="form-group full-width"> <label>Contraseña</label> <input type="password" name="password" placeholder="(Dejar vacía para no cambiar)"> </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('userModal');
        
        function openModal() {
            document.getElementById('userId').value = '';
            document.getElementById('modalTitle').innerText = 'Nuevo Usuario';
            document.getElementById('userName').value = '';
            document.getElementById('userLastname').value = '';
            document.getElementById('userEmail').value = '';
            document.getElementById('userCode').value = '';
            document.getElementById('userCareer').value = '';
            document.getElementById('userPhone').value = '';
            document.getElementById('userRole').value = 'ALUMNO';
            toggleFields();
            modal.style.display = 'flex';
        }

        function editUser(user) {
            document.getElementById('userId').value = user.usuario_id; // OJO: usuario_id
            document.getElementById('modalTitle').innerText = 'Editar Usuario';
            document.getElementById('userName').value = user.nombre;
            document.getElementById('userLastname').value = user.apellidos;
            document.getElementById('userEmail').value = user.correo; // OJO: correo
            document.getElementById('userPhone').value = user.telefono;
            document.getElementById('userRole').value = user.rol;
            document.getElementById('userStatus').value = user.estatus;
            
            // Datos específicos
            document.getElementById('userCode').value = user.codigo || '';
            document.getElementById('userCareer').value = user.carrera || '';

            toggleFields();
            modal.style.display = 'flex';
        }

        function closeModal() { modal.style.display = 'none'; }
        
        function toggleFields() {
            const role = document.getElementById('userRole').value;
            const fields = document.querySelectorAll('.student-field');
            fields.forEach(f => f.style.display = (role === 'ALUMNO') ? 'block' : 'none');
        }

        window.onclick = function(e) { if(e.target == modal) closeModal(); }
    </script>
</body>
</html>