<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

// Para que el filtro en tiempo real funcione, cargamos todos los perfiles a la vez.
$sql = "SELECT u.*, a.carrera 
        FROM usuarios u 
        LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id 
        WHERE u.rol IN ('ALUMNO', 'PROFESOR') 
        ORDER BY u.rol ASC, u.nombre ASC, u.apellido_paterno ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
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
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div class="page-title-center" style="margin-bottom: 30px;">
            <h1><i class="fas fa-folder-open"></i> Perfiles y Expedientes</h1>
            <p>Consulta el historial de los alumnos y las asignaciones de los profesores.</p>
        </div>

        <!-- BARRA DE BÚSQUEDA DINÁMICA EXPEDIENTES -->
        <form class="toolbar mt-20" onsubmit="event.preventDefault();">
            <i class="fas fa-search icon-muted" style="align-self:center;"></i>
            <input type="text" id="buscadorExpedientes" class="search-input" placeholder="Buscar por nombre, correo o código...">
            
            <select id="filtroRol" class="filter-select">
                <option value="">Ambos roles</option>
                <option value="ALUMNO">Alumnos</option>
                <option value="PROFESOR">Profesores</option>
            </select>
        </form>

        <div class="card" style="padding: 0; overflow: hidden; margin-top: 20px;">
            <div class="table-wrapper" style="overflow-x:auto;">
                <table class="history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Perfil / Usuario</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Código</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Rol / Carrera</th>
                            <th style="padding: 15px; text-align: left; background-color: #f8f9fa; border-bottom: 2px solid #eee;">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="tablaExpedientes">
                        <?php if (count($estudiantes) > 0): ?>
                            <?php foreach ($estudiantes as $e): 
                                $ruta_destino = ($e['rol'] == 'ALUMNO') ? 'ver_expediente_alumno.php' : 'ver_expediente_profesor.php';
                            ?>
                            <tr class="clickable-row" data-rol="<?php echo $e['rol']; ?>" style="border-bottom: 1px solid #eee;" onclick="window.location.href='<?php echo $ruta_destino; ?>?id=<?php echo $e['usuario_id']; ?>'">
                                
                                <td class="user-cell" style="padding: 15px;">
                                    <h4 style="margin: 0; color: var(--udg-blue);">
                                        <?php 
                                            if (isset($e['apellido_paterno'])) {
                                                echo htmlspecialchars($e['nombre'] . ' ' . $e['apellido_paterno'] . (isset($e['apellido_materno']) && $e['apellido_materno'] ? ' ' . $e['apellido_materno'] : ''));
                                            } else {
                                                echo htmlspecialchars($e['nombre'] . ' ' . $e['apellidos']);
                                            }
                                        ?>
                                    </h4>
                                    <span style="font-size: 0.85rem; color: #666;"><?php echo htmlspecialchars($e['correo']); ?></span>
                                </td>

                                <td style="padding: 15px; color: #555;">
                                    <?php echo $e['codigo'] ? htmlspecialchars($e['codigo']) : '-'; ?>
                                </td>

                                <td style="padding: 15px; color: #555;">
                                    <?php if($e['rol'] == 'ALUMNO'): ?>
                                        <i class="fas fa-user-graduate" style="color:#888;"></i> Alumno
                                        <?php if($e['carrera']): ?>
                                            <br><span style="font-size: 0.8rem; color: #aaa;"><?php echo htmlspecialchars($e['carrera']); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <i class="fas fa-chalkboard-teacher" style="color:#888;"></i> Profesor
                                    <?php endif; ?>
                                    <br>
                                    <div style="font-size: 0.8rem; color: #666; margin-top: 4px;">
                                        <?php 
                                            if($e['genero'] == 'MASCULINO') echo '<i class="fas fa-mars" style="color:#3b82f6;"></i> Masc';
                                            elseif($e['genero'] == 'FEMENINO') echo '<i class="fas fa-venus" style="color:#e83e8c;"></i> Fem';
                                            elseif($e['genero'] == 'OTRO') echo '<i class="fas fa-transgender-alt" style="color:#6f42c1;"></i> Otro';
                                            else echo '<i class="fas fa-genderless" style="color:#aaa;"></i> N/E';
                                        ?>
                                    </div>
                                </td>

                                <td style="padding: 15px;">
                                    <?php if($e['estatus'] == 'ACTIVO'): ?>
                                        <span class="tag-aprobado" style="background-color: #d4edda; color: #155724; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;">Activo</span>
                                    <?php else: ?>
                                        <span class="tag-aprobado" style="background-color: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold;">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr id="noResultsRow" style="display: none;"><td colspan="4" class="empty-table-msg"><i class="fas fa-search" style="font-size: 2.5rem; margin-bottom: 10px; display: block; color: #ddd;"></i>No se encontraron perfiles.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>
    
    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        
        // FILTRO EN TIEMPO REAL
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('buscadorExpedientes'); 
            const rolSelect = document.getElementById('filtroRol'); 
            const rows = document.querySelectorAll('.clickable-row');
            
            function filterTable() {
                const term = searchInput.value.toLowerCase(); 
                const role = rolSelect.value;
                let found = false;
                
                rows.forEach(row => {
                    const txt = row.innerText.toLowerCase(); 
                    const r = row.getAttribute('data-rol');
                    if (txt.includes(term) && (role === '' || r === role)) {
                        row.style.display = '';
                        found = true;
                    } else {
                        row.style.display = 'none';
                    }
                });
                document.getElementById('noResultsRow').style.display = found ? 'none' : '';
            }
            
            searchInput.addEventListener('input', filterTable); 
            rolSelect.addEventListener('change', filterTable);
        });
    </script>
</body>
</html>
