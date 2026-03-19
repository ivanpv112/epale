<?php
session_start();
require '../db.php'; // Ajusta la ruta si tu db.php está en otro lado

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
// Ojo: El admin no está en la tabla 'alumnos', así que solo consultamos 'usuarios'
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Estilos de los modales y foto grande (Mismos que el estudiante) */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 26, 87, 0.7); display: none; justify-content: center; align-items: center; z-index: 3000; backdrop-filter: blur(3px); }
        .modal-content { background-color: white; width: 90%; max-width: 450px; padding: 30px; border-radius: 12px; box-shadow: 0 15px 40px rgba(0,0,0,0.3); animation: slideDown 0.3s ease-out; }
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

        .profile-header-card { height: auto; padding: 40px 0; display: flex; justify-content: center; align-items: center; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); }
        .profile-photo-wrapper { position: relative; width: 180px; height: 180px; border-radius: 50%; border: 5px solid white; box-shadow: 0 5px 25px rgba(0,0,0,0.3); overflow: visible; }
        .profile-photo-img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; background-color: white; }
        .edit-photo-btn { position: absolute; bottom: 5px; right: 5px; background-color: var(--udg-light); color: white; width: 45px; height: 45px; border-radius: 50%; border: 4px solid white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; cursor: pointer; transition: transform 0.2s; box-shadow: 0 3px 10px rgba(0,0,0,0.2); }
        .edit-photo-btn:hover { transform: scale(1.1); background-color: white; color: var(--udg-light); }
        
        /* Etiqueta especial para Administrador */
        .admin-badge { background-color: #dc3545; color: white; padding: 5px 12px; border-radius: 15px; font-size: 0.85rem; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; }
    </style>
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
                <h2>Editar Información</h2>
                <button class="close-btn" onclick="cerrarModalEditar()">&times;</button>
            </div>
            <form method="POST">
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
            <p style="font-size: 0.9rem; color: #666; margin-bottom: 20px;">Formatos permitidos: JPG, PNG, WEBP (Máx 2MB).</p>
            <form action="upload_foto_admin.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <input type="file" name="foto_perfil" accept="image/*" required style="font-size: 0.9rem;">
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
