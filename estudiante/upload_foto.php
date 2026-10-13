<?php
session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

// 1. Seguridad de Sesión
requerir_rol('ALUMNO');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto_perfil'])) {
    $file = $_FILES['foto_perfil'];
    $usuario_id = $_SESSION['user_id'];

    $target_dir = "../img/perfiles/";
    $new_file_name = secure_image_upload($file, $target_dir);

    if ($new_file_name) {
        // 5. Actualizar la Base de Datos
        $stmt_old = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE usuario_id = ?");
        $stmt_old->execute([$usuario_id]);
        $old_foto = $stmt_old->fetchColumn();
        
        // Borrar foto vieja si existe para no acumular basura
        if($old_foto && file_exists($target_dir . $old_foto)) {
            unlink($target_dir . $old_foto);
        }

        // Guardar nuevo nombre en la BD
        $stmt_update = $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE usuario_id = ?");
        $stmt_update->execute([$new_file_name, $usuario_id]);
        
        $_SESSION['foto_perfil'] = $new_file_name;

        header("Location: perfil?exito=foto"); exit;
    } else {
        header("Location: perfil?error=save"); exit;
    }
} else {
    header("Location: perfil"); exit;
}
?>