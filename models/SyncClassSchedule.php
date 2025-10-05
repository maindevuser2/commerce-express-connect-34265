<?php
namespace Models;

class SyncClassSchedule {
    private $conn;
    private $table_name = "sync_class_schedules";
    
    public $id;
    public $sync_class_id;
    public $day_of_week;
    public $start_time;
    public $end_time;
    public $created_at;
    public $updated_at;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Crear horario
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                SET sync_class_id = :sync_class_id,
                    day_of_week = :day_of_week,
                    start_time = :start_time,
                    end_time = :end_time";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':sync_class_id', $this->sync_class_id);
        $stmt->bindParam(':day_of_week', $this->day_of_week);
        $stmt->bindParam(':start_time', $this->start_time);
        $stmt->bindParam(':end_time', $this->end_time);
        
        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }
    
    // Leer horarios por clase
    public function readBySyncClass($sync_class_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                WHERE sync_class_id = :sync_class_id 
                ORDER BY day_of_week ASC, start_time ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':sync_class_id', $sync_class_id);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    // Eliminar por clase
    public function deleteBySyncClass($sync_class_id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE sync_class_id = :sync_class_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':sync_class_id', $sync_class_id);
        return $stmt->execute();
    }
    
    // Eliminar un horario específico
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    // Obtener nombre del día
    public static function getDayName($day_of_week) {
        $days = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado'
        ];
        return $days[$day_of_week] ?? 'Desconocido';
    }
}
?>
