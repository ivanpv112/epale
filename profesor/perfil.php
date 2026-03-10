<?php
session_start();
require '../db.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') { 
    header("Location: ../index.php"); exit; 
}

$usuario_id = $_SESSION['user_id'];
$mensaje = '';
$tipo_mensaje = '';

// ==========================================
// PROCESAR ACTUALIZACIÓN DE PERFIL
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';
    
    try {
        // 1. Actualizar teléfono
        $pdo->prepare("UPDATE usuarios SET telefono = ? WHERE usuario_id = ?")->execute([$telefono, $usuario_id]);
        
        // 2. Actualizar contraseña si se escribió una nueva
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET password = ? WHERE usuario_id = ?")->execute([$hash, $usuario_id]);
        }

        // 3. Procesar Foto de Perfil
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['foto']['tmp_name'];
            $fileName = $_FILES['foto']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            
            $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
            if (in_array($fileExtension, $allowedfileExtensions)) {
                // Crear nombre único para la foto
                $newFileName = 'prof_' . $usuario_id . '_' . time() . '.' . $fileExtension;
                $uploadFileDir = '../img/perfiles/';
                
                // Crear la carpeta si no existe
                if (!is_dir($uploadFileDir)) { mkdir($uploadFileDir, 0755, true); }
                
                $dest_path = $uploadFileDir . $newFileName;
                
                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    // Borrar foto anterior si existe
                    $stmt_old = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE usuario_id = ?");
                    $stmt_old->execute([$usuario_id]);
                    $old_photo = $stmt_old->fetchColumn();
                    if ($old_photo && file_exists($uploadFileDir . $old_photo)) {
                        unlink($uploadFileDir . $old_photo);
                    }
                    // Guardar nueva foto en BD
                    $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE usuario_id = ?")->execute([$newFileName, $usuario_id]);
                }
            } else {
                throw new Exception("Formato de imagen no permitido. Usa JPG o PNG.");
            }
        }
        
        $mensaje = "¡Perfil actualizado correctamente!";
        $tipo_mensaje = "success";
        
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = "error";
    }
}

// OBTENER DATOS ACTUALES
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$profesor = $stmt->fetch(PDO::FETCH_ASSOC);

$foto_actual = "../img/avatar-default.png";
if ($profesor['foto_perfil'] && file_exists("../img/perfiles/" . $profesor['foto_perfil'])) {
    $foto_actual = "../img/perfiles/" . $profesor['foto_perfil'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil | Portal Docente</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profesor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        
        <?php if($mensaje): ?>
            <div class="alert <?php echo ($tipo_mensaje == 'success') ? 'alert-success' : 'alert-error'; ?>" style="margin-bottom: 20px; <?php echo ($tipo_mensaje == 'error') ? 'background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:15px; border-radius:8px;' : ''; ?>">
                <i class="fas <?php echo ($tipo_mensaje == 'success') ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="perfil.php" enctype="multipart/form-data">
            
            <div class="teacher-header-card">
                <div class="teacher-avatar-wrapper">
                    <img src="<?php echo $foto_actual; ?>" id="previewAvatar" class="teacher-avatar-img">
                    <label for="fotoInput" class="teacher-upload-btn" title="Cambiar Foto">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="fotoInput" name="foto" accept="image/png, image/jpeg" style="display: none;" onchange="previewImage(this)">
                </div>
                <div class="teacher-info-wrapper">
                    <h1><?php echo htmlspecialchars($profesor['nombre'] . ' ' . $profesor['apellido_paterno']); ?></h1>
                    <p>
                        <span><i class="fas fa-chalkboard-teacher"></i> Docente Activo</span> 
                        <span style="opacity: 0.5;">|</span> 
                        <span><i class="fas fa-id-badge"></i> Código: <?php echo htmlspecialchars($profesor['codigo']); ?></span>
                    </p>
                </div>
            </div>

            <div class="content-card">
                <h3 class="card-title"><i class="fas fa-user-edit"></i> Información Personal</h3>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    
                    <div class="form-group">
                        <label>Nombre(s)</label>
                        <input type="text" value="<?php echo htmlspecialchars($profesor['nombre']); ?>" class="readonly-data" readonly title="Solicita cambios de nombre al Administrador">
                    </div>
                    <div class="form-group">
                        <label>Apellidos</label>
                        <input type="text" value="<?php echo htmlspecialchars($profesor['apellido_paterno'] . ' ' . $profesor['apellido_materno']); ?>" class="readonly-data" readonly>
                    </div>
                    <div class="form-group">
                        <label>Correo Institucional</label>
                        <input type="text" value="<?php echo htmlspecialchars($profesor['correo']); ?>" class="readonly-data" readonly>
                    </div>
                    <div class="form-group">
                        <label>Código de Profesor</label>
                        <input type="text" value="<?php echo htmlspecialchars($profesor['codigo']); ?>" class="readonly-data" readonly>
                    </div>

                    <div style="grid-column: span 2; margin: 10px 0;"><hr style="border: none; border-top: 1px solid #eee;"></div>

                    <div class="form-group">
                        <label style="color: var(--udg-blue); font-weight: bold;">Teléfono de Contacto</label>
                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($profesor['telefono'] ?? ''); ?>" placeholder="Ej. 33 1234 5678">
                    </div>
                    <div class="form-group">
                        <label style="color: var(--udg-blue); font-weight: bold;">Actualizar Contraseña</label>
                        <input type="password" name="password" placeholder="Escribe aquí solo si deseas cambiarla">
                        <small style="color: #888;">Déjalo en blanco para mantener tu contraseña actual.</small>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="submit" class="btn-save" style="font-size: 1.1rem; padding: 12px 30px;"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </div>

        </form>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Portal Docente</div></footer>

    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        
        // Vista previa de la foto antes de guardar
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewAvatar').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Limpiar URL de refresh
        if (window.history.replaceState) { window.history.replaceState(null, null, window.location.href); }
    </script>
</body>
</html>