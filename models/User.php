<?php
namespace Models;
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

use Helpers\SecurityHelper;
use PDO;

class User {
    private $conn;
    private $table_name = "users";
    
    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $password;
    public $role;
    public $is_active;
    public $email_verified;
    public $google_id;
    public $created_at;
    public $updated_at;
    public $last_login;
    
    public function __construct($db = null) {
        if ($db === null) {
            $database = new \Database();
            $this->conn = $database->getConnection();
        } else {
            $this->conn = $db;
        }
        $this->createTableIfNotExists();
    }

    // Crear la tabla si no existe
    private function createTableIfNotExists() {
        try {
            $query = "CREATE TABLE IF NOT EXISTS " . $this->table_name . " (
                id INT(11) NOT NULL AUTO_INCREMENT,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                role ENUM('user', 'admin') DEFAULT 'user',
                is_active TINYINT(1) DEFAULT 1,
                email_verified TINYINT(1) DEFAULT 0,
                google_id VARCHAR(255) NULL,
                last_login DATETIME NULL,
                failed_login_attempts INT DEFAULT 0,
                locked_until DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_email (email),
                INDEX idx_role (role),
                INDEX idx_is_active (is_active),
                INDEX idx_email_verified (email_verified),
                INDEX idx_locked_until (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return true;
        } catch (\Exception $e) {
            error_log("Error al crear la tabla users: " . $e->getMessage());
            return false;
        }
    }
    
    // Crear usuario - SEGURO
    public function create() {
        try {
            // Validar datos antes de insertar
            if (!$this->validateUserData()) {
                return false;
            }

            $query = "INSERT INTO " . $this->table_name . " 
                      (first_name, last_name, email, password, role, is_active, email_verified, created_at) 
                      VALUES (?, ?, ?, ?, ?, 1, 0, NOW())";
        
            $stmt = $this->conn->prepare($query);
            
            // Sanitizar datos
            $first_name = SecurityHelper::sanitizeInput($this->first_name);
            $last_name = SecurityHelper::sanitizeInput($this->last_name);
            $email = SecurityHelper::sanitizeInput($this->email);
            $password = $this->password; // Ya viene hasheada
            $role = $this->role ?? 'user';
            
            // Validar que el email no exista
            if ($this->emailExists($email)) {
                error_log("User::create - Email ya existe: $email");
                return false;
            }
            
            // Log para debugging
            error_log("User::create - Intentando insertar: $first_name, $last_name, $email, role: $role");
            
            $result = $stmt->execute([$first_name, $last_name, $email, $password, $role]);
            
            if ($result) {
                $this->id = $this->conn->lastInsertId();
                error_log("User::create - Usuario creado exitosamente con ID: " . $this->id);
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("User::create - Error en execute(): " . print_r($errorInfo, true));
                return false;
            }
            
        } catch (\Exception $e) {
            error_log("User::create - Excepción: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validar datos del usuario
     */
    private function validateUserData() {
        if (empty($this->first_name) || empty($this->last_name) || empty($this->email)) {
            error_log("User::validateUserData - Campos requeridos vacíos");
            return false;
        }

        if (!SecurityHelper::validateEmail($this->email)) {
            error_log("User::validateUserData - Email inválido: " . $this->email);
            return false;
        }

        if (strlen($this->first_name) > 100 || strlen($this->last_name) > 100) {
            error_log("User::validateUserData - Nombres demasiado largos");
            return false;
        }

        return true;
    }
    
    // Leer todos los usuarios con paginación
    public function readAll($page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            
            $query = "SELECT id, first_name, last_name, email, role, is_active, email_verified, 
                             created_at, last_login, failed_login_attempts, locked_until
                      FROM " . $this->table_name . " 
                      ORDER BY created_at DESC 
                      LIMIT ? OFFSET ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error en User::readAll: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Contar total de usuarios
     */
    public function countAll() {
        try {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['total'];
        } catch (\Exception $e) {
            error_log("Error en User::countAll: " . $e->getMessage());
            return 0;
        }
    }
    
    // Buscar usuario por email
    public function findByEmail($email) {
        try {
            $email = SecurityHelper::sanitizeInput($email);
            
            $query = "SELECT * FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
        } catch (\Exception $e) {
            error_log("Error en User::findByEmail: " . $e->getMessage());
            return false;
        }
    }
    
    // Verificar si el email existe
    public function emailExists($email) {
        try {
            $email = SecurityHelper::sanitizeInput($email);
            
            $query = "SELECT id FROM " . $this->table_name . " WHERE email = ? LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$email]);
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("Error en User::emailExists: " . $e->getMessage());
            return false;
        }
    }
    
    // Verificar contraseña
    public function verifyPassword($password) {
        return SecurityHelper::verifyPassword($password, $this->password);
    }
    
    // Hash de contraseña
    public function hashPassword($password) {
        return SecurityHelper::hashPassword($password);
    }

    /**
     * Actualizar contraseña de forma segura
     */
    public function updatePassword($userId, $hashedPassword) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET password = ?, updated_at = NOW(), failed_login_attempts = 0, locked_until = NULL 
                      WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$hashedPassword, $userId]);
        } catch (\Exception $e) {
            error_log("Error en User::updatePassword: " . $e->getMessage());
            return false;
        }
    }
    
    // Actualizar último login
    public function updateLastLogin() {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET last_login = NOW(), failed_login_attempts = 0, locked_until = NULL 
                      WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$this->id]);
        } catch (\Exception $e) {
            error_log("Error en User::updateLastLogin: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Incrementar intentos de login fallidos
     */
    public function incrementFailedAttempts() {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET failed_login_attempts = failed_login_attempts + 1,
                          locked_until = CASE 
                              WHEN failed_login_attempts >= 4 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                              ELSE locked_until 
                          END
                      WHERE email = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$this->email]);
        } catch (\Exception $e) {
            error_log("Error en User::incrementFailedAttempts: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si la cuenta está bloqueada
     */
    public function isLocked() {
        try {
            $query = "SELECT locked_until FROM " . $this->table_name . " WHERE email = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$this->email]);
            $result = $stmt->fetch();
            
            if ($result && $result['locked_until']) {
                return strtotime($result['locked_until']) > time();
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("Error en User::isLocked: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Desbloquear cuenta
     */
    public function unlock() {
        try {
            $query = "UPDATE " . $this->table_name . " 
                      SET failed_login_attempts = 0, locked_until = NULL 
                      WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$this->id]);
        } catch (\Exception $e) {
            error_log("Error en User::unlock: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar email
     */
    public function verifyEmail() {
        try {
            $query = "UPDATE " . $this->table_name . " SET email_verified = 1, updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$this->id]);
        } catch (\Exception $e) {
            error_log("Error en User::verifyEmail: " . $e->getMessage());
            return false;
        }
    }
    
    // Actualizar usuario
    public function update() {
        try {
            // Validar datos
            if (!$this->validateUserData()) {
                return false;
            }

            $query = "UPDATE " . $this->table_name . " 
                      SET first_name=?, last_name=?, email=?, updated_at=NOW()
                      WHERE id=?";
            
            $stmt = $this->conn->prepare($query);
            
            $first_name = SecurityHelper::sanitizeInput($this->first_name);
            $last_name = SecurityHelper::sanitizeInput($this->last_name);
            $email = SecurityHelper::sanitizeInput($this->email);
            
            return $stmt->execute([$first_name, $last_name, $email, $this->id]);
        } catch (\Exception $e) {
            error_log("Error en User::update: " . $e->getMessage());
            return false;
        }
    }

    // Cambiar el estado de is_active
    public function toggleStatus() {
        try {
            $query = "UPDATE " . $this->table_name . " SET is_active = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$this->is_active, $this->id]);
        } catch (\Exception $e) {
            error_log("Error en User::toggleStatus: " . $e->getMessage());
            return false;
        }
    }

    // Desactivar (soft delete) un usuario
    public function deactivate() {
        try {
            $query = "UPDATE " . $this->table_name . " SET is_active = 0, updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$this->id]);
        } catch (\Exception $e) {
            error_log("Error en User::deactivate: " . $e->getMessage());
            return false;
        }
    }
    
    // Obtener estadísticas de usuarios
    public function getStats() {
        try {
            $stats = [];
            
            // Total de usuarios
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE role = 'user'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Usuarios activos
            $query = "SELECT COUNT(*) as active FROM " . $this->table_name . " WHERE role = 'user' AND is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
            
            // Usuarios verificados
            $query = "SELECT COUNT(*) as verified FROM " . $this->table_name . " WHERE role = 'user' AND email_verified = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['verified_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['verified'];
            
            // Nuevos usuarios este mes
            $query = "SELECT COUNT(*) as new_users FROM " . $this->table_name . " 
                      WHERE role = 'user' AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                      AND YEAR(created_at) = YEAR(CURRENT_DATE())";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['new_users_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['new_users'];
            
            // Usuarios bloqueados
            $query = "SELECT COUNT(*) as locked FROM " . $this->table_name . " WHERE locked_until > NOW()";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $stats['locked_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['locked'];
            
            return $stats;
        } catch (\Exception $e) {
            error_log("Error obteniendo estadísticas de User: " . $e->getMessage());
            return [
                'total_users' => 0,
                'active_users' => 0,
                'verified_users' => 0,
                'new_users_month' => 0,
                'locked_users' => 0
            ];
        }
    }
    
    // Obtener nombre completo
    public function getFullName() {
        return trim(SecurityHelper::sanitizeInput($this->first_name . ' ' . $this->last_name));
    }
    
    // Verificar si es administrador
    public function isAdmin() {
        return $this->role === 'admin';
    }

    // Buscar usuario por ID
    public function findById($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Asignar propiedades
                $this->id = $row['id'];
                $this->first_name = $row['first_name'];
                $this->last_name = $row['last_name'];
                $this->email = $row['email'];
                $this->password = $row['password'];
                $this->role = $row['role'];
                $this->is_active = $row['is_active'];
                $this->email_verified = $row['email_verified'];
                $this->google_id = $row['google_id'];
                $this->created_at = $row['created_at'];
                $this->updated_at = $row['updated_at'];
                $this->last_login = $row['last_login'];
                
                return $row;
            }
            return false;
        } catch (\Exception $e) {
            error_log("Error en User::findById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Buscar usuarios con filtros
     */
    public function search($filters = [], $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            $whereConditions = [];
            $params = [];

            // Construir condiciones WHERE
            if (!empty($filters['email'])) {
                $whereConditions[] = "email LIKE ?";
                $params[] = '%' . SecurityHelper::sanitizeInput($filters['email']) . '%';
            }

            if (!empty($filters['name'])) {
                $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ?)";
                $searchTerm = '%' . SecurityHelper::sanitizeInput($filters['name']) . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            if (isset($filters['role']) && !empty($filters['role'])) {
                $whereConditions[] = "role = ?";
                $params[] = $filters['role'];
            }

            if (isset($filters['is_active'])) {
                $whereConditions[] = "is_active = ?";
                $params[] = $filters['is_active'];
            }

            if (isset($filters['email_verified'])) {
                $whereConditions[] = "email_verified = ?";
                $params[] = $filters['email_verified'];
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $query = "SELECT id, first_name, last_name, email, role, is_active, email_verified, 
                             created_at, last_login, failed_login_attempts, locked_until
                      FROM " . $this->table_name . " 
                      {$whereClause}
                      ORDER BY created_at DESC 
                      LIMIT ? OFFSET ?";

            $stmt = $this->conn->prepare($query);
            
            // Agregar parámetros de paginación
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Error en User::search: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Limpiar cuentas no verificadas antiguas
     */
    public function cleanupUnverifiedAccounts($daysOld = 7) {
        try {
            $query = "DELETE FROM " . $this->table_name . " 
                      WHERE email_verified = 0 
                      AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$daysOld]);
            
            $deletedRows = $stmt->rowCount();
            error_log("User::cleanupUnverifiedAccounts - Eliminadas {$deletedRows} cuentas no verificadas");
            
            return $deletedRows;
        } catch (\Exception $e) {
            error_log("Error en User::cleanupUnverifiedAccounts: " . $e->getMessage());
            return false;
        }
    }
}
?>
