<?php
namespace Controllers;

// Asegúrate de que la sesión esté iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir las clases necesarias
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/UserCourse.php';
require_once __DIR__ . '/../models/Playlist.php';
require_once __DIR__ . '/../models/UserSyncClass.php';
require_once __DIR__ . '/../models/SyncClass.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/CartController.php';
require_once __DIR__ . '/../controllers/SyncClassController.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';
require_once __DIR__ . '/../helpers/ValidationHelper.php';
require_once __DIR__ . '/../helpers/StripeHelper.php';
require_once __DIR__ . '/../config/config.php';

use Models\Order;
use Models\UserCourse;
use Models\Playlist;
use Models\UserSyncClass;
use Models\SyncClass;
use Controllers\AuthController;
use Controllers\CartController;
use Controllers\SyncClassController;
use Helpers\SecurityHelper;
use Helpers\ValidationHelper;
use Helpers\StripeHelper;

class PaymentController {
    private $db;
    private $orderModel;
    private $userCourseModel;
    private $playlistModel;
    private $userSyncClassModel;
    private $syncClassModel;
    private $stripeHelper;

    // Configura tus claves de Stripe aquí
    private $stripeSecretKey;
    private $stripePublishableKey;

    public function __construct() {
        try {
            // Cargar configuración desde .env o usar valores por defecto para testing
            $this->stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? ''; // Clave de prueba por defecto
            $this->stripePublishableKey = $_ENV['STRIPE_PUBLIC_KEY'] ?? ''; // Clave pública de prueba por defecto

            // Si no hay claves configuradas, usar claves de prueba de Stripe
            if (empty($this->stripeSecretKey) || $this->stripeSecretKey === '') {
                // Claves de prueba reales de Stripe para desarrollo
                $this->stripeSecretKey = '';
                $this->stripePublishableKey = '';
                error_log("⚠️ Usando claves de prueba de Stripe por defecto");
            }

            $database = new \Database();
            $this->db = $database->getConnection();
            $this->orderModel = new Order($this->db);
            $this->userCourseModel = new UserCourse($this->db);
            $this->playlistModel = new Playlist($this->db);
            $this->userSyncClassModel = new UserSyncClass($this->db);
            $this->syncClassModel = new SyncClass($this->db);

            // Inicializar Stripe Helper
            $this->stripeHelper = new StripeHelper($this->stripeSecretKey);
            
            // Intentar cargar la librería oficial de Stripe si está disponible
            $this->initializeStripe();
            
        } catch (\Exception $e) {
            error_log("Error inicializando PaymentController: " . $e->getMessage());
            throw new \Exception("Error del sistema de pagos. Por favor, contacta al administrador.");
        }
    }

    /**
     * Inicializar Stripe (librería oficial si está disponible)
     */
    private function initializeStripe() {
        // Intentar cargar Stripe usando Composer
        $composerPaths = [
            __DIR__ . '/../vendor/autoload.php',
            __DIR__ . '/../../vendor/autoload.php',
            __DIR__ . '/../../../vendor/autoload.php'
        ];

        foreach ($composerPaths as $path) {
            if (file_exists($path)) {
                require_once $path;
                break;
            }
        }

        // Configurar Stripe si la clase está disponible
        if (class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($this->stripeSecretKey);
            error_log("✅ Stripe library loaded successfully");
        } else {
            error_log("⚠️ Stripe library not found, using StripeHelper");
        }
    }

    // Método para obtener la clave publicable (usado en el frontend)
    public function getPublishableKey() {
        return $this->stripePublishableKey;
    }

    // Generar token CSRF
    public function generateCSRFToken() {
        return SecurityHelper::generateCSRFToken();
    }

    // Validar token CSRF
    private function validateCSRFToken($token) {
        return SecurityHelper::validateCSRFToken($token);
    }

    // Validar datos del formulario
    private function validateCheckoutData($data) {
        return ValidationHelper::validateCheckoutData($data);
    }

    public function checkout() {
        try {
            // Verificar autenticación
            if (!AuthController::isAuthenticated()) {
                AuthController::setFlashMessage('error', 'Debes iniciar sesión para continuar con la compra.');
                header('Location: ../../login.php');
                exit();
            }

            $user = AuthController::getCurrentUser();
            $cart = $_SESSION['cart'] ?? [];
            $cartSyncClasses = $_SESSION['cart_sync_classes'] ?? [];

            if (empty($cart) && empty($cartSyncClasses)) {
                AuthController::setFlashMessage('error', 'Tu carrito está vacío.');
                header('Location: ../views/client/cart.php');
                exit();
            }

            // Usar CartController para obtener items normalizados
            $cartController = new CartController();
            $syncClassController = new SyncClassController();
            
            $cartItems = $cartController->getCartItems();
            $syncClassItems = $syncClassController->getCartItems();
            
            // Combinar items de cursos y clases sincrónicas
            $allCartItems = array_merge($cartItems, $syncClassItems);
            
            // Calcular totales combinados
            $totals = [
                'subtotal' => 0,
                'discount' => 0,
                'tax' => 0,
                'total' => 0,
                'promo_code_applied' => null
            ];
            
            if (!empty($cartItems)) {
                $courseTotals = $cartController->calculateTotals($cartItems);
                $totals['subtotal'] += $courseTotals['subtotal'];
                $totals['discount'] += $courseTotals['discount'];
                $totals['tax'] += $courseTotals['tax'];
                $totals['total'] += $courseTotals['total'];
                $totals['promo_code_applied'] = $courseTotals['promo_code_applied'];
            }
            
            if (!empty($syncClassItems)) {
                $syncClassTotals = $syncClassController->calculateTotals($syncClassItems);
                $totals['subtotal'] += $syncClassTotals['subtotal'];
                $totals['tax'] += $syncClassTotals['tax'];
                $totals['total'] += $syncClassTotals['total'];
            }

            if (empty($allCartItems)) {
                AuthController::setFlashMessage('error', 'No hay items válidos en tu carrito.');
                header('Location: ../views/client/cart.php');
                exit();
            }

            // Generar token CSRF
            $csrfToken = $this->generateCSRFToken();

            // Pasar datos a la vista (ambos tipos de items)
            $cart_items = $cartItems;
            $sync_class_items = $syncClassItems;

            include __DIR__ . '/../views/client/checkout.php';
            
        } catch (\Exception $e) {
            error_log("Error en checkout: " . $e->getMessage());
            AuthController::setFlashMessage('error', 'Error del sistema. Por favor, intenta de nuevo.');
            header('Location: ../views/client/cart.php');
            exit();
        }
    }

    public function processPayment() {
        try {
            if (!AuthController::isAuthenticated()) {
                AuthController::setFlashMessage('error', 'Debes iniciar sesión para completar la compra.');
                header('Location: ../../login.php');
                exit();
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                AuthController::setFlashMessage('error', 'Método de solicitud no permitido.');
                header('Location: ../views/client/cart.php');
                exit();
            }

            // Validar token CSRF
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!$this->validateCSRFToken($csrfToken)) {
                AuthController::setFlashMessage('error', 'Token de seguridad inválido. Por favor, intenta de nuevo.');
                header('Location: ../views/client/checkout.php');
                exit();
            }

            $currentUser = AuthController::getCurrentUser();
            $userId = $currentUser['id'];
            $userEmail = $currentUser['email'];

            // Validar datos del formulario
            $validationErrors = $this->validateCheckoutData($_POST);
            if (!empty($validationErrors)) {
                AuthController::setFlashMessage('error', 'Errores en el formulario: ' . implode(', ', $validationErrors));
                header('Location: ../views/client/checkout.php');
                exit();
            }

            $stripeToken = $_POST['stripeToken'] ?? null;

            if (empty($stripeToken)) {
                AuthController::setFlashMessage('error', 'Token de pago no recibido. Intenta de nuevo.');
                header('Location: ../views/client/checkout.php');
                exit();
            }

            // Usar CartController para obtener datos actualizados del carrito
            $cartController = new CartController();
            $syncClassController = new SyncClassController();
            
            $cartItems = $cartController->getCartItems();
            $syncClassItems = $syncClassController->getCartItems();
            
            // Combinar todos los items
            $allCartItems = array_merge($cartItems, $syncClassItems);
            
            // Calcular totales combinados
            $totals = [
                'subtotal' => 0,
                'discount' => 0,
                'tax' => 0,
                'total' => 0,
                'promo_code_applied' => null
            ];
            
            if (!empty($cartItems)) {
                $courseTotals = $cartController->calculateTotals($cartItems);
                $totals['subtotal'] += $courseTotals['subtotal'];
                $totals['discount'] += $courseTotals['discount'];
                $totals['tax'] += $courseTotals['tax'];
                $totals['total'] += $courseTotals['total'];
                $totals['promo_code_applied'] = $courseTotals['promo_code_applied'];
            }
            
            if (!empty($syncClassItems)) {
                $syncClassTotals = $syncClassController->calculateTotals($syncClassItems);
                $totals['subtotal'] += $syncClassTotals['subtotal'];
                $totals['tax'] += $syncClassTotals['tax'];
                $totals['total'] += $syncClassTotals['total'];
            }

            if (empty($allCartItems)) {
                AuthController::setFlashMessage('error', 'Tu carrito está vacío. No se puede procesar el pago.');
                header('Location: ../views/client/cart.php');
                exit();
            }

            // Verificar acceso duplicado y preparar listas
            $purchasedPlaylistIds = [];
            $purchasedSyncClassIds = [];
            
            foreach ($cartItems as $item) {
                $playlistId = $item['id'];
                
                // Verificar que el usuario no tenga acceso
                if ($this->userCourseModel->hasAccess($userId, $playlistId)) {
                    AuthController::setFlashMessage('error', 'Ya tienes acceso a uno de los cursos seleccionados: ' . htmlspecialchars($item['name']));
                    header('Location: ../views/client/cart.php');
                    exit();
                }
                
                $purchasedPlaylistIds[] = $playlistId;
            }
            
            foreach ($syncClassItems as $item) {
                $syncClassId = $item['id'];
                
                // Verificar que el usuario no tenga acceso
                if ($this->userSyncClassModel->hasAccess($userId, $syncClassId)) {
                    AuthController::setFlashMessage('error', 'Ya tienes acceso a una de las clases seleccionadas: ' . htmlspecialchars($item['title']));
                    header('Location: ../views/client/cart.php');
                    exit();
                }
                
                $purchasedSyncClassIds[] = $syncClassId;
            }

            $finalAmount = $totals['total'];

            // Validar que el monto sea mayor a 0
            if ($finalAmount <= 0) {
                AuthController::setFlashMessage('error', 'El monto total debe ser mayor a cero.');
                header('Location: ../views/client/checkout.php');
                exit();
            }

            // Stripe espera el monto en centavos
            $amountInCents = round($finalAmount * 100);
            $currency = 'usd';

            // Procesar pago con Stripe
            $charge = $this->processStripePayment([
                'amount' => $amountInCents,
                'currency' => $currency,
                'source' => $stripeToken,
                'description' => 'Compra de cursos en El Profesor Hernán - Usuario: ' . $userEmail,
                'receipt_email' => $userEmail,
                'metadata' => [
                    'user_id' => $userId,
                    'user_email' => $userEmail,
                    'cart_courses' => json_encode($purchasedPlaylistIds),
                    'cart_sync_classes' => json_encode($purchasedSyncClassIds),
                    'original_amount' => $totals['subtotal'],
                    'discount_applied' => $totals['discount'],
                    'tax_amount' => $totals['tax'],
                    'promo_code' => $totals['promo_code_applied'] ?? 'none'
                ]
            ]);

            if ($charge && isset($charge['status']) && $charge['status'] === 'succeeded') {
                $transactionId = $charge['id'];
                $orderStatus = 'completed';

                error_log("=== INICIO PROCESO REGISTRO PEDIDO ===");
                error_log("Transaction ID: $transactionId");
                error_log("User ID: $userId");
                error_log("Amount: $finalAmount");
                error_log("Cursos a comprar: " . json_encode($purchasedPlaylistIds));
                error_log("Clases sincrónicas a comprar: " . json_encode($purchasedSyncClassIds));

                try {
                    if (!$this->db) {
                        throw new \Exception("No hay conexión a la base de datos");
                    }

                    $this->db->beginTransaction();
                    error_log("Transacción de BD iniciada");

                    $existingOrder = $this->orderModel->findByTransactionId($transactionId);
                    if ($existingOrder) {
                        error_log("Ya existe un pedido con transaction_id: $transactionId. No se creará duplicado.");
                        $this->db->rollback();
                        
                        // Limpiar carrito y redirigir a confirmación
                        unset($_SESSION['cart']);
                        unset($_SESSION['cart_sync_classes']);
                        unset($_SESSION['promo_code_applied']);
                        unset($_SESSION['promo_discount_rate']);
                        unset($_SESSION['promo_message']);
                        unset($_SESSION['csrf_token']);
                        
                        AuthController::setFlashMessage('info', '¡Tu pago ya fue procesado anteriormente! Revisa tu historial de compras.');
                        header('Location: ../views/client/purchase-history.php');
                        exit();
                    }

                    $this->orderModel->user_id = $userId;
                    $this->orderModel->transaction_id = $transactionId;
                    $this->orderModel->amount = $finalAmount;
                    $this->orderModel->currency = $currency;
                    $this->orderModel->status = $orderStatus;
                    $this->orderModel->payment_method = 'stripe';

                    error_log("Intentando crear pedido...");
                    if (!$this->orderModel->create()) {
                        throw new \Exception("Error al crear el pedido en la base de datos - método create() retornó false");
                    }

                    $orderId = $this->orderModel->id;
                    if (!$orderId || $orderId <= 0) {
                        throw new \Exception("ID de pedido inválido después de crear: $orderId");
                    }

                    error_log("Pedido creado exitosamente con ID: $orderId");

                    $accessGranted = true;
                    $accessErrors = [];
                    
                    // Otorgar acceso a cursos
                    foreach ($purchasedPlaylistIds as $playlistId) {
                        error_log("Otorgando acceso al curso $playlistId para usuario $userId");
                        
                        $course = $this->playlistModel->findById($playlistId);
                        if (!$course) {
                            $accessErrors[] = "Curso $playlistId no encontrado";
                            error_log("Error: Curso $playlistId no encontrado");
                            continue;
                        }
                        
                        if ($this->userCourseModel->hasAccess($userId, $playlistId)) {
                            error_log("Usuario $userId ya tiene acceso al curso $playlistId");
                            continue;
                        }
                        
                        if (!$this->userCourseModel->grantAccess($userId, $playlistId, $orderId)) {
                            $accessGranted = false;
                            $accessErrors[] = "Error al otorgar acceso al curso $playlistId";
                            error_log("Error al otorgar acceso al curso $playlistId para el usuario $userId");
                        } else {
                            error_log("Acceso otorgado exitosamente al curso $playlistId para el usuario $userId");
                            if ($this->userCourseModel->hasAccess($userId, $playlistId)) {
                                error_log("VERIFICACIÓN: Usuario $userId AHORA tiene acceso al curso $playlistId.");
                            } else {
                                error_log("VERIFICACIÓN FALLIDA: Usuario $userId NO tiene acceso al curso $playlistId después de intentar otorgarlo.");
                            }
                        }
                    }
                    
                    // Otorgar acceso a clases sincrónicas
                    foreach ($purchasedSyncClassIds as $syncClassId) {
                        error_log("Otorgando acceso a la clase sincrónica $syncClassId para usuario $userId");
                        
                        $syncClass = $this->syncClassModel->readOne($syncClassId);
                        if (!$syncClass) {
                            $accessErrors[] = "Clase sincrónica $syncClassId no encontrada";
                            error_log("Error: Clase sincrónica $syncClassId no encontrada");
                            continue;
                        }
                        
                        if ($this->userSyncClassModel->hasAccess($userId, $syncClassId)) {
                            error_log("Usuario $userId ya tiene acceso a la clase sincrónica $syncClassId");
                            continue;
                        }
                        
                        if (!$this->userSyncClassModel->grantAccess($userId, $syncClassId, $orderId)) {
                            $accessGranted = false;
                            $accessErrors[] = "Error al otorgar acceso a la clase sincrónica $syncClassId";
                            error_log("Error al otorgar acceso a la clase sincrónica $syncClassId para el usuario $userId");
                        } else {
                            error_log("Acceso otorgado exitosamente a la clase sincrónica $syncClassId para el usuario $userId");
                            if ($this->userSyncClassModel->hasAccess($userId, $syncClassId)) {
                                error_log("VERIFICACIÓN: Usuario $userId AHORA tiene acceso a la clase sincrónica $syncClassId.");
                            } else {
                                error_log("VERIFICACIÓN FALLIDA: Usuario $userId NO tiene acceso a la clase sincrónica $syncClassId después de intentar otorgarlo.");
                            }
                        }
                    }

                    if (!$accessGranted && !empty($accessErrors)) {
                        error_log("Errores de acceso: " . implode(", ", $accessErrors));
                    }

                    $this->db->commit();
                    error_log("Transacción de BD confirmada exitosamente");

                    error_log("Limpiando carrito de la sesión...");
                    unset($_SESSION['cart']);
                    unset($_SESSION['cart_sync_classes']);
                    unset($_SESSION['promo_code_applied']);
                    unset($_SESSION['promo_discount_rate']);
                    unset($_SESSION['promo_message']);
                    unset($_SESSION['csrf_token']);
                    error_log("Carrito limpiado exitosamente");

                    error_log("=== PROCESO COMPLETADO EXITOSAMENTE ===");

                    AuthController::setFlashMessage('success', '¡Pago exitoso! Tu compra ha sido confirmada y ya tienes acceso a tus cursos.');
                    header('Location: ../views/client/order-confirmation.php?order_id=' . $orderId);
                    exit();

                } catch (\Exception $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollback();
                        error_log("Transacción de BD revertida");
                    }
                    
                    error_log("=== ERROR EN REGISTRO DE PEDIDO ===");
                    error_log("Error completo: " . $e->getMessage());
                    error_log("Stack trace: " . $e->getTraceAsString());
                    error_log("Transaction ID: $transactionId");
                    error_log("User ID: $userId");
                    error_log("Amount: $finalAmount");
                    
                    try {
                        error_log("Intentando crear registro de emergencia...");
                        $emergencyQuery = "INSERT INTO orders (user_id, transaction_id, amount, currency, status, payment_method, created_at) 
                                          VALUES (?, ?, ?, ?, 'pending_review', 'stripe', NOW())";
                        $emergencyStmt = $this->db->prepare($emergencyQuery);
                        $emergencyStmt->execute([$userId, $transactionId, $finalAmount, $currency]);
                        error_log("Registro de emergencia creado");
                    } catch (\Exception $emergencyError) {
                        error_log("Error creando registro de emergencia: " . $emergencyError->getMessage());
                    }
                    
                    AuthController::setFlashMessage('error', 'Pago exitoso, pero hubo un error al registrar tu pedido. Por favor, contacta a soporte con el ID de transacción: ' . $transactionId);
                    header('Location: ../views/client/cart.php');
                    exit();
                }
            } else {
                $status = $charge['status'] ?? 'unknown';
                AuthController::setFlashMessage('error', 'El pago no pudo ser procesado. Estado: ' . $status);
                header('Location: ../views/client/checkout.php');
                exit();
            }

        } catch (\Exception $e) {
            error_log("Error general en processPayment: " . $e->getMessage());
            $errorMessage = 'Ocurrió un error inesperado al procesar tu pago. Intenta de nuevo.';

            error_log("Error general en processPayment: " . $e->getMessage());            
            $errorMessage = 'Ocurrió un error inesperado al procesar tu pago. Intenta de nuevo.';
            
            if (strpos($e->getMessage(), 'card') !== false || strpos($e->getMessage(), 'declined') !== false) {
                $errorMessage = 'Tu tarjeta fue rechazada. Por favor, verifica los datos o usa otra tarjeta.';
            } elseif (strpos($e->getMessage(), 'network') !== false || strpos(strtolower($e->getMessage()), 'connection') !== false) {
                $errorMessage = 'Error de conexión con el sistema de pagos. Intenta de nuevo.';
            }
            
            AuthController::setFlashMessage('error', $errorMessage);
            header('Location: ../views/client/checkout.php');
            exit();
        }
    }

    /**
     * Procesar pago con Stripe (usando librería oficial o helper)
     */
    private function processStripePayment($params) {
        try {
            // Intentar usar la librería oficial de Stripe primero
            if (class_exists('\Stripe\Charge')) {
                return \Stripe\Charge::create($params)->toArray();
            } else {
                // Usar nuestro helper personalizado
                return $this->stripeHelper->createCharge($params);
            }
        } catch (\Exception $e) {
            error_log("Error en processStripePayment: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Webhook para manejar eventos de Stripe
     */
    public function handleWebhook() {
        try {
            $payload = file_get_contents('php://input');
            $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
            
            // Validar la firma del webhook (implementar según necesidades)
            // $this->validateWebhookSignature($payload, $signature);
            
            $event = json_decode($payload, true);
            
            if (!$event) {
                http_response_code(400);
                exit('Invalid payload');
            }

            // Manejar diferentes tipos de eventos
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentSucceeded($event['data']['object']);
                    CartController::emptyCart();
                    break;
                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailed($event['data']['object']);
                    break;
                default:
                    error_log('Unhandled webhook event type: ' . $event['type']);
            }

            http_response_code(200);
            echo 'Webhook handled successfully';
            
        } catch (\Exception $e) {
            error_log("Error en webhook: " . $e->getMessage());
            http_response_code(500);
            exit('Webhook error');
        }
    }

    private function handlePaymentSucceeded($paymentIntent) {
        // Lógica para manejar pago exitoso
        error_log("Payment succeeded: " . $paymentIntent['id']);
    }

    private function handlePaymentFailed($paymentIntent) {
        // Lógica para manejar pago fallido
        error_log("Payment failed: " . $paymentIntent['id']);
    }
}

// Manejar las rutas si se accede directamente a este archivo
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    $action = $_GET['action'] ?? 'checkout';
    $controller = new PaymentController();
    
    switch ($action) {
        case 'processPayment':
            $controller->processPayment();
            break;
        case 'webhook':
            $controller->handleWebhook();
            break;
        case 'checkout':
        default:
            $controller->checkout();
            break;
    }
}
