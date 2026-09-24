<?php
session_start();
header('Content-Type: application/json');

require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

$action = $input['action'] ?? '';
$clave_grupo = $input['clave_grupo'] ?? '';

if (!$clave_grupo) {
    echo json_encode(['success' => false, 'error' => 'Falta clave del grupo']);
    exit;
}

if ($action === 'guardar_equipos') {
    $equipos = $input['equipos'] ?? [];
    
    try {
        $pdo->beginTransaction();

        // Eliminar equipos anteriores de este grupo
        $stmt_delete = $pdo->prepare("DELETE FROM equipos_grupo WHERE clave_grupo = ?");
        $stmt_delete->execute([$clave_grupo]);

        // Insertar los nuevos
        $stmt_equipo = $pdo->prepare("INSERT INTO equipos_grupo (clave_grupo, nombre_equipo, color_hex, fecha_creacion) VALUES (?, ?, ?, NOW())");
        $stmt_integrante = $pdo->prepare("INSERT INTO equipo_integrantes (equipo_id, inscripcion_id) VALUES (?, ?)");

        foreach ($equipos as $eq) {
            $nombre = $eq['nombre'] ?? '';
            $color = $eq['color'] ?? '#ffffff';
            $integrantes = $eq['integrantes'] ?? [];

            if (!$nombre) continue;

            $stmt_equipo->execute([$clave_grupo, $nombre, $color]);
            $equipo_id = $pdo->lastInsertId();

            foreach ($integrantes as $insc_id) {
                $stmt_integrante->execute([$equipo_id, $insc_id]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} elseif ($action === 'cargar_equipos') {
    try {
        $stmt_equipos = $pdo->prepare("SELECT equipo_id, nombre_equipo, color_hex FROM equipos_grupo WHERE clave_grupo = ? ORDER BY equipo_id ASC");
        $stmt_equipos->execute([$clave_grupo]);
        $equipos_db = $stmt_equipos->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        
        $stmt_integrantes = $pdo->prepare("SELECT i.inscripcion_id, u.nombre, u.apellido_paterno, u.apellido_materno, u.foto_perfil 
            FROM equipo_integrantes ei 
            JOIN inscripciones i ON ei.inscripcion_id = i.inscripcion_id
            JOIN alumnos a ON i.alumno_id = a.alumno_id
            JOIN usuarios u ON a.usuario_id = u.usuario_id
            WHERE ei.equipo_id = ?");

        foreach ($equipos_db as $eq) {
            $stmt_integrantes->execute([$eq['equipo_id']]);
            $integrantes = $stmt_integrantes->fetchAll(PDO::FETCH_ASSOC);

            $resultado[] = [
                'id' => $eq['equipo_id'],
                'nombre' => $eq['nombre_equipo'],
                'color' => $eq['color_hex'],
                'integrantes' => $integrantes
            ];
        }

        echo json_encode(['success' => true, 'equipos' => $resultado]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}
