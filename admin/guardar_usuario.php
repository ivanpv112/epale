<?php
session_start();
require '../db.php';
require '../security.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index"); exit; }

// Función CSRF
validar_csrf_estricto();

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
    $telefono = preg_replace('/[^0-9]/', '', trim($_POST['telefono'] ?? ''));
    $genero = $_POST['genero'] ?? null;
    $password = $_POST['password'] ?? '';

    // ESCUDO ROOT
    if ($usuario_id == 1 && $_SESSION['user_id'] != 1) {
        header("Location: usuarios?msg=error_root");
        exit;
    }

    // 2. Recibir nuevos campos
    $carrera = trim($_POST['carrera'] ?? '');
    $periodo_ingreso = trim($_POST['periodo_ingreso'] ?? '');
    $nacionalidad = trim($_POST['nacionalidad'] ?? '');
    $experiencia = trim($_POST['experiencia'] ?? '');

    if ($codigo === '') $codigo = null;
    if ($genero === '') $genero = null;

    try {
        $pdo->beginTransaction();

        $modo = empty($usuario_id) ? 'crear' : 'editar';
        $old_data = null;
        if ($modo === 'editar') {
            $stmt_old = $pdo->prepare("SELECT u.*, a.carrera, p.nacionalidad, p.experiencia FROM usuarios u LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id LEFT JOIN profesores p ON u.usuario_id = p.usuario_id WHERE u.usuario_id = ?");
            $stmt_old->execute([$usuario_id]);
            $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
        }

        if ($modo === 'crear') {
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
            header("Location: ../index");
            exit;
        }

        // REGISTRO EN HISTORIAL
        $admin_id_sesion = $_SESSION['user_id'];
        $nombre_afectado = $nombre . ' ' . $apellido_paterno . ($codigo ? " ($codigo)" : "");
        
        if ($modo === 'crear') {
            $detalle = "Se registró en el sistema un nuevo usuario con rol de " . ucfirst(strtolower($rol)) . " y correo $correo.";
            registrar_historial($pdo, $admin_id_sesion, 'Creación', 'Usuarios', 'Nuevo usuario creado', $nombre_afectado, $detalle);
        } else {
            if ($old_data['estatus'] !== $estatus) {
                $detalle = "Se cambió el estado de cuenta al usuario (Estado: " . ucfirst(strtolower($old_data['estatus'])) . " → " . ucfirst(strtolower($estatus)) . ").";
                registrar_historial($pdo, $admin_id_sesion, 'Estado', 'Usuarios', 'Cambio de estado de cuenta', $nombre_afectado, $detalle);
            }
            if ($old_data['rol'] !== $rol) {
                $detalle = "Se actualizó el rol del usuario (Rol: " . ucfirst(strtolower($old_data['rol'])) . " → " . ucfirst(strtolower($rol)) . ").";
                registrar_historial($pdo, $admin_id_sesion, 'Edición', 'Usuarios', 'Cambio de rol de usuario', $nombre_afectado, $detalle);
            }
            
            $cambios = [];
            if ($old_data['nombre'] != $nombre) $cambios[] = "Nombre: {$old_data['nombre']} → $nombre";
            if ($old_data['apellido_paterno'] != $apellido_paterno) $cambios[] = "Ape. Paterno: {$old_data['apellido_paterno']} → $apellido_paterno";
            if ($old_data['apellido_materno'] != $apellido_materno) $cambios[] = "Ape. Materno: {$old_data['apellido_materno']} → $apellido_materno";
            if ($old_data['correo'] != $correo) $cambios[] = "Correo: {$old_data['correo']} → $correo";
            if ($old_data['codigo'] != $codigo) $cambios[] = "Código: {$old_data['codigo']} → $codigo";
            if ($old_data['telefono'] != $telefono) $cambios[] = "Teléfono: {$old_data['telefono']} → $telefono";
            if ($old_data['genero'] != $genero) $cambios[] = "Género: {$old_data['genero']} → $genero";
            if ($old_data['periodo_ingreso'] != $periodo_ingreso) $cambios[] = "Ingreso: {$old_data['periodo_ingreso']} → $periodo_ingreso";
            
            if ($rol === 'ALUMNO' && ($old_data['carrera'] ?? '') != $carrera) $cambios[] = "Carrera: " . ($old_data['carrera'] ?? 'N/A') . " → $carrera";
            if ($rol === 'PROFESOR') {
                if (($old_data['nacionalidad'] ?? '') != $nacionalidad) $cambios[] = "Nacionalidad: " . ($old_data['nacionalidad'] ?? 'N/A') . " → $nacionalidad";
                if (($old_data['experiencia'] ?? '') != $experiencia) $cambios[] = "Experiencia: " . ($old_data['experiencia'] ?? 'N/A') . " → $experiencia";
            }
            
            if (!empty($cambios)) {
                $detalle = "Se actualizaron los datos del usuario (" . implode(", ", $cambios) . ").";
                registrar_historial($pdo, $admin_id_sesion, 'Edición', 'Usuarios', 'Datos de usuario actualizados', $nombre_afectado, $detalle);
            }
        }

        $pdo->commit();
        header("Location: usuarios?msg=ok");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->errorInfo[1] == 1062) { header("Location: usuarios?msg=dup"); } 
        else { header("Location: usuarios?msg=error"); }
        exit;
    }
}