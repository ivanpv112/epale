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

// 1. OBTENER TODAS LAS CLASES ACTIVAS DEL PROFESOR
$sql_horarios = "SELECT m.materia_id, m.nombre as materia, c.nombre as ciclo, g.clave_grupo,
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

$ciclo_actual = count($horarios_db) > 0 ? $horarios_db[0]['ciclo'] : 'Sin ciclo activo';

// 2. FUNCIÓN PARA DESCIFRAR DÍAS 
if (!function_exists('descifrarDias')) {
    function descifrarDias(string $cadena): array {
        $columnas = []; $s = strtoupper($cadena);
        if(strpos($s, 'LUN')!==false) $columnas[] = 2; if(strpos($s, 'MAR')!==false) $columnas[] = 3; if(strpos($s, 'MIE')!==false || strpos($s, 'MIÉ')!==false) $columnas[] = 4; if(strpos($s, 'JUE')!==false) $columnas[] = 5; if(strpos($s, 'VIE')!==false) $columnas[] = 6;
        if(empty($columnas)) {
            if(strpos($s, 'L')!==false) $columnas[] = 2; if(strpos($s, 'M')!==false) $columnas[] = 3; if(strpos($s, 'I')!==false || strpos($s, 'X')!==false || strpos($s, 'W')!==false) $columnas[] = 4; if(strpos($s, 'J')!==false) $columnas[] = 5; if(strpos($s, 'V')!==false) $columnas[] = 6;
        }
        return array_unique($columnas);
    }
}

// 3. PALETA DE COLORES INTELIGENTE (Solo inyectamos la clase CSS)
$paleta = [
    'theme-blue',
    'theme-green',
    'theme-yellow',
    'theme-red',
    'theme-purple',
    'theme-cyan'
];

$colores_asignados = []; $color_index = 0; $bloques_render = [];

// 4. CALCULAR EL CALENDARIO GRID
foreach ($horarios_db as $h) {
    if (!$h['hora_inicio'] || !$h['hora_fin'] || !$h['dias_patron']) continue;

    // COLOR POR CLAVE DEL GRUPO (Hace cada clase independiente visualmente)
    if (!isset($colores_asignados[$h['clave_grupo']])) {
        $colores_asignados[$h['clave_grupo']] = $paleta[$color_index % count($paleta)];
        $color_index++;
    }
    $tema_css = $colores_asignados[$h['clave_grupo']];

    $hora_ini = (int)date('H', strtotime($h['hora_inicio']));
    $hora_fin = (int)date('H', strtotime($h['hora_fin']));
    $min_fin = (int)date('i', strtotime($h['hora_fin']));
    if ($min_fin > 0) $hora_fin++; 

    $fila_inicio = ($hora_ini - 7) + 2; 
    $fila_fin = ($hora_fin - 7) + 2;

    $dias = descifrarDias($h['dias_patron']);
    $icono = ($h['modalidad'] == 'PRESENCIAL') ? '<i class="fas fa-building"></i>' : '<i class="fas fa-laptop-house"></i>';

    foreach ($dias as $columna_dia) {
        $bloques_render[] = [
            'col' => $columna_dia, 'row_ini' => $fila_inicio, 'row_fin' => $fila_fin, 'tema' => $tema_css,
            'titulo' => $h['materia'], 'tiempo' => date('H:i', strtotime($h['hora_inicio'])) . ' - ' . date('H:i', strtotime($h['hora_fin'])),
            'aula' => $h['aula'], 'icono' => $icono, 'clave' => $h['clave_grupo']
        ];
    }
}
