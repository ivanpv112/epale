<?php
session_start();
require '../db.php';

// 1. SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { 
    header("Location: ../index.php"); exit; 
}

// 2. VERIFICAR ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: expedientes.php"); exit;
}
$usuario_id = intval($_GET['id']);

// 3. OBTENER ÚNICAMENTE EL ROL
$stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$rol = $stmt->fetchColumn();

if (!$rol) {
    header("Location: expedientes.php"); exit;
}

// 4. ENRUTADOR INTELIGENTE
if ($rol === 'ALUMNO') {
    header("Location: ver_expediente_alumno.php?id=" . $usuario_id);
} elseif ($rol === 'PROFESOR') {
    header("Location: ver_expediente_profesor.php?id=" . $usuario_id);
} else {
    // Si intentan ver el expediente de un ADMIN, los regresamos
    header("Location: expedientes.php");
}
exit;
?>
