<?php
namespace Models;

class SyncClass {
    private $conn;
    private $table_name = "sync_classes";
    
    public $id;
    public $title;
    public $description;
    public $price;
    public $meeting_link;
    public $whatsapp_group_link;
    public $start_date;
    public $end_date;
    public $status;
    public $is_active;
    public $created_at;
    public $updated_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Crear clase sincrónica
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                SET title = :title,
                    description = :description,
                    price = :price,
                    meeting_link = :meeting_link,
                    whatsapp_group_link = :whatsapp_group_link,
                    start_date = :start_date,
                    end_date = :end_date,
                    status = :status,
                    is_active = :is_active";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':price', $this->price);
        $stmt->bindParam(':meeting_link', $this->meeting_link);
        $stmt->bindParam(':whatsapp_group_link', $this->whatsapp_group_link);
        $stmt->bindParam(':start_date', $this->start_date);
        $stmt->bindParam(':end_date', $this->end_date);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':is_active', $this->is_active);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }
    
    // Leer todas las clases
    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY start_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Leer clases activas (no finalizadas)
    public function readActive() {
        $query = "SELECT * FROM " . $this->table_name . " 
                WHERE is_active = 1 AND status != 'finished'
                ORDER BY start_date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Leer una clase
    public function readOne($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    // Actualizar clase
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                SET title = :title,
                    description = :description,
                    price = :price,
                    meeting_link = :meeting_link,
                    whatsapp_group_link = :whatsapp_group_link,
                    start_date = :start_date,
                    end_date = :end_date,
                    status = :status,
                    is_active = :is_active
                WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':title', $this->title);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':price', $this->price);
        $stmt->bindParam(':meeting_link', $this->meeting_link);
        $stmt->bindParam(':whatsapp_group_link', $this->whatsapp_group_link);
        $stmt->bindParam(':start_date', $this->start_date);
        $stmt->bindParam(':end_date', $this->end_date);
        $stmt->bindParam(':status', $this->status);
        $stmt->bindParam(':is_active', $this->is_active);
        
        return $stmt->execute();
    }
    
    // Eliminar clase
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    // Buscar clase por ID (para PaymentController)
    public function findById($id) {
        return $this->readOne($id);
    }
}
?>
