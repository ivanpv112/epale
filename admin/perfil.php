<?php
session_start();
require '../db.php'; 

// 1. Seguridad: Verificar que sea ADMINISTRADOR
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
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
            $mensaje_exito = "¡La información del administrador se ha actualizado!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { 
                $mensaje_error = "Error: Ese correo electrónico ya está en uso.";
            } else {
                $mensaje_error = "Ocurrió un error al actualizar los datos.";
            }
        }
    } else {
        $mensaje_error = "El correo electrónico es obligatorio.";
    }
}

// Mensajes de la foto
if(isset($_GET['exito']) && $_GET['exito'] == 'foto') {
    $mensaje_exito = "¡Tu foto de perfil ha sido actualizada!";
}
if(isset($_GET['error'])) {
    if($_GET['error'] == 'ext') $mensaje_error = "Error: Solo se permiten imágenes JPG, PNG o WEBP.";
    else if($_GET['error'] == 'mime') $mensaje_error = "Error: El archivo no es una imagen válida.";
    else if($_GET['error'] == 'upload') $mensaje_error = "Error: La imagen es demasiado pesada (Máx 2MB).";
    else $mensaje_error = "Ocurrió un error al guardar la foto.";
}

// 3. Obtener datos del Administrador de la BD
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Unir nombres (Paterno y Materno)
$nombre_completo = trim($user['nombre'] . " " . $user['apellido_paterno'] . " " . $user['apellido_materno']);

// Definir foto de perfil
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
    <title>Perfil Administrador | E-Pale</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_admin.php'; ?>

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
            <div style="margin-top: 8px;">
                <span class="admin-badge"><i class="fas fa-shield-alt"></i> Administrador del Sistema</span>
            </div>
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
                    <i class="far fa-user"></i> Datos de Contacto
                    <button onclick="abrirModalEditar()" style="margin-left:auto; background:none; border:none; font-size:1.1rem; cursor:pointer; color:var(--udg-blue);" title="Editar Información">
                        <i class="fas fa-pen"></i>
                    </button>
                </h3>
                
                <div class="info-section">
                    <span class="info-label">Nombre Completo</span>
                    <span class="info-value"><?php echo htmlspecialchars($nombre_completo); ?></span>
                </div>
                
                <div class="info-section">
                    <span class="info-label">Correo Institucional</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['correo']); ?></span>
                </div>

                <div class="info-section">
                    <span class="info-label">Teléfono de Contacto</span>
                    <span class="info-value"><?php echo $user['telefono'] ? htmlspecialchars($user['telefono']) : '<span style="color:#aaa;">No registrado</span>'; ?></span>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-server"></i> Información de Cuenta</h3>
                
                <div class="info-section">
                    <span class="info-label">Código / Usuario ID</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['codigo']); ?></span>
                </div>

                <div class="info-section">
                    <span class="info-label">Nivel de Acceso</span>
                    <span class="info-value" style="color: #dc3545; font-weight: bold;">Acceso Total (Root)</span>
                </div>

                <div class="info-section">
                    <span class="info-label">Estatus de Cuenta</span>
                    <span class="info-value">
                        <?php if($user['estatus'] == 'ACTIVO'): ?>
                            <span style="color: #28a745;"><i class="fas fa-check-circle"></i> Activo</span>
                        <?php else: ?>
                            <span style="color: #dc3545;"><i class="fas fa-times-circle"></i> Inactivo</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="info-section">
                    <span class="info-label">Fecha de Registro</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($user['fecha_creacion'])); ?></span>
                </div>
            </div>
        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>

    <div id="modalEditar" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Editar Información</h2>
                <button class="close-btn" onclick="cerrarModalEditar()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="actualizar_perfil" value="1">
                <div class="modal-body">
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
                <h2 id="modalTitle">Cambiar Foto de Perfil</h2>
                <button class="close-btn" onclick="cerrarModalFoto()">&times;</button>
            </div>
            <form action="upload_foto_admin.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <p style="font-size: 0.9rem; color: #666; margin-bottom: 20px;">Formatos permitidos: JPG, PNG, WEBP (Máx 2MB).</p>
                    <div class="form-group">
                        <input type="file" name="foto_perfil" accept="image/*" required style="font-size: 0.9rem; border: none; padding: 0;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModalFoto()">Cancelar</button>
                    <button type="submit" class="btn-save"><i class="fas fa-upload"></i> Subir Foto</button>
                </div>
            </form>
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
