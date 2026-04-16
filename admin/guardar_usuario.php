<?php
session_start();
require '../db.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Recibir y limpiar los datos del formulario
    $usuario_id = $_POST['usuario_id'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
    $apellido_materno = trim($_POST['apellido_materno'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $rol = $_POST['rol'] ?? 'ALUMNO';
    $estatus = $_POST['estatus'] ?? 'ACTIVO';
    $codigo = trim($_POST['codigo'] ?? '');
    $carrera = trim($_POST['carrera'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($codigo === '') $codigo = null;

    try {
        $pdo->beginTransaction();

        if (empty($usuario_id)) {
            // ==========================================
            // MODO CREAR NUEVO USUARIO
            // ==========================================
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO usuarios (codigo, nombre, apellido_paterno, apellido_materno, correo, password, rol, estatus, telefono)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo, $nombre, $apellido_paterno, $apellido_materno, $correo, $hash, $rol, $estatus, $telefono]);

            $nuevo_id = $pdo->lastInsertId();

            if ($rol === 'ALUMNO') {
                $sql_al = "INSERT INTO alumnos (usuario_id, carrera) VALUES (?, ?)";
                $pdo->prepare($sql_al)->execute([$nuevo_id, $carrera]);
            }

        } else {
            // ==========================================
            // MODO EDITAR USUARIO EXISTENTE
            // ==========================================
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET codigo=?, nombre=?, apellido_paterno=?, apellido_materno=?, correo=?, password=?, rol=?, estatus=?, telefono=? WHERE usuario_id=?";
                $pdo->prepare($sql)->execute([$codigo, $nombre, $apellido_paterno, $apellido_materno, $correo, $hash, $rol, $estatus, $telefono, $usuario_id]);
            } else {
                $sql = "UPDATE usuarios SET codigo=?, nombre=?, apellido_paterno=?, apellido_materno=?, correo=?, rol=?, estatus=?, telefono=? WHERE usuario_id=?";
                $pdo->prepare($sql)->execute([$codigo, $nombre, $apellido_paterno, $apellido_materno, $correo, $rol, $estatus, $telefono, $usuario_id]);
            }

            if ($rol === 'ALUMNO') {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM alumnos WHERE usuario_id = ?");
                $chk->execute([$usuario_id]);
                if ($chk->fetchColumn() > 0) {
                    $pdo->prepare("UPDATE alumnos SET carrera=? WHERE usuario_id=?")->execute([$carrera, $usuario_id]);
                } else {
                    $pdo->prepare("INSERT INTO alumnos (usuario_id, carrera) VALUES (?, ?)")->execute([$usuario_id, $carrera]);
                }
            }

            // ==============================================================
            // NUEVO: VERIFICAR SI EL ADMIN SE QUITÓ SUS PROPIOS PERMISOS
            // ==============================================================
            if ($usuario_id == $_SESSION['user_id']) {
                $_SESSION['rol'] = $rol; // Actualizamos la memoria
                if ($rol !== 'ADMIN') {
                    $pdo->commit(); // Guardamos antes de sacarlo
                    header("Location: ../index.php"); // Expulsado del panel
                    exit;
                }
            }
            // ==============================================================
        }

        $pdo->commit();
        header("Location: usuarios.php?msg=ok");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->errorInfo[1] == 1062 || strpos($e->getMessage(), '1062') !== false) {
            header("Location: usuarios.php?msg=dup"); exit;
        } else {
            header("Location: usuarios.php?msg=error"); exit;
        }
    }
}
?>
