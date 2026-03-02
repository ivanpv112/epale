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
    $pdo->prepare("DELETE FROM materias WHERE materia_id = ?")->execute([$id]);
    header("Location: materias.php"); exit;
}

// CONSULTAS Y FILTROS
$where = "1=1";
$params = [];

if (isset($_GET['q']) && !empty($_GET['q'])) {
    $where .= " AND (nombre LIKE :q OR clave LIKE :q)";
    $params[':q'] = "%".$_GET['q']."%";
}

// definir idiomas posibles y detectar los que existen
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

$sql = "SELECT * FROM materias WHERE $where ORDER BY nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ESTADÍSTICAS
$total_materias = $pdo->query("SELECT COUNT(*) FROM materias")->fetchColumn();
$total_grupos = $pdo->query("SELECT COUNT(*) FROM grupos")->fetchColumn();
$total_idiomas = count($idiomas_present);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Materias | Admin</title>
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <nav class="navbar">
        <div class="navbar-brand">
            <img src="../img/logo-pale.png" alt="Logo" style="height: 35px;"> e-PALE
        </div>
        <div class="navbar-menu">
            <a href="usuarios.php">USUARIOS</a>
            <a href="grupos_nrc.php">GRUPOS (NRC)</a>
            <a href="materias.php" class="active">MATERIAS</a>
            <a href="#">REPORTES</a>
            <a href="importar_csv.php">CARGAR</a>
        </div>
        <div class="user-profile">
            <i class="fas fa-user-circle fa-lg"></i> PERFIL <i class="fas fa-sign-out-alt" onclick="window.location.href='../logout.php'" title="Salir" style="margin-left:10px; cursor:pointer;"></i>
        </div>
    </nav>

    <div class="main-container">
        
        <div class="stats-grid">
            <div class="stat-card"> <span class="stat-number"><?php echo $total_materias; ?></span> <span class="stat-label">Total Materias</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_grupos; ?></span> <span class="stat-label">Grupos Activos</span> </div>
            <div class="stat-card"> <span class="stat-number"><?php echo $total_idiomas; ?></span> <span class="stat-label">Idiomas</span> </div>
        </div>

        <form class="toolbar" method="GET" action="materias.php">
            <i class="fas fa-search" style="color:#aaa; align-self:center;"></i>
            <input type="text" name="q" class="search-input" placeholder="Buscar por nombre o clave..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
            <select name="idioma" class="filter-select" onchange="this.form.submit()">
                <option value="">Todos los idiomas</option>
                <?php foreach ($idiomas_present as $idioma): ?>
                    <option value="<?php echo htmlspecialchars($idioma); ?>" <?php if(isset($_GET['idioma']) && $_GET['idioma']==$idioma) echo 'selected'; ?>><?php echo htmlspecialchars($idioma); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn-primary" onclick="openModal()">
                <i class="fas fa-book-plus"></i> Nueva Materia
            </button>
        </form>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Clave</th>
                        <th>Nombre</th>
                        <th>Nivel</th>
                        <th>Grupos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($materias) > 0): ?>
                        <?php foreach ($materias as $m): ?>
                        <tr>
                            <td>
                                <span style="font-weight: 600; color: var(--udg-blue);"><?php echo htmlspecialchars($m['clave']); ?></span>
                            </td>
                            <td>
                                <h4 style="margin: 0;"><?php echo htmlspecialchars($m['nombre']); ?></h4>
                            </td>
                            <td>
                                <span class="badge-nivel">Nivel <?php echo $m['nivel']; ?></span>
                            </td>
                            <td>
                                <?php 
                                    $count_grupos = $pdo->prepare("SELECT COUNT(*) FROM grupos WHERE materia_id = ?");
                                    $count_grupos->execute([$m['materia_id']]);
                                    $num_grupos = $count_grupos->fetchColumn();
                                    echo '<i class="fas fa-users"></i> ' . $num_grupos;
                                ?>
                            </td>
                            <td>
                                <button class="action-btn" onclick='editMateria(<?php echo json_encode($m); ?>)'>
                                    <i class="fas fa-pen"></i>
                                </button>
                                <a href="materias.php?borrar=<?php echo $m['materia_id']; ?>" class="action-btn delete" onclick="return confirm('¿Está seguro de que desea eliminar esta materia?');">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-light);">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                No hay materias registradas
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Modal para crear/editar materia -->
    <div id="materiaModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nueva Materia</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form action="guardar_materia.php" method="POST">
                <input type="hidden" name="materia_id" id="materiaId">
                <div class="form-grid">
                    <div class="form-group"> 
                        <label>Clave</label> 
                        <input type="text" name="clave" id="materiaclave" required placeholder="Ej. ING101"> 
                    </div>
                    <div class="form-group"> 
                        <label>Nivel</label> 
                        <input type="number" name="nivel" id="materiaLevel" required min="1" max="10" placeholder="Ej. 1"> 
                    </div>
                    <div class="form-group full-width"> 
                        <label>Nombre</label> 
                        <input type="text" name="nombre" id="materiaNombre" required placeholder="Ej. Inglés Nivel 1"> 
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        /* Badge personalizado para nivel */
        .badge-nivel {
            background-color: #e7f3ff;
            color: var(--udg-blue);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
    </style>

    <script>
        const modal = document.getElementById('materiaModal');
        
        function openModal() {
            document.getElementById('materiaId').value = '';
            document.getElementById('modalTitle').innerText = 'Nueva Materia';
            document.getElementById('materiaclave').value = '';
            document.getElementById('materiaNombre').value = '';
            document.getElementById('materiaLevel').value = '';
            modal.style.display = 'flex';
        }
        
        function editMateria(materia) {
            document.getElementById('materiaId').value = materia.materia_id;
            document.getElementById('modalTitle').innerText = 'Editar Materia';
            document.getElementById('materiaclave').value = materia.clave;
            document.getElementById('materiaNombre').value = materia.nombre;
            document.getElementById('materiaLevel').value = materia.nivel;
            modal.style.display = 'flex';
        }
        
        function closeModal() { 
            modal.style.display = 'none'; 
        }
        
        window.onclick = function(e) { 
            if(e.target == modal) closeModal(); 
        };
    </script>

</body>
</html>
