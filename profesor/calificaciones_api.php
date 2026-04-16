<?php
session_start();
require '../db.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

// Seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR' || !isset($input['action'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']); exit;
}

if ($input['action'] === 'save_single') {
    $insc_id = $input['inscripcion_id'];
    $tipo_examen = $input['tipo_examen'];
    $puntaje = $input['puntaje']; // Puede venir vacío si el profe borra la calificación

    try {
        // Verificar si ya existe esa calificación
        $check = $pdo->prepare("SELECT calificacion_id FROM calificaciones WHERE inscripcion_id = ? AND tipo_examen = ?");
        $check->execute([$insc_id, $tipo_examen]);
        $id_existente = $check->fetchColumn();

        if (trim($puntaje) === '') {
            // Si lo dejó en blanco, borramos el registro
            if ($id_existente) {
                $pdo->prepare("DELETE FROM calificaciones WHERE calificacion_id = ?")->execute([$id_existente]);
            }
        } else {
            // Si puso un número, actualizamos o insertamos
            $puntaje_val = floatval($puntaje);
            if ($id_existente) {
                $pdo->prepare("UPDATE calificaciones SET puntaje = ? WHERE calificacion_id = ?")->execute([$puntaje_val, $id_existente]);
            } else {
                $pdo->prepare("INSERT INTO calificaciones (inscripcion_id, tipo_examen, puntaje) VALUES (?, ?, ?)")->execute([$insc_id, $tipo_examen, $puntaje_val]);
            }
        }
        echo json_encode(['success' => true]);
    } catch(Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>