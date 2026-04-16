<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ALUMNO') {
    header("Location: ../index.php"); exit;
}

// CREACIÓN SILENCIOSA DE TABLAS (Por si no se han creado)
try { 
    $pdo->exec("CREATE TABLE IF NOT EXISTS certificaciones (
        certificacion_id INT AUTO_INCREMENT PRIMARY KEY, 
        alumno_id INT, 
        idioma VARCHAR(50), 
        nivel_obtenido VARCHAR(50), 
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"); 
    $pdo->exec("CREATE TABLE IF NOT EXISTS examenes_diagnosticos (
        examen_id INT AUTO_INCREMENT PRIMARY KEY, 
        alumno_id INT, 
        idioma VARCHAR(50), 
        calificacion_texto VARCHAR(50),
        nivel_asignado INT,
        fecha_realizacion DATE,
        periodo VARCHAR(20),
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch(Exception $e) {}

$mensaje_exito = "";
$mensaje_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_perfil'])) {
    $nuevo_correo = trim($_POST['correo']);
    $nuevo_telefono = trim($_POST['telefono']);

    if (!empty($nuevo_correo)) {
        try {
            $sql_update = "UPDATE usuarios SET correo = ?, telefono = ? WHERE usuario_id = ?";
            $pdo->prepare($sql_update)->execute([$nuevo_correo, $nuevo_telefono, $_SESSION['user_id']]);
            $mensaje_exito = "¡Tu información se ha actualizado correctamente!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { 
                $mensaje_error = "Error: Ese correo electrónico ya está registrado en otra cuenta.";
            } else {
                $mensaje_error = "Ocurrió un error al actualizar los datos.";
            }
        }
    } else {
        $mensaje_error = "El correo electrónico no puede estar vacío.";
    }
}

if(isset($_GET['exito']) && $_GET['exito'] == 'foto') { $mensaje_exito = "¡Tu foto de perfil ha sido actualizada!"; }
if(isset($_GET['error'])) {
    if($_GET['error'] == 'ext') $mensaje_error = "Error: Solo se permiten imágenes JPG, PNG o WEBP.";
    else if($_GET['error'] == 'mime') $mensaje_error = "Error: El archivo no es una imagen válida.";
    else if($_GET['error'] == 'upload') $mensaje_error = "Error: La imagen es demasiado pesada.";
    else $mensaje_error = "Ocurrió un error al guardar la foto.";
}

$stmt = $pdo->prepare("SELECT u.*, a.carrera, a.alumno_id FROM usuarios u LEFT JOIN alumnos a ON u.usuario_id = a.usuario_id WHERE u.usuario_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$alumno_id = $user['alumno_id'];

$nombre_completo = $user['nombre'] . " " . (isset($user['apellido_paterno']) ? $user['apellido_paterno'] : $user['apellidos']) . (isset($user['apellido_materno']) && $user['apellido_materno'] ? ' ' . $user['apellido_materno'] : '');
$foto_perfil = "../img/avatar-default.png"; 
if(isset($user['foto_perfil']) && $user['foto_perfil'] && file_exists("../img/perfiles/" . $user['foto_perfil'])) { $foto_perfil = "../img/perfiles/" . $user['foto_perfil']; }

// ===============================================
// KÁRDEX: EXTRAER CLASES CERRADAS
// ===============================================
$sql_historial = "SELECT i.inscripcion_id, m.nombre as materia, m.nivel, c.nombre as ciclo, 
                         (SELECT SUM(puntaje) FROM calificaciones WHERE inscripcion_id = i.inscripcion_id) as calificacion_final
                  FROM inscripciones i
                  JOIN grupos g ON i.nrc = g.nrc
                  JOIN materias m ON g.materia_id = m.materia_id
                  JOIN ciclos c ON g.ciclo_id = c.ciclo_id
                  WHERE i.alumno_id = ? AND g.estado = 'CERRADO' AND i.estatus = 'INSCRITO'
                  ORDER BY c.nombre DESC, m.nivel DESC";
$stmt_hist = $pdo->prepare($sql_historial);
$stmt_hist->execute([$alumno_id]);
$historial = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

// ===============================================
// CERTIFICACIONES
// ===============================================
$sql_todas = "SELECT m.nombre as materia, m.nivel 
              FROM inscripciones i
              JOIN grupos g ON i.nrc = g.nrc
              JOIN materias m ON g.materia_id = m.materia_id
              WHERE i.alumno_id = ? AND i.estatus = 'INSCRITO'";
$stmt_todas = $pdo->prepare($sql_todas);
$stmt_todas->execute([$alumno_id]);
$todas_las_inscripciones = $stmt_todas->fetchAll(PDO::FETCH_ASSOC);

$idiomas_nivel_4 = [];
foreach($todas_las_inscripciones as $ins) {
    if ($ins['nivel'] >= 4) {
        $idiomas_nivel_4[$ins['materia']] = true;
    }
}
$idiomas_nivel_4 = array_keys($idiomas_nivel_4);

$certificaciones_bd = [];
if (count($idiomas_nivel_4) > 0) {
    $stmt_cert = $pdo->prepare("SELECT idioma, nivel_obtenido FROM certificaciones WHERE alumno_id = ?");
    $stmt_cert->execute([$alumno_id]);
    while($row = $stmt_cert->fetch(PDO::FETCH_ASSOC)) {
        $certificaciones_bd[$row['idioma']] = $row['nivel_obtenido'];
    }
}

// ===============================================
// EXÁMENES DIAGNÓSTICOS
// ===============================================
$stmt_diag = $pdo->prepare("SELECT * FROM examenes_diagnosticos WHERE alumno_id = ? ORDER BY fecha_realizacion DESC");
$stmt_diag->execute([$alumno_id]);
$examenes_diagnosticos = $stmt_diag->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Perfil | E-Pale</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .clickable-row { cursor: pointer; transition: background-color 0.2s; }
        .clickable-row:hover { background-color: #f1f8ff; }
    </style>
</head>
<body>

    <?php include 'menu_estudiante.php'; ?>

    <main class="main-content">
        
        <div class="profile-header-card">
            <div class="profile-photo-wrapper">
                <img src="<?php echo $foto_perfil; ?>" alt="Foto de Perfil" class="profile-photo-img">
                <label class="edit-photo-btn" onclick="abrirModalFoto()" title="Cambiar foto de perfil">
                    <i class="fas fa-camera"></i>
                </label>
            </div>
        </div>

        <div class="profile-basic-info">
            <h2 style="margin:0; color:var(--udg-blue);"><?php echo htmlspecialchars($nombre_completo); ?></h2>
            <span class="profile-role-badge"><i class="fas fa-graduation-cap"></i> Alumno</span>
            <p style="color:#666; margin-top:5px; font-size:0.9rem;"><?php echo htmlspecialchars($user['correo']); ?></p>
        </div>

        <?php if(!empty($mensaje_exito)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $mensaje_exito; ?></div>
        <?php endif; ?>
        <?php if(!empty($mensaje_error)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo $mensaje_error; ?></div>
        <?php endif; ?>

        <?php if(count($examenes_diagnosticos) > 0): ?>
            <div class="card" style="border-top: 4px solid #17a2b8; margin-bottom: 25px;">
                <h3 style="margin-top: 0; color: #17a2b8;"><i class="fas fa-clipboard-check" style="color: #17a2b8;"></i> Examen Diagnóstico</h3>
                <p style="font-size: 0.85rem; color: #666; margin-top: -10px; margin-bottom: 15px;">Resultados de tu evaluación inicial para la asignación de nivel.</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
                    <?php foreach($examenes_diagnosticos as $diag): ?>
                        <div style="border: 1px solid #eee; border-left: 4px solid #17a2b8; border-radius: 8px; padding: 20px; background: #f8f9fa; box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                <h4 style="margin: 0; color: var(--udg-blue); font-size: 1.15rem;"><?php echo htmlspecialchars($diag['idioma']); ?></h4>
                                <span style="background: #e7f3ff; color: var(--udg-blue); padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; border: 1px solid #b8daff;"><?php echo htmlspecialchars($diag['periodo']); ?></span>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 0.9rem; color: #555;">
                                <div><i class="fas fa-layer-group" style="color:#aaa;"></i> Nivel asignado: <strong style="color: var(--udg-blue); font-size: 1.05rem;"><?php echo htmlspecialchars($diag['nivel_asignado']); ?></strong></div>
                                <div><i class="fas fa-star" style="color:#aaa;"></i> Calif: <strong style="color:#333;"><?php echo htmlspecialchars($diag['calificacion_texto']); ?></strong></div>
                                <div style="grid-column: span 2;"><i class="far fa-calendar-alt" style="color:#aaa;"></i> Fecha de aplicación: <span style="color:#333; font-weight:500;"><?php echo date('d/m/Y', strtotime($diag['fecha_realizacion'])); ?></span></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if(count($idiomas_nivel_4) > 0): ?>
            <div class="card" style="border-top: 4px solid var(--udg-blue); margin-bottom: 25px;">
                <h3 style="margin-top: 0; color: var(--udg-blue);"><i class="fas fa-award" style="color: var(--udg-blue);"></i> Mis Certificaciones Oficiales</h3>
                <p style="font-size: 0.85rem; color: #666; margin-top: -10px; margin-bottom: 15px;">Niveles obtenidos en los idiomas en los que has cursado el Nivel 4 o superior.</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <?php foreach($idiomas_nivel_4 as $idioma): 
                        $nivel_obt = $certificaciones_bd[$idioma] ?? null;
                    ?>
                        <div style="border: 1px solid <?php echo $nivel_obt ? 'var(--udg-light)' : '#eee'; ?>; border-radius: 8px; padding: 20px; text-align: center; background: <?php echo $nivel_obt ? '#eaf4fc' : '#f8f9fa'; ?>; box-shadow: 0 2px 10px rgba(0,0,0,0.02); transition: 0.2s;">
                            <i class="fas fa-certificate" style="font-size: 2.5rem; color: <?php echo $nivel_obt ? 'var(--udg-light)' : '#ccc'; ?>; margin-bottom: 10px;"></i>
                            <h4 style="margin: 0 0 5px 0; color: <?php echo $nivel_obt ? 'var(--udg-blue)' : '#666'; ?>;"><?php echo htmlspecialchars($idioma); ?></h4>
                            <span style="font-size: 1.2rem; font-weight: bold; color: <?php echo $nivel_obt ? 'var(--udg-blue)' : '#888'; ?>;">
                                <?php echo $nivel_obt ? htmlspecialchars(strtoupper($nivel_obt)) : '<span style="font-size:0.8rem; font-weight:normal; font-style:italic;">Pendiente de registro</span>'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            
            <div class="card">
                <h3>
                    <i class="far fa-user"></i> Información Personal 
                    <button onclick="abrirModalEditar()" style="margin-left:auto; background:none; border:none; font-size:1.1rem; cursor:pointer; color:var(--udg-blue);" title="Editar Información">
                        <i class="fas fa-pen"></i>
                    </button>
                </h3>
                <div class="info-section">
                    <span class="info-label">Nombre Completo</span>
                    <span class="info-value"><?php echo htmlspecialchars($nombre_completo); ?></span>
                </div>
                <div class="info-section">
                    <span class="info-label">Correo Electrónico</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['correo']); ?></span>
                </div>
                <div class="info-section">
                    <span class="info-label">Teléfono</span>
                    <span class="info-value"><?php echo $user['telefono'] ? htmlspecialchars($user['telefono']) : '<span style="color:#aaa;">No registrado</span>'; ?></span>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-graduation-cap"></i> Información Académica</h3>
                <div class="info-section">
                    <span class="info-label">Código de Estudiante</span>
                    <span class="info-value"><?php echo isset($user['codigo']) && $user['codigo'] ? htmlspecialchars($user['codigo']) : '---'; ?></span>
                </div>
                <div class="info-section">
                    <span class="info-label">Carrera / Programa</span>
                    <span class="info-value"><?php echo isset($user['carrera']) && $user['carrera'] ? htmlspecialchars($user['carrera']) : 'Sin asignar'; ?></span>
                </div>
                <div class="info-section">
                    <span class="info-label">Estatus en el Sistema</span>
                    <span class="info-value" style="color: #28a745; font-weight: bold;">Estudiante Activo</span>
                </div>
            </div>
        </div>

        <div class="card">
            <h3><i class="fas fa-history"></i> Historial Académico (Kárdex)</h3>
            <p style="font-size: 0.85rem; color: #666; margin-top: -10px; margin-bottom: 15px;">Haz clic en cualquier materia para ver el desglose de tu calificación final.</p>
            <div style="overflow-x:auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Materia</th>
                            <th>Ciclo</th>
                            <th style="text-align: center;">Calificación Final</th>
                            <th style="text-align: center;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($historial) > 0): ?>
                            <?php foreach($historial as $h): 
                                $calif = floatval($h['calificacion_final']);
                            ?>
                                <tr class="clickable-row" onclick="window.location.href='calificaciones.php?ins=<?php echo $h['inscripcion_id']; ?>'">
                                    <td><?php echo htmlspecialchars($h['materia'] . ' ' . $h['nivel']); ?></td>
                                    <td><?php echo htmlspecialchars($h['ciclo']); ?></td>
                                    <td style="text-align: center; font-size: 1.1rem; color: var(--udg-blue);"><strong><?php echo $calif; ?></strong></td>
                                    <td style="text-align: center;">
                                        <div style="margin-bottom: 5px;"><span style="background: #e2e3e5; color: #383d41; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: bold;"><i class="fas fa-archive" style="font-size:0.6rem;"></i> Finalizada</span></div>
                                        <?php if($calif >= 60): ?>
                                            <span class="tag-aprobado" style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: bold;">Aprobado</span>
                                        <?php else: ?>
                                            <span class="tag-rechazada" style="background: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 10px; font-size: 0.8rem; font-weight: bold;">Reprobado</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; padding: 30px; color:#888;">Aún no tienes materias finalizadas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <footer class="main-footer">
        <div class="address-bar">
            Copyright © 2026 E-PALE
        </div>
    </footer>

    <div id="modalEditar" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Editar Información</h2>
                <button class="close-btn" onclick="cerrarModalEditar()">&times;</button>
            </div>
            <form method="POST" action="perfil.php">
                <input type="hidden" name="actualizar_perfil" value="1">
                <div class="modal-body" style="padding-top: 0; overflow-y: visible;">
                    <div class="form-group">
                        <label>Correo Electrónico</label>
                        <input type="email" name="correo" value="<?php echo htmlspecialchars($user['correo']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($user['telefono']); ?>" placeholder="Ej. 33 1234 5678">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModalEditar()">Cancelar</button>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalFoto" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Cambiar Foto de Perfil</h2>
                <button class="close-btn" onclick="cerrarModalFoto()">&times;</button>
            </div>
            <div class="modal-body" style="padding-top: 0; overflow-y: visible;">
                <p style="font-size: 0.9rem; color: #666; margin-bottom: 20px;">Por favor, selecciona una imagen cuadrada y de buena calidad (máx 5MB). Formatos permitidos: JPG, PNG, WEBP.</p>
                <form action="upload_foto.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <input type="file" name="foto_perfil" id="fileFoto" accept="image/*" required style="font-size: 0.9rem; padding: 0; border: none;">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" onclick="cerrarModalFoto()">Cancelar</button>
                        <button type="submit" class="btn-save" style="display:flex; align-items:center; gap:8px;"><i class="fas fa-upload"></i> Subir Foto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleMobileMenu() {
            document.getElementById('navWrapper').classList.toggle('active');
            document.getElementById('menuOverlay').classList.toggle('active');
        }

        const modalEditar = document.getElementById('modalEditar');
        const modalFoto = document.getElementById('modalFoto');
        const overlayMenu = document.getElementById('menuOverlay');
        
        function abrirModalEditar() { modalEditar.style.display = 'flex'; }
        function cerrarModalEditar() { modalEditar.style.display = 'none'; }
        function abrirModalFoto() { modalFoto.style.display = 'flex'; }
        function cerrarModalFoto() { modalFoto.style.display = 'none'; }

        window.onclick = function(e) { 
            if(e.target == modalEditar) cerrarModalEditar(); 
            if(e.target == modalFoto) cerrarModalFoto(); 
            if(e.target == overlayMenu) toggleMobileMenu(); 
        }
    </script>
</body>
</html>
