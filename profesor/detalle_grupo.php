<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'PROFESOR') { header("Location: ../index.php"); exit; }

// Generación de Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$profesor_id = $_SESSION['user_id'];
$clave_grupo = $_GET['clave'] ?? ''; 

if (!$clave_grupo) { header("Location: mis_grupos.php"); exit; }

// ... [TODO EL CÓDIGO PHP DE CONSULTAS PERMANECE EXACTAMENTE IGUAL AL ANTERIOR] ...
$stmt_info = $pdo->prepare("SELECT m.materia_id, m.nombre AS materia, m.nivel, c.nombre AS ciclo, g.edicion_total, g.estado FROM grupos g JOIN materias m ON g.materia_id = m.materia_id JOIN ciclos c ON c.ciclo_id = g.ciclo_id WHERE g.clave_grupo = ? AND g.profesor_id = ? LIMIT 1");
$stmt_info->execute([$clave_grupo, $profesor_id]);
$info_grupo = $stmt_info->fetch(PDO::FETCH_ASSOC);
if (!$info_grupo) { header("Location: mis_grupos.php"); exit; }

$edicion_total = (int)$info_grupo['edicion_total'];
$grupo_cerrado = ($info_grupo['estado'] === 'CERRADO');
$materia_id = $info_grupo['materia_id']; 

$stmt_hor = $pdo->prepare("SELECT g.nrc, h.modalidad, h.aula FROM grupos g LEFT JOIN horarios h ON g.nrc = h.nrc WHERE g.clave_grupo = ? AND g.profesor_id = ?");
$stmt_hor->execute([$clave_grupo, $profesor_id]);
$horarios = $stmt_hor->fetchAll(PDO::FETCH_ASSOC);
$txt_nrc_aula = '';
foreach ($horarios as $h) {
    if ($h['modalidad'] === 'PRESENCIAL') { $aula = !empty($h['aula']) ? $h['aula'] : 'Sin asignar'; $txt_nrc_aula .= "P: {$h['nrc']} (Aula: $aula) "; } 
    elseif ($h['modalidad'] === 'VIRTUAL') { $aula = !empty($h['aula']) ? $h['aula'] : 'Virtual'; $txt_nrc_aula .= "| V: {$h['nrc']} (Aula: $aula)"; }
}
$txt_nrc_aula = trim($txt_nrc_aula, " |");

$stmt_crit = $pdo->prepare("SELECT codigo_examen, nombre_examen, puntos_maximos, color, icono, categoria FROM criterios_evaluacion WHERE materia_id = ?");
$stmt_crit->execute([$materia_id]); 
$criterios = $stmt_crit->fetchAll(PDO::FETCH_ASSOC);

usort($criterios, function($a, $b) {
    $cod_a = strtoupper($a['codigo_examen']); $cod_b = strtoupper($b['codigo_examen']);
    $get_peso = function($cod) {
        if (strpos($cod, 'Q1') !== false || strpos($cod, 'Q2') !== false || strpos($cod, 'Q3') !== false) return 1;
        if (strpos($cod, 'QO') !== false) return 2;
        if (strpos($cod, 'WRITING') !== false) return 3;
        if (strpos($cod, 'PARTICIPACION') !== false) return 4;
        if (strpos($cod, 'PLATAFORMA') !== false) return 5;
        if (strpos($cod, 'CERTIFICACION') !== false || strpos($cod, 'FINAL') !== false) return 6;
        return 7; 
    };
    $peso_a = $get_peso($cod_a); $peso_b = $get_peso($cod_b);
    if ($peso_a == $peso_b) { return strnatcasecmp($a['nombre_examen'], $b['nombre_examen']); }
    return $peso_a - $peso_b;
});

$permitidos_profesor = ['QO', 'WRITING', 'PARTICIPACION']; 
$puntos_maximos_totales = 0;
foreach ($criterios as &$c) {
    $puntos_maximos_totales += floatval($c['puntos_maximos']); 
    $es_esencial = false; $cod_upper = strtoupper($c['codigo_examen']);
    foreach($permitidos_profesor as $palabra) { if (strpos($cod_upper, $palabra) !== false) { $es_esencial = true; break; } }
    $c['bloqueado'] = ($grupo_cerrado || ($edicion_total === 0 && !$es_esencial)) ? true : false;
}
unset($c);

$sql_alum = "SELECT i.inscripcion_id, u.codigo, u.nombre, u.apellido_paterno, u.apellido_materno, u.foto_perfil FROM inscripciones i JOIN alumnos a ON i.alumno_id = a.alumno_id JOIN usuarios u ON a.usuario_id = u.usuario_id WHERE i.nrc IN (SELECT nrc FROM grupos WHERE clave_grupo = ? AND profesor_id = ?) AND i.estatus = 'INSCRITO' ORDER BY u.apellido_paterno ASC, u.apellido_materno ASC, u.nombre ASC";
$stmt_alum = $pdo->prepare($sql_alum); 
$stmt_alum->execute([$clave_grupo, $profesor_id]);
$alumnos = $stmt_alum->fetchAll(PDO::FETCH_ASSOC);

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
    <style>
        @keyframes savedFlash { 0% { background-color: #d4edda; } 100% { background-color: white; } }
        .flash-success { animation: savedFlash 1.5s ease-out; }
    </style>
</head>
<body>

    <?php include 'menu_profesor.php'; ?>

    <main class="main-content">
        <!-- ... [TODO EL HTML PERMANECE IGUAL, DESDE LA CABECERA HASTA LA TABLA] ... -->
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="margin: 0; color: var(--udg-blue); font-size: 1.8rem;"><?php echo htmlspecialchars($info_grupo['materia'] . ' ' . $info_grupo['nivel']); ?></h1>
                <p style="margin: 5px 0 0 0; color: #666; font-size: 0.95rem;">
                    <span style="font-family: monospace; font-weight: bold; color: #555;">NRC <?php echo htmlspecialchars($txt_nrc_aula); ?></span><br>
                    <i class="far fa-calendar-alt" style="margin-top:5px;"></i> Semestre <?php echo htmlspecialchars($info_grupo['ciclo']); ?> &nbsp;|&nbsp; 
                    <i class="fas fa-users"></i> <?php echo count($alumnos); ?> Alumnos
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="asistencia.php?clave=<?php echo urlencode($clave_grupo); ?>" class="btn-save" style="background:var(--udg-light); color:white; text-decoration:none; padding:10px 20px; border-radius:8px; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-calendar-check"></i> Lista de Asistencia
                </a>
            </div>
        </div>

        <?php if($grupo_cerrado): ?>
            <div class="alert" style="background: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
                <i class="fas fa-archive" style="font-size: 1.8rem;"></i>
                <div><strong>Clase Finalizada</strong><br><span style="font-size: 0.9rem;">Esta clase ha sido cerrada por la administración. Las calificaciones son de solo lectura.</span></div>
            </div>
        <?php elseif($edicion_total === 0): ?>
            <div class="alert" style="background: #e7f3ff; color: #004085; border: 1px solid #b8daff; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px;">
                <i class="fas fa-lock" style="font-size: 1.8rem; color: #0056b3;"></i>
                <div><strong>Control Escolar Restringido</strong><br><span style="font-size: 0.9rem;">Solo puedes capturar las evaluaciones de tu competencia (Proyectos, Orales, Participación). Los exámenes principales y plataforma son capturados por administración.</span></div>
            </div>
        <?php endif; ?>

        <?php if (count($criterios) === 0): ?>
            <div class="alert" style="background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 20px; border-radius: 8px; text-align: center;"><i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i><strong>No hay criterios de evaluación definidos para esta materia.</strong></div>
        <?php elseif (count($alumnos) === 0): ?>
            <div class="content-card" style="text-align: center; padding: 50px 20px; color: #888;"><i class="fas fa-ghost" style="font-size: 3rem; color: #ddd; margin-bottom: 15px; display: block;"></i><h3>El grupo no tiene alumnos inscritos</h3></div>
        <?php else: ?>
            <div style="display: flex; justify-content: space-between; align-items: center; background: <?php echo $grupo_cerrado ? '#6c757d' : '#001a57'; ?>; padding: 15px 20px; border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <div style="color: white; font-weight: bold;"><i class="fas fa-edit"></i> Hoja de Calificaciones <?php if($grupo_cerrado) echo "(Solo Lectura)"; else echo "En Vivo"; ?></div>
                <?php if(!$grupo_cerrado): ?><div id="saveStatus" style="color: #a0d8ff; font-size: 0.85rem; display: flex; align-items: center; gap: 5px;"><i class="fas fa-cloud-upload-alt"></i> Guardado automático activo</div><?php endif; ?>
            </div>

            <div class="excel-table-wrapper-profe" style="border-top-left-radius: 0; border-top-right-radius: 0; margin-bottom: 30px;">
                <table class="excel-table">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <?php foreach($criterios as $c): ?>
                                <th title="<?php echo htmlspecialchars($c['nombre_examen'] ?? ''); ?>">
                                    <i class="fas <?php echo htmlspecialchars($c['icono'] ?? 'fa-star'); ?>" style="color: <?php echo ($grupo_cerrado || $c['bloqueado']) ? '#aaa' : htmlspecialchars($c['color'] ?? ''); ?>; display: block; font-size: 1.2rem; margin-bottom: 5px;"></i>
                                    <div style="max-width: 90px; overflow: hidden; text-overflow: ellipsis; margin: 0 auto;"><?php echo htmlspecialchars($c['nombre_examen'] ?? ''); ?><?php if($c['bloqueado'] || $grupo_cerrado) echo ' <i class="fas fa-lock" style="color:#aaa; font-size:0.75rem;" title="Manejado por Control Escolar"></i>'; ?></div>
                                    <span style="font-weight: normal; color: #aaa; font-size: 0.75rem;">Máx: <?php echo floatval($c['puntos_maximos'] ?? 0); ?></span>
                                </th>
                            <?php endforeach; ?>
                            <th style="background: #e7f3ff; color: var(--udg-blue);"><i class="fas fa-calculator" style="display: block; font-size: 1.2rem; margin-bottom: 5px;"></i>TOTAL<br><span style="font-weight: normal; font-size: 0.75rem;">/ <?php echo $puntos_maximos_totales; ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($alumnos as $a): 
                            $insc_id = $a['inscripcion_id']; $suma_alumno = 0; $nombre_seguro = trim(($a['apellido_paterno'] ?? '') . ' ' . ($a['apellido_materno'] ?? '') . ' ' . ($a['nombre'] ?? ''));
                            $foto_url = "../img/avatar-default.png"; if (!empty($a['foto_perfil']) && file_exists("../img/perfiles/" . $a['foto_perfil'])) { $foto_url = "../img/perfiles/" . $a['foto_perfil']; }
                        ?>
                            <tr>
                                <td style="padding: 10px 15px; min-width: 250px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <img src="<?php echo htmlspecialchars($foto_url); ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #ddd;">
                                        <div>
                                            <div style="font-weight: bold; color: var(--udg-blue);"><?php echo htmlspecialchars($nombre_seguro); ?></div>
                                            <div style="font-size: 0.8rem; color: #888; font-family: monospace; margin-top: 2px;">Código: <?php echo htmlspecialchars($a['codigo'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <?php foreach($criterios as $c): 
                                    $cod_examen = $c['codigo_examen']; $max_pts = floatval($c['puntos_maximos'] ?? 0); $val_actual = '';
                                    if(isset($calificaciones_actuales[$insc_id]) && isset($calificaciones_actuales[$insc_id][$cod_examen])) { $val_actual = floatval($calificaciones_actuales[$insc_id][$cod_examen]); $suma_alumno += $val_actual; }
                                    $bloqueado_total = $c['bloqueado'] || $grupo_cerrado; $readonly_attr = $bloqueado_total ? 'readonly tabindex="-1"' : ''; $class_attr = $bloqueado_total ? 'grade-input grade-locked js-grade-input' : 'grade-input js-grade-input';
                                ?>
                                    <td><input type="number" step="0.01" min="0" max="<?php echo $max_pts; ?>" value="<?php echo htmlspecialchars((string)$val_actual); ?>" class="<?php echo $class_attr; ?>" data-insc="<?php echo htmlspecialchars((string)$insc_id); ?>" data-examen="<?php echo htmlspecialchars((string)$cod_examen); ?>" <?php echo $readonly_attr; ?> <?php if($bloqueado_total) echo 'title="Calificación Bloqueada o Cerrada"'; ?>></td>
                                <?php endforeach; ?>
                                <td style="background: #f8fbff;"><div class="total-cell js-total-<?php echo htmlspecialchars((string)$insc_id); ?>"><?php echo number_format($suma_alumno, 1); ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // VARIABLE GLOBAL DEL CSRF TOKEN GENERADA EN PHP
        const csrfToken = "<?php echo $_SESSION['csrf_token']; ?>";

        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.js-grade-input'); 
            const totalMaximo = <?php echo $puntos_maximos_totales; ?>; 
            const puntajeMinimoAprobatorio = 60; 
            const saveStatusText = document.getElementById('saveStatus');

            function recalcularTotal(insc_id) {
                const studentInputs = document.querySelectorAll(`.js-grade-input[data-insc="${insc_id}"]`); let suma = 0;
                studentInputs.forEach(input => { const val = parseFloat(input.value); if (!isNaN(val)) suma += val; });
                const displayCell = document.querySelector(`.js-total-${insc_id}`); 
                if(displayCell) {
                    displayCell.innerText = suma.toFixed(1);
                    let porcentaje = (totalMaximo > 0) ? (suma / totalMaximo) * 100 : 0;
                    displayCell.classList.remove('total-aprobado', 'total-reprobado');
                    if (porcentaje >= puntajeMinimoAprobatorio) displayCell.classList.add('total-aprobado'); else displayCell.classList.add('total-reprobado');
                }
            }

            inputs.forEach(input => {
                if (!input.hasAttribute('readonly')) {
                    input.addEventListener('input', function() {
                        const maxAllowed = parseFloat(this.getAttribute('max')); 
                        if (parseFloat(this.value) > maxAllowed) this.value = maxAllowed; 
                        if (parseFloat(this.value) < 0) this.value = 0;
                        recalcularTotal(this.getAttribute('data-insc'));
                    });

                    input.addEventListener('change', function() {
                        const insc_id = this.getAttribute('data-insc');
                        const cod_examen = this.getAttribute('data-examen');
                        const val = this.value;
                        const cell = this;

                        if (saveStatusText) saveStatusText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                        
                        // SE AÑADE EL TOKEN CSRF AL CUERPO DEL JSON
                        fetch('calificaciones_api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                action: 'save_single', 
                                inscripcion_id: insc_id, 
                                tipo_examen: cod_examen, 
                                puntaje: val,
                                csrf_token: csrfToken
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if(data.success) {
                                if (saveStatusText) saveStatusText.innerHTML = '<i class="fas fa-check-circle" style="color:#28a745;"></i> Guardado';
                                cell.classList.remove('flash-success'); void cell.offsetWidth; cell.classList.add('flash-success');
                                setTimeout(() => { if (saveStatusText) saveStatusText.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Guardado automático activo'; }, 2000);
                            } else {
                                alert('Error al guardar: ' + data.error);
                            }
                        })
                        .catch(err => { alert('Problema de conexión al guardar.'); });
                    });
                }
                recalcularTotal(input.getAttribute('data-insc'));
            });
        });
    </script>
</body>
</html>
