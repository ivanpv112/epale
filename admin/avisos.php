<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') { header("Location: ../index.php"); exit; }

// CREAR LA TABLA SILENCIOSAMENTE SI NO EXISTE
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS avisos (
        aviso_id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(150),
        cuerpo TEXT,
        tipo_audiencia ENUM('GLOBAL', 'IDIOMA', 'MATERIA', 'GRUPO'),
        audiencia_ref VARCHAR(100),
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_expiracion DATETIME NULL
    )");
} catch (Exception $e) {}

$mensaje = ''; $tipo_mensaje = '';

// PROCESAR NUEVO AVISO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_aviso'])) {
    $titulo = trim($_POST['titulo']);
    $cuerpo = trim($_POST['cuerpo']);
    $audiencia_raw = explode('::', $_POST['audiencia']); 
    $tipo_audiencia = $audiencia_raw[0];
    $audiencia_ref = $audiencia_raw[1] ?? NULL;
    
    // NUEVA LÓGICA DE CADUCIDAD POR CALENDARIO
    $tipo_duracion = $_POST['tipo_duracion'];
    $fecha_expiracion = NULL; // Por defecto es indefinido
    
    if ($tipo_duracion === 'PERSONALIZADO' && !empty($_POST['fecha_expiracion_custom'])) {
        // Convertimos la fecha del formato del navegador (YYYY-MM-DDTHH:MM) al formato de MySQL
        $fecha_expiracion = date('Y-m-d H:i:s', strtotime($_POST['fecha_expiracion_custom']));
    }

    try {
        $pdo->prepare("INSERT INTO avisos (titulo, cuerpo, tipo_audiencia, audiencia_ref, fecha_expiracion) VALUES (?, ?, ?, ?, ?)")
            ->execute([$titulo, $cuerpo, $tipo_audiencia, $audiencia_ref, $fecha_expiracion]);
        $mensaje = "El aviso fue publicado exitosamente."; $tipo_mensaje = "success";
    } catch(Exception $e) {
        $mensaje = "Error al publicar el aviso."; $tipo_mensaje = "error";
    }
}

// PROCESAR ELIMINACIÓN DE AVISO
if (isset($_GET['borrar'])) {
    $pdo->prepare("DELETE FROM avisos WHERE aviso_id = ?")->execute([$_GET['borrar']]);
    $mensaje = "Aviso eliminado correctamente."; $tipo_mensaje = "success";
}

// LIMPIEZA AUTOMÁTICA: Borrar los expirados para no ensuciar la base de datos
$pdo->exec("DELETE FROM avisos WHERE fecha_expiracion IS NOT NULL AND fecha_expiracion < NOW()");

// OBTENER DATOS PARA LOS SELECTORES (Idiomas, Materias, Grupos)
$idiomas = $pdo->query("SELECT DISTINCT nombre FROM materias")->fetchAll(PDO::FETCH_COLUMN);
$materias = $pdo->query("SELECT materia_id, nombre, nivel FROM materias ORDER BY nombre, nivel")->fetchAll(PDO::FETCH_ASSOC);
$grupos = $pdo->query("SELECT g.nrc, m.nombre, m.nivel, u.nombre as prof FROM grupos g JOIN materias m ON g.materia_id=m.materia_id LEFT JOIN usuarios u ON g.profesor_id=u.usuario_id WHERE g.estado='ACTIVO'")->fetchAll(PDO::FETCH_ASSOC);

// OBTENER AVISOS ACTIVOS
$avisos = $pdo->query("SELECT * FROM avisos ORDER BY fecha_creacion DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avisos y Notificaciones | Admin</title>
    <link rel="stylesheet" href="../css/estudiante.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

    <?php include 'menu_admin.php'; ?>

    <main class="main-content">
        <div class="page-title-center mb-30">
            <h1><i class="fas fa-bullhorn"></i> Avisos Dinámicos</h1>
            <p>Redacta mensajes para los estudiantes. Puedes dirigirlos a toda la escuela o a grupos específicos.</p>
        </div>

        <?php if($mensaje): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({ title: '<?php echo ($tipo_mensaje == "success") ? "¡Éxito!" : "Error"; ?>', text: '<?php echo addslashes($mensaje); ?>', icon: '<?php echo $tipo_mensaje; ?>', confirmButtonColor: 'var(--udg-blue)' });
                    const url = new URL(window.location); url.searchParams.delete('borrar'); window.history.replaceState({}, '', url);
                });
            </script>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 20px; align-items: start;">
            
            <div class="card" style="margin-top: 0; border-top: 4px solid var(--udg-blue);">
                <h3 style="margin-top: 0; color: var(--udg-blue); border-bottom: 1px solid #eee; padding-bottom: 10px;"><i class="fas fa-pen-nib"></i> Redactar Aviso</h3>
                
                <form method="POST">
                    <input type="hidden" name="crear_aviso" value="1">
                    
                    <div class="form-group">
                        <label>Dirigido a:</label>
                        <select name="audiencia" required>
                            <option value="GLOBAL::ALL" style="font-weight:bold;">Toda la escuela (Global)</option>
                            
                            <optgroup label="Por Idioma General">
                                <?php foreach($idiomas as $idm): ?>
                                    <option value="IDIOMA::<?php echo htmlspecialchars($idm); ?>">Todos los grupos de <?php echo htmlspecialchars($idm); ?></option>
                                <?php endforeach; ?>
                            </optgroup>

                            <optgroup label="Por Nivel / Materia">
                                <?php foreach($materias as $m): ?>
                                    <option value="MATERIA::<?php echo $m['materia_id']; ?>"><?php echo htmlspecialchars($m['nombre'] . ' Nivel ' . $m['nivel']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>

                            <optgroup label="Por Grupo Específico (En Curso)">
                                <?php foreach($grupos as $g): ?>
                                    <option value="GRUPO::<?php echo $g['nrc']; ?>">NRC: <?php echo $g['nrc']; ?> - <?php echo htmlspecialchars($g['nombre'] . ' ' . $g['nivel']); ?> (Prof. <?php echo strtok($g['prof'], " "); ?>)</option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Título (En negritas)</label>
                        <input type="text" name="titulo" required placeholder="Ej. Aviso de mantenimiento" maxlength="100">
                    </div>

                    <div class="form-group">
                        <label>Cuerpo del Mensaje</label>
                        <textarea name="cuerpo" rows="4" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; resize:none; font-family:inherit;" placeholder="Ej. El día de mañana la plataforma estará en mantenimiento..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Duración (Caducidad)</label>
                        <select name="tipo_duracion" id="tipoDuracion" onchange="toggleFechaCustom()" required>
                            <option value="INDEFINIDO">Hasta que yo lo borre (Indefinido)</option>
                            <option value="PERSONALIZADO">Elegir fecha y hora exacta...</option>
                        </select>
                    </div>

                    <div class="form-group" id="grupoFechaCustom" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #eee; border-left: 4px solid var(--udg-blue);">
                        <label style="color: var(--udg-blue);"><i class="far fa-calendar-alt"></i> Selecciona la fecha límite:</label>
                        <input type="datetime-local" name="fecha_expiracion_custom" id="inputFechaCustom" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-family:inherit; margin-top:5px;">
                        <small style="color: #888; display: block; margin-top: 5px;">El aviso se borrará automáticamente de los tableros en esta fecha.</small>
                    </div>

                    <button type="submit" class="btn-save" style="width: 100%; margin-top: 10px;"><i class="fas fa-paper-plane"></i> Publicar Aviso</button>
                </form>
            </div>

            <div class="card" style="margin-top: 0;">
                <h3 style="margin-top: 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px;"><i class="fas fa-broadcast-tower"></i> Avisos Activos (<?php echo count($avisos); ?>)</h3>
                
                <div style="max-height: 500px; overflow-y: auto; padding-right: 5px;">
                    <?php if(count($avisos) > 0): ?>
                        <?php foreach($avisos as $a): 
                            // Formatear la etiqueta de la audiencia
                            $badge_color = '#e2e3e5'; $badge_text = '#383d41'; $txt_audiencia = '';
                            if($a['tipo_audiencia'] == 'GLOBAL') { $txt_audiencia = 'Global'; $badge_color = '#fff3cd'; $badge_text = '#856404'; }
                            elseif($a['tipo_audiencia'] == 'IDIOMA') { $txt_audiencia = 'Idioma: ' . $a['audiencia_ref']; $badge_color = '#cce5ff'; $badge_text = '#004085'; }
                            elseif($a['tipo_audiencia'] == 'MATERIA') { $txt_audiencia = 'Materia ID: ' . $a['audiencia_ref']; $badge_color = '#d4edda'; $badge_text = '#155724'; }
                            elseif($a['tipo_audiencia'] == 'GRUPO') { $txt_audiencia = 'NRC: ' . $a['audiencia_ref']; $badge_color = '#e2e3e5'; $badge_text = '#383d41'; }
                        ?>
                            <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; position: relative;">
                                <a href="#" onclick="confirmarBorrado('avisos.php?borrar=<?php echo $a['aviso_id']; ?>')" style="position: absolute; top: 15px; right: 15px; color: #dc3545; font-size: 1.2rem;" title="Eliminar"><i class="fas fa-trash-alt"></i></a>
                                
                                <span style="background: <?php echo $badge_color; ?>; color: <?php echo $badge_text; ?>; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; border: 1px solid <?php echo $badge_text; ?>;">
                                    Dirigido a: <?php echo htmlspecialchars($txt_audiencia); ?>
                                </span>
                                
                                <div style="margin-top: 10px; font-size: 0.95rem; color: #333;">
                                    <strong><?php echo htmlspecialchars($a['titulo']); ?>:</strong> <?php echo nl2br(htmlspecialchars($a['cuerpo'])); ?>
                                </div>
                                
                                <div style="margin-top: 10px; font-size: 0.8rem; color: #888; display: flex; justify-content: space-between;">
                                    <span><i class="far fa-clock"></i> Publicado: <?php echo date('d/m/Y H:i', strtotime($a['fecha_creacion'])); ?></span>
                                    <span>
                                        <?php if($a['fecha_expiracion']): ?>
                                            <i class="fas fa-stopwatch" style="color: #dc3545;"></i> Expira: <strong style="color:#555;"><?php echo date('d/m/Y H:i', strtotime($a['fecha_expiracion'])); ?></strong>
                                        <?php else: ?>
                                            <i class="fas fa-infinity"></i> Indefinido
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 40px 20px; color: #aaa;">
                            <i class="fas fa-wind" style="font-size: 3rem; margin-bottom: 15px; display: block; color: #eee;"></i>
                            No hay avisos activos en este momento.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

    <footer class="main-footer"><div class="address-bar">Copyright © 2026 E-PALE | Panel de Administración</div></footer>
    
    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        
        function confirmarBorrado(url) {
            Swal.fire({ title: '¿Eliminar Aviso?', text: "Este aviso desaparecerá inmediatamente de los tableros de los alumnos.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', confirmButtonText: 'Sí, eliminar' }).then((result) => { if (result.isConfirmed) { window.location.href = url; } });
        }

        // Lógica para mostrar/ocultar el calendario
        function toggleFechaCustom() {
            const select = document.getElementById('tipoDuracion');
            const grupoFecha = document.getElementById('grupoFechaCustom');
            const inputFecha = document.getElementById('inputFechaCustom');

            if (select.value === 'PERSONALIZADO') {
                grupoFecha.style.display = 'block';
                inputFecha.required = true;
                
                // Establecemos la fecha/hora actual como mínima para que no puedan poner fechas pasadas
                const tzoffset = (new Date()).getTimezoneOffset() * 60000;
                const localISOTime = (new Date(Date.now() - tzoffset)).toISOString().slice(0, 16);
                inputFecha.min = localISOTime;
            } else {
                grupoFecha.style.display = 'none';
                inputFecha.required = false;
                inputFecha.value = ''; // Limpiamos el valor por si acaso
            }
        }
    </script>
</body>
</html>