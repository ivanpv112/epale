<?php
session_start();
require '../db.php';

// 1. Seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}

// 2. PROCESAR ACTUALIZACIÓN DE DATOS (Si el alumno envía el formulario)
$mensaje_exito = "";
$mensaje_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_perfil'])) {
    $nuevo_correo = trim($_POST['correo']);
    $nuevo_telefono = trim($_POST['telefono']);

    if (!empty($nuevo_correo)) {
        try {
            $sql_update = "UPDATE usuarios SET correo = ?, telefono = ? WHERE usuario_id = ?";
            $pdo->prepare($sql_update)->execute([$nuevo_correo, $nuevo_telefono, $_SESSION['user_id']]);
            $mensaje_exito = "¡Tu información se ha actualizado correctamente!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Código de error para datos duplicados
                $mensaje_error = "Error: Ese correo electrónico ya está registrado en otra cuenta.";
            } else {
                $mensaje_error = "Ocurrió un error al actualizar los datos.";
            }
        }
    } else {
        $mensaje_error = "El correo electrónico no puede estar vacío.";
    }
}

// 3. Obtener datos frescos de la BD (Se ejecuta DESPUÉS de actualizar para mostrar los datos nuevos)
$stmt = $pdo->prepare("
    SELECT u.*, a.carrera 
    FROM usuarios u 
    LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id 
    WHERE u.usuario_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$nombre_completo = $user['nombre'] . " " . (isset($user['apellido_paterno']) ? $user['apellido_paterno'] : $user['apellidos']) . (isset($user['apellido_materno']) && $user['apellido_materno'] ? ' ' . $user['apellido_materno'] : '');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Perfil | E-Pale</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 26, 87, 0.7); display: none;
            justify-content: center; align-items: center; z-index: 1000;
            backdrop-filter: blur(3px);
        }
        .modal-content {
            background-color: white; width: 90%; max-width: 450px;
            padding: 30px; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease-out;
        }
        @keyframes slideDown { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .modal-header h2 { margin: 0; color: var(--udg-blue); font-size: 1.3rem; }
        .close-btn { background: none; border: none; font-size: 1.5rem; color: #999; cursor: pointer; }
        .close-btn:hover { color: #dc3545; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 0.95rem;}
        .form-group input:focus { border-color: var(--udg-light); outline: none; }
        .modal-footer { margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel { background: white; border: 1px solid #ddd; padding: 10px 15px; border-radius: 6px; cursor: pointer; color: #666; font-weight: 600;}
        .btn-save { background: var(--udg-blue); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600;}
        .btn-save:hover { background: var(--udg-light); }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; text-align: center; font-weight: 500;}
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
    </style>
</head>
<body>

    <header class="main-header">
        <div class="logo-container">
            <img src="../img/logo-pale.png" alt="E-PALE" class="logo-img">
            <span>e-PALE</span>
        </div>
        
        <nav class="main-nav">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
                <li><a href="#"><i class="far fa-calendar-alt"></i> Horario</a></li>
                <li><a href="calificaciones.php"><i class="fas fa-star"></i> Calificaciones</a></li>
                <li><a href="#"><i class="fas fa-bullhorn"></i> Oferta</a></li>
            </ul>
        </nav>

        <div class="user-actions">
            <a href="perfil.php" class="profile-btn" style="background: rgba(255,255,255,0.3);">
                <i class="fas fa-user-circle"></i>
                <span><?php echo strtok($_SESSION['nombre'], " "); ?></span>
            </a>
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Salir
            </a>
        </div>
    </header>

    <main class="main-content">
        
        <div class="profile-header-card">
            <div class="profile-avatar-container">
                <i class="fas fa-user profile-avatar-icon"></i>
            </div>
        </div>

        <div class="profile-basic-info">
            <h2 style="margin:0; color:var(--udg-blue);"><?php echo htmlspecialchars($nombre_completo); ?></h2>
            <span class="profile-role-badge"><i class="fas fa-graduation-cap"></i> Alumno</span>
            <p style="color:#666; margin-top:5px; font-size:0.9rem;"><?php echo htmlspecialchars($user['correo']); ?></p>
        </div>

        <?php if(!empty($mensaje_exito)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $mensaje_exito; ?></div>
        <?php endif; ?>
        <?php if(!empty($mensaje_error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $mensaje_error; ?></div>
        <?php endif; ?>

        <div class="profile-grid">
            
            <div class="card">
                <h3>
                    <i class="far fa-user"></i> Información Personal 
                    <button onclick="abrirModal()" style="margin-left:auto; background:none; border:none; font-size:1.1rem; cursor:pointer; color:var(--udg-blue);" title="Editar Información">
                        <i class="fas fa-pen"></i>
                    </button>
                </h3>
                
                <div class="info-section">
                    <span class="info-label">Nombre Completo</span>
                    <span class="info-value"><?php echo htmlspecialchars($nombre_completo); ?></span>
                </div>
                
                <div class="info-section">
                    <span class="info-label">Correo Electrónico</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['correo']); ?></span>
                </div>

                <div class="info-section">
                    <span class="info-label">Teléfono</span>
                    <span class="info-value"><?php echo $user['telefono'] ? htmlspecialchars($user['telefono']) : '<span style="color:#aaa;">No registrado</span>'; ?></span>
                </div>
                </div>

            <div class="card">
                <h3><i class="fas fa-graduation-cap"></i> Información Académica</h3>
                
                <div class="info-section">
                    <span class="info-label">Código de Estudiante</span>
                    <span class="info-value"><?php echo $user['codigo'] ? htmlspecialchars($user['codigo']) : '---'; ?></span>
                </div>

                <div class="info-section">
                    <span class="info-label">Carrera / Programa</span>
                    <span class="info-value"><?php echo isset($user['carrera']) && $user['carrera'] ? htmlspecialchars($user['carrera']) : 'Sin asignar'; ?></span>
                </div>

                <div class="info-section">
                    <span class="info-label">Nivel Actual</span>
                    <span class="info-value">Inglés IV</span>
                </div>

                <div class="info-section">
                    <span class="info-label">Ciclo Escolar</span>
                    <span class="info-value">2026-A</span>
                </div>
            </div>
        </div>

        <div class="card">
            <h3><i class="fas fa-history"></i> Historial de Calificaciones</h3>
            <div style="overflow-x:auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Materia</th>
                            <th>Ciclo</th>
                            <th>Calificación</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Inglés III</td><td>2025-B</td><td><strong>92</strong></td><td><span class="tag-aprobado">Aprobado</span></td></tr>
                        <tr><td>Inglés II</td><td>2025-A</td><td><strong>88</strong></td><td><span class="tag-aprobado">Aprobado</span></td></tr>
                        <tr><td>Inglés I</td><td>2024-B</td><td><strong>95</strong></td><td><span class="tag-aprobado">Aprobado</span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <footer class="main-footer">
        <div class="address-bar">
            Copyright © 2026 E-PALE
        </div>
    </footer>

    <div id="modalEditar" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Información</h2>
                <button class="close-btn" onclick="cerrarModal()">&times;</button>
            </div>
            <form method="POST" action="perfil.php">
                <input type="hidden" name="actualizar_perfil" value="1">
                
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="correo" value="<?php echo htmlspecialchars($user['correo']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="text" name="telefono" value="<?php echo htmlspecialchars($user['telefono']); ?>" placeholder="Ej. 33 1234 5678">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('modalEditar');
        function abrirModal() { modal.style.display = 'flex'; }
        function cerrarModal() { modal.style.display = 'none'; }
        window.onclick = function(e) { if(e.target == modal) cerrarModal(); }
    </script>

</body>
</html>