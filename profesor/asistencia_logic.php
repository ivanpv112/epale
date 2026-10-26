<?php
/**
 * asistencia_logic.php
 * Lógica de negocio y consultas para la toma y visualización de asistencias en profesor/asistencia.php
 */

session_start();
require '../db.php';
require_once '../security.php';

requerir_rol('PROFESOR');

// Token CSRF
validar_csrf_estricto('POST');

$clave = $_GET['clave'] ?? '';
$profesor_id = $_SESSION['user_id'];

// 1. Obtener información del grupo y ciclo
$stmt = $pdo->prepare("SELECT m.materia_id, m.clave as clave_materia, m.nombre as materia, m.nivel, c.nombre as ciclo, c.activo as ciclo_activo, g.estado 
                       FROM grupos g 
                       JOIN materias m ON g.materia_id = m.materia_id 
                       JOIN ciclos c ON g.ciclo_id = c.ciclo_id 
                       WHERE g.clave_grupo = ? AND g.profesor_id = ?
                       LIMIT 1");
$stmt->execute([$clave, $profesor_id]);
$grupo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$grupo) { 
    header("Location: mis_grupos"); 
    exit; 
}

$ciclo_inactivo = ((int)($grupo['ciclo_activo'] ?? 0) === 0);
$grupo_cerrado = ($grupo['estado'] === 'CERRADO');
$es_ciclo_pasado = $ciclo_inactivo || isset($_GET['pasado']) || isset($_GET['historico']);
$modo_solo_lectura = $grupo_cerrado || $es_ciclo_pasado;

// Obtener sesiones y horarios del grupo (Presencial y Virtual)
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
$stmt_det->execute([$clave, $profesor_id]);
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

// 2. Obtener lista de alumnos inscritos
$stmt_al = $pdo->prepare("SELECT i.inscripcion_id, u.nombre, u.apellido_paterno, u.apellido_materno, u.codigo, u.foto_perfil 
                          FROM inscripciones i 
                          JOIN alumnos a ON i.alumno_id = a.alumno_id 
                          JOIN usuarios u ON a.usuario_id = u.usuario_id 
                          WHERE i.nrc IN (SELECT nrc FROM grupos WHERE clave_grupo = ? AND profesor_id = ?) 
                          AND i.estatus = 'INSCRITO' 
                          ORDER BY u.apellido_paterno ASC, u.apellido_materno ASC");
$stmt_al->execute([$clave, $profesor_id]);
$alumnos_brutos = $stmt_al->fetchAll(PDO::FETCH_ASSOC);

$alumnos = [];
foreach($alumnos_brutos as $alum) {
    $foto_url = "../img/avatar-default.png";
    if (!empty($alum['foto_perfil']) && file_exists("../img/perfiles/" . $alum['foto_perfil'])) {
        $foto_url = "../img/perfiles/" . $alum['foto_perfil'];
    }
    $alum['foto_url'] = $foto_url;
    $alumnos[] = $alum;
}

// 3. Obtener fechas de clases
$stmt_fechas = $pdo->prepare("SELECT DISTINCT fecha FROM asistencias a 
                              JOIN inscripciones i ON a.inscripcion_id = i.inscripcion_id 
                              WHERE i.nrc IN (SELECT nrc FROM grupos WHERE clave_grupo = ? AND profesor_id = ?) 
                              ORDER BY fecha ASC");
$stmt_fechas->execute([$clave, $profesor_id]);
$fechas_clase = $stmt_fechas->fetchAll(PDO::FETCH_COLUMN);
$total_sesiones = count($fechas_clase);

// 4. Mapear asistencias
$asistencias_log = [];
$stmt_log = $pdo->prepare("SELECT a.inscripcion_id, a.fecha, a.estatus FROM asistencias a 
                           JOIN inscripciones i ON a.inscripcion_id = i.inscripcion_id 
                           WHERE i.nrc IN (SELECT nrc FROM grupos WHERE clave_grupo = ? AND profesor_id = ?)");
$stmt_log->execute([$clave, $profesor_id]);
while($row = $stmt_log->fetch(PDO::FETCH_ASSOC)) {
    $asistencias_log[$row['inscripcion_id']][$row['fecha']] = $row['estatus'];
}

$hoy = date('Y-m-d');
$asistencia_hoy_completada = in_array($hoy, $fechas_clase);
