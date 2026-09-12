<?php
session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

// 1. Seguridad de Sesión
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto_perfil'])) {
    $file = $_FILES['foto_perfil'];
    $usuario_id = $_SESSION['user_id'];

    if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        header("Location: perfil.php?error=upload"); exit;
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_ext)) {
        header("Location: perfil.php?error=ext"); exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime_type, $allowed_mimes)) {
        header("Location: perfil.php?error=mime"); exit;
    }

    $target_dir = "../img/perfiles/";
    if (!file_exists($target_dir)) { mkdir($target_dir, 0755, true); }

    $new_file_name = bin2hex(random_bytes(10)) . '.webp';
    $target_file = $target_dir . $new_file_name;

    if ($mime_type == 'image/jpeg') {
        $img_source = @imagecreatefromjpeg($file['tmp_name']);
    } elseif ($mime_type == 'image/png') {
        $img_source = @imagecreatefrompng($file['tmp_name']);
        if ($img_source !== false) {
            imagepalettetotruecolor($img_source);
            imagealphablending($img_source, false);
            imagesavealpha($img_source, true);
        }
    } elseif ($mime_type == 'image/webp') {
        $img_source = @imagecreatefromwebp($file['tmp_name']);
    } else {
        $img_source = false;
    }

    if ($img_source) {
        imagewebp($img_source, $target_file, 80); 
        imagedestroy($img_source); 

        $stmt_old = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE usuario_id = ?");
        $stmt_old->execute([$usuario_id]);
        $old_foto = $stmt_old->fetchColumn();
        
        if($old_foto && file_exists($target_dir . $old_foto)) {
            unlink($target_dir . $old_foto);
        }

        $stmt_update = $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE usuario_id = ?");
        $stmt_update->execute([$new_file_name, $usuario_id]);
        
        header("Location: perfil.php?exito=foto"); exit;
    } else {
        header("Location: perfil.php?error=save"); exit;
    }
} else {
    header("Location: perfil.php"); exit;
}
?>
