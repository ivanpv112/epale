<?php
session_start();
require '../db.php';

// 1. SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$mensaje = "";
$tipo_preseleccionado = isset($_GET['tipo']) ? $_GET['tipo'] : 'estudiante'; // Por defecto estudiante

// 2. PROCESAR FORMULARIO (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Recibir datos básicos
    $nombre = $_POST['nombre'];
    $apellidos = $_POST['apellidos'];
    $email = $_POST['email'];
    $password = $_POST['password']; // Contraseña plana
    $telefono = $_POST['telefono'];
    $rol = $_POST['rol'];
    $estatus = $_POST['estatus'];

    // Datos específicos de alumno (si no es alumno, se guardarán como NULL)
    $codigo = ($rol == 'estudiante') ? $_POST['codigo'] : null;
    $carrera = ($rol == 'estudiante') ? $_POST['carrera'] : null;
    $ciclo = ($rol == 'estudiante') ? $_POST['ciclo'] : null;

    // Validaciones básicas
    if (empty($nombre) || empty($email) || empty($password)) {
        $mensaje = "Por favor completa los campos obligatorios.";
    } else {
        try {
            // ENCRIPTAR CONTRASEÑA
            $pass_hash = password_hash($password, PASSWORD_DEFAULT);

            // INSERTAR EN BASE DE DATOS
            $sql = "INSERT INTO users (codigo, nombre, apellidos, email, password, telefono, rol, estatus, carrera, ciclo_escolar) 
                    VALUES (:cod, :nom, :ape, :email, :pass, :tel, :rol, :st, :carrera, :ciclo)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'cod' => $codigo,
                'nom' => $nombre,
                'ape' => $apellidos,
                'email' => $email,
                'pass' => $pass_hash,
                'tel' => $telefono,
                'rol' => $rol,
                'st' => $estatus,
                'carrera' => $carrera,
                'ciclo' => $ciclo
            ]);

            // Redireccionar según el rol creado
            if ($rol == 'profesor') header("Location: profesores.php");
            elseif ($rol == 'estudiante') header("Location: estudiantes.php");
            else header("Location: usuarios.php");
            exit;

        } catch (PDOException $e) {
            // Error común: Duplicado (Código o Email ya existen)
            if ($e->getCode() == 23000) {
                $mensaje = "Error: El Correo o el Código ya están registrados.";
            } else {
                $mensaje = "Error en la base de datos: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Usuario | Admin E-PALE</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="