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
        'host'      => $env['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port'      => intval($env['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587),
        'user'      => $env['SMTP_USER'] ?? getenv('SMTP_USER') ?: '',
        'pass'      => $env['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '',
        'from_name' => $env['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'E-PALE CUCEA',
    ];
}

/**
 * Genera el cuerpo HTML estilizado con el branding de E-PALE.
 */
function construir_plantilla_aviso(string $titulo, string $cuerpo, bool $tiene_imagen = false, string $audiencia_txt = 'Aviso'): string {
    $cuerpo_html = nl2br(htmlspecialchars($cuerpo));
    $titulo_html = htmlspecialchars($titulo);
    $audiencia_html = htmlspecialchars($audiencia_txt);
    
    $seccion_imagen = '';
    if ($tiene_imagen) {
        // La imagen se incrusta de forma nativa vía CID para que Gmail la muestre sin depender de dominios locales
        $seccion_imagen = '
        <div style="margin: 22px 0 10px 0; text-align: center;">
            <img src="cid:imagen_aviso_cid" alt="Cartel Informativo" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: inline-block;">
        </div>';
    }

    return '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $titulo_html . '</title>
        <style>
            /* Reglas Responsivas Fluidas para Dispositivos Móviles (Gmail App, iOS Mail, etc.) */
            @media only screen and (max-width: 620px) {
                .email-container {
                    width: 100% !important;
                    max-width: 100% !important;
                    border-radius: 8px !important;
                }
                .email-header {
                    padding: 24px 20px !important;
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
            }
        </style>
    </head>
    <body style="margin: 0; padding: 0; background-color: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #333333; -webkit-text-size-adjust: 100%;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f4f6f9; padding: 25px 8px;">
            <tr>
                <td align="center">
                    <table class="email-container" role="presentation" width="600" cellspacing="0" cellpadding="0" style="width: 100%; max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.06); border: 1px solid #e8ecf2; margin: 0 auto;">
                        <!-- Header Banner -->
                        <tr>
                            <td class="email-header" style="background: linear-gradient(135deg, #001a57 0%, #002d80 100%); padding: 30px 40px; text-align: center; border-bottom: 4px solid #f29100;">
                                <h2 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700; letter-spacing: 0.5px;">E-PALE</h2>
                                <p style="color: #cbd5e1; margin: 5px 0 0; font-size: 13px; letter-spacing: 0.5px;">Universidad De Guadalajara - CUCEA</p>
                            </td>
                        </tr>

                        <!-- Body Content -->
                        <tr>
                            <td class="email-body" style="padding: 32px 36px;">
                                <div style="margin-bottom: 16px;">
                                    <span class="email-badge" style="display: inline-block; background-color: #eef2ff; color: #001a57; font-size: 13px; font-weight: 600; padding: 6px 14px; border-radius: 20px; border: 1px solid #c7d2fe; line-height: 1.4;">
                                        ' . $audiencia_html . '
                                    </span>
                                </div>
                                
                                <h1 class="email-title" style="color: #001a57; font-size: 21px; font-weight: 700; margin: 0 0 18px 0; line-height: 1.4;">' . $titulo_html . '</h1>
                                
                                <div class="email-cuerpo-box" style="color: #4b5563; font-size: 15px; line-height: 1.7; background-color: #f8fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #001a57; word-break: break-word; text-align: justify; text-justify: inter-word;">
                                    ' . $cuerpo_html . '
                                </div>

                                ' . $seccion_imagen . '

                                <div style="text-align: center; margin-top: 28px;">
                                    <a class="email-btn" href="http://localhost/e-pale/" style="display: inline-block; background-color: #001a57; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 14px; padding: 12px 28px; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,26,87,0.2); text-align: center;">
                                        Acceder a la Plataforma E-PALE
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <!-- Footer -->
                        <tr>
                            <td class="email-footer" style="background-color: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #edf2f7; color: #94a3b8; font-size: 12px; line-height: 1.5;">
                                <p style="margin: 0 0 5px 0;">Este es un mensaje institucional automático generado por la plataforma E-PALE.</p>
                                <p style="margin: 0;">© ' . date('Y') . ' Coordinación del Programa de Aprendizaje de Lenguas Extranjeras (PALE) - CUCEA.</p>
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

            // Remitente
            $mail->setFrom($config['user'], $config['from_name']);
            // El destinatario principal es el propio correo institucional emisor
            $mail->addAddress($config['user'], $config['from_name']);

            // Los destinatarios van en Cco (Bcc) para proteger la privacidad
            foreach ($lote as $correo_dest) {
                $mail->addBCC($correo_dest);
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

        $mail->setFrom($config['user'], $config['from_name']);
        $mail->addAddress($destinatario, $nombre);

        $mail->isHTML(true);
        $mail->Subject = 'E-PALE: Tu código de recuperación de contraseña';

        $nombre_html = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $codigo_html = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Recuperación de Contraseña</title>
        </head>
        <body style="margin:0; padding:0; background-color:#f4f6f9; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color:#333;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f6f9; padding:25px 8px;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%; max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.06); border:1px solid #e8ecf2; margin:0 auto;">
                            <tr>
                                <td style="background: linear-gradient(135deg, #001a57 0%, #002d80 100%); padding:28px 35px; text-align:center; border-bottom:4px solid #f29100;">
                                    <h2 style="color:#ffffff; margin:0; font-size:24px; font-weight:700;">E-PALE</h2>
                                    <p style="color:#cbd5e1; margin:4px 0 0; font-size:13px;">Universidad de Guadalajara - CUCEA</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px 36px;">
                                    <h1 style="color:#001a57; font-size:20px; font-weight:700; margin:0 0 16px 0;">Recuperación de Contraseña</h1>
                                    <p style="color:#4b5563; font-size:15px; line-height:1.6; margin:0 0 15px 0;">
                                        Hola <strong>' . $nombre_html . '</strong>,
                                    </p>
                                    <p style="color:#4b5563; font-size:15px; line-height:1.6; margin:0 0 20px 0;">
                                        Recibimos una solicitud para restablecer la contraseña de tu cuenta en E-PALE. Ingresa el siguiente código de seguridad en la pantalla:
                                    </p>
                                    <div style="text-align:center; margin:26px 0;">
                                        <div style="display:inline-block; background-color:#eef2ff; border:2px dashed #001a57; border-radius:10px; padding:16px 36px; font-family:\'Courier New\', Courier, monospace; font-size:32px; font-weight:800; letter-spacing:8px; color:#001a57;">
                                            ' . $codigo_html . '
                                        </div>
                                        <p style="color:#64748b; font-size:13px; margin:12px 0 0 0;">
                                            ⏱️ Este código expira en <strong>5 minutos</strong>.
                                        </p>
                                    </div>
                                    <div style="background-color:#fff7ed; border-left:4px solid #f97316; padding:12px 16px; border-radius:6px; margin-top:22px; font-size:13px; color:#9a3412; line-height:1.5;">
                                        <strong>¿No solicitaste este cambio?</strong> Puedes ignorar este mensaje; tu contraseña actual continuará siendo segura y no se modificará sin este código.
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="background-color:#f8fafc; padding:18px 30px; text-align:center; border-top:1px solid #edf2f7; color:#94a3b8; font-size:12px;">
                                    <p style="margin:0 0 4px 0;">Este es un mensaje automático de seguridad generado por E-PALE.</p>
                                    <p style="margin:0;">© ' . date('Y') . ' Coordinación PALE - CUCEA.</p>
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

        $mail->setFrom($config['user'], $config['from_name']);
        $mail->addAddress($destinatario, $nombre);

        $mail->isHTML(true);
        $mail->Subject = 'E-PALE: Tu contraseña ha sido restablecida correctamente';

        $nombre_html = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $fecha_html  = htmlspecialchars($fecha_hora, ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Contraseña Restablecida</title>
        </head>
        <body style="margin:0; padding:0; background-color:#f4f6f9; font-family:-apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color:#333;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f6f9; padding:25px 8px;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="width:100%; max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.06); border:1px solid #e8ecf2; margin:0 auto;">
                            <tr>
                                <td style="background: linear-gradient(135deg, #001a57 0%, #002d80 100%); padding:28px 35px; text-align:center; border-bottom:4px solid #10b981;">
                                    <h2 style="color:#ffffff; margin:0; font-size:24px; font-weight:700;">E-PALE</h2>
                                    <p style="color:#cbd5e1; margin:4px 0 0; font-size:13px;">Universidad de Guadalajara - CUCEA</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:32px 36px;">
                                    <div style="text-align:center; margin-bottom:20px;">
                                        <div style="display:inline-block; width:52px; height:52px; line-height:52px; border-radius:50%; background-color:#ecfdf5; color:#10b981; font-size:26px;">
                                            ✓
                                        </div>
                                    </div>
                                    <h1 style="color:#001a57; font-size:20px; font-weight:700; margin:0 0 16px 0; text-align:center;">Contraseña Restablecida con Éxito</h1>
                                    <p style="color:#4b5563; font-size:15px; line-height:1.6; margin:0 0 15px 0;">
                                        Hola <strong>' . $nombre_html . '</strong>,
                                    </p>
                                    <p style="color:#4b5563; font-size:15px; line-height:1.6; margin:0 0 18px 0;">
                                        Te confirmamos que la contraseña asociada a tu cuenta institucional en <strong>E-PALE</strong> ha sido modificada satisfactoriamente.
                                    </p>
                                    <div style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 18px; margin:18px 0; font-size:14px; color:#334155; line-height:1.6;">
                                        <div><strong>Fecha y hora:</strong> ' . $fecha_html . '</div>
                                        <div><strong>Plataforma:</strong> E-PALE (CUCEA)</div>
                                    </div>
                                    <div style="background-color:#fff1f2; border-left:4px solid #e11d48; padding:14px 16px; border-radius:6px; margin-top:22px; font-size:13px; color:#9f1239; line-height:1.5;">
                                        <strong>¿Tú no realizaste este cambio?</strong><br>
                                        Si no reconoces esta acción, es posible que alguien haya accedido a tu cuenta. Por favor <strong>comunícate de inmediato con la administración de E-PALE</strong> o el soporte técnico para proteger tu cuenta institucional.
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="background-color:#f8fafc; padding:18px 30px; text-align:center; border-top:1px solid #edf2f7; color:#94a3b8; font-size:12px;">
                                    <p style="margin:0 0 4px 0;">Notificación automática de seguridad generada por E-PALE.</p>
                                    <p style="margin:0;">© ' . date('Y') . ' Coordinación PALE - CUCEA.</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';

        $mail->Body = $html;
        $mail->AltBody = "E-PALE - Contraseña Restablecida\n\nHola {$nombre},\nTu contraseña de E-PALE fue cambiada exitosamente el {$fecha_hora}.\n\nSi no fuiste tú, contacta inmediatamente a la administración.";

        $mail->send();
        return ['exito' => true, 'error' => null];
    } catch (Exception $e) {
        error_log("Error al enviar alerta de cambio de contraseña: " . $mail->ErrorInfo);
        return ['exito' => false, 'error' => 'No se pudo enviar la alerta: ' . $mail->ErrorInfo];
    }
}
