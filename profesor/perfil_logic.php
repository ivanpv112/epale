<?php
/**
 * perfil_logic.php
 * Lógica de negocio y procesamiento de datos para profesor/perfil.php
 */

session_start();
require '../db.php';
require_once '../security.php';

// SEGURIDAD
requerir_rol('PROFESOR');

// Función CSRF
validar_csrf_estricto('POST');

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
        
        // 2. Actualizar contraseña si se escribió una nueva (y actualizar la fecha de cambio)
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE usuarios SET password = ?, fecha_cambio_password = CURRENT_TIMESTAMP WHERE usuario_id = ?")->execute([$hash, $usuario_id]);
        }

        // 3. Procesar Foto de Perfil
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $uploadFileDir = '../img/perfiles/';
            $newFileName = secure_image_upload($_FILES['foto'], $uploadFileDir);
            
            if ($newFileName) {
                // Borrar foto anterior si existe
                $stmt_old = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE usuario_id = ?");
                $stmt_old->execute([$usuario_id]);
                $old_photo = $stmt_old->fetchColumn();
                if ($old_photo && file_exists($uploadFileDir . $old_photo)) {
                    unlink($uploadFileDir . $old_photo);
                }
                // Guardar nueva foto en BD
                $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE usuario_id = ?")->execute([$newFileName, $usuario_id]);
            } else {
                throw new Exception("Formato de imagen no permitido o archivo inválido. Usa JPG o PNG.");
            }
        }
        
        $mensaje = "¡Perfil actualizado correctamente!";
        $tipo_mensaje = "success";
        
    } catch (Exception $e) {
        $mensaje = mensaje_error_amigable($e);
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

// Lógica Visual para la fecha de cambio de contraseña
$fecha_cambio = $profesor['fecha_cambio_password'];
if ($fecha_cambio) {
    $texto_pass = "Último cambio: " . date('d/m/Y \a \l\a\s H:i', strtotime($fecha_cambio));
    $clase_pass = "color: #28a745;"; // Verde
    $icono_pass = "fas fa-check-circle";
} else {
    $texto_pass = "Advertencia: Nunca has cambiado la contraseña por defecto.";
    $clase_pass = "color: #dc3545; font-weight: bold;"; // Rojo
    $icono_pass = "fas fa-exclamation-triangle";
}
