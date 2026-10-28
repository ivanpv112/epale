<?php
/**
 * horario_logic.php
 * Lógica de negocio y cálculo de la matriz de horarios para profesor/horario.php
 */

session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

// SEGURIDAD: Solo Profesores
requerir_rol('PROFESOR');
$profesor_id = $_SESSION['user_id'];

// 1. OBTENER EL CICLO ACTUAL DEL SISTEMA (ACTIVO)
$stmt_act = $pdo->query("SELECT ciclo_id, nombre, activo FROM ciclos WHERE activo = 1 LIMIT 1");
$ciclo_activo_sistema = $stmt_act->fetch(PDO::FETCH_ASSOC) ?: null;

$ciclo_actual = $ciclo_activo_sistema ? $ciclo_activo_sistema['nombre'] : 'Sin ciclo activo';
$ciclo_id_activo = $ciclo_activo_sistema ? (int)$ciclo_activo_sistema['ciclo_id'] : 0;

// 2. OBTENER EXCLUSIVAMENTE LAS CLASES DEL PROFESOR EN EL CICLO ACTIVO
$sql_horarios = "SELECT m.materia_id, m.nombre as materia, m.nivel, c.nombre as ciclo, g.clave_grupo,
                        h.modalidad, h.dias_patron, h.hora_inicio, h.hora_fin, h.aula
                 FROM grupos g
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                 JOIN horarios h ON g.nrc = h.nrc
                 WHERE g.profesor_id = ? AND c.activo = 1 AND g.estado = 'ACTIVO'
                 ORDER BY h.hora_inicio ASC";
                 
$stmt_h = $pdo->prepare($sql_horarios);
$stmt_h->execute([$profesor_id]);
$horarios_db = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

// 3. FUNCIÓN PARA DESCIFRAR DÍAS (Lunes = 2 ... Sábado = 7)
if (!function_exists('descifrarDias')) {
    function descifrarDias(string $cadena): array {
        $columnas = []; $s = strtoupper($cadena);
        if(strpos($s, 'LUN')!==false) $columnas[] = 2; 
        if(strpos($s, 'MAR')!==false) $columnas[] = 3; 
        if(strpos($s, 'MIE')!==false || strpos($s, 'MIÉ')!==false) $columnas[] = 4; 
        if(strpos($s, 'JUE')!==false) $columnas[] = 5; 
        if(strpos($s, 'VIE')!==false) $columnas[] = 6;
        if(strpos($s, 'SAB')!==false || strpos($s, 'SÁB')!==false) $columnas[] = 7;

        if(empty($columnas)) {
            if(strpos($s, 'L')!==false) $columnas[] = 2; 
            if(strpos($s, 'M')!==false) $columnas[] = 3; 
            if(strpos($s, 'I')!==false || strpos($s, 'X')!==false || strpos($s, 'W')!==false) $columnas[] = 4; 
            if(strpos($s, 'J')!==false) $columnas[] = 5; 
            if(strpos($s, 'V')!==false) $columnas[] = 6;
            if(strpos($s, 'S')!==false) $columnas[] = 7;
        }
        return array_unique($columnas);
    }
}

if (!function_exists('getRomanNumeralHorario')) {
    function getRomanNumeralHorario(int|string|null $num): string {
        $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];
        return (string)($map[(int)$num] ?? $num);
    }
}

// 4. PALETA DE COLORES IDÉNTICA AL HORARIO DEL ALUMNO (compatible claro/oscuro)
$paleta = [
    ['bg' => 'rgba(0, 86, 179, 0.1)', 'border' => '#8bb9ff', 'text' => 'var(--udg-blue)'],
    ['bg' => 'rgba(25, 135, 84, 0.1)', 'border' => '#89dfa9', 'text' => '#198754'],
    ['bg' => 'rgba(255, 193, 7, 0.1)', 'border' => '#ffe69c', 'text' => '#d39e00'],
    ['bg' => 'rgba(220, 53, 69, 0.1)', 'border' => '#f1aeb5', 'text' => '#dc3545'],
    ['bg' => 'rgba(111, 66, 193, 0.1)', 'border' => '#c29ffa', 'text' => '#6f42c1'],
    ['bg' => 'rgba(23, 162, 184, 0.1)', 'border' => '#9eeaf9', 'text' => '#17a2b8']
];

$colores_asignados = []; 
$color_index = 0; 
$bloques_render = [];

// 5. CALCULAR EL CALENDARIO GRID
foreach ($horarios_db as $h) {
    if (!$h['hora_inicio'] || !$h['hora_fin'] || !$h['dias_patron']) continue;

    // COLOR POR CLAVE DEL GRUPO (Cada clase tiene su identidad)
    if (!isset($colores_asignados[$h['clave_grupo']])) {
        $colores_asignados[$h['clave_grupo']] = $paleta[$color_index % count($paleta)];
        $color_index++;
    }
    $color = $colores_asignados[$h['clave_grupo']];

    $hora_ini = (int)date('H', strtotime($h['hora_inicio']));
    $hora_fin = (int)date('H', strtotime($h['hora_fin']));
    $min_fin = (int)date('i', strtotime($h['hora_fin']));
    if ($min_fin > 0) $hora_fin++; 

    $fila_inicio = ($hora_ini - 7) + 2; 
    $fila_fin = ($hora_fin - 7) + 2;

    $dias = descifrarDias($h['dias_patron']);
    $icono = ($h['modalidad'] == 'PRESENCIAL') ? '<i class="fas fa-building"></i>' : '<i class="fas fa-laptop-house"></i>';
    $titulo_completo = $h['materia'] . (!empty($h['nivel']) ? ' ' . getRomanNumeralHorario($h['nivel']) : '');

    foreach ($dias as $columna_dia) {
        $bloques_render[] = [
            'col' => $columna_dia, 
            'row_ini' => $fila_inicio, 
            'row_fin' => $fila_fin, 
            'color' => $color,
            'titulo' => $titulo_completo, 
            'tiempo' => date('H:i', strtotime($h['hora_inicio'])) . ' - ' . date('H:i', strtotime($h['hora_fin'])),
            'aula' => $h['aula'], 
            'icono' => $icono, 
            'modalidad' => $h['modalidad'],
            'clave' => $h['clave_grupo']
        ];
    }
}
