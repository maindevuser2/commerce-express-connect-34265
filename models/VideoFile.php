<?php
namespace Models;
class VideoFile {
    private $conn;
    private $table_name = "video_files";

    public $id;
    public $video_id;
    public $filename;
    public $original_name;
    public $file_path;
    public $file_size;
    public $file_type;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
        $this->createTableIfNotExists();
    }

    public function create() {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     SET video_id=:video_id, filename=:filename, original_name=:original_name, 
                         file_path=:file_path, file_size=:file_size, file_type=:file_type";

            $stmt = $this->conn->prepare($query);

            // Sanitizar datos
            $this->video_id = htmlspecialchars(strip_tags($this->video_id));
            $this->filename = htmlspecialchars(strip_tags($this->filename));
            $this->original_name = htmlspecialchars(strip_tags($this->original_name));
            $this->file_path = htmlspecialchars(strip_tags($this->file_path));
            $this->file_size = intval($this->file_size);
            $this->file_type = htmlspecialchars(strip_tags($this->file_type));

            // Bind valores
            $stmt->bindParam(":video_id", $this->video_id);
            $stmt->bindParam(":filename", $this->filename);
            $stmt->bindParam(":original_name", $this->original_name);
            $stmt->bindParam(":file_path", $this->file_path);
            $stmt->bindParam(":file_size", $this->file_size);
            $stmt->bindParam(":file_type", $this->file_type);

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                error_log("VideoFile creado exitosamente con ID: " . $this->id);
                return true;
            } else {
                error_log("Error en VideoFile::create - execute falló");
                error_log("Error info: " . print_r($stmt->errorInfo(), true));
                return false;
            }
        } catch (\PDOException $e) {
            error_log("Error en VideoFile::create: " . $e->getMessage());
            return false;
        }
    }

    public function readByVideo($video_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE video_id = :video_id 
                     ORDER BY created_at DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":video_id", $video_id);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error en VideoFile::readByVideo: " . $e->getMessage());
            return [];
        }
    }

    public function readOne($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->execute();

            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($row) {
                $this->id = $row['id'];
                $this->video_id = $row['video_id'];
                $this->filename = $row['filename'];
                $this->original_name = $row['original_name'];
                $this->file_path = $row['file_path'];
                $this->file_size = $row['file_size'];
                $this->file_type = $row['file_type'];
                $this->created_at = $row['created_at'];
                
                return $row;
            }
            
            return false;
        } catch (\PDOException $e) {
            error_log("Error en VideoFile::readOne: " . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);

            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Error en VideoFile::delete: " . $e->getMessage());
            return false;
        }
    }

    public function deleteByVideo($video_id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE video_id = :video_id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":video_id", $video_id);

            return $stmt->execute();
        } catch (\PDOException $e) {
            error_log("Error en VideoFile::deleteByVideo: " . $e->getMessage());
            return false;
        }
    }

    public function getFileExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    public function getFileIcon($file_type) {
        $icons = [
            'application/pdf' => 'fas fa-file-pdf',
            'image/jpeg' => 'fas fa-file-image',
            'image/jpg' => 'fas fa-file-image',
            'image/png' => 'fas fa-file-image',
            'image/gif' => 'fas fa-file-image',
            'application/msword' => 'fas fa-file-word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'fas fa-file-word',
            'application/vnd.ms-excel' => 'fas fa-file-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'fas fa-file-excel',
            'application/vnd.ms-powerpoint' => 'fas fa-file-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'fas fa-file-powerpoint',
            'text/plain' => 'fas fa-file-alt',
            'application/zip' => 'fas fa-file-archive',
            'application/x-rar-compressed' => 'fas fa-file-archive',
            'pdf' => 'fas fa-file-pdf',
            'doc' => 'fas fa-file-word',
            'docx' => 'fas fa-file-word',
            'xls' => 'fas fa-file-excel',
            'xlsx' => 'fas fa-file-excel',
            'ppt' => 'fas fa-file-powerpoint',
            'pptx' => 'fas fa-file-powerpoint',
            'jpg' => 'fas fa-file-image',
            'jpeg' => 'fas fa-file-image',
            'png' => 'fas fa-file-image',
            'gif' => 'fas fa-file-image',
            'zip' => 'fas fa-file-archive',
            'rar' => 'fas fa-file-archive',
            'txt' => 'fas fa-file-alt',
        ];

        return $icons[$file_type] ?? 'fas fa-file';
    }

    public function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    private function createTableIfNotExists() {
        try {
            $query = "CREATE TABLE IF NOT EXISTS " . $this->table_name . " (
                id INT(11) NOT NULL AUTO_INCREMENT,
                video_id INT(11) NOT NULL,
                filename VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                file_size INT(11) NOT NULL,
                file_type VARCHAR(100) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_video_id (video_id),
                INDEX idx_file_type (file_type),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute();
            
            if ($result) {
                error_log("Tabla video_files verificada/creada exitosamente");
            } else {
                error_log("Error al crear tabla video_files: " . print_r($stmt->errorInfo(), true));
            }
            
            return $result;
        } catch (\PDOException $e) {
            error_log("Error al crear la tabla video_files: " . $e->getMessage());
            return false;
        }
    }
}
?>
