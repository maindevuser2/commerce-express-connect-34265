<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/helpers/SecurityHelper.php';
require_once __DIR__ . '/helpers/SecurityLogger.php'; // <-- Agrega esta línea

use Controllers\EmailController;
use Helpers\SecurityHelper;
use Helpers\SecurityLogger;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Sanitizar entrada
$email = SecurityHelper::sanitizeInput($_POST['reset_email'] ?? '');

// Validar email
if (!SecurityHelper::validateEmail($email)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de email inválido']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']}", $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $securityLogger = new SecurityLogger();
    $clientIP = getClientIP();

    // === RATE LIMITING: Verificar intentos recientes ===
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempt_count 
        FROM password_reset_attempts 
        WHERE email = ? 
        AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$email]);
    $attempts = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attempts['attempt_count'] >= 3) {
        $securityLogger->logSecurityEvent(
            null,
            'password_reset_rate_limit',
            "Rate limit excedido para email: {$email} desde IP: {$clientIP}",
            'high'
        );
        
        // Respuesta genérica para no revelar si el email existe
        echo json_encode(['success' => 'Si el correo existe, recibirás un enlace de recuperación.']);
        exit;
    }

    // Registrar intento
    $stmt = $pdo->prepare("INSERT INTO password_reset_attempts (email, ip_address, attempted_at) VALUES (?, ?, NOW())");
    $stmt->execute([$email, $clientIP]);

    // === Verificar si el usuario existe ===
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // SIEMPRE dar la misma respuesta (no revelar si existe o no)
    if (!$user) {
        $securityLogger->logSecurityEvent(
            null,
            'password_reset_nonexistent_email',
            "Intento de reset para email inexistente: {$email} desde IP: {$clientIP}",
            'low'
        );
        
        echo json_encode(['success' => 'Si el correo existe, recibirás un enlace de recuperación.']);
        exit;
    }

    $userId = $user['id'];
    $userName = trim($user['first_name'] . ' ' . $user['last_name']);
    
    // === Generar token seguro con mayor entropía ===
    $token = SecurityHelper::generateRandomString(64);
    $hashedToken = hash('sha256', $token); // Hashear token antes de guardar

    $createdAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hora después

    $baseUrl = APP_URL; // Usa la constante definida en config.php

    // Guardar $hashedToken en la base de datos
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, created_at, expires_at, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $hashedToken, $createdAt, $expiresAt, $clientIP]);

    // Enviar el enlace con el token ORIGINAL
    $resetLink = $baseUrl . "/reset_password.php?token=" . urlencode($token);

    // === Template de email mejorado ===
    $htmlMessage = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 30px 20px; }
            .content p { margin: 15px 0; }
            .button { display: inline-block; padding: 14px 30px; background: #667eea; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
            .button:hover { background: #5568d3; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 20px 0; border-radius: 4px; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
            .security-info { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 12px; margin: 20px 0; border-radius: 4px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Restablece tu Contraseña</h1>
            </div>
            <div class='content'>
                <p>Hola <strong>{$userName}</strong>,</p>
                <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta. Si no fuiste tú, puedes ignorar este correo.</p>
                <p style='text-align: center;'>
                    <a href='{$resetLink}' class='button'>Restablecer mi contraseña</a>
                </p>
                <p>O copia y pega este enlace en tu navegador:</p>
                <p style='word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;'>{$resetLink}</p>
                
                <div class='warning'>
                    <strong>⚠️ Importante:</strong> Este enlace expirará en <strong>1 hora</strong> por motivos de seguridad.
                </div>
                
                <div class='security-info'>
                    <strong>🛡️ Información de seguridad:</strong><br>
                    IP de la solicitud: {$clientIP}<br>
                    Hora: " . date('d/m/Y H:i:s') . "
                </div>
                
                <p>Si no solicitaste este cambio, te recomendamos cambiar tu contraseña inmediatamente e informarnos.</p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " " . ($_ENV['APP_NAME'] ?? 'English Learning Platform') . ". Todos los derechos reservados.</p>
                <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // === Enviar email ===
    $emailController = new EmailController();
    $sent = $emailController->sendEmail($email, '🔐 Restablecer tu contraseña', $htmlMessage);

    if ($sent) {
        // Registrar evento de seguridad
        $securityLogger->logSecurityEvent(
            $userId,
            'password_reset_requested',
            "Solicitud de reset de contraseña desde IP: {$clientIP}",
            'medium'
        );
        
        echo json_encode(['success' => 'Si el correo existe, recibirás un enlace de recuperación.']);
    } else {
        $securityLogger->logSecurityEvent(
            $userId,
            'password_reset_email_failed',
            "Fallo al enviar email de reset para usuario ID: {$userId}",
            'high'
        );
        
        // Respuesta genérica
        echo json_encode(['success' => 'Si el correo existe, recibirás un enlace de recuperación.']);
    }

} catch (Exception $e) {
    error_log("Error en send-reset-email.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al procesar la solicitud. Intenta nuevamente.',
        'debug' => $e->getMessage() // <-- agrega esto solo para depuración
    ]);
}
