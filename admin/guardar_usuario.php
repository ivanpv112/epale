<?php
session_start();
require '../db.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Recibir datos básicos
    $usuario_id = $_POST['usuario_id'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido_paterno = trim($_POST['apellido_paterno'] ?? '');
    $apellido_materno = trim($_POST['apellido_materno'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $rol = $_POST['rol'] ?? 'ALUMNO';
    $estatus = $_POST['estatus'] ?? 'ACTIVO';
    $codigo = trim($_POST['codigo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $genero = $_POST['genero'] ?? null;
    $password = $_POST['password'] ?? '';

    // 2. Recibir nuevos campos
    $carrera = trim($_POST['carrera'] ?? '');
    $periodo_ingreso = trim($_POST['periodo_ingreso'] ?? '');
    $nacionalidad = trim($_POST['nacionalidad'] ?? '');
    $experiencia = trim($_POST['experiencia'] ?? '');

    if ($codigo === '') $codigo = null;
    if ($genero === '') $genero = null;

    try {
        $pdo->beginTransaction();

        if (empty($usuario_id)) {
            // MODO CREAR NUEVO
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (codigo, nombre, apellido_paterno, apellido_materno, correo, password, rol, estatus, telefono, genero, periodo_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$codigo, $nombre, $apellido_paterno, $apellido_materno, $correo, $hash, $rol, $estatus, $telefono, $genero, $periodo_ingreso]);
            $usuario_id = $pdo->lastInsertId();
        } else {
            // MODO EDITAR
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET codigo=?, nombre=?, apellido_paterno=?, apellido_materno=?, correo=?, password=?, rol=?, estatus=?, telefono=?, genero=?, periodo_ingreso=? WHERE usuario_id=?");
                $stmt->execute([$codigo, $nombre, $apellido_paterno, $apellido_materno, $correo, $hash, $rol, $estatus, $telefono, $genero, $periodo_ingreso, $usuario_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE usuarios SET codigo=?, nombre=?, apellido_paterno=?, apellido_materno=?, correo=?, rol=?, estatus=?, telefono=?, genero=?, periodo_ingreso=? WHERE usuario_id=?");
                $stmt->execute([$codigo, $nombre, $apellido_paterno, $apellido_materno, $correo, $rol, $estatus, $telefono, $genero, $periodo_ingreso, $usuario_id]);
            }
        }

        // 3. Lógica para ALUMNOS
        if ($rol === 'ALUMNO') {
            $check = $pdo->prepare("SELECT COUNT(*) FROM alumnos WHERE usuario_id = ?");
            $check->execute([$usuario_id]);
            if ($check->fetchColumn() > 0) {
                $pdo->prepare("UPDATE alumnos SET carrera = ? WHERE usuario_id = ?")->execute([$carrera, $usuario_id]);
            } else {
                $pdo->prepare("INSERT INTO alumnos (usuario_id, carrera) VALUES (?, ?)")->execute([$usuario_id, $carrera]);
            }
        }

        // 4. Lógica para PROFESORES
        if ($rol === 'PROFESOR') {
            $checkP = $pdo->prepare("SELECT COUNT(*) FROM profesores WHERE usuario_id = ?");
            $checkP->execute([$usuario_id]);
            if ($checkP->fetchColumn() > 0) {
                $pdo->prepare("UPDATE profesores SET nacionalidad = ?, experiencia = ? WHERE usuario_id = ?")->execute([$nacionalidad, $experiencia, $usuario_id]);
            } else {
                $pdo->prepare("INSERT INTO profesores (usuario_id, nacionalidad, experiencia) VALUES (?, ?, ?)")->execute([$usuario_id, $nacionalidad, $experiencia]);
            }
        }

        // Verificar si el admin se cambió el rol a sí mismo
        if ($usuario_id == $_SESSION['user_id'] && $rol !== 'ADMIN') {
            $_SESSION['rol'] = $rol;
            $pdo->commit();
            header("Location: ../index.php");
            exit;
        }

        $pdo->commit();
        header("Location: usuarios.php?msg=ok");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->errorInfo[1] == 1062) { header("Location: usuarios.php?msg=dup"); } 
        else { header("Location: usuarios.php?msg=error"); }
        exit;
    }
}
