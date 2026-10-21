<?php
/**
 * mis_dictamenes_logic.php
 * Controlador y lógica para la consulta y visualización de dictámenes y resoluciones oficiales del alumno.
 */

// Si se intenta acceder directamente al archivo de lógica por URL, redirigir a mis_dictamenes
if (basename($_SERVER['PHP_SELF']) === 'mis_dictamenes_logic.php') {
    header('Location: mis_dictamenes');
    exit;
}

session_start();
require_once '../db.php';
require_once '../security.php';

validar_csrf_estricto('POST');

// Validar seguridad: Solo Alumnos
requerir_rol('ALUMNO');

$usuario_id = $_SESSION['user_id'] ?? null;

// 1. Obtener el código del alumno desde la tabla usuarios
$stmt_codigo = $pdo->prepare("SELECT codigo FROM usuarios WHERE usuario_id = ?");
$stmt_codigo->execute([$usuario_id]);
$codigo_alumno = $stmt_codigo->fetchColumn() ?: '';

// 2. Buscar sus acreditaciones en la base de datos
$stmt_dictamenes = $pdo->prepare("
    SELECT * FROM dictamenes_estudiantes 
    WHERE codigo_alumno = ? 
    ORDER BY ciclo_acreditacion DESC, nombre_materia ASC
");
$stmt_dictamenes->execute([$codigo_alumno]);
$dictamenes = $stmt_dictamenes->fetchAll(PDO::FETCH_ASSOC);

// 3. Leer los archivos físicos de la carpeta local y asociar URLs precalculadas
$upload_dir = '../uploads/dictamenes/';
$archivos_fisicos = [];
if (is_dir($upload_dir)) {
    $archivos_fisicos = array_diff(scandir($upload_dir), ['.', '..']);
}

// Preasociar la URL del PDF a cada dictamen para mantener la vista limpia y declarativa
foreach ($dictamenes as &$d) {
    $d['pdf_url'] = null;
    $num_dictamen = $d['num_dictamen'] ?? '';
    if (!empty($num_dictamen)) {
        $llave_busqueda = str_replace('/', '.', $num_dictamen);
        foreach ($archivos_fisicos as $archivo) {
            if (stripos($archivo, $llave_busqueda) !== false) {
                $d['pdf_url'] = '../uploads/dictamenes/' . rawurlencode($archivo);
                break;
            }
        }
    }
}
unset($d);
