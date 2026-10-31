<?php
// recuperar_process.php - API Backend para el flujo de recuperación de contraseña
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

date_default_timezone_set('America/Mexico_City');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método HTTP no permitido.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;
$accion = trim($data['accion'] ?? '');

function obtener_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function enmascarar_correo(string $correo): string {
    $partes = explode('@', $correo);
    if (count($partes) !== 2) {
        return $correo;
    }
    $usuario = $partes[0];
    $dominio = $partes[1];
    $len = mb_strlen($usuario, 'UTF-8');

    if ($len <= 2) {
        $mascara = mb_substr($usuario, 0, 1, 'UTF-8') . '****';
    } else {
        $mascara = mb_substr($usuario, 0, 2, 'UTF-8') . '****';
    }

    return $mascara . '@' . $dominio;
}

function generar_codigo_seguro(int $longitud = 6): string {
    // Excluye caracteres ambiguos: 0, O, 1, I
    $alfabeto = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $codigo = '';
    $maxIndex = strlen($alfabeto) - 1;
    for ($i = 0; $i < $longitud; $i++) {
        $codigo .= $alfabeto[random_int(0, $maxIndex)];
    }
    return $codigo;
}

// =========================================================================
// ACCIÓN 1: SOLICITAR CÓDIGO (Envío y control de tasa / anti-enumeración)
// =========================================================================
if ($accion === 'solicitar_codigo') {
    $correo = strtolower(trim($data['correo'] ?? ''));
    $ip = obtener_ip();

    if (empty($correo) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Por favor, ingresa un correo electrónico institucional válido.'
        ]);
        exit;
    }

    // 1. Verificar límite de intentos en los últimos 15 minutos (máximo 3)
    $stmtCheck = $pdo->prepare("
        SELECT creado_en 
        FROM recuperacion_password 
        WHERE correo = :correo 
          AND creado_en > (NOW() - INTERVAL 15 MINUTE)
        ORDER BY creado_en ASC
    ");
    $stmtCheck->execute(['correo' => $correo]);
    $intentosRecientes = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

    if (count($intentosRecientes) >= 3) {
        // Calcular tiempo restante de bloqueo de 15 minutos desde el primer intento de la ráfaga
        $primerIntentoTimestamp = strtotime($intentosRecientes[0]['creado_en']);
        $desbloqueoTimestamp = $primerIntentoTimestamp + (15 * 60);
        $segundosRestantes = max(0, $desbloqueoTimestamp - time());
        $minutosRestantes = max(1, (int)ceil($segundosRestantes / 60));

        echo json_encode([
            'success' => false,
            'bloqueado' => true,
            'segundos_restantes' => $segundosRestantes,
            'message' => "Has alcanzado el límite de 3 solicitudes. Por seguridad, este correo ha sido bloqueado temporalmente. Podrás intentar nuevamente en aproximadamente {$minutosRestantes} minuto(s)."
        ]);
        exit;
    }

    // 2. Verificar si el usuario existe y está ACTIVO
    $stmtUser = $pdo->prepare("
        SELECT usuario_id, nombre, apellido_paterno, estatus 
        FROM usuarios 
        WHERE LOWER(correo) = :correo
        LIMIT 1
    ");
    $stmtUser->execute(['correo' => $correo]);
    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $correoEnmascarado = enmascarar_correo($correo);
    $intentosUsados = count($intentosRecientes) + 1;

    // Regla de anti-enumeración: Si no existe o no está activo, respondemos idéntico pero no enviamos correo
    if (!$usuario || $usuario['estatus'] !== 'ACTIVO') {
        // Guardamos un registro genérico sin usuario_id para contabilizar el intento de spam hacia este correo
        $codigoDummy = generar_codigo_seguro(6);
        $dummyHash = hash('sha256', $codigoDummy);
        $stmtDummy = $pdo->prepare("
            INSERT INTO recuperacion_password (correo, usuario_id, codigo_hash, expira_en, ip)
            VALUES (:correo, NULL, :hash, NOW() + INTERVAL 5 MINUTE, :ip)
        ");
        $stmtDummy->execute([
            'correo' => $correo,
            'hash'   => $dummyHash,
            'ip'     => $ip
        ]);

        // Retardo leve contra ataques de temporización
        usleep(300000);

        echo json_encode([
            'success'            => true,
            'correo_enmascarado' => $correoEnmascarado,
            'tiempo_segundos'    => 300,
            'intentos_usados'    => $intentosUsados,
            'intentos_maximos'   => 3,
            'message'            => 'Si el correo está registrado, recibirás un código de 6 caracteres en breve.'
        ]);
        exit;
    }

    // 3. Usuario válido: Generar código de 6 caracteres
    $codigo = generar_codigo_seguro(6);
    $codigoHash = hash('sha256', strtoupper($codigo));

    // Invalidar códigos anteriores no utilizados de este usuario
    $stmtInvalidar = $pdo->prepare("
        UPDATE recuperacion_password 
        SET expira_en = NOW() 
        WHERE correo = :correo AND utilizado = 0 AND expira_en > NOW()
    ");
    $stmtInvalidar->execute(['correo' => $correo]);

    // Insertar nuevo registro con 5 minutos de vigencia
    $stmtInsert = $pdo->prepare("
        INSERT INTO recuperacion_password (correo, usuario_id, codigo_hash, expira_en, ip)
        VALUES (:correo, :usuario_id, :codigo_hash, NOW() + INTERVAL 5 MINUTE, :ip)
    ");
    $stmtInsert->execute([
        'correo'      => $correo,
        'usuario_id'  => $usuario['usuario_id'],
        'codigo_hash' => $codigoHash,
        'ip'          => $ip
    ]);

    // 4. Enviar correo mediante PHPMailer
    $nombreCompleto = trim($usuario['nombre'] . ' ' . $usuario['apellido_paterno']);
    $resultadoMail = enviar_codigo_recuperacion($correo, $nombreCompleto, $codigo);

    if (!$resultadoMail['exito']) {
        error_log("Error enviando código de recuperación: " . ($resultadoMail['error'] ?? ''));
        // Si hay fallo de servidor SMTP lo reportamos limpiamente
        echo json_encode([
            'success' => false,
            'message' => 'Hubo un inconveniente al enviar el correo con el código. Por favor intenta de nuevo.'
        ]);
        exit;
    }

    echo json_encode([
        'success'            => true,
        'correo_enmascarado' => $correoEnmascarado,
        'tiempo_segundos'    => 300,
        'intentos_usados'    => $intentosUsados,
        'intentos_maximos'   => 3,
        'message'            => 'Hemos enviado tu código de seguridad al correo electrónico.'
    ]);
    exit;
}

// =========================================================================
// ACCIÓN 2: VERIFICAR CÓDIGO
// =========================================================================
if ($accion === 'verificar_codigo') {
    $correo = strtolower(trim($data['correo'] ?? ''));
    $codigo = strtoupper(trim($data['codigo'] ?? ''));

    if (empty($correo) || empty($codigo)) {
        echo json_encode(['success' => false, 'message' => 'Ingresa el código de 6 caracteres recibido.']);
        exit;
    }

    // Buscar el registro activo no utilizado y no expirado
    $stmt = $pdo->prepare("
        SELECT id, usuario_id, codigo_hash, intentos_validacion, expira_en,
               TIMESTAMPDIFF(SECOND, NOW(), expira_en) AS segundos_restantes
        FROM recuperacion_password
        WHERE correo = :correo 
          AND utilizado = 0 
          AND expira_en > NOW()
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute(['correo' => $correo]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        echo json_encode([
            'success' => false,
            'expirado' => true,
            'message' => 'El código ha expirado o no es válido. Por favor, solicita un código nuevo.'
        ]);
        exit;
    }

    // Validar si ya superó los 3 intentos en este código
    if ((int)$registro['intentos_validacion'] >= 3) {
        // Forzar expiración
        $pdo->prepare("UPDATE recuperacion_password SET expira_en = NOW() WHERE id = :id")->execute(['id' => $registro['id']]);
        echo json_encode([
            'success' => false,
            'expirado' => true,
            'message' => 'Has agotado los 3 intentos para este código. Debes solicitar un código nuevo.'
        ]);
        exit;
    }

    $hashIngresado = hash('sha256', $codigo);

    if (hash_equals($registro['codigo_hash'], $hashIngresado) && !empty($registro['usuario_id'])) {
        // Código correcto: creamos sesión temporal de restablecimiento (10 min de gracia para poner nueva clave)
        $_SESSION['reset_autorizado'] = [
            'id_recuperacion' => $registro['id'],
            'usuario_id'      => $registro['usuario_id'],
            'correo'          => $correo,
            'expira'          => time() + 600
        ];

        echo json_encode([
            'success' => true,
            'message' => 'Código validado correctamente.'
        ]);
        exit;
    } else {
        // Código incorrecto: incrementar contador de intentos de validación
        $nuevosIntentos = (int)$registro['intentos_validacion'] + 1;
        $stmtUp = $pdo->prepare("UPDATE recuperacion_password SET intentos_validacion = :inv WHERE id = :id");
        $stmtUp->execute(['inv' => $nuevosIntentos, 'id' => $registro['id']]);

        $restantes = max(0, 3 - $nuevosIntentos);

        if ($restantes <= 0) {
            // Forzar expiración si llegó al tercer fallo
            $pdo->prepare("UPDATE recuperacion_password SET expira_en = NOW() WHERE id = :id")->execute(['id' => $registro['id']]);
            echo json_encode([
                'success' => false,
                'expirado' => true,
                'message' => 'Código incorrecto. Has agotado tus 3 intentos. Por favor espera a que finalice el contador para solicitar otro código.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'expirado' => false,
                'intentos_restantes' => $restantes,
                'message' => "Código incorrecto. Te queda(n) {$restantes} intento(s) antes de que se anule este código."
            ]);
        }
        exit;
    }
}

// =========================================================================
// ACCIÓN 3: CAMBIAR CONTRASEÑA
// =========================================================================
if ($accion === 'cambiar_password') {
    if (empty($_SESSION['reset_autorizado'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Sesión no autorizada o expirada. Por favor reinicia el proceso.'
        ]);
        exit;
    }

    $auth = $_SESSION['reset_autorizado'];
    if (time() > ($auth['expira'] ?? 0)) {
        unset($_SESSION['reset_autorizado']);
        echo json_encode([
            'success' => false,
            'message' => 'El tiempo para actualizar tu contraseña ha expirado. Solicita un nuevo código.'
        ]);
        exit;
    }

    $nuevaPassword = $data['password'] ?? '';
    $confirmPassword = $data['confirm_password'] ?? '';

    if (mb_strlen($nuevaPassword) < 8) {
        echo json_encode([
            'success' => false,
            'message' => 'La nueva contraseña debe tener al menos 8 caracteres.'
        ]);
        exit;
    }

    if (!preg_match('/[a-z]/', $nuevaPassword) || 
        !preg_match('/[A-Z]/', $nuevaPassword) || 
        !preg_match('/[0-9]/', $nuevaPassword) || 
        !preg_match('/[^A-Za-z0-9]/', $nuevaPassword)) {
        echo json_encode([
            'success' => false,
            'message' => 'La contraseña debe ser segura: incluir al menos una mayúscula, una minúscula, un número y un carácter especial.'
        ]);
        exit;
    }

    if ($nuevaPassword !== $confirmPassword) {
        echo json_encode([
            'success' => false,
            'message' => 'Las contraseñas no coinciden. Verifícalas cuidadosamente.'
        ]);
        exit;
    }

    $usuarioId = (int)$auth['usuario_id'];
    $recuperacionId = (int)$auth['id_recuperacion'];
    $correo = $auth['correo'];

    // 1. Obtener datos actuales del usuario para el correo de alerta
    $stmtUser = $pdo->prepare("SELECT nombre, apellido_paterno FROM usuarios WHERE usuario_id = :id");
    $stmtUser->execute(['id' => $usuarioId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // 2. Cifrar la nueva contraseña
    $hashPassword = password_hash($nuevaPassword, PASSWORD_DEFAULT);

    // 3. Actualizar la contraseña en la tabla usuarios y registrar fecha
    $stmtUpdate = $pdo->prepare("
        UPDATE usuarios 
        SET password = :pass, 
            fecha_cambio_password = NOW() 
        WHERE usuario_id = :id
    ");
    $stmtUpdate->execute([
        'pass' => $hashPassword,
        'id'   => $usuarioId
    ]);

    // 4. Marcar el código de recuperación como utilizado
    $stmtRec = $pdo->prepare("UPDATE recuperacion_password SET utilizado = 1 WHERE id = :id");
    $stmtRec->execute(['id' => $recuperacionId]);

    // 5. Enviar correo de notificación de seguridad
    if ($user) {
        $nombreCompleto = trim($user['nombre'] . ' ' . $user['apellido_paterno']);
        $fechaHora = date('d/m/Y \a \l\a\s h:i:s A');
        enviar_alerta_password_cambiada($correo, $nombreCompleto, $fechaHora);
    }

    // 6. Limpiar la sesión de reseteo y preparar mensaje flash de éxito para index.php
    unset($_SESSION['reset_autorizado']);
    $_SESSION['login_success'] = 'Contraseña restablecida correctamente. Ya puedes iniciar sesión con tu nueva contraseña.';

    echo json_encode([
        'success'  => true,
        'message'  => 'Contraseña restablecida correctamente.',
        'redirect' => 'index'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
exit;

