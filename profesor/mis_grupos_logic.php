<?php
/**
 * mis_grupos_logic.php
 * Lógica de negocio y consultas de datos para profesor/mis_grupos.php
 */

session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

// SEGURIDAD: Solo Profesores (Validación de Sesión y Rol)
requerir_rol('PROFESOR');

$profesor_id = $_SESSION['user_id'];

// 1. OBTENER EL CICLO ACTUAL DEL SISTEMA (independientemente de si el profesor tiene grupos o no)
$stmt_act = $pdo->query("SELECT ciclo_id, nombre, activo FROM ciclos WHERE activo = 1 LIMIT 1");
$ciclo_activo_sistema = $stmt_act->fetch(PDO::FETCH_ASSOC) ?: null;

// 2. OBTENER TODOS LOS CICLOS DONDE EL PROFESOR HA IMPARTIDO CLASES
$sql_ciclos_prof = "SELECT DISTINCT c.ciclo_id, c.nombre, c.activo 
                    FROM grupos g 
                    JOIN ciclos c ON g.ciclo_id = c.ciclo_id 
                    WHERE g.profesor_id = ? 
                    ORDER BY c.activo DESC, c.nombre DESC, c.ciclo_id DESC";
$stmt_cp = $pdo->prepare($sql_ciclos_prof);
$stmt_cp->execute([$profesor_id]);
$ciclos_profesor = $stmt_cp->fetchAll(PDO::FETCH_ASSOC);

// Si el ciclo activo del sistema no está en la lista de ciclos del profesor (porque aún no tiene grupos asignados),
// lo añadimos al inicio de la lista para que siempre aparezca disponible y detectable en el selector.
if ($ciclo_activo_sistema) {
    $existe_activo_en_lista = false;
    foreach ($ciclos_profesor as $cp) {
        if ($cp['ciclo_id'] == $ciclo_activo_sistema['ciclo_id']) {
            $existe_activo_en_lista = true;
            break;
        }
    }
    if (!$existe_activo_en_lista) {
        array_unshift($ciclos_profesor, $ciclo_activo_sistema);
    }
} elseif (!empty($ciclos_profesor)) {
    $ciclo_activo_sistema = $ciclos_profesor[0];
}

// 3. Determinar el ciclo que debe mostrarse:
// Siempre por defecto debe detectar y seleccionar el ciclo activo del sistema
if (isset($_GET['ciclo_ver'])) {
    $ciclo_filtro_id = (int)$_GET['ciclo_ver'];
} else {
    $ciclo_filtro_id = $ciclo_activo_sistema ? (int)$ciclo_activo_sistema['ciclo_id'] : ($ciclos_profesor[0]['ciclo_id'] ?? 0);
}

$ciclo_seleccionado_obj = null;
if ($ciclo_filtro_id === -1) {
    // Opción especial: Todos los ciclos
    $ciclo_seleccionado_obj = ['ciclo_id' => -1, 'nombre' => 'Todos los ciclos', 'activo' => 0];
} else {
    // Buscar en la lista de ciclos del profesor o ciclo activo del sistema
    foreach ($ciclos_profesor as $cp) {
        if ($cp['ciclo_id'] == $ciclo_filtro_id) {
            $ciclo_seleccionado_obj = $cp;
            break;
        }
    }
    if (!$ciclo_seleccionado_obj && $ciclo_activo_sistema && $ciclo_activo_sistema['ciclo_id'] == $ciclo_filtro_id) {
        $ciclo_seleccionado_obj = $ciclo_activo_sistema;
    }
    if (!$ciclo_seleccionado_obj) {
        $ciclo_seleccionado_obj = $ciclo_activo_sistema ?: ($ciclos_profesor[0] ?? ['ciclo_id' => 0, 'nombre' => 'Sin ciclos', 'activo' => 0]);
        $ciclo_filtro_id = (int)($ciclo_seleccionado_obj['ciclo_id'] ?? 0);
    }
}

// OBTENER GRUPOS DEL PROFESOR (Filtrados por ciclo si no es -1)
$params_grupos = [$profesor_id];
$where_ciclo = "";
if ($ciclo_filtro_id !== -1 && $ciclo_filtro_id > 0) {
    $where_ciclo = " AND g.ciclo_id = ? ";
    $params_grupos[] = $ciclo_filtro_id;
}

$sql = "SELECT g.clave_grupo, m.clave AS materia_codigo, m.nombre AS materia, m.nivel, c.nombre AS ciclo, c.activo, g.estado, g.materia_id, g.ciclo_id, g.profesor_id,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END) AS nrc_p,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END) AS nrc_v,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.aula END) AS aula_p,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.aula END) AS aula_v,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.dias_patron END) AS dias_p,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.dias_patron END) AS dias_v,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_inicio END) AS inicio_p,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_inicio END) AS inicio_v,
               MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_fin END) AS fin_p,
               MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_fin END) AS fin_v,
               (SELECT COUNT(DISTINCT i.alumno_id) 
                FROM inscripciones i 
                JOIN grupos g2 ON i.nrc = g2.nrc 
                WHERE g2.clave_grupo = g.clave_grupo AND i.estatus = 'INSCRITO') AS inscritos
        FROM grupos g
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        LEFT JOIN horarios h ON g.nrc = h.nrc
        WHERE g.profesor_id = ? $where_ciclo
        GROUP BY g.clave_grupo, m.clave, m.nombre, m.nivel, c.nombre, c.activo, g.estado, g.materia_id, g.ciclo_id, g.profesor_id
        ORDER BY c.activo DESC, g.estado ASC, c.nombre DESC, m.nivel ASC";

$stmt = $pdo->prepare($sql); 
$stmt->execute($params_grupos); 
$grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
