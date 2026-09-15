<?php
session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

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
        $stmt_old = $pdo->prepare("SELECT nombre, nivel, clave FROM materias WHERE materia_id = ?");
        $stmt_old->execute([$materia_id]);
        $old_mat = $stmt_old->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("UPDATE materias SET clave = ?, nombre = ?, nivel = ? WHERE materia_id = ?");
        $stmt->execute([$clave, $nombre, $nivel, $materia_id]);
        
        $cambios = [];
        if ($old_mat && $old_mat['nombre'] != $nombre) $cambios[] = "Nombre: {$old_mat['nombre']} → $nombre";
        if ($old_mat && $old_mat['nivel'] != $nivel) $cambios[] = "Nivel: {$old_mat['nivel']} → $nivel";
        if ($old_mat && $old_mat['clave'] != $clave) $cambios[] = "Clave: {$old_mat['clave']} → $clave";

        $detalle_str = !empty($cambios) ? " (" . implode(", ", $cambios) . ")" : "";
        
        // LOG HISTORIAL
        registrar_historial($pdo, $_SESSION['user_id'], 'Edición', 'Clases', 'Materia actualizada', "$nombre $nivel ($clave)", "Se actualizaron los datos de la materia/idioma en el catálogo del sistema$detalle_str.");
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
        
        // LOG HISTORIAL
        registrar_historial($pdo, $_SESSION['user_id'], 'Creación', 'Clases', 'Materia creada', "$nombre $nivel ($clave)", "Se registró una nueva materia/idioma en el catálogo del sistema.");
    }
    
    header("Location: materias.php?success=1");
    exit;
} catch (Exception $e) {
    header("Location: materias.php?error=" . urlencode($e->getMessage()));
    exit;
}
?>