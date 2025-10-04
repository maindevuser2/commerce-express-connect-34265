<?php
// Asegúrate de que la sesión esté iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir el controlador de pagos para obtener la clave publicable
require_once __DIR__ . '/../../controllers/PaymentController.php';
require_once __DIR__ . '/../../controllers/AuthController.php';
require_once __DIR__ . '/../../controllers/CartController.php';

use Controllers\PaymentController;
use Controllers\AuthController;
use Controllers\CartController;
use Controllers\SyncClassController;

$paymentController = new PaymentController();
$stripePublishableKey = $paymentController->getPublishableKey();

// Las variables $cart_items, $sync_class_items, $totals y $csrfToken se pasan desde el PaymentController::checkout()
// Si se accede directamente, inicializarlas
if (!isset($cart_items) || !isset($totals)) {
    require_once __DIR__ . '/../../controllers/SyncClassController.php';
    
    $cartController = new CartController();
    $syncClassController = new SyncClassController();
    
    $cart_items = $cartController->getCartItems();
    $sync_class_items = $syncClassController->getCartItems();
    
    // Combinar items para cálculo de totales
    $all_items = array_merge($cart_items, $sync_class_items);
    $totals = $cartController->calculateTotals($all_items);
    $csrfToken = $paymentController->generateCSRFToken();
}

if (empty($cart_items) && empty($sync_class_items)) {
    header('Location: cart.php');
    exit();
}

// Obtener mensaje flash si existe
$flashMessage = AuthController::getFlashMessage();
$currentUser = AuthController::getCurrentUser();

// Determinar la ruta base correcta
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Obtener la ruta base del proyecto
if (strpos($scriptName, '/controllers/') !== false) {
    // Se accede desde el controlador
    $basePath = str_replace('/controllers/PaymentController.php', '', $scriptName);
} else {
    // Se accede desde views/client/
    $basePath = str_replace('/views/client/checkout.php', '', $scriptName);
}

$baseUrl = $protocol . '://' . $host . $basePath;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>El Profesor Hernán - Finalizar Compra</title>
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/public/css/styles.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>/public/css/client/checkout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Stripe.js -->
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="logo">
                <img src="<?php echo $baseUrl; ?>/public/img/logo-profe-hernan.png" alt="El Profesor Hernán" style="height: 40px;">
                <span>El Profesor Hernán</span>
            </div>
            
            <nav class="nav">
                <ul>
                    <li><a href="<?php echo $baseUrl; ?>/views/client/home.php">Inicio</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/views/client/home.php">Cursos</a></li>
                    <li><a href="<?php echo $baseUrl; ?>/views/client/cart.php">
                        <i class="fas fa-shopping-cart"></i>
                        Carrito
                        <?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                            <span class="cart-count"><?php echo count($_SESSION['cart']); ?></span>
                        <?php endif; ?>
                    </a></li>
                </ul>
            </nav>
            
            <div class="auth-links">
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                    <span>Hola, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['user_email']); ?></span>
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <a href="<?php echo $baseUrl; ?>/views/admin/index.php?controller=admin&action=dashboard" class="btn-admin">Panel Admin</a>
                    <?php endif; ?>
                    <a href="<?php echo $baseUrl; ?>/logout.php" class="btn-logout">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="<?php echo $baseUrl; ?>/login.php" class="btn-login">Iniciar Sesión</a>
                    <a href="<?php echo $baseUrl; ?>/signup.php" class="btn-signup">Registrarse</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Progress Indicator -->
    <div class="checkout-progress">
        <div class="container">
            <div class="progress-steps">
                <div class="step completed">
                    <div class="step-number">1</div>
                    <div class="step-label">Carrito</div>
                </div>
                <div class="step active">
                    <div class="step-number">2</div>
                    <div class="step-label">Información</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-label">Pago</div>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-label">Confirmación</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Checkout Section -->
    <section class="checkout">
        <div class="container">
            <h1 class="checkout-title">Finalizar Compra</h1>
            
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?php echo $flashMessage['type']; ?>">
                    <i class="fas fa-<?php echo $flashMessage['type'] === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                    <?php echo $flashMessage['message']; ?>
                </div>
            <?php endif; ?>

            <div class="checkout-container">
                <!-- Formulario de Checkout -->
                <div class="checkout-form">
                    <form id="payment-form" action="<?php echo $baseUrl; ?>/controllers/PaymentController.php?action=processPayment" method="post" novalidate>
                        <!-- Token CSRF -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                        
                        <!-- Información Personal -->
                        <div class="checkout-section">
                            <h2><i class="fas fa-user"></i> Información Personal</h2>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="first_name">Nombre *</label>
                                    <input type="text" id="first_name" name="first_name" required 
                                           value="<?php echo htmlspecialchars($currentUser['first_name'] ?? ''); ?>"
                                           placeholder="Ingresa tu nombre">
                                    <div class="error-message"></div>
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Apellido *</label>
                                    <input type="text" id="last_name" name="last_name" required 
                                           value="<?php echo htmlspecialchars($currentUser['last_name'] ?? ''); ?>"
                                           placeholder="Ingresa tu apellido">
                                    <div class="error-message"></div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">Correo Electrónico *</label>
                                    <input type="email" id="email" name="email" required 
                                           value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>"
                                           placeholder="tu@email.com">
                                    <div class="error-message"></div>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Teléfono *</label>
                                    <input type="tel" id="phone" name="phone" required 
                                           placeholder="+1 (555) 123-4567">
                                    <div class="error-message"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Información de Facturación -->
                        <div class="checkout-section">
                            <h2><i class="fas fa-map-marker-alt"></i> Información de Facturación</h2>
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="address">Dirección *</label>
                                    <input type="text" id="address" name="address" required 
                                           placeholder="Calle, número, apartamento">
                                    <div class="error-message"></div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="city">Ciudad *</label>
                                    <input type="text" id="city" name="city" required 
                                           placeholder="Tu ciudad">
                                    <div class="error-message"></div>
                                </div>
                                <div class="form-group">
                                    <label for="state">Estado/Provincia *</label>
                                    <input type="text" id="state" name="state" required 
                                           placeholder="Estado o provincia">
                                    <div class="error-message"></div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="zip_code">Código Postal *</label>
                                    <input type="text" id="zip_code" name="zip_code" required 
                                           placeholder="12345">
                                    <div class="error-message"></div>
                                </div>
                                <div class="form-group">
                                    <label for="country">País *</label>
                                    <select id="country" name="country" required>
                                        <option value="">Seleccionar País</option>
                                        <option value="CO">Colombia</option>
                                        <option value="MX">México</option>
                                        <option value="AR">Argentina</option>
                                        <option value="ES">España</option>
                                        <option value="US">Estados Unidos</option>
                                        <option value="PE">Perú</option>
                                        <option value="CL">Chile</option>
                                        <option value="EC">Ecuador</option>
                                        <option value="VE">Venezuela</option>
                                        <option value="other">Otro</option>
                                    </select>
                                    <div class="error-message"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Método de Pago -->
                        <div class="checkout-section">
                            <h2><i class="fas fa-credit-card"></i> Método de Pago</h2>
                            <div class="payment-methods">
                                <label class="payment-method active">
                                    <input type="radio" name="payment_method" value="credit_card" checked>
                                    <span class="radio-custom"></span>
                                    <div class="payment-label">
                                        <i class="fas fa-credit-card"></i>
                                        Tarjeta de Crédito/Débito
                                        <div class="payment-icons">
                                            <i class="fab fa-cc-visa"></i>
                                            <i class="fab fa-cc-mastercard"></i>
                                            <i class="fab fa-cc-amex"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <!-- Stripe Elements Form -->
                            <div class="credit-card-form" id="stripe-card-form">
                                <div class="card-element-container">
                                    <label for="card-element">Información de la Tarjeta *</label>
                                    <div id="card-element" class="stripe-element">
                                        <!-- Un elemento de Stripe se insertará aquí. -->
                                    </div>
                                </div>
                                <!-- Usado para mostrar errores de Stripe.js -->
                                <div id="card-errors" role="alert"></div>
                                <div id="payment-status"></div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Resumen del Pedido -->
                <div class="order-summary">
                    <div class="summary-header">
                        <h2>Resumen del Pedido</h2>
                        <span class="items-count"><?php echo count($cart_items) + count($sync_class_items ?? []); ?></span>
                    </div>
                    
                    <div class="cart-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item">
                                <div class="item-image">
                                    <?php if (!empty($item['cover_image'])): ?>
                                        <img src="<?php echo $baseUrl; ?>/<?php echo htmlspecialchars($item['cover_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php else: ?>
                                        <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/design-mode-images/xdbHo4E%281%29%281%29-QSxOwvO8DrsOWwS2dHkipAjOOOFPhb.png" alt="Imagen por defecto">
                                    <?php endif; ?>
                                </div>
                                <div class="item-details">
                                    <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p>Acceso Digital Completo</p>
                                    <span class="item-level"><?php echo htmlspecialchars($item['level'] ?? 'Todos los niveles'); ?></span>
                                </div>
                                <div class="item-price">$<?php echo htmlspecialchars(number_format($item['price'], 2)); ?></div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (isset($sync_class_items)): ?>
                            <?php foreach ($sync_class_items as $item): ?>
                                <div class="cart-item" style="border-left: 3px solid #667eea;">
                                    <div class="item-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white;">
                                        <i class="fas fa-video" style="font-size: 2rem;"></i>
                                    </div>
                                    <div class="item-details">
                                        <h3><i class="fas fa-video"></i> <?php echo htmlspecialchars($item['title']); ?></h3>
                                        <p><i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($item['start_date'])); ?></p>
                                        <span class="item-level" style="background: #667eea; color: white;">Clase Sincrónica</span>
                                    </div>
                                    <div class="item-price">$<?php echo htmlspecialchars(number_format($item['price'], 2)); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="summary-totals">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span>$<?php echo htmlspecialchars(number_format($totals['subtotal'], 2)); ?></span>
                        </div>
                        
                        <?php if ($totals['discount'] > 0): ?>
                            <div class="summary-row discount">
                                <span>Descuento 
                                    <?php if ($totals['promo_code_applied']): ?>
                                        (<?php echo htmlspecialchars($totals['promo_code_applied']); ?>)
                                    <?php endif; ?>
                                </span>
                                <span>-$<?php echo htmlspecialchars(number_format($totals['discount'], 2)); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="summary-row">
                            <span>Impuesto (7%)</span>
                            <span>$<?php echo htmlspecialchars(number_format($totals['tax'], 2)); ?></span>
                        </div>
                        
                        <div class="summary-row total">
                            <span>Total</span>
                            <span>$<?php echo htmlspecialchars(number_format($totals['total'], 2)); ?></span>
                        </div>
                    </div>

                    <button type="button" id="submit-button" class="btn-checkout">
                        <i class="fas fa-lock"></i>
                        <span class="btn-text">Completar Compra</span>
                        <span class="btn-loading" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                            Procesando...
                        </span>
                    </button>

                    <div class="security-badges">
                        <div class="security-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Pago 100% Seguro</span>
                        </div>
                        <div class="security-item">
                            <i class="fas fa-lock"></i>
                            <span>SSL Encriptado</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Back to Shopping Link -->
    <div class="back-to-shopping">
        <a href="<?php echo $baseUrl; ?>/views/client/cart.php">
            <i class="fas fa-arrow-left"></i>
            Volver al Carrito
        </a>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 Iniciando configuración de Stripe...');
        
        // Elementos del DOM
        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit-button');
        const cardErrors = document.getElementById('card-errors');
        const paymentStatus = document.getElementById('payment-status');
        const btnText = submitButton.querySelector('.btn-text');
        const btnLoading = submitButton.querySelector('.btn-loading');
        const debugInfo = document.getElementById('debug-info');
        
        // Verificar si Stripe está disponible
        if (typeof Stripe === 'undefined') {
            console.error('❌ Stripe no está disponible');
            showError('No se pudo cargar el sistema de pagos. Por favor, recarga la página o contacta al administrador.');
            submitButton.disabled = true;
            return;
        }
        
        console.log('✅ Stripe cargado correctamente');
        
        // Obtener la clave pública de Stripe
        const stripePublishableKey = '<?php echo $stripePublishableKey; ?>';
        console.log('🔑 Clave de Stripe:', stripePublishableKey ? 'Configurada' : 'NO CONFIGURADA');
        
        if (!stripePublishableKey || stripePublishableKey.trim() === '') {
            console.error('❌ Clave pública de Stripe no configurada');
            showError('La configuración de pagos no está completa. Por favor, contacta al administrador.');
            submitButton.disabled = true;
            return;
        }
        
        // Inicializar Stripe con la clave publicable
        let stripe, elements, cardElement;
        
        try {
            stripe = Stripe(stripePublishableKey);
            elements = stripe.elements();
            console.log('✅ Stripe inicializado correctamente');
        } catch (error) {
            console.error('❌ Error inicializando Stripe:', error);
            showError('Error al inicializar el sistema de pagos: ' + error.message);
            submitButton.disabled = true;
            return;
        }
        
        // Crear el elemento de tarjeta con estilos mejorados
        try {
            cardElement = elements.create('card', {
                style: {
                    base: {
                        iconColor: '#8a56e2',
                        color: '#333',
                        fontWeight: '500',
                        fontFamily: 'Poppins, -apple-system, BlinkMacSystemFont, sans-serif',
                        fontSize: '16px',
                        fontSmoothing: 'antialiased',
                        '::placeholder': {
                            color: '#aab7c4',
                        },
                        ':-webkit-autofill': {
                            color: '#333',
                        },
                    },
                    invalid: {
                        iconColor: '#ff5a5a',
                        color: '#ff5a5a',
                    },
                    complete: {
                        iconColor: '#56e2c6',
                    },
                },
                hidePostalCode: true
            });
            console.log('✅ Elemento de tarjeta creado');
        } catch (error) {
            console.error('❌ Error creando elemento de tarjeta:', error);
            showError('Error al crear el formulario de tarjeta: ' + error.message);
            submitButton.disabled = true;
            return;
        }
        
        // Montar el elemento de tarjeta en el DOM
        try {
            const cardElementContainer = document.getElementById('card-element');
            if (!cardElementContainer) {
                throw new Error('Contenedor del elemento de tarjeta no encontrado');
            }
            
            cardElement.mount('#card-element');
            console.log('✅ Elemento de tarjeta montado en el DOM');
            
            // Agregar clase para indicar que está listo
            cardElementContainer.classList.add('stripe-ready');
            
        } catch (error) {
            console.error('❌ Error montando elemento de tarjeta:', error);
            showError('Error al mostrar el formulario de tarjeta: ' + error.message);
            submitButton.disabled = true;
            return;
        }
        
        // Manejar errores de validación en tiempo real
        cardElement.on('change', function(event) {
            console.log('💳 Estado de la tarjeta:', event);
            
            if (event.error) {
                showError(event.error.message);
            } else {
                clearError();
            }
            
            // Actualizar estado visual del elemento
            const cardElementContainer = document.querySelector('.card-element-container');
            if (event.complete) {
                cardElementContainer.classList.add('complete');
                console.log('✅ Tarjeta completa y válida');
            } else {
                cardElementContainer.classList.remove('complete');
            }
        });
        
        // Manejar cuando el elemento está listo
        cardElement.on('ready', function() {
            console.log('✅ Elemento de tarjeta listo para usar');
            // Ocultar información de debug después de 5 segundos
            setTimeout(() => {
                if (debugInfo) {
                    debugInfo.style.display = 'none';
                }
            }, 5000);
        });
        
        // Validación de formulario en tiempo real
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            field.addEventListener('blur', validateField);
            field.addEventListener('input', function() {
                if (this.value.trim()) {
                    clearFieldError(this);
                }
            });
        });
        
        // Manejar el clic en el botón de envío
        submitButton.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🔄 Iniciando proceso de pago...');
            
            // Validar el formulario antes de procesar el pago
            if (!validateForm()) {
                console.log('❌ Validación de formulario falló');
                return;
            }
            
            // Mostrar estado de carga
            setLoading(true);
            
            // Crear token con Stripe
            console.log('🔄 Creando token con Stripe...');
            stripe.createToken(cardElement).then(function(result) {
                if (result.error) {
                    console.error('❌ Error creando token:', result.error);
                    // Mostrar error y habilitar el botón nuevamente
                    showError(result.error.message);
                    setLoading(false);
                } else {
                    console.log('✅ Token creado exitosamente:', result.token.id);
                    // Añadir el token al formulario
                    const hiddenInput = document.createElement('input');
                    hiddenInput.setAttribute('type', 'hidden');
                    hiddenInput.setAttribute('name', 'stripeToken');
                    hiddenInput.setAttribute('value', result.token.id);
                    form.appendChild(hiddenInput);
                    
                    console.log('🚀 Enviando formulario...');
                    // Enviar el formulario
                    form.submit();
                }
            }).catch(function(error) {
                console.error('❌ Error inesperado con Stripe:', error);
                showError('Ocurrió un error al procesar tu pago. Por favor, intenta de nuevo.');
                setLoading(false);
            });
        });
        
        // Función para validar un campo individual
        function validateField() {
            const field = this;
            const value = field.value.trim();
            let isValid = true;
            let errorMessage = '';
            
            if (field.hasAttribute('required') && !value) {
                isValid = false;
                errorMessage = 'Este campo es requerido.';
            } else if (field.type === 'email' && value && !isValidEmail(value)) {
                isValid = false;
                errorMessage = 'Por favor, ingresa un email válido.';
            } else if (field.type === 'tel' && value && !isValidPhone(value)) {
                isValid = false;
                errorMessage = 'Por favor, ingresa un teléfono válido.';
            }
            
            if (isValid) {
                clearFieldError(field);
            } else {
                showFieldError(field, errorMessage);
            }
            
            return isValid;
        }
        
        // Función para validar el formulario completo
        function validateForm() {
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!validateField.call(field)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                showError('Por favor, completa todos los campos requeridos correctamente.');
                // Hacer scroll al primer campo con error
                const firstError = form.querySelector('.form-group.error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
            
            return isValid;
        }
        
        // Funciones de validación
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        function isValidPhone(phone) {
            const phoneRegex = /^[\+]?[0-9\s\-()]{10,}$/;
            return phoneRegex.test(phone);
        }
        
        // Funciones para mostrar/limpiar errores de campo
        function showFieldError(field, message) {
            const formGroup = field.closest('.form-group');
            const errorElement = formGroup.querySelector('.error-message');
            
            formGroup.classList.add('error');
            field.classList.add('error');
            errorElement.textContent = message;
        }
        
        function clearFieldError(field) {
            const formGroup = field.closest('.form-group');
            const errorElement = formGroup.querySelector('.error-message');
            
            formGroup.classList.remove('error');
            field.classList.remove('error');
            errorElement.textContent = '';
        }
        
        // Función para mostrar errores generales
        function showError(message) {
            console.error('💥 Error:', message);
            cardErrors.textContent = message;
            paymentStatus.textContent = message;
            paymentStatus.className = 'error';
            paymentStatus.style.display = 'block';
        }
        
        // Función para limpiar errores generales
        function clearError() {
            cardErrors.textContent = '';
            paymentStatus.textContent = '';
            paymentStatus.style.display = 'none';
        }
        
        // Función para mostrar/ocultar estado de carga
        function setLoading(isLoading) {
            if (isLoading) {
                submitButton.disabled = true;
                submitButton.classList.add('processing');
                btnText.style.display = 'none';
                btnLoading.style.display = 'inline-flex';
                document.querySelector('.checkout-form').classList.add('loading');
            } else {
                submitButton.disabled = false;
                submitButton.classList.remove('processing');
                btnText.style.display = 'inline-flex';
                btnLoading.style.display = 'none';
                document.querySelector('.checkout-form').classList.remove('loading');
            }
        }
        
        // Mostrar formulario de tarjeta por defecto
        document.getElementById('stripe-card-form').style.display = 'block';
        
        console.log('🎉 Configuración de Stripe completada exitosamente');
    });
    </script>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2024 El Profesor Hernán. Todos los derechos reservados.</p>
            <div class="footer-links">
                <a href="<?php echo $baseUrl; ?>/views/client/home.php">Inicio</a>
                <a href="<?php echo $baseUrl; ?>/views/client/home.php">Cursos</a>
                <a href="<?php echo $baseUrl; ?>/views/client/cart.php">Carrito</a>
            </div>
            <p>Aprende inglés con los mejores cursos online</p>
        </div>
    </footer>
</body>
</html>
