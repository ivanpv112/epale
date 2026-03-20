<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') { header("Location: ../index.php"); exit; }

$profesor_id = $_SESSION['user_id'];
$clave_grupo = $_GET['clave'] ?? ''; // RECIBIMOS LA CLAVE

if (!$clave_grupo) { header("Location: mis_grupos.php"); exit; }

$mensaje = '';

// 1. OBTENER INFORMACIÓN (AHORA INCLUYE g.estado)
$stmt_info = $pdo->prepare("SELECT m.materia_id, m.nombre AS materia, m.nivel, m.clave, c.nombre AS ciclo, g.edicion_total, g.estado,
                                   MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END) AS nrc_presencial,
                                   MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END) AS nrc_virtual
                            FROM grupos g
                            JOIN materias m ON g.materia_id = m.materia_id 
                            JOIN ciclos c ON c.ciclo_id = g.ciclo_id 
                            LEFT JOIN horarios h ON g.nrc = h.nrc
                            WHERE g.clave_grupo = ? AND g.profesor_id = ?
                            GROUP BY g.clave_grupo, m.materia_id, c.ciclo_id, g.profesor_id, m.nombre, m.nivel, m.clave, c.nombre, g.edicion_total, g.estado");
$stmt_info->execute([$clave_grupo, $profesor_id]);
$info_grupo = $stmt_info->fetch(PDO::FETCH_ASSOC);

if (!$info_grupo) { header("Location: mis_grupos.php"); exit; }

$edicion_total = $info_grupo['edicion_total'] ?? 0;
$grupo_cerrado = ($info_grupo['estado'] === 'CERRADO');
$materia_id = $info_grupo['materia_id']; 

$txt_nrc = '';
if ($info_grupo['nrc_presencial']) $txt_nrc .= 'P: ' . $info_grupo['nrc_presencial'] . ' ';
if ($info_grupo['nrc_virtual']) $txt_nrc .= ($txt_nrc ? '| V: ' : 'V: ') . $info_grupo['nrc_virtual'];

// 2. GUARDAR CALIFICACIONES (Bloqueado por seguridad si la clase está cerrada)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'guardar_calificaciones' && !$grupo_cerrado) {
    $nuevas_calificaciones = $_POST['calificaciones'] ?? [];
    try {
        $pdo->beginTransaction();
        foreach ($nuevas_calificaciones as $insc_id => $notas) {
            foreach ($notas as $tipo_examen => $puntaje) {
                $check = $pdo->prepare("SELECT calificacion_id FROM calificaciones WHERE inscripcion_id = ? AND tipo_examen = ?");
                $check->execute([$insc_id, $tipo_examen]); $id_existente = $check->fetchColumn();

                if (trim($puntaje) === '') {
                    if ($id_existente) $pdo->prepare("DELETE FROM calificaciones WHERE calificacion_id = ?")->execute([$id_existente]);
                } else {
                    $puntaje_val = floatval($puntaje);
                    if ($id_existente) { $pdo->prepare("UPDATE calificaciones SET puntaje = ? WHERE calificacion_id = ?")->execute([$puntaje_val, $id_existente]); } 
                    else { $pdo->prepare("INSERT INTO calificaciones (inscripcion_id, tipo_examen, puntaje) VALUES (?, ?, ?)")->execute([$insc_id, $tipo_examen, $puntaje_val]); }
                }
            }
        }
        $pdo->commit(); $mensaje = "¡Calificaciones guardadas exitosamente!";
    } catch (Exception $e) { $pdo->rollBack(); $mensaje = "Error al guardar: " . $e->getMessage(); }
}

// 3. OBTENER CRITERIOS
$stmt_crit = $pdo->prepare("SELECT codigo_examen, nombre_examen, puntos_maximos, color, icono, categoria FROM criterios_evaluacion WHERE materia_id = ?");
$stmt_crit->execute([$materia_id]); $criterios = $stmt_crit->fetchAll(PDO::FETCH_ASSOC);

$orden_deseado = ['oral 1'=>4, 'oral 2'=>5, 'quiz 1'=>1, 'qz 1'=>1, 'qz1'=>1, 'quiz 2'=>2, 'qz 2'=>2, 'qz2'=>2, 'quiz 3'=>3, 'qz 3'=>3, 'qz3'=>3, 'writing'=>6, 'plataforma'=>7, 'participaci'=>8, 'toefl'=>9];
usort($criterios, function($a, $b) use ($orden_deseado) {
    $nom_a = strtolower(trim($a['nombre_examen'])); $nom_b = strtolower(trim($b['nombre_examen']));
    $peso_a = 99; foreach ($orden_deseado as $key => $peso) { if (strpos($nom_a, $key) !== false) { $peso_a = $peso; break; } }
    $peso_b = 99; foreach ($orden_deseado as $key => $peso) { if (strpos($nom_b, $key) !== false) { $peso_b = $peso; break; } }
    if ($peso_a == $peso_b) return strcmp($nom_a, $nom_b); return $peso_a - $peso_b;
});

$lista_blanca = ['oral', 'writing', 'plataforma', 'participación', 'participacion']; $puntos_maximos_totales = 0;
foreach ($criterios as &$c) {
    $puntos_maximos_totales += $c['puntos_maximos']; $es_esencial = false; $nombre_lower = strtolower($c['nombre_examen']);
    foreach($lista_blanca as $palabra) { if (strpos($nombre_lower, $palabra) !== false) { $es_esencial = true; break; } }
    $c['bloqueado'] = ($edicion_total == 0 && !$es_esencial) ? true : false;
}
unset($c);

// 4. OBTENER ALUMNOS
$sql_alum = "SELECT i.inscripcion_id, u.codigo, u.nombre, u.apellido_paterno, u.apellido_materno, a.carrera
             FROM inscripciones i
             JOIN grupos g ON i.nrc = g.nrc
             JOIN alumnos a ON i.alumno_id = a.alumno_id
             JOIN usuarios u ON a.usuario_id = u.usuario_id
             WHERE g.clave_grupo = ? AND g.profesor_id = ? AND i.estatus = 'INSCRITO'
             ORDER BY u.apellido_paterno ASC";
$stmt_alum = $pdo->prepare($sql_alum); $stmt_alum->execute([$clave_grupo, $profesor_id]);
$alumnos = $stmt_alum->fetchAll(PDO::FETCH_ASSOC);

// 5. OBTENER CALIFICACIONES
$calificaciones_actuales = [];
if (count($alumnos) > 0) {
    $inscripciones_ids = array_column($alumnos, 'inscripcion_id'); $in_clause = implode(',', array_fill(0, count($inscripciones_ids), '?'));
    $stmt_cal = $pdo->prepare("SELECT inscripcion_id, tipo_examen, puntaje FROM calificaciones WHERE inscripcion_id IN ($in_clause)");
    $stmt_cal->execute($inscripciones_ids);
    while ($row = $stmt_cal->fetch(PDO::FETCH_ASSOC)) { $calificaciones_actuales[$row['inscripcion_id']][$row['tipo_examen']] = $row['puntaje']; }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificar Grupo | Portal Docente</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/profesor.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <a href="<?php echo htmlspecialchars($url_volver); ?>" style="color: var(--udg-blue); text-decoration: none; font-weight: bold; display: flex; align-items: center; gap: 8px;"><i class="fas fa-arrow-left"></i> Volver a Mis Grupos</a>
            <div style="text-align: right;">
                <h1 style="margin: 0; color: var(--udg-blue); font-size: 1.5rem;"><?php echo htmlspecialchars($info_grupo['materia'] . ' ' . $info_grupo['nivel']); ?></h1>
                <p style="margin: 2px 0 0 0; color: #666; font-size: 0.9rem;">
                    <span style="font-family: monospace; font-weight: bold; color: #555;">NRC <?php echo htmlspecialchars($txt_nrc); ?></span><br>
                    <i class="far fa-calendar-alt"></i> Semestre <?php echo htmlspecialchars($info_grupo['ciclo']); ?> &nbsp;|&nbsp; <i class="fas fa-users"></i> <?php echo count($alumnos); ?> Alumnos
                </p>
            </div>
        </div>

        <?php if($grupo_cerrado): ?>
            <div class="alert" style="background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
                <i class="fas fa-archive" style="font-size: 1.8rem;"></i>
                <div><strong>Clase Finalizada</strong><br><span style="font-size: 0.9rem;">Esta clase ha sido cerrada por la administración. Las calificaciones son de solo lectura.</span></div>
            </div>
        <?php elseif($edicion_total == 0): ?>
            <div class="alert" style="background: #e7f3ff; color: #004085; border: 1px solid #b8daff; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
                <i class="fas fa-lock" style="font-size: 1.8rem; color: #0056b3;"></i>
                <div><strong>Control Escolar Restringido</strong><br><span style="font-size: 0.9rem;">Como docente, puedes calificar proyectos, actividades y participaciones. La captura de exámenes (Qz1, Qz2, Qz3, TOEFL) es manejada por Control Escolar.</span></div>
            </div>
        <?php endif; ?>

        <?php if($mensaje): ?><div class="alert alert-success" style="margin-bottom: 20px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 15px; border-radius: 8px;"><i class="fas fa-check-circle"></i> <?php echo $mensaje; ?></div><?php endif; ?>

        <?php if (count($criterios) === 0): ?><div class="alert" style="background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 20px; border-radius: 8px; text-align: center;"><i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i><strong>No hay criterios de evaluación definidos.</strong></div>
        <?php elseif (count($alumnos) === 0): ?><div class="content-card" style="text-align: center; padding: 50px 20px; color: #888;"><i class="fas fa-ghost" style="font-size: 3rem; color: #ddd; margin-bottom: 15px; display: block;"></i><h3>Grupo Vacío</h3></div>
        <?php else: ?>
            <form method="POST" action="detalle_grupo.php?clave=<?php echo $clave_grupo; ?>" id="formCalificaciones">
                <input type="hidden" name="action" value="guardar_calificaciones">
                <div style="display: flex; justify-content: space-between; align-items: center; background: <?php echo $grupo_cerrado ? '#6c757d' : '#001a57'; ?>; padding: 15px 20px; border-top-left-radius: 12px; border-top-right-radius: 12px;">
                    <div style="color: white; font-weight: bold;"><i class="fas fa-edit"></i> Hoja de Calificaciones <?php if($grupo_cerrado) echo "(Solo Lectura)"; ?></div>
                    <?php if(!$grupo_cerrado): ?>
                        <button type="submit" class="btn-save" style="background: #28a745; box-shadow: none;"><i class="fas fa-save"></i> Guardar Cambios</button>
                    <?php endif; ?>
                </div>

                <div class="excel-table-wrapper" style="border-top-left-radius: 0; border-top-right-radius: 0; margin-bottom: 0;">
                    <table class="excel-table">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <?php foreach($criterios as $c): ?>
                                    <th title="<?php echo htmlspecialchars($c['nombre_examen']); ?>">
                                        <i class="fas <?php echo $c['icono']; ?>" style="color: <?php echo $grupo_cerrado ? '#aaa' : $c['color']; ?>; display: block; font-size: 1.2rem; margin-bottom: 5px;"></i>
                                        <div style="max-width: 90px; overflow: hidden; text-overflow: ellipsis; margin: 0 auto;"><?php echo htmlspecialchars($c['nombre_examen']); ?>
                                            <?php if($c['bloqueado'] || $grupo_cerrado) echo ' <i class="fas fa-lock" style="color:#aaa; font-size:0.75rem;" title="Manejado por Control Escolar"></i>'; ?>
                                        </div>
                                        <span style="font-weight: normal; color: #aaa; font-size: 0.75rem;">Máx: <?php echo floatval($c['puntos_maximos']); ?></span>
                                    </th>
                                <?php endforeach; ?>
                                <th style="background: #e7f3ff; color: var(--udg-blue);"><i class="fas fa-calculator" style="display: block; font-size: 1.2rem; margin-bottom: 5px;"></i>TOTAL<br><span style="font-weight: normal; font-size: 0.75rem;">/ <?php echo $puntos_maximos_totales; ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($alumnos as $index => $a): $insc_id = $a['inscripcion_id']; $suma_alumno = 0; ?>
                                <tr>
                                    <td><div style="font-weight: bold; color: var(--udg-blue);"><?php echo htmlspecialchars($a['apellido_paterno'] . ' ' . $a['apellido_materno'] . ' ' . $a['nombre']); ?></div><div style="font-size: 0.8rem; color: #888; font-family: monospace; margin-top: 3px;">Cód: <?php echo htmlspecialchars($a['codigo']); ?></div></td>
                                    <?php foreach($criterios as $c): 
                                        $cod_examen = $c['codigo_examen']; $max_pts = floatval($c['puntos_maximos']);
                                        $val_actual = isset($calificaciones_actuales[$insc_id][$cod_examen]) ? floatval($calificaciones_actuales[$insc_id][$cod_examen]) : '';
                                        if($val_actual !== '') $suma_alumno += $val_actual;
                                        
                                        // BLOQUEO LÓGICO Y VISUAL
                                        $bloqueado_total = $c['bloqueado'] || $grupo_cerrado;
                                        $readonly_attr = $bloqueado_total ? 'readonly tabindex="-1"' : ''; 
                                        $class_attr = $bloqueado_total ? 'grade-input grade-locked js-grade-input' : 'grade-input js-grade-input';
                                    ?>
                                        <td>
                                            <?php if($bloqueado_total && !$grupo_cerrado): ?>
                                                <input type="hidden" name="calificaciones[<?php echo $insc_id; ?>][<?php echo $cod_examen; ?>]" value="<?php echo $val_actual; ?>">
                                            <?php endif; ?>
                                            
                                            <input type="number" step="0.01" min="0" max="<?php echo $max_pts; ?>" <?php if(!$bloqueado_total): ?>name="calificaciones[<?php echo $insc_id; ?>][<?php echo $cod_examen; ?>]"<?php endif; ?> value="<?php echo $val_actual; ?>" class="<?php echo $class_attr; ?>" data-insc="<?php echo $insc_id; ?>" <?php echo $readonly_attr; ?> <?php if($bloqueado_total) echo 'title="Calificación Bloqueada o Cerrada"'; ?>>
                                        </td>
                                    <?php endforeach; ?>
                                    <td style="background: #f8fbff;"><div class="total-cell js-total-<?php echo $insc_id; ?>"><?php echo number_format($suma_alumno, 1); ?></div></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if(!$grupo_cerrado): ?>
                <div style="background: #f8f9fa; padding: 15px 20px; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; border: 1px solid #eee; border-top: none; text-align: right;">
                    <button type="submit" class="btn-save" style="font-size: 1.1rem; padding: 10px 25px;"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.js-grade-input'); const totalMaximo = <?php echo $puntos_maximos_totales; ?>; const puntajeMinimoAprobatorio = 60; 
            function recalcularTotal(insc_id) {
                const studentInputs = document.querySelectorAll(`.js-grade-input[data-insc="${insc_id}"]`); let suma = 0;
                studentInputs.forEach(input => { const val = parseFloat(input.value); if (!isNaN(val)) suma += val; });
                const displayCell = document.querySelector(`.js-total-${insc_id}`); displayCell.innerText = suma.toFixed(1);
                let porcentaje = (totalMaximo > 0) ? (suma / totalMaximo) * 100 : 0;
                displayCell.classList.remove('total-aprobado', 'total-reprobado');
                if (porcentaje >= puntajeMinimoAprobatorio) displayCell.classList.add('total-aprobado'); else displayCell.classList.add('total-reprobado');
            }
            inputs.forEach(input => {
                if (!input.hasAttribute('readonly')) {
                    input.addEventListener('input', function() {
                        const maxAllowed = parseFloat(this.getAttribute('max')); if (parseFloat(this.value) > maxAllowed) this.value = maxAllowed; if (parseFloat(this.value) < 0) this.value = 0;
                        const insc_id = this.getAttribute('data-insc'); recalcularTotal(insc_id);
                    });
                }
                const insc_id = input.getAttribute('data-insc'); recalcularTotal(insc_id);
            });
        });
    </script>
</body>
</html>
