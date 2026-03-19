<?php
session_start();
require '../db.php';

// 1. Seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}

// 2. PROCESAR ACTUALIZACIÓN DE DATOS
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
            if ($e->getCode() == 23000) { 
                $mensaje_error = "Error: Ese correo electrónico ya está registrado en otra cuenta.";
            } else {
                $mensaje_error = "Ocurrió un error al actualizar los datos.";
            }
        }
    } else {
        $mensaje_error = "El correo electrónico no puede estar vacío.";
    }
}

// Mensajes por GET
if(isset($_GET['exito']) && $_GET['exito'] == 'foto') {
    $mensaje_exito = "¡Tu foto de perfil ha sido actualizada!";
}
if(isset($_GET['error'])) {
    if($_GET['error'] == 'ext') $mensaje_error = "Error: Solo se permiten imágenes JPG, PNG o WEBP.";
    else if($_GET['error'] == 'mime') $mensaje_error = "Error: El archivo no es una imagen válida.";
    else if($_GET['error'] == 'upload') $mensaje_error = "Error: La imagen es demasiado pesada o superó el límite del servidor (Máximo 2MB recomendados).";
    else $mensaje_error = "Ocurrió un error al guardar la foto.";
}

// 3. Obtener datos
$stmt = $pdo->prepare("
    SELECT u.*, a.carrera 
    FROM usuarios u 
    LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id 
    WHERE u.usuario_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$nombre_completo = $user['nombre'] . " " . (isset($user['apellido_paterno']) ? $user['apellido_paterno'] : $user['apellidos']) . (isset($user['apellido_materno']) && $user['apellido_materno'] ? ' ' . $user['apellido_materno'] : '');

$foto_perfil = "../img/avatar-default.png"; 
if(isset($user['foto_perfil']) && $user['foto_perfil'] && file_exists("../img/perfiles/" . $user['foto_perfil'])) {
    $foto_perfil = "../img/perfiles/" . $user['foto_perfil'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Perfil | E-Pale</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content">
        
        <div class="profile-header-card">
            <div class="profile-photo-wrapper">
                <img src="<?php echo $foto_perfil; ?>" alt="Foto de Perfil" class="profile-photo-img">
                <label class="edit-photo-btn" onclick="abrirModalFoto()" title="Cambiar foto de perfil">
                    <i class="fas fa-camera"></i>
                </label>
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
                    <button onclick="abrirModalEditar()" style="margin-left:auto; background:none; border:none; font-size:1.1rem; cursor:pointer; color:var(--udg-blue);" title="Editar Información">
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
                    <span class="info-value"><?php echo isset($user['codigo']) && $user['codigo'] ? htmlspecialchars($user['codigo']) : '---'; ?></span>
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
                <button class="close-btn" onclick="cerrarModalEditar()">&times;</button>
            </div>
            <form method="POST" action="perfil.php">
                <input type="hidden" name="actualizar_perfil" value="1">
                
                <div class="modal-body" style="padding-top: 0; overflow-y: visible;">
                    <div class="form-group">
                        <label>Correo Electrónico</label>
                        <input type="email" name="correo" value="<?php echo htmlspecialchars($user['correo']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($user['telefono']); ?>" placeholder="Ej. 33 1234 5678">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModalEditar()">Cancelar</button>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalFoto" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cambiar Foto de Perfil</h2>
                <button class="close-btn" onclick="cerrarModalFoto()">&times;</button>
            </div>
            
            <div class="modal-body" style="padding-top: 0; overflow-y: visible;">
                <p style="font-size: 0.9rem; color: #666; margin-bottom: 20px;">Por favor, selecciona una imagen cuadrada y de buena calidad (máx 5MB). Formatos permitidos: JPG, PNG, WEBP.</p>
                
                <form action="upload_foto.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <input type="file" name="foto_perfil" id="fileFoto" accept="image/*" required style="font-size: 0.9rem; padding: 0; border: none;">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="cerrarModalFoto()">Cancelar</button>
                        <button type="submit" class="btn-save" style="display:flex; align-items:center; gap:8px;"><i class="fas fa-upload"></i> Subir Foto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }

        const modalEditar = document.getElementById('modalEditar');
        const modalFoto = document.getElementById('modalFoto');
        const overlayMenu = document.getElementById('menuOverlay');
        
        function abrirModalEditar() { modalEditar.style.display = 'flex'; }
        function cerrarModalEditar() { modalEditar.style.display = 'none'; }
        
        function abrirModalFoto() { modalFoto.style.display = 'flex'; }
        function cerrarModalFoto() { modalFoto.style.display = 'none'; }

        window.onclick = function(e) { 
            if(e.target == modalEditar) cerrarModalEditar(); 
            if(e.target == modalFoto) cerrarModalFoto(); 
            if(e.target == overlayMenu) toggleMobileMenu(); 
        }
    </script>
</body>
</html>
