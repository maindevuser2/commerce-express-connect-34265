<?php
namespace Controllers;

// Asegurar que la sesión esté iniciada
if (session_status() == PHP_SESSION_NONE) {
   session_start();
}

// Incluir las clases necesarias
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../models/Playlist.php';
require_once __DIR__ . '/../models/UserCourse.php';
require_once __DIR__ . '/../controllers/AuthController.php';

use Models\Playlist;
use Models\UserCourse;
use Controllers\AuthController;
use Exception; // <-- Añadido: Importa la clase Exception global

class CartController {
   private $db;
   private $playlistModel;
   private $userCourseModel;
   
   // Códigos promocionales
   private $promo_codes = [
       'SAVE10' => 0.10,
       'SAVE20' => 0.20,
       'SAVE30' => 0.30,
       'STUDENT' => 0.15,
       'WELCOME' => 0.25,
       'PROMO50' => 0.50,
       'VERANO10' => 0.10,
       'EDUCACION25' => 0.25,
       'PROFE50' => 0.50,
       'NUEVOUSUARIO' => 0.15,
       'AHORRAHOY' => 0.20,
       'BIENVENIDO20' => 0.20
   ];

   public function __construct() {
       try {
           $database = new \Database();
           $this->db = $database->getConnection();
           $this->playlistModel = new Playlist($this->db);
           $this->userCourseModel = new UserCourse($this->db);

           // Inicializar el carrito si no existe
           if (!isset($_SESSION['cart'])) {
               $_SESSION['cart'] = [];
           }
            
           // Inicializar códigos promocionales
           if (!isset($_SESSION['promo_code_applied'])) {
               $_SESSION['promo_code_applied'] = null;
               $_SESSION['promo_discount_rate'] = 0;
               $_SESSION['promo_message'] = '';
           }
       } catch (Exception $e) {
           error_log("Error en CartController constructor: " . $e->getMessage());
           throw $e;
       }
   }

   public function add($playlist_id) {
       try {
           // Validar ID
           if (!$playlist_id || !is_numeric($playlist_id)) {
               $this->sendJsonResponse('error', 'ID de curso inválido.');
               return;
           }

           // Obtener información del curso
           $playlist = $this->playlistModel->findById($playlist_id);
           if (!$playlist) {
               $this->sendJsonResponse('error', 'Curso no encontrado.');
               return;
           }

           // Verificar si el usuario ya tiene acceso
           if (AuthController::isAuthenticated()) {
               $currentUser = AuthController::getCurrentUser();
               if ($this->userCourseModel->hasAccess($currentUser['id'], $playlist_id)) {
                   $this->sendJsonResponse('info', 'Ya tienes acceso a este curso.'); // Cambiado a 'info'
                   return;
               }
           }

           // Verificar si ya está en el carrito
           if (isset($_SESSION['cart'][$playlist_id])) {
               $this->sendJsonResponse('info', 'Este curso ya está en tu carrito.');
               return;
           }

           // Agregar al carrito
           $_SESSION['cart'][$playlist_id] = [
               'id' => $playlist['id'],
               'name' => $playlist['name'] ?? $playlist['title'] ?? 'Curso sin nombre', // Mejor manejo de nombre/título
               'price' => floatval($playlist['price'] ?? 0), // Asegurar float y valor por defecto
               'cover_image' => $playlist['cover_image'] ?? $playlist['thumbnail'] ?? '', // Mejor manejo de imagen
               'level' => $playlist['level'] ?? '',
               'description' => $playlist['description'] ?? '',
               'quantity' => 1
           ];

           // Log para debugging
           error_log("Curso agregado al carrito: " . $playlist_id . " - Total items: " . count($_SESSION['cart']));

           $this->sendJsonResponse('success', 'Curso agregado al carrito exitosamente.', [
               'cart_count' => count($_SESSION['cart']),
               'course_name' => $playlist['name'] ?? $playlist['title'] ?? 'Curso sin nombre'
           ]);

       } catch (Exception $e) {
           error_log("Error agregando al carrito: " . $e->getMessage());
           $this->sendJsonResponse('error', 'Error interno del servidor.');
       }
   }

   public function remove($playlist_id) {
       try {
           if (isset($_SESSION['cart'][$playlist_id])) {
               unset($_SESSION['cart'][$playlist_id]);
               $this->sendJsonResponse('success', 'Curso eliminado del carrito.', [
                   'cart_count' => count($_SESSION['cart'])
               ]);
           } else {
               $this->sendJsonResponse('error', 'El curso no estaba en el carrito.');
           }
       } catch (Exception $e) {
           error_log("Error removiendo del carrito: " . $e->getMessage());
           $this->sendJsonResponse('error', 'Error interno del servidor.');
       }
   }

   public function getCount() {
       $count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
       $this->sendJsonResponse('success', '', ['count' => $count]);
   }

   public function getCartItems() {
    $cart_items = [];

    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        return $cart_items;
    }

    foreach ($_SESSION['cart'] as $key => $item) {
        try {
            // Detectar si $item es un array o un ID simple
            $courseId = is_array($item) ? $item['id'] : $item;

            $playlist = $this->playlistModel->findById($courseId);

            if ($playlist) {
                $cart_items[] = [
                    'id' => $playlist['id'],
                    'name' => $playlist['name'] ?? $playlist['title'] ?? 'Curso sin nombre',
                    'price' => floatval($playlist['price'] ?? 0),
                    'cover_image' => $playlist['cover_image'] ?? $playlist['thumbnail'] ?? '',
                    'level' => $playlist['level'] ?? '',
                    'description' => $playlist['description'] ?? '',
                    'quantity' => 1
                ];
            }
        } catch (Exception $e) {
            error_log("Error obteniendo item del carrito: " . $e->getMessage());
        }
    }
        return $cart_items;
    }

   public function calculateTotals($items) {
       $subtotal = 0;
       foreach ($items as $item) {
           $subtotal += floatval($item['price']);
       }

       $discount_rate = $_SESSION['promo_discount_rate'] ?? 0;
       $tax_rate = 0.07; // 7% de impuesto

       $discount = $subtotal * $discount_rate;
       $subtotal_after_discount = $subtotal - $discount;
       $tax = $subtotal_after_discount * $tax_rate;
       $total = $subtotal_after_discount + $tax;

       return [
           'subtotal' => $subtotal,
           'discount' => $discount,
           'tax' => $tax,
           'total' => $total,
           'promo_code_applied' => $_SESSION['promo_code_applied'] ?? null // Asegurar valor por defecto
       ];
   }

   public function clear() {
       $_SESSION['cart'] = [];
       unset($_SESSION['promo_code_applied']);
       unset($_SESSION['promo_discount_rate']);
       unset($_SESSION['promo_message']);
       
       $this->sendJsonResponse('success', 'Carrito vaciado.');
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

   // Método estático para obtener el contador desde otras partes
   public static function getCartCount() {
       return isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
   }

   //Metodo estatico para vaciar el carrito
   public static function emptyCart() {
    $_SESSION['cart'] = [];
   }
}

// Manejo de rutas AJAX
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    try {
        $controller = new CartController();
        $action = $_REQUEST['action'] ?? 'view';

        switch ($action) {
            case 'add':
                $playlist_id = intval($_POST['id'] ?? 0);
                $controller->add($playlist_id);
                break;

            case 'remove':
                $playlist_id = intval($_REQUEST['id'] ?? 0);
                if (isset($_SESSION['cart'][$playlist_id])) {
                    unset($_SESSION['cart'][$playlist_id]);
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Curso eliminado del carrito.',
                        'cart_count' => count($_SESSION['cart'])
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'El curso no estaba en el carrito.'
                    ]);
                }
                break;

            case 'count':
                $controller->getCount();
                break;

            case 'clear':
                $controller->clear();
                break;

            default:
                header('Location: ../views/client/cart.php');
                break;
        }
    } catch (Exception $e) {
        error_log("Error en CartController: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Error interno del servidor.']);
    }
    exit();
}
?>
