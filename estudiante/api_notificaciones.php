<?php
/**
 * api_notificaciones.php
 * Endpoint asíncrono para el Centro de Notificaciones del Estudiante.
 * Devuelve en formato JSON los avisos de profesores, comunicados de coordinación y trámites.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../security.php';

// Validar que el usuario tenga sesión activa y sea ALUMNO
if (!isset($_SESSION['user_id']) || ($_SESSION['rol'] ?? '') !== 'ALUMNO') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$user_id_notif = (int)$_SESSION['user_id'];

// Obtener alumno_id
$stmt_al = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
$stmt_al->execute([$user_id_notif]);
$alumno_id_notif = $stmt_al->fetchColumn();

if (!$alumno_id_notif) {
    echo json_encode([
        'status' => 'success',
        'data' => [
            'profesores' => [],
            'institucionales' => [],
            'cant_prof_activas' => 0,
            'cant_generales_activas' => 0,
            'total_activas' => 0
        ]
    ]);
    exit;
}

// 1. Obtener grupos y materias activas del alumno
$sql_grupos = "
    SELECT g.nrc, m.materia_id, m.nombre
    FROM inscripciones i
    JOIN grupos g ON i.nrc = g.nrc
    JOIN materias m ON g.materia_id = m.materia_id
    JOIN ciclos c ON g.ciclo_id = c.ciclo_id
    WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO' AND g.estado = 'ACTIVO' AND c.activo = 1
";
$stmt_gn = $pdo->prepare($sql_grupos);
$stmt_gn->execute([$alumno_id_notif]);
$grupos_data = $stmt_gn->fetchAll(PDO::FETCH_ASSOC);

$mis_nrcs = [];
$mis_materias_ids = [];
$mis_idiomas = [];

foreach ($grupos_data as $row) {
    $mis_nrcs[] = (int)$row['nrc'];
    $mis_materias_ids[] = (int)$row['materia_id'];
    $idioma_puro = trim(preg_replace('/[0-9]+/', '', $row['nombre']));
    if (!empty($idioma_puro)) {
        $mis_idiomas[] = $idioma_puro;
    }
}
$mis_idiomas = array_values(array_unique($mis_idiomas));

// Función auxiliar de números romanos
if (!function_exists('cn_nivel_romano')) {
    function cn_nivel_romano(int $nivel) {
        $r = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X'];
        return $r[$nivel] ?? (string)$nivel;
    }
}

// 2. Avisos de profesores
$notif_avisos_profesor = [];
if (count($mis_nrcs) > 0) {
    $in_placeholders = implode(',', array_fill(0, count($mis_nrcs), '?'));
    $sql_ap = "
        SELECT a.aviso_id, a.autor_id, a.audiencia_ref AS nrc, a.tipo_comunicado AS tipo, 
               a.titulo, a.cuerpo AS descripcion, a.fecha_inicio, a.fecha_expiracion AS fecha_fin, 
               a.fecha_creacion, a.imagen, m.nombre as materia_nombre, m.nivel as materia_nivel, 
               g.clave_grupo, u.nombre as prof_nombre, u.apellido_paterno as prof_ap
        FROM avisos a
        JOIN grupos g ON a.audiencia_ref = g.nrc
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN usuarios u ON a.autor_id = u.usuario_id
        WHERE a.tipo_audiencia = 'GRUPO' AND a.audiencia_ref IN ($in_placeholders)
        ORDER BY COALESCE(a.fecha_inicio, a.fecha_creacion) DESC
        LIMIT 20
    ";
    $stmt_ap = $pdo->prepare($sql_ap);
    $stmt_ap->execute($mis_nrcs);
    $raw_ap = $stmt_ap->fetchAll(PDO::FETCH_ASSOC);

    $hoy = new DateTime();
    foreach ($raw_ap as $item) {
        $inicio = new DateTime($item['fecha_inicio'] ?: $item['fecha_creacion']);
        $fin = !empty($item['fecha_fin']) ? new DateTime($item['fecha_fin']) : null;

        if ($fin && $hoy > $fin) {
            $estatus = 'FINALIZADA';
        } elseif ($fin && $hoy >= $inicio && $hoy <= $fin) {
            $estatus = 'ACTIVA';
        } elseif (!$fin && $hoy >= $inicio) {
            $estatus = 'ACTIVA';
        } else {
            $diff = $hoy->diff($inicio);
            $estatus = ($diff->invert == 0 && $diff->days <= 3) ? 'PRÓXIMA' : 'PENDIENTE';
        }

        if ($estatus !== 'PENDIENTE') {
            $item['estatus'] = $estatus;
            $notif_avisos_profesor[] = $item;
        }
    }
}

// 3. Avisos Institucionales (Admin)
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
              WHERE autor_id IS NULL AND (" . implode(" OR ", $where_clauses) . ") 
              ORDER BY fecha_creacion DESC LIMIT 15";
$stmt_admin = $pdo->prepare($sql_admin);
$stmt_admin->execute($admin_params);
$raw_admin = $stmt_admin->fetchAll(PDO::FETCH_ASSOC);

// 4. Trámites de Control Escolar (Bajas y Altas recientes)
$notif_tramites = [];

// Bajas
$sql_tbajas = "
    SELECT sb.solicitud_id, sb.estatus, COALESCE(sb.fecha_respuesta, sb.fecha_solicitud) as fecha_evento, m.nombre, m.nivel, 'BAJA' as tipo_solicitud
    FROM solicitudes_bajas sb
    JOIN inscripciones i ON sb.inscripcion_id = i.inscripcion_id
    JOIN grupos g ON i.nrc = g.nrc
    JOIN materias m ON g.materia_id = m.materia_id
    WHERE i.alumno_id = ? 
      AND (sb.estatus = 'PENDIENTE' OR ((sb.estatus = 'APROBADA' OR sb.estatus = 'RECHAZADA') AND sb.fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 15 DAY)))
    ORDER BY fecha_evento DESC LIMIT 5
";
$st_tb = $pdo->prepare($sql_tbajas);
$st_tb->execute([$alumno_id_notif]);
$notif_tramites = array_merge($notif_tramites, $st_tb->fetchAll(PDO::FETCH_ASSOC));

// Altas
$sql_taltas = "
    SELECT sa.solicitud_id, sa.estatus, COALESCE(sa.fecha_respuesta, sa.fecha_solicitud) as fecha_evento, m.nombre, m.nivel, 'ALTA' as tipo_solicitud
    FROM solicitudes_altas sa
    JOIN inscripciones i ON sa.inscripcion_id = i.inscripcion_id
    JOIN grupos g ON i.nrc = g.nrc
    JOIN materias m ON g.materia_id = m.materia_id
    WHERE i.alumno_id = ? 
      AND (sa.estatus = 'PENDIENTE' OR ((sa.estatus = 'APROBADA' OR sa.estatus = 'RECHAZADA') AND sa.fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 15 DAY)))
    ORDER BY fecha_evento DESC LIMIT 5
";
$st_ta = $pdo->prepare($sql_taltas);
$st_ta->execute([$alumno_id_notif]);
$notif_tramites = array_merge($notif_tramites, $st_ta->fetchAll(PDO::FETCH_ASSOC));

// 5. Formatear lista de Mis Clases (Profesores)
$prof_list = [];
$cant_prof_activas = 0;
foreach ($notif_avisos_profesor as $ap) {
    if ($ap['estatus'] === 'ACTIVA' || $ap['estatus'] === 'PRÓXIMA') {
        $cant_prof_activas++;
    }
    
    $badgeBg = '#28a745';
    if ($ap['estatus'] === 'PRÓXIMA') $badgeBg = '#ffc107; color: #000;';
    elseif ($ap['estatus'] === 'FINALIZADA') $badgeBg = '#6c757d;';

    $icono = ($ap['tipo'] === 'TAREA') ? '📝' : '📢';
    $img_src = !empty($ap['imagen']) ? '../img/avisos/' . basename($ap['imagen']) : null;
    $fecha_ts = strtotime($ap['fecha_inicio'] ?: $ap['fecha_creacion']);

    $prof_list[] = [
        'aviso_id' => (int)$ap['aviso_id'],
        'key' => 'prof_' . ($ap['aviso_id'] ?? ($ap['nrc'] . '_' . $fecha_ts)),
        'tipo' => $ap['tipo'],
        'icono' => $icono,
        'badge_bg' => $badgeBg,
        'estatus' => $ap['estatus'],
        'titulo' => $ap['titulo'],
        'descripcion' => $ap['descripcion'],
        'desc_corta' => mb_substr(strip_tags($ap['descripcion']), 0, 85, 'UTF-8') . (mb_strlen($ap['descripcion']) > 85 ? '...' : ''),
        'materia_nombre' => $ap['materia_nombre'],
        'materia_nivel' => cn_nivel_romano((int)$ap['materia_nivel']),
        'profesor' => trim(($ap['prof_nombre'] ?? '') . ' ' . ($ap['prof_ap'] ?? '')),
        'imagen' => $img_src,
        'fecha_formateada' => date('d/m/Y', $fecha_ts),
        'fecha_completa' => date('d/m/Y h:i A', $fecha_ts),
        'fecha_expiracion' => !empty($ap['fecha_fin']) ? date('d/m/Y h:i A', strtotime($ap['fecha_fin'])) : null
    ];
}

// 6. Formatear lista de Institucionales (Trámites + Admin)
$inst_list = [];
foreach ($notif_tramites as $tr) {
    $fecha_ts = strtotime($tr['fecha_evento'] ?? 'now');
    $inst_list[] = [
        'tipo' => 'SOLICITUD',
        'key' => 'sol_' . $tr['tipo_solicitud'] . '_' . $tr['solicitud_id'],
        'id' => (int)$tr['solicitud_id'],
        'estatus' => $tr['estatus'],
        'tipo_solicitud' => $tr['tipo_solicitud'],
        'nombre' => $tr['nombre'],
        'nivel' => cn_nivel_romano((int)$tr['nivel']),
        'fecha_formateada' => date('d/m/Y h:i A', $fecha_ts),
        'timestamp' => $fecha_ts
    ];
}

foreach ($raw_admin as $aa) {
    $etiqueta = 'Global';
    if ($aa['tipo_audiencia'] === 'IDIOMA') $etiqueta = 'Idioma';
    elseif ($aa['tipo_audiencia'] === 'MATERIA') $etiqueta = 'Nivel';
    elseif ($aa['tipo_audiencia'] === 'GRUPO') $etiqueta = 'Clase';

    $img_src = !empty($aa['imagen']) ? '../img/avisos/' . basename($aa['imagen']) : null;
    $fecha_ts = strtotime($aa['fecha_creacion'] ?? 'now');

    $inst_list[] = [
        'tipo' => 'ADMIN',
        'key' => 'inst_' . $aa['aviso_id'],
        'id' => (int)$aa['aviso_id'],
        'titulo' => $aa['titulo'],
        'etiqueta' => $etiqueta,
        'cuerpo' => $aa['cuerpo'],
        'cuerpo_corto' => mb_substr(strip_tags($aa['cuerpo']), 0, 85, 'UTF-8') . (mb_strlen($aa['cuerpo']) > 85 ? '...' : ''),
        'imagen' => $img_src,
        'fecha_formateada' => date('d/m/Y', $fecha_ts),
        'fecha_completa' => date('d/m/Y h:i A', $fecha_ts),
        'fecha_expiracion' => !empty($aa['fecha_expiracion']) ? date('d/m/Y h:i A', strtotime($aa['fecha_expiracion'])) : null,
        'timestamp' => $fecha_ts
    ];
}

// Ordenar institucionales cronológicamente descendente
usort($inst_list, function($a, $b) { 
    return $b['timestamp'] <=> $a['timestamp']; 
});

echo json_encode([
    'status' => 'success',
    'data' => [
        'profesores' => $prof_list,
        'institucionales' => $inst_list,
        'cant_prof_activas' => $cant_prof_activas,
        'cant_generales_activas' => count($inst_list),
        'total_activas' => $cant_prof_activas + count($inst_list)
    ]
], JSON_UNESCAPED_UNICODE);
