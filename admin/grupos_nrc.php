<?php
session_start();
require '../db.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php"); exit;
}

// listas para formularios
$profesores = $pdo->query("SELECT usuario_id, nombre, apellido_paterno, apellido_materno FROM usuarios WHERE rol='PROFESOR' AND estatus='ACTIVO'")->fetchAll(PDO::FETCH_ASSOC);
$materias_list = $pdo->query("SELECT materia_id, clave, nombre FROM materias ORDER BY clave")->fetchAll(PDO::FETCH_ASSOC);
$ciclos = $pdo->query("SELECT ciclo_id, nombre FROM ciclos ORDER BY nombre DESC")->fetchAll(PDO::FETCH_ASSOC);

// obtener lista de grupos "lógicos" agrupados por materia, profesor y ciclo
$sql = "SELECT c.nombre AS periodo,
               m.clave AS curso,
               m.nombre AS materia,
               m.nivel AS nivel,
               u.nombre AS profesor,
               GROUP_CONCAT(DISTINCT CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END SEPARATOR '<br>') AS nrc_presencial,
               GROUP_CONCAT(DISTINCT CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END SEPARATOR '<br>') AS nrc_virtual,
               GROUP_CONCAT(DISTINCT CASE WHEN h.modalidad='PRESENCIAL' THEN h.aula END SEPARATOR '<br>') AS aula_presencial,
               GROUP_CONCAT(DISTINCT CASE WHEN h.modalidad='VIRTUAL' THEN h.aula END SEPARATOR '<br>') AS aula_virtual
        FROM grupos g
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN usuarios u ON g.profesor_id = u.usuario_id AND u.rol = 'PROFESOR'
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        JOIN horarios h ON g.nrc = h.nrc
        GROUP BY g.materia_id, g.profesor_id, g.ciclo_id
        ORDER BY c.nombre DESC, m.clave, u.nombre";

$stmt = $pdo->query($sql);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Grupos (NRC) | Admin</title>
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
        <a href="grupos_nrc.php" class="active">GRUPOS (NRC)</a>
        <a href="materias.php">MATERIAS</a>
        <a href="#">REPORTES</a>
        <a href="importar_csv.php">CARGAR</a>
    </div>
    <div class="user-profile">
        <i class="fas fa-user-circle fa-lg"></i> PERFIL <i class="fas fa-sign-out-alt" onclick="window.location.href='../logout.php'" title="Salir" style="margin-left:10px; cursor:pointer;"></i>
    </div>
</nav>

<div class="main-container">
    <?php if(isset($_GET['success'])): ?>
        <div class="alert success" style="margin-bottom:20px; padding:10px; background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:6px;">Grupo agregado correctamente.</div>
    <?php elseif(isset($_GET['error'])): ?>
        <div class="alert error" style="margin-bottom:20px; padding:10px; background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:6px;">
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>
    <div class="page-header">
        <h1>Grupos (NRC)</h1>
        <p>Se muestran pares presencial/virtual por materia y profesor</p>
    </div>
    <div class="toolbar">
        <button type="button" class="btn-primary" onclick="openModal()">
            <i class="fas fa-plus-circle"></i> Agregar Grupo
        </button>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Periodo</th>
                    <th>Curso</th>
                    <th>Materia</th>
                    <th>Nivel</th>
                    <th>NRC</th>
                    <th>Profesor</th>
                    <th>Aula</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($grupos) > 0): ?>
                <?php foreach ($grupos as $g): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($g['periodo']); ?></td>
                        <td><?php echo htmlspecialchars($g['curso']); ?></td>
                        <td><?php echo htmlspecialchars($g['materia']); ?></td>
                        <td><?php echo htmlspecialchars($g['nivel']); ?></td>
                        <td style="white-space: nowrap;">
                            <strong>P:</strong> <?php echo $g['nrc_presencial'] ?: '-'; ?><br>
                            <strong>V:</strong> <?php echo $g['nrc_virtual'] ?: '-'; ?>
                        </td>
                        <td><?php echo htmlspecialchars($g['profesor']); ?></td>
                        <td style="white-space: nowrap;">
                            <strong>P:</strong> <?php echo $g['aula_presencial'] ?: '-'; ?><br>
                            <strong>V:</strong> <?php echo $g['aula_virtual'] ?: '-'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center; color: var(--text-light);">No hay grupos registrados</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

    <!-- Modal para agregar grupo -->
    <div id="grupoModal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Agregar Grupo</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form action="guardar_grupo.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Profesor</label>
                        <select name="profesor_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach($profesores as $p): ?>
                                <?php $full = $p['nombre'] . ' ' . $p['apellido_paterno'] . ($p['apellido_materno']? ' ' . $p['apellido_materno'] : ''); ?>
                                <option value="<?php echo $p['usuario_id']; ?>"><?php echo htmlspecialchars($full); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Materia</label>
                        <select name="materia_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach($materias_list as $m): ?>
                                <option value="<?php echo $m['materia_id']; ?>"><?php echo htmlspecialchars($m['clave'] . ' - ' . $m['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ciclo</label>
                        <select name="ciclo_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach($ciclos as $c): ?>
                                <option value="<?php echo $c['ciclo_id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>NRC Presencial</label>
                        <input type="text" name="rnc_presencial" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Aula Presencial</label>
                        <input type="text" name="aula_presencial">
                    </div>
                    <div class="form-group">
                        <label>Días (P)</label>
                        <input type="text" name="dias_presencial" placeholder="Ej L-M" maxlength="3">
                    </div>
                    <div class="form-group">
                        <label>Inicio (P)</label>
                        <input type="time" name="inicio_presencial">
                    </div>
                    <div class="form-group">
                        <label>Fin (P)</label>
                        <input type="time" name="fin_presencial">
                    </div>
                    <div class="form-group full-width">
                        <label>NRC Virtual</label>
                        <input type="text" name="rnc_virtual" required>
                    </div>
                    <div class="form-group full-width">
                        <label>Aula Virtual</label>
                        <input type="text" name="aula_virtual">
                    </div>
                    <div class="form-group">
                        <label>Días (V)</label>
                        <input type="text" name="dias_virtual" placeholder="Ej M-J" maxlength="3">
                    </div>
                    <div class="form-group">
                        <label>Inicio (V)</label>
                        <input type="time" name="inicio_virtual">
                    </div>
                    <div class="form-group">
                        <label>Fin (V)</label>
                        <input type="time" name="fin_virtual">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-save">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        const modal = document.getElementById('grupoModal');
        function openModal() {
            modal.style.display = 'flex';
        }
        function closeModal() { modal.style.display = 'none'; }
        window.onclick = function(e) { if(e.target == modal) closeModal(); };
    </script>
</div>

</body>
</html>
