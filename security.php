<?php
// security.php

// 1. GENERACIÓN ÚNICA DEL TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. FUNCIÓN MAESTRA DE VALIDACIÓN
function validar_csrf_estricto() {
    // Solo validamos si es una petición que intenta modificar datos (POST, PUT, DELETE)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // A) Intentar leer el token desde un formulario normal (multipart o urlencoded)
        $token = $_POST['csrf_token'] ?? '';

        // B) Si está vacío, intentar leerlo desde el cuerpo JSON (Fetch API)
        if (empty($token)) {
            $json_body = json_decode(file_get_contents('php://input'), true);
            $token = $json_body['csrf_token'] ?? '';
        }

        // Validación criptográfica
        if (empty($_SESSION['csrf_token']) || empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            
            // Determinar cómo responder: JSON (para APIs) o Texto Plano (para formularios)
            $is_json_request = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
            
            if ($is_json_request) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido (CSRF). Por favor, recarga la página.']);
            } else {
                die("Error de Seguridad Crítico: Token CSRF inválido o ausente. Petición bloqueada.");
            }
            exit;
        }
    }
}
?>