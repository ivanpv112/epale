<?php
/**
 * avisos_logic.php
 * Archivo de lógica de negocio y procesamiento de datos para admin/avisos.php
 */

session_start();
require '../db.php';
require_once '../security.php';

$mensaje = ''; $tipo_mensaje = '';

// Si la petición excede post_max_size, PHP vacía $_POST y $_FILES automáticamente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $mensaje = "El archivo o imagen seleccionada supera el límite máximo permitido por el servidor (" . ini_get('post_max_size') . "). Por favor, selecciona una imagen más ligera.";
    $tipo_mensaje = "error";
} else {
    validar_csrf_estricto('POST');
}

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

if (!function_exists('recortar_texto')) {
    function recortar_texto(string $texto, int $limite = 85)
    {
        if (mb_strlen($texto, 'UTF-8') > $limite) {
            return mb_substr($texto, 0, $limite, 'UTF-8') . '... <span style="color:var(--udg-blue); font-size:0.8rem; font-weight:bold;">Ver más</span>';
        }
        return $texto;
    }
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
            // Obtener el ciclo escolar activo
            $st_ciclo_act = $pdo->query("SELECT ciclo_id FROM ciclos WHERE activo = 1 LIMIT 1");
            $ciclo_actual_id = $st_ciclo_act ? (int)$st_ciclo_act->fetchColumn() : null;

            // Rol destinatario unificado para plataforma y correo
            $rol_destinatario = $_POST['rol_destinatario'] ?? 'TODOS';
            if (!in_array($rol_destinatario, ['TODOS', 'ALUMNO', 'PROFESOR'])) {
                $rol_destinatario = 'TODOS';
            }

            if ($tipo_audiencia === 'CLASE') {
                $nrcs = $pdo->prepare("SELECT nrc, ciclo_id FROM grupos WHERE clave_grupo = ?");
                $nrcs->execute([$audiencia_ref]);
                $grupos_encontrados = $nrcs->fetchAll(PDO::FETCH_ASSOC);
                foreach($grupos_encontrados as $g_item) {
                    $c_id = $g_item['ciclo_id'] ?: $ciclo_actual_id;
                    $pdo->prepare("INSERT INTO avisos (titulo, cuerpo, tipo_audiencia, rol_destinatario, audiencia_ref, ciclo_id, fecha_expiracion, imagen) VALUES (?, ?, 'GRUPO', ?, ?, ?, ?, ?)")
                        ->execute([$titulo, $cuerpo, $rol_destinatario, $g_item['nrc'], $c_id, $fecha_expiracion, $imagen]);
                }
            } else {
                $pdo->prepare("INSERT INTO avisos (titulo, cuerpo, tipo_audiencia, rol_destinatario, audiencia_ref, ciclo_id, fecha_expiracion, imagen) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$titulo, $cuerpo, $tipo_audiencia, $rol_destinatario, $audiencia_ref, $ciclo_actual_id, $fecha_expiracion, $imagen]);
            }
            $mensaje = "El aviso fue publicado exitosamente."; $tipo_mensaje = "success";

            // NOTIFICACIÓN POR CORREO ELECTRÓNICO (Si el checkbox fue marcado)
            if (!empty($_POST['notificar_correo'])) {
                require_once __DIR__ . '/../mailer.php';

                $destinatarios = [];

                // Etiqueta formal de Comunidad según el rol seleccionado
                if ($rol_destinatario === 'ALUMNO') {
                    $comunidad_txt = 'Comunidad Estudiantil E-PALE';
                } elseif ($rol_destinatario === 'PROFESOR') {
                    $comunidad_txt = 'Comunidad Docente E-PALE';
                } else {
                    $comunidad_txt = 'Comunidad Universitaria E-PALE';
                }

                $audiencia_label = $comunidad_txt;

                // Condición SQL según el rol
                $cond_rol_usuario = "";
                if ($rol_destinatario === 'ALUMNO') {
                    $cond_rol_usuario = " AND u.rol = 'ALUMNO'";
                } elseif ($rol_destinatario === 'PROFESOR') {
                    $cond_rol_usuario = " AND u.rol = 'PROFESOR'";
                } else {
                    $cond_rol_usuario = " AND u.rol IN ('ALUMNO', 'PROFESOR')";
                }

                if ($tipo_audiencia === 'GLOBAL') {
                    $audiencia_label = $comunidad_txt;
                    $stmt_dest = $pdo->query("SELECT DISTINCT correo FROM usuarios u WHERE u.estatus = 'ACTIVO' AND u.correo IS NOT NULL AND u.correo != ''" . $cond_rol_usuario);
                    $destinatarios = $stmt_dest->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($tipo_audiencia === 'IDIOMA') {
                    $audiencia_label = $comunidad_txt . ' • ' . $audiencia_ref;
                    $correos_alumnos = [];
                    $correos_profes = [];

                    // Alumnos del idioma
                    if ($rol_destinatario !== 'PROFESOR') {
                        $stmt_al = $pdo->prepare("
                            SELECT DISTINCT u.correo 
                            FROM inscripciones i
                            JOIN alumnos a ON i.alumno_id = a.alumno_id
                            JOIN usuarios u ON a.usuario_id = u.usuario_id
                            JOIN grupos g ON i.nrc = g.nrc
                            JOIN materias m ON g.materia_id = m.materia_id
                            WHERE m.nombre = ? AND i.estatus = 'INSCRITO' AND u.estatus = 'ACTIVO' AND u.correo IS NOT NULL AND u.correo != ''
                        ");
                        $stmt_al->execute([$audiencia_ref]);
                        $correos_alumnos = $stmt_al->fetchAll(PDO::FETCH_COLUMN);
                    }

                    // Profesores que imparten ese idioma
                    if ($rol_destinatario !== 'ALUMNO') {
                        $stmt_prof = $pdo->prepare("
                            SELECT DISTINCT u.correo 
                            FROM grupos g
                            JOIN materias m ON g.materia_id = m.materia_id
                            JOIN usuarios u ON g.profesor_id = u.usuario_id
                            WHERE m.nombre = ? AND g.estado = 'ACTIVO' AND u.estatus = 'ACTIVO' AND u.correo IS NOT NULL AND u.correo != ''
                        ");
                        $stmt_prof->execute([$audiencia_ref]);
                        $correos_profes = $stmt_prof->fetchAll(PDO::FETCH_COLUMN);
                    }

                    $destinatarios = array_merge($correos_alumnos, $correos_profes);
                } elseif ($tipo_audiencia === 'MATERIA') {
                    $nom_mat = $materia_lookup[$audiencia_ref] ?? 'Materia';
                    $audiencia_label = $comunidad_txt . ' • ' . strip_tags($nom_mat);
                    $correos_alumnos = [];
                    $correos_profes = [];

                    // Alumnos de la materia
                    if ($rol_destinatario !== 'PROFESOR') {
                        $stmt_al = $pdo->prepare("
                            SELECT DISTINCT u.correo 
                            FROM inscripciones i
                            JOIN alumnos a ON i.alumno_id = a.alumno_id
                            JOIN usuarios u ON a.usuario_id = u.usuario_id
                            JOIN grupos g ON i.nrc = g.nrc
                            WHERE g.materia_id = ? AND i.estatus = 'INSCRITO' AND u.estatus = 'ACTIVO' AND u.correo IS NOT NULL AND u.correo != ''
                        ");
                        $stmt_al->execute([$audiencia_ref]);
                        $correos_alumnos = $stmt_al->fetchAll(PDO::FETCH_COLUMN);
                    }

                    // Profesores de esa materia
                    if ($rol_destinatario !== 'ALUMNO') {
                        $stmt_prof = $pdo->prepare("
                            SELECT DISTINCT u.correo 
                            FROM grupos g
                            JOIN usuarios u ON g.profesor_id = u.usuario_id
                            WHERE g.materia_id = ? AND g.estado = 'ACTIVO' AND u.estatus = 'ACTIVO' AND u.correo IS NOT NULL AND u.correo != ''
                        ");
                        $stmt_prof->execute([$audiencia_ref]);
                        $correos_profes = $stmt_prof->fetchAll(PDO::FETCH_COLUMN);
                    }

                    $destinatarios = array_merge($correos_alumnos, $correos_profes);
                } elseif ($tipo_audiencia === 'CLASE') {
                    $audiencia_label = $comunidad_txt . ' • Grupo: ' . $audiencia_ref;
                    $correos_alumnos = [];
                    $correos_profes = [];

                    // Alumnos del grupo
                    if ($rol_destinatario !== 'PROFESOR') {
                        $stmt_al = $pdo->prepare("
                            SELECT DISTINCT u.correo 
                            FROM inscripciones i
                            JOIN alumnos a ON i.alumno_id = a.alumno_id
                            JOIN usuarios u ON a.usuario_id = u.usuario_id
                            JOIN grupos g ON i.nrc = g.nrc
                            WHERE g.clave_grupo = ? AND i.estatus = 'INSCRITO' AND u.estatus = 'ACTIVO' AND u.correo IS NOT NULL AND u.correo != ''
                        ");
                        $stmt_al->execute([$audiencia_ref]);
                        $correos_alumnos = $stmt_al->fetchAll(PDO::FETCH_COLUMN);
                    }

                    // Profesor titular de esa clase
                    if ($rol_destinatario !== 'ALUMNO') {
                        $stmt_prof = $pdo->prepare("
                            SELECT DISTINCT u.correo 
                            FROM grupos g
                            JOIN usuarios u ON g.profesor_id = u.usuario_id
                            WHERE g.clave_grupo = ? AND g.estado = 'ACTIVO' AND u.estatus = 'ACTIVO' AND u.correo IS NOT NULL AND u.correo != ''
                        ");
                        $stmt_prof->execute([$audiencia_ref]);
                        $correos_profes = $stmt_prof->fetchAll(PDO::FETCH_COLUMN);
                    }

                    $destinatarios = array_merge($correos_alumnos, $correos_profes);
                }

                if (!empty($destinatarios)) {
                    $ruta_imagen_fisica = $imagen ? __DIR__ . '/../' . ltrim($imagen, '/') : null;
                    $resultado_mail = enviar_aviso_por_correo($destinatarios, $titulo, $cuerpo, $ruta_imagen_fisica, $audiencia_label);
                    if ($resultado_mail['exito']) {
                        $mensaje .= " Además, se enviaron " . $resultado_mail['enviados'] . " notificaciones por correo electrónico.";
                    } else {
                        $mensaje .= " Sin embargo, hubo un detalle con el envío de correos: " . $resultado_mail['error'];
                    }
                } else {
                    $mensaje .= " No se encontraron destinatarios activos con el rol seleccionado.";
                }
            }

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

// PROCESAR VACIADO DE TODOS LOS AVISOS DE UN CICLO ESPECÍFICO
if (isset($_GET['vaciar_ciclo'])) {
    validar_csrf_estricto('GET');
    $id_ciclo_vaciar = (int)$_GET['vaciar_ciclo'];

    if ($id_ciclo_vaciar > 0) {
        // 1. Obtener todas las imágenes de los avisos institucionales de este ciclo
        $st_imgs = $pdo->prepare("SELECT aviso_id, imagen FROM avisos WHERE autor_id IS NULL AND ciclo_id = ? AND imagen IS NOT NULL AND imagen != ''");
        $st_imgs->execute([$id_ciclo_vaciar]);
        $avisos_a_borrar = $st_imgs->fetchAll(PDO::FETCH_ASSOC);

        foreach ($avisos_a_borrar as $item_av) {
            $img_nombre = basename($item_av['imagen']);
            $img_fisica = '../img/avisos/' . $img_nombre;
            // Solo borramos el archivo si ningún otro aviso lo usa
            $uso = $pdo->prepare("SELECT COUNT(*) FROM avisos WHERE (imagen = ? OR imagen LIKE ?) AND ciclo_id != ?");
            $uso->execute([$img_nombre, '%' . $img_nombre, $id_ciclo_vaciar]);
            if ($uso->fetchColumn() == 0 && file_exists($img_fisica)) {
                @unlink($img_fisica);
            }
        }

        // 2. Eliminar todos los avisos institucionales de ese ciclo
        $del_st = $pdo->prepare("DELETE FROM avisos WHERE autor_id IS NULL AND ciclo_id = ?");
        $del_st->execute([$id_ciclo_vaciar]);
        $cant_eliminados = $del_st->rowCount();

        $mensaje = "Se han eliminado $cant_eliminados comunicado(s) de este ciclo escolar.";
        $tipo_mensaje = "success";
    }
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

// Obtener todos los ciclos escolares registrados
$todos_los_ciclos = $pdo->query("SELECT ciclo_id, nombre, activo FROM ciclos ORDER BY activo DESC, nombre DESC, ciclo_id DESC")->fetchAll(PDO::FETCH_ASSOC);

$ciclo_activo_sistema = null;
foreach ($todos_los_ciclos as $c_item) {
    if ($c_item['activo'] == 1) {
        $ciclo_activo_sistema = $c_item;
        break;
    }
}
if (!$ciclo_activo_sistema && !empty($todos_los_ciclos)) {
    $ciclo_activo_sistema = $todos_los_ciclos[0];
}

// Determinar el ciclo que el administrador quiere visualizar (por defecto el activo)
$ciclo_filtro_id = isset($_GET['ciclo_ver']) ? (int)$_GET['ciclo_ver'] : ($ciclo_activo_sistema['ciclo_id'] ?? 0);

// Ciclo seleccionado objeto
$ciclo_seleccionado_obj = null;
foreach ($todos_los_ciclos as $c_item) {
    if ($c_item['ciclo_id'] == $ciclo_filtro_id) {
        $ciclo_seleccionado_obj = $c_item;
        break;
    }
}
if (!$ciclo_seleccionado_obj) {
    $ciclo_seleccionado_obj = $ciclo_activo_sistema;
    $ciclo_filtro_id = $ciclo_activo_sistema['ciclo_id'] ?? 0;
}

// Lookups para mostrar nombres amigables en los avisos activos
$materia_lookup = [];
foreach($materias as $m) {
    $materia_lookup[$m['materia_id']] = htmlspecialchars($m['clave'] . ' - ' . $m['nombre'] . ' Nivel ' . numeroARomano($m['nivel']));
}
$grupo_lookup = [];
$mod_dict = [];
foreach($grupos_indiv as $gi) { $mod_dict[$gi['nrc']] = $gi['modalidad']; }

foreach($clases as $c) {
    $nombre_escapado = htmlspecialchars($c['nombre'] . ' Nivel ' . numeroARomano($c['nivel']));
    if ($c['nrc_p']) $grupo_lookup[$c['nrc_p']] = $nombre_escapado . ' (NRC: <i class="fas fa-building" style="color:#ffc107;" title="Presencial"></i> ' . htmlspecialchars($c['nrc_p']) . ')';
    if ($c['nrc_v']) $grupo_lookup[$c['nrc_v']] = $nombre_escapado . ' (NRC: <i class="fas fa-laptop" style="color:#17a2b8;" title="Virtual"></i> ' . htmlspecialchars($c['nrc_v']) . ')';
}

// OBTENER AVISOS INSTITUCIONALES DEL CICLO SELECCIONADO
if ($ciclo_filtro_id > 0) {
    $st_av = $pdo->prepare("SELECT * FROM avisos WHERE autor_id IS NULL AND ciclo_id = ? ORDER BY fecha_creacion DESC");
    $st_av->execute([$ciclo_filtro_id]);
    $avisos = $st_av->fetchAll(PDO::FETCH_ASSOC);
} else {
    $avisos = $pdo->query("SELECT * FROM avisos WHERE autor_id IS NULL ORDER BY fecha_creacion DESC")->fetchAll(PDO::FETCH_ASSOC);
}
