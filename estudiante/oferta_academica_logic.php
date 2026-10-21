<?php
/**
 * oferta_academica_logic.php
 * Controlador y lógica de datos para la oferta académica disponible para el estudiante.
 * Gestiona consultas de grupos activos, modalidades, cupos, materias acreditadas e historial de solicitudes.
 */

// Si se intenta acceder directamente al archivo de lógica por URL, redirigir a oferta_academica
if (basename($_SERVER['PHP_SELF']) === 'oferta_academica_logic.php') {
    header('Location: oferta_academica');
    exit;
}

session_start();
require_once '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

requerir_rol('ALUMNO');

// Obtener o asegurar el alumno_id correspondiente al usuario en sesión
$alumno_id_usuario = (int)$_SESSION['user_id'];
$stmt_al = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
$stmt_al->execute([$alumno_id_usuario]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);

if (!$alumno || empty($alumno['alumno_id'])) {
    $ins_al = $pdo->prepare("INSERT INTO alumnos (usuario_id, carrera) VALUES (?, '')");
    $ins_al->execute([$alumno_id_usuario]);
    $alumno_id = (int)$pdo->lastInsertId();
} else {
    $alumno_id = (int)$alumno['alumno_id'];
}

// 1. Obtener nombre del ciclo activo
$stmt_ciclo = $pdo->query("SELECT nombre FROM ciclos WHERE activo = 1 LIMIT 1");
$nombre_ciclo = $stmt_ciclo->fetchColumn() ?: 'Actual';

// 2. Estado del periodo de altas
$altas_habilitadas = config_get('altas_habilitadas', false);

// 3. Obtener grupos activos del ciclo vigente
$sql_grupos = "
    SELECT 
        g.clave_grupo, g.nrc, g.cupo, 
        m.materia_id, m.clave as clave_materia, m.nombre as idioma, m.nivel, 
        u.nombre as prof_nombre, u.apellido_paterno as prof_ap,
        (SELECT COUNT(*) FROM inscripciones i WHERE i.nrc = g.nrc AND (i.estatus = 'INSCRITO' OR i.estatus = 'BAJA')) as inscritos,
        (SELECT COUNT(*) FROM solicitudes_altas sa JOIN inscripciones i ON sa.inscripcion_id = i.inscripcion_id WHERE i.nrc = g.nrc AND sa.estatus = 'PENDIENTE') as demanda,
        h.hora_inicio, h.hora_fin, h.dias_patron, h.aula, h.modalidad
    FROM grupos g
    JOIN materias m ON g.materia_id = m.materia_id
    JOIN usuarios u ON g.profesor_id = u.usuario_id
    JOIN ciclos c ON g.ciclo_id = c.ciclo_id
    LEFT JOIN horarios h ON g.nrc = h.nrc
    WHERE c.activo = 1 AND g.estado = 'ACTIVO'
    ORDER BY m.nombre ASC, m.nivel ASC, g.clave_grupo ASC
";
$raw_grupos = $pdo->query($sql_grupos)->fetchAll(PDO::FETCH_ASSOC);

// 4. Materias en las que el alumno ya está inscrito
$stmt_inscritos = $pdo->prepare("
    SELECT m.materia_id, i.nrc 
    FROM inscripciones i 
    JOIN grupos g ON i.nrc = g.nrc 
    JOIN materias m ON g.materia_id = m.materia_id 
    WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'
");
$stmt_inscritos->execute([$alumno_id]);
$inscritos_data = $stmt_inscritos->fetchAll(PDO::FETCH_ASSOC);
$mis_nrcs = array_column($inscritos_data, 'nrc');
$mis_materias_inscritas = array_column($inscritos_data, 'materia_id');

// 5. Materias que el alumno ya solicitó y están pendientes
$stmt_solicitados = $pdo->prepare("
    SELECT m.materia_id, i.nrc 
    FROM inscripciones i 
    JOIN grupos g ON i.nrc = g.nrc 
    JOIN materias m ON g.materia_id = m.materia_id 
    WHERE i.alumno_id = ? AND i.estatus = 'SOLICITUD_ALTA'
");
$stmt_solicitados->execute([$alumno_id]);
$solicitados_data = $stmt_solicitados->fetchAll(PDO::FETCH_ASSOC);
$mis_solicitudes = array_column($solicitados_data, 'nrc');
$mis_materias_solicitadas = array_column($solicitados_data, 'materia_id');

// 6. Materias que el alumno ya acreditó (calificación final >= 80)
$stmt_acreditadas = $pdo->prepare("
    SELECT CONCAT(UPPER(TRIM(m.nombre)), '_', m.nivel) as materia_key
    FROM inscripciones i
    JOIN grupos g ON i.nrc = g.nrc
    JOIN materias m ON g.materia_id = m.materia_id
    LEFT JOIN calificaciones c ON c.inscripcion_id = i.inscripcion_id
    WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'
    GROUP BY m.nombre, m.nivel, i.inscripcion_id
    HAVING SUM(COALESCE(c.puntaje, 0)) >= 80
");
$stmt_acreditadas->execute([$alumno_id]);
$mis_materias_acreditadas = $stmt_acreditadas->fetchAll(PDO::FETCH_COLUMN);

// Funciones auxiliares para formato y presentación
if (!function_exists('getRomanNumeral')) {
    function getRomanNumeral(int $num): string
    {
        $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];
        return (string)($map[$num] ?? $num);
    }
}

if (!function_exists('getDiasCompletos')) {
    function getDiasCompletos(string $dias_patron): string
    {
        if (empty($dias_patron)) return 'Sin asignar';
        $map = ['L' => 'Lunes', 'M' => 'Martes', 'I' => 'Miércoles', 'J' => 'Jueves', 'V' => 'Viernes', 'S' => 'Sábado', 'D' => 'Domingo'];
        $letras = explode('-', $dias_patron);
        $nombres = [];
        foreach ($letras as $l) {
            $l = trim($l);
            if (isset($map[$l])) $nombres[] = $map[$l];
        }
        return !empty($nombres) ? implode('-', $nombres) : $dias_patron;
    }
}

// 7. Algoritmo de agrupación: unificar modalidad presencial y virtual por clave_grupo
$grupos_agrupados = [];
foreach ($raw_grupos as $row) {
    $clave = $row['clave_grupo'] ?: $row['nrc']; // Usar nrc como clave si clave_grupo es nulo
    if (!isset($grupos_agrupados[$clave])) {
        $grupos_agrupados[$clave] = [
            'clave_grupo' => $row['clave_grupo'],
            'materia_id' => $row['materia_id'],
            'clave_materia' => $row['clave_materia'],
            'idioma' => $row['idioma'],
            'nivel' => $row['nivel'],
            'prof_nombre' => $row['prof_nombre'],
            'prof_ap' => $row['prof_ap'],
            'cupo' => (int)$row['cupo'],
            'inscritos' => (int)$row['inscritos'],
            'demanda' => (int)$row['demanda'],
            'nrc_presencial' => 'NA',
            'nrc_virtual' => 'NA',
            'horario_presencial' => null,
            'horario_virtual' => null
        ];
    }

    // Maximizar inscritos y demanda
    $grupos_agrupados[$clave]['inscritos'] = max($grupos_agrupados[$clave]['inscritos'], (int)$row['inscritos']);
    $grupos_agrupados[$clave]['demanda'] = max($grupos_agrupados[$clave]['demanda'], (int)$row['demanda']);

    if ($row['modalidad'] === 'PRESENCIAL') {
        $grupos_agrupados[$clave]['nrc_presencial'] = $row['nrc'];
        $grupos_agrupados[$clave]['horario_presencial'] = [
            'inicio' => substr($row['hora_inicio'] ?? '', 0, 5),
            'fin' => substr($row['hora_fin'] ?? '', 0, 5),
            'dias' => getDiasCompletos($row['dias_patron'] ?? ''),
            'aula' => $row['aula'] ?? 'Sin aula'
        ];
    } elseif ($row['modalidad'] === 'VIRTUAL') {
        $grupos_agrupados[$clave]['nrc_virtual'] = $row['nrc'];
        $grupos_agrupados[$clave]['horario_virtual'] = [
            'inicio' => substr($row['hora_inicio'] ?? '', 0, 5),
            'fin' => substr($row['hora_fin'] ?? '', 0, 5),
            'dias' => getDiasCompletos($row['dias_patron'] ?? ''),
            'aula' => $row['aula'] ?? 'Virtual'
        ];
    } else {
        // Sin modalidad asignada, se coloca como presencial por defecto
        if ($grupos_agrupados[$clave]['nrc_presencial'] === 'NA') {
            $grupos_agrupados[$clave]['nrc_presencial'] = $row['nrc'];
        }
    }
}
$grupos = array_values($grupos_agrupados);

// 8. Historial de solicitudes de alta anteriores del estudiante
$stmt_hist_altas = $pdo->prepare("
    SELECT sa.*, m.nombre AS materia, m.nivel, m.clave AS clave_materia, g.clave_grupo 
    FROM solicitudes_altas sa
    JOIN inscripciones i ON sa.inscripcion_id = i.inscripcion_id
    JOIN grupos g ON i.nrc = g.nrc
    JOIN materias m ON g.materia_id = m.materia_id
    WHERE i.alumno_id = ?
    ORDER BY sa.fecha_solicitud DESC
");
$stmt_hist_altas->execute([$alumno_id]);
$historial_altas = $stmt_hist_altas->fetchAll(PDO::FETCH_ASSOC);

$hay_actualizacion_reciente_alta = false;
if (count($historial_altas) > 0) {
    $ultima_alta = $historial_altas[0];
    if ($ultima_alta['estatus'] === 'RECHAZADA' || $ultima_alta['estatus'] === 'APROBADA') {
        $hay_actualizacion_reciente_alta = true;
    }
}

// Token CSRF para formularios modales
$csrf_token = $_SESSION['csrf_token'] ?? '';
