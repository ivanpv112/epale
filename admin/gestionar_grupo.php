<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: ../index.php"); exit;
}

// Variables iniciales
$mat = $_GET['mat'] ?? '';
$prof = $_GET['prof'] ?? '';
$ciclo = $_GET['ciclo'] ?? '';
$es_edicion = (!empty($mat) && !empty($prof) && !empty($ciclo));

$mensaje = '';
$tipo_mensaje = '';

// ==========================================
// PROCESAMIENTO DE FORMULARIOS (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. DAR DE BAJA A UN ALUMNO DEL GRUPO (Sin borrar calificaciones)
    if (isset($_POST['action']) && $_POST['action'] === 'remove_student') {
        $alumno_quitar = $_POST['alumno_id'];
        $nrc_grupo = $_POST['nrc_base'];
        // Cambiamos a 'BAJA' en lugar de borrar el registro
        $pdo->prepare("UPDATE inscripciones SET estatus = 'BAJA' WHERE alumno_id = ? AND nrc = ?")->execute([$alumno_quitar, $nrc_grupo]);
        $mensaje = "El alumno fue dado de baja."; $tipo_mensaje = "success";
    }
    
    // 2. INSCRIBIR A UN NUEVO ALUMNO (O Reactivar)
    if (isset($_POST['action']) && $_POST['action'] === 'add_student') {
        $nuevo_alumno_id = $_POST['nuevo_alumno_id'];
        $nrc_grupo = $_POST['nrc_base'];
        $cupo_actual = $_POST['cupo_actual'];
        $inscritos_actuales = $_POST['inscritos_actuales'];

        if (empty($nuevo_alumno_id) || !is_numeric($nuevo_alumno_id)) {
            $mensaje = "Debes seleccionar un alumno válido de la lista desplegable."; $tipo_mensaje = "error";
        } elseif ($inscritos_actuales >= $cupo_actual) {
            $mensaje = "Error: El grupo ya está lleno. Aumenta el cupo máximo primero."; $tipo_mensaje = "error";
        } else {
            // Revisar si ya existe un registro (para saber si es nuevo o estaba dado de baja)
            $check = $pdo->prepare("SELECT estatus FROM inscripciones WHERE alumno_id = ? AND nrc = ?");
            $check->execute([$nuevo_alumno_id, $nrc_grupo]);
            $registro_existente = $check->fetch(PDO::FETCH_ASSOC);

            if ($registro_existente) {
                if ($registro_existente['estatus'] === 'INSCRITO') {
                    $mensaje = "El alumno ya estaba inscrito en este grupo."; $tipo_mensaje = "error";
                } else {
                    // Estaba dado de baja, lo reactivamos
                    $pdo->prepare("UPDATE inscripciones SET estatus = 'INSCRITO' WHERE alumno_id = ? AND nrc = ?")->execute([$nuevo_alumno_id, $nrc_grupo]);
                    $mensaje = "Alumno re-inscrito correctamente."; $tipo_mensaje = "success";
                }
            } else {
                // Es un alumno completamente nuevo en la clase
                $pdo->prepare("INSERT INTO inscripciones (alumno_id, nrc, estatus) VALUES (?, ?, 'INSCRITO')")->execute([$nuevo_alumno_id, $nrc_grupo]);
                $mensaje = "Alumno inscrito correctamente."; $tipo_mensaje = "success";
            }
        }
    }
}
// ==========================================
// OBTENER DATOS PARA LA VISTA
// ==========================================
$list_profesores = $pdo->query("SELECT usuario_id, nombre, apellido_paterno FROM usuarios WHERE rol='PROFESOR' AND estatus='ACTIVO'")->fetchAll(PDO::FETCH_ASSOC);
$list_materias = $pdo->query("SELECT materia_id, clave, nombre FROM materias ORDER BY clave")->fetchAll(PDO::FETCH_ASSOC);
$list_ciclos = $pdo->query("SELECT ciclo_id, nombre FROM ciclos ORDER BY nombre DESC")->fetchAll(PDO::FETCH_ASSOC);

// Alumnos para el buscador (Ahora traemos la carrera también)
$list_alumnos = $pdo->query("SELECT a.alumno_id, u.codigo, u.nombre, u.apellido_paterno, u.apellido_materno, a.carrera FROM alumnos a JOIN usuarios u ON a.usuario_id = u.usuario_id WHERE u.estatus='ACTIVO' ORDER BY u.nombre")->fetchAll(PDO::FETCH_ASSOC);

$g = [];
$alumnos_inscritos = [];
$total_inscritos = 0;
$nrc_base = '';

if ($es_edicion) {
    // 1. Traer información del Grupo y del Profesor
    $sql = "SELECT m.materia_id, m.nombre AS materia, u.usuario_id AS profesor_id, u.nombre AS prof_nombre, u.apellido_paterno AS prof_ap, u.foto_perfil AS prof_foto, u.correo AS prof_correo, c.ciclo_id,
                   MAX(g.cupo) AS cupo,
                   MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END) AS nrc_presencial,
                   MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END) AS nrc_virtual,
                   MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.aula END) AS aula_presencial,
                   MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.aula END) AS aula_virtual,
                   MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.dias_patron END) AS dias_presencial,
                   MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.dias_patron END) AS dias_virtual,
                   MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_inicio END) AS inicio_presencial,
                   MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_inicio END) AS inicio_virtual,
                   MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN h.hora_fin END) AS fin_presencial,
                   MAX(CASE WHEN h.modalidad='VIRTUAL' THEN h.hora_fin END) AS fin_virtual
            FROM grupos g
            JOIN materias m ON g.materia_id = m.materia_id
            JOIN usuarios u ON g.profesor_id = u.usuario_id
            JOIN ciclos c ON g.ciclo_id = c.ciclo_id
            JOIN horarios h ON g.nrc = h.nrc
            WHERE g.materia_id=? AND g.profesor_id=? AND g.ciclo_id=?
            GROUP BY g.materia_id, g.profesor_id, g.ciclo_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mat, $prof, $ciclo]);
    $g = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$g) { header("Location: grupos_nrc.php"); exit; }

    $nrc_base = $g['nrc_presencial'] ?: $g['nrc_virtual'];

    // 2. Traer alumnos inscritos
    $sql_alum = "SELECT i.inscripcion_id, a.alumno_id, u.usuario_id, u.codigo, u.nombre, u.apellido_paterno, u.correo, a.carrera
                 FROM inscripciones i
                 JOIN alumnos a ON i.alumno_id = a.alumno_id
                 JOIN usuarios u ON a.usuario_id = u.usuario_id
                 WHERE i.nrc = ? AND i.estatus = 'INSCRITO'
                 ORDER BY u.apellido_paterno ASC";
    $stmt_alum = $pdo->prepare($sql_alum);
    $stmt_alum->execute([$nrc_base]);
    $alumnos_inscritos = $stmt_alum->fetchAll(PDO::FETCH_ASSOC);
    $total_inscritos = count($alumnos_inscritos);

    $foto_profesor = "../img/avatar-default.png";
    if($g['prof_foto'] && file_exists("../img/perfiles/" . $g['prof_foto'])) {
        $foto_profesor = "../img/perfiles/" . $g['prof_foto'];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Grupo | Admin</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .readonly-input { background-color: #f1f3f5 !important; cursor: not-allowed; color: #666; }
        
        .hover-lift { transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease; text-decoration: none; color: inherit; display: block; }
        .hover-lift:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-color: var(--udg-light) !important; }

        .student-row { display: flex; justify-content: space-between; align-items: stretch; background: white; border: 1px solid #eee; border-radius: 8px; margin-bottom: 10px; overflow: hidden; transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease; }
        .student-row:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); border-color: var(--udg-light); }
        .student-link { flex-grow: 1; padding: 12px 15px; text-decoration: none; color: inherit; }
        .student-action { padding: 12px 15px; display: flex; align-items: center; justify-content: center; background: #fafafa; border-left: 1px solid #eee; transition: background 0.2s; }
        .student-action:hover { background: #fde8e8; }

        /* ESTILOS DEL BUSCADOR INTELIGENTE */
        .custom-select-wrapper { position: relative; flex-grow: 1; }
        .custom-options { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-radius: 6px; max-height: 250px; overflow-y: auto; z-index: 1000; display: none; box-shadow: 0 5px 15px rgba(0,0,0,0.15); margin-top: 4px; }
        .custom-option { padding: 12px 15px; border-bottom: 1px solid #f1f3f5; cursor: pointer; transition: background 0.2s; }
        .custom-option:hover { background: #e7f3ff; }
        .custom-option:last-child { border-bottom: none; }
        .opt-name { font-weight: bold; color: var(--udg-blue); font-size: 0.95rem; margin-bottom: 3px; }
        .opt-details { font-size: 0.8rem; color: #666; font-family: monospace; display: flex; justify-content: space-between;}
    </style>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="<?php echo htmlspecialchars($url_volver); ?>" style="display: inline-block; margin-bottom: 20px; color: var(--udg-blue); text-decoration: none; font-weight: bold;">
                <i class="fas fa-arrow-left"></i> Volver a la página anterior
            </a>
            <?php if($es_edicion): ?>
                <span style="font-weight: bold; color: #555;">Gestionando: <span style="color: var(--udg-blue);"><?php echo htmlspecialchars($g['materia']); ?></span></span>
            <?php else: ?>
                <span style="font-weight: bold; color: #28a745;">Creando Nuevo Grupo</span>
            <?php endif; ?>
        </div>

        <?php if($mensaje): ?>
            <div class="alert <?php echo ($tipo_mensaje == 'success') ? 'alert-success' : 'alert-error'; ?>" style="margin-bottom: 20px; <?php echo ($tipo_mensaje == 'error') ? 'background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; padding:15px; border-radius:8px;' : ''; ?>">
                <i class="fas <?php echo ($tipo_mensaje == 'success') ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i> <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1.3fr; gap: 20px; align-items: start;">
            
            <div>
                <?php if($es_edicion): ?>
                    <a href="ver_expediente.php?id=<?php echo $g['profesor_id']; ?>" class="hover-lift" style="background: white; border: 1px solid #eee; border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                        <img src="<?php echo $foto_profesor; ?>" alt="Profesor" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid var(--udg-light);">
                        <div>
                            <div style="font-size: 0.8rem; color: #888; font-weight: bold; text-transform: uppercase;">Profesor Asignado</div>
                            <h3 style="margin: 0; color: var(--udg-blue); font-size: 1.1rem;"><?php echo htmlspecialchars($g['prof_nombre'] . ' ' . $g['prof_ap']); ?></h3>
                            <div style="font-size: 0.85rem; color: #666;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($g['prof_correo']); ?></div>
                        </div>
                    </a>

                    <div class="card" style="margin-top: 0; background: linear-gradient(135deg, var(--udg-blue) 0%, #001a57 100%); color: white; border: none; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 10px 0; color: white;"><i class="fas fa-user-plus"></i> Inscribir Alumno</h3>
                        <p style="font-size: 0.85rem; opacity: 0.8; margin-bottom: 15px;">Busca por nombre o código. (Cupo restante: <?php echo ($g['cupo'] - $total_inscritos); ?>)</p>
                        
                        <form method="POST" id="formAddStudent">
                            <input type="hidden" name="action" value="add_student">
                            <input type="hidden" name="nrc_base" value="<?php echo $nrc_base; ?>">
                            <input type="hidden" name="cupo_actual" value="<?php echo $g['cupo']; ?>">
                            <input type="hidden" name="inscritos_actuales" value="<?php echo $total_inscritos; ?>">
                            
                            <input type="hidden" name="nuevo_alumno_id" id="hiddenAlumnoId" required>
                            
                            <div style="display: flex; gap: 10px; align-items: stretch;">
                                <div class="custom-select-wrapper">
                                    <input type="text" id="searchInput" placeholder="Escribe el nombre o código..." style="width: 100%; height: 42px; padding: 10px 15px; border-radius: 6px; border: none; box-sizing: border-box; font-family: inherit;" autocomplete="off">
                                    
                                    <div class="custom-options" id="optionsContainer"></div>
                                </div>
                                <button type="submit" class="btn-save" style="background: #28a745; white-space: nowrap; height: 42px;"><i class="fas fa-plus"></i> Añadir</button>
                            </div>
                        </form>
                    </div>

                    <div class="card" style="margin-top: 0; padding: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px;">
                            <i class="fas fa-users"></i> Estudiantes (<?php echo $total_inscritos; ?>/<?php echo $g['cupo']; ?>)
                        </h3>
                        
                        <?php if(count($alumnos_inscritos) > 0): ?>
                            <div style="max-height: 400px; overflow-y: auto; padding-right: 5px;">
                                <?php foreach($alumnos_inscritos as $alum): ?>
                                    <div class="student-row">
                                        <a href="ver_expediente.php?id=<?php echo $alum['usuario_id']; ?>" class="student-link">
                                            <div style="font-weight: bold; color: var(--udg-blue); font-size: 0.95rem;"><?php echo htmlspecialchars($alum['nombre'] . ' ' . $alum['apellido_paterno']); ?></div>
                                            <div style="font-size: 0.8rem; color: #888; font-family: monospace; margin-top: 3px;">Cod: <?php echo htmlspecialchars($alum['codigo']); ?> | <?php echo htmlspecialchars($alum['carrera']); ?></div>
                                        </a>
                                        
                                        <div class="student-action">
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="action" value="remove_student">
                                                <input type="hidden" name="alumno_id" value="<?php echo $alum['alumno_id']; ?>">
                                                <input type="hidden" name="nrc_base" value="<?php echo $nrc_base; ?>">
                                                <button type="button" onclick="confirmarBajaAlumno(this, '<?php echo htmlspecialchars($alum['nombre']); ?>')" style="background: none; border: none; color: #dc3545; cursor: pointer; font-size: 1.2rem; padding: 5px;" title="Expulsar del grupo">
                                                    <i class="fas fa-user-minus"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: #aaa; padding: 20px;"><i class="fas fa-ghost" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>La clase está vacía.</p>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <div class="card" style="text-align: center; padding: 50px 20px; color: #888; border: 2px dashed #ddd; background: transparent;">
                        <i class="fas fa-users-slash" style="font-size: 3rem; margin-bottom: 15px; color: #ccc;"></i>
                        <h3 style="color: #555;">Grupo Nuevo</h3>
                        <p style="font-size: 0.9rem;">Llena la configuración a la derecha y haz clic en "Crear Grupo" para que puedas comenzar a asignarle alumnos.</p>
                    </div>
                <?php endif; ?>
            </div>


            <div>
                <form method="POST" action="gestionar_grupo.php?mat=<?php echo $mat; ?>&prof=<?php echo $prof; ?>&ciclo=<?php echo $ciclo; ?>" id="configForm">
                    <input type="hidden" name="action" value="save_group">
                    <input type="hidden" name="inscritos_actuales" value="<?php echo $total_inscritos; ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div class="card" style="margin: 0; border-top: 4px solid #28a745;">
                            <h4 style="margin: 0 0 15px 0; color: #28a745;"><i class="fas fa-building"></i> Presencial</h4>
                            <div class="form-group">
                                <label>NRC</label>
                                <input type="number" name="rnc_presencial" value="<?php echo $es_edicion ? $g['nrc_presencial'] : ''; ?>" <?php if($es_edicion && $g['nrc_presencial']) echo 'readonly class="readonly-input" title="El NRC no se puede cambiar"'; ?> placeholder="Ej. 60495">
                            </div>
                            <div class="form-group">
                                <label>Aula</label>
                                <input type="text" name="aula_presencial" value="<?php echo $es_edicion ? $g['aula_presencial'] : ''; ?>" placeholder="Ej. A-202">
                            </div>
                            <div class="form-group">
                                <label>Días</label>
                                <input type="text" name="dias_presencial" value="<?php echo $es_edicion ? $g['dias_presencial'] : ''; ?>" placeholder="Ej. L-M" maxlength="5">
                            </div>
                            <div class="form-group" style="display: flex; gap: 5px; margin-bottom: 0;">
                                <div style="flex:1"><label>De</label><input type="time" name="inicio_presencial" value="<?php echo $es_edicion ? $g['inicio_presencial'] : ''; ?>"></div>
                                <div style="flex:1"><label>A</label><input type="time" name="fin_presencial" value="<?php echo $es_edicion ? $g['fin_presencial'] : ''; ?>"></div>
                            </div>
                        </div>

                        <div class="card" style="margin: 0; border-top: 4px solid #17a2b8;">
                            <h4 style="margin: 0 0 15px 0; color: #17a2b8;"><i class="fas fa-laptop-house"></i> Virtual</h4>
                            <div class="form-group">
                                <label>NRC</label>
                                <input type="number" name="rnc_virtual" value="<?php echo $es_edicion ? $g['nrc_virtual'] : ''; ?>" <?php if($es_edicion && $g['nrc_virtual']) echo 'readonly class="readonly-input"'; ?> placeholder="Ej. 60501">
                            </div>
                            <div class="form-group">
                                <label>Plataforma</label>
                                <input type="text" name="aula_virtual" value="<?php echo $es_edicion ? $g['aula_virtual'] : ''; ?>" placeholder="Ej. Zoom">
                            </div>
                            <div class="form-group">
                                <label>Días</label>
                                <input type="text" name="dias_virtual" value="<?php echo $es_edicion ? $g['dias_virtual'] : ''; ?>" placeholder="Ej. M-J" maxlength="5">
                            </div>
                            <div class="form-group" style="display: flex; gap: 5px; margin-bottom: 0;">
                                <div style="flex:1"><label>De</label><input type="time" name="inicio_virtual" value="<?php echo $es_edicion ? $g['inicio_virtual'] : ''; ?>"></div>
                                <div style="flex:1"><label>A</label><input type="time" name="fin_virtual" value="<?php echo $es_edicion ? $g['fin_virtual'] : ''; ?>"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="margin: 0; padding-bottom: 5px;">
                        <h3 style="margin: 0 0 15px 0; border-bottom: 1px solid #eee; padding-bottom: 10px; color: var(--udg-blue);">
                            <i class="fas fa-sliders-h"></i> Configuración del Grupo
                        </h3>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                            <div class="form-group">
                                <label>Cambiar Profesor Asignado</label>
                                <select name="profesor_id" required>
                                    <option value="">Selecciona al docente...</option>
                                    <?php foreach($list_profesores as $p): ?>
                                        <option value="<?php echo $p['usuario_id']; ?>" <?php if($es_edicion && $g['profesor_id'] == $p['usuario_id']) echo 'selected'; ?>><?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido_paterno']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div class="form-group">
                                    <label>Materia</label>
                                    <select name="materia_id" required>
                                        <?php foreach($list_materias as $m): ?>
                                            <option value="<?php echo $m['materia_id']; ?>" <?php if($es_edicion && $g['materia_id'] == $m['materia_id']) echo 'selected'; ?>><?php echo htmlspecialchars($m['clave'] . ' - ' . $m['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Ciclo Escolar</label>
                                    <select name="ciclo_id" required>
                                        <?php foreach($list_ciclos as $c): ?>
                                            <option value="<?php echo $c['ciclo_id']; ?>" <?php if($es_edicion && $g['ciclo_id'] == $c['ciclo_id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Capacidad Máxima (Cupo)</label>
                                <input type="number" name="cupo" min="<?php echo $es_edicion ? $total_inscritos : 1; ?>" value="<?php echo $es_edicion ? $g['cupo'] : 30; ?>" required style="font-weight: bold; color: var(--udg-blue); font-size: 1.1rem;">
                                <?php if($es_edicion): ?>
                                    <small style="color: #888;">No puedes reducir el cupo a menos de <?php echo $total_inscritos; ?> (alumnos actuales).</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <a href="<?php echo htmlspecialchars($url_volver); ?>" class="btn-cancel" style="text-decoration: none; flex: 1; text-align: center; display: flex; align-items: center; justify-content: center;">Cancelar</a>
                        <button type="submit" class="btn-save" style="flex: 2; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; gap: 10px;">
                            <i class="fas fa-save"></i> <?php echo $es_edicion ? 'Guardar Cambios' : 'Crear Grupo'; ?>
                        </button>
                    </div>
                </form>
            </div>
            
        </div>
    </main>

    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }

        // ==========================================
        // LÓGICA DEL BUSCADOR INTELIGENTE EN JAVASCRIPT
        // ==========================================
        <?php if($es_edicion): ?>
        
        // Pasamos todos los alumnos de PHP a una variable Javascript
        const alumnosData = <?php echo json_encode($list_alumnos, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        const searchInput = document.getElementById('searchInput');
        const optionsContainer = document.getElementById('optionsContainer');
        const hiddenInput = document.getElementById('hiddenAlumnoId');

        // Función que dibuja las opciones de la lista
        function renderOptions(filterText = '') {
            optionsContainer.innerHTML = '';
            const lowerFilter = filterText.toLowerCase();

            // Filtramos por coincidencia de nombre o código
            const filtered = alumnosData.filter(a => {
                const fullName = `${a.nombre} ${a.apellido_paterno} ${a.apellido_materno || ''}`.toLowerCase();
                const code = (a.codigo || '').toLowerCase();
                return fullName.includes(lowerFilter) || code.includes(lowerFilter);
            });

            if (filtered.length === 0) {
                optionsContainer.innerHTML = '<div style="padding:15px; color:#888; text-align:center;">No se encontró ningún estudiante</div>';
                optionsContainer.style.display = 'block';
                return;
            }

            // Dibujar cada resultado con su diseño
            filtered.forEach(a => {
                const div = document.createElement('div');
                div.className = 'custom-option';
                
                const fullName = `${a.nombre} ${a.apellido_paterno} ${a.apellido_materno || ''}`;
                
                div.innerHTML = `
                    <div class="opt-name">${fullName}</div>
                    <div class="opt-details">
                        <span>Cód: ${a.codigo || 'N/A'}</span>
                        <span>${a.carrera || 'N/A'}</span>
                    </div>
                `;
                
                // Cuando el admin da clic a un alumno de la lista:
                div.onclick = () => {
                    searchInput.value = fullName; // Ponemos el nombre en la barra
                    hiddenInput.value = a.alumno_id; // GUARDAMOS EL ID REAL (Evita el error)
                    optionsContainer.style.display = 'none'; // Escondemos la lista
                };
                optionsContainer.appendChild(div);
            });
            optionsContainer.style.display = 'block';
        }

        // ==========================================
        // ALERTA PARA DAR DE BAJA
        // ==========================================
        function confirmarBajaAlumno(btn, nombreAlumno) {
            Swal.fire({
                title: '¿Dar de baja?',
                // TEXTO:
                html: `Estás a punto de dar de baja a <b>${nombreAlumno}</b> de la clase activa. <br><br><small style="color:#666;">Sus calificaciones previas seguirán a salvo en su expediente.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, dar de baja',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    btn.closest('form').submit();
                }
            });
        }

        // Eventos del buscador
        searchInput.addEventListener('input', (e) => {
            hiddenInput.value = ''; // Si el usuario vuelve a escribir, borramos el ID anterior
            renderOptions(e.target.value);
        });

        // Mostrar lista completa si da clic en la caja y está vacía
        searchInput.addEventListener('focus', () => {
            renderOptions(searchInput.value);
        });

        // Esconder la lista si da clic afuera
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !optionsContainer.contains(e.target)) {
                optionsContainer.style.display = 'none';
            }
        });
        
        <?php endif; ?>
    </script>
</body>
</html>