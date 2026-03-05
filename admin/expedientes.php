<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

$limite_por_pagina = 25;
$pagina_actual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($pagina_actual - 1) * $limite_por_pagina;

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$rol_filter = isset($_GET['rol']) ? $_GET['rol'] : '';

// Por defecto mostramos a Alumnos y Profesores (Excluimos a los ADMIN de esta vista)
$where = "u.rol IN ('ALUMNO', 'PROFESOR')"; 
$params = [];

if ($rol_filter === 'ALUMNO') {
    $where = "u.rol = 'ALUMNO'";
} elseif ($rol_filter === 'PROFESOR') {
    $where = "u.rol = 'PROFESOR'";
}

if ($search !== '') {
    $where .= " AND (u.nombre LIKE :q1 OR u.apellido_paterno LIKE :q2 OR u.apellido_materno LIKE :q3 OR u.codigo LIKE :q4 OR a.carrera LIKE :q5)";
    $termino = "%" . $search . "%";
    $params[':q1'] = $termino;
    $params[':q2'] = $termino;
    $params[':q3'] = $termino;
    $params[':q4'] = $termino;
    $params[':q5'] = $termino;
}

$sql_count = "SELECT COUNT(*) FROM usuarios u LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id WHERE $where";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_estudiantes = $stmt_count->fetchColumn();

$total_paginas = ceil($total_estudiantes / $limite_por_pagina);

// OBTENER RESULTADOS (Usamos usuario_id como llave maestra)
$sql = "SELECT u.*, a.carrera 
        FROM usuarios u 
        LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id 
        WHERE $where 
        ORDER BY u.rol ASC, u.apellido_paterno ASC, u.nombre ASC 
        LIMIT $limite_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador de Perfiles | Admin</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .google-pagination { display: flex; justify-content: center; align-items: center; margin-top: 30px; gap: 5px; font-family: Arial, sans-serif; }
        .google-pagination a { color: #1a0dab; text-decoration: none; padding: 8px 12px; border-radius: 4px; font-size: 1rem; transition: background-color 0.2s; }
        .google-pagination a:hover { background-color: #f1f3f4; }
        .google-pagination a.active { color: #202124; font-weight: bold; background-color: transparent; pointer-events: none; }
        .google-pagination .btn-nav { font-weight: 500; margin: 0 10px; }
        .google-pagination .btn-nav:hover { text-decoration: underline; background-color: transparent; }
    </style>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-folder-open"></i> Perfiles y Expedientes</h1>
            <p>Consulta el historial de los alumnos y las asignaciones de los profesores.</p>
        </div>

        <form class="toolbar" method="GET" action="expedientes.php" style="margin-top: 20px; display: flex; gap: 10px;">
            <i class="fas fa-search" style="color:#aaa; align-self:center;"></i>
            <input type="text" name="q" class="search-input" placeholder="Buscar por nombre o código..." value="<?php echo htmlspecialchars($search); ?>" style="flex-grow: 1;">
            
            <select name="rol" style="padding: 10px; border-radius: 6px; border: 1px solid #ddd;">
                <option value="">Ambos (Alumnos y Profesores)</option>
                <option value="ALUMNO" <?php if($rol_filter=='ALUMNO') echo 'selected'; ?>>Solo Alumnos</option>
                <option value="PROFESOR" <?php if($rol_filter=='PROFESOR') echo 'selected'; ?>>Solo Profesores</option>
            </select>

            <button type="submit" class="btn-save"><i class="fas fa-search"></i> Buscar</button>
            <?php if($search !== '' || $rol_filter !== ''): ?>
                <a href="expedientes.php" class="btn-cancel" style="text-decoration: none; display:flex; align-items:center;">Limpiar</a>
            <?php endif; ?>
        </form>

        <div class="card" style="padding: 0; overflow: hidden; margin-top: 20px;">
            <div class="table-wrapper" style="overflow-x:auto;">
                <table class="history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Código</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Nombre Completo</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Rol / Carrera</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Estado</th>
                            <th style="padding: 15px; text-align: center; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($estudiantes) > 0): ?>
                            <?php foreach ($estudiantes as $e): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 15px; font-family: monospace; font-weight: bold; color: #555;">
                                    <?php echo $e['codigo'] ? htmlspecialchars($e['codigo']) : '---'; ?>
                                </td>
                                <td style="padding: 15px;">
                                    <h4 style="margin: 0; color: var(--udg-blue);">
                                        <?php echo htmlspecialchars($e['apellido_paterno'] . ' ' . $e['apellido_materno'] . ' ' . $e['nombre']); ?>
                                    </h4>
                                    <span style="font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($e['correo']); ?></span>
                                </td>
                                <td style="padding: 15px;">
                                    <?php if($e['rol'] == 'ALUMNO'): ?>
                                        <span style="background: #e7f3ff; color: var(--udg-blue); padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;"><i class="fas fa-user-graduate"></i> <?php echo $e['carrera'] ?: 'Alumno'; ?></span>
                                    <?php else: ?>
                                        <span style="background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;"><i class="fas fa-chalkboard-teacher"></i> Profesor</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px;">
                                    <?php if($e['estatus'] == 'ACTIVO'): ?>
                                        <span style="color: #28a745; font-size: 0.9rem; font-weight: bold;"><i class="fas fa-check-circle"></i> Activo</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-size: 0.9rem; font-weight: bold;"><i class="fas fa-times-circle"></i> Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <a href="ver_expediente.php?id=<?php echo $e['usuario_id']; ?>" class="btn-save" style="display: inline-flex; width: auto; font-size: 0.85rem; padding: 8px 15px;">
                                        <i class="fas fa-eye"></i> Perfil
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-light);">
                                    <i class="fas fa-search" style="font-size: 2.5rem; margin-bottom: 10px; display: block; color: #ddd;"></i>
                                    No se encontraron perfiles con esa búsqueda.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_paginas > 1): ?>
            <div class="google-pagination">
                <?php 
                $qs = urlencode($search); 
                if ($pagina_actual > 1) { echo '<a href="?q='.$qs.'&rol='.$rol_filter.'&page='.($pagina_actual - 1).'" class="btn-nav"><i class="fas fa-chevron-left" style="font-size:0.8rem;"></i> Anterior</a>'; }
                $inicio = max(1, $pagina_actual - 4);
                $fin = min($total_paginas, $pagina_actual + 5);
                for ($i = $inicio; $i <= $fin; $i++) {
                    $activeClass = ($i == $pagina_actual) ? 'class="active"' : '';
                    echo '<a href="?q='.$qs.'&rol='.$rol_filter.'&page='.$i.'" '.$activeClass.'>'.$i.'</a>';
                }
                if ($pagina_actual < $total_paginas) { echo '<a href="?q='.$qs.'&rol='.$rol_filter.'&page='.($pagina_actual + 1).'" class="btn-nav">Siguiente <i class="fas fa-chevron-right" style="font-size:0.8rem;"></i></a>'; }
                ?>
            </div>
        <?php endif; ?>

    </main>
    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>
    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }
    </script>
</body>
</html>