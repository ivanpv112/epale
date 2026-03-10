<?php
session_start();
require '../db.php';

// SEGURIDAD: Solo Profesores
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') { header("Location: ../index.php"); exit; }
$profesor_id = $_SESSION['user_id'];

// 1. OBTENER TODAS LAS CLASES ACTIVAS DEL PROFESOR
$sql_horarios = "SELECT m.materia_id, m.nombre as materia, c.nombre as ciclo, g.clave_grupo,
                        h.modalidad, h.dias_patron, h.hora_inicio, h.hora_fin, h.aula
                 FROM grupos g
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                 JOIN horarios h ON g.nrc = h.nrc
                 WHERE g.profesor_id = ? AND c.activo = 1
                 ORDER BY h.hora_inicio ASC";
                 
$stmt_h = $pdo->prepare($sql_horarios);
$stmt_h->execute([$profesor_id]);
$horarios_db = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

$ciclo_actual = count($horarios_db) > 0 ? $horarios_db[0]['ciclo'] : 'Sin ciclo activo';

// 2. FUNCIÓN PARA DESCIFRAR DÍAS 
function descifrarDias($cadena) {
    $columnas = []; $s = strtoupper($cadena);
    if(strpos($s, 'LUN')!==false) $columnas[] = 2; if(strpos($s, 'MAR')!==false) $columnas[] = 3; if(strpos($s, 'MIE')!==false || strpos($s, 'MIÉ')!==false) $columnas[] = 4; if(strpos($s, 'JUE')!==false) $columnas[] = 5; if(strpos($s, 'VIE')!==false) $columnas[] = 6;
    if(empty($columnas)) {
        if(strpos($s, 'L')!==false) $columnas[] = 2; if(strpos($s, 'M')!==false) $columnas[] = 3; if(strpos($s, 'I')!==false || strpos($s, 'X')!==false || strpos($s, 'W')!==false) $columnas[] = 4; if(strpos($s, 'J')!==false) $columnas[] = 5; if(strpos($s, 'V')!==false) $columnas[] = 6;
    }
    return array_unique($columnas);
}

// 3. PALETA DE COLORES PASTEL
$paleta = [
    ['bg' => '#e7f3ff', 'border' => '#8bb9ff', 'text' => '#0056b3'], // Azul
    ['bg' => '#e6f8ec', 'border' => '#89dfa9', 'text' => '#0f5132'], // Verde
    ['bg' => '#fff3cd', 'border' => '#ffe69c', 'text' => '#664d03'], // Amarillo
    ['bg' => '#f8d7da', 'border' => '#f1aeb5', 'text' => '#842029'], // Rosa
    ['bg' => '#f3e8ff', 'border' => '#c29ffa', 'text' => '#432874'], // Morado
    ['bg' => '#cff4fc', 'border' => '#9eeaf9', 'text' => '#055160']  // Cyan
];

$colores_asignados = []; $color_index = 0; $bloques_render = [];

// 4. CALCULAR EL CALENDARIO GRID
foreach ($horarios_db as $h) {
    if (!$h['hora_inicio'] || !$h['hora_fin'] || !$h['dias_patron']) continue;

    // Asigna el mismo color a la misma materia para identificarlas fácil
    if (!isset($colores_asignados[$h['materia_id']])) {
        $colores_asignados[$h['materia_id']] = $paleta[$color_index % count($paleta)];
        $color_index++;
    }
    $color = $colores_asignados[$h['materia_id']];

    $hora_ini = (int)date('H', strtotime($h['hora_inicio']));
    $hora_fin = (int)date('H', strtotime($h['hora_fin']));
    $min_fin = (int)date('i', strtotime($h['hora_fin']));
    if ($min_fin > 0) $hora_fin++; 

    $fila_inicio = ($hora_ini - 7) + 2; 
    $fila_fin = ($hora_fin - 7) + 2;

    $dias = descifrarDias($h['dias_patron']);
    $icono = ($h['modalidad'] == 'PRESENCIAL') ? '<i class="fas fa-building" style="color:#28a745;"></i>' : '<i class="fas fa-laptop-house" style="color:#17a2b8;"></i>';

    foreach ($dias as $columna_dia) {
        $bloques_render[] = [
            'col' => $columna_dia, 'row_ini' => $fila_inicio, 'row_fin' => $fila_fin, 'color' => $color,
            'titulo' => $h['materia'], 'tiempo' => date('H:i', strtotime($h['hora_inicio'])) . ' - ' . date('H:i', strtotime($h['hora_fin'])),
            'aula' => $h['aula'], 'icono' => $icono, 'clave' => $h['clave_grupo']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Horario | Portal Docente</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profesor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: var(--udg-blue); margin: 0; font-size: 2.2rem;"><i class="far fa-calendar-alt"></i> Mi Horario Docente</h1>
            <p style="color: #666; font-size: 1.1rem; margin-top: 5px;">Semestre <?php echo htmlspecialchars($ciclo_actual); ?></p>
        </div>

        <div class="schedule-wrapper">
            <div class="schedule-grid">
                
                <div class="grid-header" style="grid-column: 1; grid-row: 1;"><i class="far fa-clock" style="font-size: 1.2rem;"></i></div>
                <div class="grid-header" style="grid-column: 2; grid-row: 1;">Lunes</div>
                <div class="grid-header" style="grid-column: 3; grid-row: 1;">Martes</div>
                <div class="grid-header" style="grid-column: 4; grid-row: 1;">Miércoles</div>
                <div class="grid-header" style="grid-column: 5; grid-row: 1;">Jueves</div>
                <div class="grid-header" style="grid-column: 6; grid-row: 1;">Viernes</div>

                <?php for ($h = 7; $h <= 19; $h++): 
                    $fila = ($h - 7) + 2; $hora_str = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                ?>
                    <div class="time-label" style="grid-row: <?php echo $fila; ?>;"><?php echo $hora_str; ?></div>
                    <?php for($col = 2; $col <= 6; $col++): ?>
                        <div class="grid-line" style="grid-column: <?php echo $col; ?>; grid-row: <?php echo $fila; ?>;"></div>
                    <?php endfor; ?>
                <?php endfor; ?>

                <?php foreach ($bloques_render as $b): ?>
                    <a href="detalle_grupo.php?clave=<?php echo $b['clave']; ?>" class="class-block" style="
                        grid-column: <?php echo $b['col']; ?>; 
                        grid-row: <?php echo $b['row_ini']; ?> / <?php echo $b['row_fin']; ?>;
                        background-color: <?php echo $b['color']['bg']; ?>;
                        border-left-color: <?php echo $b['color']['border']; ?>;
                        color: <?php echo $b['color']['text']; ?>;
                    " title="Ver lista de alumnos">
                        <div class="class-title"><?php echo htmlspecialchars($b['titulo']); ?></div>
                        <div class="class-details"><?php echo $b['icono']; ?> <?php echo htmlspecialchars($b['aula'] ?: 'Sin Aula'); ?></div>
                        <div class="class-details" style="font-weight: normal;"><i class="far fa-clock"></i> <?php echo $b['tiempo']; ?></div>
                    </a>
                <?php endforeach; ?>

            </div>
        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Portal de Profesores</div></footer>
    <script>function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }</script>
</body>
</html>