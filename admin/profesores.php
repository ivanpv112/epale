<?php
// Este archivo únicamente redirige a la lista global de usuarios con filtro de rol profesor.
header('Location: usuarios.php?rol=PROFESOR');
exit;
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
                            <td><?php 
                                if (isset($p['apellido_paterno'])) {
                                    echo $p['nombre'] . " " . $p['apellido_paterno'] . (isset($p['apellido_materno']) && $p['apellido_materno'] ? ' ' . $p['apellido_materno'] : '');
                                } else {
                                    echo $p['nombre'] . " " . $p['apellidos'];
                                }
                            ?></td>
                            <td><?php echo $p['email'] ?? $p['correo']; ?></td>
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