<?php
namespace Models;
class Video {
    private $conn;
    private $table = "videos";

    public $id;
    public $title;
    public $description;
    public $file_path;
    public $thumbnail_image;
    public $playlist_id;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
        $this->createTableIfNotExists();
    }

    public function create() {
        try {
            $query = "INSERT INTO {$this->table} (title, description, file_path, thumbnail_image, playlist_id, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([
                $this->title,
                $this->description,
                $this->file_path,
                $this->thumbnail_image,
                $this->playlist_id
            ]);
        
            if ($result) {
                $this->id = $this->conn->lastInsertId();
            }
        
            return $result;
        } catch (\PDOException $e) {
            error_log("Error en Video::create: " . $e->getMessage());
            return false;
        }
    }

    public function readByPlaylist($playlist_id, $exclude_id = null) {
        try {
            if ($exclude_id) {
                $query = "SELECT * FROM {$this->table} WHERE playlist_id = ? AND id != ? ORDER BY created_at ASC";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$playlist_id, $exclude_id]);
            } else {
                $query = "SELECT * FROM {$this->table} WHERE playlist_id = ? ORDER BY created_at ASC";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$playlist_id]);
            }
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error en Video::readByPlaylist: " . $e->getMessage());
            return [];
        }
    }

    public function readOne($id) {
        try {
            $query = "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$id]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error en Video::readOne: " . $e->getMessage());
            return null;
        }
    }

    public function readAll() {
        try {
            $query = "SELECT v.*, p.title as playlist_name 
                      FROM {$this->table} v 
                      LEFT JOIN playlists p ON v.playlist_id = p.id 
                      ORDER BY v.created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error en Video::readAll: " . $e->getMessage());
            return [];
        }
    }

    public function update($id, $data) {
        try {
            $query = "UPDATE {$this->table} 
                    SET title = ?, description = ?, file_path = ?, thumbnail_image = ?, playlist_id = ? 
                    WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $success = $stmt->execute([
                $data['title'],
                $data['description'],
                $data['file_path'],
                $data['thumbnail_image'],
                $data['playlist_id'],
                $id
            ]);

            if (!$success) {
                $errorInfo = $stmt->errorInfo();
                error_log("PDO Error [{$errorInfo[0]}]: {$errorInfo[2]}");
            }

            return $success;
        } catch (\PDOException $e) {
            error_log("Excepción en Video::update: " . $e->getMessage());
            return false;
        }
    }


    public function delete($id) {
        try {
            $query = "DELETE FROM {$this->table} WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            error_log("Error en Video::delete: " . $e->getMessage());
            return false;
        }
    }

    public function deleteByPlaylist($playlist_id) {
        try {
            $query = "DELETE FROM {$this->table} WHERE playlist_id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$playlist_id]);
        } catch (\PDOException $e) {
            error_log("Error en Video::deleteByPlaylist: " . $e->getMessage());
            return false;
        }
    }

    // Obtener estadísticas de videos
    public function getVideoStats($playlist_id = null) {
        try {
            $whereClause = $playlist_id ? "WHERE playlist_id = ?" : "";
            $query = "SELECT 
                        COUNT(*) as total_videos,
                        COUNT(CASE WHEN thumbnail_image IS NOT NULL THEN 1 END) as videos_with_thumbnails
                      FROM {$this->table} {$whereClause}";
            
            $stmt = $this->conn->prepare($query);
            
            if ($playlist_id) {
                $stmt->execute([$playlist_id]);
            } else {
                $stmt->execute();
            }
            
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error en Video::getVideoStats: " . $e->getMessage());
            return ['total_videos' => 0, 'videos_with_thumbnails' => 0];
        }
    }

    // Buscar videos por título
    public function searchByTitle($searchTerm) {
        try {
            $query = "SELECT v.*, p.title as playlist_name 
                      FROM {$this->table} v 
                      LEFT JOIN playlists p ON v.playlist_id = p.id 
                      WHERE v.title LIKE ? OR v.description LIKE ?
                      ORDER BY v.created_at DESC";
            $searchPattern = '%' . $searchTerm . '%';
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$searchPattern, $searchPattern]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error en Video::searchByTitle: " . $e->getMessage());
            return [];
        }
    }

    // Crear tabla si no existe
    private function createTableIfNotExists() {
        try {
            $query = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT(11) NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                file_path VARCHAR(500) NOT NULL,
                thumbnail_image VARCHAR(500),
                playlist_id INT(11) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_playlist_id (playlist_id),
                INDEX idx_title (title),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return true;
        } catch (\PDOException $e) {
            error_log("Error al crear la tabla videos: " . $e->getMessage());
            return false;
        }
    }
}
?>
