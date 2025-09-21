<?php
namespace Models;
require_once __DIR__ . '/../config/Database.php';

use PDO;
use Exception;

class Order {
    private $conn;
    private $table_name = "orders";

    public $id;
    public $user_id;
    public $transaction_id;
    public $amount;
    public $currency;
    public $status;
    public $payment_method; // <--- Asegurarse de que esta propiedad exista
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

    // Crear un nuevo pedido
    public function create() {
        try {
            // Validar datos mínimos
            if (empty($this->user_id) || empty($this->transaction_id) || empty($this->amount) || empty($this->currency) || empty($this->status)) {
                error_log("Order::create - Datos incompletos para crear pedido.");
                return false;
            }

            $query = "INSERT INTO " . $this->table_name . "
                      (user_id, transaction_id, amount, currency, status, payment_method, created_at)
                      VALUES (:user_id, :transaction_id, :amount, :currency, :status, :payment_method, NOW())";

            $stmt = $this->conn->prepare($query);

            // Sanitizar y bindear valores
            $this->user_id = htmlspecialchars(strip_tags($this->user_id));
            $this->transaction_id = htmlspecialchars(strip_tags($this->transaction_id));
            $this->amount = htmlspecialchars(strip_tags($this->amount));
            $this->currency = htmlspecialchars(strip_tags($this->currency));
            $this->status = htmlspecialchars(strip_tags($this->status));
            $this->payment_method = htmlspecialchars(strip_tags($this->payment_method ?? 'unknown')); // Valor por defecto

            $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
            $stmt->bindParam(':transaction_id', $this->transaction_id);
            $stmt->bindParam(':amount', $this->amount);
            $stmt->bindParam(':currency', $this->currency);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':payment_method', $this->payment_method);

            error_log("Order::create - Ejecutando INSERT para pedido. User: {$this->user_id}, TransID: {$this->transaction_id}");

            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                error_log("Order::create - Pedido creado con ID: {$this->id}");
                return true;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("Order::create - Error SQL al crear pedido: " . implode(" - ", $errorInfo));
                return false;
            }
        } catch (Exception $e) {
            error_log("Order::create - Excepción: " . $e->getMessage());
            return false;
        }
    }

    // Leer un pedido por ID
    public function getOrderById($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Order::getOrderById - Error: " . $e->getMessage());
            return null;
        }
    }

    // Leer pedidos por user_id
    public function readByUserId($userId) {
        $sql = "
            SELECT 
                o.id,
                o.user_id,
                o.amount,
                o.status,
                o.created_at,
                COUNT(uc.id) as course_count
            FROM orders o
            LEFT JOIN user_courses uc ON uc.order_id = o.id
            WHERE o.user_id = :user_id
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener ítems de un pedido (cursos)
    public function getOrderItems($order_id) {
        try {
            $query = "SELECT uc.playlist_id as id, p.name, p.description, p.price, p.cover_image, p.level
                      FROM user_courses uc
                      INNER JOIN playlists p ON uc.playlist_id = p.id
                      WHERE uc.order_id = :order_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Order::getOrderItems - Error: " . $e->getMessage());
            return [];
        }
    }

    // Buscar pedido por transaction_id
    public function findByTransactionId($transaction_id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE transaction_id = :transaction_id LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':transaction_id', $transaction_id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Order::findByTransactionId - Error: " . $e->getMessage());
            return null;
        }
    }

    // Crear la tabla si no existe
    private function createTableIfNotExists() {
        try {
            $query = "CREATE TABLE IF NOT EXISTS " . $this->table_name . " (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                transaction_id VARCHAR(255) NOT NULL UNIQUE,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) NOT NULL,
                status VARCHAR(20) NOT NULL,
                payment_method VARCHAR(50) DEFAULT 'unknown', -- <--- Asegurarse de que esta columna exista
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            error_log("Error al crear la tabla orders: " . $e->getMessage());
            return false;
        }
    }

    public function getOrdersByUserId($userId){
        $stmt = $this->conn->prepare("
            SELECT o.id, o.user_id, o.amount, o.status, o.created_at, 
                COUNT(uc.id) AS course_count
            FROM orders o
            LEFT JOIN user_courses uc ON o.id = uc.order_id
            WHERE o.user_id = :user_id
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function readAllWithUserDetails() {
    $query = "
        SELECT o.*, u.first_name, u.last_name 
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
    ";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAll() {
        $query = "SELECT o.id, o.amount, o.status, o.created_at, u.first_name, u.last_name
                FROM orders o
                JOIN users u ON o.user_id = u.id
                ORDER BY o.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
