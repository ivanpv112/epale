<?php
/**
 * detalle_grupo_logic.php
 * Lógica de negocio y procesamiento de datos para profesor/detalle_grupo.php
 */

session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

requerir_rol('PROFESOR');

$profesor_id = $_SESSION['user_id'];
$clave_grupo = $_GET['clave'] ?? '';

if (!$clave_grupo) {
    header("Location: mis_grupos");
    exit;
}

$stmt_info = $pdo->prepare("SELECT m.materia_id, m.clave AS clave_materia, m.nombre AS materia, m.nivel, c.nombre AS ciclo, c.activo AS ciclo_activo, g.edicion_total, g.estado FROM grupos g JOIN materias m ON g.materia_id = m.materia_id JOIN ciclos c ON c.ciclo_id = g.ciclo_id WHERE g.clave_grupo = ? AND g.profesor_id = ? LIMIT 1");
$stmt_info->execute([$clave_grupo, $profesor_id]);
$info_grupo = $stmt_info->fetch(PDO::FETCH_ASSOC);
if (!$info_grupo) {
    header("Location: mis_grupos");
    exit;
}

$edicion_total = (int)$info_grupo['edicion_total'];

// REQUERIMIENTO: Detectar si el ciclo es pasado (o para pruebas, tratar ciclos como pasados)
// Un ciclo es pasado si c.activo == 0, o si el grupo está CERRADO.
// Para verificar bien su funcionamiento con el ciclo actual, habilitamos la detección de solo lectura.
$ciclo_inactivo = ((int)($info_grupo['ciclo_activo'] ?? 0) === 0);
$grupo_cerrado = ($info_grupo['estado'] === 'CERRADO');

// Si el usuario indicó simulación/vista de pasado vía parámetro o si el ciclo no es el activo:
$es_ciclo_pasado = $ciclo_inactivo || isset($_GET['historico']) || isset($_GET['pasado']);
// Modo solo lectura si el grupo está cerrado o si pertenece a un ciclo pasado:
$modo_solo_lectura = $grupo_cerrado || $es_ciclo_pasado;
$grupo_cerrado = $modo_solo_lectura; // Protege y bloquea todas las celdas automáticamente

$materia_id = $info_grupo['materia_id'];

// Consultar detalles completos de sesiones y modalidades
$stmt_det = $pdo->prepare("SELECT 
    MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END) AS nrc_p,
    MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END) AS nrc_v,
    MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.aula END) AS aula_p,
    MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.aula END) AS aula_v,
    MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.dias_patron END) AS dias_p,
    MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.dias_patron END) AS dias_v,
    MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_inicio END) AS inicio_p,
    MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_inicio END) AS inicio_v,
    MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_fin END) AS fin_p,
    MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_fin END) AS fin_v
FROM grupos g 
LEFT JOIN horarios h ON g.nrc = h.nrc 
WHERE g.clave_grupo = ? AND g.profesor_id = ?");
$stmt_det->execute([$clave_grupo, $profesor_id]);
$detalles_grupo = $stmt_det->fetch(PDO::FETCH_ASSOC) ?: [];

if (!function_exists('nivel_a_romano')) {
    function nivel_a_romano(int|string|null $nivel): string {
        $n = (int)$nivel;
        $mapa = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X'];
        return $mapa[$n] ?? (string)$nivel;
    }
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

        $partes = preg_split('/[\s,\/]+/', $s);
        $res = [];
        foreach ($partes as $p) {
            if (isset($nombres[$p])) {
                $res[] = substr($nombres[$p], 0, 3);
            } elseif (!empty($p)) {
                $res[] = $p;
            }
        }
        return !empty($res) ? implode(', ', $res) : $s;
    }
}

if (!function_exists('abreviar_criterio_movil')) {
    function abreviar_criterio_movil(string $nombre, string $codigo): string {
        $nombre_clean = trim($nombre);
        $cod_upper = strtoupper(trim($codigo));
        
        // 1. Exámenes orales: E.O1, E.O2, etc.
        if (preg_match('/(?:quiz|examen)?\s*oral\s*(\d+)/i', $nombre_clean, $m)) {
            return 'E.O' . $m[1];
        }
        if (preg_match('/^QO(\d+)$/', $cod_upper, $m)) {
            return 'E.O' . $m[1];
        }

        // 2. Exámenes / Quizzes estándar: E1, E2, E3...
        if (preg_match('/(?:quiz|examen)\s*(\d+)/i', $nombre_clean, $m)) {
            return 'E' . $m[1];
        }
        if (preg_match('/^Q(\d+)$/', $cod_upper, $m)) {
            return 'E' . $m[1];
        }

        // 3. Proyecto Escrito / Writing Project: P.E
        if (stripos($nombre_clean, 'proyecto') !== false || stripos($nombre_clean, 'writing') !== false || $cod_upper === 'WRITING') {
            return 'P.E';
        }

        // 4. Participación: Part.
        if (stripos($nombre_clean, 'participaci') !== false || $cod_upper === 'PARTICIPACION') {
            return 'Part.';
        }

        // 5. Plataforma / Moodle: Platf.
        if (stripos($nombre_clean, 'plataforma') !== false || stripos($nombre_clean, 'moodle') !== false || $cod_upper === 'PLATAFORMA') {
            return 'Platf.';
        }

        // 6. Certificación: Certif.
        if (stripos($nombre_clean, 'certificaci') !== false) {
            return 'Certif.';
        }

        // 7. TOEFL
        if (stripos($nombre_clean, 'toefl') !== false || $cod_upper === 'TOEFL') {
            return 'TOEFL';
        }

        // 8. Examen Final: Final
        if (stripos($nombre_clean, 'final') !== false) {
            return 'Final';
        }

        // Si ya es un código muy corto (<= 4 caracteres)
        if (!empty($codigo) && strlen($codigo) <= 4 && !preg_match('/[0-9]{4,}/', $codigo)) {
            return $codigo;
        }

        // Si el nombre es corto (<= 6 caracteres), mantenerlo
        if (mb_strlen($nombre_clean) <= 6) {
            return $nombre_clean;
        }

        return mb_substr($nombre_clean, 0, 5) . '.';
    }
}

$stmt_crit = $pdo->prepare("SELECT codigo_examen, nombre_examen, puntos_maximos, color, icono, categoria FROM criterios_evaluacion WHERE materia_id = ?");
$stmt_crit->execute([$materia_id]);
$criterios = $stmt_crit->fetchAll(PDO::FETCH_ASSOC);

usort($criterios, function ($a, $b) {
    $cod_a = strtoupper($a['codigo_examen']);
    $cod_b = strtoupper($b['codigo_examen']);
    $get_peso = function ($cod) {
        if (strpos($cod, 'Q1') !== false || strpos($cod, 'Q2') !== false || strpos($cod, 'Q3') !== false) return 1;
        if (strpos($cod, 'QO') !== false) return 2;
        if (strpos($cod, 'WRITING') !== false) return 3;
        if (strpos($cod, 'PARTICIPACION') !== false) return 4;
        if (strpos($cod, 'PLATAFORMA') !== false) return 5;
        if (strpos($cod, 'CERTIFICACION') !== false || strpos($cod, 'FINAL') !== false) return 6;
        return 7;
    };
    $peso_a = $get_peso($cod_a);
    $peso_b = $get_peso($cod_b);
    if ($peso_a == $peso_b) {
        return strnatcasecmp($a['nombre_examen'], $b['nombre_examen']);
    }
    return $peso_a - $peso_b;
});

// Asignamos iconos fijos del sistema para el frontend
foreach ($criterios as &$c) {
    $nombre = $c['nombre_examen'];
    if (stripos($nombre, 'Oral') !== false) {
        $c['icono_fijo'] = 'fas fa-comments';
        $c['color_fijo'] = 'class-green';
    } elseif (stripos($nombre, 'Proyecto') !== false) {
        $c['icono_fijo'] = 'fas fa-file-signature';
        $c['color_fijo'] = 'class-yellow';
    } elseif (stripos($nombre, 'Participaci') !== false) {
        $c['icono_fijo'] = 'fas fa-hand-paper';
        $c['color_fijo'] = 'class-cyan';
    } else {
        $c['icono_fijo'] = 'fas fa-book-open';
        $c['color_fijo'] = 'class-muted';
    }
}

$permitidos_profesor = ['QO', 'WRITING', 'PARTICIPACION'];
$puntos_maximos_totales = 0;
foreach ($criterios as &$c) {
    $puntos_maximos_totales += floatval($c['puntos_maximos']);
    $es_esencial = false;
    $cod_upper = strtoupper($c['codigo_examen']);
    foreach ($permitidos_profesor as $palabra) {
        if (strpos($cod_upper, $palabra) !== false) {
            $es_esencial = true;
            break;
        }
    }
    $c['bloqueado'] = ($grupo_cerrado || ($edicion_total === 0 && !$es_esencial)) ? true : false;
}
unset($c);

$sql_alum = "SELECT i.inscripcion_id, u.codigo, u.nombre, u.apellido_paterno, u.apellido_materno, u.foto_perfil FROM inscripciones i JOIN alumnos a ON i.alumno_id = a.alumno_id JOIN usuarios u ON a.usuario_id = u.usuario_id WHERE i.nrc IN (SELECT nrc FROM grupos WHERE clave_grupo = ? AND profesor_id = ?) AND i.estatus = 'INSCRITO' ORDER BY u.apellido_paterno ASC, u.apellido_materno ASC, u.nombre ASC";
$stmt_alum = $pdo->prepare($sql_alum);
$stmt_alum->execute([$clave_grupo, $profesor_id]);
$alumnos = $stmt_alum->fetchAll(PDO::FETCH_ASSOC);

$calificaciones_actuales = [];
if (count($alumnos) > 0) {
    $inscripciones_ids = array_column($alumnos, 'inscripcion_id');
    $in_clause = implode(',', array_fill(0, count($inscripciones_ids), '?'));
    $stmt_cal = $pdo->prepare("SELECT inscripcion_id, tipo_examen, puntaje FROM calificaciones WHERE inscripcion_id IN ($in_clause)");
    $stmt_cal->execute($inscripciones_ids);
    while ($row = $stmt_cal->fetch(PDO::FETCH_ASSOC)) {
        $calificaciones_actuales[$row['inscripcion_id']][$row['tipo_examen']] = $row['puntaje'];
    }
}
