<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/Order.php';
require_once __DIR__ . '/../../models/UserCourse.php';
require_once __DIR__ . '/../../models/UserSyncClass.php';
require_once __DIR__ . '/../../models/Playlist.php';
require_once __DIR__ . '/../../controllers/CartController.php';

use Controllers\AuthController;
use Models\Order;
use Models\UserCourse;
use Models\UserSyncClass;
use Controllers\CartController;

// Verificar autenticación
if (!AuthController::isAuthenticated()) {
    AuthController::setFlashMessage('error', 'Debes iniciar sesión para ver esta página.');
    header('Location: ../../login.php');
    exit();
}

$orderId = $_GET['order_id'] ?? null;
if (!$orderId || !is_numeric($orderId)) {
    AuthController::setFlashMessage('error', 'ID de pedido inválido.');
    header('Location: home.php');
    exit();
}

// Conexión a la BD
$database = new \Database();
$db = $database->getConnection();

// Modelos
$orderModel = new Order($db);
$userCourseModel = new UserCourse($db);
$userSyncClassModel = new UserSyncClass($db);
$cartController = new CartController();

// Obtener datos del pedido
$order = $orderModel->getOrderById($orderId);
if (!$order) {
    AuthController::setFlashMessage('error', 'Pedido no encontrado.');
    header('Location: home.php');
    exit();
}

// Verificar propiedad del pedido
$currentUser = AuthController::getCurrentUser();
if ($order['user_id'] != $currentUser['id']) {
    AuthController::setFlashMessage('error', 'No tienes permiso para ver este pedido.');
    header('Location: home.php');
    exit();
}

// Obtener cursos asociados al pedido
$orderItems = $userCourseModel->getCoursesByOrderId($orderId);

// Obtener clases sincrónicas asociadas al pedido
$syncClassItems = $userSyncClassModel->getSyncClassesByOrderId($orderId);

// Obtener conteo del carrito
$cart_count = $cartController->getCartCount();

// Función para mostrar nombre del usuario
function getUserDisplayName($user) {
    if (!empty($user['name'])) return htmlspecialchars($user['name']);
    if (!empty($user['first_name'])) {
        $name = $user['first_name'];
        if (!empty($user['last_name'])) $name .= ' ' . $user['last_name'];
        return htmlspecialchars($name);
    }
    if (!empty($user['email'])) {
        $emailParts = explode('@', $user['email']);
        return htmlspecialchars(ucfirst($emailParts[0]));
    }
    return 'Usuario';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Compra - El Profesor Hernán</title>
    <link rel="stylesheet" href="../../public/css/styles.css">
    <link rel="stylesheet" href="../../public/css/client/order-confirmation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="../../public/img/logo-profe-hernan.png" alt="El Profesor Hernán" style="height: 40px;">
                <span>El Profesor Hernán</span>
            </div>
            
            <nav class="nav">
                <ul>
                    <li><a href="home.php">Inicio</a></li>
                    <li><a href="all-courses.php">Cursos</a></li>
                    <li><a href="cart.php">
                        <i class="fas fa-shopping-cart"></i>
                        Carrito
                        <?php if ($cart_count > 0): ?>
                            <span class="cart-count"><?php echo $cart_count; ?></span>
                        <?php endif; ?>
                    </a></li>
                </ul>
            </nav>
            
            <div class="auth-links">
                <span>Hola, <?php echo getUserDisplayName($currentUser); ?></span>
                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                    <a href="../admin/index.php?controller=admin&action=dashboard" class="btn-admin">Panel Admin</a>
                <?php endif; ?>
                <a href="purchase-history.php" class="btn-history">Mis Cursos</a>
                <a href="../../logout.php" class="btn-logout">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <!-- Confirmation Section -->
    <section class="confirmation-section">
        <div class="container">
            <div class="confirmation-container">
                <!-- Header -->
                <div class="confirmation-header">
                    <i class="fas fa-check-circle"></i>
                    <h1>¡Compra Exitosa!</h1>
                    <p>Tu pago ha sido procesado correctamente</p>
                </div>

                <!-- Content -->
                <div class="confirmation-content">
                    <!-- Order Summary -->
                    <div class="order-summary">
                        <h3><i class="fas fa-receipt"></i> Resumen del Pedido</h3>
                        <div class="order-details">
                            <div class="order-detail">
                                <span>Número de Pedido:</span>
                                <strong>#<?php echo htmlspecialchars($order['id']); ?></strong>
                            </div>
                            <div class="order-detail">
                                <span>ID de Transacción:</span>
                                <strong><?php echo htmlspecialchars($order['transaction_id']); ?></strong>
                            </div>
                            <div class="order-detail">
                                <span>Fecha:</span>
                                <strong><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></strong>
                            </div>
                            <div class="order-detail">
                                <span>Total Pagado:</span>
                                <strong>$<?php echo number_format($order['amount'], 2); ?> <?php echo strtoupper($order['currency']); ?></strong>
                            </div>
                            <div class="order-detail">
                                <span>Método de Pago:</span>
                                <strong><?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></strong>
                            </div>
                            <div class="order-detail">
                                <span>Estado:</span>
                                <strong style="color: #28a745;">Completado</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Courses Purchased -->
                    <div class="courses-purchased">
                        <h3><i class="fas fa-graduation-cap"></i> Productos Adquiridos</h3>
                        
                        <?php if (!empty($orderItems)): ?>
                            <div class="course-list">
                                <?php foreach ($orderItems as $course): ?>
                                    <div class="course-item">
                                        <div class="course-image">
                                            <?php 
                                            $courseImageUrl = !empty($course['cover_image']) ? '../../' . $course['cover_image'] : 'https://i.imgur.com/xdbHo4E.png';
                                            ?>
                                            <img src="<?php echo htmlspecialchars($courseImageUrl); ?>" 
                                                alt="<?php echo htmlspecialchars($course['name'] ?? 'Curso'); ?>">
                                        </div>
                                        <div class="course-info">
                                            <div class="course-name"><?php echo htmlspecialchars($course['name'] ?? 'Curso sin nombre'); ?></div>
                                            <div class="course-level">Nivel: <?php echo htmlspecialchars($course['level'] ?? 'Todos los niveles'); ?></div>
                                        </div>
                                        <div class="course-price">
                                            $<?php echo number_format($course['price'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($syncClassItems)): ?>
                            <div class="course-list">
                                <?php foreach ($syncClassItems as $syncClass): ?>
                                    <div class="course-item" style="border-left: 3px solid #667eea;">
                                        <div class="course-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-video" style="font-size: 3rem; color: white;"></i>
                                        </div>
                                        <div class="course-info">
                                            <div class="course-name">
                                                <i class="fas fa-video"></i> <?php echo htmlspecialchars($syncClass['title'] ?? 'Clase sin título'); ?>
                                            </div>
                                            <div class="course-level">
                                                <i class="fas fa-calendar"></i> <?php echo date('d M Y - H:i', strtotime($syncClass['start_date'])); ?>
                                            </div>
                                            <?php if (!empty($syncClass['meeting_link'])): ?>
                                                <div class="course-level">
                                                    <i class="fas fa-link"></i> 
                                                    <a href="<?php echo htmlspecialchars($syncClass['meeting_link']); ?>" target="_blank" style="color: #667eea;">
                                                        Enlace de reunión
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="course-price">
                                            $<?php echo number_format($syncClass['price'] ?? 0, 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($orderItems) && empty($syncClassItems)): ?>
                            <p>No se encontraron detalles de los productos. Por favor, contacta a soporte.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Next Steps -->
                    <div class="next-steps">
                        <h4><i class="fas fa-lightbulb"></i> Próximos Pasos</h4>
                        <ul>
                            <?php if (!empty($orderItems)): ?>
                                <li><i class="fas fa-check"></i> Ya tienes acceso completo a todos los cursos comprados</li>
                                <li><i class="fas fa-play"></i> Puedes comenzar a estudiar inmediatamente</li>
                            <?php endif; ?>
                            <?php if (!empty($syncClassItems)): ?>
                                <li><i class="fas fa-video"></i> Tus clases sincrónicas están confirmadas</li>
                                <li><i class="fas fa-calendar-alt"></i> Descarga los eventos a tu calendario desde "Mis Cursos"</li>
                                <li><i class="fas fa-bell"></i> Recibirás recordatorios automáticos antes de cada clase</li>
                            <?php endif; ?>
                            <li><i class="fas fa-history"></i> Revisa tu historial de compras en cualquier momento</li>
                        </ul>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="purchase-history.php" class="btn-primary">
                            <i class="fas fa-graduation-cap"></i>
                            Ver Mis Cursos
                        </a>
                        <a href="home.php" class="btn-secondary">
                            <i class="fas fa-home"></i>
                            Volver al Inicio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 El Profesor Hernán. Todos los derechos reservados.</p>
            <div class="footer-links">
                <a href="home.php">Inicio</a>
                <a href="all-courses.php">Cursos</a>
                <a href="purchase-history.php">Mis Cursos</a>
            </div>
            <p>¡Gracias por confiar en nosotros para tu aprendizaje!</p>
        </div>
    </footer>

    <script>
        // Limpiar cualquier dato del carrito que pueda quedar en localStorage
        if (typeof(Storage) !== "undefined") {
            localStorage.removeItem('cart');
            localStorage.removeItem('cartCount');
        }

        // Animación de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const courseItems = document.querySelectorAll('.course-item');
            courseItems.forEach((item, index) => {
                setTimeout(() => {
                    item.style.opacity = '0';
                    item.style.transform = 'translateY(20px)';
                    item.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 150);
            });
        });
    </script>
</body>
</html>
