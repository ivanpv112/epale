<?php
session_start();
require '../db.php';

// SEGURIDAD
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

// 2. ESCUDO CSRF: Bloquear peticiones de origen cruzado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Error de Seguridad Crítico: Token CSRF inválido o ausente. Petición bloqueada.");
    }
}

// Validacions
$materia_id = $_POST['materia_id'] ?? null;
$clave = trim($_POST['clave'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$nivel = intval($_POST['nivel'] ?? 0);

// Validar que no estén vacíos
if (empty($clave) || empty($nombre) || $nivel < 1) {
    header("Location: materias.php?error=incomplete");
    exit;
}

try {
    if ($materia_id) {
        // EDITAR materia existente
        $stmt = $pdo->prepare("UPDATE materias SET clave = ?, nombre = ?, nivel = ? WHERE materia_id = ?");
        $stmt->execute([$clave, $nombre, $nivel, $materia_id]);
    } else {
        // CREAR nueva materia
        // Verificar que la clave sea única
        $check = $pdo->prepare("SELECT COUNT(*) FROM materias WHERE clave = ?");
        $check->execute([$clave]);
        if ($check->fetchColumn() > 0) {
            header("Location: materias.php?error=duplicate_clave");
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO materias (clave, nombre, nivel) VALUES (?, ?, ?)");
        $stmt->execute([$clave, $nombre, $nivel]);
    }
    
    header("Location: materias.php?success=1");
    exit;
} catch (Exception $e) {
    header("Location: materias.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>
