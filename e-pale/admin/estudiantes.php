<?php
session_start();
require '../db.php';

// Seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Eliminar Alumno
if (isset($_GET['borrar'])) {
    $id_borrar = $_GET['borrar'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
    $stmt->execute([$id_borrar]);
    header("Location: estudiantes.php");
    exit;
}

// CONSULTA SOLO ESTUDIANTES
$query = "SELECT * FROM users WHERE rol = 'estudiante' ORDER BY apellidos ASC";
$stmt = $pdo->query($query);
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alumnos | Admin E-PALE</title>
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
            <li><a href="profesores.php"><i class="fas fa-chalkboard-teacher"></i> Profesores</a></li>
            <li><a href="estudiantes.php" class="active"><i class="fas fa-user-graduate"></i> Alumnos</a></li>
            <li><a href="materias.php"><i class="fas fa-book"></i> Materias</a></li>
            <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="header-title">
            <h1>Gestión de Alumnos</h1>
            <a href="crear_usuario.php?tipo=estudiante" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nuevo Alumno
            </a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre Completo</th>
                        <th>Correo Electrónico</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($estudiantes) > 0): ?>
                        <?php foreach ($estudiantes as $e): ?>
                        <tr>
                            <td><strong><?php echo $e['codigo']; ?></strong></td>
                            <td><?php echo $e['nombre'] . " " . $e['apellidos']; ?></td>
                            <td><?php echo $e['email']; ?></td>
                            <td>
                                <a href="editar_usuario.php?id=<?php echo $e['id_user']; ?>" class="btn btn-edit"><i class="fas fa-edit"></i></a>
                                <a href="estudiantes.php?borrar=<?php echo $e['id_user']; ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('¿Eliminar a este alumno? Se borrarán sus calificaciones.');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;">No hay alumnos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>