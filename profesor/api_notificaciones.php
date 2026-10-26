<?php
/**
 * api_notificaciones.php
 * Endpoint asíncrono para el Centro de Notificaciones del Profesor.
 * Devuelve en formato JSON los comunicados emitidos por Administración / Coordinación dirigidos al docente.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../security.php';

// Validar que el usuario tenga sesión activa y sea PROFESOR
if (!isset($_SESSION['user_id']) || ($_SESSION['rol'] ?? '') !== 'PROFESOR') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$profesor_id = (int)$_SESSION['user_id'];

// Función para convertir niveles a números romanos en el API
if (!function_exists('cn_nivel_romano_prof')) {
    function cn_nivel_romano_prof(int|string|null $nivel): string {
        $n = (int)$nivel;
        $mapa = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X'];
        return $mapa[$n] ?? (string)$nivel;
    }
}

// 1. Obtener grupos, materias e idiomas asignados al profesor
$sql_grupos = "
    SELECT DISTINCT g.nrc, g.clave_grupo, m.materia_id, m.nombre AS materia_nombre, m.nivel
    FROM grupos g
    JOIN materias m ON g.materia_id = m.materia_id
    JOIN ciclos c ON g.ciclo_id = c.ciclo_id
    WHERE g.profesor_id = ? AND c.activo = 1 AND g.estado = 'ACTIVO'
";
$stmt_g = $pdo->prepare($sql_grupos);
$stmt_g->execute([$profesor_id]);
$mis_grupos = $stmt_g->fetchAll(PDO::FETCH_ASSOC);

$mis_nrcs = [];
$mis_materias_ids = [];
$mis_idiomas = [];

foreach ($mis_grupos as $row) {
    if (!empty($row['nrc'])) {
        $mis_nrcs[] = (int)$row['nrc'];
    }
    if (!empty($row['materia_id']) && !in_array((int)$row['materia_id'], $mis_materias_ids)) {
        $mis_materias_ids[] = (int)$row['materia_id'];
    }
    $idioma_puro = trim(preg_replace('/[0-9]+/', '', $row['materia_nombre']));
    if (!empty($idioma_puro) && !in_array($idioma_puro, $mis_idiomas)) {
        $mis_idiomas[] = $idioma_puro;
    }
}

// 2. Consulta de Avisos emitidos por Administración (autor_id IS NULL) dirigidos al profesor
$where_clauses = ["tipo_audiencia = 'GLOBAL'"];
$admin_params = [];

if (count($mis_idiomas) > 0) {
    $placeholders_idm = implode(',', array_fill(0, count($mis_idiomas), '?'));
    $where_clauses[] = "(tipo_audiencia = 'IDIOMA' AND audiencia_ref IN ($placeholders_idm))";
    $admin_params = array_merge($admin_params, $mis_idiomas);
}

if (count($mis_materias_ids) > 0) {
    $placeholders_mat = implode(',', array_fill(0, count($mis_materias_ids), '?'));
    $where_clauses[] = "(tipo_audiencia = 'MATERIA' AND audiencia_ref IN ($placeholders_mat))";
    $admin_params = array_merge($admin_params, $mis_materias_ids);
}

if (count($mis_nrcs) > 0) {
    $placeholders_nrc = implode(',', array_fill(0, count($mis_nrcs), '?'));
    $where_clauses[] = "(tipo_audiencia = 'GRUPO' AND audiencia_ref IN ($placeholders_nrc))";
    $admin_params = array_merge($admin_params, $mis_nrcs);
}

$sql_admin = "SELECT aviso_id, titulo, cuerpo, tipo_audiencia, audiencia_ref, imagen, fecha_creacion, fecha_expiracion 
              FROM avisos 
              WHERE autor_id IS NULL 
                AND rol_destinatario IN ('TODOS', 'PROFESOR')
                AND (" . implode(" OR ", $where_clauses) . ") 
              ORDER BY fecha_creacion DESC LIMIT 20";
$stmt_admin = $pdo->prepare($sql_admin);
$stmt_admin->execute($admin_params);
$raw_admin = $stmt_admin->fetchAll(PDO::FETCH_ASSOC);

// 3. Formatear notificaciones institucionales
$ahora_dt = new DateTime();
$notificaciones = [];
$total_activas = 0;

foreach ($raw_admin as $aa) {
    $etiqueta = 'Institucional';
    $icono = 'fas fa-university';
    if ($aa['tipo_audiencia'] === 'IDIOMA') {
        $etiqueta = 'Academia ' . htmlspecialchars($aa['audiencia_ref']);
        $icono = 'fas fa-language';
    } elseif ($aa['tipo_audiencia'] === 'MATERIA') {
        $etiqueta = 'Materia / Nivel';
        $icono = 'fas fa-book';
    } elseif ($aa['tipo_audiencia'] === 'GRUPO') {
        $etiqueta = 'Clase / NRC ' . htmlspecialchars($aa['audiencia_ref']);
        $icono = 'fas fa-chalkboard';
    }

    $img_src = !empty($aa['imagen']) ? '../img/avisos/' . basename($aa['imagen']) : null;
    $fecha_ts = strtotime($aa['fecha_creacion'] ?? 'now');
    
    // Vigencia
    $expirado = false;
    if (!empty($aa['fecha_expiracion'])) {
        $exp_dt = new DateTime($aa['fecha_expiracion']);
        if ($ahora_dt > $exp_dt) {
            $expirado = true;
        }
    }

    if (!$expirado) {
        $total_activas++;
    }

    $notificaciones[] = [
        'id' => (int)$aa['aviso_id'],
        'key' => 'prof_admin_' . $aa['aviso_id'],
        'titulo' => $aa['titulo'],
        'etiqueta' => $etiqueta,
        'tipo_audiencia' => $aa['tipo_audiencia'],
        'icono' => $icono,
        'cuerpo' => $aa['cuerpo'],
        'cuerpo_corto' => mb_substr(strip_tags($aa['cuerpo']), 0, 95, 'UTF-8') . (mb_strlen($aa['cuerpo']) > 95 ? '...' : ''),
        'imagen' => $img_src,
        'expirado' => $expirado,
        'fecha_formateada' => date('d/m/Y', $fecha_ts),
        'fecha_completa' => date('d/m/Y h:i A', $fecha_ts),
        'fecha_expiracion' => !empty($aa['fecha_expiracion']) ? date('d/m/Y h:i A', strtotime($aa['fecha_expiracion'])) : null,
        'timestamp' => $fecha_ts
    ];
}

// Ordenar cronológicamente descendente
usort($notificaciones, function($a, $b) { 
    return $b['timestamp'] <=> $a['timestamp']; 
});

echo json_encode([
    'status' => 'success',
    'data' => [
        'notificaciones' => $notificaciones,
        'total_activas' => $total_activas
    ]
], JSON_UNESCAPED_UNICODE);
