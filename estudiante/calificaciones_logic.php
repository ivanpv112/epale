<?php
/**
 * calificaciones_logic.php
 * Controlador y lógica de datos para la vista de calificaciones del estudiante.
 */

// Si se intenta acceder directamente al archivo de lógica por URL, redirigir a calificaciones
if (basename($_SERVER['PHP_SELF']) === 'calificaciones_logic.php') {
    header('Location: calificaciones');
    exit;
}

session_start();
require_once '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

requerir_rol('ALUMNO');

// Obtener o asegurar el alumno_id correspondiente al usuario en sesión
$stmt_al = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
$stmt_al->execute([$_SESSION['user_id']]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);

if (!$alumno || empty($alumno['alumno_id'])) {
    $ins_al = $pdo->prepare("INSERT INTO alumnos (usuario_id, carrera) VALUES (?, '')");
    $ins_al->execute([$_SESSION['user_id']]);
    $alumno_id = (int)$pdo->lastInsertId();
} else {
    $alumno_id = (int)$alumno['alumno_id'];
}

if (!function_exists('formatear_dias_es')) {
    function formatear_dias_es(?string $cadena): string {
        if (!$cadena) return '';
        $s = strtoupper(trim($cadena));

        $nombres = [
            'L' => 'Lunes',
            'M' => 'Martes',
            'I' => 'Miércoles',
            'X' => 'Miércoles',
            'J' => 'Jueves',
            'V' => 'Viernes',
            'S' => 'Sábado',
            'D' => 'Domingo'
        ];

        if (preg_match('/^([A-Z])\s*-\s*([A-Z])$/', $s, $m)) {
            $d1 = $nombres[$m[1]] ?? $m[1];
            $d2 = $nombres[$m[2]] ?? $m[2];
            return $d1 . ' - ' . $d2;
        }

        if (strpos($s, 'LUN') !== false || strpos($s, 'MAR') !== false || strpos($s, 'MIE') !== false || strpos($s, 'JUE') !== false || strpos($s, 'VIE') !== false) {
            return $cadena;
        }

        $chars = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
        $res = [];
        foreach ($chars as $ch) {
            if (isset($nombres[$ch])) {
                $res[] = $nombres[$ch];
            }
        }
        return !empty($res) ? implode(' - ', $res) : $cadena;
    }
}

if (!function_exists('nivel_a_romano')) {
    function nivel_a_romano(int $nivel) {
        $romanos = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X'];
        return isset($romanos[$nivel]) ? $romanos[$nivel] : (string)$nivel;
    }
}

$mensaje = ''; 
$tipo_mensaje = '';

$bajas_habilitadas = config_get('bajas_habilitadas', true);

// =========================================================================
// PROCESAMIENTO DE ACCIONES POST (Solicitudes de Baja con Validación IDOR)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['action']) && $_POST['action'] === 'solicitar_baja') {
        if (!$bajas_habilitadas) {
            die("Error de Seguridad: El periodo para solicitar bajas se encuentra cerrado.");
        }
        $insc_baja = (int)($_POST['inscripcion_id'] ?? 0);
        $motivo = strip_tags(trim($_POST['motivo'] ?? ''));
        $descripcion = substr(strip_tags(trim($_POST['descripcion'] ?? '')), 0, 250);
        
        if ($insc_baja > 0 && !empty($motivo)) {
            // Protección IDOR: Validar que la inscripción realmente pertenezca al alumno autenticado
            $chk_propiedad = $pdo->prepare("SELECT COUNT(*) FROM inscripciones WHERE inscripcion_id = ? AND alumno_id = ? AND estatus = 'INSCRITO'");
            $chk_propiedad->execute([$insc_baja, $alumno_id]);
            
            if ($chk_propiedad->fetchColumn() > 0) {
                // Verificar si ya tiene una solicitud pendiente para esta inscripción
                $chk_pendiente = $pdo->prepare("SELECT COUNT(*) FROM solicitudes_bajas WHERE inscripcion_id = ? AND estatus = 'PENDIENTE'");
                $chk_pendiente->execute([$insc_baja]);
                
                if ($chk_pendiente->fetchColumn() == 0) {
                    $stmt_ins = $pdo->prepare("INSERT INTO solicitudes_bajas (inscripcion_id, motivo, descripcion, estatus, fecha_solicitud) VALUES (?, ?, ?, 'PENDIENTE', NOW())");
                    $stmt_ins->execute([$insc_baja, $motivo, $descripcion]);
                    $mensaje = "Tu solicitud de baja ha sido enviada a Administración."; 
                    $tipo_mensaje = "success";
                } else {
                    $mensaje = "Ya cuentas con una solicitud de baja pendiente para esta materia.";
                    $tipo_mensaje = "error";
                }
            } else {
                $mensaje = "Operación no autorizada: La inscripción no pertenece a tu cuenta o no está activa.";
                $tipo_mensaje = "error";
            }
        } else {
            $mensaje = "Por favor selecciona un motivo válido para la solicitud.";
            $tipo_mensaje = "error";
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'cancelar_baja') {
        $solicitud_id = (int)($_POST['solicitud_id'] ?? 0);
        if ($solicitud_id > 0) {
            // Protección IDOR: Validar que la solicitud pertenezca al alumno autenticado mediante JOIN con inscripciones
            $chk_solicitud = $pdo->prepare("SELECT sb.solicitud_id 
                                            FROM solicitudes_bajas sb
                                            JOIN inscripciones i ON sb.inscripcion_id = i.inscripcion_id
                                            WHERE sb.solicitud_id = ? AND i.alumno_id = ? AND sb.estatus = 'PENDIENTE'");
            $chk_solicitud->execute([$solicitud_id, $alumno_id]);
            
            if ($chk_solicitud->fetchColumn()) {
                $pdo->prepare("UPDATE solicitudes_bajas SET estatus = 'CANCELADA', fecha_respuesta = NOW() WHERE solicitud_id = ?")->execute([$solicitud_id]);
                $mensaje = "Has retirado tu solicitud de baja exitosamente."; 
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Operación no autorizada: No se encontró una solicitud pendiente válida para cancelar.";
                $tipo_mensaje = "error";
            }
        }
    }
}

// =========================================================================
// CONSULTA DE DATOS (Materias, Criterios y Calificaciones)
// =========================================================================

// Obtener materias activas en curso
$sql_materias_activas = "SELECT i.inscripcion_id, m.nombre AS materia, m.nivel, c.nombre AS ciclo, g.nrc
                         FROM inscripciones i
                         JOIN grupos g ON i.nrc = g.nrc
                         JOIN materias m ON g.materia_id = m.materia_id
                         JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                         WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO' AND g.estado = 'ACTIVO'
                         ORDER BY m.nombre ASC, m.nivel ASC";
$stmt_mat = $pdo->prepare($sql_materias_activas); 
$stmt_mat->execute([$alumno_id]);
$materias_activas = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

// Materia seleccionada actualmente (por parámetro GET o primera activa)
$ins_activa = isset($_GET['ins']) ? (int)$_GET['ins'] : (count($materias_activas) > 0 ? (int)$materias_activas[0]['inscripcion_id'] : null);

$materia_actual = null;
if ($ins_activa) {
    // La consulta exige i.alumno_id = ?, previniendo visualización no autorizada, e incorpora detalles de sesiones/horarios
    $stmt_actual = $pdo->prepare("SELECT i.inscripcion_id, m.materia_id, m.clave AS materia_codigo, m.nombre AS materia, m.nivel, c.nombre AS ciclo, g.estado as grupo_estado, g.nrc, g.clave_grupo,
                                         u.nombre AS prof_nombre, u.apellido_paterno AS prof_ap_pat, u.apellido_materno AS prof_ap_mat,
                                         MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g_all.nrc END) AS nrc_p,
                                         MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g_all.nrc END) AS nrc_v,
                                         MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.aula END) AS aula_p,
                                         MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.aula END) AS aula_v,
                                         MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.dias_patron END) AS dias_p,
                                         MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.dias_patron END) AS dias_v,
                                         MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_inicio END) AS inicio_p,
                                         MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_inicio END) AS inicio_v,
                                         MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_fin END) AS fin_p,
                                         MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_fin END) AS fin_v
                                  FROM inscripciones i
                                  JOIN grupos g ON i.nrc = g.nrc
                                  JOIN grupos g_all ON (
                                      (g.clave_grupo IS NOT NULL AND g.clave_grupo != '' AND g_all.clave_grupo = g.clave_grupo AND g_all.materia_id = g.materia_id AND g_all.ciclo_id = g.ciclo_id)
                                      OR (g_all.nrc = g.nrc)
                                  )
                                  JOIN materias m ON g.materia_id = m.materia_id
                                  JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                                  JOIN usuarios u ON g.profesor_id = u.usuario_id
                                  LEFT JOIN horarios h ON g_all.nrc = h.nrc
                                  WHERE i.inscripcion_id = ? AND i.alumno_id = ?
                                  GROUP BY i.inscripcion_id, m.materia_id, m.clave, m.nombre, m.nivel, c.nombre, g.estado, g.nrc, g.clave_grupo, u.nombre, u.apellido_paterno, u.apellido_materno");
    $stmt_actual->execute([$ins_activa, $alumno_id]);
    $materia_actual = $stmt_actual->fetch(PDO::FETCH_ASSOC);
}

// Historial y estado de solicitudes de baja para la materia actual
$historial_solicitudes = [];
$solicitud_pendiente = null;
$hay_rechazada_reciente = false;

if ($ins_activa && $materia_actual && $materia_actual['grupo_estado'] == 'ACTIVO') {
    $stmt_sol = $pdo->prepare("SELECT * FROM solicitudes_bajas WHERE inscripcion_id = ? ORDER BY fecha_solicitud DESC");
    $stmt_sol->execute([$ins_activa]);
    $historial_solicitudes = $stmt_sol->fetchAll(PDO::FETCH_ASSOC);

    if (count($historial_solicitudes) > 0) {
        $ultima = $historial_solicitudes[0];
        if ($ultima['estatus'] === 'PENDIENTE') { 
            $solicitud_pendiente = $ultima; 
        } elseif ($ultima['estatus'] === 'RECHAZADA') { 
            $hay_rechazada_reciente = true; 
        }
    }
}

// Criterios de evaluación y cálculo de puntos totales posibles
$evaluacion = []; 
$puntos_totales_posibles = 0;
if ($materia_actual) {
    $stmt_crit = $pdo->prepare("SELECT * FROM criterios_evaluacion WHERE materia_id = ? ORDER BY categoria ASC");
    $stmt_crit->execute([$materia_actual['materia_id']]);
    $criterios_bd = $stmt_crit->fetchAll(PDO::FETCH_ASSOC);
    foreach ($criterios_bd as $crit) {
        $cat = $crit['categoria'];
        if (!isset($evaluacion[$cat])) { 
            $evaluacion[$cat] = ['icono' => $crit['icono'], 'color' => $crit['color'], 'items' => []]; 
        }
        $evaluacion[$cat]['items'][$crit['codigo_examen']] = ['nombre' => $crit['nombre_examen'], 'max' => floatval($crit['puntos_maximos'])];
        $puntos_totales_posibles += floatval($crit['puntos_maximos']);
    }
}

// Calificaciones registradas y puntaje acumulado
$calificaciones_bd = []; 
$puntaje_total_acumulado = 0;
if ($ins_activa) {
    $stmt_cal = $pdo->prepare("SELECT tipo_examen, puntaje FROM calificaciones WHERE inscripcion_id = ?");
    $stmt_cal->execute([$ins_activa]);
    while ($row = $stmt_cal->fetch(PDO::FETCH_ASSOC)) {
        $calificaciones_bd[$row['tipo_examen']] = $row['puntaje'];
        if ($row['puntaje'] !== null) $puntaje_total_acumulado += floatval($row['puntaje']);
    }
}
$porcentaje_total = $puntos_totales_posibles > 0 ? ($puntaje_total_acumulado / $puntos_totales_posibles) * 100 : 0;

if (!function_exists('format_score')) {
    /**
     * @param float|int|string $num
     * @return float|int
     */
    function format_score($num) { 
        return floatval($num) == intval($num) ? intval($num) : floatval($num); 
    }
}
