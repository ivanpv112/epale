<?php
// security.php

// 1. GENERACIÓN ÚNICA DEL TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. FUNCIÓN MAESTRA DE VALIDACIÓN
function validar_csrf_estricto($metodo_esperado = 'POST') {
    
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
?>
