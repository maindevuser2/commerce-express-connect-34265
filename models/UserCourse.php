<?php
namespace Models;
require_once __DIR__ . '/../config/Database.php';

use PDO;
use Exception;

class UserCourse {
   private $conn;
   private $table_name = "user_courses";
   
   public $id;
   public $user_id;
   public $playlist_id;
   public $order_id;
   public $access_granted_at;
   public $created_at;
   
   public function __construct($db = null) {
       if ($db === null) {
           $database = new \Database();
           $this->conn = $database->getConnection();
       } else {
           $this->conn = $db;
       }
       $this->createTableIfNotExists();
   }
   
   // Otorgar acceso a un curso
public function grantAccess($user_id, $playlist_id, $order_id = null) {
    try {
        // Validar parámetros
        if (empty($user_id) || $user_id <= 0) {
            error_log("Error en UserCourse::grantAccess - user_id inválido: " . $user_id);
            return false;
        }
        if (empty($playlist_id) || $playlist_id <= 0) {
            error_log("Error en UserCourse::grantAccess - playlist_id inválido: " . $playlist_id);
            return false;
        }

        error_log("UserCourse::grantAccess - Otorgando acceso:");
        error_log("  user_id: $user_id");
        error_log("  playlist_id: $playlist_id");
        error_log("  order_id: " . ($order_id ?? 'NULL'));

        // Si ya tiene acceso, no duplicar
        if ($this->hasAccess($user_id, $playlist_id)) {
            error_log("UserCourse::grantAccess - Usuario $user_id ya tiene acceso al curso $playlist_id. No se duplicará.");
            return true;
        }

        // Insert solo en las columnas existentes, sin created_at
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, playlist_id, order_id, access_granted_at) 
                  VALUES (:user_id, :playlist_id, :order_id, NOW())";

        $stmt = $this->conn->prepare($query);
        if (!$stmt) {
            error_log("UserCourse::grantAccess - Error preparando consulta: " . implode(" - ", $this->conn->errorInfo()));
            return false;
        }
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':playlist_id', $playlist_id, PDO::PARAM_INT);
        if ($order_id !== null) {
            $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':order_id', null, PDO::PARAM_NULL);
        }

        if ($stmt->execute()) {
            error_log("UserCourse::grantAccess - Acceso otorgado exitosamente para User: $user_id, Playlist: $playlist_id");
            return true;
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("UserCourse::grantAccess - Error SQL: " . implode(" - ", $errorInfo));
            return false;
        }
    } catch (Exception $e) {
        error_log("UserCourse::grantAccess - Excepción: " . $e->getMessage());
        return false;
    }
}

   
   // Verificar si un usuario tiene acceso a un curso
   public function hasAccess($user_id, $playlist_id) {
       try {
           $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                     WHERE user_id = :user_id AND playlist_id = :playlist_id";
           
           $stmt = $this->conn->prepare($query);
           $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
           $stmt->bindParam(':playlist_id', $playlist_id, PDO::PARAM_INT);
           $stmt->execute();
           
           $result = $stmt->fetch(PDO::FETCH_ASSOC);
           error_log("UserCourse::hasAccess - User: $user_id, Playlist: $playlist_id, Has Access: " . ($result['count'] > 0 ? 'Yes' : 'No'));
           return $result['count'] > 0;
       } catch (Exception $e) {
           error_log("Error en UserCourse::hasAccess: " . $e->getMessage());
           return false;
       }
   }
   
   // Obtener cursos de un usuario
public function readByUserId($userId) {
    $query = "
        SELECT 
            uc.*, 
            p.title AS name, 
            p.description, 
            p.thumbnail AS cover_image, 
            p.level, 
            p.price,
            o.amount AS paid_amount
        FROM user_courses uc
        INNER JOIN playlists p ON uc.playlist_id = p.id
        INNER JOIN orders o ON uc.order_id = o.id
        WHERE uc.user_id = :user_id
        ORDER BY uc.access_granted_at DESC
    ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
   
   // Obtener usuarios de un curso
   public function readByPlaylistId($playlist_id) {
       try {
           $query = "SELECT uc.*, u.first_name, u.last_name, u.email
                     FROM " . $this->table_name . " uc
                     INNER JOIN users u ON uc.user_id = u.id
                     WHERE uc.playlist_id = :playlist_id
                     ORDER BY uc.created_at DESC";
           
           $stmt = $this->conn->prepare($query);
           $stmt->bindParam(':playlist_id', $playlist_id, PDO::PARAM_INT);
           $stmt->execute();
           
           return $stmt->fetchAll(PDO::FETCH_ASSOC);
       } catch (Exception $e) {
           error_log("Error en UserCourse::readByPlaylistId: " . $e->getMessage());
           return [];
       }
   }
   
   // Revocar acceso a un curso
   public function revokeAccess($user_id, $playlist_id) {
       try {
           $query = "DELETE FROM " . $this->table_name . " 
                     WHERE user_id = :user_id AND playlist_id = :playlist_id";
           
           $stmt = $this->conn->prepare($query);
           $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
           $stmt->bindParam(':playlist_id', $playlist_id, PDO::PARAM_INT);
           
           return $stmt->execute();
       } catch (Exception $e) {
           error_log("Error en UserCourse::revokeAccess: " . $e->getMessage());
           return false;
       }
   }
   
   // Obtener estadísticas de inscripciones
   public function getStats($user_id = null) {
       try {
           $stats = [];
           
           // Total de inscripciones
           $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
           if ($user_id) {
               $query .= " WHERE user_id = :user_id";
           }
           $stmt = $this->conn->prepare($query);
           if ($user_id) {
               $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
           }
           $stmt->execute();
           $stats['total_enrollments'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
           
           // Inscripciones este mes
           $query = "SELECT COUNT(*) as monthly FROM " . $this->table_name . " 
                     WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
                     AND YEAR(created_at) = YEAR(CURRENT_DATE())";
           if ($user_id) {
               $query .= " AND user_id = :user_id";
           }
           $stmt = $this->conn->prepare($query);
           if ($user_id) {
               $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
           }
           $stmt->execute();
           $stats['monthly_enrollments'] = $stmt->fetch(PDO::FETCH_ASSOC)['monthly'];
           
           // Curso más popular (global o del usuario)
           $query = "SELECT p.title as name, COUNT(uc.playlist_id) as enrollments
                     FROM " . $this->table_name . " uc
                     INNER JOIN playlists p ON uc.playlist_id = p.id";
           if ($user_id) {
               $query .= " WHERE uc.user_id = :user_id";
           }
           $query .= " GROUP BY uc.playlist_id, p.title
                     ORDER BY enrollments DESC
                     LIMIT 1";
           $stmt = $this->conn->prepare($query);
           if ($user_id) {
               $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
           }
           $stmt->execute();
           $popular = $stmt->fetch(PDO::FETCH_ASSOC);
           $stats['most_popular_course'] = $popular ?: ['name' => 'N/A', 'enrollments' => 0];

           // Total gastado por el usuario (si aplica)
           if ($user_id) {
               $query = "SELECT SUM(o.amount) as total_spent, MIN(o.created_at) as first_purchase
                         FROM orders o
                         WHERE o.user_id = :user_id AND o.status = 'completed'";
               $stmt = $this->conn->prepare($query);
               $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
               $stmt->execute();
               $result = $stmt->fetch(PDO::FETCH_ASSOC);
               $stats['total_spent'] = $result['total_spent'] ?? 0;
               $stats['first_purchase'] = $result['first_purchase'];
           } else {
               $stats['total_spent'] = 0;
               $stats['first_purchase'] = null;
           }

           // Niveles diferentes (si aplica)
           if ($user_id) {
               $query = "SELECT COUNT(DISTINCT p.level) as different_levels
                         FROM " . $this->table_name . " uc
                         INNER JOIN playlists p ON uc.playlist_id = p.id
                         WHERE uc.user_id = :user_id AND p.level IS NOT NULL AND p.level != ''";
               $stmt = $this->conn->prepare($query);
               $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
               $stmt->execute();
               $stats['different_levels'] = $stmt->fetch(PDO::FETCH_ASSOC)['different_levels'] ?? 0;
           } else {
               $stats['different_levels'] = 0;
           }
           
           return $stats;
       } catch (Exception $e) {
           error_log("Error obteniendo estadísticas de UserCourse: " . $e->getMessage());
           return [
               'total_enrollments' => 0,
               'monthly_enrollments' => 0,
               'most_popular_course' => ['name' => 'N/A', 'enrollments' => 0],
               'total_spent' => 0,
               'first_purchase' => null,
               'different_levels' => 0
           ];
       }
   }
   
   // Crear la tabla si no existe
   private function createTableIfNotExists() {
       try {
           $query = "CREATE TABLE IF NOT EXISTS " . $this->table_name . " (
               id INT(11) NOT NULL AUTO_INCREMENT,
               user_id INT(11) NOT NULL,
               playlist_id INT(11) NOT NULL,
               order_id INT(11) NULL,
               access_granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
               created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
               PRIMARY KEY (id),
               UNIQUE KEY unique_user_playlist (user_id, playlist_id),
               INDEX idx_user_id (user_id),
               INDEX idx_playlist_id (playlist_id),
               INDEX idx_order_id (order_id),
               INDEX idx_created_at (created_at)
           ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
           
           $stmt = $this->conn->prepare($query);
           $stmt->execute();
           
           return true;
       } catch (Exception $e) {
           error_log("Error al crear la tabla user_courses: " . $e->getMessage());
           return false;
       }
   }

   // Registrar los dias desde la primera compra
    public function getUserStats($userId)
    {
        $query = "
            SELECT 
                (SELECT MIN(access_granted_at) 
                FROM user_courses 
                WHERE user_id = :user_id) AS first_purchase,
                SUM(paid_amount) AS total_spent
            FROM user_courses
            WHERE user_id = :user_id
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getFirstAccessDate($userId) {
        $query = "
            SELECT access_granted_at 
            FROM user_courses 
            WHERE user_id = :user_id 
            ORDER BY id ASC 
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['access_granted_at'] ?? null;
    }

    public function getCoursesByOrderId($orderId) {
        $query = "
            SELECT uc.*, p.title AS name, p.thumbnail AS cover_image, p.level, p.price
            FROM user_courses uc
            JOIN playlists p ON uc.playlist_id = p.id
            WHERE uc.order_id = :order_id
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
