<?php
session_start();
require '../db.php';

// Seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Eliminar Profesor
if (isset($_GET['borrar'])) {
    $id_borrar = $_GET['borrar'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
    $stmt->execute([$id_borrar]);
    header("Location: profesores.php");
    exit;
}

// CONSULTA SOLO PROFESORES
$query = "SELECT * FROM users WHERE rol = 'profesor' ORDER BY apellidos ASC";
$stmt = $pdo->query($query);
$profesores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Profesores | Admin E-PALE</title>
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
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
            <li><a href="profesores.php" class="active"><i class="fas fa-chalkboard-teacher"></i> Profesores</a></li>
            <li><a href="estudiantes.php"><i class="fas fa-user-graduate"></i> Alumnos</a></li>
            <li><a href="materias.php"><i class="fas fa-book"></i> Materias</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="header-title">
            <h1>Gestión de Profesores</h1>
            <a href="crear_usuario.php?tipo=profesor" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nuevo Profesor
            </a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Correo Electrónico</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($profesores) > 0): ?>
                        <?php foreach ($profesores as $p): ?>
                        <tr>
                            <td>#<?php echo $p['id_user']; ?></td>
                            <td><?php echo $p['nombre'] . " " . $p['apellidos']; ?></td>
                            <td><?php echo $p['email']; ?></td>
                            <td>
                                <a href="editar_usuario.php?id=<?php echo $p['id_user']; ?>" class="btn btn-edit"><i class="fas fa-edit"></i></a>
                                <a href="profesores.php?borrar=<?php echo $p['id_user']; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('¿Eliminar a este profesor?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;">No hay profesores registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>