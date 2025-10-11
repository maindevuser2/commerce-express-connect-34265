<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir dependencias
require_once 'controllers/AuthController.php';
require_once 'controllers/EmailController.php';
require_once 'helpers/SecurityHelper.php';

use Controllers\AuthController;
use Controllers\EmailController;
use Helpers\SecurityHelper;

// Redirigir si ya está logueado
if (AuthController::isAuthenticated()) {
    if (AuthController::isAdmin()) {
        header('Location: views/admin/dashboard.php');
    } else {
        header('Location: views/client/home.php');
    }
    exit();
}

// Procesar recuperación de contraseña si es POST y tiene el campo reset_email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_email'])) {
    $authController = new AuthController();
    $authController->resetPassword();
}

// Procesar login si es POST y no tiene reset_email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['reset_email'])) {
    $authController = new AuthController();
    $authController->login();
}

// Generar token CSRF para los formularios
$csrfToken = SecurityHelper::generateCSRFToken();

// Obtener mensaje flash
$flash_message = AuthController::getFlashMessage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - English Learning Platform</title>
    
    <!-- Meta tags de seguridad -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    
    <!-- Preload de recursos críticos -->
    <link rel="preload" href="public/css/styles.css" as="style">
    <link rel="preload" href="public/css/client/auth-login.css" as="style">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="public/css/styles.css">
    <link rel="stylesheet" href="public/css/client/auth-login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" integrity="sha512-Fo3rlrZj/k7ujTnHg4CGR2D7kSs0v4LLanw2qksYuRlEzO+tcaEPQogQ0KaoGN26/zrn20ImR1DfuLWnOo7aBA==" crossorigin="anonymous" referrerpolicy="no-referrer">
</head>
<body>
    <!-- Login Section -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-header">
                    <a href="index.php" class="btn-back" style="display: inline-flex; align-items: center; gap: 8px; color: #000000ff; text-decoration: none; margin-bottom: 20px; font-size: 14px;">
                        <i class="fas fa-arrow-left"></i>
                        Volver al inicio
                    </a>
                    <h1>Bienvenido de Vuelta</h1>
                    <p>Inicia sesión para continuar tu aprendizaje</p>
                </div>
                
                <?php if ($flash_message): ?>
                    <div class="flash-message <?php echo htmlspecialchars($flash_message['type']); ?>">
                        <?php if ($flash_message['type'] === 'success'): ?>
                            <i class="fas fa-check-circle"></i>
                        <?php else: ?>
                            <i class="fas fa-exclamation-triangle"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($flash_message['message']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="auth-form">
                    <form id="loginForm" method="POST" novalidate>
                        <!-- Token CSRF -->
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <div class="input-with-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       placeholder="Ingresa tu email" 
                                       required 
                                       autocomplete="email"
                                       maxlength="255"
                                       pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                            </div>
                            <div class="error-message" id="emailError"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Contraseña</label>
                            <div class="input-with-icon">
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Ingresa tu contraseña" 
                                       required 
                                       autocomplete="current-password"
                                       maxlength="255">
                                <i class="fas fa-eye toggle-password" tabindex="0" role="button" aria-label="Mostrar contraseña"></i>
                            </div>
                            <div class="error-message" id="passwordError"></div>
                        </div>
                        
                        <div class="form-options">
                            <a href="#" class="forgot-password" id="forgotPassword">¿Olvidaste tu contraseña?</a>
                        </div>
                        
                        <button type="submit" class="btn-primary btn-full" id="loginBtn">
                            <span class="loading-spinner" id="loginSpinner"></span>
                            <span id="loginBtnText">Iniciar Sesión</span>
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        <p>¿No tienes una cuenta? <a href="signup.php">Regístrate</a></p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Password Reset Modal -->
    <div class="modal" id="passwordResetModal" role="dialog" aria-labelledby="resetModalTitle" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="resetModalTitle">Recuperar Contraseña</h2>
                <button type="button" class="close-modal" aria-label="Cerrar modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Ingresa tu dirección de email y te enviaremos un enlace seguro para restablecer tu contraseña.</p>
                
                <div id="resetMessages" role="alert" aria-live="polite"></div>
                
                <form id="resetPasswordForm" class="reset-form" method="POST" novalidate>
                    <!-- Token CSRF para reset -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    
                    <div class="form-group">
                        <label for="resetEmail">Email</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope"></i>
                            <input type="email" 
                                   id="resetEmail" 
                                   name="reset_email" 
                                   placeholder="Ingresa tu email" 
                                   required 
                                   autocomplete="email"
                                   maxlength="255"
                                   pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                        </div>
                        <div class="error-message" id="resetEmailError"></div>
                    </div>
                    <button type="submit" class="btn-primary" id="resetSubmitBtn">
                        <span class="loading-spinner" id="resetSpinner"></span>
                        <span id="resetBtnText">Enviar Enlace de Recuperación</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" aria-hidden="true">
        <div class="spinner"></div>
        <p>Procesando...</p>
    </div>
    
    <!-- Scripts -->
    <script src="auth/login.js" defer></script>
    <script>
        // Configuración de seguridad para JavaScript
        window.APP_CONFIG = {
            maxLoginAttempts: 5,
            lockoutDuration: 15, // minutos
            csrfToken: '<?php echo htmlspecialchars($csrfToken); ?>'
        };
    </script>

</body>
</html>
