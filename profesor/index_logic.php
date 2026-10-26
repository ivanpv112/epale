<?php
/**
 * index_logic.php
 * Controlador y lógica del panel principal del profesor.
 * Gestiona consultas de grupos activos, cálculo de avance de calificaciones por criterio y clases del día.
 */

// Si se intenta acceder directamente al archivo de lógica por URL, redirigir a index
if (basename($_SERVER['PHP_SELF']) === 'index_logic.php') {
    header('Location: index');
    exit;
}

session_start();
require_once '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

// SEGURIDAD: Solo Profesores
requerir_rol('PROFESOR');

date_default_timezone_set('America/Mexico_City');

$profesor_id = (int)$_SESSION['user_id'];
$nombre_profesor = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido_paterno'] ?? '') . ' ' . ($_SESSION['apellido_materno'] ?? ''));
if ($nombre_profesor === '') {
    $nombre_profesor = $_SESSION['nombre'] ?? 'Profesor';
}

// Saludo dinámico según la hora del día (Zona horaria de México)
$hora_actual = (int)date('G');
if ($hora_actual >= 6 && $hora_actual < 12) {
    $saludo_tiempo = '¡Buenos días';
    $icono_saludo = 'fa-sun';
    $color_icono_saludo = '#f59e0b';
} elseif ($hora_actual >= 12 && $hora_actual < 19) {
    $saludo_tiempo = '¡Buenas tardes';
    $icono_saludo = 'fa-cloud-sun';
    $color_icono_saludo = '#f97316';
} else {
    $saludo_tiempo = '¡Buenas noches';
    $icono_saludo = 'fa-moon';
    $color_icono_saludo = '#3ba5ff';
}

// Formato de fecha en español para la vista
$dias_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$meses_es = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$fecha_texto_es = $dias_es[date('w')] . ', ' . date('d') . ' de ' . $meses_es[date('n') - 1];

// 1. OBTENER GRUPOS ACTIVOS (CORRECCIÓN MODO ESTRICTO SQL)
$sql_grupos = "SELECT g.clave_grupo, m.clave AS materia_codigo, m.nombre AS materia, m.nivel, c.nombre AS ciclo, g.materia_id, g.ciclo_id, g.profesor_id,
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
               JOIN materias m ON g.materia_id = m.materia_id
               JOIN ciclos c ON g.ciclo_id = c.ciclo_id
               LEFT JOIN horarios h ON g.nrc = h.nrc
               WHERE g.profesor_id = ? AND c.activo = 1 AND g.estado = 'ACTIVO'
               GROUP BY g.clave_grupo, g.materia_id, c.ciclo_id, g.profesor_id, m.clave, m.nombre, m.nivel, c.nombre
               ORDER BY m.nivel ASC";

$stmt = $pdo->prepare($sql_grupos);
$stmt->execute([$profesor_id]);
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_alumnos = 0;
$total_criterios_pendientes = 0;

// MOTOR DE PROGRESO DE CALIFICACIÓN POR GRUPO
foreach ($grupos as &$g) {
    // A. Obtener Criterios del Profesor
    $stmtCriterios = $pdo->prepare("
        SELECT codigo_examen, nombre_examen, color, icono 
        FROM criterios_evaluacion 
        WHERE materia_id = ? 
        AND (nombre_examen LIKE '%Oral%' OR nombre_examen LIKE '%Participaci%' OR nombre_examen LIKE '%Proyecto%')
        ORDER BY categoria ASC
    ");
    $stmtCriterios->execute([$g['materia_id']]);
    $criterios = $stmtCriterios->fetchAll(PDO::FETCH_ASSOC);

    // B. Obtener Inscripciones del Grupo
    $stmtAlumnos = $pdo->prepare("SELECT i.inscripcion_id FROM inscripciones i JOIN grupos g2 ON i.nrc = g2.nrc WHERE g2.clave_grupo = ? AND i.estatus = 'INSCRITO'");
    $stmtAlumnos->execute([$g['clave_grupo']]);
    $inscripciones = $stmtAlumnos->fetchAll(PDO::FETCH_COLUMN);
    $total_inscritos = count($inscripciones);
    
    $g['inscritos'] = $total_inscritos;
    $total_alumnos += $total_inscritos;

    $g['criterios_progreso'] = [];
    $total_calificados_global = 0;
    $total_esperados_global = $total_inscritos * count($criterios);

    // C. Calcular Avance e Inyectar los Iconos Oficiales
    if ($total_inscritos > 0 && count($criterios) > 0) {
        $in_ins = implode(',', array_fill(0, $total_inscritos, '?'));
        foreach ($criterios as $crit) {
            $nombre = $crit['nombre_examen'];
            $icono = $crit['icono'];
            $color = $crit['color'];

            if (stripos($nombre, 'Oral') !== false) {
                $icono = 'fas fa-comments'; if (!$color) $color = '#00a859';
            } elseif (stripos($nombre, 'Proyecto') !== false) {
                $icono = 'fas fa-file-signature'; if (!$color) $color = '#f59e0b';
            } elseif (stripos($nombre, 'Participaci') !== false) {
                $icono = 'fas fa-hand-paper'; if (!$color) $color = '#00a3c4';
            } else {
                if (!$icono) $icono = 'fas fa-check-circle'; if (!$color) $color = 'var(--udg-blue)';
            }

            $params = array_merge([$crit['codigo_examen']], $inscripciones);
            $stmtCal = $pdo->prepare("SELECT COUNT(puntaje) FROM calificaciones WHERE tipo_examen = ? AND inscripcion_id IN ($in_ins) AND puntaje IS NOT NULL");
            $stmtCal->execute($params);
            $calificados = $stmtCal->fetchColumn();

            $porcentaje = round(($calificados / $total_inscritos) * 100);
            if ($porcentaje < 100) { $total_criterios_pendientes++; }

            $g['criterios_progreso'][] = [
                'nombre' => $nombre, 'color' => $color, 'icono' => $icono,
                'calificados' => $calificados, 'total' => $total_inscritos, 'porcentaje' => $porcentaje
            ];
            $total_calificados_global += $calificados;
        }
    }
    $g['progreso_global'] = ($total_esperados_global > 0) ? round(($total_calificados_global / $total_esperados_global) * 100) : 0;
}
unset($g);

$total_grupos = count($grupos);

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

        // Mapeo completo de nombres
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

        // Caso con guión simple como L-I o L-M o L-J
        if (preg_match('/^([A-Z])\s*-\s*([A-Z])$/', $s, $m)) {
            $d1 = $nombres[$m[1]] ?? $m[1];
            $d2 = $nombres[$m[2]] ?? $m[2];
            return $d1 . ' - ' . $d2;
        }

        // Si son caracteres juntos ej. 'LM', 'LMI', 'MI', 'JV'
        $encontrados = [];
        // Checar combinaciones específicas
        if (strpos($s, 'LUN') !== false || strpos($s, 'MAR') !== false || strpos($s, 'MIE') !== false || strpos($s, 'JUE') !== false || strpos($s, 'VIE') !== false) {
            return $cadena; // Ya viene con texto legible
        }

        $chars = preg_split('//u', str_replace([' ', '-', ','], '', $s), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $ch) {
            if (isset($nombres[$ch]) && !in_array($nombres[$ch], $encontrados)) {
                $encontrados[] = $nombres[$ch];
            }
        }

        if (!empty($encontrados)) {
            return implode(' - ', $encontrados);
        }

        return $cadena;
    }
}

// 3. DETERMINAR CLASES DE HOY
$dia_num = date('N'); 
$letra_hoy = '';
switch($dia_num) { case 1: $letra_hoy = 'L'; break; case 2: $letra_hoy = 'M'; break; case 3: $letra_hoy = 'I'; break; case 4: $letra_hoy = 'J'; break; case 5: $letra_hoy = 'V'; break; }

$clases_hoy = [];
if ($letra_hoy !== '') {
    foreach ($grupos as $g) {
        $nivel_rom = !empty($g['nivel']) ? ' ' . nivel_a_romano($g['nivel']) : '';
        if ($g['dias_p'] && (strpos(strtoupper($g['dias_p']), $letra_hoy) !== false || ($letra_hoy == 'I' && (strpos(strtoupper($g['dias_p']), 'MIE') !== false || strpos(strtoupper($g['dias_p']), 'X') !== false)))) {
            $clases_hoy[] = [
                'clave_grupo' => $g['clave_grupo'],
                'nrc' => $g['nrc_p'] ?: ($g['nrc_v'] ?? ''),
                'materia' => $g['materia'] . $nivel_rom,
                'aula' => $g['aula_p'] ?: 'Sin Aula',
                'inicio' => $g['inicio_p'],
                'fin' => $g['fin_p'],
                'tipo' => 'Presencial'
            ];
        }
        if ($g['dias_v'] && (strpos(strtoupper($g['dias_v']), $letra_hoy) !== false || ($letra_hoy == 'I' && (strpos(strtoupper($g['dias_v']), 'MIE') !== false || strpos(strtoupper($g['dias_v']), 'X') !== false)))) {
            $clases_hoy[] = [
                'clave_grupo' => $g['clave_grupo'],
                'nrc' => $g['nrc_v'] ?: ($g['nrc_p'] ?? ''),
                'materia' => $g['materia'] . $nivel_rom,
                'aula' => $g['aula_v'] ?: 'Virtual',
                'inicio' => $g['inicio_v'],
                'fin' => $g['fin_v'],
                'tipo' => 'Virtual'
            ];
        }
    }
}
usort($clases_hoy, function($a, $b) { return strtotime($a['inicio']) - strtotime($b['inicio']); });

// 4. AVISOS Y ASIGNACIONES CENTRALIZADAS DEL PROFESOR
$sql_avisos_prof = "
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
";
$stmt_avisos = $pdo->prepare($sql_avisos_prof);
$stmt_avisos->execute([$profesor_id]);
$todos_avisos_prof = $stmt_avisos->fetchAll(PDO::FETCH_ASSOC);

$ahora_dt = new DateTime();
$avisos_activos_prof = [];
$total_avisos_activos = 0;

foreach ($todos_avisos_prof as &$ap) {
    $inicio_dt = new DateTime($ap['fecha_inicio'] ?: $ap['fecha_creacion']);
    $fin_dt = !empty($ap['fecha_fin']) ? new DateTime($ap['fecha_fin']) : null;
    
    if ($fin_dt && $ahora_dt > $fin_dt) {
        $estatus = 'FINALIZADA';
    } elseif ($fin_dt && $ahora_dt >= $inicio_dt && $ahora_dt <= $fin_dt) {
        $estatus = 'ACTIVA';
    } elseif (!$fin_dt && $ahora_dt >= $inicio_dt) {
        $estatus = 'ACTIVA';
    } else {
        $diff = $ahora_dt->diff($inicio_dt);
        $estatus = ($diff->invert == 0 && $diff->days <= 3) ? 'PRÓXIMA' : 'PENDIENTE';
    }
    
    $ap['estatus'] = $estatus;
    if ($estatus !== 'FINALIZADA') {
        $total_avisos_activos++;
        if (count($avisos_activos_prof) < 4) {
            $avisos_activos_prof[] = $ap;
        }
    }
}
unset($ap);
