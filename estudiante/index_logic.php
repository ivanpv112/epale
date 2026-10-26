<?php
/**
 * index_logic.php
 * Controlador y lógica del dashboard principal del estudiante.
 */

// Si se intenta acceder directamente al archivo de lógica por URL, redirigir a index
if (basename($_SERVER['PHP_SELF']) === 'index_logic.php') {
    header('Location: index');
    exit;
}

session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

date_default_timezone_set('America/Mexico_City');

requerir_rol('ALUMNO');

$stmt_al = $pdo->prepare("SELECT a.alumno_id, u.nombre, u.apellido_paterno, u.apellido_materno 
                          FROM usuarios u 
                          LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id 
                          WHERE u.usuario_id = ?");
$stmt_al->execute([$_SESSION['user_id']]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);

if (!$alumno) {
    requerir_rol('ALUMNO'); // Expulsa si no existe el usuario
}

// Si es un usuario alumno pero no tenía registro en la tabla `alumnos`, lo creamos automáticamente
if (empty($alumno['alumno_id'])) {
    $ins_al = $pdo->prepare("INSERT INTO alumnos (usuario_id, carrera) VALUES (?, '')");
    $ins_al->execute([$_SESSION['user_id']]);
    $alumno_id = (int)$pdo->lastInsertId();
} else {
    $alumno_id = (int)$alumno['alumno_id'];
}

$nombre_completo = trim(($alumno['nombre'] ?? '') . ' ' . ($alumno['apellido_paterno'] ?? '') . ' ' . ($alumno['apellido_materno'] ?? ''));
if ($nombre_completo === '') {
    $nombre_completo = $_SESSION['nombre'] ?? 'Estudiante';
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
    $color_icono_saludo = '#6366f1';
}

// Extraer el primer nombre para un saludo más personal o mostrar el nombre completo
$primer_nombre = trim(explode(' ', trim($alumno['nombre'] ?? ''))[0]);
if ($primer_nombre === '') {
    $primer_nombre = $nombre_completo;
}

// MATERIAS ACTIVAS ORDENADAS POR IDIOMA Y NIVEL
$sql_materias = "SELECT i.inscripcion_id, m.materia_id, m.nombre, m.nivel, g.nrc 
                 FROM inscripciones i
                 JOIN grupos g ON i.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                 WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO' AND g.estado = 'ACTIVO' AND c.activo = 1
                 ORDER BY m.nombre ASC, m.nivel ASC";
$stmt_mat = $pdo->prepare($sql_materias);
$stmt_mat->execute([$alumno_id]);
$materias_inscritas = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

$dias_map = ['Monday' => 2, 'Tuesday' => 3, 'Wednesday' => 4, 'Thursday' => 5, 'Friday' => 6, 'Saturday' => 7, 'Sunday' => 1];
$columna_hoy = $dias_map[date('l')];

if (!function_exists('descifrarDiasHoy')) {
    function descifrarDiasHoy(string $cadena): array
    {
        $columnas = [];
        $s = strtoupper($cadena);

        if (strpos($s, 'LUN') !== false) $columnas[] = 2;
        if (strpos($s, 'MAR') !== false) $columnas[] = 3;
        if (strpos($s, 'MIE') !== false || strpos($s, 'MIÉ') !== false) $columnas[] = 4;
        if (strpos($s, 'JUE') !== false) $columnas[] = 5;
        if (strpos($s, 'VIE') !== false) $columnas[] = 6;
        if (strpos($s, 'SAB') !== false || strpos($s, 'SÁB') !== false) $columnas[] = 7;

        if (empty($columnas)) {
            if (strpos($s, 'L') !== false) $columnas[] = 2;
            if (strpos($s, 'M') !== false) $columnas[] = 3;
            if (strpos($s, 'I') !== false || strpos($s, 'X') !== false || strpos($s, 'W') !== false) $columnas[] = 4;
            if (strpos($s, 'J') !== false) $columnas[] = 5;
            if (strpos($s, 'V') !== false) $columnas[] = 6;
            if (strpos($s, 'S') !== false) $columnas[] = 7;
        }
        return array_unique($columnas);
    }
}

// HORARIOS ACTIVOS (Presenciales y Virtuales mediante clave_grupo o nrc inscrito)
$sql_horarios = "
    SELECT h.hora_inicio, h.hora_fin, h.aula, h.modalidad, h.dias_patron, m.nombre, m.nivel, 
           i.inscripcion_id, g_todos.nrc 
    FROM inscripciones i
    JOIN grupos g_inscrito ON i.nrc = g_inscrito.nrc
    JOIN grupos g_todos ON 
        (g_inscrito.clave_grupo IS NOT NULL AND g_inscrito.clave_grupo != '' 
         AND g_todos.clave_grupo = g_inscrito.clave_grupo 
         AND g_todos.materia_id = g_inscrito.materia_id 
         AND g_todos.ciclo_id = g_inscrito.ciclo_id)
        OR (g_todos.nrc = g_inscrito.nrc)
    JOIN materias m ON g_todos.materia_id = m.materia_id
    JOIN ciclos c ON g_todos.ciclo_id = c.ciclo_id
    JOIN horarios h ON g_todos.nrc = h.nrc
    WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO' AND g_todos.estado = 'ACTIVO' AND c.activo = 1
    ORDER BY h.hora_inicio ASC
";
$stmt_hor = $pdo->prepare($sql_horarios);
$stmt_hor->execute([$alumno_id]);
$todos_horarios = $stmt_hor->fetchAll(PDO::FETCH_ASSOC);

$clases_hoy = [];
foreach ($todos_horarios as $h) {
    if (empty($h['dias_patron']) || empty($h['hora_inicio']) || empty($h['hora_fin'])) {
        continue;
    }
    $dias_clase = descifrarDiasHoy((string)$h['dias_patron']);
    if (in_array($columna_hoy, $dias_clase)) {
        $clases_hoy[] = $h;
    }
}

$meses_es = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$dias_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$fecha_texto_es = $dias_es[date('w')] . ', ' . date('d') . ' de ' . $meses_es[date('n') - 1];

// Notificaciones dinámicas de solicitudes (consultas separadas para evitar error de collation en MySQL)
$sql_bajas = "
    SELECT sb.estatus, sb.fecha_respuesta, m.nombre, m.nivel, 'BAJA' as tipo_solicitud
    FROM solicitudes_bajas sb
    JOIN inscripciones i ON sb.inscripcion_id = i.inscripcion_id
    JOIN grupos g ON i.nrc = g.nrc
    JOIN materias m ON g.materia_id = m.materia_id
    WHERE i.alumno_id = ? 
      AND (sb.estatus = 'APROBADA' OR sb.estatus = 'RECHAZADA')
      AND sb.fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 15 DAY)
    ORDER BY sb.fecha_respuesta DESC LIMIT 9
";
$stmt_bajas = $pdo->prepare($sql_bajas);
$stmt_bajas->execute([$alumno_id]);
$notif_bajas = $stmt_bajas->fetchAll(PDO::FETCH_ASSOC);

$sql_altas = "
    SELECT sa.estatus, sa.fecha_respuesta, m.nombre, m.nivel, 'ALTA' as tipo_solicitud
    FROM solicitudes_altas sa
    JOIN inscripciones i ON sa.inscripcion_id = i.inscripcion_id
    JOIN grupos g ON i.nrc = g.nrc
    JOIN materias m ON g.materia_id = m.materia_id
    WHERE i.alumno_id = ? 
      AND (sa.estatus = 'APROBADA' OR sa.estatus = 'RECHAZADA')
      AND sa.fecha_respuesta >= DATE_SUB(NOW(), INTERVAL 15 DAY)
    ORDER BY sa.fecha_respuesta DESC LIMIT 9
";
$stmt_altas = $pdo->prepare($sql_altas);
$stmt_altas->execute([$alumno_id]);
$notif_altas = $stmt_altas->fetchAll(PDO::FETCH_ASSOC);

// Unir y ordenar en PHP
$avisos_dinamicos = array_merge($notif_bajas, $notif_altas);
usort($avisos_dinamicos, function ($a, $b) {
    return strtotime($b['fecha_respuesta'] ?? '') <=> strtotime($a['fecha_respuesta'] ?? '');
});
$avisos_dinamicos = array_slice($avisos_dinamicos, 0, 9);

// 4. ASISTENCIAS POR MATERIA ACTIVA
$asistencias_resumen = [];
if (!empty($materias_inscritas)) {
    $stmt_asist_materia = $pdo->prepare("
        SELECT 
            COUNT(*) as total_registradas,
            SUM(CASE WHEN a.estatus IN ('ASISTENCIA', 'RETARDO') THEN 1 ELSE 0 END) as asistencias_validas,
            SUM(CASE WHEN a.estatus = 'FALTA' THEN 1 ELSE 0 END) as faltas,
            SUM(CASE WHEN a.estatus = 'RETARDO' THEN 1 ELSE 0 END) as retardos
        FROM asistencias a
        WHERE a.inscripcion_id = ?
    ");

    foreach ($materias_inscritas as $mat) {
        $stmt_asist_materia->execute([$mat['inscripcion_id']]);
        $row_asist = $stmt_asist_materia->fetch(PDO::FETCH_ASSOC);

        $tot = (int)($row_asist['total_registradas'] ?? 0);
        $asistidas = (int)($row_asist['asistencias_validas'] ?? 0);
        $faltas = (int)($row_asist['faltas'] ?? 0);
        $retardos = (int)($row_asist['retardos'] ?? 0);

        if ($tot > 0) {
            $porcentaje = round(($asistidas / $tot) * 100);
            if ($porcentaje > 100) $porcentaje = 100;
        } else {
            // Sin registros aún: 100% de inicio
            $porcentaje = 100;
        }

        // Colores y estados:
        // >= 80%: Verde (buena asistencia)
        // >= 70% y < 80%: Amarillo / Ámbar (en riesgo)
        // < 70%: Rojo (reprobado por faltas / sin derecho)
        if ($tot == 0) {
            $color = '#10b981';
            $estado_texto = 'Sin faltas';
            $badge_bg = 'rgba(16, 185, 129, 0.12)';
            $badge_color = '#059669';
            $categoria = 'excelente';
        } elseif ($porcentaje >= 80) {
            $color = '#10b981';
            $estado_texto = 'Regular';
            $badge_bg = 'rgba(16, 185, 129, 0.12)';
            $badge_color = '#059669';
            $categoria = 'excelente';
        } elseif ($porcentaje >= 70) {
            $color = '#f59e0b';
            $estado_texto = 'En riesgo';
            $badge_bg = 'rgba(245, 158, 11, 0.12)';
            $badge_color = '#d97706';
            $categoria = 'riesgo';
        } else {
            $color = '#ef4444';
            $estado_texto = 'Reprobado';
            $badge_bg = 'rgba(239, 68, 68, 0.12)';
            $badge_color = '#dc2626';
            $categoria = 'reprobado';
        }

        $asistencias_resumen[] = [
            'inscripcion_id' => $mat['inscripcion_id'],
            'materia_id'     => $mat['materia_id'],
            'nombre'         => $mat['nombre'],
            'nivel'          => $mat['nivel'],
            'nrc'            => $mat['nrc'],
            'total'          => $tot,
            'asistidas'      => $asistidas,
            'faltas'         => $faltas,
            'retardos'       => $retardos,
            'porcentaje'     => $porcentaje,
            'color'          => $color,
            'estado_texto'   => $estado_texto,
            'badge_bg'       => $badge_bg,
            'badge_color'    => $badge_color,
            'categoria'      => $categoria
        ];
    }
}

if (!function_exists('format_score')) {
    function format_score(mixed $num)
    {
        return floatval($num) == intval($num) ? intval($num) : floatval($num);
    }
}

if (!function_exists('recortar_texto')) {
    function recortar_texto(string $texto, int $limite = 60)
    {
        if (mb_strlen($texto, 'UTF-8') > $limite) {
            return mb_substr($texto, 0, $limite, 'UTF-8') . '... <span style="color:var(--udg-blue); font-size:0.8rem; font-weight:bold;">Ver más</span>';
        }
        return $texto;
    }
}

if (!function_exists('nivel_a_romano')) {
    function nivel_a_romano(int $nivel) {
        $romanos = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X'];
        return isset($romanos[$nivel]) ? $romanos[$nivel] : $nivel;
    }
}

$pdo->exec("DELETE FROM avisos WHERE fecha_expiracion IS NOT NULL AND fecha_expiracion < NOW()");

$mis_nrcs = [];
$mis_materias_ids = [];
$mis_idiomas = [];
foreach ($materias_inscritas as $m) {
    $mis_nrcs[] = $m['nrc'];
    $mis_materias_ids[] = $m['materia_id'];
    $idioma_puro = trim(preg_replace('/[0-9]+/', '', $m['nombre']));
    $mis_idiomas[] = $idioma_puro;
}
$mis_idiomas = array_unique($mis_idiomas);

$sql_avisos = "SELECT * FROM avisos WHERE autor_id IS NULL AND rol_destinatario IN ('TODOS', 'ALUMNO') AND (tipo_audiencia = 'GLOBAL'";

if (count($mis_idiomas) > 0) {
    $in_idiomas = "'" . implode("','", $mis_idiomas) . "'";
    $sql_avisos .= " OR (tipo_audiencia = 'IDIOMA' AND audiencia_ref IN ($in_idiomas))";
}
if (count($mis_materias_ids) > 0) {
    $in_mats = implode(",", $mis_materias_ids);
    $sql_avisos .= " OR (tipo_audiencia = 'MATERIA' AND audiencia_ref IN ($in_mats))";
}
if (count($mis_nrcs) > 0) {
    $in_nrc = implode(",", $mis_nrcs);
    $sql_avisos .= " OR (tipo_audiencia = 'GRUPO' AND audiencia_ref IN ($in_nrc))";
}

$sql_avisos .= ") ORDER BY fecha_creacion DESC";
$avisos_admin = $pdo->query($sql_avisos)->fetchAll(PDO::FETCH_ASSOC);

$avisos_prof_dashboard = [];
$avisos_prof_todas = [];

if (count($mis_nrcs) > 0) {
    $in_nrc_avisos_prof = implode(",", $mis_nrcs);
    $sql_avisos_prof = "
        SELECT a.aviso_id, a.autor_id, a.audiencia_ref AS nrc, a.tipo_comunicado AS tipo, 
               a.titulo, a.cuerpo AS descripcion, a.fecha_inicio, a.fecha_expiracion AS fecha_fin, 
               a.fecha_creacion, a.imagen, m.nombre as materia_nombre, m.nivel as materia_nivel, 
               g.clave_grupo, u.nombre as prof_nombre, u.apellido_paterno as prof_ap
        FROM avisos a
        JOIN grupos g ON CAST(a.audiencia_ref AS UNSIGNED) = g.nrc
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN usuarios u ON a.autor_id = u.usuario_id
        WHERE a.tipo_audiencia = 'GRUPO' AND a.audiencia_ref IN ($in_nrc_avisos_prof)
        ORDER BY COALESCE(a.fecha_inicio, a.fecha_creacion) DESC
    ";
    $avisos_prof_raw = $pdo->query($sql_avisos_prof)->fetchAll(PDO::FETCH_ASSOC);

    $hoy = new DateTime();
    foreach ($avisos_prof_raw as $a) {
        $inicio = new DateTime($a['fecha_inicio'] ?: $a['fecha_creacion']);
        $fin = $a['fecha_fin'] ? new DateTime($a['fecha_fin']) : null;

        if ($fin && $hoy > $fin) {
            $estatus = 'FINALIZADA';
        } elseif ($fin && $hoy >= $inicio && $hoy <= $fin) {
            $estatus = 'ACTIVA';
        } elseif (!$fin && $hoy >= $inicio) {
            $estatus = 'ACTIVA';
        } else {
            $diff = $hoy->diff($inicio);
            $dias_faltantes = $diff->days;
            $invert = $diff->invert;
            if ($invert == 0 && $dias_faltantes <= 3) {
                $estatus = 'PRÓXIMA';
            } else {
                $estatus = 'PENDIENTE';
            }
        }

        if ($estatus !== 'PENDIENTE') {
            $a['estatus'] = $estatus;
            $avisos_prof_todas[] = $a;
            if ($estatus !== 'FINALIZADA' && count($avisos_prof_dashboard) < 3) {
                $avisos_prof_dashboard[] = $a;
            }
        }
    }
}

// UNIFICAR TODOS LOS AVISOS GENERALES (DINÁMICOS Y ADMINISTRATIVOS) ORDENADOS POR FECHA DESCENDENTE
$todos_avisos_generales = [];

foreach ($avisos_dinamicos as $ad) {
    $todos_avisos_generales[] = [
        'tipo' => 'SOLICITUD',
        'estatus' => $ad['estatus'],
        'tipo_solicitud' => $ad['tipo_solicitud'],
        'nombre' => $ad['nombre'],
        'nivel' => $ad['nivel'],
        'fecha' => $ad['fecha_respuesta'],
        'timestamp' => !empty($ad['fecha_respuesta']) ? strtotime($ad['fecha_respuesta']) : time()
    ];
}

foreach ($avisos_admin as $aa) {
    $tag_color = '#e2e3e5';
    $tag_text = '#383d41';
    $etiqueta = 'Aviso Global';
    if ($aa['tipo_audiencia'] == 'IDIOMA') {
        $etiqueta = 'Aviso de Idioma';
        $tag_color = '#cce5ff';
        $tag_text = '#004085';
    } elseif ($aa['tipo_audiencia'] == 'MATERIA') {
        $etiqueta = 'Aviso de Nivel';
        $tag_color = '#d4edda';
        $tag_text = '#155724';
    } elseif ($aa['tipo_audiencia'] == 'GRUPO') {
        $etiqueta = 'Aviso de tu Clase';
        $tag_color = '#f8d7da';
        $tag_text = '#721c24';
    }

    $todos_avisos_generales[] = [
        'tipo' => 'ADMIN',
        'id' => $aa['aviso_id'],
        'titulo' => $aa['titulo'],
        'etiqueta' => $etiqueta,
        'tag_color' => $tag_color,
        'tag_text' => $tag_text,
        'cuerpo' => $aa['cuerpo'],
        'imagen' => !empty($aa['imagen']) ? '../img/avisos/' . basename($aa['imagen']) : null,
        'fecha' => $aa['fecha_creacion'],
        'fecha_expiracion' => $aa['fecha_expiracion'],
        'timestamp' => !empty($aa['fecha_creacion']) ? strtotime($aa['fecha_creacion']) : time()
    ];
}

// Ordenar estrictamente: siempre mostrando primero los más recientes
usort($todos_avisos_generales, function ($a, $b) {
    return $b['timestamp'] <=> $a['timestamp'];
});
?>