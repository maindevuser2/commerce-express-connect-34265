<?php
namespace Controllers;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/SyncClass.php';
require_once __DIR__ . '/../models/UserSyncClass.php';
require_once __DIR__ . '/../controllers/AuthController.php';

use Models\SyncClass;
use Models\UserSyncClass;
use Controllers\AuthController;
use PDO;
use Exception;

class SyncClassController {
    private $db;
    private $syncClassModel;
    private $userSyncClassModel;
    
    public function __construct() {
        try {
            $database = new \Database();
            $this->db = $database->getConnection();
            $this->syncClassModel = new SyncClass($this->db);
            $this->userSyncClassModel = new UserSyncClass($this->db);
        } catch (Exception $e) {
            error_log("Error en SyncClassController constructor: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function add($sync_class_id) {
        try {
            if (!$sync_class_id || !is_numeric($sync_class_id)) {
                $this->sendJsonResponse('error', 'ID de clase inválido.');
                return;
            }
            
            $syncClass = $this->syncClassModel->readOne($sync_class_id);
            if (!$syncClass) {
                $this->sendJsonResponse('error', 'Clase sincrónica no encontrada.');
                return;
            }
            
            // Verificar si el usuario ya tiene acceso
            if (AuthController::isAuthenticated()) {
                $currentUser = AuthController::getCurrentUser();
                if ($this->userSyncClassModel->hasAccess($currentUser['id'], $sync_class_id)) {
                    $this->sendJsonResponse('info', 'Ya tienes acceso a esta clase.');
                    return;
                }
            }
            
            // Verificar si ya está en el carrito
            if (isset($_SESSION['cart_sync_classes'][$sync_class_id])) {
                $this->sendJsonResponse('info', 'Esta clase ya está en tu carrito.');
                return;
            }
            
            // Inicializar carrito de clases sincrónicas si no existe
            if (!isset($_SESSION['cart_sync_classes'])) {
                $_SESSION['cart_sync_classes'] = [];
            }
            
            // Agregar al carrito
            $_SESSION['cart_sync_classes'][$sync_class_id] = [
                'id' => $syncClass['id'],
                'title' => $syncClass['title'],
                'price' => floatval($syncClass['price'] ?? 0),
                'description' => $syncClass['description'] ?? '',
                'start_date' => $syncClass['start_date'],
                'end_date' => $syncClass['end_date'],
                'meeting_link' => $syncClass['meeting_link'],
                'whatsapp_group_link' => $syncClass['whatsapp_group_link'] ?? '',
                'quantity' => 1
            ];
            
            $this->sendJsonResponse('success', 'Clase agregada al carrito exitosamente.', [
                'cart_count' => count($_SESSION['cart_sync_classes']),
                'class_title' => $syncClass['title']
            ]);
            
        } catch (Exception $e) {
            error_log("Error agregando clase al carrito: " . $e->getMessage());
            $this->sendJsonResponse('error', 'Error interno del servidor.');
        }
    }
    
    public function remove($sync_class_id) {
        try {
            if (isset($_SESSION['cart_sync_classes'][$sync_class_id])) {
                unset($_SESSION['cart_sync_classes'][$sync_class_id]);
                $this->sendJsonResponse('success', 'Clase eliminada del carrito.', [
                    'cart_count' => count($_SESSION['cart_sync_classes'])
                ]);
            } else {
                $this->sendJsonResponse('error', 'La clase no estaba en el carrito.');
            }
        } catch (Exception $e) {
            error_log("Error removiendo clase del carrito: " . $e->getMessage());
            $this->sendJsonResponse('error', 'Error interno del servidor.');
        }
    }
    
    public function getCartItems() {
        $cart_items = [];
        
        if (!isset($_SESSION['cart_sync_classes']) || empty($_SESSION['cart_sync_classes'])) {
            return $cart_items;
        }
        
        foreach ($_SESSION['cart_sync_classes'] as $key => $item) {
            try {
                // Detectar si $item es un array o un ID simple
                $classId = is_array($item) ? $item['id'] : $item;
                
                $syncClass = $this->syncClassModel->readOne($classId);
                
                if ($syncClass) {
                    $cart_items[] = [
                        'id' => $syncClass['id'],
                        'title' => $syncClass['title'],
                        'price' => floatval($syncClass['price'] ?? 0),
                        'description' => $syncClass['description'] ?? '',
                        'start_date' => $syncClass['start_date'],
                        'end_date' => $syncClass['end_date'],
                        'meeting_link' => $syncClass['meeting_link'],
                        'whatsapp_group_link' => $syncClass['whatsapp_group_link'] ?? '',
                        'quantity' => 1,
                        'type' => 'sync_class'
                    ];
                }
            } catch (Exception $e) {
                error_log("Error obteniendo clase del carrito: " . $e->getMessage());
            }
        }
        
        return $cart_items;
    }
    
    public function calculateTotals($items) {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += floatval($item['price']);
        }
        
        $tax_rate = 0.07; // 7% de impuesto
        
        $tax = $subtotal * $tax_rate;
        $total = $subtotal + $tax;
        
        return [
            'subtotal' => $subtotal,
            'discount' => 0,
            'tax' => $tax,
            'total' => $total,
            'promo_code_applied' => null
        ];
    }
    
    private function sendJsonResponse($status, $message, $data = []) {
        header('Content-Type: application/json');
        $response = [
            'status' => $status,
            'message' => $message
        ];
        
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }
        
        echo json_encode($response);
    }
    
    public static function getCartCount() {
        return isset($_SESSION['cart_sync_classes']) ? count($_SESSION['cart_sync_classes']) : 0;
    }
    
    public static function emptyCart() {
        $_SESSION['cart_sync_classes'] = [];
    }
}

// Manejo de rutas AJAX
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    try {
        $controller = new SyncClassController();
        $action = $_REQUEST['action'] ?? 'view';
        
        switch ($action) {
            case 'add':
                $sync_class_id = intval($_POST['id'] ?? 0);
                $controller->add($sync_class_id);
                break;
                
            case 'remove':
                $sync_class_id = intval($_REQUEST['id'] ?? 0);
                if (isset($_SESSION['cart_sync_classes'][$sync_class_id])) {
                    unset($_SESSION['cart_sync_classes'][$sync_class_id]);
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Clase eliminada del carrito.',
                        'cart_count' => count($_SESSION['cart_sync_classes'])
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'La clase no estaba en el carrito.'
                    ]);
                }
                break;
                
            default:
                header('Location: ../views/client/cart.php');
                break;
        }
    } catch (Exception $e) {
        error_log("Error en SyncClassController: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.']);
    }
    exit();
}
?>
