<?php
// security.php

// 1. GENERACIÓN ÚNICA DEL TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 2. FUNCIÓN DE VALIDACIÓN: Valida POST y GET
function validar_csrf_estricto($metodo_esperado = 'POST') {
    
    $metodo_actual = $_SERVER['REQUEST_METHOD'];
    
    // Solo validamos si el método actual coincide con el que queremos proteger
    if ($metodo_actual === strtoupper($metodo_esperado)) {
        
        $token = '';

        // Buscar el token dependiendo de por dónde viene
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
