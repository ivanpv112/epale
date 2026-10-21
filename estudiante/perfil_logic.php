<?php
/**
 * perfil_logic.php
 * Controlador y lógica de datos para el perfil del estudiante (información personal, kárdex, certificaciones y diagnósticos).
 */

// Si se intenta acceder directamente al archivo de lógica por URL, redirigir a perfil
if (basename($_SERVER['PHP_SELF']) === 'perfil_logic.php') {
    header('Location: perfil');
    exit;
}

session_start();
require_once '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

requerir_rol('ALUMNO');

$mensaje_exito = "";
$mensaje_error = "";

// =========================================================================
// 1. PROCESAMIENTO DE ACTUALIZACIÓN DE PERFIL (POST)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['actualizar_perfil'])) {
    $nuevo_correo = trim($_POST['correo'] ?? '');
    $nuevo_telefono = preg_replace('/[^0-9]/', '', $_POST['telefono'] ?? '');
    $nueva_password = $_POST['password'] ?? '';

    if (empty($nuevo_correo)) {
        $mensaje_error = "El correo electrónico no puede estar vacío.";
    } elseif (!filter_var($nuevo_correo, FILTER_VALIDATE_EMAIL)) {
        $mensaje_error = "Por favor ingresa un correo electrónico con formato válido.";
    } else {
        try {
            if (!empty($nueva_password)) {
                $hash_password = password_hash($nueva_password, PASSWORD_DEFAULT);
                $sql_update = "UPDATE usuarios SET correo = ?, telefono = ?, password = ?, fecha_cambio_password = NOW() WHERE usuario_id = ?";
                $pdo->prepare($sql_update)->execute([$nuevo_correo, $nuevo_telefono, $hash_password, $_SESSION['user_id']]);
            } else {
                $sql_update = "UPDATE usuarios SET correo = ?, telefono = ? WHERE usuario_id = ?";
                $pdo->prepare($sql_update)->execute([$nuevo_correo, $nuevo_telefono, $_SESSION['user_id']]);
            }
            $mensaje_exito = "¡Tu información se ha actualizado correctamente!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { 
                $mensaje_error = "Error: Ese correo electrónico ya está registrado en otra cuenta.";
            } else {
                $mensaje_error = "Ocurrió un error al actualizar los datos.";
            }
        }
    }
}

// =========================================================================
// 2. MENSAJES FLASH DESDE PARÁMETROS GET
// =========================================================================
if (isset($_GET['exito']) && $_GET['exito'] === 'foto') { 
    $mensaje_exito = "¡Tu foto de perfil ha sido actualizada!"; 
}

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'ext') $mensaje_error = "Error: Solo se permiten imágenes JPG, PNG o WEBP.";
    elseif ($_GET['error'] === 'mime') $mensaje_error = "Error: El archivo no es una imagen válida.";
    elseif ($_GET['error'] === 'upload') $mensaje_error = "Error: La imagen es demasiado pesada.";
    else $mensaje_error = "Ocurrió un error al guardar la foto.";
}

// =========================================================================
// 3. OBTENER INFORMACIÓN DEL USUARIO Y ALUMNO
// =========================================================================
$stmt = $pdo->prepare("SELECT u.*, a.carrera, a.alumno_id FROM usuarios u LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id WHERE u.usuario_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    requerir_rol('ALUMNO');
}

// Si es un usuario alumno sin registro en `alumnos`, crearlo automáticamente
if (empty($user['alumno_id'])) {
    $ins_al = $pdo->prepare("INSERT INTO alumnos (usuario_id, carrera) VALUES (?, '')");
    $ins_al->execute([$_SESSION['user_id']]);
    $alumno_id = (int)$pdo->lastInsertId();
    $user['alumno_id'] = $alumno_id;
} else {
    $alumno_id = (int)$user['alumno_id'];
}

$nombre_completo = trim($user['nombre'] . " " . ($user['apellido_paterno'] ?? $user['apellidos'] ?? '') . " " . ($user['apellido_materno'] ?? ''));
if ($nombre_completo === '') {
    $nombre_completo = $_SESSION['nombre'] ?? 'Estudiante';
}

$foto_perfil = "../img/avatar-default.png"; 
if (!empty($user['foto_perfil']) && file_exists("../img/perfiles/" . $user['foto_perfil'])) { 
    $foto_perfil = "../img/perfiles/" . $user['foto_perfil']; 
}

// =========================================================================
// 4. HISTORIAL ACADÉMICO (KÁRDEX - Clases Cerradas)
// =========================================================================
$sql_historial = "SELECT i.inscripcion_id, m.nombre as materia, m.nivel, c.nombre as ciclo, 
                         (SELECT SUM(puntaje) FROM calificaciones WHERE inscripcion_id = i.inscripcion_id) as calificacion_final
                  FROM inscripciones i
                  JOIN grupos g ON i.nrc = g.nrc
                  JOIN materias m ON g.materia_id = m.materia_id
                  JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                  WHERE i.alumno_id = ? AND g.estado = 'CERRADO' AND i.estatus = 'INSCRITO'
                  ORDER BY c.nombre DESC, m.nivel DESC";
$stmt_hist = $pdo->prepare($sql_historial);
$stmt_hist->execute([$alumno_id]);
$historial = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

// =========================================================================
// 5. CERTIFICACIONES (Idiomas con nivel 4 o superior)
// =========================================================================
$sql_todas = "SELECT m.nombre as materia, m.nivel 
              FROM inscripciones i
              JOIN grupos g ON i.nrc = g.nrc
              JOIN materias m ON g.materia_id = m.materia_id
              WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'";
$stmt_todas = $pdo->prepare($sql_todas);
$stmt_todas->execute([$alumno_id]);
$todas_las_inscripciones = $stmt_todas->fetchAll(PDO::FETCH_ASSOC);

$idiomas_nivel_4 = [];
foreach ($todas_las_inscripciones as $ins) {
    if ((int)$ins['nivel'] >= 4) {
        $idioma_norm = mb_strtoupper(trim($ins['materia']), 'UTF-8');
        $idiomas_nivel_4[$idioma_norm] = true;
    }
}
$idiomas_nivel_4 = array_keys($idiomas_nivel_4);

$certificaciones_bd = [];
if (count($idiomas_nivel_4) > 0) {
    $stmt_cert = $pdo->prepare("SELECT * FROM certificaciones WHERE alumno_id = ?");
    $stmt_cert->execute([$alumno_id]);
    while ($row = $stmt_cert->fetch(PDO::FETCH_ASSOC)) {
        $idioma_norm = mb_strtoupper(trim($row['idioma']), 'UTF-8');
        $certificaciones_bd[$idioma_norm] = $row;
    }
}

// =========================================================================
// 6. EXÁMENES DIAGNÓSTICOS
// =========================================================================
$stmt_diag = $pdo->prepare("SELECT * FROM examenes_diagnosticos WHERE alumno_id = ? ORDER BY fecha_realizacion DESC");
$stmt_diag->execute([$alumno_id]);
$examenes_diagnosticos = $stmt_diag->fetchAll(PDO::FETCH_ASSOC);
