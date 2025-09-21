<?php

namespace Controllers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use PDO;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

use Helpers\SecurityHelper;

class EmailController {
    
    private $db;
    private $rateLimitWindow = 3600; // 1 hora
    private $maxEmailsPerHour = 10;

    public function __construct() {
        $database = new \Database();
        $this->db = $database->getConnection();
    }

    /**
     * Enviar email de forma segura
     */
    public function sendEmail($to, $subject, $body, $isHTML = true) {
        try {
            // Validar entrada
            if (!SecurityHelper::validateEmail($to)) {
                error_log("EmailController: Email inválido - $to");
                return false;
            }

            // Verificar rate limiting
            if (!$this->checkRateLimit($to)) {
                error_log("EmailController: Rate limit excedido para $to");
                return false;
            }

            // Sanitizar contenido
            $subject = SecurityHelper::sanitizeInput($subject);
            $to = SecurityHelper::sanitizeInput($to);

            // Configurar PHPMailer
            $mail = new PHPMailer(true);

            // Obtener configuración de email desde variables de entorno
            $smtpHost = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
            $smtpPort = $_ENV['SMTP_PORT'] ?? 587;
            $smtpUsername = $_ENV['SMTP_USERNAME'] ?? '';
            $smtpPassword = $_ENV['SMTP_PASSWORD'] ?? '';
            $fromEmail = $_ENV['FROM_EMAIL'] ?? $smtpUsername;
            $fromName = $_ENV['FROM_NAME'] ?? 'English Learning Platform';

            if (empty($smtpUsername) || empty($smtpPassword)) {
                error_log("EmailController: Credenciales SMTP no configuradas");
                return false;
            }

            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUsername;
            $mail->Password   = $smtpPassword;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtpPort;
            $mail->CharSet    = 'UTF-8';

            // Configuración de seguridad adicional
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Configurar remitente y destinatario
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($fromEmail, $fromName);

            // Configurar contenido
            $mail->isHTML($isHTML);
            $mail->Subject = $subject;
            
            if ($isHTML) {
                $mail->Body = $this->wrapHTMLTemplate($body);
                $mail->AltBody = strip_tags($body);
            } else {
                $mail->Body = $body;
            }

            // Enviar email
            $result = $mail->send();
            
            if ($result) {
                // Registrar envío exitoso
                $this->logEmailSent($to, $subject, true);
                error_log("EmailController: Email enviado exitosamente a $to");
                return true;
            } else {
                error_log("EmailController: Fallo al enviar email a $to");
                return false;
            }

        } catch (Exception $e) {
            error_log("EmailController: Error PHPMailer - " . $e->getMessage());
            $this->logEmailSent($to ?? 'unknown', $subject ?? 'unknown', false, $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            error_log("EmailController: Error general - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar rate limiting para envío de emails
     */
    private function checkRateLimit($email) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as email_count 
                FROM email_logs 
                WHERE recipient_email = ? 
                AND sent_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
                AND success = 1
            ");
            $stmt->execute([$email, $this->rateLimitWindow]);
            $result = $stmt->fetch();

            return $result['email_count'] < $this->maxEmailsPerHour;
        } catch (\Exception $e) {
            error_log("EmailController: Error verificando rate limit - " . $e->getMessage());
            // En caso de error, permitir el envío pero loggearlo
            return true;
        }
    }

    /**
     * Registrar envío de email en la base de datos
     */
    private function logEmailSent($to, $subject, $success, $errorMessage = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_logs (recipient_email, subject, success, error_message, ip_address, sent_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $to,
                $subject,
                $success ? 1 : 0,
                $errorMessage,
                $this->getClientIP()
            ]);
        } catch (\Exception $e) {
            error_log("EmailController: Error registrando log de email - " . $e->getMessage());
        }
    }

    /**
     * Obtener IP del cliente
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Plantilla HTML para emails
     */
    private function wrapHTMLTemplate($content) {
        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>English Learning Platform</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
                a { color: #007bff; }
                .btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>English Learning Platform</h1>
                </div>
                <div class='content'>
                    {$content}
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " English Learning Platform. Todos los derechos reservados.</p>
                    <p>Este es un email automático, por favor no respondas a este mensaje.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Enviar email de bienvenida
     */
    public function sendWelcomeEmail($to, $firstName) {
        $subject = "¡Bienvenido a English Learning Platform!";
        $body = "
            <h2>¡Bienvenido, {$firstName}!</h2>
            <p>Gracias por registrarte en English Learning Platform. Estamos emocionados de tenerte como parte de nuestra comunidad.</p>
            <p>Ahora puedes:</p>
            <ul>
                <li>Explorar nuestros cursos de inglés</li>
                <li>Acceder a contenido exclusivo</li>
                <li>Seguir tu progreso de aprendizaje</li>
            </ul>
            <p><a href='" . ($_ENV['APP_URL'] ?? 'http://localhost:3000') . "/views/client/home.php' class='btn'>Comenzar Ahora</a></p>
            <p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
        ";

        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * Enviar notificación de compra
     */
    public function sendPurchaseConfirmation($to, $firstName, $courseName, $amount) {
        $subject = "Confirmación de Compra - {$courseName}";
        $body = "
            <h2>¡Compra Confirmada!</h2>
            <p>Hola {$firstName},</p>
            <p>Tu compra ha sido procesada exitosamente:</p>
            <div style='background-color: white; padding: 15px; border-left: 4px solid #28a745; margin: 20px 0;'>
                <strong>Curso:</strong> {$courseName}<br>
                <strong>Monto:</strong> $" . number_format($amount, 2) . " USD<br>
                <strong>Fecha:</strong> " . date('d/m/Y H:i') . "
            </div>
            <p>Ya puedes acceder a tu curso desde tu panel de usuario.</p>
            <p><a href='" . ($_ENV['APP_URL'] ?? 'http://localhost:3000') . "/views/client/home.php' class='btn'>Acceder a Mis Cursos</a></p>
        ";

        return $this->sendEmail($to, $subject, $body);
    }

    /**
     * Limpiar logs antiguos de emails
     */
    public function cleanupOldLogs($daysToKeep = 30) {
        try {
            $stmt = $this->db->prepare("DELETE FROM email_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $stmt->execute([$daysToKeep]);
            
            $deletedRows = $stmt->rowCount();
            error_log("EmailController: Limpiados {$deletedRows} logs de email antiguos");
            
            return $deletedRows;
        } catch (\Exception $e) {
            error_log("EmailController: Error limpiando logs antiguos - " . $e->getMessage());
            return false;
        }
    }
}
?>
