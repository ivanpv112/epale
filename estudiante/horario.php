<?php
require_once 'horario_logic.php';

/**
 * Declaración de variables importadas desde horario_logic.php
 * para evitar falsos positivos de "Undefined variable" en el IDE.
 * 
 * @var string $ciclo_actual
 * @var array $bloques_render
 * @var array $horarios_db
 */
?>


<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Horario | e-PALE</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content">

        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: var(--udg-blue); margin: 0; font-size: 2rem;">
                <i class="far fa-calendar-alt"></i> Mi Horario
            </h1>
            <p style="color: var(--text-muted); font-size: 1.1rem; margin-top: 5px;">Ciclo <?php echo htmlspecialchars($ciclo_actual); ?></p>
        </div>

        <div class="schedule-wrapper">
            <div class="schedule-grid">

                <div class="grid-header" style="grid-column: 1; grid-row: 1;"><i class="far fa-clock" style="font-size: 1.2rem;"></i></div>
                <div class="grid-header" style="grid-column: 2; grid-row: 1;">Lunes</div>
                <div class="grid-header" style="grid-column: 3; grid-row: 1;">Martes</div>
                <div class="grid-header" style="grid-column: 4; grid-row: 1;">Miércoles</div>
                <div class="grid-header" style="grid-column: 5; grid-row: 1;">Jueves</div>
                <div class="grid-header" style="grid-column: 6; grid-row: 1;">Viernes</div>
                <div class="grid-header" style="grid-column: 7; grid-row: 1;">Sábado</div>

                <?php for ($h = 7; $h <= 19; $h++):
                    $fila = ($h - 7) + 2;
                    $hora_str = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                ?>
                    <div class="time-label" style="grid-row: <?php echo $fila; ?>;"><?php echo $hora_str; ?></div>

                    <?php for ($col = 2; $col <= 7; $col++): ?>
                        <div class="grid-line" style="grid-column: <?php echo $col; ?>; grid-row: <?php echo $fila; ?>;"></div>
                    <?php endfor; ?>
                <?php endfor; ?>

                <?php foreach ($bloques_render as $b): 
                    $is_skeleton = ($b['estatus'] === 'SOLICITUD_ALTA');
                    $bg_color = $is_skeleton ? 'transparent' : $b['color']['bg'];
                    $border_color = $is_skeleton ? 'rgba(108, 117, 125, 0.4)' : $b['color']['border'];
                    $border_style = $is_skeleton ? 'dashed' : 'solid';
                    $text_color = $is_skeleton ? 'var(--text-muted)' : $b['color']['text'];
                    $opacity = $is_skeleton ? '0.7' : '1';
                ?>
                    <div class="class-block" style="
                        grid-column: <?php echo $b['col']; ?>; 
                        grid-row: <?php echo $b['row_ini']; ?> / <?php echo $b['row_fin']; ?>;
                        background-color: <?php echo $bg_color; ?>;
                        border-left-color: <?php echo $border_color; ?>;
                        border-left-style: <?php echo $border_style; ?>;
                        color: <?php echo $text_color; ?>;
                        opacity: <?php echo $opacity; ?>;
                        <?php if($is_skeleton) echo 'border: 2px dashed ' . $border_color . '; border-left-width: 5px;'; ?>
                    ">
                        <?php if($is_skeleton): ?>
                            <div style="font-size: 0.7rem; color: #ffc107; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; display: flex; align-items: center; gap: 4px;">
                                <i class="fas fa-hourglass-half"></i> Pendiente
                            </div>
                        <?php endif; ?>
                        <div class="class-modality-badge"><?php echo htmlspecialchars($b['modalidad']); ?></div>
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

    <?php include '../main_footer.php'; ?>
</body>

</html>