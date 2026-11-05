<?php
// mailer.php - Servicio de Notificaciones por Correo Electrónico (PHPMailer + SMTP Gmail)
// 100% portable: no requiere Composer ni instalaciones de servidor.

require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Obtiene la configuración de correo desde el .env o variables de entorno.
 */
function obtener_config_smtp(): array {
    $env_file = __DIR__ . '/.env';
    $env = file_exists($env_file) ? parse_ini_file($env_file) : [];
    
    return [
        'host'       => $env['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port'       => intval($env['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587),
        'user'       => $env['SMTP_USER'] ?? getenv('SMTP_USER') ?: '',
        'pass'       => $env['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '',
        'from_name'  => $env['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'E-PALE CUCEA',
        'assets_url' => rtrim($env['EMAIL_ASSETS_URL'] ?? getenv('EMAIL_ASSETS_URL') ?: '', '/'),
    ];
}

/**
 * Retorna la URL base para los recursos visuales del correo electrónico.
 */
function obtener_email_assets_url(): string {
    $config = obtener_config_smtp();
    return $config['assets_url'] ?? '';
}

/**
 * Retorna las URLs o CIDs de los recursos visuales del correo.
 */
function obtener_recursos_header_email(): array {
    $assets_url = obtener_email_assets_url();
    $usar_remoto = (!empty($assets_url) && preg_match('#^https?://#i', $assets_url));

    if ($usar_remoto) {
        return [
            'remoto'        => true,
            'header_bg_src' => $assets_url . '/email/email-header.jpg',
            'udg_logo_src'  => $assets_url . '/isologo_udg.png',
            'epale_logo_src'=> $assets_url . '/logotipo-epale.png',
            'lock_src'      => $assets_url . '/email/icon-lock.png',
            'check_src'     => $assets_url . '/email/icon-check.png',
            'cross_src'     => $assets_url . '/email/icon-xmark.png',
            'xmark_src'     => $assets_url . '/email/icon-xmark.png',
        ];
    } else {
        return [
            'remoto'        => false,
            'header_bg_src' => 'cid:email_header_bg_cid',
            'udg_logo_src'  => 'cid:isologo_udg_cid',
            'epale_logo_src'=> 'cid:logotipo_epale_cid',
            'lock_src'      => 'cid:icon_lock_cid',
            'check_src'     => 'cid:icon_check_cid',
            'cross_src'     => 'cid:icon_xmark_cid',
            'xmark_src'     => 'cid:icon_xmark_cid',
        ];
    }
}

/**
 * Retorna las reglas CSS estandarizadas y responsivas para todas las plantillas.
 */
function obtener_css_comun_email(): string {
    return '
        /* Reglas Responsivas Fluidas para Dispositivos Móviles (Gmail App, iOS Mail, etc.) */
        @media only screen and (max-width: 620px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
                border-radius: 12px !important;
            }
            .email-header-row {
                background-color: #00215b !important;
                border-radius: 12px 12px 0 0 !important;
            }
            .email-header {
                padding: 24px 12px 22px 12px !important;
                border-top-left-radius: 12px !important;
                border-top-right-radius: 12px !important;
                border-bottom-left-radius: 0 !important;
                border-bottom-right-radius: 0 !important;
            }
            .email-header-subtitle {
                font-size: 13.5px !important;
                margin-top: 10px !important;
                white-space: nowrap !important;
                letter-spacing: 0.1px !important;
            }
            .email-body {
                padding: 22px 18px !important;
            }
            .email-cuerpo-box {
                padding: 16px 14px !important;
                font-size: 14.5px !important;
                line-height: 1.65 !important;
            }
            .email-title {
                font-size: 19px !important;
                margin-bottom: 15px !important;
            }
            .email-badge {
                display: block !important;
                width: fit-content !important;
                max-width: 95% !important;
                font-size: 12px !important;
                padding: 6px 12px !important;
                line-height: 1.4 !important;
                word-break: break-word !important;
            }
            .email-code-box {
                padding: 10px 6px !important;
            }
            .email-digit {
                width: 38px !important;
                height: 44px !important;
                line-height: 44px !important;
                font-size: 22px !important;
            }
            .email-btn {
                display: block !important;
                width: 85% !important;
                margin: 0 auto !important;
                padding: 13px 15px !important;
                font-size: 14px !important;
            }
            .email-footer {
                padding: 18px 16px !important;
                font-size: 11.5px !important;
            }
        }';
}

/**
 * Retorna el bloque HTML estandarizado de la cabecera institucional para todos los correos.
 */
function obtener_html_cabecera_email(string $header_bg_src, string $udg_logo_src, string $epale_logo_src): string {
    return '
        <!-- Cabecera Institucional Modular Estandarizada -->
        <tr class="email-header-row" style="background-color: #00215b;">
            <td class="email-header" align="center" bgcolor="#00215b" background="' . $header_bg_src . '" style="background-color: #00215b; background-image: url(\'' . $header_bg_src . '\'); background-size: cover; background-position: center; padding: 30px 24px 26px 24px; text-align: center; border-top-left-radius: 16px; border-top-right-radius: 16px; border-bottom-left-radius: 0; border-bottom-right-radius: 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 auto;">
                    <tr>
                        <!-- Logo UDG -->
                        <td align="right" valign="middle" style="padding-right: 14px;">
                            <img src="' . $udg_logo_src . '" alt="Universidad de Guadalajara" style="height: 60px; width: auto; max-height: 60px; display: block; border: 0;">
                        </td>
                        <!-- Barra separadora | -->
                        <td align="center" valign="middle" style="padding: 0; width: 2px;">
                            <div style="width: 2px; height: 38px; background-color: rgba(255, 255, 255, 0.65); line-height: 38px; border-radius: 1px;"></div>
                        </td>
                        <!-- Logo E-PALE -->
                        <td align="left" valign="middle" style="padding-left: 10px;">
                            <img src="' . $epale_logo_src . '" alt="E-PALE" style="height: 75px; width: auto; max-height: 75px; display: block; border: 0; margin-top: -7px; margin-bottom: -7px;">
                        </td>
                    </tr>
                </table>
                <div class="email-header-subtitle" style="color: #ffffff; font-size: 15px; font-weight: 400; margin-top: 12px; letter-spacing: 0.3px; white-space: nowrap; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
                    Universidad de Guadalajara - CUCEA
                </div>
            </td>
        </tr>';
}

/**
 * Genera el cuerpo HTML estilizado con el branding de E-PALE.
 */
function construir_plantilla_aviso(string $titulo, string $cuerpo, bool $tiene_imagen = false, string $audiencia_txt = 'Aviso'): string {
    $cuerpo_html = nl2br(htmlspecialchars($cuerpo));
    $titulo_html = htmlspecialchars($titulo);
    $audiencia_html = htmlspecialchars($audiencia_txt);
    
    $recursos = obtener_recursos_header_email();

    $seccion_imagen = '';
    if ($tiene_imagen) {
        // La imagen se incrusta de forma nativa vía CID para que Gmail la muestre sin depender de dominios locales
        $seccion_imagen = '
        <div style="margin: 22px 0 10px 0; text-align: center;">
            <img src="cid:imagen_aviso_cid" alt="Cartel Informativo" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; display: inline-block;">
        </div>';
    }

    return '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $titulo_html . '</title>
        <style>' . obtener_css_comun_email() . '</style>
    </head>
    <body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #334155; -webkit-text-size-adjust: 100%;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f1f5f9; padding: 35px 12px;">
            <tr>
                <td align="center">
                    <!-- Tarjeta Principal con bordes redondeados y sombra suspendida -->
                    <table class="email-container" role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width: 100%; max-width: 540px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01); border: 1px solid #e2e8f0; margin: 0 auto;">
                        ' . obtener_html_cabecera_email($recursos['header_bg_src'], $recursos['udg_logo_src'], $recursos['epale_logo_src']) . '

                        <!-- Body Content -->
                        <tr>
                            <td class="email-body" style="padding: 32px 34px 28px 34px;">
                                <div style="margin-bottom: 16px;">
                                    <span class="email-badge" style="display: inline-block; background-color: #eef2ff; color: #001a4e; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 20px; border: 1px solid #c7d2fe; line-height: 1.4;">
                                        ' . $audiencia_html . '
                                    </span>
                                </div>
                                
                                <h1 class="email-title" style="color: #001a4e; font-size: 21px; font-weight: 700; margin: 0 0 18px 0; line-height: 1.4; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">' . $titulo_html . '</h1>
                                
                                <div class="email-cuerpo-box" style="color: #334155; font-size: 15px; line-height: 1.7; background-color: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; border-left: 4px solid #0056b3; word-break: break-word; text-align: justify; text-justify: inter-word;">
                                    ' . $cuerpo_html . '
                                </div>

                                ' . $seccion_imagen . '

                                <div style="text-align: center; margin-top: 28px;">
                                    <a class="email-btn" href="http://localhost/e-pale/" style="display: inline-block; background-color: #001a4e; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 14px; padding: 12px 28px; border-radius: 8px; box-shadow: 0 3px 8px rgba(0,26,78,0.25); text-align: center;">
                                        Acceder a la Plataforma E-PALE
                                    </a>
                                </div>

                                <!-- Separador -->
                                <hr style="border: 0; height: 1px; background-color: #e2e8f0; margin: 26px 0 20px 0;">

                                <!-- Footer -->
                                <div class="email-footer" style="text-align: center; color: #64748b; font-size: 12px; line-height: 1.55;">
                                    <p style="margin: 0 0 4px 0;">Este es un mensaje institucional automático generado por la plataforma E-PALE.</p>
                                    <p style="margin: 0;">© ' . date('Y') . ' Coordinación del Programa de Aprendizaje de Lenguas Extranjeras (PALE) - CUCEA.</p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}

/**
 * Envia la notificación por correo electrónico.
 * Usa Cco (Bcc) en lotes de hasta 50 destinatarios para proteger la privacidad de los correos
 * y evitar bloqueos de spam o saturación de tiempo.
 *
 * @param array $destinatarios Lista de correos electrónicos
 * @param string $titulo Título del aviso
 * @param string $cuerpo Texto del aviso
 * @param string|null $ruta_imagen Ruta física de la imagen para incrustar
 * @param string $audiencia_txt Etiqueta visible de la audiencia
 * @return array ['exito' => bool, 'enviados' => int, 'error' => string|null]
 */
function enviar_aviso_por_correo(array $destinatarios, string $titulo, string $cuerpo, ?string $ruta_imagen = null, string $audiencia_txt = 'Aviso Institucional'): array {
    $config = obtener_config_smtp();
    
    if (empty($config['user']) || empty($config['pass'])) {
        return [
            'exito' => false,
            'enviados' => 0,
            'error' => 'Faltan credenciales SMTP (SMTP_USER / SMTP_PASS) en el archivo .env.'
        ];
    }

    // Filtrar correos válidos y únicos (no vacíos)
    $emails = array_values(array_unique(array_filter(array_map('trim', $destinatarios), function($e) {
        return filter_var($e, FILTER_VALIDATE_EMAIL);
    })));

    if (empty($emails)) {
        return [
            'exito' => false,
            'enviados' => 0,
            'error' => 'No se encontraron correos electrónicos válidos para la audiencia seleccionada.'
        ];
    }

    // Resolver ruta física real de la imagen para incrustarla
    $ruta_fisica_imagen = null;
    $tiene_imagen = false;
    if (!empty($ruta_imagen)) {
        // Puede venir como 'img/avisos/archivo.webp' o ruta absoluta
        $posible_ruta = file_exists($ruta_imagen) ? $ruta_imagen : __DIR__ . '/' . ltrim($ruta_imagen, '/');
        if (file_exists($posible_ruta) && is_file($posible_ruta)) {
            $ruta_fisica_imagen = $posible_ruta;
            $tiene_imagen = true;
        }
    }

    $asunto = "E-PALE: " . $titulo;
    $html_body = construir_plantilla_aviso($titulo, $cuerpo, $tiene_imagen, $audiencia_txt);
    $plain_body = $titulo . "\n\n" . $cuerpo . "\n\nAccede a la plataforma: http://localhost/e-pale/";

    // Enviar en bloques de 50 destinatarios por Bcc (norma de Google y para no saturar memoria)
    $lotes = array_chunk($emails, 50);
    $total_enviados = 0;

    foreach ($lotes as $lote) {
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host       = $config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['user'];
            $mail->Password   = $config['pass'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $config['port'];
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 20;
            $mail->XMailer    = null; // Omitir cabecera X-Mailer para mayor privacidad y formato institucional limpio

            // Remitente
            $mail->setFrom($config['user'], $config['from_name']);
            // El destinatario principal es el propio correo institucional emisor
            $mail->addAddress($config['user'], $config['from_name']);

            // Los destinatarios van en Cco (Bcc) para proteger la privacidad
            foreach ($lote as $correo_dest) {
                $mail->addBCC($correo_dest);
            }

            // Si no hay URL remota pública en el .env, incrustar recursos vía CID como respaldo
            $assets_url = $config['assets_url'] ?? '';
            $usar_remoto = (!empty($assets_url) && preg_match('#^https?://#i', $assets_url));
            if (!$usar_remoto) {
                $ruta_udg       = __DIR__ . '/img/isologo_udg.png';
                $ruta_epale     = __DIR__ . '/img/logotipo-epale.png';
                $ruta_header_bg = file_exists(__DIR__ . '/img/email/email-header.jpg') ? __DIR__ . '/img/email/email-header.jpg' : __DIR__ . '/img/email-header.jpg';
                if (file_exists($ruta_udg)) $mail->addEmbeddedImage($ruta_udg, 'isologo_udg_cid', 'isologo_udg.png');
                if (file_exists($ruta_epale)) $mail->addEmbeddedImage($ruta_epale, 'logotipo_epale_cid', 'logotipo-epale.png');
                if (file_exists($ruta_header_bg)) $mail->addEmbeddedImage($ruta_header_bg, 'email_header_bg_cid', 'email-header.jpg');
            }

            // Incrustar imagen en el correo directamente si existe con un nombre legible basado en el título
            if ($tiene_imagen && $ruta_fisica_imagen) {
                // Generar un nombre de archivo legible y limpio para el cliente de correo
                $extension = pathinfo($ruta_fisica_imagen, PATHINFO_EXTENSION) ?: 'webp';
                // Limpiar caracteres no aptos para nombres de archivo en clientes de correo
                $titulo_limpio = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $titulo);
                $titulo_limpio = preg_replace('/\s+/', ' ', trim($titulo_limpio));
                if (mb_strlen($titulo_limpio, 'UTF-8') > 50) {
                    $titulo_limpio = mb_substr($titulo_limpio, 0, 50, 'UTF-8');
                }
                $nombre_adjunto = (!empty($titulo_limpio) ? $titulo_limpio : 'Aviso_E-PALE') . '.' . $extension;

                $mail->addEmbeddedImage($ruta_fisica_imagen, 'imagen_aviso_cid', $nombre_adjunto);
            }

            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $html_body;
            $mail->AltBody = $plain_body;

            $mail->send();
            $total_enviados += count($lote);
        } catch (Exception $e) {
            error_log("Error al enviar correos con PHPMailer: " . $mail->ErrorInfo);
            return [
                'exito' => false,
                'enviados' => $total_enviados,
                'error' => 'Error al conectar con el servidor de correo: ' . $mail->ErrorInfo
            ];
        }
    }

    return [
        'exito' => true,
        'enviados' => $total_enviados,
        'error' => null
    ];
}

/**
 * Envia el código de 6 caracteres para recuperación de contraseña.
 */
function enviar_codigo_recuperacion(string $destinatario, string $nombre, string $codigo): array {
    $config = obtener_config_smtp();

    if (empty($config['user']) || empty($config['pass'])) {
        return ['exito' => false, 'error' => 'Faltan credenciales SMTP en el archivo .env.'];
    }

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        return ['exito' => false, 'error' => 'Correo destinatario no válido.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['user'];
        $mail->Password   = $config['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;
        $mail->XMailer    = null; // Omitir cabecera X-Mailer para mayor privacidad y formato institucional limpio

        $mail->setFrom($config['user'], $config['from_name']);
        $mail->addAddress($destinatario, $nombre);

        // Resolver recursos visuales (URL remota sin adjuntos o respaldo local vía CID)
        $recursos = obtener_recursos_header_email();
        $lock_src = $recursos['lock_src'];

        if (!$recursos['remoto']) {
            $ruta_udg       = __DIR__ . '/img/isologo_udg.png';
            $ruta_epale     = __DIR__ . '/img/logotipo-epale.png';
            $ruta_header_bg = file_exists(__DIR__ . '/img/email/email-header.jpg') ? __DIR__ . '/img/email/email-header.jpg' : __DIR__ . '/img/email-header.jpg';
            $ruta_lock      = __DIR__ . '/img/email/icon-lock.png';

            if (file_exists($ruta_udg)) $mail->addEmbeddedImage($ruta_udg, 'isologo_udg_cid', 'isologo_udg.png');
            if (file_exists($ruta_epale)) $mail->addEmbeddedImage($ruta_epale, 'logotipo_epale_cid', 'logotipo-epale.png');
            if (file_exists($ruta_header_bg)) $mail->addEmbeddedImage($ruta_header_bg, 'email_header_bg_cid', 'email-header.jpg');
            if (file_exists($ruta_lock)) $mail->addEmbeddedImage($ruta_lock, 'icon_lock_cid', 'icon-lock.png');
        }

        $mail->isHTML(true);
        $mail->Subject = 'E-PALE: Tu código de recuperación de contraseña';

        $nombre_html = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $codigo_html = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');

        // Construir celdas individuales para cada dígito del código
        $casillas_html = '';
        foreach (str_split($codigo) as $char) {
            $char_html = htmlspecialchars($char, ENT_QUOTES, 'UTF-8');
            $casillas_html .= '<td align="center" valign="middle" style="padding: 0 4px;">
                <div class="email-digit" style="width: 44px; height: 50px; line-height: 50px; background-color: #eef2ff; border: 1px solid #c7d2fe; border-radius: 9px; font-family: \'Courier New\', Courier, monospace, sans-serif; font-size: 25px; font-weight: 800; color: #001a4e; text-align: center; box-shadow: 0 3px 6px rgba(0, 26, 87, 0.1);">' . $char_html . '</div>
            </td>';
        }

        $html = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Recuperación de Contraseña</title>
            <style>' . obtener_css_comun_email() . '</style>
        </head>
        <body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #334155; -webkit-text-size-adjust: 100%;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f1f5f9; padding: 35px 12px;">
                <tr>
                    <td align="center">
                        <!-- Tarjeta Principal con bordes redondeados y sombra suspendida -->
                        <table class="email-container" role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width: 100%; max-width: 540px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01); border: 1px solid #e2e8f0; margin: 0 auto;">
                            ' . obtener_html_cabecera_email($recursos['header_bg_src'], $recursos['udg_logo_src'], $recursos['epale_logo_src']) . '

                            <!-- Cuerpo de la Tarjeta -->
                            <tr>
                                <td class="email-body" style="padding: 32px 34px 28px 34px;">
                                    <!-- Icono Candado Ilustrado -->
                                    <div style="text-align: center; margin-bottom: 20px;">
                                        <img src="' . $lock_src . '" alt="Recuperación de Contraseña" style="height: 80px; width: auto; max-height: 80px; display: block; margin: 0 auto; border: 0;">
                                    </div>

                                    <!-- Título Principal -->
                                    <h1 style="color: #001a4e; font-size: 22px; font-weight: 700; margin: 0 0 20px 0; text-align: center; line-height: 1.35; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
                                        Recuperación de Contraseña
                                    </h1>

                                    <!-- Saludo e Instrucción -->
                                    <p style="color: #334155; font-size: 15px; line-height: 1.6; margin: 0 0 14px 0;">
                                        Hola <strong style="color: #0f172a;">' . $nombre_html . '</strong>,
                                    </p>
                                    <p style="color: #334155; font-size: 15px; line-height: 1.6; margin: 0 0 22px 0;">
                                        Recibimos una solicitud para restablecer la contraseña de tu cuenta en E-PALE. Ingresa el siguiente código de seguridad en la pantalla:
                                    </p>

                                    <!-- Contenedor del Código: Caja de guiones con 6 casillas individuales -->
                                    <div style="text-align: center; margin: 24px 0 18px 0;">
                                        <div class="email-code-box" style="display: inline-block; border: 2px dashed #001a4e; border-radius: 14px; padding: 12px 14px; background-color: #fafbfc;">
                                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin: 0 auto;">
                                                <tr>
                                                    ' . $casillas_html . '
                                                </tr>
                                            </table>
                                        </div>
                                        <!-- Temporizador -->
                                        <div style="color: #475569; font-size: 13.5px; margin-top: 14px;">
                                            ⏱️ Este código expira en <strong style="color: #0f172a;">5 minutos</strong>.
                                        </div>
                                    </div>

                                    <!-- Cuadro de Advertencia de Seguridad (Borde Naranja/Ámbar) -->
                                    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #ea580c; border-radius: 8px; padding: 15px 18px; margin: 22px 0 24px 0; font-size: 13.5px; line-height: 1.6;">
                                        <strong style="color: #0f172a;">¿No solicitaste este cambio?</strong> <span style="color: #334155;">Puedes ignorar este mensaje; tu contraseña actual continuará siendo segura y no se modificará sin este código.</span>
                                    </div>

                                    <!-- Separador -->
                                    <hr style="border: 0; height: 1px; background-color: #e2e8f0; margin: 26px 0 20px 0;">

                                    <!-- Pie de Notificación -->
                                    <div style="text-align: center; color: #64748b; font-size: 12px; line-height: 1.55;">
                                        <p style="margin: 0 0 4px 0;">Este es un mensaje automático de seguridad generado por E-PALE.</p>
                                        <p style="margin: 0;">© ' . date('Y') . ' Coordinación PALE - CUCEA.</p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';

        $mail->Body = $html;
        $mail->AltBody = "E-PALE - Recuperación de contraseña\n\nHola {$nombre},\nTu código de verificación es: {$codigo}\nEste código expira en 5 minutos.\n\nSi no solicitaste este cambio, ignora este mensaje.";

        $mail->send();
        return ['exito' => true, 'error' => null];
    } catch (Exception $e) {
        error_log("Error al enviar código de recuperación: " . $mail->ErrorInfo);
        return ['exito' => false, 'error' => 'No se pudo enviar el correo: ' . $mail->ErrorInfo];
    }
}

/**
 * Envia notificación de alerta confirmando que la contraseña ha sido restablecida.
 */
function enviar_alerta_password_cambiada(string $destinatario, string $nombre, string $fecha_hora): array {
    $config = obtener_config_smtp();

    if (empty($config['user']) || empty($config['pass'])) {
        return ['exito' => false, 'error' => 'Faltan credenciales SMTP en el archivo .env.'];
    }

    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
        return ['exito' => false, 'error' => 'Correo destinatario no válido.'];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['user'];
        $mail->Password   = $config['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;
        $mail->XMailer    = null; // Omitir cabecera X-Mailer para mayor privacidad y formato institucional limpio

        $mail->setFrom($config['user'], $config['from_name']);
        $mail->addAddress($destinatario, $nombre);

        // Resolver recursos visuales (URL remota sin adjuntos o respaldo local vía CID)
        $recursos = obtener_recursos_header_email();
        $check_src = $recursos['check_src'];

        if (!$recursos['remoto']) {
            $ruta_udg       = __DIR__ . '/img/isologo_udg.png';
            $ruta_epale     = __DIR__ . '/img/logotipo-epale.png';
            $ruta_header_bg = file_exists(__DIR__ . '/img/email/email-header.jpg') ? __DIR__ . '/img/email/email-header.jpg' : __DIR__ . '/img/email-header.jpg';
            $ruta_check     = __DIR__ . '/img/email/icon-check.png';

            if (file_exists($ruta_udg)) $mail->addEmbeddedImage($ruta_udg, 'isologo_udg_cid', 'isologo_udg.png');
            if (file_exists($ruta_epale)) $mail->addEmbeddedImage($ruta_epale, 'logotipo_epale_cid', 'logotipo-epale.png');
            if (file_exists($ruta_header_bg)) $mail->addEmbeddedImage($ruta_header_bg, 'email_header_bg_cid', 'email-header.jpg');
            if (file_exists($ruta_check)) $mail->addEmbeddedImage($ruta_check, 'icon_check_cid', 'icon-check.png');
        }

        $mail->isHTML(true);
        $mail->Subject = 'E-PALE: Tu contraseña ha sido restablecida correctamente';

        $nombre_html = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $fecha_html  = htmlspecialchars($fecha_hora, ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Contraseña Restablecida con Éxito</title>
            <style>' . obtener_css_comun_email() . '</style>
        </head>
        <body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #334155; -webkit-text-size-adjust: 100%;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f1f5f9; padding: 35px 12px;">
                <tr>
                    <td align="center">
                        <!-- Tarjeta Principal con bordes redondeados y sombra suave -->
                        <table class="email-container" role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width: 100%; max-width: 540px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01); border: 1px solid #e2e8f0; margin: 0 auto;">
                            ' . obtener_html_cabecera_email($recursos['header_bg_src'], $recursos['udg_logo_src'], $recursos['epale_logo_src']) . '

                            <!-- Cuerpo de la Tarjeta -->
                            <tr>
                                <td class="email-body" style="padding: 32px 34px 28px 34px;">
                                    <!-- Icono de Verificación Ilustrado -->
                                    <div style="text-align: center; margin-bottom: 20px;">
                                        <img src="' . $check_src . '" alt="Contraseña Restablecida" style="height: 80px; width: auto; max-height: 80px; display: block; margin: 0 auto; border: 0;">
                                    </div>

                                    <!-- Título Principal -->
                                    <h1 style="color: #001a4e; font-size: 22px; font-weight: 700; margin: 0 0 22px 0; text-align: center; line-height: 1.35; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
                                        Contraseña Restablecida con Éxito
                                    </h1>

                                    <!-- Saludo y Mensaje -->
                                    <p style="color: #334155; font-size: 15px; line-height: 1.6; margin: 0 0 14px 0;">
                                        Hola <strong style="color: #0f172a;">' . $nombre_html . '</strong>,
                                    </p>
                                    <p style="color: #334155; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
                                        Te confirmamos que la contraseña asociada a tu cuenta institucional en <strong style="color: #0f172a;">E-PALE</strong> ha sido modificada satisfactoriamente.
                                    </p>

                                    <!-- Cuadro Informativo (Borde Azul Izquierdo) -->
                                    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #0056b3; border-radius: 8px; padding: 15px 18px; margin: 20px 0 16px 0; font-size: 14px; line-height: 1.65;">
                                        <div><strong style="color: #0f172a;">Fecha y hora:</strong> <span style="color: #334155;">' . $fecha_html . '</span></div>
                                        <div style="margin-top: 5px;"><strong style="color: #0f172a;">Plataforma:</strong> <span style="color: #334155;">E-PALE (CUCEA)</span></div>
                                    </div>

                                    <!-- Cuadro de Advertencia de Seguridad (Borde Rojo Izquierdo) -->
                                    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #dc2626; border-radius: 8px; padding: 15px 18px; margin: 16px 0 26px 0; font-size: 13.5px; line-height: 1.6;">
                                        <div style="color: #b91c1c; font-weight: 700; font-size: 14.5px; margin-bottom: 6px;">¿Tú no realizaste este cambio?</div>
                                        <div style="color: #334155;">
                                            Si no reconoces esta acción, es posible que alguien haya accedido a tu cuenta.<br>
                                            Por favor <strong style="color: #991b1b; text-decoration: underline;">comunícate de inmediato con la administración de E-PALE</strong> o el soporte técnico para proteger tu cuenta institucional.
                                        </div>
                                    </div>

                                    <!-- Separador Común -->
                                    <hr style="border: 0; height: 1px; background-color: #e2e8f0; margin: 26px 0 20px 0;">

                                    <!-- Pie de Notificación Común -->
                                    <div style="text-align: center; color: #64748b; font-size: 12px; line-height: 1.55;">
                                        <p style="margin: 0 0 4px 0;">Este es un mensaje automático de seguridad generado por E-PALE.</p>
                                        <p style="margin: 0;">© ' . date('Y') . ' Coordinación PALE - CUCEA.</p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';

        $mail->Body = $html;
        $mail->AltBody = "E-PALE - Contraseña Restablecida con Éxito\n\nHola {$nombre},\n\nTe confirmamos que la contraseña asociada a tu cuenta institucional en E-PALE ha sido modificada satisfactoriamente.\n\nFecha y hora: {$fecha_hora}\nPlataforma: E-PALE (CUCEA)\n\n¿Tú no realizaste este cambio?\nSi no reconoces esta acción, es posible que alguien haya accedido a tu cuenta. Por favor comunícate de inmediato con la administración de E-PALE o el soporte técnico para proteger tu cuenta institucional.\n\nNotificación automática de seguridad generada por E-PALE.\n© " . date('Y') . " Coordinación PALE - CUCEA.";

        $mail->send();
        return ['exito' => true, 'error' => null];
    } catch (Exception $e) {
        error_log("Error al enviar alerta de cambio de contraseña: " . $mail->ErrorInfo);
        return ['exito' => false, 'error' => 'No se pudo enviar la alerta: ' . $mail->ErrorInfo];
    }
}

/**
 * Retorna la URL base de la aplicación para enlaces en correos.
 */
function obtener_app_base_url(): string {
    $env_file = __DIR__ . '/.env';
    $env = file_exists($env_file) ? parse_ini_file($env_file) : [];
    if (!empty($env['APP_URL'])) {
        return rtrim($env['APP_URL'], '/');
    }
    if (isset($_SERVER['HTTP_HOST'])) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . '/e-pale';
    }
    return 'http://localhost/e-pale';
}

/**
 * Consulta y estructura todos los datos de la solicitud (alumno, materia, horarios, profesor) para el correo.
 */
function obtener_datos_solicitud_notificacion(PDO $pdo, string $tipo, int $solicitud_id): ?array {
    $tabla = ($tipo === 'baja') ? 'solicitudes_bajas' : 'solicitudes_altas';
    $alias = ($tipo === 'baja') ? 'sb' : 'sa';

    $sql = "
        SELECT 
            $alias.solicitud_id,
            $alias.inscripcion_id,
            $alias.motivo,
            $alias.descripcion,
            $alias.estatus,
            $alias.respuesta_admin,
            $alias.fecha_solicitud,
            $alias.fecha_respuesta,
            u.codigo AS codigo_alumno,
            u.nombre AS alumno_nombre,
            u.apellido_paterno AS alumno_ap_pat,
            u.apellido_materno AS alumno_ap_mat,
            u.correo AS alumno_correo,
            m.materia_id,
            m.clave AS clave_materia,
            m.nombre AS materia_nombre,
            m.nivel AS materia_nivel,
            g.nrc,
            g.clave_grupo,
            g.ciclo_id,
            c.nombre AS ciclo_nombre,
            up.nombre AS prof_nombre,
            up.apellido_paterno AS prof_ap_pat,
            up.apellido_materno AS prof_ap_mat
        FROM $tabla $alias
        JOIN inscripciones i ON $alias.inscripcion_id = i.inscripcion_id
        JOIN alumnos a ON i.alumno_id = a.alumno_id
        JOIN usuarios u ON a.usuario_id = u.usuario_id
        JOIN grupos g ON i.nrc = g.nrc
        JOIN materias m ON g.materia_id = m.materia_id
        JOIN ciclos c ON g.ciclo_id = c.ciclo_id
        LEFT JOIN usuarios up ON g.profesor_id = up.usuario_id
        WHERE $alias.solicitud_id = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$solicitud_id]);
    $sol = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sol) {
        return null;
    }

    $stmtG = $pdo->prepare("
        SELECT g.nrc, h.modalidad, h.hora_inicio, h.hora_fin, h.dias_patron, h.aula 
        FROM grupos g 
        LEFT JOIN horarios h ON g.nrc = h.nrc
        WHERE g.materia_id = ? AND g.clave_grupo = ?
    ");
    $stmtG->execute([$sol['materia_id'], $sol['clave_grupo']]);
    $grupos_clase = $stmtG->fetchAll(PDO::FETCH_ASSOC);

    $nrc_presencial = 'NA';
    $nrc_virtual = 'NA';
    $horarios_parts = [];

    $map_dias = ['L' => 'Lunes', 'M' => 'Martes', 'I' => 'Miércoles', 'J' => 'Jueves', 'V' => 'Viernes', 'S' => 'Sábado', 'D' => 'Domingo'];

    foreach ($grupos_clase as $gc) {
        $mod = strtoupper($gc['modalidad'] ?? '');
        $dias_raw = $gc['dias_patron'] ?? '';
        $dias_txt = '';
        if (!empty($dias_raw)) {
            $letras = explode('-', $dias_raw);
            $nombres = [];
            foreach ($letras as $l) {
                $l = trim($l);
                if (isset($map_dias[$l])) $nombres[] = $map_dias[$l];
            }
            if (count($nombres) === 2) {
                $dias_txt = $nombres[0] . ' y ' . $nombres[1];
            } elseif (!empty($nombres)) {
                $dias_txt = implode(', ', $nombres);
            }
        }

        $horas_txt = '';
        if (!empty($gc['hora_inicio']) && !empty($gc['hora_fin'])) {
            $horas_txt = substr($gc['hora_inicio'], 0, 5) . ' - ' . substr($gc['hora_fin'], 0, 5) . ' hrs';
        }

        if ($mod === 'PRESENCIAL') {
            $nrc_presencial = $gc['nrc'];
            if (!empty($dias_txt) && !empty($horas_txt)) {
                $aula = !empty($gc['aula']) ? " (Aula {$gc['aula']})" : '';
                $horarios_parts[] = "Presencial: {$dias_txt} {$horas_txt}{$aula}";
            }
        } elseif ($mod === 'VIRTUAL') {
            $nrc_virtual = $gc['nrc'];
            if (!empty($dias_txt) && !empty($horas_txt)) {
                $aula = !empty($gc['aula']) ? " ({$gc['aula']})" : '';
                $horarios_parts[] = "Virtual: {$dias_txt} {$horas_txt}{$aula}";
            }
        } else {
            if ($nrc_presencial === 'NA') {
                $nrc_presencial = $gc['nrc'];
            }
            if (!empty($dias_txt) && !empty($horas_txt)) {
                $horarios_parts[] = "{$dias_txt} {$horas_txt}";
            }
        }
    }

    if (!empty($horarios_parts)) {
        if (count($horarios_parts) === 1) {
            $horario_final = preg_replace('/^(Presencial|Virtual):\s*/i', '', $horarios_parts[0]);
        } else {
            $horario_final = implode('<br>', $horarios_parts);
        }
    } else {
        $horario_final = 'Por asignar';
    }

    $map_romano = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X'];
    $nivel_romano = $map_romano[(int)($sol['materia_nivel'] ?? 1)] ?? (string)($sol['materia_nivel'] ?? 1);

    $clave = trim($sol['clave_materia'] ?? '');
    $materia_nom = trim($sol['materia_nombre'] ?? '');
    $clave_y_materia = !empty($clave) ? "{$clave} - {$materia_nom} {$nivel_romano}" : "{$materia_nom} {$nivel_romano}";

    $profesor_raw = trim(($sol['prof_nombre'] ?? '') . ' ' . ($sol['prof_ap_pat'] ?? '') . ' ' . ($sol['prof_ap_mat'] ?? ''));
    // Limpiar títulos honoríficos / académicos para presentar únicamente el nombre completo
    $profesor_limpio = preg_replace('/^(Mtro\.|Mtra\.|Prof\.|Profr\.|Profra\.|Dr\.|Dra\.|Lic\.)\s+/i', '', $profesor_raw);
    if (empty($profesor_limpio)) {
        $profesor_limpio = 'Por asignar';
    }

    $alumno_nombre = trim(($sol['alumno_nombre'] ?? '') . ' ' . ($sol['alumno_ap_pat'] ?? '') . ' ' . ($sol['alumno_ap_mat'] ?? ''));

    return [
        'solicitud_id'    => (int)$sol['solicitud_id'],
        'alumno_correo'   => trim($sol['alumno_correo'] ?? ''),
        'alumno_nombre'   => $alumno_nombre,
        'codigo_alumno'   => trim($sol['codigo_alumno'] ?? ''),
        'clave_y_materia' => $clave_y_materia,
        'nrc_presencial'  => $nrc_presencial,
        'nrc_virtual'     => $nrc_virtual,
        'horario'         => $horario_final,
        'profesor'        => $profesor_limpio,
        'ciclo'           => trim($sol['ciclo_nombre'] ?? '2026-B'),
    ];
}

/**
 * Envía la notificación automática por correo cuando un administrador autoriza o rechaza una solicitud (alta o baja).
 */
function enviar_notificacion_resolucion(PDO $pdo, string $tipo, int $solicitud_id, string $accion, string $respuesta_admin = ''): array {
    $tipo = strtolower(trim($tipo));
    $accion = strtolower(trim($accion));
    $es_aprobada = in_array($accion, ['aprobar', 'aprobada', 'aprobado', 'autorizar', 'autorizada']);

    $datos = obtener_datos_solicitud_notificacion($pdo, $tipo, $solicitud_id);
    if (!$datos) {
        return ['exito' => false, 'success' => false, 'error' => "No se encontró la solicitud con ID {$solicitud_id}"];
    }

    if (empty($datos['alumno_correo']) || !filter_var($datos['alumno_correo'], FILTER_VALIDATE_EMAIL)) {
        return ['exito' => false, 'success' => false, 'error' => "El estudiante ({$datos['alumno_nombre']}) no tiene un correo institucional válido registrado."];
    }

    $config = obtener_config_smtp();
    if (empty($config['user']) || empty($config['pass'])) {
        return ['exito' => false, 'success' => false, 'error' => 'Configuración SMTP incompleta en .env'];
    }

    if (empty(trim($respuesta_admin))) {
        if ($tipo === 'alta') {
            $respuesta_admin = $es_aprobada ? 'Su solicitud de alta ha sido aprobada.' : 'Su solicitud de alta ha sido rechazada.';
        } else {
            $respuesta_admin = $es_aprobada ? 'Su solicitud de baja ha sido aprobada.' : 'Su solicitud de baja ha sido rechazada.';
        }
    }

    $recursos = obtener_recursos_header_email();
    $base_url = obtener_app_base_url();
    $fecha_resolucion = date('d/m/Y \a \l\a\s h:i A');

    $alumno_nom_html = htmlspecialchars($datos['alumno_nombre'], ENT_QUOTES, 'UTF-8');
    $alumno_cod_html = htmlspecialchars($datos['codigo_alumno'], ENT_QUOTES, 'UTF-8');
    $materia_html    = htmlspecialchars($datos['clave_y_materia'], ENT_QUOTES, 'UTF-8');
    $nrc_pres_html   = htmlspecialchars($datos['nrc_presencial'], ENT_QUOTES, 'UTF-8');
    $nrc_virt_html   = htmlspecialchars($datos['nrc_virtual'], ENT_QUOTES, 'UTF-8');
    $profesor_html   = htmlspecialchars($datos['profesor'], ENT_QUOTES, 'UTF-8');
    $ciclo_html      = htmlspecialchars($datos['ciclo'], ENT_QUOTES, 'UTF-8');
    $resp_admin_html = htmlspecialchars(trim($respuesta_admin), ENT_QUOTES, 'UTF-8');

    // Determinación de textos, insignias, íconos y botones por caso
    if ($tipo === 'alta') {
        if ($es_aprobada) {
            $asunto = 'E-PALE: Tu solicitud de alta ha sido autorizada';
            $badge_html = '<span class="email-badge" style="display: inline-block; background-color: #ecfdf5; color: #065f46; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 20px; border: 1px solid #a7f3d0; line-height: 1.4;">Solicitud de Alta • Autorizada</span>';
            $icon_src = $recursos['check_src'];
            $icon_alt = 'Alta Autorizada';
            $titulo_html = 'Solicitud de Alta Autorizada';
            $intro_p = 'Nos complace informarte que tu solicitud para dar de alta una materia en <strong style="color: #0f172a;">E-PALE</strong> ha sido <strong>autorizada</strong> por la Coordinación. Ya te encuentras formalmente inscrito en el grupo y la materia ha sido agregada a tu horario activo.';
            $border_table_color = '#10b981';
            $label_materia = 'Materia / Nivel:';
            $label_horario = 'Horario:';
            $label_fecha   = 'Fecha de resolución:';
            $box_html = '<div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #10b981; border-radius: 8px; padding: 14px 18px; margin: 16px 0 24px 0; font-size: 13.5px; line-height: 1.6;">
                <div style="color: #065f46; font-weight: 700; font-size: 14px; margin-bottom: 4px;">Resolución de la Coordinación:</div>
                <div style="color: #166534;">"' . $resp_admin_html . '"</div>
            </div>';
            $btn_text = 'Ver mi Horario y Clases';
            $btn_url = $base_url . '/estudiante/horario';
        } else {
            $asunto = 'E-PALE: Tu solicitud de alta no fue autorizada';
            $badge_html = '<span class="email-badge" style="display: inline-block; background-color: #fef2f2; color: #991b1b; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 20px; border: 1px solid #fecaca; line-height: 1.4;">Solicitud de Alta • No Autorizada</span>';
            $icon_src = $recursos['xmark_src'];
            $icon_alt = 'Alta No Autorizada';
            $titulo_html = 'Solicitud de Alta No Autorizada';
            $intro_p = 'Te notificamos que tu solicitud para dar de alta la materia no pudo ser autorizada en esta ocasión. Tu horario actual no ha sufrido modificaciones.';
            $border_table_color = '#ef4444';
            $label_materia = 'Materia solicitada:';
            $label_horario = 'Horario solicitado:';
            $label_fecha   = 'Fecha de respuesta:';
            $box_html = '<div style="background-color: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc2626; border-radius: 8px; padding: 14px 18px; margin: 16px 0 24px 0; font-size: 13.5px; line-height: 1.6;">
                <div style="color: #991b1b; font-weight: 700; font-size: 14px; margin-bottom: 4px;">Motivo de la resolución:</div>
                <div style="color: #7f1d1d;">"' . $resp_admin_html . '"</div>
            </div>';
            $btn_text = 'Consultar Grupos con Cupo';
            $btn_url = $base_url . '/estudiante/oferta_academica';
        }
    } else {
        // BAJA
        if ($es_aprobada) {
            $asunto = 'E-PALE: Tu solicitud de baja ha sido autorizada';
            $badge_html = '<span class="email-badge" style="display: inline-block; background-color: #ecfdf5; color: #065f46; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 20px; border: 1px solid #a7f3d0; line-height: 1.4;">Solicitud de Baja • Autorizada</span>';
            $icon_src = $recursos['check_src'];
            $icon_alt = 'Baja Autorizada';
            $titulo_html = 'Solicitud de Baja Autorizada';
            $intro_p = 'Te confirmamos que tu solicitud de baja de materia ha sido <strong>autorizada</strong> por la Coordinación. La materia ha sido removida formalmente de tu horario activo del ciclo escolar.';
            $border_table_color = '#10b981';
            $label_materia = 'Materia dada de baja:';
            $label_horario = 'Horario que cursabas:';
            $label_fecha   = 'Fecha de respuesta:';
            $box_html = '<div style="background-color: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0284c7; border-radius: 8px; padding: 14px 18px; margin: 16px 0 24px 0; font-size: 13.5px; line-height: 1.6;">
                <div style="color: #0369a1; font-weight: 700; font-size: 14px; margin-bottom: 4px;">Información de tu Expediente:</div>
                <div style="color: #0c4a6e;">El estatus de la inscripción se actualizó a <strong>BAJA</strong>. Conforme al reglamento del programa, tu historial y evaluaciones previas se conservan en tu expediente sin afectación negativa a tu promedio.</div>
            </div>';
            if (!empty($resp_admin_html) && $resp_admin_html !== 'Su solicitud de baja ha sido aprobada.') {
                $box_html .= '<div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #10b981; border-radius: 8px; padding: 14px 18px; margin: 0 0 24px 0; font-size: 13.5px; line-height: 1.6;">
                    <div style="color: #065f46; font-weight: 700; font-size: 14px; margin-bottom: 4px;">Comentario de la Coordinación:</div>
                    <div style="color: #166534;">"' . $resp_admin_html . '"</div>
                </div>';
            }
            $btn_text = 'Ver mi Horario Actualizado';
            $btn_url = $base_url . '/estudiante/horario';
        } else {
            $asunto = 'E-PALE: Tu solicitud de baja no fue autorizada';
            $badge_html = '<span class="email-badge" style="display: inline-block; background-color: #fef2f2; color: #991b1b; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 20px; border: 1px solid #fecaca; line-height: 1.4;">Solicitud de Baja • No Autorizada</span>';
            $icon_src = $recursos['xmark_src'];
            $icon_alt = 'Baja No Autorizada';
            $titulo_html = 'Solicitud de Baja No Autorizada';
            $intro_p = 'Te notificamos que tu solicitud de baja de materia no fue autorizada por la administración. Permaneces formalmente inscrito en la materia y tu compromiso académico continúa activo.';
            $border_table_color = '#ef4444';
            $label_materia = 'Materia en curso:';
            $label_horario = 'Horario:';
            $label_fecha   = 'Fecha de respuesta:';
            $box_html = '<div style="background-color: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc2626; border-radius: 8px; padding: 14px 18px; margin: 16px 0 24px 0; font-size: 13.5px; line-height: 1.6;">
                <div style="color: #991b1b; font-weight: 700; font-size: 14px; margin-bottom: 4px;">Motivo de la resolución:</div>
                <div style="color: #7f1d1d;">"' . $resp_admin_html . '"</div>
            </div>';
            $btn_text = 'Consultar Aula y Clases';
            $btn_url = $base_url . '/estudiante/horario';
        }
    }

    $cabecera_html = obtener_html_cabecera_email($recursos['header_bg_src'], $recursos['udg_logo_src'], $recursos['epale_logo_src']);
    $css_comun = obtener_css_comun_email();

    $html = '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8') . '</title>
        <style>' . $css_comun . '</style>
    </head>
    <body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
        <table role="presentation" width="100%" bgcolor="#f1f5f9" cellpadding="0" cellspacing="0" border="0" style="table-layout: fixed; width: 100%;">
            <tr>
                <td align="center" style="padding: 24px 12px;">
                    <table role="presentation" class="email-container" width="540" bgcolor="#ffffff" cellpadding="0" cellspacing="0" border="0" style="max-width: 540px; width: 100%; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; margin: 0 auto; border-collapse: separate;">
                        ' . $cabecera_html . '
                        <tr>
                            <td class="email-body" style="padding: 32px 30px;">
                                <div style="margin-bottom: 16px;">
                                    ' . $badge_html . '
                                </div>
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <img src="' . $icon_src . '" alt="' . $icon_alt . '" style="height: 80px; width: auto; max-height: 80px; display: block; margin: 0 auto; border: 0;">
                                </div>
                                <h1 class="email-title" style="color: #001a4e; font-size: 22px; font-weight: 700; margin: 0 0 20px 0; text-align: center; line-height: 1.35; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
                                    ' . $titulo_html . '
                                </h1>
                                <p style="color: #334155; font-size: 15px; line-height: 1.6; margin: 0 0 14px 0;">
                                    Hola <strong style="color: #0f172a;">' . $alumno_nom_html . '</strong> (Código: <strong style="color: #0f172a;">' . $alumno_cod_html . '</strong>),
                                </p>
                                <p style="color: #334155; font-size: 15px; line-height: 1.6; margin: 0 0 20px 0;">
                                    ' . $intro_p . '
                                </p>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid ' . $border_table_color . '; border-radius: 8px; margin: 20px 0 16px 0; padding: 16px 18px; font-size: 14px; line-height: 1.65;">
                                    <tr>
                                        <td style="padding: 4px 0; color: #64748b; width: 38%; font-size: 13.5px;">' . $label_materia . '</td>
                                        <td style="padding: 4px 0; color: #0f172a; font-weight: 700;">' . $materia_html . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 4px 0; color: #64748b; font-size: 13.5px;">NRC Presencial:</td>
                                        <td style="padding: 4px 0; color: #0f172a; font-weight: 600;">' . $nrc_pres_html . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 4px 0; color: #64748b; font-size: 13.5px;">NRC Virtual:</td>
                                        <td style="padding: 4px 0; color: #0f172a; font-weight: 600;">' . $nrc_virt_html . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 4px 0; color: #64748b; font-size: 13.5px;">' . $label_horario . '</td>
                                        <td style="padding: 4px 0; color: #0f172a; font-weight: 600;">' . $datos['horario'] . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 4px 0; color: #64748b; font-size: 13.5px;">Profesor(a):</td>
                                        <td style="padding: 4px 0; color: #0f172a; font-weight: 600;">' . $profesor_html . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 4px 0; color: #64748b; font-size: 13.5px;">Ciclo Escolar:</td>
                                        <td style="padding: 4px 0; color: #0f172a; font-weight: 600;">' . $ciclo_html . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 4px 0; color: #64748b; font-size: 13.5px;">' . $label_fecha . '</td>
                                        <td style="padding: 4px 0; color: #334155;">' . $fecha_resolucion . '</td>
                                    </tr>
                                </table>
                                ' . $box_html . '
                                <div style="text-align: center; margin-top: 26px;">
                                    <a class="email-btn" href="' . htmlspecialchars($btn_url, ENT_QUOTES, 'UTF-8') . '" style="display: inline-block; background-color: #001a4e; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 14px; padding: 12px 28px; border-radius: 8px; box-shadow: 0 3px 8px rgba(0,26,78,0.25); text-align: center;">
                                        ' . $btn_text . '
                                    </a>
                                </div>
                                <hr style="border: 0; height: 1px; background-color: #e2e8f0; margin: 26px 0 20px 0;">
                                <div style="text-align: center; color: #64748b; font-size: 12px; line-height: 1.55;">
                                    <p style="margin: 0 0 4px 0;">Este es un mensaje institucional automático generado por la plataforma E-PALE.</p>
                                    <p style="margin: 0;">© ' . date('Y') . ' Coordinación PALE - CUCEA.</p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['user'];
        $mail->Password   = $config['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['port'];
        $mail->CharSet    = 'UTF-8';

        // Suprimir cabecera X-Mailer para presentación profesional y mayor entregabilidad
        $mail->XMailer = null;

        $mail->setFrom($config['user'], $config['from_name']);
        $mail->addAddress($datos['alumno_correo'], $datos['alumno_nombre']);

        if (!$recursos['remoto']) {
            $ruta_udg       = __DIR__ . '/img/isologo_udg.png';
            $ruta_epale     = __DIR__ . '/img/logotipo-epale.png';
            $ruta_header_bg = file_exists(__DIR__ . '/img/email/email-header.jpg') ? __DIR__ . '/img/email/email-header.jpg' : __DIR__ . '/img/email-header.jpg';
            $ruta_check     = __DIR__ . '/img/email/icon-check.png';
            $ruta_xmark     = __DIR__ . '/img/email/icon-xmark.png';

            if (file_exists($ruta_udg)) $mail->addEmbeddedImage($ruta_udg, 'isologo_udg_cid', 'isologo_udg.png');
            if (file_exists($ruta_epale)) $mail->addEmbeddedImage($ruta_epale, 'logotipo_epale_cid', 'logotipo-epale.png');
            if (file_exists($ruta_header_bg)) $mail->addEmbeddedImage($ruta_header_bg, 'email_header_bg_cid', 'email-header.jpg');
            if ($es_aprobada && file_exists($ruta_check)) {
                $mail->addEmbeddedImage($ruta_check, 'icon_check_cid', 'icon-check.png');
            }
            if (!$es_aprobada && file_exists($ruta_xmark)) {
                $mail->addEmbeddedImage($ruta_xmark, 'icon_xmark_cid', 'icon-xmark.png');
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $html;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</tr>', '</p>'], ["\n", "\n", "\n\n"], $html));

        $mail->send();
        return ['exito' => true, 'success' => true, 'error' => null];
    } catch (Exception $e) {
        error_log("Error al enviar notificación de solicitud ($tipo #$solicitud_id): " . $mail->ErrorInfo);
        return ['exito' => false, 'success' => false, 'error' => $mail->ErrorInfo];
    }
}

/**
 * Helper directo para solicitudes de Alta.
 */
function enviar_notificacion_alta(PDO $pdo, int $solicitud_id, string $accion, string $respuesta_admin = ''): array {
    return enviar_notificacion_resolucion($pdo, 'alta', $solicitud_id, $accion, $respuesta_admin);
}

/**
 * Helper directo para solicitudes de Baja.
 */
function enviar_notificacion_baja(PDO $pdo, int $solicitud_id, string $accion, string $respuesta_admin = ''): array {
    return enviar_notificacion_resolucion($pdo, 'baja', $solicitud_id, $accion, $respuesta_admin);
}

