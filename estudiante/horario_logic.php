<?php
/**
 * horario_logic.php
 * Controlador y lógica de cálculo para la cuadrícula del horario semanal del estudiante.
 */

// Si se intenta acceder directamente al archivo de lógica por URL, redirigir a horario
if (basename($_SERVER['PHP_SELF']) === 'horario_logic.php') {
    header('Location: horario');
    exit;
}

session_start();
require_once '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

requerir_rol('ALUMNO');

// Obtener o asegurar el alumno_id correspondiente al usuario en sesión
$stmt_alumno = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
$stmt_alumno->execute([$_SESSION['user_id']]);
$alumno_id = $stmt_alumno->fetchColumn();

if (empty($alumno_id)) {
    $ins_al = $pdo->prepare("INSERT INTO alumnos (usuario_id, carrera) VALUES (?, '')");
    $ins_al->execute([$_SESSION['user_id']]);
    $alumno_id = (int)$pdo->lastInsertId();
} else {
    $alumno_id = (int)$alumno_id;
}

// Consultar materias y horarios activos (incluyendo grupos asociados por clave_grupo)
$sql_horarios = "
    SELECT m.materia_id, m.nombre as materia, m.nivel, c.nombre as ciclo, 
           h.modalidad, h.dias_patron, h.hora_inicio, h.hora_fin, h.aula, i.estatus
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
    WHERE i.alumno_id = ? AND i.estatus IN ('INSCRITO', 'SOLICITUD_ALTA') AND g_todos.estado = 'ACTIVO'
    ORDER BY h.hora_inicio ASC
";

$stmt_h = $pdo->prepare($sql_horarios);
$stmt_h->execute([$alumno_id]);
$horarios_db = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

$ciclo_actual = count($horarios_db) > 0 ? $horarios_db[0]['ciclo'] : 'Sin clases activas';

if (!function_exists('descifrarDias')) {
    /**
     * Traduce una cadena de días (ej. 'LUN-MIE', 'SAB') a números de columna CSS Grid.
     * 2 = Lunes, 3 = Martes, 4 = Miércoles, 5 = Jueves, 6 = Viernes, 7 = Sábado.
     */
    function descifrarDias(string $cadena): array
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

// Paleta de colores para las materias del horario (compatible con modo claro y oscuro)
$paleta = [
    ['bg' => 'rgba(0, 86, 179, 0.1)', 'border' => '#8bb9ff', 'text' => 'var(--udg-blue)'],
    ['bg' => 'rgba(25, 135, 84, 0.1)', 'border' => '#89dfa9', 'text' => '#198754'],
    ['bg' => 'rgba(255, 193, 7, 0.1)', 'border' => '#ffe69c', 'text' => '#d39e00'],
    ['bg' => 'rgba(220, 53, 69, 0.1)', 'border' => '#f1aeb5', 'text' => '#dc3545'],
    ['bg' => 'rgba(111, 66, 193, 0.1)', 'border' => '#c29ffa', 'text' => '#6f42c1'],
    ['bg' => 'rgba(23, 162, 184, 0.1)', 'border' => '#9eeaf9', 'text' => '#17a2b8']
];

if (!function_exists('getRomanNumeralHorario')) {
    function getRomanNumeralHorario(int $num): string
    {
        $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];
        return (string)($map[$num] ?? $num);
    }
}

// Construcción de los bloques de clases listos para ser renderizados por CSS Grid
$colores_asignados = [];
$color_index = 0;
$bloques_render = [];

foreach ($horarios_db as $h) {
    if (!$h['hora_inicio'] || !$h['hora_fin'] || !$h['dias_patron']) continue;

    if (!isset($colores_asignados[$h['materia_id']])) {
        $colores_asignados[$h['materia_id']] = $paleta[$color_index % count($paleta)];
        $color_index++;
    }
    $color = $colores_asignados[$h['materia_id']];

    $hora_ini = (int)date('H', strtotime($h['hora_inicio']));
    $hora_fin = (int)date('H', strtotime($h['hora_fin']));
    $min_fin = (int)date('i', strtotime($h['hora_fin']));

    if ($min_fin > 0) $hora_fin++;

    // Cálculo de filas en base al rango horario (7:00 am = Fila 2)
    $fila_inicio = ($hora_ini - 7) + 2;
    $fila_fin = ($hora_fin - 7) + 2;

    $dias = descifrarDias($h['dias_patron']);
    $icono = ($h['modalidad'] == 'PRESENCIAL') ? '<i class="fas fa-building"></i>' : '<i class="fas fa-laptop-house"></i>';

    foreach ($dias as $columna_dia) {
        $bloques_render[] = [
            'col' => $columna_dia,
            'row_ini' => $fila_inicio,
            'row_fin' => $fila_fin,
            'color' => $color,
            'titulo' => $h['materia'] . ' ' . getRomanNumeralHorario((int)$h['nivel']),
            'tiempo' => date('H:i', strtotime($h['hora_inicio'])) . ' - ' . date('H:i', strtotime($h['hora_fin'])),
            'aula' => $h['aula'],
            'icono' => $icono,
            'modalidad' => $h['modalidad'],
            'estatus' => $h['estatus']
        ];
    }
}
