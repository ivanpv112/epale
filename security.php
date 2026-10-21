<?php
// security.php

// 1. GENERACIÓN ÚNICA DEL TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1.1 ZONA HORARIA Y CONTROL DE INACTIVIDAD (15 minutos)
date_default_timezone_set('America/Mexico_City');

if (isset($_SESSION['user_id'])) {
    $tiempo_limite = 15 * 60; // 15 minutos en segundos (900s)
    if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad'] > $tiempo_limite)) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['login_error'] = "Tu sesión se ha cerrado por inactividad.";
    } else {
        $_SESSION['ultima_actividad'] = time(); // Actualiza el tiempo cada vez que hay actividad
    }
}

// 1.2 FUNCIONES DE CONFIGURACIÓN GLOBAL (config.json)
/**
 * Obtiene el valor de una llave de configuración desde config.json.
 * Implementa caché en memoria para no leer el disco múltiples veces en una misma petición.
 */
function config_get(string $key, $default = null) {
    static $config_cache = null;
    $path = __DIR__ . '/config.json';
    
    if ($config_cache === null) {
        $config_cache = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
        if (!is_array($config_cache)) $config_cache = [];
    }
    
    return array_key_exists($key, $config_cache) ? $config_cache[$key] : $default;
}

/**
 * Guarda o actualiza un valor en config.json
 */
function config_set(string $key, mixed $value) {
    $path = __DIR__ . '/config.json';
    $config = file_exists($path) ? json_decode(file_get_contents($path), true) : [];
    if (!is_array($config)) $config = [];
    
    $config[$key] = $value;
    file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT));
}

/**
 * Verifica que haya una sesión activa y que corresponda al rol requerido.
 * Si falla, destruye variables sensibles (por seguridad) y expulsa al usuario.
 * 
 * @param string $rol_esperado 'ADMIN', 'PROFESOR' o 'ALUMNO'
 * @param string $redirect_url Ruta relativa hacia donde expulsar al usuario
 */
function requerir_rol(string $rol_esperado, string $redirect_url = '../index') {
    if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== $rol_esperado) {
        $login_error = $_SESSION['login_error'] ?? null;
        session_unset();
        session_destroy();
        if ($login_error) {
            session_start();
            $_SESSION['login_error'] = $login_error;
        }
        header("Location: " . $redirect_url);
        exit;
    }
}

/**
 * Filtra los mensajes de error para no exponer información delicada de SQL (PDOException)
 * al usuario final, manteniendo los mensajes de lógica de negocio normales.
 */
function mensaje_error_amigable(Exception $e) {
    if ($e instanceof \PDOException) {
        error_log("Error Base de Datos: " . $e->getMessage());
        return "Ocurrió un error inesperado al procesar la información. Por favor, intenta de nuevo más tarde.";
    }
    return $e->getMessage();
}

// 2. FUNCIÓN MAESTRA DE VALIDACIÓN
function validar_csrf_estricto($metodo_esperado = 'POST')
{

    $metodo_actual = $_SERVER['REQUEST_METHOD'];

    if ($metodo_actual === strtoupper($metodo_esperado)) {

        $token = '';

        if ($metodo_actual === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            if (empty($token)) {
                $json_body = json_decode(file_get_contents('php://input'), true);
                $token = $json_body['csrf_token'] ?? '';
            }
        } elseif ($metodo_actual === 'GET') {
            $token = $_GET['csrf_token'] ?? '';
        }

        // Validación criptográfica
        if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {

            // Código HTTP 403 Forbidden (Bloqueo formal a nivel de servidor)
            http_response_code(403);

            $is_json_request = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;

            if ($is_json_request) {
                header('Content-Type: application/json');
                // Mensaje genérico y amigable para APIs
                echo json_encode(['success' => false, 'error' => 'No pudimos procesar la solicitud. Es posible que tu sesión haya expirado por inactividad.']);
            } else {
                // Interfaz de Rescate (HTML Limpio y con diseño e-PALE)
                echo '
                <!DOCTYPE html>
                <html lang="es">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Acción no válida | e-PALE</title>
                    <style>
                        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                        .error-container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; max-width: 450px; border-top: 4px solid #001a57; }
                        .error-container h1 { color: #001a57; font-size: 1.5rem; margin-bottom: 10px; margin-top: 0; }
                        .error-container p { color: #666; font-size: 0.95rem; margin-bottom: 25px; line-height: 1.5; }
                        .btn-back { background-color: #001a57; color: white; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: bold; transition: background 0.3s; display: inline-block; }
                        .btn-back:hover { background-color: #002677; }
                        .icon-error { font-size: 3.5rem; color: #ffc107; margin-bottom: 15px; }
                    </style>
                </head>
                <body>
                    <div class="error-container">
                        <div class="icon-error">⚠️</div>
                        <h1>Acción no autorizada</h1>
                        <p>No hemos podido procesar tu solicitud. Esto suele ocurrir si dejaste la página inactiva por mucho tiempo o si intentaste una acción no permitida.</p>
                        <a href="javascript:history.back()" class="btn-back">Volver a la página anterior</a>
                    </div>
                </body>
                </html>
                ';
            }
            exit;
        }
    }
}

// 3. FUNCIÓN PARA EL HISTORIAL DE MODIFICACIONES (AUDITORÍA)
function registrar_historial(PDO $pdo, int $admin_id, string $tipo_accion, string $categoria, string $titulo, string $afectado, string $detalle)
{
    try {
        $stmt = $pdo->prepare("INSERT INTO historial_admin (admin_id, tipo_accion, categoria, titulo, afectado, detalle, fecha) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$admin_id, $tipo_accion, $categoria, $titulo, $afectado, $detalle]);
        return true;
    } catch (PDOException $e) {
        // En un entorno de producción real podríamos registrar este error en un log de sistema (file).
        return false;
    }
}

/**
 * Valida, purga malware y convierte a WebP una imagen subida.
 * 
 * @param array $file $_FILES['input_name']
 * @param string $target_dir Directorio destino (ej. '../img/perfiles/')
 * @param int $quality Calidad WebP (0-100)
 * @return string|false Nombre del archivo generado (ej. 'ab12cd.webp'), o false si falla.
 */
function secure_image_upload($file, $target_dir, $quality = 80) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) return false;

    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_ext)) return false;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime_type, $allowed_mimes)) return false;

    // Verificar dimensiones para evitar bombas de descompresión (decompression bomb / memory exhaustion)
    $image_info = @getimagesize($file['tmp_name']);
    if (!$image_info) return false;
    list($orig_w, $orig_h) = $image_info;

    // Si la resolución es astronómica (ej. más de 6000x6000px), rechazarla de forma segura
    if ($orig_w > 6000 || $orig_h > 6000) return false;

    // Ampliar temporalmente el límite de memoria para GD si es necesario
    $current_limit = ini_get('memory_limit');
    if ($current_limit && $current_limit !== '-1') {
        @ini_set('memory_limit', '256M');
    }

    $target_dir = rtrim($target_dir, '/') . '/';
    if (!file_exists($target_dir)) { @mkdir($target_dir, 0755, true); }

    $new_file_name = bin2hex(random_bytes(10)) . '.webp';
    $target_file = $target_dir . $new_file_name;

    try {
        if ($mime_type == 'image/jpeg') $img_source = @imagecreatefromjpeg($file['tmp_name']);
        elseif ($mime_type == 'image/png') {
            $img_source = @imagecreatefrompng($file['tmp_name']);
            if ($img_source !== false) {
                imagepalettetotruecolor($img_source);
                imagealphablending($img_source, false);
                imagesavealpha($img_source, true);
            }
        } elseif ($mime_type == 'image/webp') {
            $img_source = @imagecreatefromwebp($file['tmp_name']);
        } else {
            $img_source = false;
        }

        if (!$img_source) {
            return false;
        }

        // Redimensionar automáticamente si la imagen supera los 1920px de ancho o alto (Full HD estándar para banners/avisos)
        $max_dimension = 1920;
        if ($orig_w > $max_dimension || $orig_h > $max_dimension) {
            $ratio = min($max_dimension / $orig_w, $max_dimension / $orig_h);
            $new_w = max(1, (int)round($orig_w * $ratio));
            $new_h = max(1, (int)round($orig_h * $ratio));

            $resized_img = imagecreatetruecolor($new_w, $new_h);
            if ($resized_img !== false) {
                imagealphablending($resized_img, false);
                imagesavealpha($resized_img, true);
                imagecopyresampled($resized_img, $img_source, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
                imagedestroy($img_source);
                $img_source = $resized_img;
            }
        }

        $guardado = @imagewebp($img_source, $target_file, $quality); 
        imagedestroy($img_source); 
        return $guardado ? $new_file_name : false;
    } catch (\Throwable $e) {
        error_log("Error al procesar imagen: " . $e->getMessage());
        return false;
    }
}
