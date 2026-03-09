<?php
session_start();
require '../db.php';

// SEGURIDAD: Solo alumnos
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}

// 1. Obtener el ID de alumno
$stmt_alumno = $pdo->prepare("SELECT alumno_id FROM alumnos WHERE usuario_id = ?");
$stmt_alumno->execute([$_SESSION['user_id']]);
$alumno_id = $stmt_alumno->fetchColumn();

// 2. Obtener todas las materias inscritas en el ciclo ACTIVO
$sql_horarios = "SELECT m.materia_id, m.nombre as materia, c.nombre as ciclo, 
                        h.modalidad, h.dias_patron, h.hora_inicio, h.hora_fin, h.aula
                 FROM inscripciones i
                 JOIN grupos g ON i.nrc = g.nrc
                 JOIN materias m ON g.materia_id = m.materia_id
                 JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                 JOIN horarios h ON g.nrc = h.nrc
                 WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO' AND c.activo = 1
                 ORDER BY h.hora_inicio ASC";
                 
$stmt_h = $pdo->prepare($sql_horarios);
$stmt_h->execute([$alumno_id]);
$horarios_db = $stmt_h->fetchAll(PDO::FETCH_ASSOC);

$ciclo_actual = count($horarios_db) > 0 ? $horarios_db[0]['ciclo'] : 'Sin ciclo activo';

// 3. Función inteligente para descifrar los días (Ej. 'L-M' -> Lunes y Martes)
function descifrarDias($cadena) {
    $columnas = [];
    $s = strtoupper($cadena);
    
    // Mapeo directo si usan nombres completos
    if(strpos($s, 'LUN')!==false) $columnas[] = 2; // Col 2 es Lunes
    if(strpos($s, 'MAR')!==false) $columnas[] = 3; // Col 3 es Martes
    if(strpos($s, 'MIE')!==false || strpos($s, 'MIÉ')!==false) $columnas[] = 4; // Col 4 es Miércoles
    if(strpos($s, 'JUE')!==false) $columnas[] = 5; // Col 5 es Jueves
    if(strpos($s, 'VIE')!==false) $columnas[] = 6; // Col 6 es Viernes

    // Si no usaron nombres completos, buscamos letras
    if(empty($columnas)) {
        if(strpos($s, 'L')!==false) $columnas[] = 2;
        if(strpos($s, 'M')!==false) $columnas[] = 3; 
        if(strpos($s, 'I')!==false || strpos($s, 'X')!==false || strpos($s, 'W')!==false) $columnas[] = 4; // Miércoles a veces es I, X o W
        if(strpos($s, 'J')!==false) $columnas[] = 5;
        if(strpos($s, 'V')!==false) $columnas[] = 6;
    }
    return array_unique($columnas);
}

// 4. Paleta de colores pastel (Igual a tu boceto digital)
$paleta = [
    ['bg' => '#e7f3ff', 'border' => '#8bb9ff', 'text' => '#0056b3'], // Azul
    ['bg' => '#e6f8ec', 'border' => '#89dfa9', 'text' => '#0f5132'], // Verde
    ['bg' => '#fff3cd', 'border' => '#ffe69c', 'text' => '#664d03'], // Amarillo
    ['bg' => '#f8d7da', 'border' => '#f1aeb5', 'text' => '#842029'], // Rojo/Rosa
    ['bg' => '#f3e8ff', 'border' => '#c29ffa', 'text' => '#432874'], // Morado
    ['bg' => '#cff4fc', 'border' => '#9eeaf9', 'text' => '#055160']  // Cyan
];

$colores_asignados = [];
$color_index = 0;
$bloques_render = [];

// Preparar los bloques para el calendario
foreach ($horarios_db as $h) {
    if (!$h['hora_inicio'] || !$h['hora_fin'] || !$h['dias_patron']) continue;

    // Asignar color consistente por materia
    if (!isset($colores_asignados[$h['materia_id']])) {
        $colores_asignados[$h['materia_id']] = $paleta[$color_index % count($paleta)];
        $color_index++;
    }
    $color = $colores_asignados[$h['materia_id']];

    // Calcular filas del Grid CSS
    // El calendario empieza a las 07:00 (fila 2). Entonces las 08:00 es la fila 3.
    $hora_ini = (int)date('H', strtotime($h['hora_inicio']));
    $hora_fin = (int)date('H', strtotime($h['hora_fin']));
    $min_fin = (int)date('i', strtotime($h['hora_fin']));
    
    // Si la clase termina a y media (ej. 11:55), redondeamos a la siguiente línea (12:00)
    if ($min_fin > 0) $hora_fin++; 

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
            'titulo' => $h['materia'],
            'tiempo' => date('H:i', strtotime($h['hora_inicio'])) . ' - ' . date('H:i', strtotime($h['hora_fin'])),
            'aula' => $h['aula'],
            'icono' => $icono,
            'modalidad' => $h['modalidad']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Horario | e-PALE</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ESTILOS DEL CALENDARIO GRID */
        .schedule-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            padding: 20px;
            overflow-x: auto;
        }

        .schedule-grid {
            display: grid;
            grid-template-columns: 70px repeat(5, minmax(180px, 1fr));
            /* 14 filas: 1 cabecera + 13 horas (de 07:00 a 20:00) */
            grid-template-rows: 50px repeat(13, 65px);
            min-width: 900px;
            position: relative;
        }

        /* Líneas y Cabeceras */
        .grid-header {
            background-color: var(--udg-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .grid-header:first-child { border-top-left-radius: 8px; }
        .grid-header:last-child { border-top-right-radius: 8px; border-right: none; }

        .time-label {
            grid-column: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 10px;
            font-size: 0.85rem;
            color: #888;
            border-right: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }

        .grid-line {
            grid-column: 2 / -1;
            border-bottom: 1px solid #f1f3f5;
            border-right: 1px solid #f9f9f9;
        }

        /* Bloques de Clase */
        .class-block {
            margin: 4px;
            padding: 10px 12px;
            border-radius: 8px;
            border-left: 5px solid;
            font-size: 0.85rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
            z-index: 10;
        }
        .class-block:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
            z-index: 20;
        }
        .class-title { font-weight: bold; font-size: 0.95rem; margin-bottom: 4px; }
        .class-details { opacity: 0.8; font-family: monospace; display: flex; align-items: center; gap: 5px; margin-top: 3px; }
    </style>
</head>
<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content">

        <a href="<?php echo htmlspecialchars($url_volver); ?>" style="display: inline-block; margin-bottom: 20px; color: var(--udg-blue); text-decoration: none; font-weight: bold;">
            <i class="fas fa-arrow-left"></i> Volver a la página anterior
        </a>
        
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: var(--udg-blue); margin: 0; font-size: 2rem;">
                <i class="far fa-calendar-alt"></i> Mi Horario
            </h1>
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
                    $fila = ($h - 7) + 2; 
                    $hora_str = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                ?>
                    <div class="time-label" style="grid-row: <?php echo $fila; ?>;"><?php echo $hora_str; ?></div>
                    
                    <?php for($col = 2; $col <= 6; $col++): ?>
                        <div class="grid-line" style="grid-column: <?php echo $col; ?>; grid-row: <?php echo $fila; ?>;"></div>
                    <?php endfor; ?>
                <?php endfor; ?>

                <?php foreach ($bloques_render as $b): ?>
                    <div class="class-block" style="
                        grid-column: <?php echo $b['col']; ?>; 
                        grid-row: <?php echo $b['row_ini']; ?> / <?php echo $b['row_fin']; ?>;
                        background-color: <?php echo $b['color']['bg']; ?>;
                        border-left-color: <?php echo $b['color']['border']; ?>;
                        color: <?php echo $b['color']['text']; ?>;
                    ">
                        <div class="class-title"><?php echo htmlspecialchars($b['titulo']); ?></div>
                        
                        <div class="class-details" style="font-weight: bold;">
                            <?php echo $b['icono']; ?> <?php echo htmlspecialchars($b['aula'] ?: 'Sin Aula'); ?>
                        </div>
                        
                        <div class="class-details">
                            <i class="far fa-clock"></i> <?php echo $b['tiempo']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Portal de Estudiantes</div></footer>
</body>
</html>