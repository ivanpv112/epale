<?php
/**
 * avisos_api.php
 * API centralizada para la gestión de avisos y tareas del profesor.
 * Conectada a la tabla global 'avisos' con tipo_audiencia = 'GRUPO'.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require '../db.php';
require_once '../security.php';

date_default_timezone_set('America/Mexico_City'); 

// Capa estricta de seguridad
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']); 
    exit;
}

requerir_rol('PROFESOR');

// Si la petición viene en JSON crudo, mezclarlo en $_POST
$input = json_decode(file_get_contents('php://input'), true);
if (is_array($input)) { 
    $_POST = array_merge($_POST, $input); 
}

// Validación estricta del Token CSRF
validar_csrf_estricto('POST');

$profesor_id = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$ahora = date('Y-m-d H:i:s'); 

function calcularEstatus(string $fecha_inicio, ?string $fecha_fin = null): string {
    $hoy = new DateTime();
    $inicio = new DateTime($fecha_inicio);
    $fin = !empty($fecha_fin) ? new DateTime($fecha_fin) : null;

    if ($fin && $hoy > $fin) return 'FINALIZADA';
    if ($fin && $hoy >= $inicio && $hoy <= $fin) return 'ACTIVA';
    if (!$fin && $hoy >= $inicio) return 'ACTIVA';

    $diff = $hoy->diff($inicio);
    $dias_faltantes = $diff->days;
    $invert = $diff->invert;

    if ($invert == 0 && $dias_faltantes <= 3) return 'PRÓXIMA';
    return 'PENDIENTE';
}

if ($action === 'get_avisos') {
    $stmt = $pdo->prepare("
        SELECT a.aviso_id, a.autor_id AS profesor_id, a.audiencia_ref AS nrc, 
               a.tipo_comunicado AS tipo, a.titulo, a.cuerpo AS descripcion, 
               a.fecha_inicio, a.fecha_expiracion AS fecha_fin, a.fecha_creacion, 
               a.imagen, g.clave_grupo, m.nombre AS materia_nombre, m.nivel AS materia_nivel
        FROM avisos a 
        JOIN grupos g ON CAST(a.audiencia_ref AS UNSIGNED) = g.nrc
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        WHERE a.autor_id = ? AND a.tipo_audiencia = 'GRUPO' AND c.activo = 1
        ORDER BY COALESCE(a.fecha_inicio, a.fecha_creacion) DESC
    ");
    $stmt->execute([$profesor_id]);
    $avisos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($avisos as &$a) {
        $fecha_inicio_val = $a['fecha_inicio'] ?: $a['fecha_creacion'];
        $a['estatus'] = calcularEstatus($fecha_inicio_val, $a['fecha_fin']);
        $a['fecha_inicio_input'] = date('Y-m-d\TH:i', strtotime($fecha_inicio_val));
        $a['fecha_fin_input'] = $a['fecha_fin'] ? date('Y-m-d\TH:i', strtotime($a['fecha_fin'])) : '';
        $a['materia_nivel'] = (int)($a['materia_nivel'] ?? 1);
    }
    unset($a);

    echo json_encode($avisos); 
    exit;
}

elseif ($action === 'get_grupos_activos') {
    $stmt = $pdo->prepare("
        SELECT g.nrc, g.clave_grupo, m.nombre, m.nivel,
               MAX(h.modalidad) AS modalidad
        FROM grupos g 
        JOIN materias m ON g.materia_id = m.materia_id 
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        LEFT JOIN horarios h ON g.nrc = h.nrc
        WHERE g.profesor_id = ? AND g.estado = 'ACTIVO' AND c.activo = 1
        GROUP BY g.nrc, g.clave_grupo, m.nombre, m.nivel
        ORDER BY m.nombre ASC, m.nivel ASC, g.nrc ASC
    ");
    $stmt->execute([$profesor_id]);
    $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($grupos); 
    exit;
}

elseif ($action === 'save_aviso') {
    $aviso_id = !empty($_POST['aviso_id']) ? (int)$_POST['aviso_id'] : null;
    $nrc = trim($_POST['nrc'] ?? '');
    $tipo = in_array(strtoupper($_POST['tipo'] ?? ''), ['AVISO', 'ASIGNACION']) ? strtoupper($_POST['tipo']) : 'AVISO';
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_inicio = !empty($_POST['fecha_inicio']) ? date('Y-m-d H:i:s', strtotime($_POST['fecha_inicio'])) : $ahora;
    $sin_expiracion = isset($_POST['sin_expiracion']) && ($_POST['sin_expiracion'] === '1' || $_POST['sin_expiracion'] === 'true' || $_POST['sin_expiracion'] === 'on');
    $fecha_fin = (!$sin_expiracion && !empty($_POST['fecha_fin'])) ? date('Y-m-d H:i:s', strtotime($_POST['fecha_fin'])) : null;
    $eliminar_imagen = isset($_POST['eliminar_imagen']) && ($_POST['eliminar_imagen'] === '1' || $_POST['eliminar_imagen'] === 'true' || $_POST['eliminar_imagen'] === 'on');

    if (empty($nrc) || empty($titulo) || empty($descripcion)) {
        echo json_encode(['status' => 'error', 'message' => 'Por favor completa todos los campos obligatorios.']);
        exit;
    }

    // Validar que el NRC pertenezca a un grupo activo del profesor
    $stmt_check = $pdo->prepare("
        SELECT COUNT(*) FROM grupos g 
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id 
        WHERE g.nrc = ? AND g.profesor_id = ? AND g.estado = 'ACTIVO' AND c.activo = 1
    ");
    $stmt_check->execute([$nrc, $profesor_id]);
    if ((int)$stmt_check->fetchColumn() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'No tienes permisos sobre el grupo seleccionado.']);
        exit;
    }

    // Procesamiento seguro de imagen si fue adjuntada
    $imagen_subida = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
        $new_file_name = secure_image_upload($_FILES['imagen'], '../img/avisos/');
        if ($new_file_name) {
            $imagen_subida = 'img/avisos/' . $new_file_name;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'La imagen seleccionada no es válida o excede el límite permitido (JPG, PNG, WEBP).']);
            exit;
        }
    }

    if ($aviso_id === null) {
        // Obtener el ciclo_id asociado a este grupo
        $st_cg = $pdo->prepare("SELECT ciclo_id FROM grupos WHERE nrc = ? LIMIT 1");
        $st_cg->execute([$nrc]);
        $grupo_ciclo_id = (int)$st_cg->fetchColumn();
        if ($grupo_ciclo_id <= 0) {
            $st_act = $pdo->query("SELECT ciclo_id FROM ciclos WHERE activo = 1 LIMIT 1");
            $grupo_ciclo_id = $st_act ? (int)$st_act->fetchColumn() : null;
        }

        // Crear nuevo aviso
        $stmt = $pdo->prepare("
            INSERT INTO avisos (titulo, cuerpo, tipo_audiencia, audiencia_ref, autor_id, ciclo_id, tipo_comunicado, fecha_inicio, fecha_expiracion, imagen) 
            VALUES (?, ?, 'GRUPO', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$titulo, $descripcion, $nrc, $profesor_id, $grupo_ciclo_id, $tipo, $fecha_inicio, $fecha_fin, $imagen_subida]);
        echo json_encode(['status' => 'success', 'message' => 'El comunicado ha sido creado exitosamente.']);
        exit;
    } else {
        // Modificar aviso existente
        $stmt_old = $pdo->prepare("SELECT imagen FROM avisos WHERE aviso_id = ? AND autor_id = ?");
        $stmt_old->execute([$aviso_id, $profesor_id]);
        $old_img = $stmt_old->fetchColumn();

        $final_imagen = $old_img;

        if ($imagen_subida !== null) {
            $final_imagen = $imagen_subida;
            if ($old_img && file_exists('../' . $old_img)) {
                @unlink('../' . $old_img);
            }
        } elseif ($eliminar_imagen) {
            $final_imagen = null;
            if ($old_img && file_exists('../' . $old_img)) {
                @unlink('../' . $old_img);
            }
        }

        $stmt = $pdo->prepare("
            UPDATE avisos 
            SET audiencia_ref = ?, tipo_comunicado = ?, titulo = ?, cuerpo = ?, fecha_inicio = ?, fecha_expiracion = ?, imagen = ?
            WHERE aviso_id = ? AND autor_id = ?
        ");
        $stmt->execute([$nrc, $tipo, $titulo, $descripcion, $fecha_inicio, $fecha_fin, $final_imagen, $aviso_id, $profesor_id]);
        echo json_encode(['status' => 'success', 'message' => 'El comunicado ha sido actualizado correctamente.']);
        exit;
    }
}

elseif ($action === 'publicar_manual') {
    $aviso_id = (int)($_POST['aviso_id'] ?? 0);
    $stmt = $pdo->prepare("
        UPDATE avisos 
        SET fecha_inicio = ?,
            fecha_expiracion = CASE WHEN fecha_expiracion IS NOT NULL AND fecha_expiracion <= ? THEN NULL ELSE fecha_expiracion END
        WHERE aviso_id = ? AND autor_id = ?
    ");
    $stmt->execute([$ahora, $ahora, $aviso_id, $profesor_id]);
    echo json_encode(['status' => 'success', 'message' => 'El comunicado se ha publicado de inmediato.']); 
    exit;
}

elseif ($action === 'finalizar_manual') {
    $aviso_id = (int)($_POST['aviso_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE avisos SET fecha_expiracion = ? WHERE aviso_id = ? AND autor_id = ?");
    $stmt->execute([$ahora, $aviso_id, $profesor_id]);
    echo json_encode(['status' => 'success', 'message' => 'El comunicado ha sido finalizado.']); 
    exit;
}

elseif ($action === 'delete_aviso') {
    $aviso_id = (int)($_POST['aviso_id'] ?? 0);
    $stmt_old = $pdo->prepare("SELECT imagen FROM avisos WHERE aviso_id = ? AND autor_id = ?");
    $stmt_old->execute([$aviso_id, $profesor_id]);
    $old_img = $stmt_old->fetchColumn();
    if ($old_img && file_exists('../' . $old_img)) {
        @unlink('../' . $old_img);
    }

    $stmt = $pdo->prepare("DELETE FROM avisos WHERE aviso_id = ? AND autor_id = ?");
    $stmt->execute([$aviso_id, $profesor_id]);
    echo json_encode(['status' => 'success', 'message' => 'El comunicado ha sido eliminado.']); 
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Acción no reconocida.']);
exit;