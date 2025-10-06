<?php
namespace Models;

class AdminContactInfo {
    private $conn;
    private $table_name = "admin_contact_info";

    public $id;
    public $phone;
    public $instagram_url;
    public $facebook_url;
    public $youtube_url;
    public $whatsapp_number;
    public $whatsapp_channel;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() {
        $query = "CREATE TABLE IF NOT EXISTS " . $this->table_name . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL DEFAULT '573123456789',
            instagram_url VARCHAR(255) DEFAULT 'https://instagram.com/profehernan',
            facebook_url VARCHAR(255) DEFAULT 'https://facebook.com/profehernan',
            youtube_url VARCHAR(255) DEFAULT 'https://youtube.com/@profehernan',
            whatsapp_number VARCHAR(20) NOT NULL DEFAULT '573123456789',
            whatsapp_channel VARCHAR(255) DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->conn->exec($query);
            
            // Verificar si ya existe un registro
            $checkQuery = "SELECT COUNT(*) as count FROM " . $this->table_name;
            $stmt = $this->conn->prepare($checkQuery);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Si no existe ningún registro, crear uno por defecto
            if ($result['count'] == 0) {
                $insertQuery = "INSERT INTO " . $this->table_name . " 
                    (phone, instagram_url, facebook_url, youtube_url, whatsapp_number, whatsapp_channel) 
                    VALUES 
                    ('573123456789', 'https://instagram.com/profehernan', 'https://facebook.com/profehernan', 
                     'https://youtube.com/@profehernan', '573123456789', NULL)";
                $this->conn->exec($insertQuery);
            }
            
            // Agregar columna whatsapp_channel si no existe
            try {
                $alterQuery = "ALTER TABLE " . $this->table_name . " ADD COLUMN IF NOT EXISTS whatsapp_channel VARCHAR(255) DEFAULT NULL";
                $this->conn->exec($alterQuery);
            } catch (\PDOException $e) {
                // Columna ya existe, ignorar error
            }
        } catch (\PDOException $e) {
            error_log("Error creando tabla admin_contact_info: " . $e->getMessage());
        }
    }

    // Obtener la información de contacto (siempre habrá solo un registro)
    public function get() {
        $query = "SELECT * FROM " . $this->table_name . " LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($row) {
            $this->id = $row['id'];
            $this->phone = $row['phone'];
            $this->instagram_url = $row['instagram_url'];
            $this->facebook_url = $row['facebook_url'];
            $this->youtube_url = $row['youtube_url'];
            $this->whatsapp_number = $row['whatsapp_number'];
            $this->whatsapp_channel = $row['whatsapp_channel'] ?? null;
            $this->updated_at = $row['updated_at'];
            
            return $row;
        }
        
        return null;
    }

    // Actualizar la información de contacto
    public function update($data) {
        $query = "UPDATE " . $this->table_name . " 
                  SET phone = :phone,
                      instagram_url = :instagram_url,
                      facebook_url = :facebook_url,
                      youtube_url = :youtube_url,
                      whatsapp_number = :whatsapp_number,
                      whatsapp_channel = :whatsapp_channel
                  WHERE id = 1";

        $stmt = $this->conn->prepare($query);

        // Sanitizar datos
        $phone = htmlspecialchars(strip_tags($data['phone']));
        $instagram_url = htmlspecialchars(strip_tags($data['instagram_url']));
        $facebook_url = htmlspecialchars(strip_tags($data['facebook_url']));
        $youtube_url = htmlspecialchars(strip_tags($data['youtube_url']));
        $whatsapp_number = htmlspecialchars(strip_tags($data['whatsapp_number']));
        $whatsapp_channel = !empty($data['whatsapp_channel']) ? htmlspecialchars(strip_tags($data['whatsapp_channel'])) : null;

        // Bind de valores
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':instagram_url', $instagram_url);
        $stmt->bindParam(':facebook_url', $facebook_url);
        $stmt->bindParam(':youtube_url', $youtube_url);
        $stmt->bindParam(':whatsapp_number', $whatsapp_number);
        $stmt->bindParam(':whatsapp_channel', $whatsapp_channel);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }
}
