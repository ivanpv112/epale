<?php
session_start();
require '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

requerir_rol('ADMIN');

// HELPER: Convertir números a romanos para los niveles
function numeroARomano(int $numero) {
    $mapa = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
    $resultado = '';
    $numero = intval($numero);
    while ($numero > 0) {
        foreach ($mapa as $romano => $int) {
            if($numero >= $int) {
                $numero -= $int;
                $resultado .= $romano;
                break;
            }
        }
    }
    return $resultado ?: $numero;
}

// CREAR LA TABLA SILENCIOSAMENTE SI NO EXISTE
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS avisos (
        aviso_id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(150),
        cuerpo TEXT,
        tipo_audiencia ENUM('GLOBAL', 'IDIOMA', 'MATERIA', 'GRUPO'),
        audiencia_ref VARCHAR(100),
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_expiracion DATETIME NULL,
        imagen VARCHAR(255) DEFAULT NULL
    )");
} catch (Exception $e) {}

$mensaje = ''; $tipo_mensaje = '';

// PROCESAR NUEVO AVISO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_aviso'])) {
    validar_csrf_estricto('POST');
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

    // PROCESAR IMAGEN (se guarda únicamente el nombre del archivo, igual que foto_perfil)
    $imagen = NULL;
    $error_imagen = '';
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload_err = $_FILES['imagen']['error'];
        if ($upload_err === UPLOAD_ERR_INI_SIZE || $upload_err === UPLOAD_ERR_FORM_SIZE) {
            $error_imagen = "La imagen es demasiado pesada. El tamaño máximo permitido es " . ini_get('upload_max_filesize') . ".";
        } elseif ($upload_err !== UPLOAD_ERR_OK) {
            $error_imagen = "No se pudo subir la imagen (código de error " . intval($upload_err) . "). Intenta de nuevo.";
        } else {
            $new_file_name = secure_image_upload($_FILES['imagen'], '../img/avisos/');
            if ($new_file_name) {
                $imagen = 'img/avisos/' . $new_file_name; // Guarda la ruta relativa a img/avisos/
            } else {
                $error_imagen = "La imagen no es válida. Usa un archivo JPG, PNG o WEBP.";
            }
        }
    }

    if ($error_imagen !== '') {
        // No publicamos el aviso sin la imagen que el administrador intentó adjuntar
        $mensaje = $error_imagen; $tipo_mensaje = "error";
    } else {
        try {
            if ($tipo_audiencia === 'CLASE') {
                $nrcs = $pdo->prepare("SELECT nrc FROM grupos WHERE clave_grupo = ?");
                $nrcs->execute([$audiencia_ref]);
                foreach($nrcs->fetchAll(PDO::FETCH_COLUMN) as $nrc) {
                    $pdo->prepare("INSERT INTO avisos (titulo, cuerpo, tipo_audiencia, audiencia_ref, fecha_expiracion, imagen) VALUES (?, ?, 'GRUPO', ?, ?, ?)")
                        ->execute([$titulo, $cuerpo, $nrc, $fecha_expiracion, $imagen]);
                }
            } else {
                $pdo->prepare("INSERT INTO avisos (titulo, cuerpo, tipo_audiencia, audiencia_ref, fecha_expiracion, imagen) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$titulo, $cuerpo, $tipo_audiencia, $audiencia_ref, $fecha_expiracion, $imagen]);
            }
            $mensaje = "El aviso fue publicado exitosamente."; $tipo_mensaje = "success";
        } catch(Exception $e) {
            $mensaje = "Error al publicar el aviso."; $tipo_mensaje = "error";
        }
    }
}

// PROCESAR ELIMINACIÓN DE AVISO (AHORA CON ESCUDO CSRF)
if (isset($_GET['borrar'])) {
    validar_csrf_estricto('GET');
    
    $img_stmt = $pdo->prepare("SELECT imagen FROM avisos WHERE aviso_id = ?");
    $img_stmt->execute([$_GET['borrar']]);
    $img_val = $img_stmt->fetchColumn();
    if ($img_val) {
        $img_nombre = basename($img_val);
        $img_fisica = '../img/avisos/' . $img_nombre;
        // Solo borramos el archivo si ningún otro aviso (p. ej. avisos por clase) lo sigue usando
        $uso = $pdo->prepare("SELECT COUNT(*) FROM avisos WHERE (imagen = ? OR imagen LIKE ?) AND aviso_id <> ?");
        $uso->execute([$img_nombre, '%' . $img_nombre, $_GET['borrar']]);
        if ($uso->fetchColumn() == 0 && file_exists($img_fisica)) {
            unlink($img_fisica);
        }
    }
    
    $pdo->prepare("DELETE FROM avisos WHERE aviso_id = ?")->execute([$_GET['borrar']]);
    $mensaje = "Aviso eliminado correctamente."; $tipo_mensaje = "success";
}

// LIMPIEZA AUTOMÁTICA: Borrar los expirados y sus imágenes físicas
try {
    $exp_stmt = $pdo->query("SELECT imagen FROM avisos WHERE fecha_expiracion IS NOT NULL AND fecha_expiracion < NOW() AND imagen IS NOT NULL");
    if ($exp_stmt) {
        while ($exp_img = $exp_stmt->fetchColumn()) {
            $exp_nombre = basename($exp_img);
            $uso_exp = $pdo->prepare("SELECT COUNT(*) FROM avisos WHERE (imagen = ? OR imagen LIKE ?) AND (fecha_expiracion IS NULL OR fecha_expiracion >= NOW())");
            $uso_exp->execute([$exp_nombre, '%' . $exp_nombre]);
            if ($uso_exp->fetchColumn() == 0 && file_exists('../img/avisos/' . $exp_nombre)) {
                @unlink('../img/avisos/' . $exp_nombre);
            }
        }
    }
    $pdo->exec("DELETE FROM avisos WHERE fecha_expiracion IS NOT NULL AND fecha_expiracion < NOW()");
} catch (Exception $e) {
    // Si falla la limpieza automática, no detener la ejecución
}

// OBTENER DATOS PARA LOS SELECTORES (Idiomas, Materias, Grupos)
$idiomas_raw = $pdo->query("SELECT nombre, MIN(clave) as min_clave FROM materias GROUP BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$idiomas = [];
foreach($idiomas_raw as $ir) {
    // Usamos el código completo (clave)
    $codigo = $ir['min_clave'];
    $idiomas[] = ['nombre' => $ir['nombre'], 'codigo' => $codigo];
}
$materias = $pdo->query("SELECT materia_id, clave, nombre, nivel FROM materias ORDER BY nombre, nivel")->fetchAll(PDO::FETCH_ASSOC);

// Obtenemos los grupos agrupados por clase para mostrar ambos NRC
$clases = $pdo->query("
    SELECT g.clave_grupo, m.nombre, m.nivel, u.nombre as prof,
    MAX(CASE WHEN h.modalidad='PRESENCIAL' THEN g.nrc END) as nrc_p,
    MAX(CASE WHEN h.modalidad='VIRTUAL' THEN g.nrc END) as nrc_v
    FROM grupos g 
    JOIN materias m ON g.materia_id=m.materia_id 
    LEFT JOIN usuarios u ON g.profesor_id=u.usuario_id 
    LEFT JOIN horarios h ON g.nrc=h.nrc 
    WHERE g.estado='ACTIVO'
    GROUP BY g.clave_grupo, m.nombre, m.nivel, u.nombre
")->fetchAll(PDO::FETCH_ASSOC);

// Mantenemos la lista individual para los lookups de Avisos Activos
$grupos_indiv = $pdo->query("SELECT g.nrc, h.modalidad FROM grupos g LEFT JOIN horarios h ON g.nrc=h.nrc WHERE g.estado='ACTIVO'")->fetchAll(PDO::FETCH_ASSOC);

// OBTENER AVISOS ACTIVOS
$avisos = $pdo->query("SELECT * FROM avisos ORDER BY fecha_creacion DESC")->fetchAll(PDO::FETCH_ASSOC);

// Lookups para mostrar nombres amigables en los avisos activos
$materia_lookup = [];
foreach($materias as $m) {
    $materia_lookup[$m['materia_id']] = htmlspecialchars($m['clave'] . ' - ' . $m['nombre'] . ' Nivel ' . numeroARomano($m['nivel']));
}
$grupo_lookup = [];
// Creamos un diccionario rápido de la modalidad por nrc
$mod_dict = [];
foreach($grupos_indiv as $gi) { $mod_dict[$gi['nrc']] = $gi['modalidad']; }

foreach($clases as $c) {
    $nombre_escapado = htmlspecialchars($c['nombre'] . ' Nivel ' . numeroARomano($c['nivel']));
    if ($c['nrc_p']) $grupo_lookup[$c['nrc_p']] = $nombre_escapado . ' (NRC: <i class="fas fa-building" style="color:#ffc107;" title="Presencial"></i> ' . htmlspecialchars($c['nrc_p']) . ')';
    if ($c['nrc_v']) $grupo_lookup[$c['nrc_v']] = $nombre_escapado . ' (NRC: <i class="fas fa-laptop" style="color:#17a2b8;" title="Virtual"></i> ' . htmlspecialchars($c['nrc_v']) . ')';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avisos y Notificaciones | Admin</title>
    <link rel="stylesheet" href="../css/estilos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        .select2-container .select2-selection--single {
            height: auto !important;
            padding: 4px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: normal !important;
            white-space: normal !important;
            padding-right: 25px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100% !important;
        }
    </style>
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
                    const url = new URL(window.location); url.searchParams.delete('borrar'); url.searchParams.delete('csrf_token'); window.history.replaceState({}, '', url);
                });
            </script>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 20px; align-items: start;">
            
            <div class="card" style="margin-top: 0; border-top: 4px solid var(--udg-blue);">
                <h3 style="margin-top: 0; color: var(--udg-blue); border-bottom: 1px solid #eee; padding-bottom: 10px;"><i class="fas fa-pen-nib"></i> Redactar Aviso</h3>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="crear_aviso" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label>Dirigido a:</label>
                        <select name="audiencia" required>
                            <option value="GLOBAL::ALL" data-html="<div style='display: flex; align-items: center; justify-content: space-between; gap: 10px;'><span style='font-size: 0.85em; color: #fff; background: #007bff; padding: 4px 8px; border-radius: 4px; font-weight: bold; white-space: nowrap;'><i class='fas fa-globe'></i> GLOBAL</span><span style='font-weight: bold; font-size: 1.05em; color: #2d3748; flex-grow: 1;'>Toda la escuela</span></div>">Toda la escuela (Global)</option>
                            
                            <optgroup label="Por Idioma General">
                                <?php foreach($idiomas as $idm): 
                                    $html_idioma = "<div style='display: flex; align-items: center; justify-content: space-between; gap: 10px;'><span style='font-size: 0.85em; color: #555; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-weight: bold; white-space: nowrap;'>" . htmlspecialchars($idm['codigo'] ?: 'IDIOMA') . "</span><span style='font-weight: bold; font-size: 1.05em; color: #2d3748; flex-grow: 1;'>" . htmlspecialchars($idm['nombre']) . " <span style='font-size:0.8em; color:#777; font-weight:normal;'>(Todos los niveles)</span></span></div>";
                                ?>
                                    <option value="IDIOMA::<?php echo htmlspecialchars($idm['nombre']); ?>" data-html="<?php echo htmlspecialchars($html_idioma); ?>">Todos los grupos de <?php echo htmlspecialchars($idm['codigo'] ? $idm['codigo'] . ' - ' . $idm['nombre'] : $idm['nombre']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>

                            <optgroup label="Por Nivel / Materia">
                                <?php foreach($materias as $m): 
                                    $roman = numeroARomano($m['nivel']);
                                    $html_mat = "<div style='display: flex; align-items: center; justify-content: flex-start; gap: 10px;'><span style='font-size: 0.85em; color: #555; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-weight: bold; white-space: nowrap;'>" . htmlspecialchars($m['clave']) . "</span><span style='font-weight: bold; font-size: 1.05em; color: #2d3748;'>" . htmlspecialchars($m['nombre']) . "</span><span style='font-weight: bold; color: var(--udg-blue); font-size: 1.1em; background: #eef2ff; padding: 4px 10px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);'>" . $roman . "</span></div>";
                                ?>
                                    <option value="MATERIA::<?php echo $m['materia_id']; ?>" data-html="<?php echo htmlspecialchars($html_mat); ?>"><?php echo htmlspecialchars($m['clave'] . ' - ' . $m['nombre'] . ' Nivel ' . $roman); ?></option>
                                <?php endforeach; ?>
                            </optgroup>

                            <optgroup label="Por Clase (Ambas modalidades)">
                                <?php foreach($clases as $c): 
                                    $nrc_p = $c['nrc_p'] ? $c['nrc_p'] : 'NA';
                                    $nrc_v = $c['nrc_v'] ? $c['nrc_v'] : 'NA';
                                    $roman = numeroARomano($c['nivel']);
                                    $prof = strtok($c['prof'] ?? '', ' ');
                                    
                                    $lbl_nrc = "<i class='fas fa-building' style='color:#ffc107;' title='Presencial'></i> " . $nrc_p . " <span style='color:#ccc; margin:0 4px;'>|</span> <i class='fas fa-laptop' style='color:#17a2b8;' title='Virtual'></i> " . $nrc_v;
                                    $txt_nrc = "Presencial $nrc_p / Virtual $nrc_v";
                                    
                                    $html_clase = "<div style='display: flex; align-items: center; justify-content: flex-start; gap: 10px;'><span style='font-size: 0.85em; color: #555; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-weight: bold; white-space: nowrap;'>" . $lbl_nrc . "</span><span style='font-weight: bold; font-size: 1.05em; color: #2d3748;'>" . htmlspecialchars($c['nombre']) . "</span><span style='font-weight: bold; color: var(--udg-blue); font-size: 1.1em; background: #eef2ff; padding: 4px 10px; border-radius: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);'>" . $roman . "</span><span style='font-size:0.85em; color:#777; font-weight:bold; white-space:nowrap;'>(Prof. " . htmlspecialchars($prof) . ")</span></div>";
                                ?>
                                    <option value="CLASE::<?php echo $c['clave_grupo']; ?>" data-html="<?php echo htmlspecialchars($html_clase); ?>">
                                        NRC: <?php echo $txt_nrc; ?> - <?php echo htmlspecialchars($c['nombre'] . ' Nivel ' . $roman); ?> (Prof. <?php echo htmlspecialchars($prof); ?>)
                                    </option>
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

                    <div class="form-group">
                        <label>Imagen Promocional (Opcional)</label>
                        <input type="file" name="imagen" id="inputImagenAviso" accept="image/jpeg,image/png,image/webp" style="width:100%; padding:8px; border:1px dashed #bbb; border-radius:6px; background:#fdfdfd;">
                        <small style="color: #888; display: block; margin-top: 5px;">Sube un flyer o imagen (JPG, PNG, WEBP; máx. <?php echo ini_get('upload_max_filesize'); ?>). Ideal para congresos o talleres.</small>
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
                            elseif($a['tipo_audiencia'] == 'IDIOMA') { $txt_audiencia = 'Idioma: ' . htmlspecialchars($a['audiencia_ref']); $badge_color = '#cce5ff'; $badge_text = '#004085'; }
                            elseif($a['tipo_audiencia'] == 'MATERIA') { $txt_materia = $materia_lookup[$a['audiencia_ref']] ?? htmlspecialchars('ID ' . $a['audiencia_ref']); $txt_audiencia = 'Materia: ' . $txt_materia; $badge_color = '#d4edda'; $badge_text = '#155724'; }
                            elseif($a['tipo_audiencia'] == 'GRUPO') { $txt_grupo = $grupo_lookup[$a['audiencia_ref']] ?? htmlspecialchars('NRC ' . $a['audiencia_ref']); $txt_audiencia = 'Grupo: ' . $txt_grupo; $badge_color = '#e2e3e5'; $badge_text = '#383d41'; }
                        ?>
                            <div style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; position: relative;">
                                <a href="#" onclick="confirmarBorrado('avisos.php?borrar=<?php echo $a['aviso_id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>')" style="position: absolute; top: 15px; right: 15px; color: #dc3545; font-size: 1.2rem;" title="Eliminar"><i class="fas fa-trash-alt"></i></a>
                                
                                <span style="background: <?php echo $badge_color; ?>; color: <?php echo $badge_text; ?>; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; border: 1px solid <?php echo $badge_text; ?>;">
                                    Dirigido a: <?php echo $txt_audiencia; ?>
                                </span>
                                
                                <div style="margin-top: 10px; font-size: 0.95rem; color: #333;">
                                    <strong><?php echo htmlspecialchars($a['titulo']); ?>:</strong> <?php echo nl2br(htmlspecialchars($a['cuerpo'])); ?>
                                </div>
                                <?php if($a['imagen']): ?>
                                <div style="margin-top: 10px;">
                                    <img src="../img/avisos/<?php echo htmlspecialchars(basename($a['imagen'])); ?>" alt="Flyer" style="max-width: 100%; max-height: 300px; object-fit: contain; border-radius: 8px; border: 1px solid #ddd;">
                                </div>
                                <?php endif; ?>
                                
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

    <?php include '../main_footer.php'; ?>
    
    <script>
        function toggleMobileMenu() { document.getElementById('navWrapper').classList.toggle('active'); document.getElementById('menuOverlay').classList.toggle('active'); }
        
        function confirmarBorrado(url) {
            Swal.fire({ title: '¿Eliminar Aviso?', text: "Este aviso desaparecerá inmediatamente de los tableros de los alumnos.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', confirmButtonText: 'Sí, eliminar' }).then((result) => { if (result.isConfirmed) { window.location.href = url; } });
        }

        // Validación en el cliente del peso de la imagen (evita que el servidor la descarte en silencio)
        (function() {
            const input = document.getElementById('inputImagenAviso');
            if (!input) return;
            const iniLimit = '<?php echo ini_get('upload_max_filesize'); ?>';
            const units = { K: 1024, M: 1024 * 1024, G: 1024 * 1024 * 1024 };
            const maxBytes = parseInt(iniLimit) * (units[iniLimit.slice(-1).toUpperCase()] || 1);
            input.addEventListener('change', function() {
                if (this.files.length && this.files[0].size > maxBytes) {
                    Swal.fire({ title: 'Imagen demasiado pesada', text: 'El tamaño máximo permitido es ' + iniLimit + '. Elige una imagen más ligera.', icon: 'warning', confirmButtonColor: 'var(--udg-blue)' });
                    this.value = '';
                }
            });
        })();

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

        // Iniciar Select2 para mejorar la búsqueda del campo Dirigido a
        $(document).ready(function() {
            function formatAudiencia(state) {
                if (!state.id) { return state.text; }
                var html = state.element.getAttribute('data-html');
                if (html) {
                    return $('<span>' + html + '</span>');
                }
                return state.text;
            }

            $('select[name="audiencia"]').select2({
                placeholder: "Selecciona a quién va dirigido",
                width: '100%',
                templateResult: formatAudiencia,
                templateSelection: formatAudiencia
            });
        });
    </script>
</body>
</html>