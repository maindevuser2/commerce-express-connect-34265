<?php
namespace Models;
require_once __DIR__ . '/../config/Database.php';

use PDO;
use Exception;

class Playlist {
    private $conn;
    private $table_name = "playlists";
    
    public $id;
    public $title;
    public $description;
    public $thumbnail;
    public $price;
    public $level;
    public $created_at;
    public $updated_at;
    
    public function __construct($db = null) {
        if ($db === null) {
            $database = new \Database();
            $this->conn = $database->getConnection();
        } else {
            $this->conn = $db;
        }
    }
    
    public function create() {
        try {
            error_log("Playlist::create - Iniciando creación");
            error_log("Datos: title=" . $this->title . ", description=" . $this->description . ", price=" . $this->price);
            
            $query = "INSERT INTO " . $this->table_name . " 
                      (title, description, thumbnail, price, level) 
                      VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("Error preparando query: " . print_r($this->conn->errorInfo(), true));
                return false;
            }
            
            // Sanitizar datos
            $title = htmlspecialchars(strip_tags($this->title ?? ''));
            $description = htmlspecialchars(strip_tags($this->description ?? ''));
            $thumbnail = htmlspecialchars(strip_tags($this->thumbnail ?? ''));
            $price = floatval($this->price ?? 0);
            $level = htmlspecialchars(strip_tags($this->level ?? 'A1'));
            
            $result = $stmt->execute([$title, $description, $thumbnail, $price, $level]);
            
            if ($result) {
                $this->id = $this->conn->lastInsertId();
                error_log("Playlist::create - Éxito! ID: " . $this->id);
                return true;
            } else {
                error_log("Error ejecutando query: " . print_r($stmt->errorInfo(), true));
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Playlist::create - Excepción: " . $e->getMessage());
            return false;
        }
    }
    
    public function update() {
        try {
            error_log("Playlist::update - Actualizando ID: " . $this->id);
            
            $query = "UPDATE " . $this->table_name . " 
                      SET title=?, description=?, thumbnail=?, price=?, level=? 
                      WHERE id=?";
            
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("Error preparando query update: " . print_r($this->conn->errorInfo(), true));
                return false;
            }
            
            // Sanitizar datos
            $title = htmlspecialchars(strip_tags($this->title ?? ''));
            $description = htmlspecialchars(strip_tags($this->description ?? ''));
            $thumbnail = htmlspecialchars(strip_tags($this->thumbnail ?? ''));
            $price = floatval($this->price ?? 0);
            $level = htmlspecialchars(strip_tags($this->level ?? 'A1'));
            $id = intval($this->id);
            
            $result = $stmt->execute([$title, $description, $thumbnail, $price, $level, $id]);
            
            if ($result) {
                error_log("Playlist::update - Éxito actualizando ID: " . $id);
                return true;
            } else {
                error_log("Error ejecutando update: " . print_r($stmt->errorInfo(), true));
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Playlist::update - Excepción: " . $e->getMessage());
            return false;
        }
    }
    
    public function readAll() {
        try {
            $query = "SELECT id, title, description, thumbnail, price, level, created_at, updated_at 
                      FROM " . $this->table_name . " 
                      ORDER BY created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Playlist::readAll - Encontrados " . count($results) . " registros");
            
            return array_map(function($row) {
                return [
                    'id' => intval($row['id']),
                    'name' => $row['title'] ?? $row['name'] ?? 'Curso sin título', // Compatibilidad name/title
                    'title' => $row['title'] ?? $row['name'] ?? 'Curso sin título', // Mantener ambos campos
                    'description' => $row['description'] ?? 'Sin descripción',
                    'cover_image' => $row['thumbnail'] ?? '', // Mapear thumbnail a cover_image
                    'thumbnail' => $row['thumbnail'] ?? '', // Mantener thumbnail también
                    'price' => floatval($row['price'] ?? 0),
                    'level' => $row['level'] ?? 'A1',
                    'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s')
                ];
            }, $results);
            
        } catch (Exception $e) {
            error_log("Playlist::readAll - Error: " . $e->getMessage());
            return [];
        }
    }
    
public function readOne($id) {
    try {
        $query = "SELECT id, title, description, thumbnail, price, level, created_at, updated_at 
                  FROM " . $this->table_name . " 
                  WHERE id = ? LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([intval($id)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Playlist::readOne - Error: " . $e->getMessage());
        return false;
    }
}

    
    public function findById($id) {
        try {
            $query = "SELECT id, title, description, thumbnail, price, level, created_at, updated_at 
                      FROM " . $this->table_name . " 
                      WHERE id = ? LIMIT 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([intval($id)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($row) {
                return [
                    'id' => intval($row['id']),
                    'name' => $row['title'] ?? $row['name'] ?? 'Curso sin título',
                    'title' => $row['title'] ?? $row['name'] ?? 'Curso sin título',
                    'description' => $row['description'] ?? 'Sin descripción',
                    'cover_image' => $row['thumbnail'] ?? '',
                    'thumbnail' => $row['thumbnail'] ?? '',
                    'price' => floatval($row['price'] ?? 0),
                    'level' => $row['level'] ?? 'A1',
                    'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    'updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Playlist::findById - Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete() {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([intval($this->id)]);
            
            if ($result) {
                error_log("Playlist::delete - Éxito eliminando ID: " . $this->id);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Playlist::delete - Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
