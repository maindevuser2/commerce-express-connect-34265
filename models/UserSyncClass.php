<?php
namespace Models;

class UserSyncClass {
    private $conn;
    private $table_name = "user_sync_classes";
    
    public $id;
    public $user_id;
    public $sync_class_id;
    public $order_id;
    public $access_granted_at;
    public $is_active;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Crear acceso a clase sincrónica
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                SET user_id = :user_id,
                    sync_class_id = :sync_class_id,
                    order_id = :order_id,
                    is_active = :is_active";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':sync_class_id', $this->sync_class_id);
        $stmt->bindParam(':order_id', $this->order_id);
        $stmt->bindParam(':is_active', $this->is_active);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }
    
    // Verificar si el usuario tiene acceso
    public function hasAccess($user_id, $sync_class_id) {
        $query = "SELECT id FROM " . $this->table_name . " 
                WHERE user_id = :user_id 
                AND sync_class_id = :sync_class_id 
                AND is_active = 1 
                LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':sync_class_id', $sync_class_id);
        $stmt->execute();
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) !== false;
    }
    
    // Otorgar acceso a una clase sincrónica
    public function grantAccess($user_id, $sync_class_id, $order_id) {
        $this->user_id = $user_id;
        $this->sync_class_id = $sync_class_id;
        $this->order_id = $order_id;
        $this->is_active = 1;
        
        return $this->create();
    }
    
    // Obtener clases sincrónicas por order_id
    public function getSyncClassesByOrderId($order_id) {
        $query = "SELECT sc.*, usc.access_granted_at, usc.order_id
                FROM " . $this->table_name . " usc
                INNER JOIN sync_classes sc ON usc.sync_class_id = sc.id
                WHERE usc.order_id = :order_id 
                AND usc.is_active = 1
                ORDER BY sc.start_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Obtener clases del usuario
    public function readByUserId($user_id) {
        $query = "SELECT sc.*, usc.access_granted_at, usc.order_id
                FROM " . $this->table_name . " usc
                INNER JOIN sync_classes sc ON usc.sync_class_id = sc.id
                WHERE usc.user_id = :user_id 
                AND usc.is_active = 1
                ORDER BY sc.start_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
?>
