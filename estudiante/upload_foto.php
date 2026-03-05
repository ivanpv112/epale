<?php
session_start();
require '../db.php';

// 1. Seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['foto_perfil'])) {
    $file = $_FILES['foto_perfil'];
    $usuario_id = $_SESSION['user_id'];

    // --- NUEVA VALIDACIÓN: Revisar si la imagen es muy pesada o hubo error al subir ---
    if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
        // Redirigimos con un error específico de peso/subida
        header("Location: perfil.php?error=upload"); exit;
    }

    // 2. Medidas de Seguridad
    // a) Whitelist de extensiones
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_ext)) {
        header("Location: perfil.php?error=ext"); exit;
    }

    // b) Verificar tipo real (MIME type)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime_type, $allowed_mimes)) {
        header("Location: perfil.php?error=mime"); exit;
    }

    // 3. Preparar el guardado
    $target_dir = "../img/perfiles/";
    if (!file_exists($target_dir)) { mkdir($target_dir, 0755, true); }

    // c) Renombrar el archivo (Aleatorio y Único)
    $new_file_name = bin2hex(random_bytes(10)) . '.' . $file_ext;
    $target_file = $target_dir . $new_file_name;

    // d) RE-ENCODIZAR LA IMAGEN (Destruye cualquier código malicioso)
    if ($mime_type == 'image/jpeg') {
        $img_source = @imagecreatefromjpeg($file['tmp_name']);
    } elseif ($mime_type == 'image/png') {
        $img_source = @imagecreatefrompng($file['tmp_name']);
    } elseif ($mime_type == 'image/webp') {
        $img_source = @imagecreatefromwebp($file['tmp_name']);
    } else {
        $img_source = false;
    }

    // Guardamos la nueva copia limpia y segura
    if ($img_source) {
        if ($mime_type == 'image/jpeg') {
            imagejpeg($img_source, $target_file, 80); // 80 es la calidad para ahorrar espacio
        } elseif ($mime_type == 'image/png') {
            imagepng($img_source, $target_file);
        } elseif ($mime_type == 'image/webp') {
            imagewebp($img_source, $target_file, 80);
        }
        imagedestroy($img_source); // Limpiar memoria

        // 4. Actualizar la Base de Datos
        $stmt_old = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE usuario_id = ?");
        $stmt_old->execute([$usuario_id]);
        $old_foto = $stmt_old->fetchColumn();
        
        // Borrar foto vieja si existe
        if($old_foto && file_exists($target_dir . $old_foto)) {
            unlink($target_dir . $old_foto);
        }

        // Guardar nuevo nombre
        $stmt_update = $pdo->prepare("UPDATE usuarios SET foto_perfil = ? WHERE usuario_id = ?");
        $stmt_update->execute([$new_file_name, $usuario_id]);
        
        $_SESSION['foto_perfil'] = $new_file_name;

        header("Location: perfil.php?exito=foto"); exit;
    } else {
        header("Location: perfil.php?error=save"); exit;
    }
} else {
    header("Location: perfil.php"); exit;
}