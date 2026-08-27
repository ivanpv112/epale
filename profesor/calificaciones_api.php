<?php
session_start();
require '../db.php';

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

// 1. Seguridad de Sesión
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR' || !isset($input['action'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']); exit;
}

// 2. Validación estricta del Token CSRF
if (empty($_SESSION['csrf_token']) || empty($input['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido (CSRF). Por favor, recarga la página.']); exit;
}

if ($input['action'] === 'save_single') {
    $insc_id = $input['inscripcion_id'];
    $tipo_examen = $input['tipo_examen']; 
    $puntaje = $input['puntaje']; 
    $profesor_id = $_SESSION['user_id'];

    $stmt_val = $pdo->prepare("
        SELECT g.estado, g.edicion_total
        FROM inscripciones i 
        JOIN grupos g ON i.nrc = g.nrc 
        WHERE i.inscripcion_id = ? AND g.profesor_id = ?
    ");
    $stmt_val->execute([$insc_id, $profesor_id]);
    $grupo = $stmt_val->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        echo json_encode(['success' => false, 'error' => 'Acceso denegado: El alumno no pertenece a tus grupos.']); exit;
    }
    if ($grupo['estado'] === 'CERRADO') {
        echo json_encode(['success' => false, 'error' => 'Operación rechazada: Grupo cerrado.']); exit;
    }

    if (isset($grupo['edicion_total']) && $grupo['edicion_total'] == 0) {
        $tipo_upper = strtoupper($tipo_examen);
        $permitidas = ['QO', 'WRITING', 'PARTICIPACION'];
        $es_valida = false;
        
        foreach ($permitidas as $palabra) {
            if (strpos($tipo_upper, $palabra) !== false) {
                $es_valida = true; break;
            }
        }
        
        if (!$es_valida) {
            echo json_encode(['success' => false, 'error' => 'No autorizado: Actividad restringida a administración.']); exit;
        }
    }

    try {
        $check = $pdo->prepare("SELECT calificacion_id FROM calificaciones WHERE inscripcion_id = ? AND tipo_examen = ?");
        $check->execute([$insc_id, $tipo_examen]);
        $id_existente = $check->fetchColumn();

        if (trim($puntaje) === '') {
            if ($id_existente) {
                $pdo->prepare("DELETE FROM calificaciones WHERE calificacion_id = ?")->execute([$id_existente]);
            }
        } else {
            $puntaje_val = floatval($puntaje);
            if ($id_existente) {
                $pdo->prepare("UPDATE calificaciones SET puntaje = ? WHERE calificacion_id = ?")->execute([$puntaje_val, $id_existente]);
            } else {
                $pdo->prepare("INSERT INTO calificaciones (inscripcion_id, tipo_examen, puntaje) VALUES (?, ?, ?)")->execute([$insc_id, $tipo_examen, $puntaje_val]);
            }
        }
        echo json_encode(['success' => true]);
        
    } catch(PDOException $e) {
        // 3. Fuga de Datos Resuelta: El error real va al log del servidor, el usuario ve un mensaje genérico
        error_log("Error PDO en calificaciones_api: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Ocurrió un error interno al guardar en la base de datos.']);
    } catch(Exception $e) {
        // Excepciones de negocio (las que lanzamos nosotros) sí se muestran
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
