<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php"); exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $id = isset($_POST['usuario_id']) ? $_POST['usuario_id'] : '';
    $nombre = $_POST['nombre'];
    $apellidos = $_POST['apellidos'];
    $correo = $_POST['correo'];
    $rol = $_POST['rol'];
    $estatus = $_POST['estatus'];
    $password = $_POST['password'];
    $telefono = $_POST['telefono'];
    
    // Datos opcionales
    $codigo = !empty($_POST['codigo']) ? $_POST['codigo'] : null;
    $carrera = ($rol == 'ALUMNO') ? $_POST['carrera'] : null;

    try {
        $pdo->beginTransaction(); // Inicia transacción (todo o nada)

        if (!empty($id)) {
            // --- ACTUALIZAR ---
            $sql = "UPDATE usuarios SET nombre=?, apellidos=?, correo=?, telefono=?, rol=?, estatus=?, codigo=? WHERE usuario_id=?";
            $params = [$nombre, $apellidos, $correo, $telefono, $rol, $estatus, $codigo, $id];
            
            if (!empty($password)) {
                $sql = "UPDATE usuarios SET nombre=?, apellidos=?, correo=?, telefono=?, rol=?, estatus=?, codigo=?, password=? WHERE usuario_id=?";
                $params = [$nombre, $apellidos, $correo, $telefono, $rol, $estatus, $codigo, password_hash($password, PASSWORD_DEFAULT), $id];
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Actualizar datos extra de alumno
            if ($rol == 'ALUMNO') {
                // Verificar si ya existe registro en tabla alumnos
                $check = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
                $check->execute([$id]);
                if($check->fetch()){
                    $pdo->prepare("UPDATE alumnos SET carrera=? WHERE usuario_id=?")->execute([$carrera, $id]);
                } else {
                    $pdo->prepare("INSERT INTO alumnos (usuario_id, carrera) VALUES (?,?)")->execute([$id, $carrera]);
                }
            }

        } else {
            // --- CREAR NUEVO ---
            if (empty($password)) { $password = "12345"; }
            
            $sql = "INSERT INTO usuarios (nombre, apellidos, correo, password, telefono, rol, estatus, codigo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $apellidos, $correo, password_hash($password, PASSWORD_DEFAULT), $telefono, $rol, $estatus, $codigo]);
            
            $new_id = $pdo->lastInsertId(); // Obtenemos el ID del nuevo usuario

            // Si es alumno, insertamos en su tabla detalle
            if ($rol == 'ALUMNO') {
                $sql_alum = "INSERT INTO alumnos (usuario_id, carrera) VALUES (?, ?)";
                $pdo->prepare($sql_alum)->execute([$new_id, $carrera]);
            }
        }
        
        $pdo->commit(); // Guardar cambios
        header("Location: usuarios.php?msg=ok");

    } catch (PDOException $e) {
        $pdo->rollBack(); // Si falla, deshace todo
        die("Error DB: " . $e->getMessage());
    }
}
?>