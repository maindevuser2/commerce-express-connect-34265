
<?php
// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir dependencias
require_once __DIR__ . '/../../controllers/CartController.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

use Controllers\CartController;
use Controllers\AuthController;

// Verificar autenticación
if (!AuthController::isAuthenticated()) {
    header('Location: ../../login.php');
    exit();
}

// Inicializar controlador del carrito
$cartController = new CartController();
$cart_items = $cartController->getCartItems();
$totals = $cartController->calculateTotals($cart_items);
$currentUser = AuthController::getCurrentUser();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrito de Compras - El Profesor Hernán</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/cart-improvements.css">
    <link rel="stylesheet" href="../../public/css/client/cart.css">
</head>
<body>
    <div class="header-section">
        <div class="container">
            <h1><i class="fas fa-shopping-cart"></i> Mi Carrito</h1>
            <p>Revisa y gestiona tus cursos seleccionados</p>
            <?php if (!empty($cart_items)): ?>
                <div class="cart-badge">
                    <i class="fas fa-graduation-cap"></i> <?php echo count($cart_items); ?> curso<?php echo count($cart_items) > 1 ? 's' : ''; ?> seleccionado<?php echo count($cart_items) > 1 ? 's' : ''; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <?php if (empty($cart_items)): ?>
            <div class="empty-cart">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3>Tu carrito está vacío</h3>
                <p>¡Descubre nuestra amplia variedad de cursos y comienza tu viaje de aprendizaje hoy mismo!</p>
                <a href="all-courses.php" class="btn-explore">
                    <i class="fas fa-search"></i> Explorar Cursos
                </a>
            </div>
        <?php else: ?>
            <div class="cart-content">
                <div class="cart-items">
                    <h3 class="section-title">
                        <i class="fas fa-list"></i>
                        Cursos Seleccionados
                    </h3>
                    
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                <img src="<?php echo !empty($item['cover_image']) ? '../../' . $item['cover_image'] : 'https://via.placeholder.com/140x100/8a56e2/ffffff?text=Curso'; ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                            </div>
                            
                            <div class="item-details">
                                <h4 class="item-title"><?php echo htmlspecialchars($item['name']); ?></h4>
                                <?php if (!empty($item['level'])): ?>
                                    <span class="item-level"><?php echo htmlspecialchars($item['level']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($item['description'])): ?>
                                    <p class="item-description"><?php echo htmlspecialchars(substr($item['description'], 0, 120)) . '...'; ?></p>
                                <?php endif; ?>
                                <div class="item-price">$<?php echo number_format($item['price'], 2); ?></div>
                            </div>
                            
                            <div class="item-actions">
                                <button onclick="removeFromCart(<?php echo $item['id']; ?>)" class="btn-remove">
                                    <i class="fas fa-trash-alt"></i> Eliminar
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h3 class="summary-header">
                        <i class="fas fa-receipt"></i>
                        Resumen del Pedido
                    </h3>
                    
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span class="summary-value">$<?php echo number_format($totals['subtotal'], 2); ?></span>
                    </div>
                    
                    <?php if ($totals['discount'] > 0): ?>
                        <div class="summary-row">
                            <span><i class="fas fa-tag"></i> Descuento:</span>
                            <span class="discount-value">-$<?php echo number_format($totals['discount'], 2); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="summary-row">
                        <span>Impuestos (7%):</span>
                        <span class="summary-value">$<?php echo number_format($totals['tax'], 2); ?></span>
                    </div>
                    
                    <div class="summary-row total">
                        <span>Total:</span>
                        <span>$<?php echo number_format($totals['total'], 2); ?></span>
                    </div>
                    
                    <button onclick="proceedToCheckout()" class="btn-checkout">
                        <i class="fas fa-credit-card"></i>
                        Proceder al Pago
                    </button>
                    
                    <a href="all-courses.php" class="btn-continue">
                        <i class="fas fa-arrow-left"></i>
                        Seguir Comprando
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function removeFromCart(courseId) {
            if (confirm('¿Estás seguro de que quieres eliminar este curso del carrito?')) {
                fetch('../../controllers/CartController.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=remove&id=${courseId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        location.reload();
                    } else {
                        alert(data.message || 'Error al eliminar el curso');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar el curso');
                });
            }
        }

        function proceedToCheckout() {
            window.location.href = 'checkout.php';
        }
    </script>
</body>
</html>
