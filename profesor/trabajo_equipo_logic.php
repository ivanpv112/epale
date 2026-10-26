<?php
/**
 * trabajo_equipo_logic.php
 * Lógica de negocio y consultas para la conformación de equipos en profesor/trabajo_equipo.php
 */

session_start();
require '../db.php';
require_once '../security.php';

requerir_rol('PROFESOR');

$profesor_id = $_SESSION['user_id'];
$clave_grupo = $_GET['clave'] ?? '';

if (!$clave_grupo) {
    header("Location: mis_grupos");
    exit;
}

// Obtener info del grupo
$stmt_info = $pdo->prepare("SELECT m.clave AS clave_materia, m.nombre AS materia, m.nivel FROM grupos g JOIN materias m ON g.materia_id = m.materia_id WHERE g.clave_grupo = ? AND g.profesor_id = ? LIMIT 1");
$stmt_info->execute([$clave_grupo, $profesor_id]);
$info_grupo = $stmt_info->fetch(PDO::FETCH_ASSOC);

if (!$info_grupo) {
    header("Location: mis_grupos");
    exit;
}

// Obtener alumnos
$sql_alum = "SELECT i.inscripcion_id, u.codigo, u.nombre, u.apellido_paterno, u.apellido_materno, u.foto_perfil 
             FROM inscripciones i 
             JOIN alumnos a ON i.alumno_id = a.alumno_id 
             JOIN usuarios u ON a.usuario_id = u.usuario_id 
             WHERE i.nrc IN (SELECT nrc FROM grupos WHERE clave_grupo = ?) AND i.estatus = 'INSCRITO' 
             ORDER BY u.apellido_paterno ASC";
$stmt_alum = $pdo->prepare($sql_alum);
$stmt_alum->execute([$clave_grupo]);
$alumnos = $stmt_alum->fetchAll(PDO::FETCH_ASSOC);
$total_alumnos = count($alumnos);

$alumnos_json = json_encode($alumnos);
