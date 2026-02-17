<?php
session_start();
require '../db.php';

// 1. SEGURIDAD: Solo admin entra aquí
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// 2. LÓGICA DE ELIMINAR (DELETE)
if (isset($_GET['borrar'])) {
    $id_borrar = $_GET['borrar'];
    // Evitar que el admin se borre a sí mismo
    if ($id_borrar != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
        $stmt->execute([$id_borrar]);
        header("Location: usuarios.php"); // Recargar para limpiar la URL
        exit;
    }
}

// 3. CONSULTA PARA OBTENER TODOS LOS USUARIOS (READ)
$query = "SELECT * FROM users ORDER BY rol ASC, apellidos ASC";
$stmt = $pdo->query($query);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Usuarios | E-PALE</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../img/logo-pale.png" alt="E-PALE" style="width: 40px; vertical-align: middle;">
            <span style="font-weight: bold; font-size: 1.2rem;">Panel Admin</span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="profesores.php"><i class="fas fa-chalkboard-teacher"></i> Profesores</a></li>
            <li><a href="estudiantes.php"><i class="fas fa-user-graduate"></i> Alumnos</a></li>
            <li><a href="materias.php"><i class="fas fa-book"></i> Materias</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
        </ul>
    </aside>

    <main class="main-content">
        
        <div class="header-title">
            <h1>Gestión de Usuarios</h1>
            <a href="crear_usuario.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nuevo Usuario
            </a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Rol</th>
                        <th>Nombre Completo</th>
                        <th>Correo / Usuario</th>
                        <th>Código (Alumno)</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td>#<?php echo $u['id_user']; ?></td>
                        <td>
                            <span class="badge badge-<?php echo $u['rol']; ?>">
                                <?php echo ucfirst($u['rol']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo $u['nombre'] . " " . $u['apellidos']; ?>
                        </td>
                        <td><?php echo $u['email']; ?></td>
                        <td>
                            <?php echo $u['codigo'] ? $u['codigo'] : '<span style="color:#ccc;">-</span>'; ?>
                        </td>
                        <td>
                            <a href="editar_usuario.php?id=<?php echo $u['id_user']; ?>" class="btn btn-edit" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            
                            <?php if($u['id_user'] != $_SESSION['user_id']): ?>
                                <a href="usuarios.php?borrar=<?php echo $u['id_user']; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('¿Estás seguro de eliminar a este usuario? Se borrarán sus calificaciones.');"
                                   title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>

</body>
</html>