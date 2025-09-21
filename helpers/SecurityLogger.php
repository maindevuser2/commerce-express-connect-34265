<?php
namespace Helpers;

require_once __DIR__ . '/../config/database.php';

class SecurityLogger {
    
    private $db;
    
    public function __construct() {
        $database = new \Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Registrar evento de seguridad
     */
    public function logSecurityEvent($userId, $eventType, $description, $severity = 'medium') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_logs (user_id, event_type, description, ip_address, user_agent, severity, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $eventType,
                $description,
                $this->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $severity
            ]);
            
            // También registrar en el log de errores del servidor
            error_log("SECURITY_EVENT: {$eventType} - {$description} - User: {$userId} - IP: " . $this->getClientIP());
            
            return true;
        } catch (\Exception $e) {
            error_log("Error registrando evento de seguridad: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar intento de login sospechoso
     */
    public function logSuspiciousLogin($email, $reason) {
        $this->logSecurityEvent(
            null,
            'suspicious_login',
            "Intento de login sospechoso para email: {$email}. Razón: {$reason}",
            'high'
        );
    }
    
    /**
     * Registrar acceso no autorizado
     */
    public function logUnauthorizedAccess($userId, $attemptedResource) {
        $this->logSecurityEvent(
            $userId,
            'unauthorized_access',
            "Intento de acceso no autorizado al recurso: {$attemptedResource}",
            'high'
        );
    }
    
    /**
     * Registrar cambio de contraseña
     */
    public function logPasswordChange($userId, $method = 'user_initiated') {
        $this->logSecurityEvent(
            $userId,
            'password_change',
            "Contraseña cambiada. Método: {$method}",
            'medium'
        );
    }
    
    /**
     * Registrar posible ataque de fuerza bruta
     */
    public function logBruteForceAttempt($email, $attemptCount) {
        $this->logSecurityEvent(
            null,
            'brute_force_attempt',
            "Posible ataque de fuerza bruta detectado para email: {$email}. Intentos: {$attemptCount}",
            'critical'
        );
    }
    
    /**
     * Registrar session hijacking
     */
    public function logSessionHijacking($userId, $reason) {
        $this->logSecurityEvent(
            $userId,
            'session_hijacking',
            "Posible session hijacking detectado. Razón: {$reason}",
            'critical'
        );
    }
    
    /**
     * Obtener eventos de seguridad recientes
     */
    public function getRecentSecurityEvents($limit = 100, $severity = null) {
        try {
            $whereClause = '';
            $params = [];
            
            if ($severity) {
                $whereClause = 'WHERE severity = ?';
                $params[] = $severity;
            }
            
            $stmt = $this->db->prepare("
                SELECT sl.*, u.email, u.first_name, u.last_name 
                FROM security_logs sl 
                LEFT JOIN users u ON sl.user_id = u.id 
                {$whereClause}
                ORDER BY sl.created_at DESC 
                LIMIT ?
            ");
            
            $params[] = $limit;
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            error_log("Error obteniendo eventos de seguridad: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de seguridad
     */
    public function getSecurityStats($days = 7) {
        try {
            $stats = [];
            
            // Total de eventos por severidad
            $stmt = $this->db->prepare("
                SELECT severity, COUNT(*) as count 
                FROM security_logs 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY severity
            ");
            $stmt->execute([$days]);
            $stats['by_severity'] = $stmt->fetchAll();
            
            // Eventos por tipo
            $stmt = $this->db->prepare("
                SELECT event_type, COUNT(*) as count 
                FROM security_logs 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY event_type 
                ORDER BY count DESC
            ");
            $stmt->execute([$days]);
            $stats['by_type'] = $stmt->fetchAll();
            
            // IPs más activas
            $stmt = $this->db->prepare("
                SELECT ip_address, COUNT(*) as count 
                FROM security_logs 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY ip_address 
                ORDER BY count DESC 
                LIMIT 10
            ");
            $stmt->execute([$days]);
            $stats['top_ips'] = $stmt->fetchAll();
            
            return $stats;
        } catch (\Exception $e) {
            error_log("Error obteniendo estadísticas de seguridad: " . $e->getMessage());
            return [];
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
}
?>
