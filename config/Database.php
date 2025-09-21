<?php
// Incluir configuración principal
require_once __DIR__ . '/config.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        // Usar variables de entorno si están disponibles, sino valores por defecto
        $this->host = $_ENV['DB_HOST'] ?? "127.0.0.1";
        $this->db_name = $_ENV['DB_NAME'] ?? "ecommerce_cursos";
        $this->username = $_ENV['DB_USER'] ?? "root";
        $this->password = $_ENV['DB_PASSWORD'] ?? "";
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $this->conn = new \PDO($dsn, $this->username, $this->password, $options);
            
            // Log de conexión exitosa solo en desarrollo
            if (function_exists('isDevelopment') && isDevelopment()) {
                error_log("Conexión a base de datos establecida exitosamente");
            }
            
        } catch(\PDOException $exception) {
            error_log("Error de conexión a la base de datos: " . $exception->getMessage());
            
            // En producción, no mostrar detalles del error
            if (function_exists('isProduction') && isProduction()) {
                throw new \Exception("Error de conexión a la base de datos");
            } else {
                throw new \Exception("Error de conexión: " . $exception->getMessage());
            }
        }

        return $this->conn;
    }

    // Método para verificar la conexión
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT 1");
            return true;
        } catch (\Exception $e) {
            error_log("Test de conexión falló: " . $e->getMessage());
            return false;
        }
    }

    // Método para obtener información de la base de datos
    public function getDatabaseInfo() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT DATABASE() as db_name, VERSION() as version");
            return $stmt->fetch();
        } catch (\Exception $e) {
            error_log("Error obteniendo información de la base de datos: " . $e->getMessage());
            return false;
        }
    }

    // Método para verificar si las tablas existen
    public function checkTablesExist() {
        try {
            $conn = $this->getConnection();
            
            $requiredTables = [
                'users', 'playlists', 'videos', 'orders', 'user_courses', 'video_files',
                'login_attempts', 'password_resets', 'password_reset_attempts', 
                'registration_attempts', 'email_logs', 'security_logs'
            ];
            
            $existingTables = [];
            $stmt = $conn->query("SHOW TABLES");
            while ($row = $stmt->fetch()) {
                $existingTables[] = array_values($row)[0];
            }
            
            $missingTables = array_diff($requiredTables, $existingTables);
            
            return [
                'all_exist' => empty($missingTables),
                'existing' => $existingTables,
                'missing' => $missingTables,
                'required' => $requiredTables
            ];
            
        } catch (\Exception $e) {
            error_log("Error verificando tablas: " . $e->getMessage());
            return false;
        }
    }

    // Método para ejecutar el script de estructura de base de datos
    public function executeStructureScript() {
        try {
            $conn = $this->getConnection();
            $scriptPath = __DIR__ . '/../E_database_structure.sql';
            
            if (!file_exists($scriptPath)) {
                throw new \Exception("Archivo de estructura de base de datos no encontrado: $scriptPath");
            }
            
            $sql = file_get_contents($scriptPath);
            if ($sql === false) {
                throw new \Exception("No se pudo leer el archivo de estructura de base de datos");
            }
            
            // Dividir el script en declaraciones individuales
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
                }
            );
            
            $conn->beginTransaction();
            
            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    $conn->exec($statement);
                }
            }
            
            $conn->commit();
            error_log("Script de estructura de base de datos ejecutado exitosamente");
            return true;
            
        } catch (\Exception $e) {
            if (isset($conn) && $conn->inTransaction()) {
                $conn->rollback();
            }
            error_log("Error ejecutando script de estructura: " . $e->getMessage());
            return false;
        }
    }

    // Método para limpiar datos antiguos de seguridad
    public function cleanupSecurityData() {
        try {
            $conn = $this->getConnection();
            
            // Limpiar intentos de login antiguos (más de 24 horas)
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stmt->execute();
            $loginDeleted = $stmt->rowCount();
            
            // Limpiar tokens de reset expirados
            $stmt = $conn->prepare("DELETE FROM password_resets WHERE expires_at < NOW()");
            $stmt->execute();
            $resetDeleted = $stmt->rowCount();
            
            // Limpiar intentos de reset antiguos (más de 24 horas)
            $stmt = $conn->prepare("DELETE FROM password_reset_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stmt->execute();
            $resetAttemptsDeleted = $stmt->rowCount();
            
            // Limpiar intentos de registro antiguos (más de 24 horas)
            $stmt = $conn->prepare("DELETE FROM registration_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stmt->execute();
            $registrationDeleted = $stmt->rowCount();
            
            // Limpiar logs de email antiguos (más de 30 días)
            $stmt = $conn->prepare("DELETE FROM email_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute();
            $emailDeleted = $stmt->rowCount();
            
            // Limpiar logs de seguridad antiguos (más de 90 días)
            $stmt = $conn->prepare("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            $securityDeleted = $stmt->rowCount();
            
            error_log("Limpieza de datos completada - Login: $loginDeleted, Reset: $resetDeleted, ResetAttempts: $resetAttemptsDeleted, Registration: $registrationDeleted, Email: $emailDeleted, Security: $securityDeleted");
            return true;
            
        } catch (\Exception $e) {
            error_log("Error en limpieza de datos de seguridad: " . $e->getMessage());
            return false;
        }
    }

    // Método para obtener estadísticas de la base de datos
    public function getStats() {
        try {
            $conn = $this->getConnection();
            $stats = [];
            
            // Contar usuarios
            $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
            $stats['users'] = $stmt->fetch()['count'];
            
            // Contar playlists activas
            $stmt = $conn->query("SELECT COUNT(*) as count FROM playlists WHERE is_active = 1");
            $stats['active_playlists'] = $stmt->fetch()['count'];
            
            // Contar órdenes
            $stmt = $conn->query("SELECT COUNT(*) as count FROM orders");
            $stats['orders'] = $stmt->fetch()['count'];
            
            // Contar intentos de login recientes (últimas 24 horas)
            $stmt = $conn->query("SELECT COUNT(*) as count FROM login_attempts WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stats['recent_login_attempts'] = $stmt->fetch()['count'];
            
            return $stats;
            
        } catch (\Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cerrar conexión
     */
    public function closeConnection() {
        $this->conn = null;
    }
}

// Función helper para obtener una instancia de la base de datos
function getDatabase() {
    static $database = null;
    if ($database === null) {
        $database = new Database();
    }
    return $database;
}

// Función helper para obtener una conexión PDO
function getConnection() {
    return getDatabase()->getConnection();
}
?>
