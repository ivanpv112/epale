<?php
session_start();
require '../../db.php';
require_once '../../security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$tipo = isset($_POST['tipo']) ? strtoupper($_POST['tipo']) : 'GLOBAL';

try {
    $stmt = $pdo->prepare("INSERT INTO exportaciones (admin_id, tipo, estado, fecha_solicitud) VALUES (?, ?, 'PENDIENTE', NOW())");
    $stmt->execute([$user_id, $tipo]);
    $exportacion_id = $pdo->lastInsertId();

    // Cerrar la sesión tempranamente para liberar el sitio (evita que se quede cargando)
    session_write_close();

    // Obtener el ejecutable PHP según el sistema operativo (Desarrollo vs Producción)
    $script_path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'worker_exportacion.php';
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Entorno Windows (Tu laptop actual con WAMP)
        // PHP_BINARY a veces apunta a apache (httpd.exe). Usamos PHP_BINDIR para llegar a php.exe seguro.
        $php_exe = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php.exe';
        
        // Si no funciona PHP_BINDIR, buscamos el ejecutable de PHP dinámicamente en WAMP
        if (!file_exists($php_exe)) {
            $php_rutas = glob('c:\wamp64\bin\php\*\php.exe');
            if (!empty($php_rutas)) {
                $php_exe = $php_rutas[0]; // Tomamos la primera versión de PHP instalada
            } else {
                $php_exe = 'php'; // Fallback a la variable global si falla todo
            }
        }

        $cmd = "start /B \"\" \"$php_exe\" \"$script_path\" $exportacion_id > NUL 2> NUL";
        pclose(popen($cmd, "r"));
    } else {
        // Entorno Producción (Servidor Real Linux, cPanel, VPS, etc.)
        // 'nohup' y '&' permiten que el proceso viva de forma independiente
        $cmd = "nohup php " . escapeshellarg($script_path) . " " . $exportacion_id . " > /dev/null 2>&1 &";
        exec($cmd);
    }

    echo json_encode(['success' => true, 'message' => 'Solicitud agregada a la cola. Revisa el historial en unos instantes.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al solicitar exportación: ' . $e->getMessage()]);
}
?>
