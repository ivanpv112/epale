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

    // Si el código está vacío, lo mandamos como nulo para evitar choques en la BD
    if ($codigo === '') $codigo = null;

    try {
        $pdo->beginTransaction();

        if (empty($usuario_id)) {
            // ==========================================
            // MODO CREAR NUEVO USUARIO
            // ==========================================
            
            // Encriptamos la contraseña
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO usuarios (codigo, nombre, apellido_paterno, apellido_materno, correo, password, rol, estatus, telefono)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo, $nombre, $apellido_paterno, $apellido_materno, $correo, $hash, $rol, $estatus, $telefono]);

            // Obtenemos el ID del usuario recién creado
            $nuevo_id = $pdo->lastInsertId();

            // Si es alumno, guardamos su carrera en la tabla alumnos
            if ($rol === 'ALUMNO') {
                $sql_al = "INSERT INTO alumnos (usuario_id, carrera) VALUES (?, ?)";
                $pdo->prepare($sql_al)->execute([$nuevo_id, $carrera]);
            }

        } else {
            // ==========================================
            // MODO EDITAR USUARIO EXISTENTE
            // ==========================================
            
            if (!empty($password)) {
                // Si escribió una contraseña nueva, la actualizamos
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE usuarios SET codigo=?, nombre=?, apellido_paterno=?, apellido_materno=?, correo=?, password=?, rol=?, estatus=?, telefono=? WHERE usuario_id=?";
                $pdo->prepare($sql)->execute([$codigo, $nombre, $apellido_paterno, $apellido_materno, $correo, $hash, $rol, $estatus, $telefono, $usuario_id]);
            } else {
                // Si dejó la contraseña en blanco, actualizamos todo lo demás menos la contraseña
                $sql = "UPDATE usuarios SET codigo=?, nombre=?, apellido_paterno=?, apellido_materno=?, correo=?, rol=?, estatus=?, telefono=? WHERE usuario_id=?";
                $pdo->prepare($sql)->execute([$codigo, $nombre, $apellido_paterno, $apellido_materno, $correo, $rol, $estatus, $telefono, $usuario_id]);
            }

            // Actualizar tabla alumnos si el rol es alumno
            if ($rol === 'ALUMNO') {
                // Verificamos si ya existe su registro en la tabla de alumnos
                $chk = $pdo->prepare("SELECT COUNT(*) FROM alumnos WHERE usuario_id = ?");
                $chk->execute([$usuario_id]);
                
                if ($chk->fetchColumn() > 0) {
                    $pdo->prepare("UPDATE alumnos SET carrera=? WHERE usuario_id=?")->execute([$carrera, $usuario_id]);
                } else {
                    $pdo->prepare("INSERT INTO alumnos (usuario_id, carrera) VALUES (?, ?)")->execute([$usuario_id, $carrera]);
                }
            }
        }

        $pdo->commit();
        // Si todo sale bien, regresamos a usuarios con el mensaje de ÉXITO
        header("Location: usuarios.php?msg=ok");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        
        // EL CÓDIGO MÁGICO: El error 1062 significa "Registro Duplicado" en MySQL
        if ($e->errorInfo[1] == 1062 || strpos($e->getMessage(), '1062') !== false) {
            // Lo regresamos a usuarios con el mensaje de DUPLICADO
            header("Location: usuarios.php?msg=dup");
            exit;
        } else {
            // Cualquier otro error raro, mandamos mensaje genérico
            header("Location: usuarios.php?msg=error");
            exit;
        }
    }
}
?>
